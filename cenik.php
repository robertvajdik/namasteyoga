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
 * SPAYD (Short Payment Descriptor) – the Czech QR platba format.
 * Amount optional; when null the QR still works for a free-form transfer.
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
    $captchaIn = trim((string)($_POST['captcha'] ?? ''));
    $hp       = trim((string)($_POST['website'] ?? ''));

    if ($hp !== '') {
        ny_flash_set('ok', t('poukaz.flash.hp'));
        ny_redirect('cenik.php#objednavka');
    }

    if (!ny_captcha_verify('poukaz', $captchaIn)) {
        ny_flash_set('err', t('poukaz.flash.err.captcha'));
    } elseif (!ny_recaptcha_verify($_POST['g-recaptcha-response'] ?? null, 'poukaz')) {
        ny_flash_set('err', t('poukaz.flash.err.recaptcha'));
    } elseif ($name === '' || !filter_var($from, FILTER_VALIDATE_EMAIL) || $amount === '') {
        ny_flash_set('err', t('poukaz.flash.err.fields'));
    } else {
        $amountCzk = (int)preg_replace('/[^0-9]/', '', $amount);
        $voucherId = 0;
        $voucherRow = null;
        try {
            $voucherId = ny_voucher_create_from_order([
                'buyer_name'  => $name,
                'buyer_email' => $from,
                'for_whom'    => $forWhom,
                'amount_czk'  => $amountCzk,
                'amount_raw'  => $amount,
                'message'     => $msg,
            ]);
            if ($voucherId) {
                $vq = ny_db()->prepare('SELECT * FROM ny_vouchers WHERE id = ? LIMIT 1');
                $vq->execute([$voucherId]);
                $voucherRow = $vq->fetch() ?: null;
            }
        } catch (Throwable $e) {
            $voucherId = 0;
        }

        $adminTo  = ny_admin_notify_email() ?: $email;
        $siteName = $s['site_name'] ?: 'Studio Namasté';
        $base     = ny_base_url();
        $validTxt = $voucherRow && !empty($voucherRow['valid_until'])
            ? (new DateTimeImmutable($voucherRow['valid_until']))->format('j. n. Y')
            : '';
        $vCode    = $voucherRow['code'] ?? '';

        $subject = 'Dárkový poukaz – nová objednávka od ' . $name;
        $lines = [
            'V administraci přistála nová objednávka dárkového poukazu.',
            '',
            'Objednatel:   ' . $name,
            'E-mail:       ' . $from,
            'Hodnota:      ' . $amount . ($amountCzk > 0 ? ' (' . $amountCzk . ' Kč)' : ''),
        ];
        if ($forWhom !== '') $lines[] = 'Poukaz pro:   ' . $forWhom;
        if ($vCode !== '')   $lines[] = 'Kód poukazu:  ' . $vCode;
        if ($validTxt !== '')$lines[] = 'Platnost do:  ' . $validTxt;
        $lines[] = 'Čas:          ' . date('j. n. Y H:i');
        $lines[] = '';
        $lines[] = 'Zpráva od objednatele:';
        $lines[] = $msg !== '' ? $msg : '(bez zprávy)';
        if ($voucherId) {
            $lines[] = '';
            $lines[] = 'Správa v adminu:';
            $lines[] = $base . '/admin/vouchers.php?action=edit&id=' . $voucherId;
            if ($vCode !== '') {
                $lines[] = 'Náhled poukazu k tisku:';
                $lines[] = $base . '/voucher.php?code=' . rawurlencode((string)$vCode);
            }
        }
        $lines[] = '';
        $lines[] = '-- ';
        $lines[] = $siteName;
        $body = implode("\r\n", $lines) . "\r\n";

        if ($adminTo !== '') {
            ny_mail($adminTo, $subject, $body, ['reply_to' => $from]);
        }
        ny_flash_set('ok', t('poukaz.flash.ok'));
    }
    ny_redirect('cenik.php#objednavka');
}

