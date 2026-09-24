<?php
declare(strict_types=1);

require __DIR__ . '/src/layout.php';

ny_render_header('Provozní podmínky', 'podminky', [
    'description' => 'Provozní řád a podmínky Namasté yoga studia v Uherském Brodě.',
]);
?>
<section class="section-title-block reveal">
    <div class="eyebrow">Namasté yoga studio · Uherský Brod</div>
    <h1 class="page-title">Provozní podmínky studia Namasté</h1>
    <p class="page-lead">
        Prosíme, seznamte se s naším Provozním řádem. Zakoupením kurzu, jednorázového
        vstupu nebo permanentky potvrzujete, že jej respektujete a budete se jím řídit.
    </p>
</section>

<article class="legal-page reveal">
    <ol class="legal-list">
        <li>
            Každý klient je povinen seznámit se s Provozním řádem Namasté yoga studia
            Uherský Brod. Zakoupením kurzu, jednorázového vstupu nebo bodové permanentky
            potvrzuje, že se s Provozním řádem seznámil, a zavazuje se jej plně respektovat
            a dodržovat.
        </li>
        <li>
            Každý klient je povinen se při vstupu prokázat permanentkou nebo si zakoupit
            jednorázovou lekci. Aktuálně platný <a href="cenik.php">ceník služeb</a> je
            dostupný na webových stránkách. Řádně uhrazené vstupné opravňuje klienta ke
            vstupu do prostor jóga studia a využívání jeho zařízení či služeb.
        </li>
        <li>
            Namasté yoga studio Uherský Brod je otevřeno odpoledne dle aktuálně platného
            <a href="lekce.php">rozpisu lekcí</a>, jenž je umístěn na webových stránkách
            www.namasteyoga.cz. Jógové lekce probíhají pod vedením kvalifikovaných
            instruktorů. Nastane-li jakákoli událost, která znemožňuje poskytnutí
            nabízených služeb, je Provozovatel oprávněn plánované lekce zrušit a oznámit
            to na webových stránkách nebo sociálních sítích.
        </li>
        <li>
            Účast na jógové lekci je možné rezervovat využitím
            <a href="rezervace.php">rezervačního systému</a>, který je umístěný na
            webových stránkách Namasté yoga studia Uherský Brod. Zákonem č. 101/2000 Sb.
            o ochraně osobních údajů a o změně některých zákonů, ve znění účinném od
            1. ledna 2011. O zpracování osobních údajů více v záložce
            <a href="gdpr.php">Zásady zpracování a ochrany</a>.
        </li>
        <li>
            Rezervovanou odpolední lekci lze zrušit nejpozději do 14. hodiny v den, kdy
            lekce začíná. Dopolední lekci lze zrušit nejpozději den předem do 22 hodin.
            Osoby se zakoupeným kurzem mají možnost, po včasném odhlášení z lekce, využít
            náhrady za 1–2 zmeškané lekce. Pokud se klient nedostaví na danou lekci včas,
            a nebo nedojde k jejímu včasnému zrušení v on-line rezervačním systému či
            telefonicky, vstup propadá a je nutná úhrada lekce na recepci yoga studia
            Namasté v Uherském Brodě.
        </li>
        <li>
            Po vstupu do studia je každý klient povinen uložit venkovní obuv na místě
            tomu určeném, a to v prostorách v blízkosti recepce.
        </li>
        <li>
            Provozovatel nenese žádnou zodpovědnost za cennosti uložené v šatně (osobní
            doklady, klíče, cennosti, šperky, peníze, platební karty, notebooky a jiné).
            Cenné věci je možno vzít si s sebou do sálu, kde probíhá lekce. Dojde-li
            k odcizení či ztrátě osobních věcí, je klient povinen neprodleně informovat
            o této skutečnosti obsluhu recepce nebo lektora a kontaktovat Policii ČR.
            Na dodatečné ohlášení krádeže či ztráty nebude brán zřetel.
        </li>
        <li>
            Každý klient je povinen řídit se pokyny personálu. Vstup do studia je povolen
            pouze v čisté obuvi a vhodném sportovním oblečení. Při provozování příslušné
            sportovní činnosti nesmí ohrozit své zdraví, ani zdraví ostatních klientů.
            Dále je zakázáno jakýmkoli způsobem znečišťovat prostory.
        </li>
        <li>
            Klienti využívají veškeré zařízení a vybavení Provozovatele na vlastní
            nebezpečí. Na obsluhu studia či instruktora se mohou klienti obracet také
            v případě, že potřebují zodpovědět dotazy vztahující se k vybavením.
        </li>
        <li>
            Každý klient je plně zodpovědný za svůj zdravotní stav. Namasté yoga studio
            Uherský Brod nenese žádnou zodpovědnost za zdravotní stav klientů. Každý
            klient je rovněž povinen dodržovat pokyny instruktora vztahující se ke cvičení
            a vybavení. V opačném případě nenese Provozovatel žádnou zodpovědnost za
            vzniklé úrazy či zranění. Jakékoli zranění je nutné okamžitě ohlásit obsluze
            recepce či jakémukoli přítomnému instruktorovi. Během provozování sportovních
            aktivit nesmí klient ohrozit své zdraví, ani zdraví ostatních účastníků.
        </li>
        <li>
            Po skončení lekce musí každý klient po sobě uklidit vypůjčené pomůcky.
        </li>
        <li>
            Klienti jsou povinni řídit se příslušnými protipožárními předpisy, zodpovědně
            zacházet s majetkem Provozovatele a dbát na dodržování pořádku v prostorách
            studia.
        </li>
        <li>
            Klienti zodpovídají za škody vzniklé porušením Provozního řádu, za ztrátu či
            poškození majetku Provozovatele, a to v plné výši, i když byla škoda způsobena
            neúmyslným jednáním.
        </li>
    </ol>

    <p class="hint">Namasté yoga studio Uherský Brod</p>
</article>

<?php ny_render_footer();
