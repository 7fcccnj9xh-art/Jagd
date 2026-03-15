<?php
// ============================================================
// Jagd-Verwaltung: CSRF-Schutz
// Übernommen aus Wildbret/includes/csrf.php
// ============================================================

/**
 * Gibt den aktuellen CSRF-Token zurück (erzeugt ihn bei Bedarf).
 */
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Gibt ein verstecktes HTML-Input-Feld mit dem CSRF-Token zurück.
 */
function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">';
}

/**
 * Prüft den CSRF-Token. Bricht bei Fehler mit HTTP 403 ab.
 */
function csrfVerify(): void
{
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals(csrfToken(), $token)) {
        http_response_code(403);
        die('Ungültiger CSRF-Token.');
    }
}
