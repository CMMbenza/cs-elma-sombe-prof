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

// -------------------------------------------------------------------------
// 1) TRAITEMENT : SUPPRESSION D'UNE LIGNE
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
            $_SESSION['msg_success'] = "Entrée supprimée avec succès.";
        }
        $stmtDel->close();
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// -------------------------------------------------------------------------
// 2) TRAITEMENT : ENREGISTREMENT GROUPÉ (SAISIE MULTIPLE DU JOUR)
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_add_batch'])) {
    $jourDate = trim((string)($_POST['jour_date'] ?? ''));
    $coursIds = $_POST['cours_id'] ?? [];
    $matieres = $_POST['matieres'] ?? [];
    $notes    = $_POST['note'] ?? [];
    
    if (empty($jourDate) || empty($coursIds)) {
        $_SESSION['msg_error'] = "Veuillez spécifier la date et au moins une leçon.";
    } else {
        $successCount = 0;

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        foreach ($coursIds as $index => $cId) {
            $coursId    = (int)$cId;
            $matiereTxt = trim((string)($matieres[$index] ?? ''));
            $noteTxt    = trim((string)($notes[$index] ?? ''));

            if ($coursId > 0 && $matiereTxt !== '') {
                $filename = null;

                if (isset($_FILES['piece_jointe']['error'][$index]) && $_FILES['piece_jointe']['error'][$index] === UPLOAD_ERR_OK) {
                    $tmpName   = $_FILES['piece_jointe']['tmp_name'][$index];
                    $extension = pathinfo($_FILES['piece_jointe']['name'][$index], PATHINFO_EXTENSION);
                    $filename  = 'journal_' . uniqid() . '.' . strtolower($extension);
                    move_uploaded_file($tmpName, $uploadDir . $filename);
                }

                $stmt = $con->prepare("
                    INSERT INTO journal_classe (jour_date, prof_id, classe_id, cours_id, anneScolaire, matieres, note, piece_jointe, statut)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'en attente')
                ");
                $stmt->bind_param('siisssss', $jourDate, $agentId, $classeId, $coursId, $anneeEnCours, $matiereTxt, $noteTxt, $filename);
                if ($stmt->execute()) {
                    $successCount++;
                }
                $stmt->close();
            }
        }

        if ($successCount > 0) {
            $_SESSION['msg_success'] = "{$successCount} matière(s) consignée(s) avec succès pour le " . date('d/m/Y', strtotime($jourDate)) . ".";
        } else {
            $_SESSION['msg_error'] = "Aucune matière valide n'a été remplie.";
        }
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// -------------------------------------------------------------------------
// 3) TRAITEMENT : MODIFICATION D'UNE ENTRÉE
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_edit'])) {
    $idEdit    = (int)($_POST['id_journal'] ?? 0);
    $coursId   = (int)($_POST['edit_cours_id'] ?? 0);
    $matieres  = trim((string)($_POST['edit_matieres'] ?? ''));
    $note      = trim((string)($_POST['edit_note'] ?? ''));
    
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
        $_SESSION['msg_error'] = "Les champs Cours et Matières sont obligatoires.";
    } else {
        $filename = $resCheck['piece_jointe'];

        // S'il y a un nouveau fichier, on remplace l'ancien
        if (isset($_FILES['edit_piece_jointe']['error']) && $_FILES['edit_piece_jointe']['error'] === UPLOAD_ERR_OK) {
            if (!empty($filename)) {
                @unlink($uploadDir . $filename);
            }
            $tmpName   = $_FILES['edit_piece_jointe']['tmp_name'];
            $extension = pathinfo($_FILES['edit_piece_jointe']['name'], PATHINFO_EXTENSION);
            $filename  = 'journal_' . uniqid() . '.' . strtolower($extension);
            move_uploaded_file($tmpName, $uploadDir . $filename);
        }

        $stmtUpd = $con->prepare("UPDATE journal_classe SET cours_id = ?, matieres = ?, note = ?, piece_jointe = ? WHERE id = ?");
        $stmtUpd->bind_param('isssi', $coursId, $matieres, $note, $filename, $idEdit);
        if ($stmtUpd->execute()) {
            $_SESSION['msg_success'] = "Leçon modifiée avec succès.";
        }
        $stmtUpd->close();
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Récupération des messages flash
$msgSuccess = $_SESSION['msg_success'] ?? '';
$msgError   = $_SESSION['msg_error'] ?? '';
unset($_SESSION['msg_success'], $_SESSION['msg_error']);

// -------------------------------------------------------------------------
// 4) CHARGEMENT DES DONNÉES
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
$coursList = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Chargement des leçons du jour
$today = date('Y-m-d');
$stmt = $con->prepare("
    SELECT jc.*, co.intitule AS cours_nom
    FROM journal_classe jc
    INNER JOIN cours co ON co.id = jc.cours_id
    WHERE jc.prof_id = ? AND jc.classe_id = ? AND jc.jour_date = ?
    ORDER BY jc.id ASC
");
$stmt->bind_param('iis', $agentId, $classeId, $today);
$stmt->execute();
$fichesDuJour = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

include __DIR__.'/../layout/header.php';
include __DIR__.'/../layout/navbar.php';
?>

<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="fw-bold mb-0">📖 Journal de Classe (Saisie Journalière)</h4>
            <small class="text-muted">Consignez toutes vos leçons données aujourd'hui sur un seul formulaire.</small>
        </div>
        <div>
            <button type="button" class="btn btn-info text-white btn-sm me-2 fw-bold" data-bs-toggle="modal" data-bs-target="#aideModal">
                💡 Revoir l'explication
            </button>
            <a href="historique_journal_de_classe.php" class="btn btn-outline-primary btn-sm fw-bold">
                📜 Historique complet
            </a>
        </div>
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

    <!-- FORMULAIRE DE SAISIE GROUPÉE -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center py-3">
            <span class="fw-bold">✏️ Journal du jour (<?= date('d/m/Y') ?>)</span>
            <button type="button" class="btn btn-light btn-sm fw-bold text-primary" id="add-row-btn">
                ➕ Ajouter un cours
            </button>
        </div>
        <div class="card-body p-3">
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action_add_batch" value="1">

                <div class="row mb-3">
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Date du journal</label>
                        <input type="date" name="jour_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered align-middle" id="journal-table">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 25%;">Cours / Branche <span class="text-danger">*</span></th>
                                <th style="width: 40%;">Matière dispensée / Sujet vu <span class="text-danger">*</span></th>
                                <th style="width: 20%;">Remarques / Devoirs</th>
                                <th style="width: 10%;">Support / PJ</th>
                                <th style="width: 5%;" class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody id="journal-tbody">
                            <tr class="journal-row">
                                <td>
                                    <select name="cours_id[]" class="form-select" required>
                                        <option value="">-- Choisir un cours --</option>
                                        <?php foreach ($coursList as $co): ?>
                                            <option value="<?= (int)$co['id'] ?>"><?= e($co['intitule']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <textarea name="matieres[]" class="form-control" rows="2" placeholder="Ex: Additions de fractions, vocabulaire..." required></textarea>
                                </td>
                                <td>
                                    <input type="text" name="note[]" class="form-control" placeholder="Ex: Devoir p.45, contrôles...">
                                </td>
                                <td>
                                    <input type="file" name="piece_jointe[]" class="form-control form-control-sm">
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-outline-danger btn-remove-row" title="Supprimer la ligne">❌</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-3">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="add-row-btn-bottom">
                        ➕ Ajouter une autre matière
                    </button>
                    <button type="submit" class="btn btn-success fw-bold px-4">
                        💾 Enregistrer tout le journal du jour
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- LEÇONS DÉJÀ CONSIGNÉES AUJOURD'HUI -->
    <?php if (!empty($fichesDuJour)): ?>
        <div class="card shadow-sm border-0">
            <div class="card-header bg-light fw-bold py-3">
                📋 Leçons enregistrées aujourd'hui
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Cours</th>
                                <th>Matière dispensée</th>
                                <th>Remarques</th>
                                <th>Pièce jointe</th>
                                <th class="text-center">Statut</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($fichesDuJour as $f): ?>
                                <tr>
                                    <td class="fw-bold text-primary"><?= e($f['cours_nom']) ?></td>
                                    <td><?= nl2br(e($f['matieres'])) ?></td>
                                    <td><?= e($f['note'] ?: '—') ?></td>
                                    <td>
                                        <?php if (!empty($f['piece_jointe'])): ?>
                                            <a href="/uploads/attachement_journal_de_class/<?= e($f['piece_jointe']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">📎 Fichier</a>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if (($f['statut'] ?? 'en attente') === 'valider'): ?>
                                            <span class="badge bg-success">Validé</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">En attente</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if (($f['statut'] ?? 'en attente') !== 'valider'): ?>
                                            <!-- Bouton Modifier -->
                                            <button type="button" class="btn btn-sm btn-outline-secondary me-1 btn-edit"
                                                data-id="<?= (int)$f['id'] ?>"
                                                data-cours="<?= (int)$f['cours_id'] ?>"
                                                data-matieres="<?= htmlspecialchars($f['matieres'], ENT_QUOTES) ?>"
                                                data-note="<?= htmlspecialchars($f['note'], ENT_QUOTES) ?>"
                                                data-bs-toggle="modal" data-bs-target="#editModal" title="Modifier">
                                                ✏️
                                            </button>
                                            <!-- Formulaire Supprimer -->
                                            <form method="post" class="d-inline" onsubmit="return confirm('Voulez-vous vraiment supprimer cette leçon ?');">
                                                <input type="hidden" name="action_delete" value="1">
                                                <input type="hidden" name="id_journal" value="<?= (int)$f['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Supprimer">🗑️</button>
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
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- MODAL DE MODIFICATION D'UNE LEÇON -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" enctype="multipart/form-data" class="modal-content">
            <input type="hidden" name="action_edit" value="1">
            <input type="hidden" name="id_journal" id="modal_edit_id" value="">
            
            <div class="modal-header bg-warning">
                <h5 class="modal-title fw-bold" id="editModalLabel">✏️ Modifier la leçon</h5>
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
                    <textarea name="edit_matieres" id="modal_edit_matieres" class="form-control" rows="3" required></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Remarques / Devoirs</label>
                    <input type="text" name="edit_note" id="modal_edit_note" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Remplacer la pièce jointe (Optionnel)</label>
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

<!-- MODAL EXPLICATIF (Reste identique) -->
<div class="modal fade" id="aideModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title fw-bold">💡 Gain de temps : Saisie unique du journal !</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body fs-6">
                <p class="fw-bold text-dark">Chers Enseignants,</p>
                <p>Pour vous simplifier la tâche, vous n'avez plus besoin d'enregistrer votre journal cours par cours après chaque période !</p>
                <div class="card bg-light border-0 p-3 mb-3">
                    <ol class="mb-0 ps-3">
                        <li class="mb-2">Indiquez le premier cours donné dans la première ligne.</li>
                        <li class="mb-2">Cliquez sur <strong>"➕ Ajouter un cours"</strong> pour faire apparaître une autre ligne.</li>
                        <li class="mb-2">Ajoutez autant de lignes que de cours dispensés dans la journée.</li>
                        <li class="mb-0">Cliquez une seule fois sur <strong>"💾 Enregistrer tout le journal du jour"</strong>.</li>
                    </ol>
                </div>
                <div class="alert alert-warning mb-0 py-2 small">
                    📌 <strong>Note :</strong> Tant que la direction n'a pas validé, vous pouvez modifier (✏️) ou supprimer (🗑️) vos saisies via le tableau du bas.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary fw-bold" data-bs-dismiss="modal">Commencer à saisir</button>
            </div>
        </div>
    </div>
</div>

<!-- JAVASCRIPT GESTION DYNAMIQUE ET MODALS -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. Affichage automatique du modal d'aide (sauf si l'utilisateur a juste supprimé/modifié, pour ne pas le spammer)
    <?php if (empty($msgSuccess) && empty($msgError)): ?>
        const aideModalElement = document.getElementById('aideModal');
        if (aideModalElement) {
            new bootstrap.Modal(aideModalElement).show();
        }
    <?php endif; ?>

    // 2. Ajout / Suppression de lignes
    const tbody = document.getElementById('journal-tbody');
    const addBtn = document.getElementById('add-row-btn');
    const addBtnBottom = document.getElementById('add-row-btn-bottom');

    function createNewRow() {
        const firstRow = tbody.querySelector('.journal-row');
        const newRow = firstRow.cloneNode(true);
        newRow.querySelectorAll('input, textarea, select').forEach(input => {
            if (input.tagName === 'SELECT') input.selectedIndex = 0;
            else input.value = '';
        });
        tbody.appendChild(newRow);
    }
    
    if(addBtn) addBtn.addEventListener('click', createNewRow);
    if(addBtnBottom) addBtnBottom.addEventListener('click', createNewRow);

    if(tbody) {
        tbody.addEventListener('click', function (e) {
            if (e.target.classList.contains('btn-remove-row')) {
                const rows = tbody.querySelectorAll('.journal-row');
                if (rows.length > 1) e.target.closest('tr').remove();
                else alert("Vous devez conserver au moins une ligne.");
            }
        });
    }

    // 3. Pré-remplissage du Modal de modification
    const editBtns = document.querySelectorAll('.btn-edit');
    editBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('modal_edit_id').value = this.getAttribute('data-id');
            document.getElementById('modal_edit_cours').value = this.getAttribute('data-cours');
            document.getElementById('modal_edit_matieres').value = this.getAttribute('data-matieres');
            document.getElementById('modal_edit_note').value = this.getAttribute('data-note');
        });
    });
});
</script>

<?php include __DIR__.'/../layout/footer.php'; ?>