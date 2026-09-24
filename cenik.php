<?php
declare(strict_types=1);

require __DIR__ . '/src/layout.php';
ny_render_header('Ceník', 'cenik');
?>
<section class="section-title-block">
    <div class="eyebrow">Jednoduché a férové</div>
    <h1 class="page-title">Ceník</h1>
    <p class="page-lead">
        Vyberte si jednorázový vstup nebo permanentku. Studenti a senioři mají zvýhodněnou cenu.
    </p>
</section>

<h2 class="section-h">Otevřené lekce</h2>
<div class="price-grid">
    <article class="price-card">
        <div class="price-eyebrow">Jednorázově</div>
        <h3>1 vstup</h3>
        <div class="price-amount">250 Kč</div>
        <p>Pro pravidelné i příležitostné praktikující.</p>
    </article>
    <article class="price-card featured">
        <div class="price-eyebrow">Doporučujeme</div>
        <h3>Permanentka 10×</h3>
        <div class="price-amount">2 200 Kč</div>
        <p>Platnost 4 měsíce. 220 Kč za lekci.</p>
    </article>
    <article class="price-card">
        <div class="price-eyebrow">Nejvýhodnější</div>
        <h3>Permanentka 20×</h3>
        <div class="price-amount">4 000 Kč</div>
        <p>Platnost 6 měsíců. 200 Kč za lekci.</p>
    </article>
</div>

<h2 class="section-h section-h-gap">Individuální lekce</h2>
<div class="price-grid">
    <article class="price-card">
        <h3>60 minut · 1 osoba</h3>
        <div class="price-amount">900 Kč</div>
    </article>
    <article class="price-card">
        <h3>90 minut · 1 osoba</h3>
        <div class="price-amount">1 250 Kč</div>
    </article>
    <article class="price-card">
        <h3>60 minut · 2 osoby</h3>
        <div class="price-amount">1 400 Kč</div>
    </article>
</div>

<h2 class="section-h section-h-gap">Masáže</h2>
<div class="tbl-wrap">
    <table class="tbl">
        <thead><tr><th>Masáž</th><th>Trvání</th><th>Cena</th></tr></thead>
        <tbody>
            <tr><td data-label="Masáž">Klasická relaxační</td><td data-label="Trvání">60 min</td><td data-label="Cena">850 Kč</td></tr>
            <tr><td data-label="Masáž">Klasická relaxační</td><td data-label="Trvání">90 min</td><td data-label="Cena">1 200 Kč</td></tr>
            <tr><td data-label="Masáž">Sportovní</td><td data-label="Trvání">60 min</td><td data-label="Cena">900 Kč</td></tr>
            <tr><td data-label="Masáž">Thajská olejová</td><td data-label="Trvání">90 min</td><td data-label="Cena">1 350 Kč</td></tr>
            <tr><td data-label="Masáž">Lávové kameny</td><td data-label="Trvání">90 min</td><td data-label="Cena">1 400 Kč</td></tr>
        </tbody>
    </table>
</div>

<p class="hint hint-form">
    Studenti a senioři mají slevu 10 % na jednorázový vstup a permanentky (proti platnému průkazu).
</p>

<?php ny_render_footer();
