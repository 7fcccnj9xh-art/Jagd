<?php
// ============================================================
// Jagd-Verwaltung: Datenbankkonfiguration
// ============================================================

// Lokale Konfiguration zuerst einlesen (überschreibt Standardwerte)
// database.local.php ist in .gitignore und wird nicht eingecheckt
$_localDb = __DIR__ . '/database.local.php';
if (file_exists($_localDb)) {
    require $_localDb;
}

// Standardwerte – werden nur gesetzt wenn database.local.php sie nicht definiert hat
defined('DB_HOST')    || define('DB_HOST',    '192.168.0.101');
defined('DB_PORT')    || define('DB_PORT',    '3306');
defined('DB_USER')    || define('DB_USER',    'root');
defined('DB_PASS')    || define('DB_PASS',    '');
defined('DB_NAME')    || define('DB_NAME',    'jagd');
defined('DB_CHARSET') || define('DB_CHARSET', 'utf8mb4');

/**
 * Gibt eine PDO-Verbindung zur jagd-Datenbank zurück (Singleton).
 */
function getDB(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die('Datenbankverbindung fehlgeschlagen. Bitte shared/config/database.local.php prüfen.');
        }
    }

    return $pdo;
}

/**
 * Gibt eine PDO-Verbindung zur wildbret-Datenbank zurück (Singleton).
 * Wird nur für Dashboard-Widgets benötigt (Lesezugriff).
 */
function getDBWildbret(): PDO
{
    static $pdo_wb = null;

    if ($pdo_wb === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=wildbret;charset=%s',
            DB_HOST, DB_PORT, DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo_wb = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Wildbret-DB optional – kein harter Fehler
            return getDB();
        }
    }

    return $pdo_wb;
}
