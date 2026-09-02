<?php
// /prof/doc_peda/historique_journal_de_classe.php
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

// -------------------------------------------------------------------------
// 1) TRAITEMENT : MODIFICATION D'UNE ENTRÉE
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_edit'])) {
    $idEdit   = (int)($_POST['id_journal'] ?? 0);
    $coursId  = (int)($_POST['edit_cours_id'] ?? 0);
    $matieres = trim((string)($_POST['edit_matieres'] ?? ''));
    $note     = trim((string)($_POST['edit_note'] ?? ''));

    $stmtCheck = $con->prepare("SELECT piece_jointe, statut FROM journal_classe WHERE id = ? AND prof_id = ?");
    $stmtCheck->bind_param('ii', $idEdit, $agentId);
    $stmtCheck->execute();
    $resCheck = $stmtCheck->get_result()->fetch_assoc();
    $stmtCheck->close();

    if (!$resCheck) {
        $_SESSION['msg_error'] = "Fiche introuvable ou accès non autorisé.";
    } elseif ($resCheck['statut'] === 'valider') {
        $_SESSION['msg_error'] = "Impossible de modifier une fiche déjà validée.";
    } elseif (empty($matieres) || $coursId <= 0) {
        $_SESSION['msg_error'] = "Les champs Cours et Matière sont obligatoires.";
    } else {
        $filename = $resCheck['piece_jointe'];

        if (isset($_FILES['edit_piece_jointe']['error']) && $_FILES['edit_piece_jointe']['error'] === UPLOAD_ERR_OK) {
            if (!empty($filename)) {
                @unlink($uploadDir . $filename);
            }
            $tmpName   = $_FILES['edit_piece_jointe']['tmp_name'];
            $extension = pathinfo($_FILES['edit_piece_jointe']['name'], PATHINFO_EXTENSION);
            $filename  = 'journal_' . uniqid() . '.' . strtolower($extension);
            move_uploaded_file($tmpName, $uploadDir . $filename);
        }

        $stmtUpd = $con->prepare("UPDATE journal_classe SET cours_id = ?, matieres = ?, note = ?, piece_jointe = ? WHERE id = ? AND prof_id = ?");
        $stmtUpd->bind_param('isssii', $coursId, $matieres, $note, $filename, $idEdit, $agentId);
        if ($stmtUpd->execute()) {
            $_SESSION['msg_success'] = "Leçon mise à jour avec succès.";
        }
        $stmtUpd->close();
    }

    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

// -------------------------------------------------------------------------
// 2) TRAITEMENT : SUPPRESSION D'UNE ENTRÉE
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_delete'])) {
    $idDelete = (int)($_POST['id_journal'] ?? 0);

    $stmtCheck = $con->prepare("SELECT piece_jointe, statut FROM journal_classe WHERE id = ? AND prof_id = ?");
    $stmtCheck->bind_param('ii', $idDelete, $agentId);
    $stmtCheck->execute();
    $resCheck = $stmtCheck->get_result()->fetch_assoc();
    $stmtCheck->close();

    if (!$resCheck) {
        $_SESSION['msg_error'] = "Fiche introuvable ou accès non autorisé.";
    } elseif ($resCheck['statut'] === 'valider') {
        $_SESSION['msg_error'] = "Impossible de supprimer une fiche déjà validée.";
    } else {
        if (!empty($resCheck['piece_jointe'])) {
            @unlink($uploadDir . $resCheck['piece_jointe']);
        }
        $stmtDel = $con->prepare("DELETE FROM journal_classe WHERE id = ? AND prof_id = ?");
        $stmtDel->bind_param('ii', $idDelete, $agentId);
        if ($stmtDel->execute()) {
            $_SESSION['msg_success'] = "Entrée supprimée de l'historique.";
        }
        $stmtDel->close();
    }

    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

// Messages Flash
$msgSuccess = $_SESSION['msg_success'] ?? '';
$msgError   = $_SESSION['msg_error'] ?? '';
unset($_SESSION['msg_success'], $_SESSION['msg_error']);

// Filtres de recherche
$filterCoursId   = (int)($_GET['cours_id'] ?? 0);
$filterDateDebut = trim((string)($_GET['date_debut'] ?? ''));
$filterDateFin   = trim((string)($_GET['date_fin'] ?? ''));
$filterStatut    = trim((string)($_GET['statut'] ?? ''));

// Liste des cours pour le filtre et pour le select du Modal
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

// Construction de la requête avec filtres
$sql = "
    SELECT jc.*, co.intitule AS cours_nom
    FROM journal_classe jc
    INNER JOIN cours co ON co.id = jc.cours_id
    WHERE jc.prof_id = ? AND jc.classe_id = ?
";
$params = [$agentId, $classeId];
$types  = 'ii';

if ($filterCoursId > 0) {
    $sql .= " AND jc.cours_id = ?";
    $params[] = $filterCoursId;
    $types .= 'i';
}
if (!empty($filterDateDebut)) {
    $sql .= " AND jc.jour_date >= ?";
    $params[] = $filterDateDebut;
    $types .= 's';
}
if (!empty($filterDateFin)) {
    $sql .= " AND jc.jour_date <= ?";
    $params[] = $filterDateFin;
    $types .= 's';
}
if (in_array($filterStatut, ['en attente', 'valider', 'rejeter'], true)) {
    $sql .= " AND jc.statut = ?";
    $params[] = $filterStatut;
    $types .= 's';
}

$sql .= " ORDER BY jc.jour_date DESC, jc.id DESC";

$stmt = $con->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$historique = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

include __DIR__.'/../layout/header.php';
include __DIR__.'/../layout/navbar.php';
?>

