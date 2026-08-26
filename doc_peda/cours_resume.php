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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_add_chapitre'])) {
    $titreChapitre = trim((string)($_POST['titre_chapitre'] ?? ''));
    $coursId       = (int)($_POST['cours_id'] ?? 0);

    if ($titreChapitre === '' || $coursId <= 0) {
        $msgError = "Veuillez fournir un titre de chapitre et sélectionner un cours.";
    } else {
        $stmt = $con->prepare("INSERT INTO cours_chapitres (titre, cours_id, classe_id, prof_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('siii', $titreChapitre, $coursId, $classeId, $agentId);
        if ($stmt->execute()) {
            $msgSuccess = "Le chapitre a été créé avec succès.";
        } else {
            $msgError = "Erreur lors de la création du chapitre.";
        }
        $stmt->close();
    }
}

// -------------------------------------------------------------------------
// 2) TRAITEMENT : SUPPRIMER UN CHAPITRE (ET SES LEÇONS)
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_delete_chapitre'])) {
    $idChapDelete = (int)($_POST['id_chapitre'] ?? 0);

    // Vérification de sécurité
    $stmtCheck = $con->prepare("SELECT id FROM cours_chapitres WHERE id = ? AND prof_id = ?");
    $stmtCheck->bind_param('ii', $idChapDelete, $agentId);
    $stmtCheck->execute();
    if ($stmtCheck->get_result()->num_rows > 0) {
        // Supprimer physiquement les fichiers des leçons de ce chapitre
        $stmtFiles = $con->prepare("SELECT fichier FROM cours_lecons WHERE chapitre_id = ?");
        $stmtFiles->bind_param('i', $idChapDelete);
        $stmtFiles->execute();
        $resFiles = $stmtFiles->get_result();
        while ($f = $resFiles->fetch_assoc()) {
            @unlink($uploadDir . $f['fichier']);
        }
        $stmtFiles->close();

        // Les leçons sont supprimées via ON DELETE CASCADE (ou manuellement ici pour plus de sécurité)
        $con->query("DELETE FROM cours_lecons WHERE chapitre_id = $idChapDelete");
        $con->query("DELETE FROM cours_chapitres WHERE id = $idChapDelete");

        $msgSuccess = "Chapitre et toutes ses leçons supprimés.";
    } else {
        $msgError = "Chapitre introuvable ou accès refusé.";
    }
    $stmtCheck->close();
}

// -------------------------------------------------------------------------
// 3) TRAITEMENT : AJOUTER UNE LEÇON (UPLOAD FICHIER)
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_add_lecon'])) {
    $titreLecon = trim((string)($_POST['titre_lecon'] ?? ''));
    $chapitreId = (int)($_POST['chapitre_id'] ?? 0);
    $desc       = trim((string)($_POST['description'] ?? ''));

    // Vérifier que le chapitre appartient bien au prof
    $checkChap = $con->query("SELECT id FROM cours_chapitres WHERE id = $chapitreId AND prof_id = $agentId")->num_rows;

    if ($titreLecon === '' || $chapitreId <= 0) {
        $msgError = "Veuillez indiquer un titre et sélectionner un chapitre.";
    } elseif ($checkChap === 0) {
        $msgError = "Chapitre invalide.";
    } elseif (!isset($_FILES['fichier']) || $_FILES['fichier']['error'] !== UPLOAD_ERR_OK) {
        $msgError = "Veuillez sélectionner un fichier valide pour la leçon.";
    } else {
        $file     = $_FILES['fichier'];
        $tmpName  = $file['tmp_name'];
        $origName = $file['name'];
        $fileSize = $file['size'];
        $extension = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

        $maxSizeBytes = 100 * 1024 * 1024; // 100 Mo
        if ($fileSize > $maxSizeBytes) {
            $msgError = "Le fichier est trop volumineux (Taille max : 100 Mo).";
        } else {
            $allowedPdf   = ['pdf'];
            $allowedAudio = ['mp3', 'wav', 'ogg', 'm4a', 'aac'];
            $allowedVideo = ['mp4', 'webm', 'mkv', 'avi', 'mov'];
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
                $destination = $uploadDir . $newFilename;

                if (move_uploaded_file($tmpName, $destination)) {
                    $stmt = $con->prepare("
                        INSERT INTO cours_lecons (chapitre_id, titre, description, fichier, type_format)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->bind_param('issss', $chapitreId, $titreLecon, $desc, $newFilename, $typeFormat);
                    if ($stmt->execute()) {
                        $msgSuccess = "La leçon a été ajoutée avec succès !";
                    } else {
                        $msgError = "Erreur BDD lors de l'enregistrement de la leçon.";
                    }
                    $stmt->close();
                } else {
                    $msgError = "Erreur lors de la sauvegarde du fichier sur le serveur.";
                }
            }
        }
    }
}

// -------------------------------------------------------------------------
// 4) TRAITEMENT : SUPPRIMER UNE LEÇON
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_delete_lecon'])) {
    $idLeconDelete = (int)($_POST['id_lecon'] ?? 0);

    // Vérifier que la leçon appartient bien à un chapitre du prof
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
        @unlink($uploadDir . $resCheck['fichier']);
        $con->query("DELETE FROM cours_lecons WHERE id = $idLeconDelete");
        $msgSuccess = "La leçon a été supprimée.";
    } else {
        $msgError = "Leçon introuvable ou accès refusé.";
    }
}

// -------------------------------------------------------------------------
// CHARGEMENT DES DONNÉES POUR L'AFFICHAGE
// -------------------------------------------------------------------------

