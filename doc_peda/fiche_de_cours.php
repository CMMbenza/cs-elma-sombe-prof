<?php
// /prof/doc_peda/fiche_de_cours.php
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
    header("Location: fiche_des_prevision.php");
    exit;
}

// 1. Récupération des informations de la prévision
$stmt = $con->prepare("
    SELECT d.*, p.anneeScolaire, p.id AS prevision_id, c.intitule AS cours_intitule, cl.description AS classe_nom, cy.description AS cycle_nom
    FROM prevision_detail d
    JOIN prevision_matiere p ON p.id = d.prevision_id
    JOIN cours c ON c.id = p.cours_id
    JOIN classe cl ON cl.id = c.classe_id
    LEFT JOIN cycle cy ON cy.id = cl.cycle
    WHERE d.id = ?
");
$stmt->bind_param("i", $detailId);
$stmt->execute();
$detail = $stmt->get_result()->fetch_assoc();

if (!$detail) {
    die("Prévision introuvable.");
}

// Charger la fiche existante si elle existe
$stmtFiche = $con->prepare("SELECT * FROM fiche_cours WHERE prevision_detail_id = ?");
$stmtFiche->bind_param("i", $detailId);
$stmtFiche->execute();
$fiche = $stmtFiche->get_result()->fetch_assoc() ?: [];

// 2. Traitement de l'enregistrement (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date_cours            = trim($_POST['date_cours'] ?? '');
    
    // En-tête
    $domaine               = trim($_POST['domaine'] ?? '');
    $branche               = trim($_POST['branche'] ?? '');
    $sous_branche          = trim($_POST['sous_branche'] ?? '');
    $sujet                 = trim($_POST['sujet'] ?? '');
    $matiere               = trim($_POST['matiere'] ?? '');
    $objectif_specifique   = trim($_POST['objectif_specifique'] ?? '');
    $objectif_operationnel = trim($_POST['objectif_operationnel'] ?? '');
    $strategies            = trim($_POST['strategies'] ?? '');
    $materiel_didactique   = trim($_POST['materiel_didactique'] ?? '');

    // 1. Prérequis
    $prerequis_prof        = trim($_POST['prerequis_prof'] ?? '');
    $prerequis_eleve       = trim($_POST['prerequis_eleve'] ?? '');
    $prerequis_strat       = trim($_POST['prerequis_strat'] ?? '');
    $prerequis_duree       = trim($_POST['prerequis_duree'] ?? '');

    // 2. Motivation
    $motivation_prof       = trim($_POST['motivation_prof'] ?? '');
    $motivation_eleve      = trim($_POST['motivation_eleve'] ?? '');
    $motivation_strat      = trim($_POST['motivation_strat'] ?? '');
    $motivation_duree      = trim($_POST['motivation_duree'] ?? '');

    // 3. Annonce du sujet
    $annonce_prof          = trim($_POST['annonce_prof'] ?? '');
    $annonce_eleve         = trim($_POST['annonce_eleve'] ?? '');
    $annonce_strat         = trim($_POST['annonce_strat'] ?? '');
    $annonce_duree         = trim($_POST['annonce_duree'] ?? '');

    // 4. Analyse
    $analyse_prof          = trim($_POST['analyse_prof'] ?? '');
    $analyse_eleve         = trim($_POST['analyse_eleve'] ?? '');
    $analyse_strat         = trim($_POST['analyse_strat'] ?? '');
    $analyse_duree         = trim($_POST['analyse_duree'] ?? '');

    // 5. Synthèse
    $synthese_prof         = trim($_POST['synthese_prof'] ?? '');
    $synthese_eleve        = trim($_POST['synthese_eleve'] ?? '');
    $synthese_strat        = trim($_POST['synthese_strat'] ?? '');
    $synthese_duree        = trim($_POST['synthese_duree'] ?? '');

    // 6. Application
    $application_prof      = trim($_POST['application_prof'] ?? '');
    $application_eleve     = trim($_POST['application_eleve'] ?? '');
    $application_strat     = trim($_POST['application_strat'] ?? '');
    $application_duree     = trim($_POST['application_duree'] ?? '');

    // 7. Évaluation
    $evaluation_prof       = trim($_POST['evaluation_prof'] ?? '');
    $evaluation_eleve      = trim($_POST['evaluation_eleve'] ?? '');
    $evaluation_strat      = trim($_POST['evaluation_strat'] ?? '');
    $evaluation_duree      = trim($_POST['evaluation_duree'] ?? '');

    // Fichier existant
    $fichierJoint = $fiche['fichier_joint'] ?? null;

    // Traitement de l'upload de fichier
    if (isset($_FILES['fichier_joint']) && $_FILES['fichier_joint']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../../uploads/fichier_cours/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileName      = $_FILES['fichier_joint']['name'];
        $fileTmpPath   = $_FILES['fichier_joint']['tmp_name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        $newFileName   = 'fiche_' . $detailId . '_' . time() . '.' . $fileExtension;
        $destPath      = $uploadDir . $newFileName;

        if (move_uploaded_file($fileTmpPath, $destPath)) {
            if (!empty($fichierJoint) && file_exists($uploadDir . $fichierJoint)) {
                @unlink($uploadDir . $fichierJoint);
            }
            $fichierJoint = $newFileName;
        } else {
            $error = "Erreur lors du téléchargement du fichier.";
        }
    }

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

            $dateValide = !empty($date_cours) ? $date_cours : null;

            // Exactly 41 types: 2x 'i' (int) + 39x 's' (string)
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
            $success = "La fiche de cours a été enregistrée avec succès !";

            // Recharger les données enregistrées
            $stmtFiche->execute();
            $fiche = $stmtFiche->get_result()->fetch_assoc() ?: [];
        } catch (Throwable $e) {
            $error = "Erreur SQL : " . $e->getMessage();
        }
    }
    
}

