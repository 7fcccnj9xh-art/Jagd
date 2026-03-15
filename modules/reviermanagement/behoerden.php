<?php
require_once __DIR__ . '/../../shared/config/config.php';
require_once __DIR__ . '/../../shared/config/database.php';
require_once __DIR__ . '/../../shared/auth/auth.php';
require_once __DIR__ . '/../../shared/csrf/csrf.php';
require_once __DIR__ . '/../../shared/functions/functions.php';

sessionStart();
requireLogin();

$pageTitle  = 'Behörden & Dokumente';
$pageIcon   = 'fas fa-file-alt';
$activePage = 'behoerden';

$db     = getDB();
$fehler = '';
$action = $_GET['action'] ?? '';
$editId = (int)($_GET['id'] ?? 0);

$dokumentTypen = ['Jagdschein', 'WBK', 'Jahresjagdschein', 'Sonstiges'];

// -------------------------------------------------------
// POST: Speichern
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_dokument'])) {
    csrfVerify();

    $id               = (int)($_POST['id'] ?? 0);
    $titel            = trim($_POST['titel'] ?? '');
    $typ              = $_POST['typ'] ?? '';
    $dokument_nr      = trim($_POST['dokument_nr'] ?? '');
    $aussteller       = trim($_POST['aussteller'] ?? '');
    $ausstellungsdatum = $_POST['ausstellungsdatum'] !== '' ? $_POST['ausstellungsdatum'] : null;
    $ablaufdatum      = $_POST['ablaufdatum'] !== '' ? $_POST['ablaufdatum'] : null;
    $erinnerung_tage  = max(0, (int)($_POST['erinnerung_tage'] ?? 60));
    $ausruestung_id   = (int)($_POST['ausruestung_id'] ?? 0) ?: null;
    $notizen          = trim($_POST['notizen'] ?? '');

    if (empty($titel) || !in_array($typ, $dokumentTypen, true)) {
        $fehler = 'Titel und Typ sind Pflichtfelder.';
    } else {
        // Datei-Upload
        $dateiname   = null;
        $originalname = null;
        if (!empty($_FILES['scan']['name'])) {
            try {
                $dateiname    = uploadDatei($_FILES['scan'], 'behoerden', ALLOWED_DOC_EXTENSIONS);
                $originalname = $_FILES['scan']['name'];
            } catch (RuntimeException $ex) {
                $fehler = $ex->getMessage();
            }
        }

        if (empty($fehler)) {
            if ($id > 0) {
                // Bestehenden Dateinamen beibehalten wenn kein neuer Upload
                if (!$dateiname) {
                    $alt = $db->prepare('SELECT dateiname, originalname FROM dokumente WHERE id = ?');
                    $alt->execute([$id]);
                    $altRow = $alt->fetch();
                    $dateiname    = $altRow['dateiname'];
                    $originalname = $altRow['originalname'];
                }
                $db->prepare(
                    'UPDATE dokumente SET titel=?, typ=?, dokument_nr=?, aussteller=?,
                     ausstellungsdatum=?, ablaufdatum=?, erinnerung_tage=?,
                     ausruestung_id=?, dateiname=?, originalname=?, notizen=? WHERE id=?'
                )->execute([$titel, $typ, $dokument_nr ?: null, $aussteller ?: null,
                            $ausstellungsdatum, $ablaufdatum, $erinnerung_tage,
                            $ausruestung_id, $dateiname, $originalname, $notizen, $id]);
                protokollieren('dokumente', $id, 'bearbeitet', '', $titel);
                flash('success', '„' . $titel . '" gespeichert.');
            } else {
                $db->prepare(
                    'INSERT INTO dokumente (titel, typ, dokument_nr, aussteller,
                     ausstellungsdatum, ablaufdatum, erinnerung_tage,
                     ausruestung_id, dateiname, originalname, notizen)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?)'
                )->execute([$titel, $typ, $dokument_nr ?: null, $aussteller ?: null,
                            $ausstellungsdatum, $ablaufdatum, $erinnerung_tage,
                            $ausruestung_id, $dateiname, $originalname, $notizen]);
                protokollieren('dokumente', (int)$db->lastInsertId(), 'angelegt', '', $titel);
                flash('success', '„' . $titel . '" angelegt.');
            }
            header('Location: ' . BASE_URL . 'modules/reviermanagement/behoerden.php');
            exit;
        }
    }
}

// -------------------------------------------------------
// GET: Löschen
// -------------------------------------------------------
if ($action === 'delete' && $editId > 0) {
    csrfVerify();
    $row = $db->prepare('SELECT titel, dateiname FROM dokumente WHERE id = ?');
    $row->execute([$editId]);
    $eintrag = $row->fetch();
    if ($eintrag) {
        if ($eintrag['dateiname']) loescheDatei('behoerden', $eintrag['dateiname']);
        $db->prepare('DELETE FROM dokumente WHERE id = ?')->execute([$editId]);
        protokollieren('dokumente', $editId, 'gelöscht', $eintrag['titel'], '');
        flash('success', 'Dokument gelöscht.');
    }
    header('Location: ' . BASE_URL . 'modules/reviermanagement/behoerden.php');
    exit;
}

