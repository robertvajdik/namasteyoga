<?php
declare(strict_types=1);

require __DIR__ . '/src/layout.php';
ny_render_header('Individuální lekce', 'individ');
?>
<section class="section-title-block">
    <div class="eyebrow">1 : 1 praxe</div>
    <h1 class="page-title">Individuální lekce</h1>
    <p class="page-lead">
        Praxe šitá přímo pro vás. Zaměříme se na to, co potřebujete – ať už jde o dech,
        držení těla, přípravu na porod, návrat po zranění nebo prohloubení vaší praxe.
    </p>
</section>

<div class="cols cols-2">
    <section class="card">
        <h2>Kdy má individuální lekce smysl?</h2>
        <ul class="check-list">
            <li>Začínáte a chcete jistotu ve správném provedení.</li>
            <li>Řešíte konkrétní obtíž (záda, pánevní dno, dech).</li>
            <li>Vracíte se k pohybu po pauze nebo po zranění.</li>
            <li>Chcete lekci mimo běžný rozvrh.</li>
            <li>Preferujete soukromí a klid bez skupiny.</li>
        </ul>
    </section>
    <section class="card muted">
        <h2>Jak to probíhá</h2>
        <ol class="steps">
            <li><strong>Domluvíme se</strong> – krátký úvodní hovor, co potřebujete.</li>
            <li><strong>Vybereme termín</strong> – ve studiu, nebo online.</li>
            <li><strong>Praxe na míru</strong> – 60 nebo 90 minut jen pro vás.</li>
            <li><strong>Plán do praxe</strong> – jednoduché tipy, s čím dál pracovat.</li>
        </ol>
        <p><a class="btn btn-primary btn-form" href="kontakt.php">Domluvit lekci</a></p>
    </section>
</div>

<h2 class="section-h section-h-gap">Ceny individuálních lekcí</h2>
<div class="price-grid">
    <article class="price-card">
        <div class="price-eyebrow">1 osoba</div>
        <h3>60 minut</h3>
        <div class="price-amount">900 Kč</div>
        <p>Jóga, pilates nebo dechová praxe.</p>
    </article>
    <article class="price-card featured">
        <div class="price-eyebrow">Nejoblíbenější</div>
        <h3>90 minut</h3>
        <div class="price-amount">1 250 Kč</div>
        <p>Prostor na hlubší práci a klidný závěr.</p>
    </article>
    <article class="price-card">
        <div class="price-eyebrow">2 osoby</div>
        <h3>Dvojice · 60 min</h3>
        <div class="price-amount">1 400 Kč</div>
        <p>Přijďte s partnerem, kamarádkou nebo sourozencem.</p>
    </article>
</div>

<?php ny_render_footer();
