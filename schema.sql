-- Namasté Yoga – reservation system schema.
-- Run once against the target database.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS `ny_users` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email`         VARCHAR(190) NOT NULL,
  `display_name`  VARCHAR(190) NOT NULL,
  `phone`         VARCHAR(30)  DEFAULT NULL,
  `password_hash` VARCHAR(255) DEFAULT NULL,
  `is_guest`      TINYINT(1)   NOT NULL DEFAULT 0,
  `is_admin`      TINYINT(1)   NOT NULL DEFAULT 0,
  `legacy_wp_id`  BIGINT UNSIGNED DEFAULT NULL,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email_unique` (`email`),
  KEY `legacy_wp_id` (`legacy_wp_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ny_classes` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`         VARCHAR(190) NOT NULL,
  `description`  TEXT DEFAULT NULL,
  `teacher`      VARCHAR(120) NOT NULL,
  `capacity`     INT UNSIGNED NOT NULL DEFAULT 12,
  `day_of_week`  TINYINT UNSIGNED NOT NULL, -- 1=Mon .. 7=Sun (ISO-8601)
  `start_time`   TIME NOT NULL,
  `end_time`     TIME NOT NULL,
  `room`         VARCHAR(120) DEFAULT NULL,
  `active`       TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `day_of_week` (`day_of_week`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ny_reservations` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `class_id`   INT UNSIGNED NOT NULL,
  `class_date` DATE NOT NULL,
  `status`     ENUM('booked','cancelled') NOT NULL DEFAULT 'booked',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_booking` (`user_id`, `class_id`, `class_date`),
  KEY `class_slot` (`class_id`, `class_date`),
  CONSTRAINT `fk_res_user`  FOREIGN KEY (`user_id`)  REFERENCES `ny_users`(`id`)   ON DELETE CASCADE,
  CONSTRAINT `fk_res_class` FOREIGN KEY (`class_id`) REFERENCES `ny_classes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ny_settings` (
  `k` VARCHAR(64) NOT NULL,
  `v` TEXT NULL,
  PRIMARY KEY (`k`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `ny_settings` (`k`, `v`) VALUES
  ('site_name',    'Studio Namasté'),
  ('phone',        '+420 775 607 710'),
  ('email',        'studio@namasteyoga.cz'),
  ('address',      'Studio Namasté, Uherský Brod'),
  ('opening',      'Otevřeno po celý týden podle rozvrhu'),
  ('facebook_url', ''),
  ('instagram_url',''),
  ('youtube_url',  ''),
  ('map_lat',      '49.0255'),
  ('map_lon',      '17.6512'),
  ('map_zoom',     '15'),
  ('ga_id',        '')
ON DUPLICATE KEY UPDATE v = v;

CREATE TABLE IF NOT EXISTS `ny_teachers` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(120) NOT NULL,
  `role`       VARCHAR(190) NOT NULL DEFAULT '',
  `bio`        TEXT NULL,
  `sort_order` INT NOT NULL DEFAULT 100,
  `active`     TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ny_massages` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(190) NOT NULL,
  `duration`    VARCHAR(60)  NOT NULL DEFAULT '',
  `price`       VARCHAR(60)  NOT NULL DEFAULT '',
  `description` TEXT NULL,
  `sort_order`  INT NOT NULL DEFAULT 100,
  `active`      TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ny_categories` (
  `slug`        VARCHAR(40)  NOT NULL,
  `label`       VARCHAR(120) NOT NULL,
  `description` TEXT NULL,
  `sort_order`  INT NOT NULL DEFAULT 100,
  `active`      TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Example weekly schedule (safe to re-run, duplicates itself only if you rerun).
INSERT INTO `ny_classes` (`name`, `description`, `teacher`, `capacity`, `day_of_week`, `start_time`, `end_time`, `room`) VALUES
('Hatha yoga – začátečníci', 'Klidná praxe pro začátečníky.',      'Klára',   14, 1, '17:30', '18:45', 'Sál A'),
('Vinyasa flow',              'Dynamická sekvence.',                'Lenka',   12, 1, '19:00', '20:15', 'Sál A'),
('Yin yoga',                  'Pomalá, hluboká praxe.',             'Jitka',   10, 2, '18:00', '19:15', 'Sál B'),
('Ashtanga – Mysore',         'Sebeřízená ashtangová praxe.',       'Tomáš',   10, 3, '06:30', '08:00', 'Sál A'),
('Hatha yoga – pokročilí',    'Pro zkušené praktikující.',          'Klára',   12, 3, '18:00', '19:15', 'Sál A'),
('Prenatal yoga',             'Jemná praxe pro těhotné.',           'Eva',      8, 4, '10:00', '11:00', 'Sál B'),
('Power yoga',                'Silová dynamická hodina.',           'Lenka',   14, 4, '19:00', '20:15', 'Sál A'),
('Restorative yoga',          'Regenerační a klidná praxe.',        'Christina',12, 5, '17:30', '18:45', 'Sál B'),
('Sunday flow',               'Nedělní vinyasa.',                   'Klára',   16, 7, '09:30', '11:00', 'Sál A');
