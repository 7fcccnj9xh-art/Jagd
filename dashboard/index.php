<?php
require_once __DIR__ . '/../shared/config/config.php';
require_once __DIR__ . '/../shared/config/database.php';
require_once __DIR__ . '/../shared/auth/auth.php';
require_once __DIR__ . '/../shared/csrf/csrf.php';
require_once __DIR__ . '/../shared/functions/functions.php';

sessionStart();
requireLogin();

$pageTitle = 'Dashboard';
$pageIcon  = 'fas fa-tachometer-alt';
$activePage = 'dashboard';

$db = getDB();

// --- Widget: Ablaufende Dokumente (nächste 90 Tage) ---
$ablaufende = $db->query(
    "SELECT titel, typ, ablaufdatum, erinnerung_tage
     FROM dokumente
     WHERE ablaufdatum IS NOT NULL
       AND ablaufdatum <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)
     ORDER BY ablaufdatum ASC
     LIMIT 10"
)->fetchAll();

// --- Widget: Wildbeobachtungen letzte 7 Tage ---
$beobachtungen7Tage = $db->query(
    "SELECT wildart, COUNT(*) AS anzahl
     FROM wildbeobachtungen
     WHERE datum >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
     GROUP BY wildart
     ORDER BY anzahl DESC"
)->fetchAll();
$beobachtungenGesamt7Tage = array_sum(array_column($beobachtungen7Tage, 'anzahl'));

// --- Widget: Einrichtungen mit Wartungsbedarf ---
$wartungsbedarf = $db->query(
    "SELECT name, typ, zustand, letzte_wartung
     FROM einrichtungen
     WHERE zustand != 'gut'
     ORDER BY zustand, name"
)->fetchAll();

// --- Widget: Letzte Wildaufnahmen-Klassifizierungen ---
$letzteAufnahmen = $db->query(
    "SELECT kamera, kategorie, datum, uhrzeit, dateiname
     FROM wildaufnahmen_klassifizierungen
     ORDER BY created_at DESC
     LIMIT 5"
)->fetchAll();

// --- Widget: Letzte Erlegungen (aus wildbret-DB) ---
$letzteErlegungen = [];
try {
    $letzteErlegungen = getDBWildbret()->query(
        "SELECT art, erlegungsdatum, erleger, status, gewicht_kg
         FROM erlegte_stuecke
         ORDER BY erlegungsdatum DESC, id DESC
         LIMIT 5"
    )->fetchAll();
} catch (Exception $e) {
    // Wildbret-DB nicht erreichbar → Widget leer lassen
}

include __DIR__ . '/../shared/ui/header.php';
?>

