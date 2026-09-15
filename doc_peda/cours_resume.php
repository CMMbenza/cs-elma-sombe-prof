<?php
// /prof/doc_peda/cours_resume.php
declare(strict_types=1);

require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/helpers.php';
require_prof();

include __DIR__.'/../get_annee_en_cours.php';

$prof    = current_prof();
$agentId = (int)($prof['id'] ?? 0);

$journalId = (int)($_GET['journal'] ?? 0);

if (!$journalId) {
    header("Location: mes_resumes.php");
    exit;
}

$uploadDir = __DIR__.'/../../uploads/attachement_resume_cours/';

// -------------------------------------------------------------------------
// 1) CHARGEMENT DU JOURNAL DE CLASSE & INFOS ASSOCIÉES
// -------------------------------------------------------------------------
$stmt = $con->prepare("
    SELECT 
        jc.id AS journal_id,
        jc.jour_date,
        jc.matieres AS matieres_saisies,
        jc.note AS note_saisie,
        jc.piece_jointe AS pj_journal,
        CONCAT(cl.description ,' ', cy.description) AS classe_nom,
        co.intitule AS cours_nom
    FROM journal_classe jc
    INNER JOIN classe cl ON cl.id = jc.classe_id
    INNER JOIN cours co ON co.id = jc.cours_id
    INNER JOIN cycle cy ON cy.id = cl.cycle
    WHERE jc.id = ? AND jc.prof_id = ?
");
$stmt->bind_param('ii', $journalId, $agentId);
$stmt->execute();
$journal = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$journal) {
    die('<div class="alert alert-danger m-4">Entrée du journal introuvable ou accès non autorisé.</div>');
}