$captcha = ny_captcha_generate('poukaz');
$user    = ny_current_user();

ny_render_header(t('cenik.title'), 'cenik', ['description' => t('cenik.meta.description')]);
?>
<section class="page-hero-media page-hero-media--bg" style="background-image: url('assets/banners/cenik_namasteyoga.cz.png');" role="img" aria-label="<?= e(t('cenik.hero.title')) ?>">
    <div class="page-hero-media-body">
        <div class="eyebrow"><?= e(t('cenik.hero.eyebrow')) ?></div>
        <h1 class="page-title"><?= e(t('cenik.hero.title')) ?></h1>
        <p class="page-lead page-lead--start">
            <?= e(t('cenik.hero.lead')) ?>
        </p>
    </div>
</section>

<?php
$openField = function(int $i, string $field) use ($s) {
    $o = trim((string)($s['cenik_open_' . $i . '_' . $field] ?? ''));
    return $o !== '' ? $o : t('cenik.open.' . $i . '.' . $field);
};
/**
 * Build the card description. For passes (cards 2/3) with an "attempts" setting,
 * the per-lesson price is derived from amount ÷ attempts and appended to the
 * validity sentence; otherwise falls back to the admin override or the raw .desc translation.
 */
$openDesc = function(int $i) use ($s, $openField) {
    $attempts = (int) preg_replace('/[^0-9]/', '', (string)($s['cenik_open_' . $i . '_attempts'] ?? ''));
    $amountN  = (int) preg_replace('/[^0-9]/', '', $openField($i, 'amount'));
    if ($attempts > 0 && $amountN > 0) {
        $perLesson = (int) round($amountN / $attempts);
        $validity  = trim(t('cenik.open.' . $i . '.validity'));
        $suffix    = sprintf(t('cenik.open.per_lesson'), number_format($perLesson, 0, ',', ' '));
        return trim($validity . ' ' . $suffix);
    }
    return $openField($i, 'desc');
};
?>
<h2 class="section-h"><?= e(t('cenik.open.title')) ?></h2>
<div class="price-grid price-grid-4">
    <?php for ($i = 1; $i <= 6; $i++): ?>
        <article class="price-card<?= $i === 2 ? ' featured' : '' ?>">
            <div class="price-eyebrow"><?= e($openField($i, 'eyebrow')) ?></div>
            <h3><?= e($openField($i, 'title')) ?></h3>
            <div class="price-amount"><?= e($openField($i, 'amount')) ?></div>
            <p><?= e($openDesc($i)) ?></p>
        </article>
    <?php endfor; ?>
</div>

<?php
$individField = function(int $i, string $field) use ($s) {
    $o = trim((string)($s['individ_prices_' . $i . '_' . $field] ?? ''));
    return $o !== '' ? $o : t('cenik.individ.' . $i . '.' . $field);
};
?>
<h2 class="section-h section-h-gap"><?= e(t('cenik.individ.title')) ?></h2>
<div class="price-grid">
    <?php for ($i = 1; $i <= 3; $i++): ?>
        <article class="price-card">
            <h3><?= e($individField($i, 'title')) ?></h3>
            <div class="price-amount"><?= e($individField($i, 'amount')) ?></div>
        </article>
    <?php endfor; ?>
</div>

<?php
$massageField = function(int $i, string $field) use ($s) {
    $settingKey = 'cenik_massage_' . $i . '_' . ($field === 'price' ? 'price' : $field);
    $o = trim((string)($s[$settingKey] ?? ''));
    return $o !== '' ? $o : t('cenik.massage.' . $i . '.' . $field);
};
?>
<h2 class="section-h section-h-gap"><?= e(t('cenik.massage.title')) ?></h2>
<div class="tbl-wrap">
    <table class="tbl">
        <thead><tr><th><?= e(t('cenik.massage.th.name')) ?></th><th><?= e(t('cenik.massage.th.duration')) ?></th><th><?= e(t('cenik.massage.th.price')) ?></th></tr></thead>
        <tbody>
            <?php for ($i = 1; $i <= 5; $i++): ?>
                <tr>
                    <td data-label="<?= e(t('cenik.massage.th.name')) ?>"><?= e($massageField($i, 'name')) ?></td>
                    <td data-label="<?= e(t('cenik.massage.th.duration')) ?>"><?= e($massageField($i, 'duration')) ?></td>
                    <td data-label="<?= e(t('cenik.massage.th.price')) ?>"><?= e($massageField($i, 'price')) ?></td>
                </tr>
            <?php endfor; ?>
        </tbody>
    </table>