// -------------------------------------------------------
// Daten laden
// -------------------------------------------------------
$dokumente = $db->query(
    'SELECT d.*, a.bezeichnung AS waffe_bezeichnung
     FROM dokumente d
     LEFT JOIN ausruestung a ON d.ausruestung_id = a.id
     ORDER BY d.ablaufdatum ASC, d.titel ASC'
)->fetchAll();

$waffen = $db->query(
    "SELECT id, bezeichnung FROM ausruestung WHERE kategorie = 'Waffe' ORDER BY bezeichnung"
)->fetchAll();

// Bearbeiten
$edit = null;
if (($action === 'edit' || $editId > 0) && $editId > 0) {
    $stmt = $db->prepare('SELECT * FROM dokumente WHERE id = ?');
    $stmt->execute([$editId]);
    $edit = $stmt->fetch();
}

// Ablauf-Gruppen für die Übersicht
$abgelaufen  = array_filter($dokumente, fn($d) => $d['ablaufdatum'] && $d['ablaufdatum'] < date('Y-m-d'));
$baldAblauf  = array_filter($dokumente, fn($d) => $d['ablaufdatum']
    && $d['ablaufdatum'] >= date('Y-m-d')
    && $d['ablaufdatum'] <= date('Y-m-d', strtotime('+90 days')));
$gueltig     = array_filter($dokumente, fn($d) => !$d['ablaufdatum']
    || $d['ablaufdatum'] > date('Y-m-d', strtotime('+90 days')));

include __DIR__ . '/../../shared/ui/header.php';
?>

<div class="d-flex gap-2 mb-3">
    <?php if (!$edit && $action !== 'add'): ?>
        <a href="?action=add" class="btn btn-jagd">
            <i class="fas fa-plus me-2"></i>Neues Dokument
        </a>
    <?php endif; ?>
</div>

<?php if ($fehler): ?>
    <div class="alert alert-danger"><?= e($fehler) ?></div>
<?php endif; ?>

<!-- ======================================================
     ÜBERSICHT
     ====================================================== -->
<?php if (!$edit && $action !== 'add'): ?>

<?php
// Hilfsfunktion: Tabelle einer Dokumentgruppe rendern
function renderDokumentTabelle(array $docs, PDO $db): void {
    if (empty($docs)): ?>
        <p class="text-muted p-3 mb-0">Keine Dokumente.</p>
    <?php else: ?>
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Titel</th>
                    <th>Typ</th>
                    <th>Nr.</th>
                    <th>Aussteller</th>
                    <th>Ablauf</th>
                    <th>Scan</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($docs as $d): ?>
                <tr>
                    <td class="fw-semibold"><?= e($d['titel']) ?></td>
                    <td><?= e($d['typ']) ?></td>
                    <td><?= e($d['dokument_nr'] ?? '—') ?></td>
                    <td><?= e($d['aussteller'] ?? '—') ?></td>
                    <td><?= ablaufBadge($d['ablaufdatum'], (int)$d['erinnerung_tage']) ?></td>
                    <td>
                        <?php if ($d['dateiname']): ?>
                            <a href="<?= BASE_URL ?>uploads/behoerden/<?= e($d['dateiname']) ?>"
                               target="_blank" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-file"></i>
                            </a>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end text-nowrap">
                        <a href="?action=edit&id=<?= $d['id'] ?>"
                           class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-edit"></i>
                        </a>
                        <a href="?action=delete&id=<?= $d['id'] ?>"
                           class="btn btn-sm btn-outline-danger ms-1"
                           data-confirm="„<?= e($d['titel']) ?>" wirklich löschen?">
                            <i class="fas fa-trash"></i>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif;
}
?>

<!-- Abgelaufen -->
<?php if (!empty($abgelaufen)): ?>
<div class="card border-0 shadow-sm mb-3 ablauf-ampel-rot">
    <div class="card-header bg-transparent fw-semibold text-danger">
        <i class="fas fa-exclamation-circle me-2"></i>Abgelaufen (<?= count($abgelaufen) ?>)
    </div>
    <div class="card-body p-0">
        <?php renderDokumentTabelle(array_values($abgelaufen), $db); ?>
    </div>
</div>
<?php endif; ?>

<!-- Bald ablaufend -->
<?php if (!empty($baldAblauf)): ?>
<div class="card border-0 shadow-sm mb-3 ablauf-ampel-gelb">
    <div class="card-header bg-transparent fw-semibold text-warning">
        <i class="fas fa-exclamation-triangle me-2"></i>Bald ablaufend – nächste 90 Tage (<?= count($baldAblauf) ?>)
    </div>
    <div class="card-body p-0">
        <?php renderDokumentTabelle(array_values($baldAblauf), $db); ?>
    </div>
