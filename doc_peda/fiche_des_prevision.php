<?php
// /prof/doc_peda/fiche_des_prevision.php
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

function separerCoursEtBranche(string $intitule): array {
    if (preg_match('/^(.*?)\s*\((.*?)\)$/', trim($intitule), $matches)) {
        return [
            'categorie'      => trim($matches[1]),
            'sous_categorie' => trim($matches[2])
        ];
    }
    return [
        'categorie'      => $intitule,
        'sous_categorie' => '—'
    ];
}

$currentMonth = (int)date('n');
$currentYear  = (int)date('Y');
$anneeEnCours = ($currentMonth >= 8) ? $currentYear . '-' . ($currentYear + 1) : ($currentYear - 1) . '-' . $currentYear;

$error   = '';
$success = '';

$prof         = current_prof();
$enseignantId = (int)($prof['id'] ?? 0);
$classeId     = get_current_classe(); 
$previsionId  = (int)($_GET['id'] ?? 0);

// --- 1. CRÉATION / OUVERTURE D'UNE FICHE SPÉCIFIQUE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_create_head'])) {
    $coursId       = (int)($_POST['cours_id'] ?? 0);
    $anneeScolaire = trim($_POST['anneeScolaire'] ?? '');

    if ($coursId <= 0 || empty($anneeScolaire)) {
        $error = "Veuillez sélectionner un cours et préciser l'année scolaire.";
    } else {
        try {
            $stmtC = $con->prepare("SELECT intitule FROM cours WHERE id = ?");
            $stmtC->bind_param("i", $coursId);
            $stmtC->execute();
            $coursInfo = $stmtC->get_result()->fetch_assoc();
            $intituleCours = mb_strtoupper($coursInfo['intitule'] ?? '', 'UTF-8');

            $chk = $con->prepare("SELECT id FROM prevision_matiere WHERE cours_id = ? AND anneeScolaire = ? LIMIT 1");
            $chk->bind_param("is", $coursId, $anneeScolaire);
            $chk->execute();
            $existing = $chk->get_result()->fetch_assoc();

            if ($existing) {
                $previsionId = (int)$existing['id'];
            } else {
                $stmt = $con->prepare("INSERT INTO prevision_matiere (enseignant_id, cours_id, anneeScolaire) VALUES (?, ?, ?)");
                $stmt->bind_param("iis", $enseignantId, $coursId, $anneeScolaire);
                $stmt->execute();
                $previsionId = (int)$con->insert_id;
            }

            if (strpos($intituleCours, 'ANGLAIS') !== false || strpos($intituleCours, 'ENGLISH') !== false) {
                header("Location: fiche_des_prevision_anglais.php?id=" . $previsionId);
            } else {
                header("Location: fiche_des_prevision.php?id=" . $previsionId);
            }
            exit;

        } catch (Throwable $e) {
            $error = "Erreur SQL : " . $e->getMessage();
        }
    }
}

// --- 2. AJOUT D'UNE LEÇON DE PRÉVISION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_add_lecon'])) {
    $targetPrevisionId = (int)($_POST['target_prevision_id'] ?? $previsionId);
    $periode           = trim($_POST['periode'] ?? '1ÈRE PERIODE');
    $mois              = trim($_POST['mois'] ?? '');
    $semaine           = trim($_POST['semaine_libelle'] ?? '');
    $savoirsEssentiels = trim($_POST['savoirs_essentiels'] ?? '');
    $activites         = trim($_POST['activites'] ?? '');
    $code              = trim($_POST['code'] ?? '');
    $observation       = trim($_POST['observation'] ?? '');

    if ($targetPrevisionId <= 0 || empty($mois) || empty($semaine) || empty($savoirsEssentiels)) {
        $error = "Veuillez remplir tous les champs obligatoires (*).";
    } else {
        try {
            if (empty($code)) {
                $pNum = 1;
                if (preg_match('/(\d+)/', $periode, $mP)) {
                    $pNum = (int)$mP[1];
                }

                $stmtCount = $con->prepare("SELECT COUNT(*) AS total FROM prevision_detail WHERE prevision_id = ?");
                $stmtCount->bind_param("i", $targetPrevisionId);
                $stmtCount->execute();
                $resCount = $stmtCount->get_result()->fetch_assoc();
                $nextIndex = ((int)($resCount['total'] ?? 0)) + 1;

                $code = "C" . $pNum . "." . $nextIndex;
            }

            $stmt = $con->prepare("
                INSERT INTO prevision_detail (prevision_id, periode, mois, semaine_libelle, savoirs_essentiels, code, observation, activites)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("isssssss", $targetPrevisionId, $periode, $mois, $semaine, $savoirsEssentiels, $code, $observation, $activites);
            $stmt->execute();
            $success = "Prévision enregistrée avec le code " . e($code) . " !";
        } catch (Throwable $e) {
            $error = "Erreur d'enregistrement : " . $e->getMessage();
        }
    }
}

