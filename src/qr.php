<?php
declare(strict_types=1);

/**
 * Minimal dependency-free QR code encoder (byte mode, ECC level M,
 * versions 1–15) with a PNG writer that needs only zlib – no GD.
 * Enough for SPAYD payment strings embedded in e-mails.
 */

/** @return array<int, array<int, bool>> square matrix, true = dark module */
function ny_qr_matrix(string $data): array {
    // [total codewords, ec codewords per block, group1 blocks, group1 data cw, group2 blocks, group2 data cw] – level M
    static $tbl = [
        1  => [26, 10, 1, 16, 0, 0],   2  => [44, 16, 1, 28, 0, 0],
        3  => [70, 26, 1, 44, 0, 0],   4  => [100, 18, 2, 32, 0, 0],
        5  => [134, 24, 2, 43, 0, 0],  6  => [172, 16, 4, 27, 0, 0],
        7  => [196, 18, 4, 31, 0, 0],  8  => [242, 22, 2, 38, 2, 39],
        9  => [292, 22, 3, 36, 2, 37], 10 => [346, 26, 4, 43, 1, 44],
        11 => [404, 30, 1, 50, 4, 51], 12 => [466, 22, 6, 36, 2, 37],
        13 => [532, 22, 8, 37, 1, 38], 14 => [581, 24, 4, 40, 5, 41],
        15 => [655, 24, 5, 41, 5, 42],
    ];
    static $align = [
        1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30],
        6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46],
        10 => [6, 28, 50], 11 => [6, 30, 54], 12 => [6, 32, 58], 13 => [6, 34, 62],
        14 => [6, 26, 46, 66], 15 => [6, 26, 48, 70],
    ];

    $len = strlen($data);
    $ver = 0;
    foreach ($tbl as $v => $t) {
        $cap = $t[2] * $t[3] + $t[4] * $t[5];
        $need = 4 + ($v < 10 ? 8 : 16) + 8 * $len;
        if ($need <= $cap * 8) { $ver = $v; break; }
    }
    if ($ver === 0) throw new InvalidArgumentException('QR payload too long.');
    [, $ecLen, $b1, $d1, $b2, $d2] = $tbl[$ver];
    $dataCap = $b1 * $d1 + $b2 * $d2;

    // --- bit stream -------------------------------------------------------
    $bits = '0100' . str_pad(decbin($len), $ver < 10 ? 8 : 16, '0', STR_PAD_LEFT);
    for ($i = 0; $i < $len; $i++) $bits .= str_pad(decbin(ord($data[$i])), 8, '0', STR_PAD_LEFT);
    $bits .= str_repeat('0', min(4, $dataCap * 8 - strlen($bits)));
    if (strlen($bits) % 8) $bits .= str_repeat('0', 8 - strlen($bits) % 8);
    $cw = array_map('bindec', str_split($bits, 8));
    for ($p = 0; count($cw) < $dataCap; $p ^= 1) $cw[] = $p ? 0x11 : 0xEC;

    // --- Reed–Solomon -----------------------------------------------------
    $exp = array_fill(0, 512, 0); $log = array_fill(0, 256, 0);
    for ($i = 0, $x = 1; $i < 255; $i++) {
        $exp[$i] = $x; $log[$x] = $i;
        $x <<= 1; if ($x & 0x100) $x ^= 0x11D;
    }
    for ($i = 255; $i < 512; $i++) $exp[$i] = $exp[$i - 255];
    $gen = [1];
    for ($i = 0; $i < $ecLen; $i++) {
        $next = array_fill(0, count($gen) + 1, 0);
        foreach ($gen as $j => $g) {
            $next[$j] ^= $g;
            if ($g) $next[$j + 1] ^= $exp[$log[$g] + $i];
        }
        $gen = $next;
    }
    $blocks = []; $ecBlocks = []; $off = 0;
    foreach ([[$b1, $d1], [$b2, $d2]] as [$n, $dl]) {
        for ($b = 0; $b < $n; $b++) {
            $blk = array_slice($cw, $off, $dl); $off += $dl;
            $rem = array_merge($blk, array_fill(0, $ecLen, 0));
            for ($i = 0; $i < $dl; $i++) {
                $c = $rem[$i];
                if ($c === 0) continue;
                $lc = $log[$c];
                for ($j = 1; $j <= $ecLen; $j++) $rem[$i + $j] ^= $exp[$log[$gen[$j]] + $lc];
            }
            $blocks[] = $blk;
            $ecBlocks[] = array_slice($rem, $dl);
        }
    }
    $final = [];
    $maxD = max($d1, $d2);
    for ($i = 0; $i < $maxD; $i++) foreach ($blocks as $blk) if (isset($blk[$i])) $final[] = $blk[$i];
    for ($i = 0; $i < $ecLen; $i++) foreach ($ecBlocks as $blk) $final[] = $blk[$i];

    // --- function patterns ------------------------------------------------
    $size = 17 + 4 * $ver;
    $m   = array_fill(0, $size, array_fill(0, $size, false));
    $fn  = array_fill(0, $size, array_fill(0, $size, false));
    $set = static function (int $r, int $c, bool $d) use (&$m, &$fn): void { $m[$r][$c] = $d; $fn[$r][$c] = true; };

    foreach ([[0, 0], [0, $size - 7], [$size - 7, 0]] as [$r0, $c0]) {
        for ($r = -1; $r <= 7; $r++) for ($c = -1; $c <= 7; $c++) {
            $rr = $r0 + $r; $cc = $c0 + $c;
            if ($rr < 0 || $cc < 0 || $rr >= $size || $cc >= $size) continue;
            $in = $r >= 0 && $r <= 6 && $c >= 0 && $c <= 6;
            $dark = $in && ($r === 0 || $r === 6 || $c === 0 || $c === 6 || ($r >= 2 && $r <= 4 && $c >= 2 && $c <= 4));
            $set($rr, $cc, $dark);
        }
    }
    for ($i = 8; $i < $size - 8; $i++) { $set(6, $i, $i % 2 === 0); $set($i, 6, $i % 2 === 0); }
    $ap = $align[$ver]; $na = count($ap);
    foreach ($ap as $ai => $ar) foreach ($ap as $bi => $ac) {
        if (($ai === 0 && $bi === 0) || ($ai === 0 && $bi === $na - 1) || ($ai === $na - 1 && $bi === 0)) continue;
        for ($r = -2; $r <= 2; $r++) for ($c = -2; $c <= 2; $c++) {
            $set($ar + $r, $ac + $c, max(abs($r), abs($c)) !== 1);
        }
    }
    $set($size - 8, 8, true); // dark module
    // reserve format areas
    for ($i = 0; $i < 9; $i++) { if (!$fn[8][$i]) $set(8, $i, false); if (!$fn[$i][8]) $set($i, 8, false); }
    for ($i = 0; $i < 8; $i++) { $set(8, $size - 1 - $i, false); if (!$fn[$size - 1 - $i][8]) $set($size - 1 - $i, 8, false); }
    // version info
    if ($ver >= 7) {
        $rem = $ver;
        for ($i = 0; $i < 12; $i++) $rem = ($rem << 1) ^ ((($rem >> 11) & 1) * 0x1F25);
        $vb = ($ver << 12) | $rem;
        for ($i = 0; $i < 18; $i++) {
            $d = (($vb >> $i) & 1) === 1;
            $a = $size - 11 + $i % 3; $b = intdiv($i, 3);
            $set($a, $b, $d); $set($b, $a, $d);
        }
    }

    // --- data placement ---------------------------------------------------
    $bitIdx = 0; $total = count($final) * 8;
    for ($right = $size - 1; $right >= 1; $right -= 2) {
        if ($right === 6) $right = 5;
        for ($vert = 0; $vert < $size; $vert++) {
            for ($j = 0; $j < 2; $j++) {
                $c = $right - $j;
                $upward = (($right + 1) & 2) === 0;
                $r = $upward ? $size - 1 - $vert : $vert;
                if ($fn[$r][$c]) continue;
                if ($bitIdx < $total) {
                    $m[$r][$c] = (($final[$bitIdx >> 3] >> (7 - ($bitIdx & 7))) & 1) === 1;
                }
                $bitIdx++;
            }
        }
    }

    // --- masking ----------------------------------------------------------
    $maskFn = static function (int $k, int $r, int $c): bool {
        switch ($k) {
            case 0: return ($r + $c) % 2 === 0;
            case 1: return $r % 2 === 0;
            case 2: return $c % 3 === 0;
            case 3: return ($r + $c) % 3 === 0;
            case 4: return (intdiv($r, 2) + intdiv($c, 3)) % 2 === 0;
            case 5: return ($r * $c) % 2 + ($r * $c) % 3 === 0;
            case 6: return (($r * $c) % 2 + ($r * $c) % 3) % 2 === 0;
            default: return (($r + $c) % 2 + ($r * $c) % 3) % 2 === 0;
        }
    };
    $drawFormat = static function (array &$g, int $mask) use ($size): void {
        $d = (0 << 3) | $mask; // level M = 00
        $rem = $d;
        for ($i = 0; $i < 10; $i++) $rem = ($rem << 1) ^ ((($rem >> 9) & 1) * 0x537);
        $b = (($d << 10) | $rem) ^ 0x5412;
        $bit = static fn(int $i): bool => (($b >> $i) & 1) === 1;
        for ($i = 0; $i <= 5; $i++) $g[$i][8] = $bit($i);
        $g[7][8] = $bit(6); $g[8][8] = $bit(7); $g[8][7] = $bit(8);
        for ($i = 9; $i < 15; $i++) $g[8][14 - $i] = $bit($i);
        for ($i = 0; $i < 8; $i++) $g[8][$size - 1 - $i] = $bit($i);
        for ($i = 8; $i < 15; $i++) $g[$size - 15 + $i][8] = $bit($i);
        $g[$size - 8][8] = true;
    };
    $penalty = static function (array $g) use ($size): int {
        $p = 0;
        for ($pass = 0; $pass < 2; $pass++) {
            for ($i = 0; $i < $size; $i++) {
                $run = 1; $line = [];
                for ($j = 0; $j < $size; $j++) $line[] = $pass ? $g[$j][$i] : $g[$i][$j];
                for ($j = 1; $j < $size; $j++) {
                    if ($line[$j] === $line[$j - 1]) { $run++; }
                    else { if ($run >= 5) $p += $run - 2; $run = 1; }
                }
                if ($run >= 5) $p += $run - 2;
                $s = implode('', array_map(static fn($x) => $x ? '1' : '0', $line));
                $p += 40 * (substr_count($s, '10111010000') + substr_count($s, '00001011101'));
            }
        }
        $dark = 0;
        for ($r = 0; $r < $size; $r++) for ($c = 0; $c < $size; $c++) {
            if ($g[$r][$c]) $dark++;
            if ($r < $size - 1 && $c < $size - 1) {
                $v = $g[$r][$c];
                if ($g[$r][$c + 1] === $v && $g[$r + 1][$c] === $v && $g[$r + 1][$c + 1] === $v) $p += 3;
            }
        }
        $p += 10 * intdiv(abs($dark * 20 - $size * $size * 10), $size * $size);
        return $p;
    };

    $best = null; $bestP = PHP_INT_MAX;
    for ($k = 0; $k < 8; $k++) {
        $g = $m;
        for ($r = 0; $r < $size; $r++) for ($c = 0; $c < $size; $c++) {
            if (!$fn[$r][$c] && $maskFn($k, $r, $c)) $g[$r][$c] = !$g[$r][$c];
        }
        $drawFormat($g, $k);
        $pp = $penalty($g);
        if ($pp < $bestP) { $bestP = $pp; $best = $g; }
    }
    return $best;
}

/** Render a QR code as a grayscale PNG (binary string). */
function ny_qr_png(string $data, int $scale = 6, int $quiet = 4): string {
    $mx   = ny_qr_matrix($data);
    $n    = count($mx);
    $px   = ($n + 2 * $quiet) * $scale;
    $raw  = '';
    for ($y = 0; $y < $px; $y++) {
        $r = intdiv($y, $scale) - $quiet;
        $row = "\0";
        for ($x = 0; $x < $px; $x++) {
            $c = intdiv($x, $scale) - $quiet;
            $dark = $r >= 0 && $r < $n && $c >= 0 && $c < $n && $mx[$r][$c];
            $row .= $dark ? "\x00" : "\xFF";
        }
        $raw .= $row;
    }
    $chunk = static function (string $type, string $body): string {
        return pack('N', strlen($body)) . $type . $body . pack('N', crc32($type . $body));
    };
    return "\x89PNG\r\n\x1a\n"
        . $chunk('IHDR', pack('NNCCCCC', $px, $px, 8, 0, 0, 0, 0))
        . $chunk('IDAT', gzcompress($raw, 9))
        . $chunk('IEND', '');
}
