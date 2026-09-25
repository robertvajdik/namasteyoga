<?php
declare(strict_types=1);

require __DIR__ . '/src/layout.php';

$s     = ny_settings_all();
$phone = $s['phone'];
$email = $s['email'];

ny_render_header('Ochrana osobních údajů', 'gdpr', [
    'description' => 'Zásady zpracování a ochrany osobních údajů ve studiu Namasté Yoga v souladu s GDPR.',
]);
?>
<section class="section-title-block reveal">
    <div class="eyebrow">Studio Namasté</div>
    <h1 class="page-title">Zásady zpracování a ochrany osobních údajů</h1>
    <p class="page-lead">
        Pokud jste naším klientem, svěřujete nám své osobní údaje. My, jako poskytovatelé
        služeb, zodpovídáme za jejich ochranu a zabezpečení. Seznamte se, prosím, s ochranou
        osobních údajů, zásadami a právy, které máte v souvislosti s GDPR (Nařízení o ochraně
        osobních údajů).
    </p>
</section>

<article class="legal-page reveal">
    <h2>Kdo je správce / dále poskytovatel služeb?</h2>
    <p>
        Mgr. Klára Bigasová<br>
        Namasté yoga studio, Masarykovo náměstí 69, 688 01 Uherský Brod<br>
        IČ: 04614542
    </p>

    <h2>Kontaktní údaje</h2>
    <p>
        Pokud se na nás budete chtít v průběhu zpracování obrátit, můžete nás kontaktovat
        na tel. čísle <a href="tel:<?= e(preg_replace('/\s+/', '', $phone)) ?>"><?= e($phone) ?></a>
        nebo na e-mail: <?= ny_email_obf($email) ?>.
    </p>
    <p>
        Prohlašujeme, že jako správce vašich osobních údajů splňujeme zákonné povinnosti
        vyžadované platnou legislativou, zejména zákonem o ochraně osobních údajů a GDPR,
        a tedy že:
    </p>
    <ul>
        <li>budeme zpracovávat vaše osobní údaje jen na základě platného právního důvodu,
            a to především oprávněného zájmu, plnění smlouvy, zákonné povinnosti či
            uděleného souhlasu,</li>
        <li>plníme dle článku 13 GDPR informační povinnost ještě před zahájením zpracování
            osobních údajů,</li>
        <li>umožníme vám a budeme vás podporovat v uplatňování a plnění vašich práv podle
            zákona o ochraně osobních údajů a GDPR.</li>
    </ul>

    <h2>Rozsah osobních údajů a účely zpracování</h2>
    <p>Zpracováváme osobní údaje, které nám svěříte sami, a to z následujících důvodů (pro naplnění těchto účelů):</p>
    <ul>
        <li><strong>Poskytování služeb a plnění smlouvy.</strong> Vaše osobní údaje v rozsahu:
            jméno a příjmení, adresa, datum narození, e-mail, telefon nezbytně potřebujeme
            k plnění smlouvy (např. smlouvy k poskytování služeb).</li>
        <li><strong>Vedení účetnictví.</strong> Vaše osobní údaje (fakturační údaje) nezbytně
            potřebujeme, abychom vyhověli zákonné povinnosti pro vystavování a evidenci
            daňových dokladů.</li>
        <li><strong>Informovanost – zasílání newsletterů.</strong> Vaše osobní údaje (e-mail),
            na co klikáte v e-mailu a kdy je nejčastěji otevíráte, využíváme za účelem přímého
            zasílání informací o akcích a kurzech ve studiu Namasté. Newslettery vám zasíláme
            jen na základě vašeho souhlasu, po dobu 10 let od udělení. Tento souhlas můžete
            kdykoli odvolat použitím odhlašovacího odkazu v každém zaslaném e-mailu.</li>
        <li><strong>Fotografie a video záznamy z akcí.</strong> Na některých našich akcích
            pořizujeme fotografickou dokumentaci či video záznam. Fotografie z akcí používáme
            v propagačních materiálech, především na webu a sociálních sítích. U těchto
            materiálů nikdy nenajdete jména účastníků, jedině pokud by se jednalo o referenci
            a to na základě souhlasu. Pokud byste na záznamech nechtěli být, dejte nám vědět.</li>
        <li><strong>Fotografie a video záznamy pro propagační materiály.</strong> S těmito
            materiály zacházíme pouze na základě vašeho souhlasu.</li>
    </ul>
    <p>
        Vaše osobní údaje si ponecháváme po dobu běhu promlčecích lhůt, pokud zákon nestanoví
        delší dobu k jejich uchování nebo jsme v konkrétních případech neuvedli jinak.
    </p>

    <h2>Zabezpečení a ochrana osobních údajů</h2>
    <p>
        Chráníme osobní údaje v maximální možné míře pomocí moderních technologií, které
        odpovídají stupni technického rozvoje. Přijali jsme technická a organizační opatření,
        která zamezují zneužití, poškození nebo zničení vašich osobních údajů.
    </p>

    <h2>Předání osobních údajů třetím osobám</h2>
    <p>
        K vašim osobním údajům máme přístup my (poskytovatelé) a naši spolupracovníci, kteří
        jsou vázáni mlčenlivostí a proškoleni v oblasti bezpečnosti zpracování osobních údajů.
        Pro zajištění některých konkrétních zpracovatelských operací, které nedokážeme zajistit
        vlastními silami, využíváme služeb a aplikací zpracovatelů, kteří se na dané zpracování
        specializují a jsou v souladu s GDPR.
    </p>
    <p>Jsou to poskytovatelé následujících platforem a služeb:</p>
    <ul>
        <li>MailChimp</li>
        <li>Google</li>
        <li>Facebook</li>
        <li>Instagram</li>
    </ul>
    <p>
        Je možné, že se v budoucnu rozhodneme využít další aplikace či zpracovatele, pro
        usnadnění a zkvalitnění zpracování. Slibujeme vám však, že v takovém případě při
        výběru budeme na zpracovatele klást minimálně stejné nároky na zabezpečení a kvalitu
        zpracování jako na sebe.
    </p>

    <h2>Předávání dat mimo Evropskou unii</h2>
    <p>
        Data zpracováváme výhradně v Evropské unii nebo v zemích, které zajišťují odpovídající
        úroveň ochrany na základě rozhodnutí Evropské komise.
    </p>

    <h2>Vaše práva v souvislosti s ochranou osobních údajů</h2>
    <p>
        V souvislosti s ochranou osobních údajů máte řadu práv. Pokud budete chtít některého
        z těchto práv využít, prosím, kontaktujte nás prostřednictvím e-mailu:
        <?= ny_email_obf($email) ?>.
    </p>
    <ul>
        <li><strong>Právo na informace</strong>, které jsou plněny již touto informační stránkou
            se zásadami zpracování osobních údajů.</li>
        <li><strong>Právo na přístup.</strong> Můžete nás kdykoli vyzvat a my vám doložíme
            ve lhůtě 30 dní, jaké vaše osobní údaje zpracováváme a proč.</li>
        <li><strong>Právo na doplnění a změnu osobních údajů.</strong> Pokud se u vás něco
            změní, nebo shledáte své osobní údaje neaktuální či neúplné.</li>
        <li><strong>Právo na omezení zpracování</strong> můžete využít, pokud se domníváte,
            že zpracováváme vaše nepřesné údaje, domníváte se, že provádíme zpracování
            nezákonně, ale nechcete všechny údaje smazat, nebo pokud jste vznesl námitku
            proti zpracování. Omezit můžete rozsah osobních údajů nebo účelů zpracování
            (např. odhlášením z newsletteru omezujete účel zpracování pro zasílání
            obchodních sdělení).</li>
        <li><strong>Právo na výmaz (být zapomenut).</strong> V takovém případě vymažeme
            veškeré vaše osobní údaje ze svého systému. Na zajištění práva na výmaz
            potřebujeme 30 dní. V některých případech jsme vázáni zákonnou povinností
            a např. musíme evidovat vystavené daňové doklady po lhůtu stanovenou zákonem.
            V tomto případě tedy smažeme všechny takové osobní údaje, které nejsou vázány
            jiným zákonem. O dokončení výmazu vás budeme informovat na e-mail.</li>
        <li><strong>Stížnost u Úřadu pro ochranu osobních údajů.</strong> Pokud máte pocit,
            že s vašimi údaji nezacházíme v souladu se zákonem, máte právo se se svou
            stížností kdykoli obrátit na Úřad pro ochranu osobních údajů. Budeme moc rádi,
            pokud nejprve budete o tomto podezření informovat nás, abychom s tím mohli
            něco udělat a případné pochybení napravit.</li>
        <li><strong>Odhlášení ze zasílání newsletterů a obchodních sdělení.</strong>
            E-maily s inspirací, články či produkty a službami vám zasíláme, jste-li náš klient
            nebo podporovatel, na základě našeho oprávněného zájmu. Pokud jím nejste,
            posíláme vám je jen na základě vašeho souhlasu. V obou případech můžete ukončit
            odběr našich e-mailů stisknutím odhlašovacího odkazu v každém zaslaném e-mailu.</li>
    </ul>

    <h2>Mlčenlivost</h2>
    <p>
        Dovolujeme si vás ujistit, že naši spolupracovníci, kteří budou zpracovávat vaše
        osobní údaje, jsou povinni zachovávat mlčenlivost o osobních údajích a o
        bezpečnostních opatřeních, jejichž zveřejnění by ohrozilo zabezpečení vašich
        osobních údajů. Tato mlčenlivost přitom trvá i po skončení závazkových vztahů
        s námi. Bez vašeho souhlasu nebudou vaše osobní údaje vydány žádné jiné třetí straně.
    </p>

    <p class="hint">Tyto zásady zpracování osobních údajů platí od 25.&nbsp;5.&nbsp;2018.</p>
</article>

<?php ny_render_footer();