// --- 3. MODIFICATION D'UNE LEÇON DE PRÉVISION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_edit_lecon'])) {
    $lineId            = (int)($_POST['line_id'] ?? 0);
    $periode           = trim($_POST['periode'] ?? '');
    $mois              = trim($_POST['mois'] ?? '');
    $semaine           = trim($_POST['semaine_libelle'] ?? '');
    $savoirsEssentiels = trim($_POST['savoirs_essentiels'] ?? '');
    $activites         = trim($_POST['activites'] ?? '');
    $code              = trim($_POST['code'] ?? '');
    $observation       = trim($_POST['observation'] ?? '');

    if ($lineId <= 0 || empty($mois) || empty($semaine) || empty($savoirsEssentiels)) {
        $error = "Veuillez remplir tous les champs obligatoires pour la modification.";
    } else {
        try {
            $stmt = $con->prepare("
                UPDATE prevision_detail 
                SET periode = ?, mois = ?, semaine_libelle = ?, savoirs_essentiels = ?, code = ?, observation = ?, activites = ?
                WHERE id = ?
            ");
            $stmt->bind_param("sssssssi", $periode, $mois, $semaine, $savoirsEssentiels, $code, $observation, $activites, $lineId);
            $stmt->execute();
            $success = "Ligne de prévision mise à jour avec succès !";
        } catch (Throwable $e) {
            $error = "Erreur de modification : " . $e->getMessage();
        }
    }
}

// --- 4. SUPPRESSION D'UNE LIGNE ---
if (isset($_GET['delete_line'])) {
    $lineId = (int)$_GET['delete_line'];
    try {
        $stmt = $con->prepare("DELETE FROM prevision_detail WHERE id = ?");
        $stmt->bind_param("i", $lineId);
        $stmt->execute();
        header("Location: fiche_des_prevision.php" . ($previsionId > 0 ? "?id=" . $previsionId : ""));
        exit;
    } catch (Throwable $e) {
        $error = "Erreur de suppression : " . $e->getMessage();
    }
}

// --- 5. CHARGEMENT DES PRÉVISIONS ---
$details = [];
$head    = null;

