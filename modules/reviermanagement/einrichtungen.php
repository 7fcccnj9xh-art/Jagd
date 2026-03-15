<?php
require_once __DIR__ . '/../../shared/config/config.php';
require_once __DIR__ . '/../../shared/config/database.php';
require_once __DIR__ . '/../../shared/auth/auth.php';
require_once __DIR__ . '/../../shared/csrf/csrf.php';
require_once __DIR__ . '/../../shared/functions/functions.php';

sessionStart();
requireLogin();

$pageTitle  = 'Einrichtungen';
$pageIcon   = 'fas fa-tree';
$activePage = 'einrichtungen';

$db     = getDB();
$fehler = '';
$action = $_GET['action'] ?? '';
$editId = (int)($_GET['id'] ?? 0);

// -------------------------------------------------------
// POST: Speichern (Neu oder Bearbeiten)
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();

    $id            = (int)($_POST['id'] ?? 0);
    $name          = trim($_POST['name'] ?? '');
    $typ           = $_POST['typ'] ?? '';
    $geo_lat       = $_POST['geo_lat'] !== '' ? (float)$_POST['geo_lat'] : null;
    $geo_lng       = $_POST['geo_lng'] !== '' ? (float)$_POST['geo_lng'] : null;
    $baujahr       = $_POST['baujahr'] !== '' ? (int)$_POST['baujahr'] : null;
    $zustand       = $_POST['zustand'] ?? 'gut';
    $letzte_wartung = $_POST['letzte_wartung'] !== '' ? $_POST['letzte_wartung'] : null;
    $wildkamera_id = trim($_POST['wildkamera_id'] ?? '');
    $notizen       = trim($_POST['notizen'] ?? '');

    if (empty($name)) {
        $fehler = 'Name ist Pflichtfeld.';
    } elseif (!in_array($typ, EINRICHTUNGSTYPEN, true)) {
        $fehler = 'Ungültiger Typ.';
    } else {
        if ($id > 0) {
            // Bearbeiten
            $db->prepare(
                'UPDATE einrichtungen SET name=?, typ=?, geo_lat=?, geo_lng=?, baujahr=?,
                 zustand=?, letzte_wartung=?, wildkamera_id=?, notizen=? WHERE id=?'
            )->execute([$name, $typ, $geo_lat, $geo_lng, $baujahr,
                        $zustand, $letzte_wartung, $wildkamera_id ?: null, $notizen, $id]);
            protokollieren('einrichtungen', $id, 'bearbeitet', '', $name);
            flash('success', 'Einrichtung „' . $name . '" gespeichert.');
        } else {
            // Neu
            $db->prepare(
                'INSERT INTO einrichtungen (name, typ, geo_lat, geo_lng, baujahr,
                 zustand, letzte_wartung, wildkamera_id, notizen) VALUES (?,?,?,?,?,?,?,?,?)'
            )->execute([$name, $typ, $geo_lat, $geo_lng, $baujahr,
                        $zustand, $letzte_wartung, $wildkamera_id ?: null, $notizen]);
            $newId = (int)$db->lastInsertId();
            protokollieren('einrichtungen', $newId, 'angelegt', '', $name);

            // Foto-Upload (optional, mehrere)
            if (!empty($_FILES['fotos']['name'][0])) {
                foreach ($_FILES['fotos']['name'] as $i => $fname) {
                    if ($_FILES['fotos']['error'][$i] !== UPLOAD_ERR_OK) continue;
                    $einzelDatei = [
                        'name'     => $_FILES['fotos']['name'][$i],
                        'tmp_name' => $_FILES['fotos']['tmp_name'][$i],
                        'error'    => $_FILES['fotos']['error'][$i],
                        'size'     => $_FILES['fotos']['size'][$i],
                    ];
                    try {
                        $dateiname = uploadDatei($einzelDatei, 'einrichtungen', ALLOWED_IMAGE_EXTENSIONS);
                        $db->prepare(
                            'INSERT INTO einrichtungen_fotos (einrichtung_id, dateiname, originalname)
                             VALUES (?, ?, ?)'
                        )->execute([$newId, $dateiname, $fname]);
                    } catch (RuntimeException $ex) {
                        $fehler .= ' Foto-Upload: ' . $ex->getMessage();
                    }
                }
            }
            flash('success', 'Einrichtung „' . $name . '" angelegt.');
        }

        if (empty($fehler)) {
            header('Location: ' . BASE_URL . 'modules/reviermanagement/einrichtungen.php');
            exit;
        }
    }
}

