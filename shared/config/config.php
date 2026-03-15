<?php
// ============================================================
// Jagd-Verwaltung: Allgemeine Konfiguration
// ============================================================

// Basis-URL (Trailing Slash!)
define('BASE_URL', '/');

// Anwendungsname
define('APP_NAME', 'Jagd-Verwaltung');

// Session-Name
define('SESSION_NAME', 'jagd_session');

// Session-Lebensdauer in Sekunden (8 Stunden)
define('SESSION_LIFETIME', 28800);

// Upload-Verzeichnis
define('UPLOAD_DIR', dirname(__DIR__, 2) . '/uploads/');
define('UPLOAD_URL', BASE_URL . 'uploads/');

// Erlaubte Dateiendungen
define('ALLOWED_IMAGE_EXTENSIONS', ['jpg', 'jpeg', 'png', 'webp']);
define('ALLOWED_DOC_EXTENSIONS',   ['jpg', 'jpeg', 'png', 'webp', 'pdf']);
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024); // 10 MB

// Wildarten (gemeinsame Liste für alle Module)
define('WILDARTEN', [
    'Reh',
    'Rotwild',
    'Wildschwein',
    'Damwild',
    'Muffelwild',
    'Fuchs',
    'Hase',
    'Fasan',
    'Rebhuhn',
    'Ente',
    'Dachs',
    'Sonstiges',
]);

// Einrichtungstypen
define('EINRICHTUNGSTYPEN', [
    'Hochsitz',
    'Drückjagdbock',
    'Ansitzleiter',
    'Wildkamera',
    'Kirrung',
    'Fütterung',
    'Salzlecke',
]);

// Witterungsbedingungen
define('WITTERUNG_OPTIONEN', [
    'Sonnig',
    'Bewölkt',
    'Bedeckt',
    'Regen',
    'Schnee',
    'Nebel',
    'Wind',
]);

// Karten-Mittelpunkt (Heimrevier – anpassen!)
define('KARTE_LAT', 49.2837);
define('KARTE_LNG',  8.5350);
define('KARTE_ZOOM', 14);

// Zeitzone
date_default_timezone_set('Europe/Berlin');

// Fehlerausgabe (Entwicklung: true, Produktion: false)
define('DEBUG_MODE', true);

if (DEBUG_MODE) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}