<div class="container mt-3 py-2">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h5 mb-0 fw-bold">📜 Historique complet des journaux de classe</h1>
        <a href="journal_de_classe.php" class="btn btn-secondary btn-md">
            ← Saisir le journal du jour
        </a>
    </div>

    <!-- Alertes -->
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

    <!-- Zone de filtrage -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light fw-bold">Filtrer l'historique</div>
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Cours</label>
                    <select name="cours_id" class="form-select form-select-sm">
                        <option value="0">Tous les cours</option>
                        <?php foreach ($coursList as $co): ?>
                        <option value="<?= (int)$co['id'] ?>"
                            <?= $filterCoursId === (int)$co['id'] ? 'selected' : '' ?>>
                            <?= e($co['intitule']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Du</label>
                    <input type="date" name="date_debut" class="form-control form-control-sm"
                        value="<?= e($filterDateDebut) ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Au</label>
                    <input type="date" name="date_fin" class="form-control form-control-sm"
                        value="<?= e($filterDateFin) ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Statut</label>
                    <select name="statut" class="form-select form-select-sm">
                        <option value="">Tous les statuts</option>
                        <option value="en attente" <?= $filterStatut === 'en attente' ? 'selected' : '' ?>>En attente
                        </option>
                        <option value="valider" <?= $filterStatut === 'valider' ? 'selected' : '' ?>>Validé</option>
                        <option value="rejeter" <?= $filterStatut === 'rejeter' ? 'selected' : '' ?>>Rejeté</option>
                    </select>
                </div>

                <div class="col-12 text-end">
                    <a href="historique_journal_de_classe.php"
                        class="btn btn-outline-secondary btn-sm">Réinitialiser</a>
                    <button type="submit" class="btn btn-primary btn-sm">Filtrer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tableau Historique -->
    <div class="card shadow-sm">
        <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
            <span>Résultats</span>
            <span class="badge bg-primary"><?= count($historique) ?> entrée(s)</span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($historique)): ?>
            <div class="p-3 text-muted text-center">Aucune fiche ne correspond à vos critères de recherche.</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Cours</th>
                            <th>Matière dispensée</th>
                            <th>Notes / Remarques</th>
                            <th>Pièce jointe</th>
                            <th class="text-center">Statut</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($historique as $f): ?>
                        <tr>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <?= date('d/m/Y', strtotime($f['jour_date'])) ?>
                                </span>
                            </td>
                            <td><strong><?= e($f['cours_nom']) ?></strong></td>
                            <td><?= nl2br(e($f['matieres'])) ?></td>
                            <td><?= e($f['note'] ?: '—') ?></td>
                            <td>
                                <?php if (!empty($f['piece_jointe'])): ?>
                                <a href="/uploads/attachement_journal_de_class/<?= e($f['piece_jointe']) ?>"
                                    target="_blank" class="btn btn-sm btn-outline-primary">
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
                            <td class="text-end">
                                <?php if (($f['statut'] ?? 'en attente') !== 'valider'): ?>
                                <button type="button" class="btn btn-sm btn-outline-secondary me-1 btn-edit"
                                    data-id="<?= (int)$f['id'] ?>" data-cours="<?= (int)$f['cours_id'] ?>"
                                    data-matieres="<?= htmlspecialchars($f['matieres'], ENT_QUOTES) ?>"
                                    data-note="<?= htmlspecialchars($f['note'], ENT_QUOTES) ?>" data-bs-toggle="modal"
                                    data-bs-target="#editModal" title="Modifier">
                                    ✏️
                                </button>
                                <form method="post" class="d-inline"
                                    onsubmit="return confirm('Supprimer cette leçon de l\'historique ?');">
                                    <input type="hidden" name="action_delete" value="1">
                                    <input type="hidden" name="id_journal" value="<?= (int)$f['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"
                                        title="Supprimer">🗑️</button>
                                </form>
                                <?php else: ?>
                                <span class="text-muted small">🔒 Modif. bloquée</span>
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

<!-- MODAL DE MODIFICATION -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" enctype="multipart/form-data" class="modal-content">
            <input type="hidden" name="action_edit" value="1">
            <input type="hidden" name="id_journal" id="modal_edit_id" value="">

            <div class="modal-header bg-warning">
                <h5 class="modal-title fw-bold" id="editModalLabel">✏️ Modifier la leçon de l'historique</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold">Cours / Branche</label>
                    <select name="edit_cours_id" id="modal_edit_cours" class="form-select" required>
                        <option value="">-- Choisir un cours --</option>
                        <?php foreach ($coursList as $co): ?>
                        <option value="<?= (int)$co['id'] ?>"><?= e($co['intitule']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Matière dispensée</label>
                    <textarea name="edit_matieres" id="modal_edit_matieres" class="form-control" rows="3"
                        required></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Remarques / Devoirs</label>
                    <input type="text" name="edit_note" id="modal_edit_note" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Changer la pièce jointe (Optionnel)</label>
                    <input type="file" name="edit_piece_jointe" class="form-control">
                    <small class="text-muted">Laissez vide pour conserver le fichier actuel.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn btn-warning fw-bold text-dark">Enregistrer les modifications</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const editBtns = document.querySelectorAll('.btn-edit');
    editBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('modal_edit_id').value = this.getAttribute('data-id');
            document.getElementById('modal_edit_cours').value = this.getAttribute('data-cours');
            document.getElementById('modal_edit_matieres').value = this.getAttribute(
                'data-matieres');
            document.getElementById('modal_edit_note').value = this.getAttribute('data-note');
        });
    });
});
</script>

<?php include __DIR__.'/../layout/footer.php'; ?>