require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/navbar.php';
?>

<div class="container-fluid my-4">

    <div class="d-flex justify-content-between align-items-center mb-3 no-print">
        <a href="fiche_des_prevision.php?id=<?= (int)$detail['prevision_id'] ?>"
            class="btn btn-secondary btn-sm">
            &larr; Retour aux prévisions
        </a>
        <!-- <button type="button" class="btn btn-dark btn-sm" onclick="window.print()">
            <i class="bi bi-printer me-1"></i> Imprimer la fiche
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

    <form method="POST" action="" enctype="multipart/form-data">

        <!-- EN-TÊTE PÉDAGOGIQUE -->
        <div class="card border-none shadow-sm mb-4">
            <div class="card-header bg-primary text-white text-center py-2">
                <h5 class="mb-0 text-uppercase fw-bold">FICHE DE PRÉPARATION DÉTAILLÉE</h5>
            </div>
            <div class="card-body">
                <div class="row g-3 small">
                    <div class="col-md-4">
                        <label class="form-label fw-bold mb-0">Date :</label>
                        <input type="date" name="date_cours" class="form-control form-control-sm"
                            value="<?= e($fiche['date_cours'] ?? date('Y-m-d')) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold mb-0">Heure / Période :</label>
                        <input type="text" class="form-control form-control-sm bg-light"
                            value="<?= e($detail['periode']) ?>" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold mb-0">Classe / Cycle :</label>
                        <input type="text" class="form-control form-control-sm bg-light"
                            value="<?= e($detail['classe_nom']) ?> (<?= e($detail['cycle_nom'] ?? '—') ?>)" readonly>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold mb-0">Domaine :</label>
                        <input type="text" name="domaine" class="form-control form-control-sm"
                            value="<?= e($fiche['domaine'] ?? '') ?>" placeholder="Ex: Sciences">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold mb-0">Branche :</label>
                        <input type="text" name="branche" class="form-control form-control-sm"
                            value="<?= e($fiche['branche'] ?? $detail['cours_intitule']) ?>"
                            placeholder="Ex: Informatique">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold mb-0">Sous-branche :</label>
                        <input type="text" name="sous_branche" class="form-control form-control-sm"
                            value="<?= e($fiche['sous_branche'] ?? '') ?>" placeholder="Ex: Algorithmique">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold mb-0">Sujet (Titre de la leçon) :</label>
                        <input type="text" name="sujet" class="form-control form-control-sm fw-bold text-primary"
                            value="<?= e($fiche['sujet'] ?? $detail['savoirs_essentiels']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold mb-0">Matière (Résumé) :</label>
                        <input type="text" name="matiere" class="form-control form-control-sm"
                            value="<?= e($fiche['matiere'] ?? '') ?>" placeholder="Aperçu rapide du contenu">
                    </div>

                    <div class="col-md-12">
                        <label class="form-label fw-bold mb-0">Objectif spécifique (Competènces) :</label>
                        <input type="text" name="objectif_specifique" class="form-control form-control-sm"
                            value="<?= e($fiche['objectif_specifique'] ?? '') ?>"
                            placeholder="But poursuivi par l'enseignant">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label fw-bold mb-0">Objectif opérationnel :</label>
                        <textarea name="objectif_operationnel" class="form-control form-control-sm" rows="2"
                            placeholder="Résultat attendu chez l'élève à la fin de la séance"><?= e($fiche['objectif_operationnel'] ?? '') ?></textarea>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold mb-0">Stratégies (Méthode ou Exemple des situations):</label>
                        <input type="text" name="strategies" class="form-control form-control-sm"
                            value="<?= e($fiche['strategies'] ?? '') ?>" placeholder="Ex: Méthode participative">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold mb-0">Matériel didactique :</label>
                        <input type="text" name="materiel_didactique" class="form-control form-control-sm"
                            value="<?= e($fiche['materiel_didactique'] ?? '') ?>"
                            placeholder="Ex: Tableau, Projecteur, Fiches d'exercices">
                    </div>
                </div>
            </div>
        </div>

        <!-- TABLEAU DÉROULEMENT PÉDAGOGIQUE -->
        <div class="card border-dark shadow-sm mb-4">
            <div class="card-header bg-light border-dark fw-bold text-uppercase small text-center">
                DÉROULEMENT PÉDAGOGIQUE DE LA SÉANCE
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered mb-0 align-middle">
                        <thead class="table-dark text-center small text-uppercase">
                            <tr>
                                <th style="width: 15%;">Étapes</th>
                                <th style="width: 35%;">Ce que fait l'enseignant (Activité du guide)</th>
                                <th style="width: 25%;">Ce que fait l'élève (Activité de l'apprenant)</th>
                                <th style="width: 15%;">Stratégie (Méthode)</th>
                                <th style="width: 10%;">Durée</th>
                            </tr>
                        </thead>
                        <tbody class="small">
                            <!-- Prérequis -->
                            <tr>
                                <td class="fw-bold bg-light">Prérequis</td>
                                <td><textarea name="prerequis_prof" class="form-control form-control-sm border-0"
                                        rows="3"
                                        placeholder="Questions/Activités de révision..."><?= e($fiche['prerequis_prof'] ?? '') ?></textarea>
                                </td>
                                <td><textarea name="prerequis_eleve" class="form-control form-control-sm border-0"
                                        rows="3"
                                        placeholder="Réponses/Actions des élèves..."><?= e($fiche['prerequis_eleve'] ?? '') ?></textarea>
                                </td>
                                <td><input type="text" name="prerequis_strat"
                                        class="form-control form-control-sm border-0 text-center"
                                        value="<?= e($fiche['prerequis_strat'] ?? 'Interrogative') ?>"></td>
                                <td><input type="text" name="prerequis_duree"
                                        class="form-control form-control-sm border-0 text-center"
                                        value="<?= e($fiche['prerequis_duree'] ?? '5 min') ?>"></td>
                            </tr>

                            <!-- Motivation -->
                            <tr>
                                <td class="fw-bold bg-light">Motivation</td>
                                <td><textarea name="motivation_prof" class="form-control form-control-sm border-0"
                                        rows="3"
                                        placeholder="Mise en situation, amorce..."><?= e($fiche['motivation_prof'] ?? '') ?></textarea>
                                </td>
                                <td><textarea name="motivation_eleve" class="form-control form-control-sm border-0"
                                        rows="3"
                                        placeholder="Observation, émission d'idées..."><?= e($fiche['motivation_eleve'] ?? '') ?></textarea>
                                </td>
                                <td><input type="text" name="motivation_strat"
                                        class="form-control form-control-sm border-0 text-center"
                                        value="<?= e($fiche['motivation_strat'] ?? 'Inductive') ?>"></td>
                                <td><input type="text" name="motivation_duree"
                                        class="form-control form-control-sm border-0 text-center"
                                        value="<?= e($fiche['motivation_duree'] ?? '5 min') ?>"></td>
                            </tr>

                            <!-- Annonce du sujet -->
                            <tr>
                                <td class="fw-bold bg-light">Annonce du sujet</td>
                                <td><textarea name="annonce_prof" class="form-control form-control-sm border-0" rows="2"
                                        placeholder="Présentation du titre/objectif..."><?= e($fiche['annonce_prof'] ?? '') ?></textarea>
                                </td>
                                <td><textarea name="annonce_eleve" class="form-control form-control-sm border-0"
                                        rows="2"
                                        placeholder="Prise de note du sujet..."><?= e($fiche['annonce_eleve'] ?? '') ?></textarea>
                                </td>
                                <td><input type="text" name="annonce_strat"
                                        class="form-control form-control-sm border-0 text-center"
                                        value="<?= e($fiche['annonce_strat'] ?? 'Expositive') ?>"></td>
                                <td><input type="text" name="annonce_duree"
                                        class="form-control form-control-sm border-0 text-center"
                                        value="<?= e($fiche['annonce_duree'] ?? '3 min') ?>"></td>
                            </tr>

                            <!-- Analyse -->
                            <tr>
                                <td class="fw-bold bg-light">Analyse</td>
                                <td><textarea name="analyse_prof" class="form-control form-control-sm border-0" rows="5"
                                        placeholder="Explications, questions guidées..."><?= e($fiche['analyse_prof'] ?? '') ?></textarea>
                                </td>
                                <td><textarea name="analyse_eleve" class="form-control form-control-sm border-0"
                                        rows="5"
                                        placeholder="Participation, résolution, prise de note..."><?= e($fiche['analyse_eleve'] ?? '') ?></textarea>
                                </td>
                                <td><input type="text" name="analyse_strat"
                                        class="form-control form-control-sm border-0 text-center"
                                        value="<?= e($fiche['analyse_strat'] ?? 'Démonstrative') ?>"></td>
                                <td><input type="text" name="analyse_duree"
                                        class="form-control form-control-sm border-0 text-center"
                                        value="<?= e($fiche['analyse_duree'] ?? '12 min') ?>"></td>
                            </tr>

                            <!-- Synthèse -->
                            <tr>
                                <td class="fw-bold bg-light">Synthèse</td>
                                <td><textarea name="synthese_prof" class="form-control form-control-sm border-0"
                                        rows="3"
                                        placeholder="Résumé des notions à retenir..."><?= e($fiche['synthese_prof'] ?? '') ?></textarea>
                                </td>
                                <td><textarea name="synthese_eleve" class="form-control form-control-sm border-0"
                                        rows="3"
                                        placeholder="Copie de la synthèse/résumé..."><?= e($fiche['synthese_eleve'] ?? '') ?></textarea>
                                </td>
                                <td><input type="text" name="synthese_strat"
                                        class="form-control form-control-sm border-0 text-center"
                                        value="<?= e($fiche['synthese_strat'] ?? 'Active') ?>"></td>
                                <td><input type="text" name="synthese_duree"
                                        class="form-control form-control-sm border-0 text-center"
                                        value="<?= e($fiche['synthese_duree'] ?? '5 min') ?>"></td>
                            </tr>

                            <!-- Application -->
                            <tr>
                                <td class="fw-bold bg-light">Application</td>
                                <td><textarea name="application_prof" class="form-control form-control-sm border-0"
                                        rows="3"
                                        placeholder="Exercices d'entraînement immédiat..."><?= e($fiche['application_prof'] ?? '') ?></textarea>
                                </td>
                                <td><textarea name="application_eleve" class="form-control form-control-sm border-0"
                                        rows="3"
                                        placeholder="Résolution individuelle ou en groupe..."><?= e($fiche['application_eleve'] ?? '') ?></textarea>
                                </td>
                                <td><input type="text" name="application_strat"
                                        class="form-control form-control-sm border-0 text-center"
                                        value="<?= e($fiche['application_strat'] ?? 'Pratique') ?>"></td>
                                <td><input type="text" name="application_duree"
                                        class="form-control form-control-sm border-0 text-center"
                                        value="<?= e($fiche['application_duree'] ?? '10 min') ?>"></td>
                            </tr>

                            <!-- Évaluation -->
                            <tr>
                                <td class="fw-bold bg-light">Évaluation</td>
                                <td><textarea name="evaluation_prof" class="form-control form-control-sm border-0"
                                        rows="3"
                                        placeholder="Devoir à domicile ou test court..."><?= e($fiche['evaluation_prof'] ?? '') ?></textarea>
                                </td>
                                <td><textarea name="evaluation_eleve" class="form-control form-control-sm border-0"
                                        rows="3"
                                        placeholder="Travail personnel à faire..."><?= e($fiche['evaluation_eleve'] ?? '') ?></textarea>
                                </td>
                                <td><input type="text" name="evaluation_strat"
                                        class="form-control form-control-sm border-0 text-center"
                                        value="<?= e($fiche['evaluation_strat'] ?? 'Individuelle') ?>"></td>
                                <td><input type="text" name="evaluation_duree"
                                        class="form-control form-control-sm border-0 text-center"
                                        value="<?= e($fiche['evaluation_duree'] ?? '5 min') ?>"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- UPLOAD SUPPORT -->
        <div class="card border-dark shadow-sm mb-4 no-print">
            <div class="card-header bg-light border-dark fw-bold text-uppercase small">
                DOCUMENT OU FICHIER JOINT (OPTIONNEL)
            </div>
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-7">
                        <label class="form-label small fw-semibold">Joindre un support de cours (PDF, Word, PPT,
                            Image...) :</label>
                        <input type="file" name="fichier_joint" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-5 mt-3 mt-md-0">
                        <?php if (!empty($fiche['fichier_joint'])): ?>
                        <div class="p-2 border rounded bg-light text-center">
                            <span class="d-block small text-muted mb-1">Fichier joint actuel :</span>
                            <a href="../../uploads/fichier_cours/<?= e($fiche['fichier_joint']) ?>" target="_blank"
                                class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-file-earmark-arrow-down me-1"></i> <?= e($fiche['fichier_joint']) ?>
                            </a>
                        </div>
                        <?php else: ?>
                        <div class="p-2 border rounded bg-light text-center text-muted small">
                            Aucun fichier attaché.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-end no-print mb-5">
            <button type="submit" class="btn btn-success me-2">
                <i class="bi bi-save me-1"></i> Enregistrer la fiche
            </button>
            <!-- <button type="button" class="btn btn-dark" onclick="window.print()">
                <i class="bi bi-printer me-1"></i> Imprimer
            </button> -->
        </div>
    </form>
</div>

<style>
@media print {

    .no-print,
    nav,
    .navbar,
    .btn {
        display: none !important;
    }

    .card {
        border: 1px solid #000 !important;
        box-shadow: none !important;
        margin-bottom: 10px !important;
    }

    textarea {
        border: none !important;
        background: transparent !important;
        resize: none;
        width: 100%;
    }

    input {
        border: none !important;
        background: transparent !important;
    }

    .table-dark {
        background-color: #f2f2f2 !important;
        color: #000 !important;
    }
}
</style>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>