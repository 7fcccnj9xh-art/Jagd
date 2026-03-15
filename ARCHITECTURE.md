# Jagd – Architekturplan

**Status:** Entwurf – wartet auf Freigabe
**Erstellt:** 2026-03-15
**Basis:** Analyse von Wildaufnahmen + Wildbret

---

## 1. Ist-Zustand der bestehenden Projekte

### 1.1 Wildaufnahmen

| Eigenschaft | Wert |
|---|---|
| Sprache | Python 3 |
| Schnittstellen | Claude API (Anthropic), DeepFaune (lokal), ffmpeg |
| Ausgabe | Sortierte Ordnerstruktur, Excel-Datei, HTML-Auswertung |
| Weboberfläche | Nur lokal via Flask (sortier_server.py), Deployment via FTP |
| Konfiguration | config.json (lokal, nicht in Git) |
| Deployment | FTP nach wild.haasch.de via upload_wild.cfg |
| Besonderheit | Kein PHP, keine Datenbank – rein dateisystembasiert |

### 1.2 Wildbret

| Eigenschaft | Wert |
|---|---|
| Sprache | PHP 8, kein Framework |
| Datenbank | MySQL 8 auf 192.168.0.101:42763, DB: `wildbret` |
| Frontend | Bootstrap 5.3, DataTables, Chart.js, Font Awesome (alle CDN) |
| Auth | Session-basiert, bcrypt (cost 12), CSRF-Token |
| Deployment | PHP Built-in Server lokal, optional IONOS-Upload |
| Karten | Leaflet.js + OpenStreetMap (bereits in erlegte_stuecke.php) |
| GPS | Als DECIMAL(10,8) in DB gespeichert (unverschlüsselt) |

**Tabellen:** users, lagerorte, erlegte_stuecke, preise, zerlegte_teile, verwendung, plan_verwendung, abgaben, protokoll

---

## 2. Gesamtarchitektur „Jagd"

### Konzept: Umbrella-Projekt mit Modul-Referenzen

Das Jagd-Projekt ist eine neue, eigenständige PHP-Anwendung, die:
- Wildaufnahmen und Wildbret als **Git-Submodule** einbindet (kein Code-Duplikat)
- Ein neues **Reviermanagement**-Modul vollständig implementiert
- Ein gemeinsames **Haupt-Dashboard** mit Widgets aus allen Modulen bietet
- Eine **gemeinsame Datenbankinstanz** nutzt (neue DB `jagd`, Wildbret-Tabellen bleiben in `wildbret`)

### Warum getrennte Datenbanken?

Wildbret ist bereits produktiv (83 Stücke, 1240 Teile). Eine Migration birgt Risiken.
Stattdessen: Cross-DB-Queries via `wildbret.erlegte_stuecke` im Dashboard.
Der MySQL-User erhält Leserechte auf beide Datenbanken.

---

## 3. Projektstruktur (Dateisystem)

```
/Users/whaa/Documents/GitHub/Jagd/
├── ARCHITECTURE.md
├── README.md
├── .gitmodules                  ← Submodul-Definitionen
│
├── modules/
│   ├── wildaufnahmen/           ← Git-Submodul → Wildaufnahmen-Repo
│   ├── wildbret/                ← Git-Submodul → Wildbret-Repo
│   └── reviermanagement/        ← neu (PHP, eigene Seiten)
│       ├── wildbeobachtungen.php
│       ├── einrichtungen.php
│       ├── ausruestung.php
│       ├── behoerden.php
│       └── api/
│           ├── beobachtung_save.php
│           ├── einrichtung_save.php
│           ├── ausruestung_save.php
│           └── export.php
│
├── shared/
│   ├── config/
│   │   ├── config.php           ← APP_NAME, BASE_URL, Datenbankname, Session-Name
│   │   └── database.php         ← PDO-Singleton für jagd-DB + Lesezugriff wildbret-DB
│   ├── auth/
│   │   └── auth.php             ← sessionStart(), requireLogin(), login() – aus Wildbret übernommen
│   ├── csrf/
│   │   └── csrf.php             ← csrfToken(), csrfField(), csrfVerify() – aus Wildbret
│   ├── functions/
│   │   └── functions.php        ← e(), datumDE(), statusBadge(), flash(), protokollieren()
│   └── ui/
│       ├── header.php           ← HTML-Kopf, Bootstrap 5, Navigation (alle Module)
│       ├── footer.php           ← HTML-Abschluss, JS-Inits
│       └── assets/
│           ├── css/
│           │   └── jagd.css     ← Naturtöne, gemeinsames Design (basiert auf Wildbret style.css)
│           ├── js/
│           │   └── jagd.js      ← DataTables-Init, Toast, AJAX-Helpers, Leaflet-Helpers
│           └── images/
│               └── logo.svg
│
├── dashboard/
│   └── index.php                ← Haupt-Dashboard (Widgets aus allen Modulen)
│
├── sync/
│   └── sync_jagd.py             ← Führt sync_wild.py aus, weitere Sync-Aufgaben
│
├── sql/
│   ├── schema_jagd.sql          ← Neues Schema für jagd-DB (alle Reviermanagement-Tabellen)
│   └── migration_wildbret.sql   ← Migrations-Script für bestehende wildbret-DB (Ergänzungen)
│
├── uploads/                     ← Datei-Uploads (Fotos, Scans, Dokumente)
│   ├── einrichtungen/
│   ├── ausruestung/
│   └── behoerden/
│
├── index.php                    ← Redirect → dashboard/index.php
├── login.php                    ← Einheitliches Login (aus Wildbret übernommen, angepasst)
└── logout.php
```

