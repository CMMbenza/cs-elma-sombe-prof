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

// Extraction de la Catégorie et Sous-catégorie (ex: "FRANCAIS (GRAMMAIRE)")
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

// Calcul dynamique de l'année scolaire en cours (ex: 2026-2027)
$currentMonth = (int)date('n');
$currentYear  = (int)date('Y');
if ($currentMonth >= 8) {
    $anneeEnCours = $currentYear . '-' . ($currentYear + 1);
} else {
    $anneeEnCours = ($currentYear - 1) . '-' . $currentYear;
}

$error   = '';
$success = '';

$prof         = current_prof();
$enseignantId = (int)($prof['id'] ?? 0);
$classeId     = get_current_classe(); 
$previsionId  = (int)($_GET['id'] ?? 0);

// --- 1. CRÉATION / OUVERTURE DE LA FICHE VIA MODAL + REDIRECTION ANGLAIS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_create_head'])) {
    $coursId       = (int)($_POST['cours_id'] ?? 0);
    $anneeScolaire = trim($_POST['anneeScolaire'] ?? '');

    if ($coursId <= 0 || empty($anneeScolaire)) {
        $error = "Veuillez sélectionner un cours et préciser l'année scolaire.";
    } else {
        try {
            // Récupération de l'intitulé du cours pour détecter l'Anglais
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

            // REDIRECTION INTELLIGENTE SELON LA MATIÈRE
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

// --- 2. AJOUT D'UNE LEÇON AVEC CODE INCRÉMENTAL AUTOMATIQUE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_add_lecon']) && $previsionId > 0) {
    $periode           = trim($_POST['periode'] ?? '');
    $mois              = trim($_POST['mois'] ?? '');
    $semaine           = trim($_POST['semaine_libelle'] ?? '');
    $savoirsEssentiels = trim($_POST['savoirs_essentiels'] ?? '');
    $code              = trim($_POST['code'] ?? '');
    $dateExecution     = !empty($_POST['date_execution']) ? $_POST['date_execution'] : null;
    $observation       = trim($_POST['observation'] ?? '');

    if (empty($periode) || empty($mois) || empty($semaine) || empty($savoirsEssentiels)) {
        $error = "Veuillez remplir les champs obligatoires (*).";
    } else {
        try {
            if (empty($code)) {
                $pNum = 1;
                if (preg_match('/(\d+)/', $periode, $mP)) {
                    $pNum = (int)$mP[1];
                }

                $stmtCount = $con->prepare("SELECT COUNT(*) AS total FROM prevision_detail WHERE prevision_id = ? AND periode = ?");
                $stmtCount->bind_param("is", $previsionId, $periode);
                $stmtCount->execute();
                $resCount = $stmtCount->get_result()->fetch_assoc();
                $nextIndex = ((int)($resCount['total'] ?? 0)) + 1;

                $code = "C" . $pNum . "." . $nextIndex;
            }

            $stmt = $con->prepare("
                INSERT INTO prevision_detail (prevision_id, periode, mois, semaine_libelle, savoirs_essentiels, code, observation)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("issssss", $previsionId, $periode, $mois, $semaine, $savoirsEssentiels, $code, $observation);
            $stmt->execute();
            $success = "Leçon enregistrée avec le code " . e($code) . " !";
        } catch (Throwable $e) {
            $error = "Erreur d'enregistrement : " . $e->getMessage();
        }
    }
}

// --- 3. SUPPRESSION D'UNE LIGNE ---
if (isset($_GET['delete_line']) && $previsionId > 0) {
    $lineId = (int)$_GET['delete_line'];
    try {
        $stmt = $con->prepare("DELETE FROM prevision_detail WHERE id = ? AND prevision_id = ?");
        $stmt->bind_param("ii", $lineId, $previsionId);
        $stmt->execute();
        header("Location: fiche_des_prevision.php?id=" . $previsionId);
        exit;
    } catch (Throwable $e) {
        $error = "Erreur de suppression : " . $e->getMessage();
    }
}

// --- 4. CHARGEMENT DE LA FICHE EN COURS ---
$head        = null;
$details     = [];
$coursParsed = ['categorie' => '', 'sous_categorie' => ''];
$isAnglais   = false;

if ($previsionId > 0) {
    try {
        $stmt = $con->prepare("
            SELECT p.*, c.intitule AS cours_intitule, cl.description AS classe_nom, cy.description AS cycle_nom
            FROM prevision_matiere p
            JOIN cours c ON c.id = p.cours_id
            JOIN classe cl ON cl.id = c.classe_id
            LEFT JOIN cycle cy ON cy.id = cl.cycle
            WHERE p.id = ?
        ");
        $stmt->bind_param("i", $previsionId);
        $stmt->execute();
        $head = $stmt->get_result()->fetch_assoc();

        if ($head) {
            $coursParsed = separerCoursEtBranche($head['cours_intitule']);
            
            // Détection si la prévision en cours concerne l'anglais
            $intituleUpper = mb_strtoupper($head['cours_intitule'], 'UTF-8');
            $isAnglais     = (strpos($intituleUpper, 'ANGLAIS') !== false || strpos($intituleUpper, 'ENGLISH') !== false);

            $stmtD = $con->prepare("SELECT * FROM prevision_detail WHERE prevision_id = ? ORDER BY id ASC");
            $stmtD->bind_param("i", $previsionId);
            $stmtD->execute();
            $resD = $stmtD->get_result();
            while ($row = $resD->fetch_assoc()) {
                $details[] = $row;
            }
        }
    } catch (Throwable $e) {
        $error = "Erreur de chargement : " . $e->getMessage();
    }
}

// --- 5. FILTRAGE DES COURS DE L'ENSEIGNANT VIA affectation_prof_classe ---
$listeCours = [];
if ($classeId > 0 && $enseignantId > 0) {
    $stmtC = $con->prepare("
        SELECT DISTINCT c.id, c.intitule, cl.description AS classe_nom
        FROM affectation_prof_classe a
        INNER JOIN cours c ON c.id = a.cours_id
        INNER JOIN classe cl ON cl.id = a.classe_id
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
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= e($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= e($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- AUCUNE CLASSE SÉLECTIONNÉE -->
    <?php if (!$classeId && !$head): ?>
    <div class="alert alert-warning text-center my-5 py-4">
        <i class="bi bi-exclamation-triangle fs-1 d-block mb-2"></i>
        <h5>Aucune classe sélectionnée</h5>
        <p class="mb-0">Veuillez choisir une classe dans le sélecteur de la barre de navigation pour continuer.</p>
    </div>

    <!-- AUCUNE FICHE SÉLECTIONNÉE / ÉCRAN DE DÉPART -->
    <?php elseif (!$head): ?>
    <div class="text-center my-5 py-5">
        <i class="bi bi-journal-text fs-1 text-muted d-block mb-3"></i>
        <h4>Fiche de Prévision des Matières</h4>
        <p class="text-muted mb-4">Veuillez ouvrir ou créer une fiche de prévision pour la classe actuellement sélectionnée.</p>
        <button class="btn btn-primary btn-lg shadow-sm" data-bs-toggle="modal" data-bs-target="#modalCreateHead">
            <i class="bi bi-file-earmark-plus me-2"></i>+ Créer une fiche de prévision
        </button>
    </div>

    <!-- PLAN DE TRAVAIL ET FICHE DÉTAILLÉE -->
    <?php else: ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0 text-uppercase fw-bold">PLAN DE TRAVAIL : <?= e($head['cours_intitule']) ?></h1>
            <span class="text-muted small">
                Classe : <strong><?= e($head['classe_nom']) ?> (<?= e($head['cycle_nom'] ?? '—') ?>)</strong> |
                Année Scolaire : <strong><?= e($head['anneeScolaire']) ?></strong>
            </span>
        </div>
        <div class="no-print">
            <button class="btn btn-outline-primary btn-sm me-2" data-bs-toggle="modal" data-bs-target="#modalCreateHead">
                <i class="bi bi-journal-plus me-1"></i> Nouveau / Changer de cours
            </button>
            <button class="btn btn-success btn-sm me-2" data-bs-toggle="modal" data-bs-target="#modalAddLecon">
                + Ajouter une ligne
            </button>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered align-middle text-center mb-0 style-grid">
                    <thead class="table-light fw-bold small text-uppercase">
                        <tr>
                            <th style="width: 8%;">PERIODE</th>
                            <th style="width: 9%;">MOIS</th>
                            <th style="width: 10%;">SEMAINE</th>
                            <th style="width: 12%;">BRANCHE/CATEGORIE</th>
                            <th style="width: 12%;">SOUS-BRANCHE/CATEGORIE</th>
                            <th>SAVOIRS ESSENTIELS</th>
                            <th style="width: 7%;">N° FICHE</th>
                            <th style="width: 10%;">OBSERVATION</th>
                            <th style="width: 12%;" class="no-print">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        <?php if (empty($details)): ?>
                        <tr>
                            <td colspan="9" class="text-muted py-4">Aucune leçon enregistrée. Cliquez sur "+ Ajouter une ligne".</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($details as $row): ?>
                        <tr>
                            <td class="fw-bold bg-light"><?= e($row['periode']) ?></td>
                            <td><?= e($row['mois']) ?></td>
                            <td><?= e($row['semaine_libelle']) ?></td>
                            <td class="fw-bold text-dark"><?= e($coursParsed['categorie']) ?></td>
                            <td class="fw-semibold text-primary"><?= e($coursParsed['sous_categorie']) ?></td>
                            <td class="text-start"><?= nl2br(e($row['savoirs_essentiels'])) ?></td>
                            <td><code class="fw-bold"><?= e($row['code'] ?: '—') ?></code></td>
                            <td class="text-muted"><?= e($row['observation'] ?: '—') ?></td>
                            <td class="no-print">
                                <!-- REDIRECTION DYNAMIQUE SELON SI C'EST L'ANGLAIS OU AUTRE -->
                                <?php $targetLessonFile = $isAnglais ? 'fiche_de_cours_anglais.php' : 'fiche_de_cours.php'; ?>
                                <a href="<?= $targetLessonFile ?>?prevision_detail_id=<?= (int)$row['id'] ?>"
                                    class="btn btn-primary btn-sm me-1 mb-1" title="Préparer la fiche de cours">
                                    <i class="bi bi-file-earmark-text"></i> Fiche détaillée
                                </a>

                                <!-- Bouton de suppression -->
                                <a href="?id=<?= $previsionId ?>&delete_line=<?= (int)$row['id'] ?>"
                                    class="btn btn-danger btn-sm mb-1"
                                    onclick="return confirm('Supprimer cette ligne ?')" title="Supprimer">&times;</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- MODAL D'AJOUT DE LEÇON -->
    <div class="modal fade" id="modalAddLecon" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action_add_lecon" value="1">
                    <div class="modal-header">
                        <h5 class="modal-title h6">Ajouter une leçon au plan de travail</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Période <span class="text-danger">*</span></label>
                                <select name="periode" class="form-select form-select-sm" required>
                                    <option value="1ÈRE PERIODE" selected>1ÈRE PERIODE</option>
                                    <option value="2ÈME PERIODE">2ÈME PERIODE</option>
                                    <option value="3ÈME PERIODE">3ÈME PERIODE</option>
                                    <option value="4ÈME PERIODE">4ÈME PERIODE</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Mois <span class="text-danger">*</span></label>
                                <input type="text" name="mois" class="form-control form-control-sm" placeholder="ex: SEPTEMBRE 2026" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Semaine <span class="text-danger">*</span></label>
                                <input type="text" name="semaine_libelle" class="form-control form-control-sm" placeholder="ex: Du 07 au 11" required>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label small fw-semibold">Savoirs Essentiels (Contenu) <span class="text-danger">*</span></label>
                                <textarea name="savoirs_essentiels" class="form-control form-control-sm" rows="3" placeholder="ex: Les fonctions de l'adjectif qualificatif." required></textarea>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Code (Optionnel / Auto)</label>
                                <input type="text" name="code" class="form-control form-control-sm" placeholder="ex: Auto (C1.1, C1.2...)" readonly>
                                <span class="text-muted" style="font-size: 0.75rem;">Laissez vide pour générer automatiquement.</span>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Observation</label>
                                <input type="text" name="observation" class="form-control form-control-sm" placeholder="ex: Non vu">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary btn-sm">Enregistrer la leçon</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php endif; ?>

    <!-- MODAL DE CRÉATION / SÉLECTION DE FICHE DE PRÉVISION -->
    <div class="modal modal-lg fade" id="modalCreateHead" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action_create_head" value="1">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title h6"><i class="bi bi-file-earmark-plus me-2"></i>Fiche de Prévision des Matières</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fermer"></button>
                    </div>
                    <div class="modal-body">
                        <?php if (empty($listeCours)): ?>
                        <div class="text-center text-muted py-3">
                            <i class="bi bi-journal-x fs-2 d-block mb-2"></i>
                            Aucun cours ne vous a été affecté pour cette classe.
                        </div>
                        <?php else: ?>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Sélectionner un cours <span class="text-danger">*</span></label>
                            <select name="cours_id" class="form-select" required>
                                <option value="">-- Choisir un cours --</option>
                                <?php foreach ($listeCours as $cr): ?>
                                <option value="<?= (int)$cr['id'] ?>"
                                    <?= ($head && $head['cours_id'] == $cr['id']) ? 'selected' : '' ?>>
                                    <?= e($cr['intitule']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Année Scolaire <span class="text-danger">*</span></label>
                            <input type="text" name="anneeScolaire" class="form-control" value="<?= e($anneeEnCours) ?>" readonly required>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Annuler</button>
                        <?php if (!empty($listeCours)): ?>
                        <button type="submit" class="btn btn-primary btn-sm">Ouvrir / Créer la fiche &rarr;</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>