<div class="row g-3">

    <!-- KPI-Leiste -->
    <div class="col-6 col-md-3">
        <div class="card kpi-card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="kpi-icon bg-success-subtle rounded-3 p-3">
                    <i class="fas fa-binoculars fa-lg text-success"></i>
                </div>
                <div>
                    <div class="fs-4 fw-bold"><?= $beobachtungenGesamt7Tage ?></div>
                    <div class="text-muted small">Beobachtungen (7 Tage)</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <?php
        $einrichtungsCount = (int) $db->query("SELECT COUNT(*) FROM einrichtungen")->fetchColumn();
        ?>
        <div class="card kpi-card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="kpi-icon bg-primary-subtle rounded-3 p-3">
                    <i class="fas fa-tree fa-lg text-primary"></i>
                </div>
                <div>
                    <div class="fs-4 fw-bold"><?= $einrichtungsCount ?></div>
                    <div class="text-muted small">Einrichtungen</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <?php
        $waffenCount = (int) $db->query("SELECT COUNT(*) FROM ausruestung WHERE kategorie='Waffe'")->fetchColumn();
        ?>
        <div class="card kpi-card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="kpi-icon bg-warning-subtle rounded-3 p-3">
                    <i class="fas fa-gun fa-lg text-warning"></i>
                </div>
                <div>
                    <div class="fs-4 fw-bold"><?= $waffenCount ?></div>
                    <div class="text-muted small">Waffen erfasst</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <?php
        $ablaufCount = count($ablaufende);
        ?>
        <div class="card kpi-card border-0 shadow-sm h-100 <?= $ablaufCount > 0 ? 'ablauf-ampel-gelb' : '' ?>">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="kpi-icon bg-danger-subtle rounded-3 p-3">
                    <i class="fas fa-exclamation-triangle fa-lg text-danger"></i>
                </div>
                <div>
                    <div class="fs-4 fw-bold"><?= $ablaufCount ?></div>
                    <div class="text-muted small">Ablaufende Dokumente</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Schnellnavigation -->
    <div class="col-12">
        <div class="d-flex flex-wrap gap-2">
            <a href="<?= BASE_URL ?>modules/reviermanagement/wildbeobachtungen.php?action=add"
               class="btn btn-jagd">
                <i class="fas fa-plus me-2"></i>Beobachtung erfassen
            </a>
            <a href="<?= BASE_URL ?>modules/reviermanagement/wildbeobachtungen.php"
               class="btn btn-outline-secondary">
                <i class="fas fa-binoculars me-2"></i>Wildbeobachtungen
            </a>
            <a href="<?= BASE_URL ?>modules/reviermanagement/einrichtungen.php"
               class="btn btn-outline-secondary">
                <i class="fas fa-tree me-2"></i>Einrichtungen
            </a>
            <a href="<?= BASE_URL ?>modules/reviermanagement/ausruestung.php"
               class="btn btn-outline-secondary">
                <i class="fas fa-gun me-2"></i>Ausrüstung
            </a>
            <a href="<?= BASE_URL ?>modules/reviermanagement/behoerden.php"
               class="btn btn-outline-secondary">
                <i class="fas fa-file-alt me-2"></i>Dokumente
            </a>
        </div>
    </div>

    <!-- Widget: Ablaufende Dokumente -->
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent fw-semibold">
                <i class="fas fa-exclamation-triangle me-2 text-warning"></i>Ablaufende Dokumente (90 Tage)
            </div>
            <div class="card-body p-0">
                <?php if (empty($ablaufende)): ?>
                    <p class="text-muted p-3 mb-0">Keine ablaufenden Dokumente.</p>
                <?php else: ?>
                    <table class="table table-hover mb-0">
                        <tbody>
                        <?php foreach ($ablaufende as $d): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= e($d['titel']) ?></div>
                                    <small class="text-muted"><?= e($d['typ']) ?></small>
                                </td>
                                <td class="text-end align-middle">
                                    <?= ablaufBadge($d['ablaufdatum'], (int)$d['erinnerung_tage']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            <div class="card-footer bg-transparent text-end">
                <a href="<?= BASE_URL ?>modules/reviermanagement/behoerden.php"
                   class="btn btn-sm btn-outline-secondary">
                    Alle Dokumente <i class="fas fa-arrow-right ms-1"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Widget: Wildbeobachtungen letzte 7 Tage -->
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent fw-semibold">
                <i class="fas fa-binoculars me-2"></i>Wildbeobachtungen – letzte 7 Tage
            </div>
            <div class="card-body p-0">
                <?php if (empty($beobachtungen7Tage)): ?>
                    <p class="text-muted p-3 mb-0">Keine Beobachtungen in den letzten 7 Tagen.</p>
                <?php else: ?>
                    <table class="table table-hover mb-0">
                        <tbody>
                        <?php foreach ($beobachtungen7Tage as $b): ?>
                            <tr>
                                <td><?= e($b['wildart']) ?></td>
                                <td class="text-end fw-bold"><?= (int)$b['anzahl'] ?>×</td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            <div class="card-footer bg-transparent text-end">
                <a href="<?= BASE_URL ?>modules/reviermanagement/wildbeobachtungen.php"
                   class="btn btn-sm btn-outline-secondary">
                    Alle Beobachtungen <i class="fas fa-arrow-right ms-1"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Widget: Letzte Erlegungen (Wildbret) -->
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent fw-semibold">
                <i class="fas fa-drumstick-bite me-2"></i>Letzte Erlegungen
                <small class="text-muted fw-normal ms-1">(Wildbret)</small>
            </div>
            <div class="card-body p-0">
                <?php if (empty($letzteErlegungen)): ?>
                    <p class="text-muted p-3 mb-0">Keine Daten (Wildbret-Datenbank nicht erreichbar).</p>
                <?php else: ?>
                    <table class="table table-hover mb-0">
                        <tbody>
                        <?php foreach ($letzteErlegungen as $s): ?>
                            <tr>
                                <td>
                                    <strong><?= e($s['art']) ?></strong>
                                    <?php if ($s['gewicht_kg']): ?>
                                        <br><small class="text-muted"><?= number_format($s['gewicht_kg'], 1, ',', '.') ?> kg</small>
                                    <?php endif; ?>
                                </td>
                                <td><?= datumDE($s['erlegungsdatum']) ?></td>
                                <td><?= e($s['erleger'] ?? '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            <div class="card-footer bg-transparent text-end">
                <a href="http://localhost:8080/" target="_blank" class="btn btn-sm btn-outline-secondary">
                    Wildbret öffnen <i class="fas fa-external-link-alt ms-1"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Widget: Einrichtungen mit Wartungsbedarf -->
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent fw-semibold">
                <i class="fas fa-tools me-2 text-danger"></i>Einrichtungen mit Wartungsbedarf
            </div>
            <div class="card-body p-0">
                <?php if (empty($wartungsbedarf)): ?>
                    <p class="text-muted p-3 mb-0">Alle Einrichtungen in gutem Zustand.</p>
                <?php else: ?>
                    <table class="table table-hover mb-0">
                        <tbody>
                        <?php foreach ($wartungsbedarf as $e): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= e($e['name']) ?></div>
                                    <small class="text-muted"><?= e($e['typ']) ?></small>
                                </td>
                                <td class="text-end align-middle">
                                    <?= statusBadge($e['zustand']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            <div class="card-footer bg-transparent text-end">
                <a href="<?= BASE_URL ?>modules/reviermanagement/einrichtungen.php"
                   class="btn btn-sm btn-outline-secondary">
                    Alle Einrichtungen <i class="fas fa-arrow-right ms-1"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Widget: Letzte Wildkamera-Aufnahmen -->
    <?php if (!empty($letzteAufnahmen)): ?>
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent fw-semibold">
                <i class="fas fa-camera me-2"></i>Letzte Wildkamera-Klassifizierungen
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Kamera</th>
                            <th>Tierart</th>
                            <th>Datum</th>
                            <th>Uhrzeit</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($letzteAufnahmen as $a): ?>
                        <tr>
                            <td><?= e($a['kamera'] ?? '—') ?></td>
                            <td><strong><?= e($a['kategorie'] ?? 'Unbekannt') ?></strong></td>
                            <td><?= datumDE($a['datum']) ?></td>
                            <td><?= $a['uhrzeit'] ? substr($a['uhrzeit'], 0, 5) . ' Uhr' : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div><!-- /row -->

<?php include __DIR__ . '/../shared/ui/footer.php'; ?>