---

## 4. Technologie-Stack

| Schicht | Technologie | Begründung |
|---|---|---|
| Backend | PHP 8, kein Framework | Konsistenz mit Wildbret |
| Datenbank | MySQL 8 (192.168.0.101:42763) | Bestehende Instanz weiternutzen |
| Frontend | Bootstrap 5.3 (CDN) | Bereits in Wildbret, mobile-first |
| Icons | Font Awesome 6.5 (CDN) | Bereits in Wildbret |
| Tabellen | DataTables 1.13 (CDN) | Bereits in Wildbret |
| Charts | Chart.js 4.x (CDN) | Bereits in Wildbret |
| Karten | Leaflet.js + OpenStreetMap (CDN) | Bereits in Wildbret, kostenlos |
| Python-Sync | Python 3 + ftplib | Konsistenz mit Wildaufnahmen |
| Deployment | FTP → IONOS | Konsistenz mit bestehenden Projekten |

**Keine neuen Abhängigkeiten** ohne ausdrückliche Freigabe.

---

## 5. Auth & Sicherheit

### 5.1 Gemeinsames Login

Das Login-System aus `Wildbret/includes/auth.php` wird nach `shared/auth/auth.php` kopiert und leicht angepasst:
- Session-Name: `jagd_session` (statt `wildbret_session`)
- Datenbank: `jagd.users` (neue Tabelle, gleiche Struktur wie `wildbret.users`)
- **Wildbret bleibt eigenständig** mit seiner eigenen Auth – kein SSO in Phase 1

### 5.2 Sicherheitsmaßnahmen (identisch zu Wildbret)

| Maßnahme | Implementierung |
|---|---|
| CSRF | Session-Token in allen POST-Formularen und AJAX-Requests |
| XSS | `e()` = `htmlspecialchars()` bei jeder Ausgabe |
| SQL-Injection | Ausschließlich PDO Prepared Statements |
| Passwörter | bcrypt cost=12 |
| Session | HttpOnly + SameSite=Strict Cookie |
| Datei-Uploads | Whitelist (jpg, jpeg, png, webp, pdf), max 10 MB, zufällige Dateinamen |

### 5.3 GPS-Koordinaten

**Entscheidung:** GPS-Koordinaten im Reviermanagement werden **nicht in der DB verschlüsselt**.

**Begründung:**
- Wildbret speichert GPS bereits unverschlüsselt (DECIMAL) – Konsistenz
- Die Datenbank ist nicht öffentlich zugänglich (lokales Netz, 192.168.0.101)
- AES_ENCRYPT/AES_DECRYPT in MySQL würde Abfragen und Leaflet-Integration stark komplizieren
- Der Anwendungsfall (privates Jagdrevier, lokale App) rechtfertigt keinen erhöhten Aufwand

Falls später gewünscht: Verschlüsselung auf Anwendungsebene nachrüstbar, ohne Schema-Änderung.

---

## 6. Datenbank-Architektur

### 6.1 Zwei Datenbanken, ein Server

