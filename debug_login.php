<?php
declare(strict_types=1);

// ONE-OFF LOGIN DIAGNOSTIC — delete this file after use.
// This file is an UNAUTHENTICATED password-reset endpoint. DELETE AFTER USE.

require __DIR__ . '/src/db.php';
require __DIR__ . '/src/auth.php';

header('Content-Type: text/html; charset=utf-8');

$pdo   = ny_db();
$email = strtolower(trim((string)($_POST['email'] ?? $_GET['email'] ?? 'robert.vajdik@gmail.com')));
$pass  = (string)($_POST['password'] ?? '');

/* --------------------------------------------------------------------- */
/*  Optional reset                                                       */
/* --------------------------------------------------------------------- */
$resetMsg = '';
if (isset($_GET['reset']) && !empty($_POST['new_pw'])) {
    $rid = $pdo->prepare('SELECT id FROM ny_users WHERE email = ? LIMIT 1');
    $rid->execute([$email]);
    $id = (int)$rid->fetchColumn();
    if ($id) {
        $pdo->prepare('UPDATE ny_users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash((string)$_POST['new_pw'], PASSWORD_DEFAULT), $id]);
        $resetMsg = "Password reset for user id $id (bcrypt). Try logging in now.";
    } else {
        $resetMsg = "No row found for $email — cannot reset.";
    }
    $pass = '';
}

/* --------------------------------------------------------------------- */
/*  Self-test: build a fresh WordPress-format $P$ hash locally, then     */
/*  verify it with ny_verify_password(). If this FAILS, our verifier is  */
/*  broken. If it PASSES, the algorithm is correct — a real user's       */
/*  failed login must be a wrong-password / wrong-row issue.             */
/* --------------------------------------------------------------------- */
function debug_phpass_hash(string $password, string $salt, int $log2 = 13): string {
    $itoa64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    $count  = 1 << $log2;
    $h = md5($salt . $password, true);
    do { $h = md5($h . $password, true); } while (--$count);
    return '$P$' . $itoa64[$log2] . $salt . ny_phpass_encode64($h, 16);
}
$selfPassword = 'Test-Pa55!';
$selfSalt     = 'abcdefgh';
$selfHash     = debug_phpass_hash($selfPassword, $selfSalt);
$selfOk       = ny_verify_password($selfPassword, $selfHash);
$selfWrong    = ny_verify_password($selfPassword . 'x', $selfHash);

/* --------------------------------------------------------------------- */
/*  DB row                                                               */
/* --------------------------------------------------------------------- */
$stmt = $pdo->prepare('SELECT id, email, display_name, password_hash, is_guest, is_admin, legacy_wp_id FROM ny_users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$u = $stmt->fetch();

/* --------------------------------------------------------------------- */
/*  UI                                                                   */
/* --------------------------------------------------------------------- */
?>
<!doctype html><meta charset="utf-8"><title>Login debug</title>
<style>
body{font-family:ui-monospace,Menlo,Consolas,monospace;padding:24px;max-width:960px;margin:0 auto;background:#f9f6f0;color:#222}
h1,h2,h3{font-family:system-ui,sans-serif}
h2{border-top:1px solid #ddd;padding-top:18px;margin-top:24px}
pre{background:#fff;padding:12px;border:1px solid #e0d8c8;border-radius:6px;overflow:auto;font-size:12px}
.ok{color:#2c7a3d;font-weight:bold}.bad{color:#b12626;font-weight:bold}.warn{color:#8a5a12;font-weight:bold}
input,button{font:14px system-ui,sans-serif;padding:8px 10px;border:1px solid #c9bfae;border-radius:4px}
button{background:#5a3a22;color:#fff;border-color:#5a3a22;cursor:pointer}
table{border-collapse:collapse;width:100%;margin:8px 0}th,td{border:1px solid #e0d8c8;padding:6px 10px;text-align:left;font-size:13px}
th{background:#efe8dc}
</style>
<h1>Login diagnostic</h1>
<?php if ($resetMsg): ?>
    <div style="background:#dff5d8;padding:12px;border-radius:6px;margin:12px 0"><?= htmlspecialchars($resetMsg) ?></div>
<?php endif; ?>

<h2>0. Verifier self-test (algorithm sanity check)</h2>
<p>Built a fresh <code>$P$</code> hash from a known password/salt in this same request, then asked <code>ny_verify_password()</code> to verify it.</p>
<ul>
    <li>Correct password verifies: <?= $selfOk ? '<span class="ok">PASS ✓</span>' : '<span class="bad">FAIL ✗ — verifier is broken, fix code before blaming password</span>' ?></li>
    <li>Wrong password rejects: <?= !$selfWrong ? '<span class="ok">PASS ✓</span>' : '<span class="bad">FAIL ✗</span>' ?></li>
    <li>Generated hash: <code><?= htmlspecialchars($selfHash) ?></code></li>
</ul>

<h2>1. DB row for <?= htmlspecialchars($email) ?></h2>
<?php if (!$u): ?>
    <p class="bad">✗ No row found. Import may have skipped this email, or it differs (case/whitespace).</p>
    <?php
    $near = $pdo->prepare("SELECT id, email FROM ny_users WHERE email LIKE ? LIMIT 10");
    $near->execute(['%' . strtok($email, '@') . '%']);
    $rows = $near->fetchAll();
    if ($rows) echo '<p class="warn">Similar rows:</p><pre>' . htmlspecialchars(print_r($rows, true)) . '</pre>';
    ?>
<?php else: ?>
    <table>
    <tr><th>id</th><td><?= (int)$u['id'] ?></td></tr>
    <tr><th>email</th><td><?= htmlspecialchars((string)$u['email']) ?></td></tr>
    <tr><th>display_name</th><td><?= htmlspecialchars((string)$u['display_name']) ?></td></tr>
    <tr><th>is_guest</th><td><?= (int)$u['is_guest'] ?> <?= (int)$u['is_guest']===0?'<span class="ok">OK</span>':'<span class="bad">BLOCKS LOGIN — guests have no password</span>' ?></td></tr>
    <tr><th>is_admin</th><td><?= (int)$u['is_admin'] ?></td></tr>
    <tr><th>legacy_wp_id</th><td><?= htmlspecialchars((string)($u['legacy_wp_id'] ?? '')) ?></td></tr>
    <tr><th>hash prefix</th><td><code><?= htmlspecialchars(substr((string)$u['password_hash'],0,4)) ?></code></td></tr>
    <tr><th>hash length</th><td><?= strlen((string)$u['password_hash']) ?> <?= strlen((string)$u['password_hash']) > 0 ? '' : '<span class="bad">EMPTY — blocks login</span>' ?></td></tr>
    <tr><th>hash (full)</th><td><code><?= htmlspecialchars((string)$u['password_hash']) ?></code></td></tr>
    </table>
<?php endif; ?>

<h2>2. Test a password</h2>
<form method="post">
    <input type="hidden" name="email" value="<?= htmlspecialchars($email, ENT_QUOTES) ?>">
    <p><label>E-mail: <input type="email" name="email" value="<?= htmlspecialchars($email, ENT_QUOTES) ?>" size="40" style="width:100%"></label></p>
    <p><label>Password to test: <input type="text" name="password" value="<?= htmlspecialchars($pass, ENT_QUOTES) ?>" size="40" style="width:100%"></label>
        <br><small>(Shown in plaintext so you can visually inspect for stray whitespace/characters. Test then close this page.)</small></p>
    <p><button type="submit">Run all verification tests</button></p>
</form>

<?php if ($u && $pass !== ''):
    $hash = (string)$u['password_hash'];

    // Byte-level inspection of the input password.
    $bytes = [];
    for ($i = 0, $n = strlen($pass); $i < $n; $i++) {
        $bytes[] = sprintf('%02x', ord($pass[$i]));
    }

    // Try multiple variants that commonly rescue wrong-locking scenarios.
    $variants = [
        'as-typed'                  => $pass,
        'trim()'                    => trim($pass),
        'no leading/trailing NBSP'  => trim($pass, " \t\n\r\0\x0B\xC2\xA0"),
        'stripslashes()'            => stripslashes($pass),
        'html_entity_decode()'      => html_entity_decode($pass, ENT_QUOTES, 'UTF-8'),
        'utf8_encode ISO→UTF8'      => function_exists('mb_convert_encoding') ? mb_convert_encoding($pass, 'UTF-8', 'ISO-8859-1') : $pass,
    ];
?>
    <h2>3. Byte-level inspection of entered password</h2>
    <ul>
        <li>length (bytes): <?= strlen($pass) ?></li>
        <li>length (chars, mb_strlen UTF-8): <?= mb_strlen($pass, 'UTF-8') ?></li>
        <li>hex bytes: <code><?= implode(' ', $bytes) ?></code></li>
        <li>contains leading/trailing whitespace: <?= trim($pass) !== $pass ? '<span class="bad">YES — this is almost certainly the problem</span>' : '<span class="ok">no</span>' ?></li>
    </ul>

    <h2>4. Verify: <code>ny_verify_password()</code> — what login.php actually calls</h2>
    <?php $ok = ny_verify_password($pass, $hash); ?>
    <p><?= $ok ? '<span class="ok">✓ PASS — this password SHOULD log you in. If it doesn\'t, the bug is elsewhere in login.php or session handling.</span>' : '<span class="bad">✗ FAIL — verifier rejects this password against the stored hash.</span>' ?></p>

    <h2>5. Try common variants</h2>
    <table>
        <tr><th>Variant</th><th>Length</th><th>Result</th><th>Value</th></tr>
        <?php foreach ($variants as $label => $val):
            $r = ny_verify_password($val, $hash); ?>
            <tr>
                <td><?= htmlspecialchars($label) ?></td>
                <td><?= strlen($val) ?></td>
                <td><?= $r ? '<span class="ok">PASS</span>' : '<span class="bad">fail</span>' ?></td>
                <td><code><?= htmlspecialchars($val) ?></code></td>
            </tr>
        <?php endforeach; ?>
    </table>

    <?php if (str_starts_with($hash, '$P$') || str_starts_with($hash, '$H$')): ?>
        <h2>6. Manual PHPass rehash trace</h2>
        <?php
        $itoa64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        $log2 = strpos($itoa64, $hash[3]);
        $count = 1 << (int)$log2;
        $salt = substr($hash, 4, 8);
        $h = md5($salt . $pass, true);
        $c = $count;
        do { $h = md5($h . $pass, true); } while (--$c);
        $computed = substr($hash, 0, 12) . ny_phpass_encode64($h, 16);
        ?>
        <ul>
            <li>iterations: <?= (int)$count ?></li>
            <li>salt: <code><?= htmlspecialchars($salt) ?></code></li>
            <li>expected: <code><?= htmlspecialchars($hash) ?></code></li>
            <li>computed: <code><?= htmlspecialchars($computed) ?></code></li>
            <li>match: <?= hash_equals($computed, $hash) ? '<span class="ok">YES</span>' : '<span class="bad">NO — password is not what created this hash</span>' ?></li>
        </ul>
    <?php endif; ?>
<?php endif; ?>

<?php if ($u): ?>
    <h2>7. Emergency reset</h2>
    <p class="warn">Writes a bcrypt hash directly to the DB. Use only if the manual trace fails and you accept you've lost the original password.</p>
    <form method="post" action="?reset=1">
        <input type="hidden" name="email" value="<?= htmlspecialchars($email, ENT_QUOTES) ?>">
        <p><label>New password: <input type="text" name="new_pw" size="40" style="width:100%"></label></p>
        <p><button type="submit">Set new password</button></p>
    </form>
<?php endif; ?>

<p style="margin-top:32px;color:#a55;font-weight:bold">Delete this file after use — it lets anyone reset any password.</p>
