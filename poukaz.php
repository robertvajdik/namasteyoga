<?php
declare(strict_types=1);

require __DIR__ . '/src/layout.php';

$s        = ny_settings_all();
$email    = $s['email'];
$phone    = $s['phone'];
$account  = trim((string)($s['bank_account_number'] ?? ''));
$iban     = trim((string)($s['bank_iban'] ?? ''));
$holder   = trim((string)($s['bank_holder'] ?? '')) ?: (string)($s['site_name'] ?? '');
$validity = max(1, (int)($s['voucher_validity_months'] ?? 2));

/**
 * Build a SPAYD (Short Payment Descriptor) string – the Czech QR platba format.
 * Amount is optional; when null the QR still works for a free-form transfer.
 */
function ny_spayd(string $iban, ?float $amount, string $msg): string {
    $iban = preg_replace('/\s+/', '', strtoupper($iban));
    $parts = ['SPD*1.0*ACC:' . $iban];
    if ($amount !== null && $amount > 0) {
        $parts[] = 'AM:' . number_format($amount, 2, '.', '');
    }
    $parts[] = 'CC:CZK';
    $clean = preg_replace('/[^A-Za-z0-9 ěščřžýáíéúůťďňŮÁÉÍÓÚÝŽŠČŘĎŤŇ.,\-]/u', '', $msg);
    if ($clean !== '') {
        $parts[] = 'MSG:' . mb_substr($clean, 0, 60);
    }
    return implode('*', $parts);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ny_csrf_check($_POST['csrf'] ?? null);
    $name     = trim((string)($_POST['name'] ?? ''));
    $from     = strtolower(trim((string)($_POST['email'] ?? '')));
    $amount   = trim((string)($_POST['amount'] ?? ''));
    $forWhom  = trim((string)($_POST['for_whom'] ?? ''));
    $msg      = trim((string)($_POST['message'] ?? ''));
    $captcha  = trim((string)($_POST['captcha'] ?? ''));
    $hp       = trim((string)($_POST['website'] ?? ''));

    if ($hp !== '') {
        ny_flash_set('ok', 'Děkujeme, poukaz jsme přijali. Ozveme se vám na e-mail.');
        ny_redirect('poukaz.php#objednavka');
    }

    if (!ny_captcha_verify('poukaz', $captcha)) {
        ny_flash_set('err', 'Kontrolní součet nesouhlasí. Zkuste to prosím znovu.');
    } elseif (!ny_recaptcha_verify($_POST['g-recaptcha-response'] ?? null, 'poukaz')) {
        ny_flash_set('err', 'Ochrana proti robotům selhala, zkuste to prosím znovu.');
    } elseif ($name === '' || !filter_var($from, FILTER_VALIDATE_EMAIL) || $amount === '') {
        ny_flash_set('err', 'Vyplňte prosím jméno, platný e-mail a hodnotu poukazu.');
    } else {
        $amountCzk = (int)preg_replace('/[^0-9]/', '', $amount);
        try {
            $voucherId = ny_voucher_create_from_order([
                'buyer_name'  => $name,
                'buyer_email' => $from,
                'for_whom'    => $forWhom,
                'amount_czk'  => $amountCzk,
                'amount_raw'  => $amount,
                'message'     => $msg,
            ]);
        } catch (Throwable $e) {
            $voucherId = 0;
        }
        $subject = 'Dárkový poukaz – objednávka od ' . $name;
        $body    = "Objednatel: $name\r\nE-mail: $from\r\n"
                 . 'Hodnota poukazu: ' . $amount . "\r\n"
                 . ($forWhom !== '' ? "Poukaz pro: $forWhom\r\n" : '')
                 . ($voucherId ? 'Interní ID: #' . $voucherId . "\r\n" : '')
                 . "\r\nZpráva:\r\n" . ($msg !== '' ? $msg : '(bez zprávy)') . "\r\n";
        ny_mail($email, $subject, $body, ['reply_to' => $from]);
        ny_flash_set('ok', 'Děkujeme! Objednávku jsme přijali a ozveme se vám na e-mail s platebními údaji.');
    }
    ny_redirect('poukaz.php#objednavka');
}

