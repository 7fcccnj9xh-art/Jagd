<?php
require_once __DIR__ . '/../../shared/config/config.php';
require_once __DIR__ . '/../../shared/config/database.php';
require_once __DIR__ . '/../../shared/auth/auth.php';
require_once __DIR__ . '/../../shared/csrf/csrf.php';
require_once __DIR__ . '/../../shared/functions/functions.php';

sessionStart();
requireLogin();

$pageTitle  = 'Wildbeobachtungen';
$pageIcon   = 'fas fa-binoculars';
$activePage = 'wildbeobachtungen';

$db     = getDB();
$fehler = '';
$action = $_GET['action'] ?? '';
$editId = (int)($_GET['id'] ?? 0);

// -------------------------------------------------------
// POST: Speichern
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['export'])) {
    csrfVerify();

    $id              = (int)($_POST['id'] ?? 0);
    $datum           = $_POST['datum'] ?? '';
    $uhrzeit         = $_POST['uhrzeit'] ?? '';
    $geo_lat         = $_POST['geo_lat'] !== '' ? (float)$_POST['geo_lat'] : null;
    $geo_lng         = $_POST['geo_lng'] !== '' ? (float)$_POST['geo_lng'] : null;
    $wildart         = trim($_POST['wildart'] ?? '');
    $anzahl          = max(1, (int)($_POST['anzahl'] ?? 1));
    $geschlecht_alter = trim($_POST['geschlecht_alter'] ?? '');
    $witterung       = trim($_POST['witterung'] ?? '');
    $beobachter      = trim($_POST['beobachter'] ?? '');
    $einrichtung_id  = (int)($_POST['einrichtung_id'] ?? 0) ?: null;
    $notizen         = trim($_POST['notizen'] ?? '');

    if (empty($datum) || empty($uhrzeit) || empty($wildart) || !$geo_lat || !$geo_lng) {
        $fehler = 'Datum, Uhrzeit, Wildart und Koordinaten sind Pflichtfelder.';
    } else {
        if ($id > 0) {
            $db->prepare(
                'UPDATE wildbeobachtungen SET datum=?, uhrzeit=?, geo_lat=?, geo_lng=?,
                 wildart=?, anzahl=?, geschlecht_alter=?, witterung=?, beobachter=?,
                 einrichtung_id=?, notizen=? WHERE id=?'
            )->execute([$datum, $uhrzeit, $geo_lat, $geo_lng, $wildart, $anzahl,
                        $geschlecht_alter, $witterung, $beobachter, $einrichtung_id, $notizen, $id]);
            protokollieren('wildbeobachtungen', $id, 'bearbeitet', '', $wildart . ' ' . $datum);
            flash('success', 'Beobachtung aktualisiert.');
        } else {
            $db->prepare(
                'INSERT INTO wildbeobachtungen
                 (datum, uhrzeit, geo_lat, geo_lng, wildart, anzahl, geschlecht_alter,
                  witterung, beobachter, einrichtung_id, notizen, created_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([$datum, $uhrzeit, $geo_lat, $geo_lng, $wildart, $anzahl,
                        $geschlecht_alter, $witterung, $beobachter, $einrichtung_id,
                        $notizen, $_SESSION['user_id']]);
            protokollieren('wildbeobachtungen', (int)$db->lastInsertId(), 'angelegt', '', $wildart . ' ' . $datum);
            flash('success', 'Beobachtung erfasst.');
        }
        header('Location: ' . BASE_URL . 'modules/reviermanagement/wildbeobachtungen.php');
        exit;
    }
}

// -------------------------------------------------------
// CSV-Export
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export'])) {
    csrfVerify();
    $rows = $db->query(
        'SELECT datum, uhrzeit, wildart, anzahl, geschlecht_alter, witterung,
                beobachter, geo_lat, geo_lng, notizen
         FROM wildbeobachtungen ORDER BY datum DESC, uhrzeit DESC'
    )->fetchAll();

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="wildbeobachtungen_' . date('Y-m-d') . '.csv"');
    echo "\xEF\xBB\xBF"; // UTF-8 BOM für Excel
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Datum','Uhrzeit','Wildart','Anzahl','Geschlecht/Alter',
                   'Witterung','Beobachter','Breitengrad','Längengrad','Notizen'], ';');
    foreach ($rows as $r) {
        fputcsv($out, $r, ';');
    }
    fclose($out);
    exit;
}

