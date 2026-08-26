<?php
// /prof/doc_peda/fiche_des_prevision_anglais.php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

require_prof();

if (!function_exists('e')) {
    function e($val): string {
        return htmlspecialchars((string)($val ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

$previsionId = (int)($_GET['id'] ?? 0);
$profId      = (int)($_SESSION['user_id'] ?? 0);
$error       = '';
$success     = '';

if ($previsionId <= 0) {
    header("Location: fiche_des_prevision.php");
    exit;
}

// --- 1. AJOUT D'UNE LIGNE DE PRÉVISION (ENGLISH) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_add_line'])) {
    $periode           = trim($_POST['periode'] ?? '1ÈRE PERIODE');
    $mois              = trim($_POST['mois'] ?? '');
    $semaineLibelle    = trim($_POST['semaine_libelle'] ?? '');
    $savoirsEssentiels = trim($_POST['savoirs_essentiels'] ?? '');
    $activites         = trim($_POST['activites'] ?? '');
    $observation       = trim($_POST['observation'] ?? '');
    $code              = trim($_POST['code'] ?? '');

    if (empty($mois) || empty($semaineLibelle) || empty($savoirsEssentiels)) {
        $error = "Please fill in all required fields (*).";
    } else {
        try {
            // Génération automatique du code (ex: C1.1, C1.2...) si vide
            if (empty($code)) {
                $pNum = 1;
                if (preg_match('/(\d+)/', $periode, $mP)) {
                    $pNum = (int)$mP[1];
                }

                $stmtCount = $con->prepare("SELECT COUNT(*) AS total FROM prevision_detail WHERE prevision_id = ?");
                $stmtCount->bind_param("i", $previsionId);
                $stmtCount->execute();
                $resCount = $stmtCount->get_result()->fetch_assoc();
                $nextIndex = ((int)($resCount['total'] ?? 0)) + 1;

                $code = "C" . $pNum . "." . $nextIndex;
            }

            // Insertion correspondant exactement aux colonnes de la table prevision_detail (8 paramètres = "isssssss")
            $stmt = $con->prepare("
                INSERT INTO prevision_detail 
                (prevision_id, periode, mois, semaine_libelle, savoirs_essentiels, code, observation, activites)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("isssssss", $previsionId, $periode, $mois, $semaineLibelle, $savoirsEssentiels, $code, $observation, $activites);
            $stmt->execute();
            $success = "Lesson record successfully added with code " . e($code) . "!";
        } catch (Throwable $e) {
            $error = "Error adding record: " . $e->getMessage();
        }
    }
}

// --- 2. SUPPRESSION D'UNE LIGNE ---
if (isset($_GET['delete_line'])) {
    $lineId = (int)$_GET['delete_line'];
    try {
        $stmt = $con->prepare("DELETE FROM prevision_detail WHERE id = ? AND prevision_id = ?");
        $stmt->bind_param("ii", $lineId, $previsionId);
        $stmt->execute();
        header("Location: fiche_des_prevision_anglais.php?id=" . $previsionId);
        exit;
    } catch (Throwable $e) {
        $error = "Error deleting record: " . $e->getMessage();
    }
}

// --- 3. RÉCUPÉRATION DE L'EN-TÊTE ---
$stmt = $con->prepare("
    SELECT p.*, c.intitule AS cours_intitule, CONCAT (cl.description ,' ', cy.description) AS classe_nom,
    ag.nom AS nom, ag.prenom AS prenom
    FROM prevision_matiere p
    JOIN cours c ON c.id = p.cours_id
    JOIN classe cl ON cl.id = c.classe_id
    JOIN cycle cy ON cy.id = cl.cycle
    LEFT JOIN agent ag ON ag.id = p.enseignant_id
    WHERE p.id = ?
");
$stmt->bind_param("i", $previsionId);
$stmt->execute();
$headerInfo = $stmt->get_result()->fetch_assoc();

if (!$headerInfo) {
    die("Forecast record not found.");
}

// --- 4. RÉCUPÉRATION DES DÉTAILS DE LA PRÉVISION ---
$stmtDetails = $con->prepare("
    SELECT d.*, f.id AS fiche_id
    FROM prevision_detail d
    LEFT JOIN fiche_cours f ON f.prevision_detail_id = d.id
    WHERE d.prevision_id = ?
    ORDER BY d.id ASC
");
$stmtDetails->bind_param("i", $previsionId);
$stmtDetails->execute();
$details = $stmtDetails->get_result()->fetch_all(MYSQLI_ASSOC);

require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/navbar.php';
?>

<div class="container-fluid my-4">

    <!-- BARRE D'ACTIONS -->
    <div class="d-flex justify-content-between align-items-center mb-3 no-print">
        <a href="fiche_des_prevision.php" class="btn btn-outline-secondary btn-sm">&larr; Back to List</a>
        <div>
            <button class="btn btn-success btn-sm me-2" data-bs-toggle="modal" data-bs-target="#modalAddLine">
                <i class="bi bi-plus-circle me-1"></i> + Add Line
            </button>
            <!-- <button type="button" class="btn btn-dark btn-sm" onclick="window.print()">
                <i class="bi bi-printer me-1"></i> Print Forecast
            </button> -->
        </div>
    </div>

    <?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show no-print" role="alert">
        <?= e($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show no-print" role="alert">
        <?= e($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="card border-none shadow-sm">
        <div class="card-header bg-primary text-white text-center py-2">
            <h4 class="mb-0 text-uppercase fw-bold">PREVISIONS DE MATIERES - ENGLISH</h4>
        </div>
        <div class="card-body">
            <!-- Header section matching handwritten document -->
            <div class="row g-2 mb-3 fw-bold text-uppercase small">
                <div class="col-md-6">
                    <div>TEACHER: <span
                            class="fw-normal"><?= e(($headerInfo['prenom'] ?? '') . ' ' . ($headerInfo['nom'] ?? '')) ?></span>
                    </div>
                    <div>COURSE: <span class="fw-normal"><?= e($headerInfo['cours_intitule']) ?></span></div>
                    <div>CLASS: <span class="fw-normal"><?= e($headerInfo['classe_nom']) ?></span></div>
                </div>
                <div class="col-md-6 text-md-end">
                    <div>YEAR: <span
                            class="fw-normal"><?= e($headerInfo['anneeScolaire'] ?? date('Y').'-'.(date('Y')+1)) ?></span>
                    </div>
                </div>
            </div>

            <!-- Main Table -->
            <div class="table-responsive">
                <table class="table table-bordered align-middle text-center small">
                    <thead class="table text-uppercase">
                        <tr>
                            <th>MONTHS</th>
                            <th>WEEKS</th>
                            <th>CODE / N° FICHE</th>
                            <th>SUBJECTS</th>
                            <th>ACTIVITIES</th>
                            <th>OBS</th>
                            <th class="no-print">ACTION</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($details)): ?>
                        <tr>
                            <td colspan="7" class="text-muted py-3">No detail records found for this forecast. Click "+
                                Add Line" above.</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($details as $row): ?>
                        <tr>
                            <td class="fw-bold"><?= e($row['mois'] ?? '') ?></td>
                            <td><?= e($row['semaine_libelle'] ?? '') ?></td>
                            <td><code><?= e($row['code'] ?: '—') ?></code></td>
                            <td class="text-start fw-semibold"><?= e($row['savoirs_essentiels'] ?? '') ?></td>
                            <td><?= e($row['activites'] ?? '') ?></td>
                            <td><?= e($row['observation'] ?? '') ?></td>
                            <td class="no-print">
                                <!-- Bouton préparer / éditer la fiche d'anglais -->
                                <a href="fiche_de_cours_anglais.php?prevision_detail_id=<?= (int)$row['id'] ?>"
                                    class="btn btn-sm me-1 <?= !empty($row['fiche_id']) ? 'btn-success' : 'btn-outline-primary' ?>">
                                    <i class="bi bi-file-earmark-text me-1"></i>
                                    <?= !empty($row['fiche_id']) ? 'Edit Lesson Plan' : 'Prepare Lesson' ?>
                                </a>

                                <!-- Bouton supprimer la ligne -->
                                <a href="?id=<?= $previsionId ?>&delete_line=<?= (int)$row['id'] ?>"
                                    class="btn btn-danger btn-sm" onclick="return confirm('Delete this line?')"
                                    title="Delete">&times;</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- MODAL : ADD LINE (ENGLISH FORECAST) -->
<div class="modal fade" id="modalAddLine" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action_add_line" value="1">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title h6"><i class="bi bi-plus-circle me-1"></i> Add Forecast Line (English)</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">PERIOD</label>
                            <select name="periode" class="form-select form-select-sm">
                                <option value="1ÈRE PERIODE" selected>1ÈRE PERIODE</option>
                                <option value="2ÈME PERIODE">2ÈME PERIODE</option>
                                <option value="3ÈME PERIODE">3ÈME PERIODE</option>
                                <option value="4ÈME PERIODE">4ÈME PERIODE</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">MONTHS <span
                                    class="text-danger">*</span></label>
                            <input type="text" name="mois" class="form-control form-control-sm"
                                placeholder="e.g. SEPTEMBER" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">WEEKS <span class="text-danger">*</span></label>
                            <input type="text" name="semaine_libelle" class="form-control form-control-sm"
                                placeholder="e.g. Week 1 (07-11)" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small fw-semibold">SUBJECTS (Content) <span
                                    class="text-danger">*</span></label>
                            <textarea name="savoirs_essentiels" class="form-control form-control-sm" rows="3"
                                placeholder="e.g. Greetings and Introductions" required></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">CODE / N° FICHE (Auto)</label>
                            <input type="text" name="code" class="form-control form-control-sm"
                                placeholder="Auto (e.g. C1.1)">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">ACTIVITIES <span
                                    class="text-danger">*</span></label>
                            <input type="text" name="activites" class="form-control form-control-sm"
                                placeholder="e.g. Group Roleplay" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">OBSERVATION</label>
                            <input type="text" name="observation" class="form-control form-control-sm"
                                placeholder="e.g. Pending">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success btn-sm">Save Line</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>