</div>
<?php endif; ?>

<!-- Gültig / kein Ablaufdatum -->
<div class="card border-0 shadow-sm mb-3 ablauf-ampel-gruen">
    <div class="card-header bg-transparent fw-semibold text-success">
        <i class="fas fa-check-circle me-2"></i>Gültig / kein Ablaufdatum (<?= count($gueltig) ?>)
    </div>
    <div class="card-body p-0">
        <?php renderDokumentTabelle(array_values($gueltig), $db); ?>
    </div>
</div>

<?php endif; ?>

<!-- ======================================================
     FORMULAR (Neu / Bearbeiten)
     ====================================================== -->
<?php if ($edit || $action === 'add'): ?>
<div class="card border-0 shadow-sm" style="max-width:700px">
    <div class="card-header bg-transparent fw-semibold">
        <?= $edit ? 'Dokument bearbeiten: ' . e($edit['titel']) : 'Neues Dokument anlegen' ?>
    </div>
    <div class="card-body">
        <form method="post" enctype="multipart/form-data">
            <?= csrfField() ?>
            <input type="hidden" name="id" value="<?= $edit['id'] ?? 0 ?>">

            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">Titel <span class="text-danger">*</span></label>
                    <input type="text" name="titel" class="form-control" required
                           placeholder="z.B. Jagdschein Mustermann 2026"
                           value="<?= e($edit['titel'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Typ <span class="text-danger">*</span></label>
                    <select name="typ" class="form-select" required id="typSelect">
                        <?php foreach ($dokumentTypen as $t): ?>
                            <option value="<?= e($t) ?>"
                                <?= ($edit['typ'] ?? '') === $t ? 'selected' : '' ?>>
                                <?= e($t) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Dokument-Nr. / Ausweis-Nr.</label>
                    <input type="text" name="dokument_nr" class="form-control"
                           value="<?= e($edit['dokument_nr'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Ausstellende Behörde</label>
                    <input type="text" name="aussteller" class="form-control"
                           value="<?= e($edit['aussteller'] ?? '') ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Ausstellungsdatum</label>
                    <input type="date" name="ausstellungsdatum" class="form-control"
                           value="<?= e($edit['ausstellungsdatum'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Ablaufdatum</label>
                    <input type="date" name="ablaufdatum" class="form-control"
                           value="<?= e($edit['ablaufdatum'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Erinnerung (Tage vor Ablauf)</label>
                    <input type="number" name="erinnerung_tage" class="form-control"
                           min="0" max="365"
                           value="<?= e($edit['erinnerung_tage'] ?? 60) ?>">
                </div>

                <!-- WBK: Waffenzuordnung -->
                <div id="wbkFelder" class="col-12" style="display:none">
                    <label class="form-label">Zugehörige Waffe (WBK)</label>
                    <select name="ausruestung_id" class="form-select">
                        <option value="">— keine —</option>
                        <?php foreach ($waffen as $w): ?>
                            <option value="<?= $w['id'] ?>"
                                <?= ($edit['ausruestung_id'] ?? 0) == $w['id'] ? 'selected' : '' ?>>
                                <?= e($w['bezeichnung']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Scan-Upload -->
                <div class="col-12">
                    <label class="form-label">Scan / Foto
                        <small class="text-muted">(jpg, png, webp, pdf – max. 10 MB)</small>
                    </label>
                    <?php if (!empty($edit['dateiname'])): ?>
                        <div class="mb-2">
                            <a href="<?= BASE_URL ?>uploads/behoerden/<?= e($edit['dateiname']) ?>"
                               target="_blank" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-file me-1"></i><?= e($edit['originalname'] ?? $edit['dateiname']) ?>
                            </a>
                            <small class="text-muted ms-2">(neue Datei überschreibt bisherigen Scan)</small>
                        </div>
                    <?php endif; ?>
                    <input type="file" name="scan" class="form-control"
                           accept=".jpg,.jpeg,.png,.webp,.pdf">
                </div>

                <div class="col-12">
                    <label class="form-label">Notizen</label>
                    <textarea name="notizen" class="form-control" rows="2"><?= e($edit['notizen'] ?? '') ?></textarea>
                </div>

                <div class="col-12 d-flex gap-2">
                    <button type="submit" name="save_dokument" value="1" class="btn btn-jagd">
                        <i class="fas fa-save me-2"></i>Speichern
                    </button>
                    <a href="<?= BASE_URL ?>modules/reviermanagement/behoerden.php"
                       class="btn btn-outline-secondary">Abbrechen</a>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function toggleWbkFelder() {
    const typ = document.getElementById('typSelect').value;
    document.getElementById('wbkFelder').style.display = typ === 'WBK' ? 'block' : 'none';
}
document.getElementById('typSelect').addEventListener('change', toggleWbkFelder);
toggleWbkFelder();
</script>
<?php endif; ?>

<?php include __DIR__ . '/../../shared/ui/footer.php'; ?>
