<?php
declare(strict_types=1);

require __DIR__ . '/src/layout.php';

ny_render_header('Domů', 'home');
?>
<section class="home-hero">
    <div class="eyebrow">Proč se hýčkat v Namasté?</div>
    <h1 class="page-title home-hero-title">Přicházejte i odcházejte s pocitem, že je o vás postaráno</h1>
</section>

<section class="home-video">
    <div class="home-video-frame">
        <iframe
            src="https://www.youtube-nocookie.com/embed/PaXYm4M_Ivo?rel=0"
            title="Studio Namasté – představení"
            loading="lazy"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
            allowfullscreen
            referrerpolicy="strict-origin-when-cross-origin"></iframe>
    </div>
</section>

<section class="home-features">
    <div class="home-feature">
        <div class="home-feature-num">01</div>
        <h3>Široký výběr lekcí <span>|</span> masáží <span>|</span> akcí</h3>
    </div>
    <div class="home-feature">
        <div class="home-feature-num">02</div>
        <h3>Příjemná a klidná atmosféra studia</h3>
    </div>
    <div class="home-feature">
        <div class="home-feature-num">03</div>
        <h3>Zkušení lektoři s dlouholetou praxí</h3>
    </div>
    <div class="home-feature">
        <div class="home-feature-num">04</div>
        <h3>Plná vybavenost studia i netradiční pomůcky</h3>
    </div>
</section>

<section class="home-about">
    <div class="home-about-head">
        <div class="eyebrow">O nás</div>
        <h2 class="section-h">Studio Namasté.</h2>
    </div>
    <div class="home-about-body">
        <p>
            Některé věci přicházejí přesně ve chvíli, kdy mají. A Studio Namasté vzniklo právě
            tak – z dlouholetých zkušeností, hledání a touhy vytvořit místo, kde můžeme
            zpomalit, nadechnout se a vrátit se sami k sobě.
        </p>
        <p>
            Studio Namasté je prostorem pohybu, harmonie, klidu a setkávání. Místem, kde se
            propojuje tělo s dechem, pohyb s vnímáním a vnější svět s tím, co se odehrává uvnitř
            nás. Jóga je pro každého. Nemusíte nic umět ani dokazovat. Stačí přijít takoví,
            jací právě jste. Věříme, že si ze studia můžete odnést více lehkosti, síly, klidu,
            radosti – nebo jen nový nádech.
        </p>
        <figure class="home-quote">
            <blockquote>
                „Uctívám to místo v Tobě, kde přebývá celý vesmír, místo světla, lásky,
                pravdy, míru a moudrosti. Skláním se před tím místem, kde jsme oba jeden.“
            </blockquote>
            <figcaption>— Mahátma Gándhí</figcaption>
        </figure>
        <div class="home-about-cta">
            <a class="btn btn-primary" href="rezervace.php">Rezervovat lekci</a>
        </div>
    </div>
</section>

<section class="home-stats">
    <div class="home-stat">
        <div class="home-stat-num">10</div>
        <div class="home-stat-lbl">Let s vámi</div>
    </div>
    <div class="home-stat">
        <div class="home-stat-num">16&nbsp;000</div>
        <div class="home-stat-lbl">Odcvičené lekce</div>
    </div>
    <div class="home-stat">
        <div class="home-stat-num">98&nbsp;%</div>
        <div class="home-stat-lbl">Spokojená těla i duše</div>
    </div>
</section>

<section class="home-tagline">
    <p class="home-tagline-lead">Prostě přišla a našeptala mi</p>
    <p class="home-tagline-main">
        <span class="home-tagline-bar">|</span>
        Že je tajemným kořením života, že je tajemstvím, do kterého mám nahlédnout
        <span class="home-tagline-bar">|</span>
    </p>
</section>

<section class="home-final-quote">
    <blockquote>Tam, kde je láska, tam je život.</blockquote>
    <cite>– Mahátma Gándhí –</cite>
</section>

<?php ny_render_footer();
