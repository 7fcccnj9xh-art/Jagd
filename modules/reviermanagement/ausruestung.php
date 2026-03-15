<?php
require_once __DIR__ . '/../../shared/config/config.php';
require_once __DIR__ . '/../../shared/config/database.php';
require_once __DIR__ . '/../../shared/auth/auth.php';
require_once __DIR__ . '/../../shared/csrf/csrf.php';
require_once __DIR__ . '/../../shared/functions/functions.php';

sessionStart();
requireLogin();

$pageTitle  = 'Ausrüstung';
$pageIcon   = 'fas fa-gun';
$activePage = 'ausruestung';

$db       = getDB();
$fehler   = '';
$action   = $_GET['action'] ?? '';
$editId   = (int)($_GET['id'] ?? 0);
$kategorie = $_GET['kat'] ?? 'alle';

$kategorien = ['Waffe', 'Optik', 'Bekleidung', 'Technik', 'Sonstiges'];

// -------------------------------------------------------
// POST: Ausrüstung speichern
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_ausruestung'])) {
    csrfVerify();

    $id                = (int)($_POST['id'] ?? 0);
    $bezeichnung       = trim($_POST['bezeichnung'] ?? '');
    $kat               = $_POST['kategorie'] ?? '';
    $hersteller        = trim($_POST['hersteller'] ?? '');
    $modell            = trim($_POST['modell'] ?? '');
    $seriennummer      = trim($_POST['seriennummer'] ?? '');
    $kaufdatum         = $_POST['kaufdatum'] !== '' ? $_POST['kaufdatum'] : null;
    $kaufpreis         = $_POST['kaufpreis'] !== '' ? (float)$_POST['kaufpreis'] : null;
    $zustand           = $_POST['zustand'] ?? 'gut';
    $kaliber           = trim($_POST['kaliber'] ?? '');
    $magazin_kap       = $_POST['magazin_kapazitaet'] !== '' ? (int)$_POST['magazin_kapazitaet'] : null;
    $letzter_beschuss  = $_POST['letzter_beschuss'] !== '' ? $_POST['letzter_beschuss'] : null;
    $naechster_beschuss = $_POST['naechster_beschuss'] !== '' ? $_POST['naechster_beschuss'] : null;
    $notizen           = trim($_POST['notizen'] ?? '');

    if (empty($bezeichnung) || !in_array($kat, $kategorien, true)) {
        $fehler = 'Bezeichnung und Kategorie sind Pflichtfelder.';
    } else {
        // Foto-Upload
        $foto_pfad = $edit['foto_pfad'] ?? null;
        if (!empty($_FILES['foto']['name'])) {
            try {
                $foto_pfad = uploadDatei($_FILES['foto'], 'ausruestung', ALLOWED_IMAGE_EXTENSIONS);
            } catch (RuntimeException $ex) {
                $fehler = $ex->getMessage();
            }
        }

        if (empty($fehler)) {
            $felder = [$bezeichnung, $kat, $hersteller ?: null, $modell ?: null,
                       $seriennummer ?: null, $kaufdatum, $kaufpreis, $zustand,
                       $kaliber ?: null, $magazin_kap, $letzter_beschuss, $naechster_beschuss,
                       $foto_pfad, $notizen];

            if ($id > 0) {
                $db->prepare(
                    'UPDATE ausruestung SET bezeichnung=?, kategorie=?, hersteller=?, modell=?,
                     seriennummer=?, kaufdatum=?, kaufpreis=?, zustand=?, kaliber=?,
                     magazin_kapazitaet=?, letzter_beschuss=?, naechster_beschuss=?,
                     foto_pfad=?, notizen=? WHERE id=?'
                )->execute(array_merge($felder, [$id]));
                protokollieren('ausruestung', $id, 'bearbeitet', '', $bezeichnung);
                flash('success', '„' . $bezeichnung . '" gespeichert.');
            } else {
                $db->prepare(
                    'INSERT INTO ausruestung (bezeichnung, kategorie, hersteller, modell,
                     seriennummer, kaufdatum, kaufpreis, zustand, kaliber,
                     magazin_kapazitaet, letzter_beschuss, naechster_beschuss,
                     foto_pfad, notizen) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
                )->execute($felder);
                protokollieren('ausruestung', (int)$db->lastInsertId(), 'angelegt', '', $bezeichnung);
                flash('success', '„' . $bezeichnung . '" angelegt.');
            }
            header('Location: ' . BASE_URL . 'modules/reviermanagement/ausruestung.php?kat=' . urlencode($kat));
            exit;
        }
    }
}