</div>

<p class="hint hint-form">
    <?= t('cenik.discount.note') ?>
</p>

<section class="poukaz-hero" id="poukaz">
    <div class="poukaz-hero-copy">
        <div class="eyebrow"><?= e(t('poukaz.hero.eyebrow')) ?></div>
        <h2 class="poukaz-hero-title"><?= e(t('poukaz.hero.title')) ?></h2>
        <p class="poukaz-hero-lead"><em><?= e(t('poukaz.hero.tagline')) ?></em></p>
        <p class="poukaz-hero-sub">
            <?= e(t('poukaz.hero.sub')) ?>
        </p>
        <div class="poukaz-hero-cta">
            <a class="btn btn-primary" href="#objednavka"><?= e(t('poukaz.hero.cta.order')) ?></a>
            <a class="btn btn-secondary" href="#varianty"><?= e(t('poukaz.hero.cta.variants')) ?></a>
        </div>
    </div>
    <figure class="poukaz-hero-visual">
        <img src="assets/darkovy-poukaz.jpg" alt="<?= e(t('poukaz.hero.image.alt')) ?>" loading="lazy">
    </figure>
</section>

<section class="poukaz-variants" id="varianty">
    <div class="poukaz-variants-head">
        <div class="eyebrow"><?= e(t('poukaz.variants.eyebrow')) ?></div>
        <h2><?= e(t('poukaz.variants.title')) ?></h2>
        <p><?= e(t('poukaz.variants.lead')) ?></p>
    </div>
    <?php
    $variantPrice = function(int $i) use ($s) {
        $o = trim((string)($s['poukaz_variant_' . $i . '_price'] ?? ''));
        return $o !== '' ? $o : t('poukaz.variant.' . $i . '.price');
    };
    ?>
    <div class="poukaz-variants-grid">
        <article class="poukaz-variant">
            <div class="poukaz-variant-badge"><?= e(t('poukaz.variant.1.badge')) ?></div>
            <div class="poukaz-variant-price"><?= $variantPrice(1) ?></div>
            <p><?= e(t('poukaz.variant.1.desc')) ?></p>
            <a class="btn btn-ghost btn-sm" href="#objednavka" data-preset="<?= e(t('poukaz.preset.1')) ?>"><?= e(t('poukaz.variant.select')) ?></a>
        </article>
        <article class="poukaz-variant is-featured">
            <div class="poukaz-variant-badge"><?= e(t('poukaz.variant.2.badge')) ?></div>
            <div class="poukaz-variant-price"><?= $variantPrice(2) ?></div>
            <p><?= e(t('poukaz.variant.2.desc')) ?></p>
            <a class="btn btn-primary btn-sm" href="#objednavka" data-preset="<?= e(t('poukaz.preset.2')) ?>"><?= e(t('poukaz.variant.select')) ?></a>
        </article>
        <article class="poukaz-variant">
            <div class="poukaz-variant-badge"><?= e(t('poukaz.variant.3.badge')) ?></div>
            <div class="poukaz-variant-price"><?= $variantPrice(3) ?></div>
            <p><?= e(t('poukaz.variant.3.desc')) ?></p>
            <a class="btn btn-ghost btn-sm" href="#objednavka" data-preset="<?= e(t('poukaz.preset.3')) ?>"><?= e(t('poukaz.variant.select')) ?></a>
        </article>
        <article class="poukaz-variant">
            <div class="poukaz-variant-badge"><?= e(t('poukaz.variant.4.badge')) ?></div>
            <div class="poukaz-variant-price"><?= $variantPrice(4) ?></div>
            <p><?= e(t('poukaz.variant.4.desc')) ?></p>
            <a class="btn btn-ghost btn-sm" href="#objednavka" data-preset="<?= e(t('poukaz.preset.custom')) ?>"><?= e(t('poukaz.variant.select')) ?></a>
        </article>
    </div>
