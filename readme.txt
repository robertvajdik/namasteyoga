================================================================================
  STUDIO NAMASTÉ — rezervační web
================================================================================

Rezervační systém, katalog lekcí, správa obsahu a newsletter pro studio jógy
Namasté v Uherském Brodě. Postaveno v čistém PHP 8+ / MySQL (PDO), bez
frameworku a bez build kroku.

--------------------------------------------------------------------------------
POŽADAVKY
--------------------------------------------------------------------------------
* PHP 8.0+ s rozšířeními: pdo_mysql, mbstring, fileinfo, openssl, json
* MySQL/MariaDB 5.7+ (utf8mb4)
* Apache s mod_rewrite (kvůli .htaccess a bezvýpisovým URL)
* Zapisovatelné adresáře:  assets/avatars/  assets/gallery/  assets/teachers/

--------------------------------------------------------------------------------
INSTALACE
--------------------------------------------------------------------------------
1. Nakopírujte soubory na hosting (webroot = kořen projektu).
2. Upravte config.php – přihlašovací údaje k databázi (viz níže).
3. Zajistěte, že složka assets/ a její podsložky mají zápis pro PHP proces.
4. Otevřete web v prohlížeči – PHP samo doplní chybějící tabulky
   (viz ny_ensure_content_tables() v src/db.php).
5. Prvního administrátora nasaďte přes admin_setup.php:
   – otevřete /admin_setup.php v prohlížeči
   – vyplňte e-mail a heslo
   – tento skript pak SMAŽTE (nebo přejmenujte).
6. Přihlaste se přes /login a otevřete /admin.

--------------------------------------------------------------------------------
KONFIGURACE (config.php)
--------------------------------------------------------------------------------
    db.host / db.port / db.name / db.user / db.pass ... připojení k DB
    app.timezone .............. výchozí časové pásmo (Europe/Prague)
    app.wp_users_tbl .......... jen pro migrate.php – zdrojová tabulka WP userů

Editovatelná provozní nastavení (název, telefon, e-mail, adresa, sítě, mapa,
Google Analytics, reCAPTCHA v3) se ukládají do tabulky ny_settings a spravují
se v adminu na /admin/settings.

--------------------------------------------------------------------------------
STRUKTURA
--------------------------------------------------------------------------------
    index.php ................ homepage (hero, feature grid, testimonials, FAQ,
                                newsletter, JSON-LD structured data)
    rezervace.php ............ týdenní rozvrh + rezervační tlačítko
    reserve.php / cancel.php . POST endpointy pro rezervaci/zrušení
    lekce.php ................ katalog lekcí a kurzů podle kategorií
    individualni.php ......... individuální lekce
    masaze.php ............... nabídka masáží
    lektori.php .............. profily lektorů
    galerie.php .............. galerie s Fancybox lightboxem (jsdelivr)
    cenik.php ................ ceník lekcí a permanentek
    kontakt.php .............. kontaktní údaje, mapa, kontaktní formulář
    podminky.php ............. provozní podmínky studia
    gdpr.php ................. zásady zpracování osobních údajů
    my.php ................... moje rezervace, avatar, statistiky uživatele
    login.php / register.php . přihlášení + registrace + host mode
    logout.php ............... odhlášení
    newsletter.php ........... POST endpoint pro přihlášení k odběru
    unsubscribe.php .......... odhlašovací stránka s tokenem
    sitemap.php + robots.txt . dynamický sitemap.xml pro roboty
    admin_setup.php .......... prvotní vytvoření admin účtu (po použití smazat)
    migrate.php .............. jednorázová migrace uživatelů ze staré WP DB
    debug_login.php .......... vývojářský nástroj pro ověření hashů (smazat!)

    src/
        db.php ............... PDO, nastavení, provisioning tabulek, helpery
        auth.php ............. sessions, CSRF, hesla (bcrypt + WP legacy)
        layout.php ........... hlavička/patička webu, e-mail obfuskátor,
                                reCAPTCHA verify, JSON-LD, CSP headery
    admin/
        _layout.php .......... společný admin layout + navigace
        index.php ............ dashboard
        reservations.php ..... správa rezervací
        classes.php .......... CRUD lekcí + rozvrh
        users.php ............ CRUD uživatelů + avatary
        teachers.php ......... CRUD lektorů + fotky
        massages.php ......... CRUD masáží
        categories.php ....... CRUD kategorií lekcí
        gallery.php .......... CRUD galerie
        newsletter.php ....... správa odběratelů + CSV export
        settings.php ......... editovatelné nastavení webu

    assets/
        logoCream.png ........ hlavní logo (footer/OG)
        avatars/ ............. avatary uživatelů
        gallery/ ............. fotky do galerie
        teachers/ ............ portréty lektorů

    style.css ................ jednotné styly (design tokens + komponenty)
    .htaccess ................ mod_rewrite pro sitemap + bezvýpisové URL