```
MySQL 192.168.0.101:42763
├── wildbret          ← unverändert (bestehende Tabellen)
└── jagd              ← neu (alle Reviermanagement-Tabellen + users)
```

Der DB-User `root` hat bereits Zugriff auf beide. Im Code wird `jagd` als Primär-DB genutzt; für Dashboard-Widgets werden `wildbret.*`-Tabellen direkt per qualifiziertem Tabellennamen angesprochen.

### 6.2 Neues Schema `jagd` (Übersicht)

```
jagd
├── users                        ← Benutzer (gleiche Struktur wie wildbret.users)
├── protokoll                    ← Änderungsprotokoll (gleiche Struktur)
│
├── [Wildbeobachtungen]
│   └── wildbeobachtungen        ← Datum, Uhrzeit, GPS, Wildart, Anzahl, Witterung, Beobachter
│
├── [Einrichtungen]
│   ├── einrichtungen            ← Hochsitz, Wildkamera-Standort etc.
│   └── einrichtungen_fotos      ← 1:n Foto-Uploads je Einrichtung
│
├── [Ausrüstung]
│   ├── ausruestung              ← Waffen, Optik, Bekleidung, Technik, Sonstiges
│   └── wartungsprotokoll        ← Datum, Tätigkeit, Kosten, nächste Fälligkeit
│
└── [Behörden]
    └── dokumente                ← Jagdschein, WBK, Jahresjagdschein, beliebige PDFs
```

### 6.3 Detailliertes Schema (alle neuen Tabellen)

```sql
-- Wildbeobachtungen
CREATE TABLE wildbeobachtungen (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  datum           DATE        NOT NULL,
  uhrzeit         TIME        NOT NULL,
  geo_lat         DECIMAL(10,8) NOT NULL,
  geo_lng         DECIMAL(11,8) NOT NULL,
  wildart         VARCHAR(50) NOT NULL,
  anzahl          INT DEFAULT 1,
  geschlecht_alter VARCHAR(50),  -- z.B. "Bock adult", "Ricke mit Kitz"
  witterung       VARCHAR(100),  -- Sonne, Regen, Schnee, bedeckt
  beobachter      VARCHAR(100),
  einrichtung_id  INT NULL,      -- FK → einrichtungen (optionaler Bezug)
  notizen         TEXT,
  created_by      INT,           -- FK → users
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Einrichtungen
CREATE TABLE einrichtungen (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  name            VARCHAR(100) NOT NULL,
  typ             ENUM('Hochsitz','Drückjagdbock','Ansitzleiter',
                       'Wildkamera','Kirrung','Fütterung','Salzlecke') NOT NULL,
  geo_lat         DECIMAL(10,8),
  geo_lng         DECIMAL(11,8),
  baujahr         YEAR,
  zustand         ENUM('gut','reparaturbeduerftig','gesperrt') DEFAULT 'gut',
  letzte_wartung  DATE,
  wildkamera_id   VARCHAR(100) NULL,  -- Referenz auf Kameraname in Wildaufnahmen
  notizen         TEXT,
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE einrichtungen_fotos (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  einrichtung_id  INT NOT NULL,
  dateiname       VARCHAR(255) NOT NULL,  -- zufälliger Dateiname in uploads/einrichtungen/
  originalname    VARCHAR(255),
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (einrichtung_id) REFERENCES einrichtungen(id) ON DELETE CASCADE
);

-- Ausrüstung
CREATE TABLE ausruestung (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  bezeichnung     VARCHAR(200) NOT NULL,
  kategorie       ENUM('Waffe','Optik','Bekleidung','Technik','Sonstiges') NOT NULL,
  hersteller      VARCHAR(100),
  modell          VARCHAR(100),
  seriennummer    VARCHAR(100),
  kaufdatum       DATE,
  kaufpreis       DECIMAL(8,2),
  zustand         ENUM('gut','reparaturbeduerftig','defekt') DEFAULT 'gut',
  -- Waffen-Zusatzfelder (nur wenn kategorie = 'Waffe')
  kaliber         VARCHAR(50)  NULL,
  magazin_kap     TINYINT      NULL,
  letzter_beschuss DATE        NULL,
  naechster_beschuss DATE      NULL,
  -- Gemeinsam
  foto_pfad       VARCHAR(255) NULL,
  notizen         TEXT,
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- FK-Verknüpfung Waffe ↔ erlegtes Stück (wildbret-DB)
-- Gespeichert als INTEGER (wildbret.erlegte_stuecke.id), kein DB-FK über DB-Grenzen
-- Prüfung erfolgt auf Anwendungsebene

CREATE TABLE wartungsprotokoll (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  ausruestung_id  INT NOT NULL,
  datum           DATE NOT NULL,
  taetigkeit      TEXT NOT NULL,
  kosten          DECIMAL(8,2),
  naechste_faelligkeit DATE,
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (ausruestung_id) REFERENCES ausruestung(id) ON DELETE CASCADE
);

-- Behördendokumente (Jagdschein, WBK, beliebige Uploads)
CREATE TABLE dokumente (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  titel           VARCHAR(200) NOT NULL,
  typ             ENUM('Jagdschein','WBK','Jahresjagdschein','Sonstiges') NOT NULL,
  -- Jagdschein-Felder
  dokument_nr     VARCHAR(100) NULL,
  aussteller      VARCHAR(200) NULL,
  ausstellungsdatum DATE       NULL,
  ablaufdatum     DATE         NULL,
  erinnerung_tage INT DEFAULT 60,  -- Erinnerung X Tage vor Ablauf
  -- Verknüpfungen
  ausruestung_id  INT NULL,        -- WBK → zugehörige Waffe
  -- Upload
  dateiname       VARCHAR(255) NULL,  -- Scan-Upload
  originalname    VARCHAR(255) NULL,
  notizen         TEXT,
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (ausruestung_id) REFERENCES ausruestung(id) ON DELETE SET NULL
);
```

