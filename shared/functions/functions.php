<?php
// ============================================================
// Jagd-Verwaltung: Hilfsfunktionen
// ============================================================

require_once __DIR__ . '/../config/database.php';

/**
 * Sichert einen String gegen XSS.
 */
function e(string $str): string
{
    return htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Formatiert ein Datum auf Deutsch (z.B. 15.03.2026).
 */
function datumDE(?string $datum): string
{
    if (!$datum) return '—';
    return date('d.m.Y', strtotime($datum));
}

/**
 * Formatiert Datum + Uhrzeit auf Deutsch.
 */
function datumZeitDE(?string $datum, ?string $zeit = null): string
{
    if (!$datum) return '—';
    $str = date('d.m.Y', strtotime($datum));
    if ($zeit) {
        $str .= ' ' . substr($zeit, 0, 5) . ' Uhr';
    }
    return $str;
}

/**
 * Gibt ein Status-Badge zurück.
 */
function statusBadge(string $status): string
{
    $map = [
        'gut'                  => ['bg-success',          'Gut'],
        'reparaturbeduerftig'  => ['bg-warning text-dark', 'Reparaturbedürftig'],
        'gesperrt'             => ['bg-danger',            'Gesperrt'],
        'defekt'               => ['bg-danger',            'Defekt'],
    ];
    [$cls, $label] = $map[$status] ?? ['bg-secondary', e($status)];
    return '<span class="badge ' . $cls . '">' . $label . '</span>';
}

/**
 * Gibt ein Ablaufdatum-Badge zurück (grün / gelb / rot).
 * $tage: Anzahl Tage bis Ablauf (negativ = bereits abgelaufen)
 */
function ablaufBadge(?string $ablaufdatum, int $erinnerungTage = 60): string
{
    if (!$ablaufdatum) return '<span class="badge bg-secondary">Kein Ablaufdatum</span>';

    $heute = new DateTime();
    $ablauf = new DateTime($ablaufdatum);
    $diff   = (int) $heute->diff($ablauf)->format('%r%a'); // positiv = noch gültig

    if ($diff < 0) {
        $cls   = 'bg-danger';
        $label = 'Abgelaufen (' . datumDE($ablaufdatum) . ')';
    } elseif ($diff <= $erinnerungTage) {
        $cls   = 'bg-warning text-dark';
        $label = 'Läuft ab ' . datumDE($ablaufdatum) . ' (noch ' . $diff . ' Tage)';
    } else {
        $cls   = 'bg-success';
        $label = 'Gültig bis ' . datumDE($ablaufdatum);
    }

    return '<span class="badge ' . $cls . '">' . $label . '</span>';
}

/**
 * Schreibt einen Protokolleintrag.
 */
function protokollieren(
    string $tabelle,
    int    $datensatzId,
    string $aktion,
    string $alterWert = '',
    string $neuerWert = ''
): void {
    $db   = getDB();
    $user = $_SESSION['username'] ?? 'system';
    $ip   = $_SERVER['REMOTE_ADDR'] ?? '';

    $db->prepare(
        'INSERT INTO protokoll (tabelle, datensatz_id, aktion, alter_wert, neuer_wert, benutzer, ip_adresse)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    )->execute([$tabelle, $datensatzId, $aktion, $alterWert, $neuerWert, $user, $ip]);
}

/**
 * Flash-Nachrichten setzen.
 */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/**
 * Flash-Nachrichten auslesen und leeren.
 */
function getFlash(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

/**
 * Sicherer Datei-Upload. Gibt den neuen Dateinamen zurück oder wirft eine Exception.
 *
 * @param array  $file          $_FILES['feldname']
 * @param string $subdir        Unterverzeichnis in uploads/ (z.B. 'einrichtungen')
 * @param array  $allowedExts   Erlaubte Endungen
 * @return string               Dateiname (ohne Pfad) der gespeicherten Datei
 */
function uploadDatei(array $file, string $subdir, array $allowedExts = []): string
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload-Fehler: Code ' . $file['error']);
    }

    if ($file['size'] > MAX_UPLOAD_SIZE) {
        throw new RuntimeException('Datei zu groß (max. ' . (MAX_UPLOAD_SIZE / 1024 / 1024) . ' MB).');
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!empty($allowedExts) && !in_array($ext, $allowedExts, true)) {
        throw new RuntimeException('Nicht erlaubte Dateiendung: ' . $ext);
    }

    $zielDir = UPLOAD_DIR . $subdir . '/';
    if (!is_dir($zielDir)) {
        mkdir($zielDir, 0755, true);
    }

    // Zufälliger Dateiname gegen Directory Traversal und Überschreiben
    $neuerName = bin2hex(random_bytes(16)) . '.' . $ext;
    $zielPfad  = $zielDir . $neuerName;

    if (!move_uploaded_file($file['tmp_name'], $zielPfad)) {
        throw new RuntimeException('Datei konnte nicht gespeichert werden.');
    }

    return $neuerName;
}

/**
 * Löscht eine Upload-Datei sicher (nur innerhalb von UPLOAD_DIR).
 */
function loescheDatei(string $subdir, string $dateiname): void
{
    if (empty($dateiname)) return;

    $pfad = UPLOAD_DIR . $subdir . '/' . basename($dateiname);
    if (file_exists($pfad)) {
        unlink($pfad);
    }
}