// Formatage de la date en français sans dépendance à l'extension intl
$jours = ['Sunday' => 'Dimanche', 'Monday' => 'Lundi', 'Tuesday' => 'Mardi', 'Wednesday' => 'Mercredi', 'Thursday' => 'Jeudi', 'Friday' => 'Vendredi', 'Saturday' => 'Samedi'];
$mois  = [1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

$timestamp    = strtotime($journal['jour_date']);
$nomJour      = $jours[date('l', $timestamp)] ?? date('l', $timestamp);
$numJour      = date('j', $timestamp);
$nomMois      = $mois[(int)date('n', $timestamp)] ?? date('F', $timestamp);
$annee        = date('Y', $timestamp);

$dateFormatee = "{$nomJour}, {$numJour} {$nomMois} {$annee}";

// -------------------------------------------------------------------------
// 2) TRAITEMENT DU FORMULAIRE : SAUVEGARDE / MODIFICATION
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_save_resume'])) {
    $ficheNo     = trim((string)($_POST['fiche_no'] ?? ''));
    $domaine     = trim((string)($_POST['domaine'] ?? ''));
    $discipline  = trim((string)($_POST['discipline'] ?? ''));
    $titreLecon  = trim((string)($_POST['titre_lecon'] ?? ''));
    $typeLecon   = trim((string)($_POST['type_lecon'] ?? ''));
    $competence  = trim((string)($_POST['competence_attendue'] ?? ''));
    $resumeTexte = trim((string)($_POST['resume_texte'] ?? ''));
    $devoir      = trim((string)($_POST['devoir'] ?? ''));

    // Vérifier si un résumé existe déjà
    $stmtCheck = $con->prepare("SELECT id, piece_jointe FROM resume_cours WHERE journal_id = ?");
    $stmtCheck->bind_param('i', $journalId);
    $stmtCheck->execute();
    $existing = $stmtCheck->get_result()->fetch_assoc();
    $stmtCheck->close();

    $filename = $existing['piece_jointe'] ?? null;

    // Gestion de l'upload de pièce jointe
    if (isset($_FILES['piece_jointe']['error']) && $_FILES['piece_jointe']['error'] === UPLOAD_ERR_OK) {
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        if (!empty($filename) && file_exists($uploadDir . $filename)) {
            @unlink($uploadDir . $filename);
        }
        $tmpName   = $_FILES['piece_jointe']['tmp_name'];
        $extension = pathinfo($_FILES['piece_jointe']['name'], PATHINFO_EXTENSION);
        $filename  = 'resume_' . uniqid() . '.' . strtolower($extension);
        move_uploaded_file($tmpName, $uploadDir . $filename);
    }

    if ($existing) {
        $stmtUpd = $con->prepare("
            UPDATE resume_cours 
            SET fiche_no = ?, domaine = ?, discipline = ?, titre_lecon = ?, type_lecon = ?, competence_attendue = ?, resume_texte = ?, devoir = ?, piece_jointe = ?
            WHERE journal_id = ?
        ");
        $stmtUpd->bind_param('sssssssssi', $ficheNo, $domaine, $discipline, $titreLecon, $typeLecon, $competence, $resumeTexte, $devoir, $filename, $journalId);
        $stmtUpd->execute();
        $stmtUpd->close();
        $_SESSION['msg_success'] = "Résumé de la fiche mis à jour avec succès.";
    } else {
        $stmtIns = $con->prepare("
            INSERT INTO resume_cours (journal_id, fiche_no, domaine, discipline, titre_lecon, type_lecon, competence_attendue, resume_texte, devoir, piece_jointe)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmtIns->bind_param('isssssssss', $journalId, $ficheNo, $domaine, $discipline, $titreLecon, $typeLecon, $competence, $resumeTexte, $devoir, $filename);
        $stmtIns->execute();
        $stmtIns->close();
        $_SESSION['msg_success'] = "Fiche résumé enregistrée avec succès.";
    }

    header("Location: mes_resumes.php");
    exit;
}

// -------------------------------------------------------------------------
// 3) RECHARGER LES DONNÉES DU RÉSUMÉ
// -------------------------------------------------------------------------
$stmtRes = $con->prepare("SELECT * FROM resume_cours WHERE journal_id = ?");
$stmtRes->bind_param('i', $journalId);
$stmtRes->execute();
$resumeData = $stmtRes->get_result()->fetch_assoc();
$stmtRes->close();

// Valeurs par défaut
$ficheNo    = $resumeData['fiche_no'] ?? 'Fiche n° 001';
$domaine    = $resumeData['domaine'] ?? $journal['cours_nom'];
$discipline = $resumeData['discipline'] ?? '';
$titreLecon = $resumeData['titre_lecon'] ?? $journal['matieres_saisies'];
$typeLecon  = $resumeData['type_lecon'] ?? 'découverte';
$competence = $resumeData['competence_attendue'] ?? '';
$resumeTxt  = $resumeData['resume_texte'] ?? '';
$devoirTxt  = $resumeData['devoir'] ?? $journal['note_saisie'];
$pjFile     = $resumeData['piece_jointe'] ?? $journal['pj_journal'];

$msgSuccess = $_SESSION['msg_success'] ?? '';
unset($_SESSION['msg_success']);

include __DIR__.'/../layout/header.php';
include __DIR__.'/../layout/navbar.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3 d-print-none">
        <a href="mes_resumes.php" class="btn btn-outline-secondary btn-sm">
            ⬅️ Retour à Mes Résumés
        </a>
        <button onclick="window.print()" class="btn btn-primary btn-sm fw-bold">
            🖨️ Imprimer la Fiche
        </button>
    </div>

    <?php if (!empty($msgSuccess)): ?>
        <div class="alert alert-success alert-dismissible fade show d-print-none" role="alert">
            <?= e($msgSuccess) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- CARTE DE SAISIE ET APERÇU FICHE -->
    <div class="card shadow border-1 p-4 bg-white" id="printableArea">
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="action_save_resume" value="1">

            <!-- Entête -->
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Numéro de la Fiche :</label>
                    <input type="text" name="fiche_no" class="form-control form-control-sm" value="<?= e($ficheNo) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Date :</label>
                    <input type="text" class="form-control form-control-sm bg-light fw-bold" value="<?= e($dateFormatee) ?>" readonly>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label fw-bold">Classe :</label>
                    <input type="text" class="form-control form-control-sm bg-light" value="<?= e($journal['classe_nom']) ?>" readonly>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Domaine :</label>
                    <input type="text" name="domaine" class="form-control form-control-sm" value="<?= e($domaine) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Discipline (Sous-branche) :</label>
                    <input type="text" name="discipline" class="form-control form-control-sm" placeholder="Ex: Grammaire, Algèbre..." value="<?= e($discipline) ?>" required>
                </div>
            </div>

            <hr class="my-3">

            <!-- Leçon -->
            <div class="mb-3">
                <label class="form-label fw-bold">Titre de la leçon :</label>
                <input type="text" name="titre_lecon" class="form-control" value="<?= e($titreLecon) ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">Type de leçon :</label>
                <select name="type_lecon" class="form-select form-select-sm" required>
                    <?php 
                    $types = ['découverte', 'apprentissage', 'consolidation', 'remédiation', 'révision', 'évaluation'];
                    foreach ($types as $t): 
                    ?>
                        <option value="<?= $t ?>" <?= ($typeLecon === $t) ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">Compétence attendue :</label>
                <textarea name="competence_attendue" class="form-control" rows="2" placeholder="Ex: définir, identifier et employer..." required><?= e($competence) ?></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">Résumé (ou texte du cours) :</label>
                <textarea name="resume_texte" class="form-control" rows="5" placeholder="Saisir la synthèse / résumé du cours..."><?= e($resumeTxt) ?></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">Devoir :</label>
                <input type="text" name="devoir" class="form-control form-control-sm" placeholder="Ex: voir le quiz sur la plateforme de l'école." value="<?= e($devoirTxt) ?>">
            </div>

            <!-- Pièce jointe -->
            <div class="mb-4">
                <label class="form-label fw-bold">Pièce jointe / Support de cours (Optionnel) :</label>
                <?php if (!empty($pjFile)): ?>
                    <div class="mb-2">
                        <a href="/uploads/attachement_resume_cours/<?= e($pjFile) ?>" target="_blank" class="btn btn-sm btn-outline-primary fw-bold">
                            📎 Consulter le fichier joint actuel
                        </a>
                    </div>
                <?php endif; ?>
                <input type="file" name="piece_jointe" class="form-control form-control-sm d-print-none">
            </div>

            <div class="text-end d-print-none">
                <button type="submit" class="btn btn-success fw-bold px-4">
                    💾 Enregistrer le résumé
                </button>
            </div>
        </form>
    </div>
</div>

<style>
@media print {
    .d-print-none, .navbar, header, footer {
        display: none !important;
    }
    .card {
        border: 1px solid #000 !important;
        box-shadow: none !important;
    }
    input, textarea, select {
        border: none !important;
        background: transparent !important;
        padding: 0 !important;
    }
}
</style>

<?php include __DIR__.'/../layout/footer.php'; ?>