---

## 7. Modul-Beschreibung

### 7.1 Wildbeobachtungen (`modules/reviermanagement/wildbeobachtungen.php`)

**Seiten:**
- Liste aller Beobachtungen (DataTables, filterbar nach Wildart / Datum / Beobachter)
- Erfassungsformular (Modal): Datum, Uhrzeit, GPS-Picker (Leaflet-Karte), Wildart, Anzahl, Geschlecht/Alter, Witterung, Beobachter, Einrichtungsbezug, Notizen
- Kartenansicht (separates Tab): Alle Beobachtungen als farbkodierte Marker (nach Wildart), Cluster bei Zoom-out
- Statistikseite: Häufigkeit pro Wildart (Chart.js), Uhrzeit-Heatmap, Saisonvergleich

**Export:** CSV-Download, PDF-Bericht (via PHP-HTML → Browser-Druck)

### 7.2 Einrichtungen (`modules/reviermanagement/einrichtungen.php`)

**Seiten:**
- Liste aller Einrichtungen (DataTables, filterbar nach Typ / Zustand)
- Erfassungsformular (Modal): alle Felder inkl. Foto-Upload (multiple), GPS-Picker
- Kartenansicht: Alle Einrichtungen auf Leaflet-Karte, Layer-Auswahl nach Typ, kombinierbar mit Wildbeobachtungs-Layer
- Wartungsübersicht: Einrichtungen nach Datum letzter Wartung sortiert, Fälligkeits-Ampel

**Verknüpfung:** Wildkamera-Standorte können mit Kameranamen aus Wildaufnahmen verlinkt werden (Freitextfeld, kein automatischer Sync in Phase 1).

### 7.3 Ausrüstung (`modules/reviermanagement/ausruestung.php`)

**Seiten:**
- Liste nach Kategorie (Tab-Navigation: Waffen / Optik / Bekleidung / Technik / Sonstiges)
- Erfassungsformular (Modal): gemeinsame Felder + Waffen-Zusatzfelder (nur wenn Kategorie = Waffe)
- Wartungsprotokoll-Tab je Ausrüstungsgegenstand (Inline-Tabelle + Erfassungsmodal)
- Fälligkeitsübersicht: nächste Wartungen der nächsten 90 Tage

### 7.4 Behörden (`modules/reviermanagement/behoerden.php`)

**Seiten:**
- Übersicht aller Dokumente mit Ablaufdatum-Ampel (grün/gelb/rot)
- Erfassungsformular nach Dokumenttyp (Jagdschein, WBK, Jahresjagdschein, Sonstiges)
- Scan-Upload (PDF, JPG, PNG, max 10 MB)
- Erinnerungs-Widget: Dokumente die in den nächsten 90 Tagen ablaufen