// -------------------------------------------------------
// POST: Foto löschen
// -------------------------------------------------------
if (isset($_POST['delete_foto'])) {
    csrfVerify();
    $fotoId = (int)$_POST['foto_id'];
    $foto   = $db->prepare('SELECT dateiname FROM einrichtungen_fotos WHERE id = ?');
    $foto->execute([$fotoId]);
    $row = $foto->fetch();
    if ($row) {
        loescheDatei('einrichtungen', $row['dateiname']);
        $db->prepare('DELETE FROM einrichtungen_fotos WHERE id = ?')->execute([$fotoId]);
    }
    header('Location: ' . BASE_URL . 'modules/reviermanagement/einrichtungen.php?action=edit&id=' . (int)$_POST['einrichtung_id']);
    exit;
}

// -------------------------------------------------------
// GET: Löschen
// -------------------------------------------------------
if ($action === 'delete' && $editId > 0) {
    csrfVerify();
    $row = $db->prepare('SELECT name FROM einrichtungen WHERE id = ?');
    $row->execute([$editId]);
    $eintrag = $row->fetch();
    if ($eintrag) {
        // Fotos löschen
        $fotos = $db->prepare('SELECT dateiname FROM einrichtungen_fotos WHERE einrichtung_id = ?');
        $fotos->execute([$editId]);
        foreach ($fotos->fetchAll() as $f) {
            loescheDatei('einrichtungen', $f['dateiname']);
        }
        $db->prepare('DELETE FROM einrichtungen WHERE id = ?')->execute([$editId]);
        protokollieren('einrichtungen', $editId, 'gelöscht', $eintrag['name'], '');
        flash('success', 'Einrichtung gelöscht.');
    }
    header('Location: ' . BASE_URL . 'modules/reviermanagement/einrichtungen.php');
    exit;
}

// -------------------------------------------------------
// Daten laden
// -------------------------------------------------------
$einrichtungen = $db->query(
    'SELECT *, (SELECT COUNT(*) FROM einrichtungen_fotos WHERE einrichtung_id = einrichtungen.id) AS foto_anzahl
     FROM einrichtungen ORDER BY typ, name'
)->fetchAll();

// Bearbeiten-Daten laden
$edit = null;
$editFotos = [];
if ($editId > 0) {
    $stmt = $db->prepare('SELECT * FROM einrichtungen WHERE id = ?');
    $stmt->execute([$editId]);
    $edit = $stmt->fetch();
    $fotoStmt = $db->prepare('SELECT * FROM einrichtungen_fotos WHERE einrichtung_id = ?');
    $fotoStmt->execute([$editId]);
    $editFotos = $fotoStmt->fetchAll();
}

// Karten-Daten (alle Koordinaten als JSON)
$kartenDaten = array_filter($einrichtungen, fn($e) => $e['geo_lat'] && $e['geo_lng']);

include __DIR__ . '/../../shared/ui/header.php';
?>

<!-- Tab-Navigation -->
<ul class="nav nav-tabs mb-3" id="einrichtungTabs">
    <li class="nav-item">
        <a class="nav-link <?= $action !== 'karte' ? 'active' : '' ?>" href="?">
            <i class="fas fa-list me-1"></i>Liste
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $action === 'karte' ? 'active' : '' ?>" href="?action=karte">
            <i class="fas fa-map me-1"></i>Karte
        </a>
    </li>
    <li class="nav-item ms-auto">
        <a class="nav-link btn-jagd text-white" href="?action=add">
            <i class="fas fa-plus me-1"></i>Neue Einrichtung
        </a>
    </li>
</ul>

<?php if ($fehler): ?>
    <div class="alert alert-danger"><?= e($fehler) ?></div>
<?php endif; ?>

<!-- ======================================================
     LISTENANSICHT
     ====================================================== -->
