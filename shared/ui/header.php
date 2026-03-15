<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? 'Jagd-Verwaltung') ?> – <?= APP_NAME ?></title>

    <!-- Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Jagd CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>shared/ui/assets/css/jagd.css">
    <?= $extraCss ?? '' ?>
</head>
<body>

<!-- Navigationsleiste -->
<nav class="navbar navbar-expand-lg navbar-dark jagd-navbar">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="<?= BASE_URL ?>dashboard/">
            <i class="fas fa-crosshairs me-2"></i><?= APP_NAME ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">

                <!-- Dashboard -->
                <li class="nav-item">
                    <a class="nav-link <?= ($activePage ?? '') === 'dashboard' ? 'active' : '' ?>"
                       href="<?= BASE_URL ?>dashboard/">
                        <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                    </a>
                </li>

                <!-- Reviermanagement Dropdown -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= in_array($activePage ?? '', ['wildbeobachtungen','einrichtungen','ausruestung','behoerden']) ? 'active' : '' ?>"
                       href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-map me-1"></i>Reviermanagement
                    </a>
                    <ul class="dropdown-menu">
                        <li>
                            <a class="dropdown-item <?= ($activePage ?? '') === 'wildbeobachtungen' ? 'active' : '' ?>"
                               href="<?= BASE_URL ?>modules/reviermanagement/wildbeobachtungen.php">
                                <i class="fas fa-binoculars me-2"></i>Wildbeobachtungen
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?= ($activePage ?? '') === 'einrichtungen' ? 'active' : '' ?>"
                               href="<?= BASE_URL ?>modules/reviermanagement/einrichtungen.php">
                                <i class="fas fa-tree me-2"></i>Einrichtungen
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?= ($activePage ?? '') === 'ausruestung' ? 'active' : '' ?>"
                               href="<?= BASE_URL ?>modules/reviermanagement/ausruestung.php">
                                <i class="fas fa-gun me-2"></i>Ausrüstung
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?= ($activePage ?? '') === 'behoerden' ? 'active' : '' ?>"
                               href="<?= BASE_URL ?>modules/reviermanagement/behoerden.php">
                                <i class="fas fa-file-alt me-2"></i>Behörden & Dokumente
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- Wildbret-Modul -->
                <li class="nav-item">
                    <a class="nav-link <?= ($activePage ?? '') === 'wildbret' ? 'active' : '' ?>"
                       href="/modules/wildbret/">
                        <i class="fas fa-drumstick-bite me-1"></i>Wildbret
                    </a>
                </li>

                <!-- Wildaufnahmen (Flask-App, separater Port) -->
                <li class="nav-item">
                    <a class="nav-link" href="http://localhost:5001" target="_blank">
                        <i class="fas fa-camera me-1"></i>Sortierung
                        <i class="fas fa-external-link-alt ms-1 small"></i>
                    </a>
                </li>

            </ul>

            <!-- Benutzer-Menü -->
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle me-1"></i><?= e(currentName()) ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><span class="dropdown-item-text text-muted small"><?= e(currentUser()) ?></span></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-danger" href="<?= BASE_URL ?>logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Abmelden
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Flash-Nachrichten -->
<?php foreach (getFlash() as $msg): ?>
<div class="alert alert-<?= e($msg['type']) ?> alert-dismissible fade show mb-0 rounded-0" role="alert">
    <div class="container-fluid">
        <?= e($msg['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
</div>
<?php endforeach; ?>

<!-- Seiteninhalt -->
<div class="container-fluid py-3">
    <div class="d-flex align-items-center mb-3">
        <h1 class="h4 mb-0 fw-semibold">
            <?php if (!empty($pageIcon)): ?>
                <i class="<?= e($pageIcon) ?> me-2 text-jagd"></i>
            <?php endif; ?>
            <?= e($pageTitle ?? '') ?>
        </h1>
    </div>
</div>

<div class="container-fluid">