// -------------------------------------------------------
// GET: Löschen
// -------------------------------------------------------
if ($action === 'delete' && $editId > 0) {
    csrfVerify();
    $db->prepare('DELETE FROM wildbeobachtungen WHERE id = ?')->execute([$editId]);
    protokollieren('wildbeobachtungen', $editId, 'gelöscht', '', '');
    flash('success', 'Beobachtung gelöscht.');
    header('Location: ' . BASE_URL . 'modules/reviermanagement/wildbeobachtungen.php');
    exit;
}

// -------------------------------------------------------
// Daten laden
// -------------------------------------------------------
$beobachtungen = $db->query(
    'SELECT wb.*, e.name AS einrichtung_name
     FROM wildbeobachtungen wb
     LEFT JOIN einrichtungen e ON wb.einrichtung_id = e.id
     ORDER BY wb.datum DESC, wb.uhrzeit DESC'
)->fetchAll();

$einrichtungen = $db->query('SELECT id, name, typ FROM einrichtungen ORDER BY name')->fetchAll();

// Bearbeiten
$edit = null;
if ($editId > 0 || $action === 'edit') {
    $stmt = $db->prepare('SELECT * FROM wildbeobachtungen WHERE id = ?');
    $stmt->execute([$editId]);
    $edit = $stmt->fetch();
}

// Statistik: Häufigkeit nach Wildart (letztes Jahr)
$statistik = $db->query(
    "SELECT wildart, COUNT(*) AS anzahl_beobachtungen, SUM(anzahl) AS anzahl_tiere
     FROM wildbeobachtungen
     WHERE datum >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
     GROUP BY wildart ORDER BY anzahl_beobachtungen DESC"
)->fetchAll();

// Karten-Daten
$kartenDaten = array_filter($beobachtungen, fn($b) => $b['geo_lat'] && $b['geo_lng']);

include __DIR__ . '/../../shared/ui/header.php';
?>

<!-- Tab-Navigation -->
<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link <?= !in_array($action, ['karte','statistik','add']) && !$edit ? 'active' : '' ?>" href="?">
            <i class="fas fa-list me-1"></i>Liste
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $action === 'karte' ? 'active' : '' ?>" href="?action=karte">
            <i class="fas fa-map me-1"></i>Karte
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $action === 'statistik' ? 'active' : '' ?>" href="?action=statistik">
            <i class="fas fa-chart-bar me-1"></i>Statistik
        </a>
    </li>
    <li class="nav-item ms-auto">
        <a class="nav-link btn-jagd text-white" href="?action=add">
            <i class="fas fa-plus me-1"></i>Neue Beobachtung
        </a>
    </li>
</ul>

<?php if ($fehler): ?>
    <div class="alert alert-danger"><?= e($fehler) ?></div>
<?php endif; ?>

<!-- ======================================================
     LISTENANSICHT
     ====================================================== -->
<?php if (!in_array($action, ['karte','statistik','add']) && !$edit): ?>