<?php if ($action !== 'karte' && !$edit && $action !== 'add'): ?>
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <table class="table table-hover mb-0 dt-tabelle">
            <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Typ</th>
                    <th>Zustand</th>
                    <th>Letzte Wartung</th>
                    <th>Koordinaten</th>
                    <th>Fotos</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($einrichtungen as $e): ?>
                <tr>
                    <td class="fw-semibold"><?= e($e['name']) ?></td>
                    <td><?= e($e['typ']) ?></td>
                    <td><?= statusBadge($e['zustand']) ?></td>
                    <td><?= datumDE($e['letzte_wartung']) ?></td>
                    <td>
                        <?php if ($e['geo_lat']): ?>
                            <small class="text-muted">
                                <?= number_format($e['geo_lat'], 5) ?>,
                                <?= number_format($e['geo_lng'], 5) ?>
                            </small>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td><?= $e['foto_anzahl'] > 0 ? '<span class="badge bg-secondary">' . $e['foto_anzahl'] . '</span>' : '—' ?></td>
                    <td class="text-end text-nowrap">
                        <a href="?action=edit&id=<?= $e['id'] ?>"
                           class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-edit"></i>
                        </a>
                        <a href="?action=delete&id=<?= $e['id'] ?>"
                           class="btn btn-sm btn-outline-danger ms-1"
                           data-confirm="Einrichtung „<?= e($e['name']) ?>" wirklich löschen?">
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

<div class="card border-0 shadow-sm">
    <div class="card-body p-2">
        <div id="einrichtungenKarte" class="jagd-karte"></div>
    </div>
</div>

<?php
$typFarben = [
    'Hochsitz'      => '#3d6b3f',
    'Drückjagdbock' => '#5a7a3a',
    'Ansitzleiter'  => '#7a9e5a',
    'Wildkamera'    => '#1a6b8a',
    'Kirrung'       => '#8b6914',
    'Fütterung'     => '#c4a55a',
    'Salzlecke'     => '#a0785a',
];
?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const map = L.map('einrichtungenKarte').setView([<?= KARTE_LAT ?>, <?= KARTE_LNG ?>], <?= KARTE_ZOOM ?>);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap',
        maxZoom: 19
    }).addTo(map);

    const daten = <?= json_encode(array_values($kartenDaten)) ?>;
    const farben = <?= json_encode($typFarben) ?>;

    daten.forEach(function (e) {
        const farbe = farben[e.typ] || '#666';
        const icon = L.divIcon({
            className: '',
            html: `<div style="background:${farbe};width:14px;height:14px;border-radius:50%;
                   border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.4)"></div>`,
            iconSize: [14, 14],
            iconAnchor: [7, 7],
        });
        L.marker([e.geo_lat, e.geo_lng], { icon })
         .addTo(map)
         .bindPopup(`<strong>${e.name}</strong><br>${e.typ}<br>${e.zustand}`);
    });

    // Legende
    const legende = L.control({ position: 'bottomright' });
    legende.onAdd = function () {
        const div = L.DomUtil.create('div', 'leaflet-bar');
        div.style = 'background:#fff;padding:8px;font-size:12px;line-height:1.8';
        Object.entries(farben).forEach(([typ, farbe]) => {
            div.innerHTML += `<span style="display:inline-block;width:12px;height:12px;
                background:${farbe};border-radius:50%;margin-right:5px"></span>${typ}<br>`;
        });
        return div;
    };
    legende.addTo(map);
});
</script>
<?php endif; ?>

<!-- ======================================================
     FORMULAR (Neu / Bearbeiten)
     ====================================================== -->