</section>

<section class="poukaz-how">
    <div class="poukaz-how-head">
        <div class="eyebrow"><?= e(t('poukaz.how.eyebrow')) ?></div>
        <h2><?= e(t('poukaz.how.title')) ?></h2>
    </div>
    <div class="poukaz-how-grid">
        <div class="poukaz-how-step">
            <div class="poukaz-how-num">01</div>
            <h3><?= e(t('poukaz.how.1.title')) ?></h3>
            <p><?= e(t('poukaz.how.1.desc')) ?></p>
        </div>
        <div class="poukaz-how-step">
            <div class="poukaz-how-num">02</div>
            <h3><?= e(t('poukaz.how.2.title')) ?></h3>
            <p><?= e(t('poukaz.how.2.desc')) ?></p>
        </div>
        <div class="poukaz-how-step">
            <div class="poukaz-how-num">03</div>
            <h3><?= e(t('poukaz.how.3.title')) ?></h3>
            <p><?= e(t('poukaz.how.3.desc')) ?></p>
        </div>
    </div>
</section>

<?php $defaultAmount = 1500.0; $defaultSpayd = $iban !== '' ? ny_spayd($iban, $defaultAmount, 'Darkovy poukaz Namaste') : ''; ?>
<section class="poukaz-payment" id="platba">
    <div class="poukaz-payment-head">
        <div class="eyebrow"><?= e(t('poukaz.payment.eyebrow')) ?></div>
        <h2><?= e(t('poukaz.payment.title')) ?></h2>
        <p><?= e(t('poukaz.payment.lead')) ?></p>
    </div>
    <div class="poukaz-payment-grid">
        <div class="poukaz-qr">
            <?php if ($iban !== ''): ?>
                <div class="poukaz-qr-box" id="poukaz-qr" data-iban="<?= e($iban) ?>" data-amount="<?= e((string)$defaultAmount) ?>" data-msg="Darkovy poukaz Namaste" data-any-amount="<?= e(t('poukaz.payment.qr.any')) ?>" data-spayd="<?= e($defaultSpayd) ?>"></div>
                <div class="poukaz-qr-amount">
                    <span><?= e(t('poukaz.payment.qr.amount')) ?></span>
                    <strong id="poukaz-qr-amount-lbl"><?= number_format($defaultAmount, 0, ',', ' ') ?> Kč</strong>
                </div>
                <p class="poukaz-qr-hint"><?= e(t('poukaz.payment.qr.hint')) ?></p>
            <?php else: ?>
                <div class="poukaz-qr-placeholder">
                    <?= e(t('poukaz.payment.qr.placeholder')) ?>
                </div>
            <?php endif; ?>
        </div>
        <dl class="poukaz-bank">
            <?php if ($holder !== ''): ?>
                <dt><?= e(t('poukaz.bank.holder')) ?></dt><dd><?= e($holder) ?></dd>
            <?php endif; ?>
            <?php if ($account !== ''): ?>
                <dt><?= e(t('poukaz.bank.account')) ?></dt><dd class="mono"><?= e($account) ?></dd>
            <?php endif; ?>
            <?php if ($iban !== ''): ?>
                <dt><?= e(t('poukaz.bank.iban')) ?></dt><dd class="mono"><?= e($iban) ?></dd>
            <?php endif; ?>
            <dt><?= e(t('poukaz.bank.vs')) ?></dt><dd class="mono"><?= t('poukaz.bank.vs.value') ?></dd>
            <dt><?= e(t('poukaz.bank.msg')) ?></dt><dd><?= e(t('poukaz.bank.msg.value')) ?></dd>
            <?php
                $monthKey = 'poukaz.bank.validity.months.one';
                if ($validity >= 5)      $monthKey = 'poukaz.bank.validity.months.many';
                elseif ($validity >= 2)  $monthKey = 'poukaz.bank.validity.months.few';
            ?>
            <dt><?= e(t('poukaz.bank.validity')) ?></dt><dd><?= (int)$validity ?> <?= e(t($monthKey)) ?> <?= e(t('poukaz.bank.validity.from')) ?></dd>
        </dl>
    </div>
