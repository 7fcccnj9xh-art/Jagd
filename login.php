<?php
require_once __DIR__ . '/shared/config/config.php';
require_once __DIR__ . '/shared/config/database.php';
require_once __DIR__ . '/shared/auth/auth.php';
require_once __DIR__ . '/shared/csrf/csrf.php';
require_once __DIR__ . '/shared/functions/functions.php';

sessionStart();

// Bereits eingeloggt → zum Dashboard
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'dashboard/');
    exit;
}

$fehler = '';

// Erste Einrichtung: keine Benutzer vorhanden → Registrierformular
$db          = getDB();
$keinUser    = (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0;
$registrieren = $keinUser;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();

    if ($registrieren) {
        // Ersten Benutzer anlegen
        $username = trim($_POST['username'] ?? '');
        $name     = trim($_POST['name'] ?? '');
        $password = $_POST['password'] ?? '';
        $password2 = $_POST['password2'] ?? '';

        if (strlen($username) < 3) {
            $fehler = 'Benutzername muss mindestens 3 Zeichen haben.';
        } elseif (strlen($password) < 8) {
            $fehler = 'Passwort muss mindestens 8 Zeichen haben.';
        } elseif ($password !== $password2) {
            $fehler = 'Passwörter stimmen nicht überein.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $db->prepare('INSERT INTO users (username, name, password_hash, role) VALUES (?, ?, ?, ?)')
               ->execute([$username, $name, $hash, 'admin']);
            login($username, $password);
            header('Location: ' . BASE_URL . 'dashboard/');
            exit;
        }
    } else {
        // Normaler Login
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (login($username, $password)) {
            header('Location: ' . BASE_URL . 'dashboard/');
            exit;
        } else {
            $fehler = 'Falscher Benutzername oder falsches Passwort.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $registrieren ? 'Einrichtung' : 'Anmeldung' ?> – <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>shared/ui/assets/css/jagd.css">
</head>
<body class="bg-light">

<div class="login-card">
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <div class="text-center mb-4">
                <div class="login-logo mb-2">
                    <i class="fas fa-crosshairs"></i>
                </div>
                <h4 class="fw-bold mb-0"><?= APP_NAME ?></h4>
                <p class="text-muted small mt-1">
                    <?= $registrieren ? 'Ersteinrichtung – Administratorkonto anlegen' : 'Bitte anmelden' ?>
                </p>
            </div>

            <?php if ($fehler): ?>
                <div class="alert alert-danger py-2"><?= e($fehler) ?></div>
            <?php endif; ?>

            <form method="post">
                <?= csrfField() ?>

                <?php if ($registrieren): ?>
                <div class="mb-3">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" placeholder="Vollständiger Name"
                           value="<?= e($_POST['name'] ?? '') ?>">
                </div>
                <?php endif; ?>

                <div class="mb-3">
                    <label class="form-label">Benutzername</label>
                    <input type="text" name="username" class="form-control" required autofocus
                           autocomplete="username" value="<?= e($_POST['username'] ?? '') ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label">Passwort</label>
                    <input type="password" name="password" class="form-control" required
                           autocomplete="<?= $registrieren ? 'new-password' : 'current-password' ?>">
                </div>

                <?php if ($registrieren): ?>
                <div class="mb-4">
                    <label class="form-label">Passwort wiederholen</label>
                    <input type="password" name="password2" class="form-control" required
                           autocomplete="new-password">
                </div>
                <?php endif; ?>

                <button type="submit" class="btn btn-jagd w-100">
                    <i class="fas fa-<?= $registrieren ? 'user-plus' : 'sign-in-alt' ?> me-2"></i>
                    <?= $registrieren ? 'Konto anlegen' : 'Anmelden' ?>
                </button>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
