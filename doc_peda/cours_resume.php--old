<?php
// /prof/doc_peda/cours_resume.php
declare(strict_types=1);

require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/helpers.php';
require_prof();

include __DIR__.'/../get_annee_en_cours.php';

$prof     = current_prof();
$agentId  = (int)($prof['id'] ?? 0);
$classeId = (int)get_current_classe();

// Repertoire de stockage des fichiers de leçons
$uploadDir = __DIR__.'/../../uploads/cours_lecons/';

if (!$classeId) {
    include __DIR__.'/../layout/header.php';
    include __DIR__.'/../layout/navbar.php';
    echo '<div class="container mt-3"><div class="alert alert-info">
            Aucune classe sélectionnée. <a href="/prof/switch_classe.php">Choisir une classe</a>
          </div></div>';
    include __DIR__.'/../layout/footer.php';
    exit;
}

$msgSuccess = '';
$msgError   = '';

// -------------------------------------------------------------------------
// 1) TRAITEMENT : AJOUTER UN CHAPITRE
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_add_lecon'])) {
    $titreLecon  = trim((string)($_POST['titre_lecon'] ?? ''));
    $chapitreId  = (int)($_POST['chapitre_id'] ?? 0);
    $desc        = trim((string)($_POST['description'] ?? ''));
    $formatType  = $_POST['format_type'] ?? 'texte';
    $contenuText = trim((string)($_POST['contenu_resume'] ?? ''));

    $checkChap = $con->query("SELECT id FROM cours_chapitres WHERE id = $chapitreId AND prof_id = $agentId")->num_rows;
    $hasFile   = isset($_FILES['fichier']) && $_FILES['fichier']['error'] === UPLOAD_ERR_OK;

    if ($titreLecon === '' || $chapitreId <= 0) {
        $msgError = "Veuillez indiquer un titre et sélectionner un chapitre.";
    } elseif ($checkChap === 0) {
        $msgError = "Chapitre invalide.";
    } elseif (($formatType === 'texte' || $formatType === 'mixte') && empty($contenuText)) {
        $msgError = "Veuillez saisir le texte du résumé.";
    } elseif (($formatType === 'fichier' || $formatType === 'mixte') && !$hasFile) {
        $msgError = "Veuillez sélectionner un fichier à envoyer.";
    } else {
        $newFilename = null;
        $typeFormat  = 'texte';

        if ($hasFile) {
            $file      = $_FILES['fichier'];
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            $allowedPdf   = ['pdf'];
            $allowedAudio = ['mp3', 'wav', 'ogg', 'm4a'];
            $allowedVideo = ['mp4', 'webm', 'mkv', 'avi'];
            $allowedDocs  = ['doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'zip', 'rar'];
            $allowedExtensions = array_merge($allowedPdf, $allowedAudio, $allowedVideo, $allowedDocs);

            if (!in_array($extension, $allowedExtensions, true)) {
                $msgError = "Format de fichier non autorisé (.{$extension}).";
            } else {
                $typeFormat = 'document';
                if (in_array($extension, $allowedPdf, true)) $typeFormat = 'pdf';
                if (in_array($extension, $allowedAudio, true)) $typeFormat = 'audio';
                if (in_array($extension, $allowedVideo, true)) $typeFormat = 'video';

                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

                $newFilename = 'lecon_' . uniqid() . '.' . $extension;
                if (!move_uploaded_file($file['tmp_name'], $uploadDir . $newFilename)) {
                    $msgError = "Erreur lors de la sauvegarde du fichier.";
                    $newFilename = null;
                }
            }
        }

        if (empty($msgError)) {
            $stmt = $con->prepare("
                INSERT INTO cours_lecons (chapitre_id, titre, description, contenu, fichier, type_format)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param('isssss', $chapitreId, $titreLecon, $desc, $contenuText, $newFilename, $typeFormat);
            if ($stmt->execute()) {
                $msgSuccess = "La leçon a été ajoutée avec succès !";
            } else {
                $msgError = "Erreur BDD lors de l'enregistrement de la leçon.";
            }
            $stmt->close();
        }
    }
}

// -------------------------------------------------------------------------
// 4) TRAITEMENT : SUPPRIMER UNE LEÇON
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_delete_lecon'])) {
    $idLeconDelete = (int)($_POST['id_lecon'] ?? 0);

    $stmtCheck = $con->prepare("
        SELECT l.fichier 
        FROM cours_lecons l
        INNER JOIN cours_chapitres ch ON ch.id = l.chapitre_id
        WHERE l.id = ? AND ch.prof_id = ?
    ");
    $stmtCheck->bind_param('ii', $idLeconDelete, $agentId);
    $stmtCheck->execute();
    $resCheck = $stmtCheck->get_result()->fetch_assoc();
    $stmtCheck->close();

    if ($resCheck) {
        if (!empty($resCheck['fichier'])) {
            @unlink($uploadDir . $resCheck['fichier']);
        }
        $con->query("DELETE FROM cours_lecons WHERE id = $idLeconDelete");
        $msgSuccess = "La leçon a été supprimée.";
    } else {
        $msgError = "Leçon introuvable ou accès refusé.";
    }
}

// -------------------------------------------------------------------------
// CHARGEMENT DES DONNÉES
// -------------------------------------------------------------------------
$coursList = [];
$stmt = $con->prepare("
    SELECT DISTINCT co.id, co.intitule
    FROM affectation_prof_classe apc
    INNER JOIN cours co ON co.id = apc.cours_id
    WHERE apc.agent_id = ? AND apc.classe_id = ?
    ORDER BY co.intitule
");
$stmt->bind_param('ii', $agentId, $classeId);
$stmt->execute();
$resCours = $stmt->get_result();
while ($row = $resCours->fetch_assoc()) {
    $coursList[$row['id']] = $row;
    $coursList[$row['id']]['chapitres'] = [];
}
$stmt->close();

$stmt = $con->prepare("
    SELECT ch.*, co.intitule AS cours_nom 
    FROM cours_chapitres ch 
    INNER JOIN cours co ON co.id = ch.cours_id 
    WHERE ch.prof_id = ? AND ch.classe_id = ? 
    ORDER BY ch.id ASC
");
$stmt->bind_param('ii', $agentId, $classeId);
$stmt->execute();
$resChap = $stmt->get_result();

$allChapitresFlat = [];

while ($chap = $resChap->fetch_assoc()) {
    $chap['lecons'] = [];
    $allChapitresFlat[] = $chap;
    
    if (isset($coursList[$chap['cours_id']])) {
        $coursList[$chap['cours_id']]['chapitres'][$chap['id']] = $chap;
    }
}
$stmt->close();

$stmt = $con->prepare("
    SELECT l.*, ch.cours_id 
    FROM cours_lecons l
    INNER JOIN cours_chapitres ch ON ch.id = l.chapitre_id
    WHERE ch.prof_id = ? AND ch.classe_id = ?
    ORDER BY l.id ASC
");
$stmt->bind_param('ii', $agentId, $classeId);
$stmt->execute();
$resLecons = $stmt->get_result();
while ($lecon = $resLecons->fetch_assoc()) {
    $cId = $lecon['cours_id'];
    $chId = $lecon['chapitre_id'];
    if (isset($coursList[$cId]['chapitres'][$chId])) {
        $coursList[$cId]['chapitres'][$chId]['lecons'][] = $lecon;
    }
}
$stmt->close();

include __DIR__.'/../layout/header.php';
include __DIR__.'/../layout/navbar.php';
?>

<div class="container mt-3 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h4 mb-0">📚 Gestion des Cours & Résumés</h1>
        <div>
            <button class="btn btn-outline-primary me-2" data-bs-toggle="modal" data-bs-target="#modalAddChapitre">
                ➕ Nouveau Chapitre
            </button>
            <?php if (!empty($allChapitresFlat)): ?>
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalAddLecon">
                ✍️ Publier un Résumé / Fichier
            </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($msgSuccess)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= e($msgSuccess) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    <?php if (!empty($msgError)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= e($msgError) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if (empty($coursList)): ?>
    <div class="alert alert-warning">Aucun cours ne vous est attribué pour cette classe.</div>
    <?php else: ?>

    <div class="accordion" id="accordionCours">
        <?php foreach ($coursList as $cours): ?>
        <div class="accordion-item mb-3 border">
            <h2 class="accordion-header" id="headingCours<?= $cours['id'] ?>">
                <button class="accordion-button bg-light fw-bold" type="button" data-bs-toggle="collapse"
                    data-bs-target="#collapseCours<?= $cours['id'] ?>">
                    📘 <?= e($cours['intitule']) ?>
                </button>
            </h2>
            <div id="collapseCours<?= $cours['id'] ?>" class="accordion-collapse collapse show"
                data-bs-parent="#accordionCours">
                <div class="accordion-body p-4">

                    <?php if (empty($cours['chapitres'])): ?>
                    <p class="text-muted mb-0">Aucun chapitre créé pour ce cours.</p>
                    <?php else: ?>

                    <div class="row">
                        <?php foreach ($cours['chapitres'] as $chap): ?>
                        <div class="col-12 mb-4">
                            <div class="card border-primary shadow-sm">
                                <div class="card-header bg-white border-primary d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0 text-primary">📖 <?= e($chap['titre']) ?></h5>
                                    <form method="post" onsubmit="return confirm('Supprimer ce chapitre entraînera la suppression de toutes ses leçons. Continuer ?');">
                                        <input type="hidden" name="action_delete_chapitre" value="1">
                                        <input type="hidden" name="id_chapitre" value="<?= (int)$chap['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Supprimer le chapitre">🗑️ Chapitre</button>
                                    </form>
                                </div>
                                <div class="card-body bg-light p-3">
                                    <?php if (empty($chap['lecons'])): ?>
                                    <div class="text-muted small">Aucun résumé ni leçon dans ce chapitre.</div>
                                    <?php else: ?>
                                    <div class="accordion" id="accordionLecons<?= $chap['id'] ?>">
                                        <?php foreach ($chap['lecons'] as $index => $lec): ?>
                                        <?php 
                                            $fileUrl = !empty($lec['fichier']) ? '/uploads/cours_lecons/' . e($lec['fichier']) : null;
                                            $icon = '📝';
                                            if ($lec['type_format'] === 'pdf') $icon = '📄';
                                            if ($lec['type_format'] === 'video') $icon = '🎥';
                                            if ($lec['type_format'] === 'audio') $icon = '🎙️';
                                            if ($lec['type_format'] === 'document') $icon = '📁';
                                        ?>
                                        <div class="accordion-item mb-2 border-0 shadow-sm">
                                            <h2 class="accordion-header" id="headingLec<?= $lec['id'] ?>">
                                                <button class="accordion-button collapsed bg-white text-dark fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#collapseLec<?= $lec['id'] ?>">
                                                    <span class="me-2"><?= $icon ?></span>
                                                    <span><?= e($lec['titre']) ?></span>
                                                </button>
                                            </h2>
                                            <div id="collapseLec<?= $lec['id'] ?>" class="accordion-collapse collapse" data-bs-parent="#accordionLecons<?= $chap['id'] ?>">
                                                <div class="accordion-body bg-white border-top">
                                                    
                                                    <?php if (!empty($lec['description'])): ?>
                                                    <p class="text-muted small mb-3"><strong>Consignes :</strong> <?= nl2br(e($lec['description'])) ?></p>
                                                    <?php endif; ?>

                                                    <!-- AFFICHAGE DU RÉSUMÉ TEXTE S'IL EXISTE -->
                                                    <?php if (!empty($lec['contenu'])): ?>
                                                    <div class="card bg-light border-0 p-3 mb-3">
                                                        <h6 class="fw-bold text-primary mb-2">📌 Résumé du cours :</h6>
                                                        <div class="content-body">
                                                            <?= nl2br(e($lec['contenu'])) ?>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>

                                                    <!-- FICHIER ATTACHÉ (OPTIONNEL) -->
                                                    <div class="d-flex align-items-center justify-content-between mt-3 pt-2 border-top">
                                                        <div>
                                                            <?php if ($fileUrl): ?>
                                                                <?php if ($lec['type_format'] === 'audio'): ?>
                                                                    <audio controls style="height: 35px;">
                                                                        <source src="<?= $fileUrl ?>">
                                                                    </audio>
                                                                <?php elseif ($lec['type_format'] === 'video'): ?>
                                                                    <a href="<?= $fileUrl ?>" target="_blank" class="btn btn-sm btn-primary">▶️ Regarder la vidéo</a>
                                                                <?php else: ?>
                                                                    <a href="<?= $fileUrl ?>" target="_blank" class="btn btn-sm btn-outline-primary">📎 Télécharger le support (<?= strtoupper(pathinfo($lec['fichier'], PATHINFO_EXTENSION)) ?>)</a>
                                                                <?php endif; ?>
                                                            <?php else: ?>
                                                                <span class="badge bg-secondary">Aucun fichier attaché</span>
                                                            <?php endif; ?>
                                                        </div>

                                                        <form method="post" onsubmit="return confirm('Supprimer ce résumé / leçon ?');">
                                                            <input type="hidden" name="action_delete_lecon" value="1">
                                                            <input type="hidden" name="id_lecon" value="<?= (int)$lec['id'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-danger">🗑️ Supprimer</button>
                                                        </form>
                                                    </div>

                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- ================= MODALS ================= -->

<!-- Modal Ajouter Chapitre -->
<div class="modal fade" id="modalAddChapitre" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="action_add_chapitre" value="1">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Créer un nouveau Chapitre</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Sélectionner le Cours / Branche</label>
                        <select name="cours_id" class="form-select" required>
                            <option value="">-- Choisir un cours --</option>
                            <?php foreach ($coursList as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= e($c['intitule']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Titre du chapitre</label>
                        <input type="text" name="titre_chapitre" class="form-control" placeholder="Ex: Chapitre 1 - Introduction" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer le Chapitre</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Publier Résumé / Leçon -->
<!-- Modal Publier Résumé / Leçon -->
<div class="modal fade" id="modalAddLecon" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action_add_lecon" value="1">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">✍️ Publier un Support de Cours</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        
                        <!-- 1. Sélection Chapitre & Titre -->
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Chapitre <span class="text-danger">*</span></label>
                            <select name="chapitre_id" class="form-select" required>
                                <option value="">-- Choisir le chapitre --</option>
                                <?php 
                                $currentCours = '';
                                foreach ($allChapitresFlat as $chapSelect): 
                                    if ($currentCours !== $chapSelect['cours_nom']): 
                                        if ($currentCours !== '') echo '</optgroup>';
                                        $currentCours = $chapSelect['cours_nom'];
                                        echo '<optgroup label="Cours : '.e($currentCours).'">';
                                    endif;
                                ?>
                                <option value="<?= $chapSelect['id'] ?>"><?= e($chapSelect['titre']) ?></option>
                                <?php endforeach; 
                                if ($currentCours !== '') echo '</optgroup>';
                                ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Titre du cours / sujet <span class="text-danger">*</span></label>
                            <input type="text" name="titre_lecon" class="form-control" placeholder="Ex: Leçon 1 - Structure de l'atome" required>
                        </div>

                        <!-- 2. CHOIX DU FORMAT (Radio buttons) -->
                        <div class="col-12">
                            <label class="form-label fw-bold text-primary">Quel type de contenu souhaitez-vous ajouter ? <span class="text-danger">*</span></label>
                            <div class="d-flex gap-4 p-3 bg-light rounded border">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="format_type" id="formatTexte" value="texte" checked>
                                    <label class="form-check-label fw-bold" for="formatTexte">
                                        📝 Saisir un résumé (Texte)
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="format_type" id="formatFichier" value="fichier">
                                    <label class="form-check-label fw-bold" for="formatFichier">
                                        📎 Joindre un fichier (PDF, MP4, MP3, Doc...)
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="format_type" id="formatMixte" value="mixte">
                                    <label class="form-check-label fw-bold" for="formatMixte">
                                        📑 Résumé + Fichier joint
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold">Consignes / Objectifs (Optionnel)</label>
                            <input type="text" name="description" class="form-control" placeholder="Ex: À lire avant la séance de travaux pratiques.">
                        </div>

                        <!-- 3. ZONE RÉSUMÉ TEXTE (Affichée par défaut) -->
                        <div class="col-12" id="block_texte">
                            <label class="form-label fw-bold text-success">📝 Contenu du résumé / Rédaction du cours</label>
                            <textarea name="contenu_resume" id="input_contenu" class="form-control" rows="6" placeholder="Rédigez directement votre leçon ou résumé ici..."></textarea>
                        </div>

                        <!-- 4. ZONE FICHIER (Masquée par défaut) -->
                        <div class="col-12 d-none" id="block_fichier">
                            <label class="form-label fw-bold text-primary">📎 Document ou média attaché</label>
                            <input type="file" name="fichier" id="input_fichier" class="form-control">
                            <small class="text-muted d-block mt-1">Formats acceptés : PDF, Word, Excel, MP4, MP3... (Max 100 Mo)</small>
                        </div>

                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-success">Enregistrer & Publier</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const radioTexte = document.getElementById('formatTexte');
    const radioFichier = document.getElementById('formatFichier');
    const radioMixte = document.getElementById('formatMixte');

    const blockTexte = document.getElementById('block_texte');
    const blockFichier = document.getElementById('block_fichier');

    const inputContenu = document.getElementById('input_contenu');
    const inputFichier = document.getElementById('input_fichier');

    function updateFormDisplay() {
        if (radioTexte.checked) {
            blockTexte.classList.remove('d-none');
            blockFichier.classList.add('d-none');
            
            inputContenu.setAttribute('required', 'required');
            inputFichier.removeAttribute('required');
            inputFichier.value = ''; // Réinitialiser le fichier
        } else if (radioFichier.checked) {
            blockTexte.classList.add('d-none');
            blockFichier.classList.remove('d-none');
            
            inputFichier.setAttribute('required', 'required');
            inputContenu.removeAttribute('required');
            inputContenu.value = ''; // Réinitialiser le texte
        } else if (radioMixte.checked) {
            blockTexte.classList.remove('d-none');
            blockFichier.classList.remove('d-none');
            
            inputContenu.setAttribute('required', 'required');
            inputFichier.setAttribute('required', 'required');
        }
    }

    // Écouter les changements sur les radios
    radioTexte.addEventListener('change', updateFormDisplay);
    radioFichier.addEventListener('change', updateFormDisplay);
    radioMixte.addEventListener('change', updateFormDisplay);

    // Initialiser au chargement
    updateFormDisplay();
});
</script>

<?php include __DIR__.'/../layout/footer.php'; ?>