$captcha = ny_captcha_generate('poukaz');
$user    = ny_current_user();

ny_render_header('Dárkový poukaz', 'poukaz');
?>
<section class="poukaz-hero">
    <div class="poukaz-hero-copy">
        <div class="eyebrow">Namasté yoga studio</div>
        <h1 class="poukaz-hero-title">Dárkový poukaz</h1>
        <p class="poukaz-hero-lead"><em>Move and relax beautifully.</em></p>
        <p class="poukaz-hero-sub">
            Darujte blízkým hodinu klidu, pohybu a péče o sebe. Poukaz platí na kteroukoli
            lekci jógy, pilates, individuální trénink nebo masáž ve studiu Namasté.
        </p>
        <div class="poukaz-hero-cta">
            <a class="btn btn-primary" href="#objednavka">Objednat poukaz</a>
            <a class="btn btn-secondary" href="#varianty">Zobrazit varianty</a>
        </div>
    </div>
    <figure class="poukaz-hero-visual">
        <img src="assets/darkovy-poukaz.jpg" alt="Ukázka dárkového poukazu Namasté yoga studio" loading="lazy">
    </figure>
</section>

<section class="poukaz-variants" id="varianty">
    <div class="poukaz-variants-head">
        <div class="eyebrow">Vyberte hodnotu</div>
        <h2>Poukaz na míru</h2>
        <p>Nabízíme doporučené varianty, poukaz ale rádi vystavíme na jakoukoli částku nebo konkrétní službu.</p>
    </div>
    <div class="poukaz-variants-grid">
        <article class="poukaz-variant">
            <div class="poukaz-variant-badge">Ochutnávka</div>
            <div class="poukaz-variant-price">500 Kč</div>
            <p>Jednorázová skupinová lekce dle výběru – jóga, pilates nebo yin.</p>
            <a class="btn btn-ghost btn-sm" href="#objednavka" data-preset="500 Kč (Ochutnávka)">Vybrat</a>
        </article>
        <article class="poukaz-variant is-featured">
            <div class="poukaz-variant-badge">Nejoblíbenější</div>
            <div class="poukaz-variant-price">1&nbsp;500 Kč</div>
            <p>Permanentka na 5 vstupů na skupinové lekce dle výběru, s platností 3 měsíce.</p>
            <a class="btn btn-primary btn-sm" href="#objednavka" data-preset="1 500 Kč (Permanentka 5×)">Vybrat</a>
        </article>
        <article class="poukaz-variant">
            <div class="poukaz-variant-badge">Wellness</div>
            <div class="poukaz-variant-price">2&nbsp;000 Kč</div>
            <p>Individuální lekce 1:1 nebo masáž na míru – čas jen pro obdarovaného.</p>
            <a class="btn btn-ghost btn-sm" href="#objednavka" data-preset="2 000 Kč (Individuální / masáž)">Vybrat</a>
        </article>
        <article class="poukaz-variant">
            <div class="poukaz-variant-badge">Vlastní</div>
            <div class="poukaz-variant-price">? Kč</div>
            <p>Poukaz na libovolnou částku. Do zprávy nám napište, na kolik ho vystavit.</p>
            <a class="btn btn-ghost btn-sm" href="#objednavka" data-preset="Vlastní částka">Vybrat</a>
        </article>
    </div>
</section>