---

## 8. Haupt-Dashboard (`dashboard/index.php`)

Das Dashboard aggregiert Daten aus allen Modulen:

| Widget | Datenquelle | Abfrage |
|---|---|---|
| Letzte Wildaufnahmen (5) | `wildbret.erlegte_stuecke` | ORDER BY erlegungsdatum DESC LIMIT 5 |
| Letzte Erlegungen (5) | `wildbret.erlegte_stuecke` | ORDER BY created_at DESC LIMIT 5 |
| Ablaufende Dokumente (90 Tage) | `jagd.dokumente` | WHERE ablaufdatum BETWEEN NOW() AND NOW()+90 Tage |
| Wildbeobachtungen letzte 7 Tage | `jagd.wildbeobachtungen` | COUNT, GROUP BY wildart, letzte 7 Tage |
| Einrichtungen mit Wartungsbedarf | `jagd.einrichtungen` | WHERE zustand != 'gut' |
| Schnellnavigation | — | Links zu allen Modulen |

---

## 9. Deployment

### 9.1 Lokaler Betrieb

```bash
# PHP Built-in Server (analog zu Wildbret)
cd /Users/whaa/Documents/GitHub/Jagd
php -S localhost:8090   # anderer Port als Wildbret (8080)
# → http://localhost:8090/login.php
```

### 9.2 IONOS-Upload (optional, Phase 2)

Analog zu `upload_wild.cfg` / `upload_wildbret.cfg`:
- Neue Konfigurationsdatei `upload_jagd.cfg`
- `sync/sync_jagd.py` führt FTP-Upload aus
- Ziel-URL: `jagd.haasch.de` (Subdomain analog zu `wild.haasch.de`)

---

## 10. Implementierungsreihenfolge (nach Freigabe)

1. **Grundgerüst** (shared/config, shared/auth, shared/ui, login.php, logout.php, index.php)
2. **Datenbank** (sql/schema_jagd.sql, sql/migration_wildbret.sql)
3. **Dashboard** (mit Platzhalter-Widgets, echte Daten kommen mit den Modulen)
4. **Reviermanagement – Einrichtungen** (einfachstes Modul zum Einstieg)
5. **Reviermanagement – Wildbeobachtungen** (inkl. Kartenansicht)
6. **Reviermanagement – Ausrüstung** (inkl. Wartungsprotokoll)
7. **Reviermanagement – Behörden** (inkl. Dokument-Upload)
8. **Dashboard-Widgets** (alle Module fertig → Widgets mit echten Daten)
9. **Deployment** (upload_jagd.cfg, sync_jagd.py)

---

## 11. Offene Fragen / Entscheidungen vor Implementierung

| # | Frage | Optionen |
|---|---|---|
| 1 | **Git-Submodule oder Symlinks?** | Submodule = sauber, erfordert `git submodule update --init`; Symlinks = einfacher lokal, unschön im Repo |
| 2 | **Gemeinsame users-Tabelle oder getrennt?** | Getrennt (jagd.users + wildbret.users) = kein SSO aber kein Migrations-Risiko. Gemeinsam würde Wildbret-Migration erfordern |
| 3 | **Wildbeobachtungen-Export als PDF?** | Browser-Druck (kein externes Tool) oder PHP-PDF-Bibliothek (neue Abhängigkeit) |
| 4 | **Karte für Reviermanagement: Hintergrundlayer?** | OpenStreetMap (kostenlos, keine API-Key) oder zusätzlich Luftbild (z.B. Esri, kostenlos für nicht-kommerziell) |
| 5 | **Wildaufnahmen-Integration im Dashboard?** | a) Datenbank-Query (benötigt Wildaufnahmen-Daten in MySQL) b) REST-API von sortier_server.py c) Excel-Datei parsen (Python) d) Erstmal weglassen |

---

## 12. Was NICHT verändert wird

- **Wildbret-Codebase** bleibt vollständig unverändert (eigenständiges Projekt)
- **Wildaufnahmen-Codebase** bleibt vollständig unverändert
- **MySQL-DB `wildbret`** wird nicht migriert (nur erweitert falls nötig durch migration_wildbret.sql)
- **Deployment von Wildaufnahmen** (`wild.haasch.de`) wird nicht geändert