<?php if ($action === 'add' || $edit): ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<div class="card border-0 shadow-sm" style="max-width:800px">
    <div class="card-header bg-transparent fw-semibold">
        <?= $edit ? 'Einrichtung bearbeiten' : 'Neue Einrichtung anlegen' ?>
    </div>
    <div class="card-body">
        <form method="post" enctype="multipart/form-data">
            <?= csrfField() ?>
            <input type="hidden" name="id" value="<?= $edit['id'] ?? 0 ?>">

            <div class="row g-3">
                <!-- Name -->
                <div class="col-md-6">
                    <label class="form-label">Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" required
                           value="<?= e($edit['name'] ?? $_POST['name'] ?? '') ?>">
                </div>

                <!-- Typ -->
                <div class="col-md-6">
                    <label class="form-label">Typ <span class="text-danger">*</span></label>
                    <select name="typ" class="form-select" required>
                        <?php foreach (EINRICHTUNGSTYPEN as $t): ?>
                            <option value="<?= e($t) ?>"
                                <?= ($edit['typ'] ?? $_POST['typ'] ?? '') === $t ? 'selected' : '' ?>>
                                <?= e($t) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Zustand -->
                <div class="col-md-4">
                    <label class="form-label">Zustand</label>
                    <select name="zustand" class="form-select">
                        <?php foreach (['gut' => 'Gut', 'reparaturbeduerftig' => 'Reparaturbedürftig', 'gesperrt' => 'Gesperrt'] as $val => $label): ?>
                            <option value="<?= $val ?>"
                                <?= ($edit['zustand'] ?? 'gut') === $val ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Baujahr -->
                <div class="col-md-4">
                    <label class="form-label">Baujahr</label>
                    <input type="number" name="baujahr" class="form-control"
                           min="1950" max="<?= date('Y') ?>"
                           value="<?= e($edit['baujahr'] ?? '') ?>">
                </div>

                <!-- Letzte Wartung -->
                <div class="col-md-4">
                    <label class="form-label">Letzte Wartung</label>
                    <input type="date" name="letzte_wartung" class="form-control"
                           value="<?= e($edit['letzte_wartung'] ?? '') ?>">
                </div>

                <!-- Wildkamera-Referenz -->
                <div class="col-md-6">
                    <label class="form-label">Wildkamera-ID
                        <small class="text-muted">(Kameraname aus Wildaufnahmen)</small>
                    </label>
                    <input type="text" name="wildkamera_id" class="form-control"
                           placeholder="z.B. Kamera1"
                           value="<?= e($edit['wildkamera_id'] ?? '') ?>">
                </div>

                <!-- GPS -->
                <div class="col-md-3">
                    <label class="form-label">Breitengrad</label>
                    <input type="number" name="geo_lat" id="geo_lat" class="form-control"
                           step="0.00000001" placeholder="49.28370"
                           value="<?= e($edit['geo_lat'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Längengrad</label>
                    <input type="number" name="geo_lng" id="geo_lng" class="form-control"
                           step="0.00000001" placeholder="8.53500"
                           value="<?= e($edit['geo_lng'] ?? '') ?>">
                </div>

                <!-- Karte für GPS-Auswahl -->
                <div class="col-12">
                    <label class="form-label">Position auf Karte wählen
                        <small class="text-muted">(Marker klicken oder ziehen)</small>
                    </label>
                    <div id="gpsPicker" style="height:300px;border-radius:8px;border:1px solid #dee2e6"></div>
                </div>

                <!-- Notizen -->
                <div class="col-12">
                    <label class="form-label">Notizen</label>
                    <textarea name="notizen" class="form-control" rows="3"><?= e($edit['notizen'] ?? '') ?></textarea>
                </div>

                <!-- Foto-Upload (nur bei Neu) -->
                <?php if (!$edit): ?>
                <div class="col-12">
                    <label class="form-label">Fotos
                        <small class="text-muted">(jpg, png, webp – mehrere möglich)</small>
                    </label>
                    <input type="file" name="fotos[]" class="form-control"
                           accept=".jpg,.jpeg,.png,.webp" multiple>
                </div>
                <?php endif; ?>

                <!-- Vorhandene Fotos (bei Bearbeiten) -->
                <?php if ($edit && !empty($editFotos)): ?>
                <div class="col-12">
                    <label class="form-label">Vorhandene Fotos</label>
                    <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($editFotos as $foto): ?>
                        <div class="position-relative">
                            <img src="<?= BASE_URL ?>uploads/einrichtungen/<?= e($foto['dateiname']) ?>"
                                 style="height:80px;border-radius:6px;object-fit:cover">
                            <form method="post" class="d-inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="foto_id" value="<?= $foto['id'] ?>">
                                <input type="hidden" name="einrichtung_id" value="<?= $edit['id'] ?>">
                                <button type="submit" name="delete_foto" value="1"
                                        class="btn btn-danger btn-sm position-absolute top-0 end-0"
                                        style="padding:1px 5px;font-size:10px"
                                        data-confirm="Foto löschen?">×</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Buttons -->
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-jagd">
                        <i class="fas fa-save me-2"></i>Speichern
                    </button>
                    <a href="<?= BASE_URL ?>modules/reviermanagement/einrichtungen.php"
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

    // Eingabefelder → Marker aktualisieren
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
