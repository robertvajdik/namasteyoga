<?php
declare(strict_types=1);

function ny_config(): array {
    static $cfg = null;
    if ($cfg === null) {
        $cfg = require __DIR__ . '/../config.php';
    }
    return $cfg;
}

function ny_db(): PDO {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }
    $c = ny_config()['db'];
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $c['host'], (int)$c['port'], $c['name'], $c['charset']
    );
    $pdo = new PDO($dsn, $c['user'], $c['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}

date_default_timezone_set(ny_config()['app']['timezone'] ?? 'UTC');

/**
 * Editable, DB-backed site settings (contacts, map, GA, socials).
 * Auto-creates the ny_settings table + seeds defaults on first use.
 */
function ny_settings_defaults(): array {
    return [
        'site_name'     => 'Studio Namasté',
        'phone'         => '+420 775 607 710',
        'email'         => 'studio@namasteyoga.cz',
        'address'       => 'Studio Namasté, Uherský Brod',
        'opening'       => 'Otevřeno po celý týden podle rozvrhu',
        'facebook_url'  => '',
        'instagram_url' => '',
        'youtube_url'   => '',
        'map_lat'       => '49.0255',
        'map_lon'       => '17.6512',
        'map_zoom'      => '15',
        'ga_id'         => '',
        'recaptcha_site'   => '',
        'recaptcha_secret' => '',
    ];
}

function ny_settings_all(bool $refresh = false): array {
    static $cache = null;
    if ($cache !== null && !$refresh) return $cache;

    $pdo = ny_db();
    try {
        $rows = $pdo->query('SELECT k, v FROM ny_settings')->fetchAll();
    } catch (PDOException $e) {
        // Auto-provision on legacy installs.
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS ny_settings (
                k VARCHAR(64) NOT NULL,
                v TEXT NULL,
                PRIMARY KEY (k)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        $rows = [];
    }
    $out = ny_settings_defaults();
    foreach ($rows as $r) {
        $out[$r['k']] = (string)$r['v'];
    }
    // Ensure defaults are persisted for editing in admin.
    $missing = array_diff_key(ny_settings_defaults(), array_flip(array_column($rows, 'k')));
    if ($missing) {
        $ins = $pdo->prepare('INSERT IGNORE INTO ny_settings (k, v) VALUES (?, ?)');
        foreach ($missing as $k => $v) $ins->execute([$k, $v]);
    }
    return $cache = $out;
}

function ny_setting(string $key, string $default = ''): string {
    $all = ny_settings_all();
    return $all[$key] ?? $default;
}

function ny_settings_save(array $values): void {
    $pdo  = ny_db();
    $stmt = $pdo->prepare(
        'INSERT INTO ny_settings (k, v) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE v = VALUES(v)'
    );
    foreach ($values as $k => $v) {
        $stmt->execute([(string)$k, (string)$v]);
    }
    ny_settings_all(true);
}

/**
 * Auto-provisioning content tables for editable public pages.
 * Called from public pages + admin pages so legacy installs work without a re-run of schema.sql.
 */
function ny_ensure_content_tables(): void {
    static $done = false;
    if ($done) return;
    $pdo = ny_db();

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS ny_teachers (
            id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name        VARCHAR(120) NOT NULL,
            role        VARCHAR(190) NOT NULL DEFAULT "",
            bio         TEXT NULL,
            photo       VARCHAR(190) NOT NULL DEFAULT "",
            sort_order  INT NOT NULL DEFAULT 100,
            active      TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (id)
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    // Older installs may already have ny_teachers without the photo column – patch in place.
    $hasPhoto = (int)$pdo->query(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME   = 'ny_teachers'
            AND COLUMN_NAME  = 'photo'"
    )->fetchColumn();
    if ($hasPhoto === 0) {
        $pdo->exec('ALTER TABLE ny_teachers ADD COLUMN photo VARCHAR(190) NOT NULL DEFAULT "" AFTER bio');
    }
    // Same for ny_users avatar – users choose (or admin sets) their profile photo.
    $hasAvatar = (int)$pdo->query(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME   = 'ny_users'
            AND COLUMN_NAME  = 'avatar'"
    )->fetchColumn();
    if ($hasAvatar === 0) {
        $pdo->exec('ALTER TABLE ny_users ADD COLUMN avatar VARCHAR(190) NOT NULL DEFAULT "" AFTER phone');
    }
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS ny_massages (
            id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name        VARCHAR(190) NOT NULL,
            duration    VARCHAR(60)  NOT NULL DEFAULT "",
            price       VARCHAR(60)  NOT NULL DEFAULT "",
            description TEXT NULL,
            sort_order  INT NOT NULL DEFAULT 100,
            active      TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (id)
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS ny_categories (
            slug        VARCHAR(40)  NOT NULL,
            label       VARCHAR(120) NOT NULL,
            description TEXT NULL,
            sort_order  INT NOT NULL DEFAULT 100,
            active      TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (slug)
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS ny_newsletter_subscribers (
            id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
            email        VARCHAR(190) NOT NULL,
            name         VARCHAR(120) NOT NULL DEFAULT "",
            token        CHAR(32)     NOT NULL,
            confirmed_at DATETIME NULL,
            unsubscribed_at DATETIME NULL,
            source       VARCHAR(40)  NOT NULL DEFAULT "footer",
            created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY email (email),
            KEY token (token)
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS ny_gallery (
            id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
            section     VARCHAR(40)  NOT NULL DEFAULT "studio",
            title       VARCHAR(190) NOT NULL DEFAULT "",
            alt         VARCHAR(190) NOT NULL DEFAULT "",
            file        VARCHAR(190) NOT NULL,
            sort_order  INT NOT NULL DEFAULT 100,
            active      TINYINT(1) NOT NULL DEFAULT 1,
            created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY section (section, sort_order)
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    // Seed defaults only when tables are empty (first run).
    $c = (int)$pdo->query('SELECT COUNT(*) FROM ny_teachers')->fetchColumn();
    if ($c === 0) {
        $ins = $pdo->prepare('INSERT INTO ny_teachers (name, role, bio, sort_order) VALUES (?, ?, ?, ?)');
        $seed = [
            ['Mgr. Klára Bigasová',           'Majitelka studia · jóga a pilates',    'Zakladatelka studia. Propojuje zdravotní, kondiční i terapeutickou rovinu jógy – od klidných po dynamické formy. Jóga je pro ni „cestou z vnějšího labyrintu do ráje srdce".', 10],
            ['Petr Klika, Ing. arch. et Bc.', 'Fyzioterapeut · Chi-kung',             'Hledá harmonii skrze Chi-kung a aikido. Do studia přináší terapeutický pohled a bohatou zkušenost, kterou rád sdílí s ostatními.',                                    20],
            ['Dominika Koníčková',            'Masérka',                              'Skvělá masérka a milá společnice studia. Klienti oceňují její empatický přístup i pečlivost.',                                                                       30],
            ['Jitka Šenkeříková',             'Masérka · lektorka jógy',              'Kombinuje masáže s vedením jógových lekcí. Praxi vede s citem, klidem a důrazem na dech.',                                                                          40],
        ];
        foreach ($seed as $r) $ins->execute($r);
    }

    $c = (int)$pdo->query('SELECT COUNT(*) FROM ny_massages')->fetchColumn();
    if ($c === 0) {
        $ins = $pdo->prepare('INSERT INTO ny_massages (name, duration, price, description, sort_order) VALUES (?, ?, ?, ?, ?)');
        $seed = [
            ['Klasická relaxační',        '60 / 90 min', '850 / 1 200 Kč', 'Uvolní napětí, prohřeje záda, ramena a šíji. Ideální po dlouhých dnech v kanceláři.', 10],
            ['Sportovní',                 '60 min',      '900 Kč',         'Hlubší tlak, cílené uvolnění po tréninku. Skvělá pro běžce a cyklisty.',              20],
            ['Thajská olejová',           '90 min',      '1 350 Kč',       'Kombinace protahování a olejové masáže – pro tělo i mysl.',                          30],
            ['Lávové kameny',             '90 min',      '1 400 Kč',       'Hlubokou relaxaci umocňuje teplo horkých čedičových kamenů.',                        40],
            ['Reflexní masáž chodidel',   '45 min',      '650 Kč',         'Práce s reflexními body na chodidlech – uvolnění celého těla.',                      50],
            ['Prenatal masáž',            '60 min',      '950 Kč',         'Jemná masáž pro budoucí maminky (od 2. trimestru).',                                 60],
        ];
        foreach ($seed as $r) $ins->execute($r);
    }

    $c = (int)$pdo->query('SELECT COUNT(*) FROM ny_categories')->fetchColumn();
    if ($c === 0) {
        $ins = $pdo->prepare('INSERT INTO ny_categories (slug, label, description, sort_order) VALUES (?, ?, ?, ?)');
        $seed = [
            ['yoga',       'Jóga',              'Hatha, Vinyasa, Yin, Ashtanga i Restorative.', 10],
            ['pilates',    'Pilates',           'Pilates matwork a core training.',              20],
            ['workshop',   'Kurzy a workshopy', 'Uzavřené kurzy a víkendové akce.',              30],
            ['individual', 'Individuální',      'Praxe 1:1 na míru.',                            40],
            ['massage',    'Masáže',            'Regenerace po pohybu.',                         50],
        ];
        foreach ($seed as $r) $ins->execute($r);
    }

    $done = true;
}

function ny_teachers_active(): array {
    ny_ensure_content_tables();
    return ny_db()->query('SELECT * FROM ny_teachers WHERE active = 1 ORDER BY sort_order, name')->fetchAll();
}

function ny_massages_active(): array {
    ny_ensure_content_tables();
    return ny_db()->query('SELECT * FROM ny_massages WHERE active = 1 ORDER BY sort_order, name')->fetchAll();
}

function ny_categories_active(): array {
    ny_ensure_content_tables();
    return ny_db()->query('SELECT * FROM ny_categories WHERE active = 1 ORDER BY sort_order, label')->fetchAll();
}

function ny_gallery_sections(): array {
    return [
        'studio'    => 'Studio',
        'lekce'     => 'Foto z lekcí, akcí ad.',
        'festivaly' => 'Jóga festivaly',
    ];
}

/**
 * Newsletter — subscribe / unsubscribe / lookup helpers.
 * Idempotent: subscribing an already-subscribed email refreshes name/source and (if applicable) clears the unsubscribe flag.
 */
function ny_newsletter_subscribe(string $email, string $name = '', string $source = 'footer'): array {
    ny_ensure_content_tables();
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Zadejte platnou e-mailovou adresu.');
    }
    $pdo = ny_db();
    $stmt = $pdo->prepare('SELECT * FROM ny_newsletter_subscribers WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $row = $stmt->fetch();
    if ($row) {
        $pdo->prepare(
            'UPDATE ny_newsletter_subscribers
                SET name = ?, source = ?, unsubscribed_at = NULL,
                    confirmed_at = COALESCE(confirmed_at, NOW())
              WHERE id = ?'
        )->execute([$name, $source, $row['id']]);
        return array_merge($row, ['name' => $name, 'source' => $source, 'unsubscribed_at' => null]);
    }
    $token = bin2hex(random_bytes(16));
    $pdo->prepare(
        'INSERT INTO ny_newsletter_subscribers (email, name, token, confirmed_at, source)
         VALUES (?, ?, ?, NOW(), ?)'
    )->execute([$email, $name, $token, $source]);
    return [
        'id'    => (int)$pdo->lastInsertId(),
        'email' => $email,
        'name'  => $name,
        'token' => $token,
    ];
}

function ny_newsletter_unsubscribe_by_token(string $token): bool {
    ny_ensure_content_tables();
    if ($token === '') return false;
    $pdo = ny_db();
    $stmt = $pdo->prepare('UPDATE ny_newsletter_subscribers SET unsubscribed_at = NOW() WHERE token = ? AND unsubscribed_at IS NULL');
    $stmt->execute([$token]);
    return $stmt->rowCount() > 0;
}

function ny_gallery_active_grouped(): array {
    ny_ensure_content_tables();
    $rows = ny_db()->query(
        'SELECT * FROM ny_gallery WHERE active = 1 ORDER BY section, sort_order, id'
    )->fetchAll();
    $out = [];
    foreach (ny_gallery_sections() as $slug => $label) {
        $out[$slug] = ['label' => $label, 'items' => []];
    }
    foreach ($rows as $r) {
        $slug = $r['section'] ?: 'studio';
        if (!isset($out[$slug])) {
            $out[$slug] = ['label' => ucfirst($slug), 'items' => []];
        }
        $out[$slug]['items'][] = $r;
    }
    return $out;
}