<div class="d-flex justify-content-end mb-2">
    <form method="post">
        <?= csrfField() ?>
        <button type="submit" name="export" value="1" class="btn btn-outline-success btn-sm">
            <i class="fas fa-file-csv me-1"></i>CSV exportieren
        </button>
    </form>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <table class="table table-hover mb-0 dt-tabelle">
            <thead class="table-light">
                <tr>
                    <th>Datum</th>
                    <th>Uhrzeit</th>
                    <th>Wildart</th>
                    <th>Anzahl</th>
                    <th>Beobachter</th>
                    <th>Einrichtung</th>
                    <th>Witterung</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($beobachtungen as $b): ?>
                <tr>
                    <td><?= datumDE($b['datum']) ?></td>
                    <td><?= substr($b['uhrzeit'], 0, 5) ?> Uhr</td>
                    <td><strong><?= e($b['wildart']) ?></strong></td>
                    <td><?= (int)$b['anzahl'] ?>×
                        <?php if ($b['geschlecht_alter']): ?>
                            <br><small class="text-muted"><?= e($b['geschlecht_alter']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?= e($b['beobachter'] ?? '—') ?></td>
                    <td><?= e($b['einrichtung_name'] ?? '—') ?></td>
                    <td><?= e($b['witterung'] ?? '—') ?></td>
                    <td class="text-end text-nowrap">
                        <a href="?action=edit&id=<?= $b['id'] ?>"
                           class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-edit"></i>
                        </a>
                        <a href="?action=delete&id=<?= $b['id'] ?>"
                           class="btn btn-sm btn-outline-danger ms-1"
                           data-confirm="Beobachtung vom <?= datumDE($b['datum']) ?> wirklich löschen?">
                            <i class="fas fa-trash"></i>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- ======================================================
     KARTENANSICHT
     ====================================================== -->
<?php if ($action === 'karte'): ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<?php
$wildartFarben = [
    'Reh'        => '#5a7a3a',
    'Rotwild'    => '#8b1a1a',
    'Wildschwein'=> '#4a3728',
    'Damwild'    => '#c4a55a',
    'Fuchs'      => '#c45a1a',
    'Hase'       => '#8b6914',
    'Fasan'      => '#1a6b8a',
    'Dachs'      => '#555555',
];
?>

<div class="card border-0 shadow-sm">
    <div class="card-body p-2">
        <div id="beobachtungenKarte" class="jagd-karte"></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const map = L.map('beobachtungenKarte').setView([<?= KARTE_LAT ?>, <?= KARTE_LNG ?>], <?= KARTE_ZOOM ?>);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap', maxZoom: 19
    }).addTo(map);

    const daten  = <?= json_encode(array_values($kartenDaten)) ?>;
    const farben = <?= json_encode($wildartFarben) ?>;
    const standard = '#666';

    daten.forEach(function (b) {
        const farbe = farben[b.wildart] || standard;
        const icon = L.divIcon({
            className: '',
            html: `<div style="background:${farbe};width:12px;height:12px;border-radius:50%;
                   border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.4)"></div>`,
            iconSize: [12, 12], iconAnchor: [6, 6],
        });
        const datum = new Date(b.datum).toLocaleDateString('de-DE');
        const zeit  = b.uhrzeit ? b.uhrzeit.substr(0,5) + ' Uhr' : '';
        L.marker([b.geo_lat, b.geo_lng], { icon })
         .addTo(map)
         .bindPopup(`<strong>${b.wildart}</strong> (${b.anzahl}×)<br>
                     ${datum} ${zeit}<br>
                     ${b.beobachter || ''}`);
    });
});
</script>
<?php endif; ?>

<!-- ======================================================
     STATISTIK
     ====================================================== -->
<?php if ($action === 'statistik'): ?>
<div class="row g-3">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent fw-semibold">
                Beobachtungen nach Wildart (letzte 12 Monate)
            </div>
            <div class="card-body" style="min-height:250px">
                <?php if (empty($statistik)): ?>
                    <p class="text-muted">Noch keine Daten vorhanden.</p>
                <?php else: ?>
                    <canvas id="chartWildart"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent fw-semibold">
                Übersichtstabelle
            </div>
            <div class="card-body p-0">
                <table class="table mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Wildart</th>
                            <th class="text-end">Beobachtungen</th>
                            <th class="text-end">Tiere gesamt</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($statistik as $s): ?>
                        <tr>
                            <td><?= e($s['wildart']) ?></td>
                            <td class="text-end"><?= (int)$s['anzahl_beobachtungen'] ?></td>
                            <td class="text-end"><?= (int)$s['anzahl_tiere'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($statistik)): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('chartWildart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_column($statistik, 'wildart')) ?>,
            datasets: [{
                label: 'Beobachtungen',
                data: <?= json_encode(array_column($statistik, 'anzahl_beobachtungen')) ?>,
                backgroundColor: '#3d6b3f',
                borderRadius: 4,
            }]
        },
        options: {
            indexAxis: 'y',
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } } },
        }
    });
});
</script>
<?php endif; ?>
<?php endif; ?>

<!-- ======================================================
     FORMULAR (Neu / Bearbeiten)
     ====================================================== -->
