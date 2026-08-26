<?php
// /prof/doc_peda/fiche_de_cours_anglais.php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

require_prof();

$prof    = current_prof();
$agentId = (int)$prof['id'];

if (!function_exists('e')) {
    function e($val): string {
        return htmlspecialchars((string)($val ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

$detailId = (int)($_GET['prevision_detail_id'] ?? 0);
$profId   = $agentId;
// $profId   = (int)($_SESSION['user_id'] ?? 0);
$error    = '';
$success  = '';

if ($detailId <= 0) {
    header("Location: fiche_des_prevision_anglais.php");
    exit;
}

// 1. Informations de la prévision
$stmt = $con->prepare("
    SELECT d.*, p.anneeScolaire, p.id AS prevision_id, c.intitule AS cours_intitule, CONCAT(cl.description ,' ', cy.description) AS classe_nom,
    ag.nom AS prof_nom, ag.prenom AS prof_prenom
    FROM prevision_detail d
    JOIN prevision_matiere p ON p.id = d.prevision_id
    JOIN cours c ON c.id = p.cours_id
    JOIN classe cl ON cl.id = c.classe_id
    LEFT JOIN cycle cy ON cy.id = cl.cycle
    LEFT JOIN agent ag ON ag.id = p.enseignant_id
    WHERE d.id = ?
");
$stmt->bind_param("i", $detailId);
$stmt->execute();
$detail = $stmt->get_result()->fetch_assoc();

if (!$detail) {
    die("Forecast detail record not found.");
}

// Charger la fiche existante si elle existe
$stmtFiche = $con->prepare("SELECT * FROM fiche_cours WHERE prevision_detail_id = ?");
$stmtFiche->bind_param("i", $detailId);
$stmtFiche->execute();
$fiche = $stmtFiche->get_result()->fetch_assoc() ?: [];

// 2. Traitement du formulaire POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date_cours            = trim($_POST['date_cours'] ?? '');
    
    // Header Mapping
    $domaine               = trim($_POST['didactic_sequences'] ?? ''); // Map to domaine
    $branche               = trim($_POST['topic'] ?? '');              // Map to branche
    $sous_branche          = trim($_POST['skill_study'] ?? '');        // Map to sous_branche
    $sujet                 = trim($_POST['sujet'] ?? '');
    $matiere               = trim($_POST['ref_material'] ?? '');       // Map to matiere
    $objectif_specifique   = trim($_POST['specific_aims'] ?? '');     // Map to objectif_specifique
    $objectif_operationnel = trim($_POST['objectives'] ?? '');        // Map to objectif_operationnel
    $strategies            = trim($_POST['previous_knowledge'] ?? ''); // Map to strategies
    $materiel_didactique   = trim($_POST['hour_period'] ?? '');        // Map to materiel_didactique

    // 1. Warm-up
    $prerequis_prof        = trim($_POST['warmup_prof'] ?? '');
    $prerequis_eleve       = trim($_POST['warmup_eleve'] ?? '');
    $prerequis_strat       = trim($_POST['warmup_comp'] ?? '');
    $prerequis_duree       = trim($_POST['warmup_time'] ?? '');

    // 2. Recall
    $motivation_prof       = trim($_POST['recall_prof'] ?? '');
    $motivation_eleve      = trim($_POST['recall_eleve'] ?? '');
    $motivation_strat      = trim($_POST['recall_comp'] ?? '');
    $motivation_duree      = trim($_POST['recall_time'] ?? '');

    // 3. Motivation
    $annonce_prof          = trim($_POST['motivation_prof'] ?? '');
    $annonce_eleve         = trim($_POST['motivation_eleve'] ?? '');
    $annonce_strat         = trim($_POST['motivation_comp'] ?? '');
    $annonce_duree         = trim($_POST['motivation_time'] ?? '');

    // 4. Announcement
    $analyse_prof          = trim($_POST['announcement_prof'] ?? '');
    $analyse_eleve         = trim($_POST['announcement_eleve'] ?? '');
    $analyse_strat         = trim($_POST['announcement_comp'] ?? '');
    $analyse_duree         = trim($_POST['announcement_time'] ?? '');

    // 5. Presentation
    $synthese_prof         = trim($_POST['presentation_prof'] ?? '');
    $synthese_eleve        = trim($_POST['presentation_eleve'] ?? '');
    $synthese_strat        = trim($_POST['presentation_comp'] ?? '');
    $synthese_duree        = trim($_POST['presentation_time'] ?? '');

    // 6. Production
    $application_prof      = trim($_POST['production_prof'] ?? '');
    $application_eleve     = trim($_POST['production_eleve'] ?? '');
    $application_strat     = trim($_POST['production_comp'] ?? '');
    $application_duree     = trim($_POST['production_time'] ?? '');

    // 7. Practice & Homework (Mapped to evaluation)
    $evaluation_prof       = trim($_POST['practice_prof'] ?? '');
    $evaluation_eleve      = trim($_POST['practice_eleve'] ?? '');
    $evaluation_strat      = trim($_POST['practice_comp'] ?? '');
    $evaluation_duree      = trim($_POST['practice_time'] ?? '');

    $fichierJoint = $fiche['fichier_joint'] ?? null;

    if (empty($error)) {
        try {
            $stmtSave = $con->prepare("
                INSERT INTO fiche_cours 
                (prevision_detail_id, prof_id, date_cours, domaine, branche, sous_branche, sujet, matiere, objectif_specifique, objectif_operationnel, strategies, materiel_didactique,
                 prerequis_prof, prerequis_eleve, prerequis_strat, prerequis_duree,
                 motivation_prof, motivation_eleve, motivation_strat, motivation_duree,
                 annonce_prof, annonce_eleve, annonce_strat, annonce_duree,
                 analyse_prof, analyse_eleve, analyse_strat, analyse_duree,
                 synthese_prof, synthese_eleve, synthese_strat, synthese_duree,
                 application_prof, application_eleve, application_strat, application_duree,
                 evaluation_prof, evaluation_eleve, evaluation_strat, evaluation_duree,
                 fichier_joint)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    date_cours = VALUES(date_cours),
                    domaine = VALUES(domaine), branche = VALUES(branche), sous_branche = VALUES(sous_branche),
                    sujet = VALUES(sujet), matiere = VALUES(matiere), objectif_specifique = VALUES(objectif_specifique),
                    objectif_operationnel = VALUES(objectif_operationnel), strategies = VALUES(strategies), materiel_didactique = VALUES(materiel_didactique),
                    prerequis_prof = VALUES(prerequis_prof), prerequis_eleve = VALUES(prerequis_eleve), prerequis_strat = VALUES(prerequis_strat), prerequis_duree = VALUES(prerequis_duree),
                    motivation_prof = VALUES(motivation_prof), motivation_eleve = VALUES(motivation_eleve), motivation_strat = VALUES(motivation_strat), motivation_duree = VALUES(motivation_duree),
                    annonce_prof = VALUES(annonce_prof), annonce_eleve = VALUES(annonce_eleve), annonce_strat = VALUES(annonce_strat), annonce_duree = VALUES(annonce_duree),
                    analyse_prof = VALUES(analyse_prof), analyse_eleve = VALUES(analyse_eleve), analyse_strat = VALUES(analyse_strat), analyse_duree = VALUES(analyse_duree),
                    synthese_prof = VALUES(synthese_prof), synthese_eleve = VALUES(synthese_eleve), synthese_strat = VALUES(synthese_strat), synthese_duree = VALUES(synthese_duree),
                    application_prof = VALUES(application_prof), application_eleve = VALUES(application_eleve), application_strat = VALUES(application_strat), application_duree = VALUES(application_duree),
                    evaluation_prof = VALUES(evaluation_prof), evaluation_eleve = VALUES(evaluation_eleve), evaluation_strat = VALUES(evaluation_strat), evaluation_duree = VALUES(evaluation_duree),
                    fichier_joint = VALUES(fichier_joint)
            ");

            $dateValide = !empty($date_cours) ? $date_cours : date('Y-m-d');
            $typeString = "iisssssssssssssssssssssssssssssssssssssss";

            $stmtSave->bind_param($typeString, 
                $detailId, $profId, $dateValide, $domaine, $branche, $sous_branche, $sujet, $matiere, $objectif_specifique, $objectif_operationnel, $strategies, $materiel_didactique,
                $prerequis_prof, $prerequis_eleve, $prerequis_strat, $prerequis_duree,
                $motivation_prof, $motivation_eleve, $motivation_strat, $motivation_duree,
                $annonce_prof, $annonce_eleve, $annonce_strat, $annonce_duree,
                $analyse_prof, $analyse_eleve, $analyse_strat, $analyse_duree,
                $synthese_prof, $synthese_eleve, $synthese_strat, $synthese_duree,
                $application_prof, $application_eleve, $application_strat, $application_duree,
                $evaluation_prof, $evaluation_eleve, $evaluation_strat, $evaluation_duree,
                $fichierJoint
            );

            $stmtSave->execute();
            $success = "English Lesson Plan successfully saved!";

            $stmtFiche->execute();
            $fiche = $stmtFiche->get_result()->fetch_assoc() ?: [];
        } catch (Throwable $e) {
            $error = "SQL Error: " . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/navbar.php';
?>

<div class="container-fluid my-4">

    <div class="d-flex justify-content-between align-items-center mb-3 no-print">
        <a href="fiche_des_prevision_anglais.php?id=<?= (int)$detail['prevision_id'] ?>" class="btn btn-outline-secondary btn-sm">
            &larr; Back to Forecast
        </a>
        <!-- <button type="button" class="btn btn-dark btn-sm" onclick="window.print()">
            <i class="bi bi-printer me-1"></i> Print Lesson Plan
        </button> -->
    </div>

    <?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show no-print" role="alert">
        <i class="bi bi-check-circle me-1"></i> <?= e($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show no-print" role="alert">
        <i class="bi bi-exclamation-triangle me-1"></i> <?= e($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="card border-dark shadow-sm mb-4">
            <div class="card-header bg-dark text-white text-center py-2">
                <h5 class="mb-0 text-uppercase fw-bold">ENGLISH LESSON PLAN</h5>
            </div>
            <div class="card-body">
                <div class="row g-3 small">
                    <div class="col-md-6">
                        <label class="form-label fw-bold mb-0">TEACHER:</label>
                        <input type="text" class="form-control form-control-sm bg-light" value="<?= e(($detail['prof_prenom'] ?? '') . ' ' . ($detail['prof_nom'] ?? '')) ?>" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold mb-0">DIDACTIC SEQUENCES:</label>
                        <input type="text" name="didactic_sequences" class="form-control form-control-sm" value="<?= e($fiche['domaine'] ?? '') ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold mb-0">CLASS:</label>
                        <input type="text" class="form-control form-control-sm bg-light" value="<?= e($detail['classe_nom']) ?>" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold mb-0">TOPIC:</label>
                        <input type="text" name="topic" class="form-control form-control-sm fw-bold text-primary" value="<?= e($fiche['branche'] ?? $detail['savoirs_essentiels']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold mb-0">PREVIOUS KNOWLEDGE:</label>
                        <input type="text" name="previous_knowledge" class="form-control form-control-sm" value="<?= e($fiche['strategies'] ?? '') ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold mb-0">DATE:</label>
                        <input type="date" name="date_cours" class="form-control form-control-sm" value="<?= e($fiche['date_cours'] ?? date('Y-m-d')) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold mb-0">SKILL STUDY:</label>
                        <input type="text" name="skill_study" class="form-control form-control-sm" value="<?= e($fiche['sous_branche'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold mb-0">SPECIFIC AIMS:</label>
                        <input type="text" name="specific_aims" class="form-control form-control-sm" value="<?= e($fiche['objectif_specifique'] ?? '') ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold mb-0">HOUR / PERIOD:</label>
                        <input type="text" name="hour_period" class="form-control form-control-sm" value="<?= e($fiche['materiel_didactique'] ?? $detail['periode']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold mb-0">REF (MATERIAL):</label>
                        <input type="text" name="ref_material" class="form-control form-control-sm" value="<?= e($fiche['matiere'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold mb-0">OBJECTIVES:</label>
                        <input type="text" name="objectives" class="form-control form-control-sm" value="<?= e($fiche['objectif_operationnel'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- PEDAGOGICAL STEPS TABLE -->
        <div class="card border-dark shadow-sm mb-4">
            <div class="card-header bg-light border-dark fw-bold text-uppercase small text-center">
                LESSON STEPS & ACTIVITIES
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered mb-0 align-middle">
                        <thead class="table-dark text-center small text-uppercase">
                            <tr>
                                <th style="width: 10%;">TIME</th>
                                <th style="width: 15%;">STEPS</th>
                                <th style="width: 35%;">FACILITATOR'S ACTIVITIES</th>
                                <th style="width: 25%;">LEARNER'S ACTIVITIES</th>
                                <th style="width: 10%;">COMPET.</th>
                                <th style="width: 5%;">OBS</th>
                            </tr>
                        </thead>
                        <tbody class="small">
                            <!-- PRE-ACTIVITIES SECTION HEADER -->
                            <tr class="table-secondary text-center fw-bold">
                                <td colspan="6">PRE-ACTIVITIES</td>
                            </tr>
                            
                            <!-- 1. WARM-UP -->
                            <tr>
                                <td><input type="text" name="warmup_time" class="form-control form-control-sm border-0 text-center" value="<?= e($fiche['prerequis_duree'] ?? '3 min') ?>"></td>
                                <td class="fw-bold bg-light">WARM-UP</td>
                                <td><textarea name="warmup_prof" class="form-control form-control-sm border-0" rows="2"><?= e($fiche['prerequis_prof'] ?? '') ?></textarea></td>
                                <td><textarea name="warmup_eleve" class="form-control form-control-sm border-0" rows="2"><?= e($fiche['prerequis_eleve'] ?? '') ?></textarea></td>
                                <td><input type="text" name="warmup_comp" class="form-control form-control-sm border-0 text-center" value="<?= e($fiche['prerequis_strat'] ?? '') ?>"></td>
                                <td></td>
                            </tr>

                            <!-- 2. RECALL -->
                            <tr>
                                <td><input type="text" name="recall_time" class="form-control form-control-sm border-0 text-center" value="<?= e($fiche['motivation_duree'] ?? '5 min') ?>"></td>
                                <td class="fw-bold bg-light">RECALL</td>
                                <td><textarea name="recall_prof" class="form-control form-control-sm border-0" rows="2"><?= e($fiche['motivation_prof'] ?? '') ?></textarea></td>
                                <td><textarea name="recall_eleve" class="form-control form-control-sm border-0" rows="2"><?= e($fiche['motivation_eleve'] ?? '') ?></textarea></td>
                                <td><input type="text" name="recall_comp" class="form-control form-control-sm border-0 text-center" value="<?= e($fiche['motivation_strat'] ?? '') ?>"></td>
                                <td></td>
                            </tr>

                            <!-- 3. MOTIVATION -->
                            <tr>
                                <td><input type="text" name="motivation_time" class="form-control form-control-sm border-0 text-center" value="<?= e($fiche['annonce_duree'] ?? '5 min') ?>"></td>
                                <td class="fw-bold bg-light">MOTIVATION</td>
                                <td><textarea name="motivation_prof" class="form-control form-control-sm border-0" rows="2"><?= e($fiche['annonce_prof'] ?? '') ?></textarea></td>
                                <td><textarea name="motivation_eleve" class="form-control form-control-sm border-0" rows="2"><?= e($fiche['annonce_eleve'] ?? '') ?></textarea></td>
                                <td><input type="text" name="motivation_comp" class="form-control form-control-sm border-0 text-center" value="<?= e($fiche['annonce_strat'] ?? '') ?>"></td>
                                <td></td>
                            </tr>

                            <!-- 4. ANNOUNCEMENT -->
                            <tr>
                                <td><input type="text" name="announcement_time" class="form-control form-control-sm border-0 text-center" value="<?= e($fiche['analyse_duree'] ?? '2 min') ?>"></td>
                                <td class="fw-bold bg-light">ANNOUNCEMENT</td>
                                <td><textarea name="announcement_prof" class="form-control form-control-sm border-0" rows="2"><?= e($fiche['analyse_prof'] ?? '') ?></textarea></td>
                                <td><textarea name="announcement_eleve" class="form-control form-control-sm border-0" rows="2"><?= e($fiche['analyse_eleve'] ?? '') ?></textarea></td>
                                <td><input type="text" name="announcement_comp" class="form-control form-control-sm border-0 text-center" value="<?= e($fiche['analyse_strat'] ?? '') ?>"></td>
                                <td></td>
                            </tr>

                            <!-- PRESENTATION & PRODUCTION SECTION -->
                            <tr class="table-secondary text-center fw-bold">
                                <td colspan="6">MAIN ACTIVITIES</td>
                            </tr>

                            <!-- 5. PRESENTATION -->
                            <tr>
                                <td><input type="text" name="presentation_time" class="form-control form-control-sm border-0 text-center" value="<?= e($fiche['synthese_duree'] ?? '15 min') ?>"></td>
                                <td class="fw-bold bg-light">PRESENTATION</td>
                                <td><textarea name="presentation_prof" class="form-control form-control-sm border-0" rows="4"><?= e($fiche['synthese_prof'] ?? '') ?></textarea></td>
                                <td><textarea name="presentation_eleve" class="form-control form-control-sm border-0" rows="4"><?= e($fiche['synthese_eleve'] ?? '') ?></textarea></td>
                                <td><input type="text" name="presentation_comp" class="form-control form-control-sm border-0 text-center" value="<?= e($fiche['synthese_strat'] ?? '') ?>"></td>
                                <td></td>
                            </tr>

                            <!-- 6. PRODUCTION -->
                            <tr>
                                <td><input type="text" name="production_time" class="form-control form-control-sm border-0 text-center" value="<?= e($fiche['application_duree'] ?? '10 min') ?>"></td>
                                <td class="fw-bold bg-light">PRODUCTION</td>
                                <td><textarea name="production_prof" class="form-control form-control-sm border-0" rows="3"><?= e($fiche['application_prof'] ?? '') ?></textarea></td>
                                <td><textarea name="production_eleve" class="form-control form-control-sm border-0" rows="3"><?= e($fiche['application_eleve'] ?? '') ?></textarea></td>
                                <td><input type="text" name="production_comp" class="form-control form-control-sm border-0 text-center" value="<?= e($fiche['application_strat'] ?? '') ?>"></td>
                                <td></td>
                            </tr>

                            <!-- 7. PRACTICE / HOMEWORK -->
                            <tr>
                                <td><input type="text" name="practice_time" class="form-control form-control-sm border-0 text-center" value="<?= e($fiche['evaluation_duree'] ?? '5 min') ?>"></td>
                                <td class="fw-bold bg-light">PRACTICE / HOMEWORK</td>
                                <td><textarea name="practice_prof" class="form-control form-control-sm border-0" rows="3"><?= e($fiche['evaluation_prof'] ?? '') ?></textarea></td>
                                <td><textarea name="practice_eleve" class="form-control form-control-sm border-0" rows="3"><?= e($fiche['evaluation_eleve'] ?? '') ?></textarea></td>
                                <td><input type="text" name="practice_comp" class="form-control form-control-sm border-0 text-center" value="<?= e($fiche['evaluation_strat'] ?? '') ?>"></td>
                                <td></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="text-end no-print mb-5">
            <button type="submit" class="btn btn-success me-2">
                <i class="bi bi-save me-1"></i> Save English Lesson Plan
            </button>
            <!-- <button type="button" class="btn btn-dark" onclick="window.print()">
                <i class="bi bi-printer me-1"></i> Print
            </button> -->
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>