<?php
declare(strict_types=1);

require __DIR__ . '/src/layout.php';

$s     = ny_settings_all();
$phone = $s['phone'];
$email = $s['email'];

ny_render_header(t('gdpr.title'), 'gdpr', [
    'description' => t('gdpr.meta.description'),
]);
?>
<section class="section-title-block reveal">
    <div class="eyebrow"><?= e(t('gdpr.hero.eyebrow')) ?></div>
    <h1 class="page-title"><?= e(t('gdpr.hero.title')) ?></h1>
    <p class="page-lead">
        <?= e(t('gdpr.hero.lead')) ?>
    </p>
</section>

<article class="legal-page reveal">
    <h2><?= e(t('gdpr.controller.title')) ?></h2>
    <p>
        <?= t('gdpr.controller.body') ?>
    </p>

    <h2><?= e(t('gdpr.contact.title')) ?></h2>
    <p>
        <?= t('gdpr.contact.intro') ?>
        <a href="tel:<?= e(preg_replace('/\s+/', '', $phone)) ?>"><?= e($phone) ?></a>
        <?= t('gdpr.contact.or.email') ?> <?= ny_email_obf($email) ?>.
    </p>
    <p>
        <?= e(t('gdpr.declaration.intro')) ?>
    </p>
    <ul>
        <li><?= e(t('gdpr.declaration.item.1')) ?></li>
        <li><?= e(t('gdpr.declaration.item.2')) ?></li>
        <li><?= e(t('gdpr.declaration.item.3')) ?></li>
    </ul>

    <h2><?= e(t('gdpr.scope.title')) ?></h2>
    <p><?= e(t('gdpr.scope.intro')) ?></p>
    <ul>
        <li><?= t('gdpr.scope.item.1') ?></li>
        <li><?= t('gdpr.scope.item.2') ?></li>
        <li><?= t('gdpr.scope.item.3') ?></li>
        <li><?= t('gdpr.scope.item.4') ?></li>
        <li><?= t('gdpr.scope.item.5') ?></li>
    </ul>
    <p>
        <?= e(t('gdpr.scope.retention')) ?>
    </p>

    <h2><?= e(t('gdpr.security.title')) ?></h2>
    <p>
        <?= e(t('gdpr.security.body')) ?>
    </p>

    <h2><?= e(t('gdpr.third.title')) ?></h2>
    <p>
        <?= e(t('gdpr.third.body')) ?>
    </p>
    <p><?= e(t('gdpr.third.list.intro')) ?></p>
    <ul>
        <li>MailChimp</li>
        <li>Google</li>
        <li>Facebook</li>
        <li>Instagram</li>
    </ul>
    <p>
        <?= e(t('gdpr.third.promise')) ?>
    </p>

    <h2><?= e(t('gdpr.eu.title')) ?></h2>
    <p>
        <?= e(t('gdpr.eu.body')) ?>
    </p>

    <h2><?= e(t('gdpr.rights.title')) ?></h2>
    <p>
        <?= t('gdpr.rights.intro') ?> <?= ny_email_obf($email) ?>.
    </p>
    <ul>
        <li><?= t('gdpr.rights.item.1') ?></li>
        <li><?= t('gdpr.rights.item.2') ?></li>
        <li><?= t('gdpr.rights.item.3') ?></li>
        <li><?= t('gdpr.rights.item.4') ?></li>
        <li><?= t('gdpr.rights.item.5') ?></li>
        <li><?= t('gdpr.rights.item.6') ?></li>
        <li><?= t('gdpr.rights.item.7') ?></li>
    </ul>

    <h2><?= e(t('gdpr.confidentiality.title')) ?></h2>
    <p>
        <?= e(t('gdpr.confidentiality.body')) ?>
    </p>

    <p class="hint"><?= t('gdpr.effective') ?></p>
</article>

<?php ny_render_footer();