// -------------------------------------------------------
// POST: Wartungseintrag speichern
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_wartung'])) {
    csrfVerify();

    $ausruestung_id     = (int)$_POST['ausruestung_id'];
    $datum              = $_POST['wartung_datum'] ?? '';
    $taetigkeit         = trim($_POST['taetigkeit'] ?? '');
    $kosten             = $_POST['kosten'] !== '' ? (float)$_POST['kosten'] : null;
    $naechste_faelligkeit = $_POST['naechste_faelligkeit'] !== '' ? $_POST['naechste_faelligkeit'] : null;

    if ($datum && $taetigkeit) {
        $db->prepare(
            'INSERT INTO wartungsprotokoll (ausruestung_id, datum, taetigkeit, kosten, naechste_faelligkeit)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([$ausruestung_id, $datum, $taetigkeit, $kosten, $naechste_faelligkeit]);
        flash('success', 'Wartungseintrag gespeichert.');
    }
    header('Location: ' . BASE_URL . 'modules/reviermanagement/ausruestung.php?action=edit&id=' . $ausruestung_id);
    exit;
}

// -------------------------------------------------------
// POST: Wartungseintrag löschen
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_wartung'])) {
    csrfVerify();
    $wId = (int)$_POST['wartung_id'];
    $aId = (int)$_POST['ausruestung_id'];
    $db->prepare('DELETE FROM wartungsprotokoll WHERE id = ?')->execute([$wId]);
    header('Location: ' . BASE_URL . 'modules/reviermanagement/ausruestung.php?action=edit&id=' . $aId);
    exit;
}

// -------------------------------------------------------
// GET: Löschen
// -------------------------------------------------------
if ($action === 'delete' && $editId > 0) {
    csrfVerify();
    $row = $db->prepare('SELECT bezeichnung, foto_pfad FROM ausruestung WHERE id = ?');
    $row->execute([$editId]);
    $eintrag = $row->fetch();
    if ($eintrag) {
        if ($eintrag['foto_pfad']) loescheDatei('ausruestung', $eintrag['foto_pfad']);
        $db->prepare('DELETE FROM ausruestung WHERE id = ?')->execute([$editId]);
        protokollieren('ausruestung', $editId, 'gelöscht', $eintrag['bezeichnung'], '');
        flash('success', 'Ausrüstung gelöscht.');
    }
    header('Location: ' . BASE_URL . 'modules/reviermanagement/ausruestung.php');
    exit;
}

// -------------------------------------------------------
// Daten laden
// -------------------------------------------------------
$whereKat = ($kategorie !== 'alle' && in_array($kategorie, $kategorien, true))
            ? 'WHERE kategorie = ' . $db->quote($kategorie)
            : '';
$ausruestungListe = $db->query('SELECT * FROM ausruestung ' . $whereKat . ' ORDER BY kategorie, bezeichnung')->fetchAll();

// Bearbeiten
$edit = null;
$wartungen = [];
if (($action === 'edit' || $action === 'add') && $editId > 0) {
    $stmt = $db->prepare('SELECT * FROM ausruestung WHERE id = ?');
    $stmt->execute([$editId]);
    $edit = $stmt->fetch();
    $wStmt = $db->prepare('SELECT * FROM wartungsprotokoll WHERE ausruestung_id = ? ORDER BY datum DESC');
    $wStmt->execute([$editId]);
    $wartungen = $wStmt->fetchAll();
}

// Fälligkeiten (nächste 90 Tage)
$faelligkeiten = $db->query(
    "SELECT a.bezeichnung, a.kategorie, w.datum, w.naechste_faelligkeit, w.taetigkeit
     FROM wartungsprotokoll w
     JOIN ausruestung a ON w.ausruestung_id = a.id
     WHERE w.naechste_faelligkeit IS NOT NULL
       AND w.naechste_faelligkeit <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)
     ORDER BY w.naechste_faelligkeit ASC"
)->fetchAll();

include __DIR__ . '/../../shared/ui/header.php';
?>

<!-- Kategorie-Tabs -->
<ul class="nav nav-tabs mb-3">
    <?php
    $tabs = array_merge(['alle' => 'Alle'], array_combine($kategorien, $kategorien));
    foreach ($tabs as $val => $label):
    ?>
    <li class="nav-item">
        <a class="nav-link <?= $kategorie === $val && !$edit && $action !== 'add' ? 'active' : '' ?>"
           href="?kat=<?= urlencode($val) ?>">
            <?= e($label) ?>
        </a>
    </li>
    <?php endforeach; ?>
    <li class="nav-item ms-auto">
        <a class="nav-link btn-jagd text-white" href="?action=add">
            <i class="fas fa-plus me-1"></i>Neu
        </a>
    </li>
</ul>

<?php if ($fehler): ?>
    <div class="alert alert-danger"><?= e($fehler) ?></div>
<?php endif; ?>

<!-- ======================================================
     LISTE
     ====================================================== -->
