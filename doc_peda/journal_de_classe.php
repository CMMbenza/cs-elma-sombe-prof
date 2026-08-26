<?php
// /prof/doc_peda/journal_de_classe.php
declare(strict_types=1);

require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/helpers.php';
require_prof();

include __DIR__.'/../get_annee_en_cours.php';

$prof     = current_prof();
$agentId  = (int)($prof['id'] ?? 0);
$classeId = (int)get_current_classe();

$uploadDir = __DIR__.'/../../uploads/attachement_journal_de_class/';

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
// 1) TRAITEMENT : SUPPRESSION (DELETE)
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_delete'])) {
    $idDelete = (int)($_POST['id_journal'] ?? 0);

    // Vérification : la fiche existe, appartient au prof et N'EST PAS encore validée
    $stmtCheck = $con->prepare("SELECT piece_jointe, statut FROM journal_classe WHERE id = ? AND prof_id = ?");
    $stmtCheck->bind_param('ii', $idDelete, $agentId);
    $stmtCheck->execute();
    $resCheck = $stmtCheck->get_result()->fetch_assoc();
    $stmtCheck->close();

    if (!$resCheck) {
        $msgError = "Fiche introuvable ou accès non autorisé.";
    } elseif ($resCheck['statut'] === 'valider') {
        $msgError = "Impossible de supprimer une fiche déjà validée par la direction.";
    } else {
        // Supprimer le fichier physiquement s'il existe
        if (!empty($resCheck['piece_jointe'])) {
            $filePath = $uploadDir . $resCheck['piece_jointe'];
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }

        // Supprimer l'enregistrement en BDD
        $stmtDel = $con->prepare("DELETE FROM journal_classe WHERE id = ? AND prof_id = ?");
        $stmtDel->bind_param('ii', $idDelete, $agentId);
        if ($stmtDel->execute()) {
            $msgSuccess = "Entrée supprimée avec succès.";
        } else {
            $msgError = "Erreur lors de la suppression.";
        }
        $stmtDel->close();
    }
}

// -------------------------------------------------------------------------
// 2) TRAITEMENT : MODIFICATION (EDIT)
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_edit'])) {
    $idEdit   = (int)($_POST['id_journal'] ?? 0);
    $jourDate = trim((string)($_POST['jour_date'] ?? ''));
    $coursId  = (int)($_POST['cours_id'] ?? 0);
    $matieres = trim((string)($_POST['matieres'] ?? ''));
    $note     = trim((string)($_POST['note'] ?? ''));

    // Vérifier les droits et l'état de validation
    $stmtCheck = $con->prepare("SELECT piece_jointe, statut FROM journal_classe WHERE id = ? AND prof_id = ?");
    $stmtCheck->bind_param('ii', $idEdit, $agentId);
    $stmtCheck->execute();
    $resCheck = $stmtCheck->get_result()->fetch_assoc();
    $stmtCheck->close();

    if (!$resCheck) {
        $msgError = "Fiche introuvable.";
    } elseif ($resCheck['statut'] === 'valider') {
        $msgError = "Impossible de modifier une fiche déjà validée.";
    } elseif ($jourDate === '' || $coursId <= 0 || $matieres === '') {
        $msgError = "Veuillez remplir tous les champs obligatoires.";
    } else {
        $filename = $resCheck['piece_jointe']; // Conserver l'ancien fichier par défaut

        // Gestion du remplacement de fichier joint
        if (isset($_FILES['piece_jointe']) && $_FILES['piece_jointe']['error'] === UPLOAD_ERR_OK) {
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            // Supprimer l'ancien fichier si remplacé
            if (!empty($resCheck['piece_jointe'])) {
                @unlink($uploadDir . $resCheck['piece_jointe']);
            }

            $tmpName     = $_FILES['piece_jointe']['tmp_name'];
            $extension   = pathinfo($_FILES['piece_jointe']['name'], PATHINFO_EXTENSION);
            $filename    = 'journal_' . uniqid() . '.' . strtolower($extension);
            $destination = $uploadDir . $filename;

            if (!move_uploaded_file($tmpName, $destination)) {
                $msgError = "Erreur lors du transfert du nouveau fichier.";
                $filename = $resCheck['piece_jointe'];
            }
        }

        if (empty($msgError)) {
            $stmtUpd = $con->prepare("
                UPDATE journal_classe 
                SET jour_date = ?, cours_id = ?, matieres = ?, note = ?, piece_jointe = ?
                WHERE id = ? AND prof_id = ?
            ");
            $stmtUpd->bind_param('sisssii', $jourDate, $coursId, $matieres, $note, $filename, $idEdit, $agentId);

            if ($stmtUpd->execute()) {
                $msgSuccess = "Entrée mise à jour avec succès.";
            } else {
                $msgError = "Erreur lors de la mise à jour.";
            }
            $stmtUpd->close();
        }
    }
}