// 1. Les Cours (Branches)
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
    $coursList[$row['id']]['chapitres'] = []; // Initialisation
}
$stmt->close();

// 2. Les Chapitres & Leçons
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

$allChapitresFlat = []; // Pour le select du modal "Ajouter Leçon"

while ($chap = $resChap->fetch_assoc()) {
    $chap['lecons'] = [];
    $allChapitresFlat[] = $chap;
    
    // Attacher le chapitre à son cours s'il existe dans le tableau
    if (isset($coursList[$chap['cours_id']])) {
        $coursList[$chap['cours_id']]['chapitres'][$chap['id']] = $chap;
    }
}
$stmt->close();

// 3. Charger les leçons et les intégrer aux chapitres
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
        <h1 class="h4 mb-0">📚 Gestion des Cours & Leçons</h1>
        <div>
            <!-- Boutons pour ouvrir les modales -->
            <button class="btn btn-outline-primary me-2" data-bs-toggle="modal" data-bs-target="#modalAddChapitre">
                ➕ Nouveau Chapitre
            </button>
            <?php if (!empty($allChapitresFlat)): ?>
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalAddLecon">
                📤 Ajouter une Leçon
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

    <!-- Affichage de l'arborescence (Cours > Chapitres > Leçons) -->
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
                                <div
                                    class="card-header bg-white border-primary d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0 text-primary">📖 <?= e($chap['titre']) ?></h5>
                                    <form method="post"
                                        onsubmit="return confirm('Supprimer ce chapitre entraînera la suppression de toutes ses leçons. Continuer ?');">
                                        <input type="hidden" name="action_delete_chapitre" value="1">
                                        <input type="hidden" name="id_chapitre" value="<?= (int)$chap['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"
                                            title="Supprimer le chapitre">🗑️ Chapitre</button>
                                    </form>
                                </div>
                                <div class="card-body bg-light p-3">
                                    <?php if (empty($chap['lecons'])): ?>
                                    <div class="text-muted small">Aucune leçon dans ce chapitre.</div>
                                    <?php else: ?>
                                    <ul class="list-group list-group-flush">
                                        <?php foreach ($chap['lecons'] as $lec): ?>
                                        <?php 
                                                                    $fileUrl = '/uploads/cours_lecons/' . e($lec['fichier']);
                                                                    $icon = '📁';
                                                                    if ($lec['type_format'] === 'pdf') $icon = '📄';
                                                                    if ($lec['type_format'] === 'video') $icon = '🎥';
                                                                    if ($lec['type_format'] === 'audio') $icon = '🎙️';
                                                                ?>
                                        <li
                                            class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0 border-bottom">
                                            <div class="me-auto">
                                                <span class="fs-5 me-2"><?= $icon ?></span>
                                                <span class="fw-bold"><?= e($lec['titre']) ?></span>
                                                <?php if (!empty($lec['description'])): ?>
                                                <br><small
                                                    class="text-muted ms-4"><?= nl2br(e($lec['description'])) ?></small>
                                                <?php endif; ?>
                                            </div>

                                            <div class="d-flex align-items-center gap-2">
                                                <!-- Lecteur -->
                                                <?php if ($lec['type_format'] === 'audio'): ?>
                                                <audio controls style="height: 30px; max-width: 180px;">
                                                    <source src="<?= $fileUrl ?>">
                                                </audio>
                                                <?php elseif ($lec['type_format'] === 'video'): ?>
                                                <a href="<?= $fileUrl ?>" target="_blank"
                                                    class="btn btn-sm btn-primary">▶️ Regarder</a>
                                                <?php else: ?>
                                                <a href="<?= $fileUrl ?>" target="_blank"
                                                    class="btn btn-sm btn-secondary">⬇️ Ouvrir</a>
                                                <?php endif; ?>

                                                <!-- Suppression Leçon -->
                                                <form method="post"
                                                    onsubmit="return confirm('Supprimer cette leçon ?');">
                                                    <input type="hidden" name="action_delete_lecon" value="1">
                                                    <input type="hidden" name="id_lecon" value="<?= (int)$lec['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger"
                                                        title="Supprimer leçon">❌</button>
                                                </form>
                                            </div>
                                        </li>
                                        <?php endforeach; ?>
                                    </ul>
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
                        <input type="text" name="titre_chapitre" class="form-control"
                            placeholder="Ex: Chapitre 1 - Introduction" required>
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

<!-- Modal Ajouter Leçon -->
<div class="modal fade" id="modalAddLecon" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action_add_lecon" value="1">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Publier une nouvelle Leçon (Fichier)</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Rattacher à un Chapitre</label>
                            <select name="chapitre_id" class="form-select" required>
                                <option value="">-- Choisir le chapitre --</option>
                                <?php 
                                // On groupe les chapitres par cours dans le select
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
                            <label class="form-label fw-bold">Titre de la leçon</label>
                            <input type="text" name="titre_lecon" class="form-control"
                                placeholder="Ex: Leçon 1 - Les bases" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold">Consignes / Description</label>
                            <textarea name="description" class="form-control" rows="2"
                                placeholder="Que doivent faire les élèves ?"></textarea>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold">Fichier de la leçon (Vidéo, Audio, PDF...)</label>
                            <input type="file" name="fichier" class="form-control" required>
                            <small class="text-muted d-block mt-1">Formats : MP4, WEBM, MP3, PDF, DOCX, PPTX... (Max 100
                                Mo)</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-success">Upload la Leçon</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__.'/../layout/footer.php'; ?>