<?php if (!$edit && $action !== 'add'): ?>

<?php if (!empty($faelligkeiten)): ?>
<div class="alert alert-warning mb-3">
    <strong><i class="fas fa-tools me-2"></i>Wartungen fällig (nächste 90 Tage):</strong>
    <?php foreach ($faelligkeiten as $f): ?>
        <span class="badge bg-warning text-dark ms-2">
            <?= e($f['bezeichnung']) ?> – <?= datumDE($f['naechste_faelligkeit']) ?>
        </span>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <table class="table table-hover mb-0 dt-tabelle">
            <thead class="table-light">
                <tr>
                    <th>Bezeichnung</th>
                    <th>Kategorie</th>
                    <th>Hersteller / Modell</th>
                    <th>Zustand</th>
                    <th>Kaufdatum</th>
                    <th>Kaliber</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($ausruestungListe as $a): ?>
                <tr>
                    <td>
                        <strong><?= e($a['bezeichnung']) ?></strong>
                        <?php if ($a['seriennummer']): ?>
                            <br><small class="text-muted">SN: <?= e($a['seriennummer']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?= e($a['kategorie']) ?></td>
                    <td>
                        <?= e($a['hersteller'] ?? '—') ?>
                        <?php if ($a['modell']): ?>
                            <br><small class="text-muted"><?= e($a['modell']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?= statusBadge($a['zustand']) ?></td>
                    <td><?= datumDE($a['kaufdatum']) ?></td>
                    <td><?= e($a['kaliber'] ?? '—') ?></td>
                    <td class="text-end text-nowrap">
                        <a href="?action=edit&id=<?= $a['id'] ?>"
                           class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-edit"></i>
                        </a>
                        <a href="?action=delete&id=<?= $a['id'] ?>"
                           class="btn btn-sm btn-outline-danger ms-1"
                           data-confirm="„<?= e($a['bezeichnung']) ?>" wirklich löschen?">
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
     FORMULAR (Neu / Bearbeiten) + Wartungsprotokoll
     ====================================================== -->
<?php if ($edit || $action === 'add'): ?>
<div class="row g-3">

    <!-- Stammdaten -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent fw-semibold">
                <?= $edit ? 'Bearbeiten: ' . e($edit['bezeichnung']) : 'Neues Ausrüstungsteil' ?>
            </div>
            <div class="card-body">
                <form method="post" enctype="multipart/form-data">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" value="<?= $edit['id'] ?? 0 ?>">

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Bezeichnung <span class="text-danger">*</span></label>
                            <input type="text" name="bezeichnung" class="form-control" required
                                   value="<?= e($edit['bezeichnung'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Kategorie <span class="text-danger">*</span></label>
                            <select name="kategorie" class="form-select" required id="kategorieSelect">
                                <?php foreach ($kategorien as $k): ?>
                                    <option value="<?= e($k) ?>"
                                        <?= ($edit['kategorie'] ?? $kategorie) === $k ? 'selected' : '' ?>>
                                        <?= e($k) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Zustand</label>
                            <select name="zustand" class="form-select">
                                <?php foreach (['gut' => 'Gut', 'reparaturbeduerftig' => 'Reparaturbedürftig', 'defekt' => 'Defekt'] as $v => $l): ?>
                                    <option value="<?= $v ?>" <?= ($edit['zustand'] ?? 'gut') === $v ? 'selected' : '' ?>><?= $l ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Hersteller</label>
                            <input type="text" name="hersteller" class="form-control"
                                   value="<?= e($edit['hersteller'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Modell</label>
                            <input type="text" name="modell" class="form-control"
                                   value="<?= e($edit['modell'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Seriennummer</label>
                            <input type="text" name="seriennummer" class="form-control"
                                   value="<?= e($edit['seriennummer'] ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Kaufdatum</label>
                            <input type="date" name="kaufdatum" class="form-control"
                                   value="<?= e($edit['kaufdatum'] ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Kaufpreis (€)</label>
                            <input type="number" name="kaufpreis" class="form-control"
                                   step="0.01" min="0"
                                   value="<?= e($edit['kaufpreis'] ?? '') ?>">
                        </div>

                        <!-- Waffen-Felder (nur bei Kategorie Waffe) -->
                        <div id="waffenFelder" class="col-12" style="display:none">
                            <hr class="my-1">
                            <p class="fw-semibold text-jagd mb-2"><i class="fas fa-gun me-1"></i>Waffen-Details</p>
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <label class="form-label">Kaliber</label>
                                    <input type="text" name="kaliber" class="form-control"
                                           placeholder=".308 Win"
                                           value="<?= e($edit['kaliber'] ?? '') ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Magazinkapazität</label>
                                    <input type="number" name="magazin_kapazitaet" class="form-control"
                                           min="1" max="99"
                                           value="<?= e($edit['magazin_kapazitaet'] ?? '') ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Letzter Beschuss</label>
                                    <input type="date" name="letzter_beschuss" class="form-control"
                                           value="<?= e($edit['letzter_beschuss'] ?? '') ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Nächste Beschusszeit</label>
                                    <input type="date" name="naechster_beschuss" class="form-control"
                                           value="<?= e($edit['naechster_beschuss'] ?? '') ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Foto -->
                        <div class="col-12">
                            <label class="form-label">Foto</label>
                            <?php if (!empty($edit['foto_pfad'])): ?>
                                <div class="mb-2">
                                    <img src="<?= BASE_URL ?>uploads/ausruestung/<?= e($edit['foto_pfad']) ?>"
                                         style="height:80px;border-radius:6px;object-fit:cover">
                                </div>
                            <?php endif; ?>
                            <input type="file" name="foto" class="form-control"
                                   accept=".jpg,.jpeg,.png,.webp">
                        </div>

                        <div class="col-12">
                            <label class="form-label">Notizen</label>
                            <textarea name="notizen" class="form-control" rows="2"><?= e($edit['notizen'] ?? '') ?></textarea>
                        </div>

                        <div class="col-12 d-flex gap-2">
                            <button type="submit" name="save_ausruestung" value="1" class="btn btn-jagd">
                                <i class="fas fa-save me-2"></i>Speichern
                            </button>
                            <a href="<?= BASE_URL ?>modules/reviermanagement/ausruestung.php"
                               class="btn btn-outline-secondary">Abbrechen</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Wartungsprotokoll -->
    <?php if ($edit): ?>
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent fw-semibold">
                <i class="fas fa-tools me-2"></i>Wartungsprotokoll
            </div>
            <div class="card-body">

                <!-- Neuer Eintrag -->
                <form method="post" class="mb-3">
                    <?= csrfField() ?>
                    <input type="hidden" name="ausruestung_id" value="<?= $edit['id'] ?>">
                    <div class="row g-2">
                        <div class="col-6">
                            <input type="date" name="wartung_datum" class="form-control form-control-sm"
                                   value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-6">
                            <input type="number" name="kosten" class="form-control form-control-sm"
                                   step="0.01" min="0" placeholder="Kosten (€)">
                        </div>
                        <div class="col-12">
                            <textarea name="taetigkeit" class="form-control form-control-sm" rows="2"
                                      placeholder="Tätigkeit..." required></textarea>
                        </div>
                        <div class="col-12">
                            <input type="date" name="naechste_faelligkeit" class="form-control form-control-sm">
                            <small class="text-muted">Nächste Fälligkeit (optional)</small>
                        </div>
                        <div class="col-12">
                            <button type="submit" name="save_wartung" value="1"
                                    class="btn btn-sm btn-outline-jagd btn-outline-secondary">
                                <i class="fas fa-plus me-1"></i>Eintrag hinzufügen
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Bestehende Einträge -->
                <?php if (empty($wartungen)): ?>
                    <p class="text-muted small">Noch keine Wartungseinträge.</p>
                <?php else: ?>
                    <?php foreach ($wartungen as $w): ?>
                    <div class="wartung-item">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <strong><?= datumDE($w['datum']) ?></strong>
                                <?php if ($w['kosten']): ?>
                                    <span class="text-muted ms-2"><?= number_format($w['kosten'], 2, ',', '.') ?> €</span>
                                <?php endif; ?>
                                <br>
                                <span class="small"><?= e($w['taetigkeit']) ?></span>
                                <?php if ($w['naechste_faelligkeit']): ?>
                                    <br><small class="text-warning">
                                        <i class="fas fa-clock me-1"></i>Fällig: <?= datumDE($w['naechste_faelligkeit']) ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                            <form method="post" class="ms-2">
                                <?= csrfField() ?>
                                <input type="hidden" name="wartung_id" value="<?= $w['id'] ?>">
                                <input type="hidden" name="ausruestung_id" value="<?= $edit['id'] ?>">
                                <button type="submit" name="delete_wartung" value="1"
                                        class="btn btn-sm btn-outline-danger"
                                        data-confirm="Eintrag löschen?">
                                    <i class="fas fa-times"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div><!-- /row -->

<script>
// Waffen-Felder ein-/ausblenden
function toggleWaffenFelder() {
    const kat = document.getElementById('kategorieSelect').value;
    document.getElementById('waffenFelder').style.display = kat === 'Waffe' ? 'block' : 'none';
}
document.getElementById('kategorieSelect').addEventListener('change', toggleWaffenFelder);
toggleWaffenFelder();
</script>
<?php endif; ?>

<?php include __DIR__ . '/../../shared/ui/footer.php'; ?>