<section class="poukaz-how">
    <div class="poukaz-how-head">
        <div class="eyebrow">Jak to funguje</div>
        <h2>Od objednávky k dárku za tři kroky</h2>
    </div>
    <div class="poukaz-how-grid">
        <div class="poukaz-how-step">
            <div class="poukaz-how-num">01</div>
            <h3>Vyplňte formulář</h3>
            <p>Napište nám hodnotu poukazu a pro koho je určený. Odpovíme obratem s platebními údaji.</p>
        </div>
        <div class="poukaz-how-step">
            <div class="poukaz-how-num">02</div>
            <h3>Zaplaťte převodem</h3>
            <p>Po přijetí platby vám poukaz připravíme – v tištěné formě k osobnímu vyzvednutí nebo v PDF k vytištění.</p>
        </div>
        <div class="poukaz-how-step">
            <div class="poukaz-how-num">03</div>
            <h3>Předejte a užijte</h3>
            <p>Obdarovaný si termín rezervuje přes web nebo telefonicky. Platnost poukazu je 2 měsíce od data vystavení.</p>
        </div>
    </div>
</section>

<?php $defaultAmount = 1500.0; $defaultSpayd = $iban !== '' ? ny_spayd($iban, $defaultAmount, 'Darkovy poukaz Namaste') : ''; ?>
<section class="poukaz-payment" id="platba">
    <div class="poukaz-payment-head">
        <div class="eyebrow">Platba</div>
        <h2>Zaplaťte pohodlně převodem nebo QR kódem</h2>
        <p>Naskenujte QR kód v mobilním bankovnictví, nebo použijte údaje níže pro klasický převod.</p>
    </div>
    <div class="poukaz-payment-grid">
        <div class="poukaz-qr">
            <?php if ($iban !== ''): ?>
                <div class="poukaz-qr-box" id="poukaz-qr" data-iban="<?= e($iban) ?>" data-amount="<?= e((string)$defaultAmount) ?>" data-msg="Darkovy poukaz Namaste" data-spayd="<?= e($defaultSpayd) ?>"></div>
                <div class="poukaz-qr-amount">
                    <span>Částka QR platby</span>
                    <strong id="poukaz-qr-amount-lbl"><?= number_format($defaultAmount, 0, ',', ' ') ?> Kč</strong>
                </div>
                <p class="poukaz-qr-hint">Standard SPAYD (Czech QR platba). Funguje v Air Bank, ČSOB, Fio, KB, Raiffeisenbank a dalších.</p>
            <?php else: ?>
                <div class="poukaz-qr-placeholder">
                    QR platba bude k dispozici, jakmile bude vyplněn IBAN v administraci.
                </div>
            <?php endif; ?>
        </div>
        <dl class="poukaz-bank">
            <?php if ($holder !== ''): ?>
                <dt>Majitel účtu</dt><dd><?= e($holder) ?></dd>
            <?php endif; ?>
            <?php if ($account !== ''): ?>
                <dt>Číslo účtu</dt><dd class="mono"><?= e($account) ?></dd>
            <?php endif; ?>
            <?php if ($iban !== ''): ?>
                <dt>IBAN</dt><dd class="mono"><?= e($iban) ?></dd>
            <?php endif; ?>
            <dt>Variabilní symbol</dt><dd class="mono">datum narození obdarovaného <em>(nebo dle domluvy)</em></dd>
            <dt>Zpráva pro příjemce</dt><dd>„Dárkový poukaz Namasté“</dd>
            <dt>Platnost poukazu</dt><dd><?= (int)$validity ?> měsíc<?= $validity >= 5 ? 'ů' : ($validity >= 2 ? 'e' : '') ?> od data vystavení</dd>
        </dl>
    </div>
</section>