</section>

<section class="poukaz-order" id="objednavka">
    <div class="poukaz-order-head">
        <div class="eyebrow"><?= e(t('poukaz.order.eyebrow')) ?></div>
        <h2><?= e(t('poukaz.order.title')) ?></h2>
        <p><?= e(t('poukaz.order.lead')) ?></p>
    </div>
    <form method="post" class="poukaz-form" data-recaptcha="poukaz" novalidate>
        <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
        <label><?= e(t('poukaz.form.name')) ?>
            <input type="text" name="name" value="<?= e($user ? (string)$user['display_name'] : '') ?>" required>
        </label>
        <label><?= e(t('poukaz.form.email')) ?>
            <input type="email" name="email" value="<?= e($user ? (string)$user['email'] : '') ?>" required>
        </label>
        <label><?= e(t('poukaz.form.amount')) ?>
            <input type="text" name="amount" id="poukaz-amount" list="poukaz-amounts"
                   placeholder="<?= e(t('poukaz.form.amount.placeholder')) ?>"
                   autocomplete="off" required>
            <datalist id="poukaz-amounts">
                <option value="<?= e(t('poukaz.preset.1')) ?>">
                <option value="<?= e(t('poukaz.preset.2')) ?>">
                <option value="<?= e(t('poukaz.preset.3')) ?>">
                <option value="<?= e(t('poukaz.preset.4')) ?>">
                <option value="<?= e(t('poukaz.preset.5')) ?>">
                <option value="<?= e(t('poukaz.preset.custom')) ?>">
            </datalist>
        </label>
        <label><?= e(t('poukaz.form.for_whom')) ?>
            <input type="text" name="for_whom" placeholder="<?= e(t('poukaz.form.for_whom.placeholder')) ?>">
        </label>
        <label class="poukaz-form-full"><?= e(t('poukaz.form.message')) ?>
            <textarea name="message" rows="4" placeholder="<?= e(t('poukaz.form.message.placeholder')) ?>"></textarea>
        </label>
        <div class="hp-field" aria-hidden="true">
            <label><?= e(t('poukaz.form.hp')) ?>
                <input type="text" name="website" tabindex="-1" autocomplete="off">
            </label>
        </div>
        <label class="captcha-field poukaz-form-full"><?= e(t('poukaz.form.captcha')) ?> <?= (int)$captcha['a'] ?> + <?= (int)$captcha['b'] ?>?
            <input type="text" name="captcha" inputmode="numeric" pattern="[0-9]+" autocomplete="off" required>
        </label>
        <button class="btn btn-primary btn-form" type="submit"><?= e(t('poukaz.form.submit')) ?></button>
    </form>
    <p class="poukaz-order-contact">
        <?= t('poukaz.order.contact') ?> <?= ny_phone_obf($phone) ?>
        <?= t('poukaz.order.contact.or') ?> <?= ny_email_obf($email) ?>.
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
            var anyLbl = qrEl.getAttribute('data-any-amount') || 'any amount';
            qrAmountLbl.textContent = amount > 0
                ? amount.toLocaleString('cs-CZ') + ' Kč'
                : anyLbl;
        }
    }

    if (qrEl) {
        var initAmount = parseFloat(qrEl.getAttribute('data-amount') || '0') || 0;
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