// -------------------------------------------------------------------------
// 3) TRAITEMENT : AJOUT (ADD)
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_add'])) {
    $jourDate = trim((string)($_POST['jour_date'] ?? ''));
    $coursId  = (int)($_POST['cours_id'] ?? 0);
    $matieres = trim((string)($_POST['matieres'] ?? ''));
    $note     = trim((string)($_POST['note'] ?? ''));

    if ($jourDate === '' || $coursId <= 0 || $matieres === '') {
        $msgError = "Veuillez remplir la date, le cours et le contenu de la matière.";
    } else {
        $filename = null;

        if (isset($_FILES['piece_jointe']) && $_FILES['piece_jointe']['error'] === UPLOAD_ERR_OK) {
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $tmpName   = $_FILES['piece_jointe']['tmp_name'];
            $extension = pathinfo($_FILES['piece_jointe']['name'], PATHINFO_EXTENSION);
            $filename  = 'journal_' . uniqid() . '.' . strtolower($extension);
            $destination = $uploadDir . $filename;

            if (!move_uploaded_file($tmpName, $destination)) {
                $msgError = "Erreur lors du transfert du fichier.";
                $filename = null;
            }
        }

        if (empty($msgError)) {
            $stmt = $con->prepare("
                INSERT INTO journal_classe (jour_date, prof_id, classe_id, cours_id, anneScolaire, matieres, note, piece_jointe, statut)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'en attente')
            ");
            $stmt->bind_param('siisssss', $jourDate, $agentId, $classeId, $coursId, $anneeEnCours, $matieres, $note, $filename);
            
            if ($stmt->execute()) {
                $msgSuccess = "Entrée ajoutée au journal de classe avec succès.";
            } else {
                $msgError = "Erreur lors de la sauvegarde dans la base de données.";
            }
            $stmt->close();
        }
    }
}

// -------------------------------------------------------------------------
// 4) CHARGEMENT DES DONNÉES
// -------------------------------------------------------------------------