try {
    if ($previsionId > 0) {
        $stmtHead = $con->prepare("
            SELECT p.*, c.intitule AS cours_intitule, cl.description AS classe_nom, cy.description AS cycle_nom
            FROM prevision_matiere p
            JOIN cours c ON c.id = p.cours_id
            JOIN classe cl ON cl.id = c.classe_id
            LEFT JOIN cycle cy ON cy.id = cl.cycle
            WHERE p.id = ?
        ");
        $stmtHead->bind_param("i", $previsionId);
        $stmtHead->execute();
        $head = $stmtHead->get_result()->fetch_assoc();

        $stmtD = $con->prepare("
            SELECT d.*, p.id AS prevision_id, c.intitule AS cours_intitule, f.id AS fiche_id
            FROM prevision_detail d
            JOIN prevision_matiere p ON p.id = d.prevision_id
            JOIN cours c ON c.id = p.cours_id
            LEFT JOIN fiche_cours f ON f.prevision_detail_id = d.id
            WHERE d.prevision_id = ?
            ORDER BY d.id ASC
        ");
        $stmtD->bind_param("i", $previsionId);
    } else if ($classeId > 0 && $enseignantId > 0) {
        $stmtD = $con->prepare("
            SELECT d.*, p.id AS prevision_id, c.intitule AS cours_intitule, f.id AS fiche_id
            FROM prevision_detail d
            JOIN prevision_matiere p ON p.id = d.prevision_id
            JOIN cours c ON c.id = p.cours_id
            LEFT JOIN fiche_cours f ON f.prevision_detail_id = d.id
            WHERE c.classe_id = ? AND p.enseignant_id = ?
            ORDER BY c.intitule ASC, d.id ASC
        ");
        $stmtD->bind_param("ii", $classeId, $enseignantId);
    } else {
        $stmtD = null;
    }

    if ($stmtD) {
        $stmtD->execute();
        $resD = $stmtD->get_result();
        while ($row = $resD->fetch_assoc()) {
            $details[] = $row;
        }
    }
} catch (Throwable $e) {
    $error = "Erreur de chargement : " . $e->getMessage();
}

// --- 6. CHARGEMENT DE LA LISTE DES PRÉVISIONS EXISTANTES ---
$listePrevisions = [];
if ($classeId > 0 && $enseignantId > 0) {
    $stmtP = $con->prepare("
        SELECT p.id AS prevision_id, c.id AS cours_id, c.intitule AS cours_intitule
        FROM prevision_matiere p
        JOIN cours c ON c.id = p.cours_id
        WHERE c.classe_id = ? AND p.enseignant_id = ?
        ORDER BY c.intitule ASC
    ");
    $stmtP->bind_param("ii", $classeId, $enseignantId);
    $stmtP->execute();
    $resP = $stmtP->get_result();
    while ($row = $resP->fetch_assoc()) {
        $listePrevisions[] = $row;
    }
}

// --- 7. CHARGEMENT DES COURS POUR CRÉATION ---
$listeCours = [];
if ($classeId > 0 && $enseignantId > 0) {
    $stmtC = $con->prepare("
        SELECT DISTINCT c.id, c.intitule
        FROM affectation_prof_classe a
        INNER JOIN cours c ON c.id = a.cours_id
        WHERE a.agent_id = ? AND a.classe_id = ?
        ORDER BY c.intitule ASC
    ");
    $stmtC->bind_param("ii", $enseignantId, $classeId);
    $stmtC->execute();
    $resC = $stmtC->get_result();
    while ($row = $resC->fetch_assoc()) {
        $listeCours[] = $row;
    }
}

require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/navbar.php';
?>

<style>
.style-grid th,
.style-grid td {
    border: 1px solid #857f7f !important;
    vertical-align: middle;
}

.sortable-header {
    cursor: pointer;
    user-select: none;
}

.sortable-header:hover {
    /* background-color: #343a40 !important; */
}

@media print {

    .no-print,
    .btn,
    nav,
    .navbar {
        display: none !important;
    }

    .card {
        border: none !important;
    }
}
</style>

<div class="container-fluid my-4 px-4">

    <?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show no-print" role="alert">
        <?= e($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show no-print" role="alert">
        <?= e($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if (!$classeId): ?>
    <div class="alert alert-warning text-center my-5 py-4">
        <i class="bi bi-exclamation-triangle fs-1 d-block mb-2"></i>
        <h5>Aucune classe sélectionnée</h5>
        <p class="mb-0">Veuillez choisir une classe dans la barre de navigation pour consulter vos prévisions.</p>
    </div>

    <?php else: ?>

    <!-- BARRE D'ACTIONS ET FILTRE -->
    <div class="d-flex justify-content-between align-items-center mb-3 no-print flex-wrap gap-2">
        <div>
            <h1 class="h4 mb-0 text-uppercase fw-bold">
                <?= $head ? 'PLAN DE TRAVAIL : ' . e($head['cours_intitule']) : 'TOUTES MES PRÉVISIONS' ?>
            </h1>
            <span class="text-muted small">
                Consultez et gérez l'ensemble des prévisions de la classe.
            </span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <!-- CHAMP DE RECHERCHE / FILTRE -->
            <input type="text" id="searchInput" class="form-control form-control-sm"
                placeholder="🔍 Filtrer les résultats..." style="width: 200px;">

            <?php if ($previsionId > 0): ?>
            <a href="fiche_des_prevision.php" class="btn btn-outline-secondary btn-sm me-1">
                &larr; Voir tout
            </a>
            <?php endif; ?>

            <button class="btn btn-primary btn-sm me-1" data-bs-toggle="modal" data-bs-target="#modalCreateHead">
                <i class="bi bi-journal-plus me-1"></i> Créer/Ouvrir une Fiche
            </button>

            <?php if (!empty($listePrevisions)): ?>
            <button class="btn btn-success btn-sm me-1" data-bs-toggle="modal" data-bs-target="#modalAddLecon">
                <i class="bi bi-plus-circle me-1"></i> + Ajouter une ligne
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- TABLEAU PRINCIPAL -->
    <div class="cards dshadow-sm border-0" style="border:none !important">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle text-center" id="mainTable">
                    <thead class="table small text-uppercase">
                        <tr>
                            <!-- <th class="sortable-header" onclick="sortTable(0)" style="width: 11%;">COURS / MATIÈRE ⇕
                            </th> -->
                            <th class="sortable-header" onclick="sortTable(1)" style="width: 9%;">PÉRIODE ⇕</th>
                            <th class="sortable-header" onclick="sortTable(2)" style="width: 9%;">MOIS ⇕</th>
                            <th class="sortable-header" onclick="sortTable(3)" style="width: 10%;">SEMAINE ⇕</th>
                            <th class="sortable-header" onclick="sortTable(4)" style="width: 11%;">BRANCHE/CAT... ⇕</th>
                            <th class="sortable-header" onclick="sortTable(5)" style="width: 11%;">SOUS-BRANCHE/CAT... ⇕
                            </th>
                            <th class="sortable-header" onclick="sortTable(6)">SAVOIRS ESSENTIELS ⇕</th>
                            <th class="sortable-header" onclick="sortTable(7)" style="width: 9%;">CODE / N° ⇕</th>
                            <th class="sortable-header" onclick="sortTable(8)" style="width: 9%;">OBS ⇕</th>
                            <th style="width: 18%;" class="no-print">#</th>
                        </tr>
                    </thead>
                    <tbody class="small" id="tableBody">
                        <?php if (empty($details)): ?>
                        <tr>
                            <td colspan="10" class="text-muted py-4">
                                Aucune prévision enregistrée. Cliquez sur <strong>"Créer/Ouvrir une Fiche"</strong> pour
                                commencer.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($details as $row): 
                            $parsed = separerCoursEtBranche($row['cours_intitule'] ?? '');
                            $coursUpper = mb_strtoupper($row['cours_intitule'] ?? '', 'UTF-8');
                            $isAnglais = (strpos($coursUpper, 'ANGLAIS') !== false || strpos($coursUpper, 'ENGLISH') !== false);
                            
                            $pageTarget = $isAnglais ? 'fiche_des_prevision_anglais.php' : 'fiche_des_prevision.php';
                            $targetLessonFile = $isAnglais ? 'fiche_de_cours_anglais.php' : 'fiche_de_cours.php';
                        ?>
                        <tr>
                            <!-- <td class="fw-bold bg-light text-start text-uppercase"><?= e($row['cours_intitule']) ?></td> -->
                            <td class="text-primary"><?= e($row['periode'] ?? '') ?></td>
                            <td><?= e($row['mois'] ?? '') ?></td>
                            <td class="text-danger"><?= e($row['semaine_libelle'] ?? '') ?></td>
                            <td class="fw-bold text-dark"><?= e($parsed['categorie']) ?></td>
                            <td class="fw-semibold text-primary"><?= e($parsed['sous_categorie']) ?></td>
                            <td class="text-start"><?= nl2br(e($row['savoirs_essentiels'] ?? '')) ?></td>
                            <td><code class="fw-bold"><?= e($row['code'] ?: '—') ?></code></td>
                            <td class="text-muted"><?= e($row['observation'] ?: '—') ?></td>
                            <td class="no-print">
                                <a href="<?= $pageTarget ?>?id=<?= (int)$row['prevision_id'] ?>"
                                    class="btn btn-dark btn-sm me-1 mb-1" title="Ouvrir la fiche de prévision complète">
                                    <i class="bi bi-box-arrow-in-right"></i> Ouvrir
                                </a>

                                <button type="button" class="btn btn-warning btn-sm me-1 mb-1" data-bs-toggle="modal"
                                    data-bs-target="#modalEditLine<?= (int)$row['id'] ?>" title="Modifier la ligne">
                                    <i class="bi bi-pencil-square"></i> Modifier
                                </button>

                                <a href="<?= $targetLessonFile ?>?prevision_detail_id=<?= (int)$row['id'] ?>"
                                    class="btn btn-sm me-1 mb-1 <?= !empty($row['fiche_id']) ? 'btn-success' : 'btn-outline-primary' ?>"
                                    title="Fiche de cours">
                                    <i class="bi bi-file-earmark-text"></i> Fiche détaillée
                                </a>

                                <a href="?delete_line=<?= (int)$row['id'] ?><?= $previsionId > 0 ? '&id='.$previsionId : '' ?>"
                                    class="btn btn-danger btn-sm mb-1"
                                    onclick="return confirm('Supprimer cette ligne de prévision ?')"
                                    title="Supprimer">&times;</a>
                            </td>
                        </tr>

                        <!-- MODAL DE MODIFICATION POUR CETTE LIGNE -->
                        <div class="modal fade" id="modalEditLine<?= (int)$row['id'] ?>" tabindex="-1"
                            aria-hidden="true">
                            <div class="modal-dialog modal-lg text-start">
                                <div class="modal-content">
                                    <form method="POST">
                                        <input type="hidden" name="action_edit_lecon" value="1">
                                        <input type="hidden" name="line_id" value="<?= (int)$row['id'] ?>">
                                        <div class="modal-header bg-warning text-dark">
                                            <h5 class="modal-title h6"><i class="bi bi-pencil-square me-1"></i> Modifier
                                                la prévision</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                aria-label="Fermer"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="row g-3">
                                                <div class="col-md-4">
                                                    <label class="form-label small fw-semibold">Période <span
                                                            class="text-danger">*</span></label>
                                                    <select name="periode" class="form-select form-select-sm" required>
                                                        <option value="1ÈRE PERIODE"
                                                            <?= ($row['periode'] == '1ÈRE PERIODE') ? 'selected' : '' ?>>
                                                            1ÈRE PERIODE</option>
                                                        <option value="2ÈME PERIODE"
                                                            <?= ($row['periode'] == '2ÈME PERIODE') ? 'selected' : '' ?>>
                                                            2ÈME PERIODE</option>
                                                        <option value="3ÈME PERIODE"
                                                            <?= ($row['periode'] == '3ÈME PERIODE') ? 'selected' : '' ?>>
                                                            3ÈME PERIODE</option>
                                                        <option value="4ÈME PERIODE"
                                                            <?= ($row['periode'] == '4ÈME PERIODE') ? 'selected' : '' ?>>
                                                            4ÈME PERIODE</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label small fw-semibold">Mois <span
                                                            class="text-danger">*</span></label>
                                                    <input type="text" name="mois" class="form-control form-control-sm"
                                                        value="<?= e($row['mois']) ?>" required>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label small fw-semibold">Semaine <span
                                                            class="text-danger">*</span></label>
                                                    <input type="text" name="semaine_libelle"
                                                        class="form-control form-control-sm"
                                                        value="<?= e($row['semaine_libelle']) ?>" required>
                                                </div>
                                                <div class="col-md-12">
                                                    <label class="form-label small fw-semibold">Savoirs Essentiels
                                                        (Contenu) <span class="text-danger">*</span></label>
                                                    <textarea name="savoirs_essentiels"
                                                        class="form-control form-control-sm" rows="3"
                                                        required><?= e($row['savoirs_essentiels']) ?></textarea>
                                                </div>

                                                <?php if ($isAnglais): ?>
                                                <div class="col-md-4">
                                                    <label class="form-label small fw-semibold">Activités</label>
                                                    <br><small class="text-danger">N.B: Activités, c'est pour les cours
                                                        d'anglais</small>
                                                    <input type="text" name="activites"
                                                        class="form-control form-control-sm"
                                                        value="<?= e($row['activites'] ?? '') ?>">
                                                </div>
                                                <?php endif; ?>

                                                <div class="col-md-4">
                                                    <label class="form-label small fw-semibold">Code / N° Fiche</label>
                                                    <input type="text" name="code" class="form-control form-control-sm"
                                                        value="<?= e($row['code']) ?>" readonly>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label small fw-semibold">Observation</label>
                                                    <input type="text" name="observation"
                                                        class="form-control form-control-sm"
                                                        value="<?= e($row['observation'] ?? '') ?>">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary btn-sm"
                                                data-bs-dismiss="modal">Annuler</button>
                                            <button type="submit" class="btn btn-warning btn-sm">Mettre à jour</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- MODAL : AJOUT D'UNE LIGNE -->
    <div class="modal fade" id="modalAddLecon" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action_add_lecon" value="1">
                    <div class="modal-header bg-dark text-white">
                        <h5 class="modal-title h6"><i class="bi bi-plus-circle me-1"></i> Ajouter une prévision</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                            aria-label="Fermer"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label small fw-semibold">Choisir le cours / la matière <span
                                        class="text-danger">*</span></label>
                                <select name="target_prevision_id" id="selectTargetPrevision"
                                    class="form-select form-select-sm" required>
                                    <?php foreach ($listePrevisions as $lp): ?>
                                    <option value="<?= (int)$lp['prevision_id'] ?>"
                                        data-intitule="<?= e(mb_strtoupper($lp['cours_intitule'])) ?>"
                                        <?= ($previsionId == $lp['prevision_id']) ? 'selected' : '' ?>>
                                        <?= e($lp['cours_intitule']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Période <span
                                        class="text-danger">*</span></label>
                                <select name="periode" class="form-select form-select-sm" required>
                                    <option value="1ÈRE PERIODE" selected>1ÈRE PERIODE</option>
                                    <option value="2ÈME PERIODE">2ÈME PERIODE</option>
                                    <option value="3ÈME PERIODE">3ÈME PERIODE</option>
                                    <option value="4ÈME PERIODE">4ÈME PERIODE</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Mois <span
                                        class="text-danger">*</span></label>
                                <input type="text" name="mois" class="form-control form-control-sm"
                                    placeholder="ex: SEPTEMBRE 2026" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Semaine <span
                                        class="text-danger">*</span></label>
                                <input type="text" name="semaine_libelle" class="form-control form-control-sm"
                                    placeholder="ex: Du 07 au 11" required>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label small fw-semibold">Savoirs Essentiels (Contenu) <span
                                        class="text-danger">*</span></label>
                                <textarea name="savoirs_essentiels" class="form-control form-control-sm" rows="3"
                                    placeholder="ex: Les fonctions de l'adjectif qualificatif." required></textarea>
                            </div>

                            <div class="col-md-4" id="containerActivites">
                                <label class="form-label small fw-semibold">Activités</label>
                                <input type="text" name="activites" class="form-control form-control-sm"
                                    placeholder="ex: Exercices pratiques">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Code / N° Fiche (Auto)</label>
                                <input type="text" name="code" class="form-control form-control-sm"
                                    placeholder="Auto (ex: C1.1)">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Observation</label>
                                <input type="text" name="observation" class="form-control form-control-sm"
                                    placeholder="ex: En attente">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary btn-sm">Enregistrer la ligne</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL : CRÉER / OUVRIR FICHE MÈRE -->
    <div class="modal fade" id="modalCreateHead" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action_create_head" value="1">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title h6"><i class="bi bi-file-earmark-plus me-2"></i>Ouvrir ou Créer une fiche
                            de matière</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                            aria-label="Fermer"></button>
                    </div>
                    <div class="modal-body">
                        <?php if (empty($listeCours)): ?>
                        <div class="text-center text-muted py-3">
                            <i class="bi bi-journal-x fs-2 d-block mb-2"></i>
                            Aucun cours ne vous a été attribué pour cette classe.
                        </div>
                        <?php else: ?>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Sélectionner un cours <span
                                    class="text-danger">*</span></label>
                            <select name="cours_id" class="form-select" required>
                                <option value="">-- Choisir un cours --</option>
                                <?php foreach ($listeCours as $cr): ?>
                                <option value="<?= (int)$cr['id'] ?>">
                                    <?= e($cr['intitule']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Année Scolaire <span
                                    class="text-danger">*</span></label>
                            <input type="text" name="anneeScolaire" class="form-control" value="<?= e($anneeEnCours) ?>"
                                readonly required>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Annuler</button>
                        <?php if (!empty($listeCours)): ?>
                        <button type="submit" class="btn btn-primary btn-sm">Ouvrir / Créer &rarr;</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php endif; ?>

</div>

<!-- SCRIPTS DYNAMIQUES: MASQUAGE, RECHERCHE ET TRI -->
<script>
document.addEventListener('DOMContentLoaded', function() {

    // 1. MASQUAGE/AFFICHAGE DU CHAMP ACTIVITÉS
    const selectCours = document.getElementById('selectTargetPrevision');
    const containerActivites = document.getElementById('containerActivites');

    function checkActiviteVisibility() {
        if (!selectCours || !containerActivites) return;
        const selectedOption = selectCours.options[selectCours.selectedIndex];
        if (!selectedOption) return;

        const intitule = (selectedOption.getAttribute('data-intitule') || '').toUpperCase();
        if (intitule.includes('ANGLAIS') || intitule.includes('ENGLISH')) {
            containerActivites.style.display = 'block';
        } else {
            containerActivites.style.display = 'none';
        }
    }

    if (selectCours) {
        selectCours.addEventListener('change', checkActiviteVisibility);
        checkActiviteVisibility();
    }

    // 2. FILTRAGE EN TEMPS RÉEL (RECHERCHE)
    const searchInput = document.getElementById('searchInput');
    const tableBody = document.getElementById('tableBody');

    if (searchInput && tableBody) {
        searchInput.addEventListener('input', function() {
            const filter = this.value.toLowerCase();
            const rows = tableBody.getElementsByTagName('tr');

            for (let i = 0; i < rows.length; i++) {
                const text = rows[i].textContent.toLowerCase();
                rows[i].style.display = text.includes(filter) ? '' : 'none';
            }
        });
    }
});

// 3. TRI DES COLONNES DU TABLEAU
let sortDirections = {};

function sortTable(columnIndex) {
    const table = document.getElementById("mainTable");
    const tbody = table.querySelector("tbody");
    const rows = Array.from(tbody.querySelectorAll("tr"));

    // Inverser la direction du tri pour la colonne
    sortDirections[columnIndex] = !sortDirections[columnIndex];
    const isAscending = sortDirections[columnIndex];

    rows.sort((rowA, rowB) => {
        const cellA = rowA.children[columnIndex]?.innerText.trim().toLowerCase() || '';
        const cellB = rowB.children[columnIndex]?.innerText.trim().toLowerCase() || '';

        return isAscending ?
            cellA.localeCompare(cellB, undefined, {
                numeric: true
            }) :
            cellB.localeCompare(cellA, undefined, {
                numeric: true
            });
    });

    rows.forEach(row => tbody.appendChild(row));
}
</script>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>