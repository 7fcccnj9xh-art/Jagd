-- ============================================================
-- Jagd-Verwaltung: Datenbankschema
-- Datenbank: jagd
-- ============================================================
-- Erstellt: 2026-03-15
-- Tabellen:
--   users                          – Benutzer (Login)
--   protokoll                      – Änderungsprotokoll
--   wildbeobachtungen              – Wildbeobachtungen
--   einrichtungen                  – Jagdliche Einrichtungen (Hochsitz etc.)
--   einrichtungen_fotos            – Foto-Uploads je Einrichtung
--   ausruestung                    – Ausrüstungsverwaltung
--   wartungsprotokoll              – Wartungsprotokoll je Ausrüstungsgegenstand
--   dokumente                      – Behördendokumente (Jagdschein, WBK etc.)
--   wildaufnahmen_klassifizierungen – Wildkamera-Klassifizierungen (aus Wildaufnahmen-Modul)
-- ============================================================

CREATE DATABASE IF NOT EXISTS `jagd`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `jagd`;

-- ------------------------------------------------------------
-- Benutzer (Admin-Login)
-- Gleiche Struktur wie wildbret.users
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `username`      VARCHAR(50)  NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `email`         VARCHAR(100),
  `name`          VARCHAR(100),
  `role`          ENUM('admin','user') DEFAULT 'admin',
  `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `last_login`    TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Änderungsprotokoll
-- Gleiche Struktur wie wildbret.protokoll
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `protokoll` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `tabelle`       VARCHAR(50)  NOT NULL,
  `datensatz_id`  INT          NOT NULL,
  `aktion`        VARCHAR(100) NOT NULL,
  `alter_wert`    TEXT,
  `neuer_wert`    TEXT,
  `benutzer`      VARCHAR(100),
  `ip_adresse`    VARCHAR(45),
  `zeitstempel`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Einrichtungen (Hochsitz, Wildkamera-Standort etc.)
-- Muss vor wildbeobachtungen stehen (FK-Abhängigkeit)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `einrichtungen` (
  `id`              INT AUTO_INCREMENT PRIMARY KEY,
  `name`            VARCHAR(100) NOT NULL,
  `typ`             ENUM(
                      'Hochsitz',
                      'Drückjagdbock',
                      'Ansitzleiter',
                      'Wildkamera',
                      'Kirrung',
                      'Fütterung',
                      'Salzlecke'
                    ) NOT NULL,
  `geo_lat`         DECIMAL(10,8) NULL COMMENT 'Breitengrad (WGS84)',
  `geo_lng`         DECIMAL(11,8) NULL COMMENT 'Längengrad (WGS84)',
  `baujahr`         YEAR          NULL,
  `zustand`         ENUM('gut','reparaturbeduerftig','gesperrt') DEFAULT 'gut',
  `letzte_wartung`  DATE          NULL,
  `wildkamera_id`   VARCHAR(100)  NULL COMMENT 'Kameraname aus Wildaufnahmen-Modul (Freitext)',
  `notizen`         TEXT,
  `created_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Foto-Uploads je Einrichtung (1:n)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `einrichtungen_fotos` (
  `id`              INT AUTO_INCREMENT PRIMARY KEY,
  `einrichtung_id`  INT          NOT NULL,
  `dateiname`       VARCHAR(255) NOT NULL COMMENT 'Zufälliger Dateiname in uploads/einrichtungen/',
  `originalname`    VARCHAR(255) NULL,
  `created_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`einrichtung_id`) REFERENCES `einrichtungen`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Wildbeobachtungen
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `wildbeobachtungen` (
  `id`              INT AUTO_INCREMENT PRIMARY KEY,
  `datum`           DATE         NOT NULL,
  `uhrzeit`         TIME         NOT NULL,
  `geo_lat`         DECIMAL(10,8) NOT NULL COMMENT 'Breitengrad (WGS84)',
  `geo_lng`         DECIMAL(11,8) NOT NULL COMMENT 'Längengrad (WGS84)',
  `wildart`         VARCHAR(50)  NOT NULL,
  `anzahl`          INT          NOT NULL DEFAULT 1,
  `geschlecht_alter` VARCHAR(100) NULL COMMENT 'z.B. Bock adult, Ricke mit Kitz',
  `witterung`       VARCHAR(100) NULL COMMENT 'Sonne, Regen, Schnee, bedeckt, ...',
  `beobachter`      VARCHAR(100) NULL,
  `einrichtung_id`  INT          NULL COMMENT 'Optionaler Bezug zu Hochsitz o.ä.',
  `notizen`         TEXT,
  `created_by`      INT          NULL COMMENT 'FK → users(id)',
  `created_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`einrichtung_id`) REFERENCES `einrichtungen`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`)     REFERENCES `users`(`id`)         ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Ausrüstung
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ausruestung` (
  `id`                  INT AUTO_INCREMENT PRIMARY KEY,
  `bezeichnung`         VARCHAR(200) NOT NULL,
  `kategorie`           ENUM('Waffe','Optik','Bekleidung','Technik','Sonstiges') NOT NULL,
  `hersteller`          VARCHAR(100) NULL,
  `modell`              VARCHAR(100) NULL,
  `seriennummer`        VARCHAR(100) NULL,
  `kaufdatum`           DATE         NULL,
  `kaufpreis`           DECIMAL(8,2) NULL,
  `zustand`             ENUM('gut','reparaturbeduerftig','defekt') DEFAULT 'gut',
  -- Waffen-Zusatzfelder (nur relevant wenn kategorie = 'Waffe')
  `kaliber`             VARCHAR(50)  NULL,
  `magazin_kapazitaet`  TINYINT      NULL,
  `letzter_beschuss`    DATE         NULL,
  `naechster_beschuss`  DATE         NULL,
  -- Gemeinsam
  `foto_pfad`           VARCHAR(255) NULL COMMENT 'Relativer Pfad in uploads/ausruestung/',
  `notizen`             TEXT,
  `created_at`          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Wartungsprotokoll je Ausrüstungsgegenstand
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `wartungsprotokoll` (
  `id`                  INT AUTO_INCREMENT PRIMARY KEY,
  `ausruestung_id`      INT          NOT NULL,
  `datum`               DATE         NOT NULL,
  `taetigkeit`          TEXT         NOT NULL,
  `kosten`              DECIMAL(8,2) NULL,
  `naechste_faelligkeit` DATE        NULL,
  `created_at`          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`ausruestung_id`) REFERENCES `ausruestung`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Behördendokumente (Jagdschein, WBK, Jahresjagdschein, Sonstiges)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `dokumente` (
  `id`                INT AUTO_INCREMENT PRIMARY KEY,
  `titel`             VARCHAR(200) NOT NULL,
  `typ`               ENUM('Jagdschein','WBK','Jahresjagdschein','Sonstiges') NOT NULL,
  `dokument_nr`       VARCHAR(100) NULL COMMENT 'Ausweisnummer / Registriernummer',
  `aussteller`        VARCHAR(200) NULL COMMENT 'Ausstellende Behörde',
  `ausstellungsdatum` DATE         NULL,
  `ablaufdatum`       DATE         NULL,
  `erinnerung_tage`   INT          NOT NULL DEFAULT 60 COMMENT 'Erinnerung X Tage vor Ablauf',
  `ausruestung_id`    INT          NULL COMMENT 'WBK → zugehörige Waffe (FK → ausruestung)',
  `dateiname`         VARCHAR(255) NULL COMMENT 'Scan-Upload in uploads/behoerden/',
  `originalname`      VARCHAR(255) NULL,
  `notizen`           TEXT,
  `created_at`        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`ausruestung_id`) REFERENCES `ausruestung`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Wildkamera-Klassifizierungen (aus Wildaufnahmen-Modul)
-- Ersetzt die bisherige Excel-Ausgabe von foto_video_classifier.py
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `wildaufnahmen_klassifizierungen` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `dateiname`   VARCHAR(255) NOT NULL COMMENT 'Originaldateiname',
  `kamera`      VARCHAR(100) NULL     COMMENT 'Kameraname (Unterordnername)',
  `typ`         ENUM('Bild','Video')  NULL,
  `kategorie`   VARCHAR(50)  NULL     COMMENT 'Reh, Wildschwein, Fuchs, Unbekannt ...',
  `datum`       DATE         NULL     COMMENT 'Aufnahmedatum (aus EXIF oder Dateiname)',
  `uhrzeit`     TIME         NULL     COMMENT 'Aufnahmezeit',
  `zielpfad`    VARCHAR(500) NULL     COMMENT 'Pfad der sortierten Datei',
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