--------------------------------------------------------------------------------
DATABÁZOVÉ TABULKY (auto-provisioning)
--------------------------------------------------------------------------------
Následující tabulky vzniknou při prvním načtení stránky, pokud chybí:

    ny_users .................. uživatelé (registrovaní, hosté, admini)
    ny_classes ................ lekce (šablony rozvrhu)
    ny_reservations ........... jednotlivé rezervace (user + class + date)
    ny_teachers ............... profily lektorů
    ny_massages ............... nabídka masáží
    ny_categories ............. kategorie lekcí
    ny_gallery ................ položky galerie
    ny_newsletter_subscribers . odběratelé newsletteru
    ny_settings ............... key/value nastavení webu

Sloupce se defenzivně doplňují přes INFORMATION_SCHEMA.COLUMNS + ALTER TABLE
(např. ny_teachers.photo, ny_users.avatar) – bezpečné pro existující instalace.

--------------------------------------------------------------------------------
BEZPEČNOST
--------------------------------------------------------------------------------
* CSRF tokeny na všech POST formulářích (ny_csrf_check / ny_csrf_token).
* Prepared statements v celém PHP kódu (PDO ATTR_EMULATE_PREPARES = false).
* Content-Security-Policy, X-Frame-Options, X-Content-Type-Options, HSTS.
* reCAPTCHA v3 (volitelné) na login / register / kontakt / reserve / newsletter.
    – Site + Secret klíč se zadávají v adminu (Nastavení webu → reCAPTCHA v3).
    – Když jsou klíče prázdné, ochrana je vypnutá (bez chyby).
* Hesla: password_hash(PASSWORD_DEFAULT) pro nové účty; verifikace podporuje
  i legacy WordPress ($wp$, $P$, $H$) hashe kvůli migraci ze staré DB.
* Nahrávání obrázků: kontrola MIME přes fileinfo, náhodná jména, limit velikosti.
* E-maily v HTML výstupu jsou obfuskované (JS deobfuskace) – anti-scraping.
* CASCADE mazání ny_reservations při smazání uživatele (FK).

--------------------------------------------------------------------------------
SEO
--------------------------------------------------------------------------------
* JSON-LD strukturovaná data na homepage: LocalBusiness, WebSite,
  ItemList (nabídka) a FAQPage.
* Dynamický sitemap.xml (viz sitemap.php + .htaccess rewrite).
* robots.txt zamezuje indexaci /admin, endpointů a nástrojů.
* Bezvýpisové (extensionless) URL přes .htaccess – .php se z URL odstraňuje
  301 redirectem a interně mapuje zpět. Vyžaduje mod_rewrite.

--------------------------------------------------------------------------------
NEWSLETTER
--------------------------------------------------------------------------------
* Přihlašovací formulář v patičce a na homepage (POST → newsletter.php).
* Ukládá se do ny_newsletter_subscribers; token pro odhlášení.
* Odhlášení: /unsubscribe?t={token} (odkaz posílejte v odesílaných mailech).
* Admin: /admin/newsletter – filtry (aktivní/odhlášení/všichni), CSV export
  s BOM (Excel-friendly), přidání/mazání záznamu, přepnutí stavu.

--------------------------------------------------------------------------------
GALERIE
--------------------------------------------------------------------------------
* Tři sekce: Studio / Foto z lekcí, akcí / Jóga festivaly.
* Fancybox v5 (via cdn.jsdelivr.net) pro lightbox s klávesnicí, swipem
  a náhledy. CSP je nastavená tak, aby jsdelivr.net byl povolen.
* Admin nahrává obrázky do assets/gallery/, MIME/velikost check, sort_order,
  active toggle.

--------------------------------------------------------------------------------
ROLE UŽIVATELŮ
--------------------------------------------------------------------------------
* registrovaný      → e-mail + heslo, může rezervovat, spravovat profil.
* host              → login pouze e-mailem + jménem, jednorázová rezervace.
* admin             → přístup do /admin/ (is_admin = 1 v ny_users).

Admin nikdy nemůže:
* smazat vlastní účet,
* odebrat si vlastní admin oprávnění.

--------------------------------------------------------------------------------
LOKALIZACE
--------------------------------------------------------------------------------
Celý web je česky (lang="cs", UTF-8). Časové pásmo Europe/Prague.

--------------------------------------------------------------------------------
LICENCE / AUTOR
--------------------------------------------------------------------------------
Interní projekt studia Namasté (Mgr. Klára Bigasová, Uherský Brod).
Copyright © 2019 – aktuální rok, všechna práva vyhrazena.