<?php if ($action === 'add' || $edit): ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<div class="card border-0 shadow-sm" style="max-width:800px">
    <div class="card-header bg-transparent fw-semibold">
        <?= $edit ? 'Beobachtung bearbeiten' : 'Neue Beobachtung erfassen' ?>
    </div>
    <div class="card-body">
        <form method="post">
            <?= csrfField() ?>
            <input type="hidden" name="id" value="<?= $edit['id'] ?? 0 ?>">

            <div class="row g-3">
                <!-- Datum / Uhrzeit -->
                <div class="col-md-4">
                    <label class="form-label">Datum <span class="text-danger">*</span></label>
                    <input type="date" name="datum" class="form-control" required
                           value="<?= e($edit['datum'] ?? date('Y-m-d')) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Uhrzeit <span class="text-danger">*</span></label>
                    <input type="time" name="uhrzeit" class="form-control" required
                           value="<?= e(substr($edit['uhrzeit'] ?? date('H:i'), 0, 5)) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Witterung</label>
                    <select name="witterung" class="form-select">
                        <option value="">— wählen —</option>
                        <?php foreach (WITTERUNG_OPTIONEN as $w): ?>
                            <option value="<?= e($w) ?>"
                                <?= ($edit['witterung'] ?? '') === $w ? 'selected' : '' ?>>
                                <?= e($w) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Wildart / Anzahl -->
                <div class="col-md-4">
                    <label class="form-label">Wildart <span class="text-danger">*</span></label>
                    <select name="wildart" class="form-select" required>
                        <option value="">— wählen —</option>
                        <?php foreach (WILDARTEN as $w): ?>
                            <option value="<?= e($w) ?>"
                                <?= ($edit['wildart'] ?? '') === $w ? 'selected' : '' ?>>
                                <?= e($w) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Anzahl</label>
                    <input type="number" name="anzahl" class="form-control" min="1" max="999"
                           value="<?= e($edit['anzahl'] ?? 1) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Geschlecht / Alter
                        <small class="text-muted">(z.B. Bock adult, Ricke mit Kitz)</small>
                    </label>
                    <input type="text" name="geschlecht_alter" class="form-control"
                           value="<?= e($edit['geschlecht_alter'] ?? '') ?>">
                </div>

                <!-- Beobachter / Einrichtung -->
                <div class="col-md-6">
                    <label class="form-label">Beobachter</label>
                    <input type="text" name="beobachter" class="form-control"
                           value="<?= e($edit['beobachter'] ?? currentName()) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Einrichtung
                        <small class="text-muted">(z.B. Hochsitz)</small>
                    </label>
                    <select name="einrichtung_id" class="form-select">
                        <option value="">— keine —</option>
                        <?php foreach ($einrichtungen as $ein): ?>
                            <option value="<?= $ein['id'] ?>"
                                <?= ($edit['einrichtung_id'] ?? 0) == $ein['id'] ? 'selected' : '' ?>>
                                <?= e($ein['name']) ?> (<?= e($ein['typ']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- GPS -->
                <div class="col-md-6">
                    <label class="form-label">Breitengrad <span class="text-danger">*</span></label>
                    <input type="number" name="geo_lat" id="geo_lat" class="form-control" required
                           step="0.00000001" placeholder="49.28370"
                           value="<?= e($edit['geo_lat'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Längengrad <span class="text-danger">*</span></label>
                    <input type="number" name="geo_lng" id="geo_lng" class="form-control" required
                           step="0.00000001" placeholder="8.53500"
                           value="<?= e($edit['geo_lng'] ?? '') ?>">
                </div>

                <!-- Karte -->
                <div class="col-12">
                    <label class="form-label">Position auf Karte wählen</label>
                    <div id="gpsPicker" style="height:280px;border-radius:8px;border:1px solid #dee2e6"></div>
                </div>

                <!-- Notizen -->
                <div class="col-12">
                    <label class="form-label">Notizen</label>
                    <textarea name="notizen" class="form-control" rows="2"><?= e($edit['notizen'] ?? '') ?></textarea>
                </div>

                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-jagd">
                        <i class="fas fa-save me-2"></i>Speichern
                    </button>
                    <a href="<?= BASE_URL ?>modules/reviermanagement/wildbeobachtungen.php"
                       class="btn btn-outline-secondary">Abbrechen</a>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const latInput = document.getElementById('geo_lat');
    const lngInput = document.getElementById('geo_lng');
    const startLat = parseFloat(latInput.value) || <?= KARTE_LAT ?>;
    const startLng = parseFloat(lngInput.value) || <?= KARTE_LNG ?>;

    const { marker } = leafletPicker('gpsPicker', startLat, startLng, function (lat, lng) {
        latInput.value = lat.toFixed(8);
        lngInput.value = lng.toFixed(8);
    });

    [latInput, lngInput].forEach(function (inp) {
        inp.addEventListener('change', function () {
            const la = parseFloat(latInput.value);
            const ln = parseFloat(lngInput.value);
            if (!isNaN(la) && !isNaN(ln)) marker.setLatLng([la, ln]);
        });
    });
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/../../shared/ui/footer.php'; ?>