<section class="poukaz-order" id="objednavka">
    <div class="poukaz-order-head">
        <div class="eyebrow">Objednávka poukazu</div>
        <h2>Napište nám</h2>
        <p>Pošleme vám zpět potvrzení, platební údaje a domluvíme způsob předání.</p>
    </div>
    <form method="post" class="poukaz-form" data-recaptcha="poukaz" novalidate>
        <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
        <label>Vaše jméno
            <input type="text" name="name" value="<?= e($user ? (string)$user['display_name'] : '') ?>" required>
        </label>
        <label>Váš e-mail
            <input type="email" name="email" value="<?= e($user ? (string)$user['email'] : '') ?>" required>
        </label>
        <label>Hodnota poukazu
            <input type="text" name="amount" id="poukaz-amount" placeholder="např. 1 500 Kč nebo vlastní částka" required>
        </label>
        <label>Poukaz pro (nepovinné)
            <input type="text" name="for_whom" placeholder="Jméno obdarovaného">
        </label>
        <label class="poukaz-form-full">Zpráva (nepovinné)
            <textarea name="message" rows="4" placeholder="Máte přání ohledně věnování, formy předání nebo termínu?"></textarea>
        </label>
        <div class="hp-field" aria-hidden="true">
            <label>Website (nechte prázdné)
                <input type="text" name="website" tabindex="-1" autocomplete="off">
            </label>
        </div>
        <label class="captcha-field poukaz-form-full">Kontrolní otázka: kolik je <?= (int)$captcha['a'] ?> + <?= (int)$captcha['b'] ?>?
            <input type="text" name="captcha" inputmode="numeric" pattern="[0-9]+" autocomplete="off" required>
        </label>
        <button class="btn btn-primary btn-form" type="submit">Odeslat objednávku</button>
    </form>
    <p class="poukaz-order-contact">
        Raději telefonicky? Zavolejte na <a href="tel:<?= e(preg_replace('/\s+/', '', $phone)) ?>"><?= e($phone) ?></a>
        nebo nám napište přímo <?= ny_email_obf($email) ?>.
    </p>
</section>

<?php if ($iban !== ''): ?>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js" defer></script>
<?php endif; ?>
<script>
(function () {
    var amountInput = document.getElementById('poukaz-amount');
    var qrEl        = document.getElementById('poukaz-qr');
    var qrAmountLbl = document.getElementById('poukaz-qr-amount-lbl');
    var qrInstance  = null;

    function parseAmount(s) {
        var m = String(s || '').replace(/[^0-9]/g, '');
        return m ? parseInt(m, 10) : 0;
    }
    function buildSpayd(iban, amount, msg) {
        var parts = ['SPD*1.0*ACC:' + iban.replace(/\s+/g, '').toUpperCase()];
        if (amount > 0) parts.push('AM:' + amount.toFixed(2));
        parts.push('CC:CZK');
        if (msg) parts.push('MSG:' + msg.substring(0, 60));
        return parts.join('*');
    }
    function renderQr(spayd) {
        if (!qrEl) return;
        qrEl.innerHTML = '';
        if (typeof QRCode === 'undefined') return;
        qrInstance = new QRCode(qrEl, {
            text: spayd,
            width: 220,
            height: 220,
            correctLevel: QRCode.CorrectLevel.M
        });
    }
    function updateQr(amount) {
        if (!qrEl) return;
        var iban = qrEl.getAttribute('data-iban') || '';
        var msg  = qrEl.getAttribute('data-msg')  || '';
        if (!iban) return;
        renderQr(buildSpayd(iban, amount, msg));
        if (qrAmountLbl) {
            qrAmountLbl.textContent = amount > 0
                ? amount.toLocaleString('cs-CZ') + ' Kč'
                : 'libovolná částka';
        }
    }

    if (qrEl) {
        var initAmount = parseFloat(qrEl.getAttribute('data-amount') || '0') || 0;
        // Wait for the qrcodejs library to load (it's `defer`red).
        window.addEventListener('load', function () { updateQr(initAmount); });
    }

    document.querySelectorAll('.poukaz-variant [data-preset]').forEach(function (a) {
        a.addEventListener('click', function (ev) {
            var preset = a.getAttribute('data-preset') || '';
            if (amountInput) amountInput.value = preset;
            updateQr(parseAmount(preset));
        });
    });

    if (amountInput) {
        amountInput.addEventListener('input', function () {
            updateQr(parseAmount(amountInput.value));
        });
    }
})();
</script>

<?php ny_render_footer();