// Récupération des cours attribués au prof pour cette classe
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
$coursList = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Récupération des entrées du jour
$today = date('Y-m-d');
$stmt = $con->prepare("
    SELECT jc.*, co.intitule AS cours_nom
    FROM journal_classe jc
    INNER JOIN cours co ON co.id = jc.cours_id
    WHERE jc.prof_id = ? AND jc.classe_id = ? AND jc.jour_date = ?
    ORDER BY jc.id DESC
");
$stmt->bind_param('iis', $agentId, $classeId, $today);
$stmt->execute();
$fichesDuJour = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

include __DIR__.'/../layout/header.php';
include __DIR__.'/../layout/navbar.php';
?>

<div class="container mt-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h5 mb-0">Journal de Classe du jour (<?= date('d/m/Y') ?>)</h1>
        <a href="historique_journal_de_classe.php" class="btn btn-primary btn-md">
            📜 Voir l'historique complet
        </a>
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

    <!-- Formulaire d'ajout -->
    <div class="card mb-4 shadow-sm">
        <div class="card-header bg-primary text-white fw-bold">
            Consigner une leçon / matière
        </div>
        <div class="card-body">
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action_add" value="1">

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Date</label>
                        <input type="date" name="jour_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="col-md-8">
                        <label class="form-label fw-semibold">Branche (Cours)</label>
                        <select name="cours_id" class="form-select" required>
                            <option value="">-- Choisir un cours --</option>
                            <?php foreach ($coursList as $co): ?>
                                <option value="<?= (int)$co['id'] ?>"><?= e($co['intitule']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">Matières dispensées / Leçon</label>
                        <textarea name="matieres" class="form-control" rows="3" placeholder="Sujet, points clés vus en classe..." required></textarea>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Remarques / Observations (Optionnel)</label>
                        <input type="text" name="note" class="form-control" placeholder="Devoir donné, élèves absents...">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Pièce jointe (Support, devoir...)</label>
                        <input type="file" name="piece_jointe" class="form-control">
                    </div>
                </div>

                <div class="mt-3 text-end">
                    <button type="submit" class="btn btn-success">Enregistrer le journal</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tableau du Jour avec Actions Edit & Delete -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light fw-bold d-flex justify-content-between align-items-center">
            <span>Entrées consignées aujourd'hui</span>
            <span class="badge bg-secondary"><?= count($fichesDuJour) ?> fiche(s)</span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($fichesDuJour)): ?>
                <div class="p-3 text-muted text-center">Aucune fiche consignée pour la journée d'aujourd'hui.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Cours</th>
                                <th>Matière dispensée</th>
                                <th>Notes / Remarques</th>
                                <th>Pièce jointe</th>
                                <th class="text-center">Statut</th>
                                <th class="text-center" style="width: 120px;"> </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($fichesDuJour as $f): ?>
                                <tr>
                                    <td><strong><?= e($f['cours_nom']) ?></strong></td>
                                    <td><?= nl2br(e($f['matieres'])) ?></td>
                                    <td><?= e($f['note'] ?: '—') ?></td>
                                    <td>
                                        <?php if (!empty($f['piece_jointe'])): ?>
                                            <a href="/uploads/attachement_journal_de_class/<?= e($f['piece_jointe']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                📎 Ouvrir PJ
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php
                                        $statut = $f['statut'] ?? 'en attente';
                                        switch ($statut) {
                                            case 'valider':
                                                echo '<span class="badge bg-success">Validé</span>';
                                                break;
                                            case 'rejeter':
                                                echo '<span class="badge bg-danger">Rejeté</span>';
                                                break;
                                            case 'en attente':
                                            default:
                                                echo '<span class="badge bg-warning text-dark">En attente</span>';
                                                break;
                                        }
                                        ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($statut === 'valider'): ?>
                                            <span class="text-muted small" title="Fiche validée, modification verrouillée">🔒 Verrouillé</span>
                                        <?php else: ?>
                                            <div class="d-flex">
                                                <!-- Bouton Éditer (Ouvre Modal) -->
                                                <button type="button" class="btn btn-warning me-2" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#editModal<?= (int)$f['id'] ?>" title="Modifier">
                                                    Modifier
                                                </button>

                                                <!-- Formulaire de Suppression -->
                                                <form method="post" class="d-inline" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette fiche ?');">
                                                    <input type="hidden" name="action_delete" value="1">
                                                    <input type="hidden" name="id_journal" value="<?= (int)$f['id'] ?>">
                                                    <button type="submit" class="btn btn-danger" title="Supprimer">
                                                        Supprimer
                                                    </button>
                                                </form>
                                            </div>

                                            <!-- Modal de Modification -->
                                            <div class="modal fade text-start" id="editModal<?= (int)$f['id'] ?>" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog modal-lg">
                                                    <div class="modal-content">
                                                        <form method="post" enctype="multipart/form-data">
                                                            <input type="hidden" name="action_edit" value="1">
                                                            <input type="hidden" name="id_journal" value="<?= (int)$f['id'] ?>">

                                                            <div class="modal-header bg-primary text-white">
                                                                <h5 class="modal-title h6">Modifier l'entrée du journal</h5>
                                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                            </div>

                                                            <div class="modal-body">
                                                                <div class="row g-3">
                                                                    <div class="col-md-4">
                                                                        <label class="form-label fw-semibold">Date</label>
                                                                        <input type="date" name="jour_date" class="form-control" value="<?= e($f['jour_date']) ?>" required>
                                                                    </div>

                                                                    <div class="col-md-8">
                                                                        <label class="form-label fw-semibold">Branche (Cours)</label>
                                                                        <select name="cours_id" class="form-select" required>
                                                                            <?php foreach ($coursList as $co): ?>
                                                                                <option value="<?= (int)$co['id'] ?>" <?= $co['id'] == $f['cours_id'] ? 'selected' : '' ?>>
                                                                                    <?= e($co['intitule']) ?>
                                                                                </option>
                                                                            <?php endforeach; ?>
                                                                        </select>
                                                                    </div>

                                                                    <div class="col-12">
                                                                        <label class="form-label fw-semibold">Matières dispensées / Leçon</label>
                                                                        <textarea name="matieres" class="form-control" rows="3" required><?= e($f['matieres']) ?></textarea>
                                                                    </div>

                                                                    <div class="col-md-6">
                                                                        <label class="form-label fw-semibold">Remarques / Observations</label>
                                                                        <input type="text" name="note" class="form-control" value="<?= e($f['note']) ?>">
                                                                    </div>

                                                                    <div class="col-md-6">
                                                                        <label class="form-label fw-semibold">Changer la pièce jointe</label>
                                                                        <input type="file" name="piece_jointe" class="form-control">
                                                                        <?php if (!empty($f['piece_jointe'])): ?>
                                                                            <small class="text-muted d-block mt-1">Fichier actuel : <?= e($f['piece_jointe']) ?></small>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Annuler</button>
                                                                <button type="submit" class="btn btn-primary btn-sm">Mettre à jour</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- Fin Modal -->
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__.'/../layout/footer.php'; ?>