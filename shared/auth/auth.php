<?php
// ============================================================
// Jagd-Verwaltung: Auth-System
// Übernommen und angepasst aus Wildbret/includes/auth.php
// ============================================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

/**
 * Session starten mit sicheren Einstellungen.
 */
function sessionStart(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_name(SESSION_NAME);

    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => false,   // true wenn HTTPS
        'httponly' => true,
        'samesite' => 'Strict',
    ]);

    session_start();
}

/**
 * Prüft ob der Benutzer eingeloggt ist. Leitet sonst zu login.php weiter.
 */
function requireLogin(): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . 'login.php');
        exit;
    }
}

/**
 * Login-Versuch. Gibt true bei Erfolg zurück.
 */
function login(string $username, string $password): bool
{
    $db   = getDB();
    $stmt = $db->prepare('SELECT id, username, password_hash, name, role FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    session_regenerate_id(false);

    $_SESSION['user_id']  = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['name']     = $user['name'];
    $_SESSION['role']     = $user['role'];

    // Letzten Login aktualisieren
    $db->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')
       ->execute([$user['id']]);

    return true;
}

/**
 * Session zerstören und ausloggen.
 */
function sessionDestroy(): void
{
    session_unset();
    session_destroy();
    setcookie(SESSION_NAME, '', time() - 3600, '/');
}

/**
 * Gibt true zurück wenn der aktuelle Benutzer Admin ist.
 */
function isAdmin(): bool
{
    return ($_SESSION['role'] ?? '') === 'admin';
}

/**
 * Gibt den aktuellen Benutzernamen zurück.
 */
function currentUser(): string
{
    return $_SESSION['username'] ?? '';
}

/**
 * Gibt den angezeigten Namen zurück.
 */
function currentName(): string
{
    return $_SESSION['name'] ?? currentUser();
}
