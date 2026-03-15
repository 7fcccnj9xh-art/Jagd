# Jagd-Verwaltung

Zentrales Jagdverwaltungs-System. Integiert die Module Wildbret und Wildaufnahmen und ergänzt sie um das Reviermanagement.

| | |
|---|---|
| **Sprache** | PHP 8 + Python 3 |
| **Datenbank** | MySQL 8 auf `192.168.0.101:42763`, DB: `jagd` |
| **Frontend** | Bootstrap 5.3, Leaflet.js, Font Awesome (CDN) |
| **Server** | Ein PHP Built-in Server (`php -S localhost:8090`) |
| **Aufruf** | http://localhost:8090 |
| **Status** | In Entwicklung |

---

## Module

| Modul | URL | Beschreibung |
|---|---|---|
| Dashboard | http://localhost:8090 | Übersicht aller Module |
| Reviermanagement | http://localhost:8090/modules/reviermanagement/ | Wildbeobachtungen, Einrichtungen, Ausrüstung, Behörden |
| Wildbret | http://localhost:8090/modules/wildbret/ | Wildbret-Verwaltung (eigene DB: `wildbret`) |
| Wildaufnahmen-Sortierung | http://localhost:5001 | Flask-App, separater Start erforderlich |

---

## Setup & Betrieb

### Voraussetzungen

- PHP 8.0+
- Python 3.8+ (für Wildaufnahmen)
- MySQL 8 (erreichbar auf `192.168.0.101:42763`)
- `mysql-client` (`brew install mysql-client`)

### Installation

```bash
# 1. Repository klonen
cd /Users/whaa/Documents/GitHub
git clone <repo-url> Jagd
cd Jagd

# 2. Symlinks prüfen (sollten bereits vorhanden sein)
ls modules/wildbret   # → zeigt Wildbret-Verzeichnis

# 3. Datenbanken anlegen
mysql -h 192.168.0.101 -P 42763 -u root -p < sql/schema_jagd.sql
mysql -h 192.168.0.101 -P 42763 -u root -p wildbret < modules/wildbret/sql/schema.sql

# 4. Lokale DB-Konfiguration anlegen
cp shared/config/database.local.example.php shared/config/database.local.php
# → Passwort in database.local.php eintragen

# 5. Server starten
php -S localhost:8090
```

### Erster Benutzer

Beim ersten Aufruf von http://localhost:8090 erscheint automatisch das Registrierformular (solange `jagd.users`-Tabelle leer ist).

---

## Projektstruktur

```
Jagd/
├── dashboard/              ← Haupt-Dashboard
├── modules/
│   ├── reviermanagement/   ← Neu entwickelt
│   ├── wildbret/           ← Symlink → ../Wildbret
│   └── wildaufnahmen/      ← Symlink → ../Wildaufnahmen (optional)
├── shared/
│   ├── auth/               ← Einheitliches Login
│   ├── config/             ← DB-Konfiguration
│   └── ui/                 ← Header, Footer, CSS
├── sql/
│   └── schema_jagd.sql     ← Datenbank-Schema (jagd)
├── login.php
└── logout.php
```

---

## Änderungshistorie

| Datum | Änderung |
|---|---|
| 2026-03-15 | Projektstruktur angelegt, schema_jagd.sql erstellt |
| 2026-03-15 | Grundgerüst: shared/auth, shared/config, shared/ui |
| 2026-03-15 | Login, Dashboard, Reviermanagement-Module (Gerüst) |
| 2026-03-15 | Wildbret via Symlink integriert, ein gemeinsamer Server |
