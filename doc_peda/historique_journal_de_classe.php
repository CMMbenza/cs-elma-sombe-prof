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

if (!$classeId) {
    include __DIR__.'/../layout/header.php';
    include __DIR__.'/../layout/navbar.php';
    echo '<div class="container mt-3"><div class="alert alert-info">
            Aucune classe sélectionnée. <a href="/prof/switch_classe.php">Choisir une classe</a>
          </div></div>';
    include __DIR__.'/../layout/footer.php';
    exit;
}

// Filtres de recherche
$filterCoursId  = (int)($_GET['cours_id'] ?? 0);
$filterDateDebut = trim((string)($_GET['date_debut'] ?? ''));
$filterDateFin   = trim((string)($_GET['date_fin'] ?? ''));
$filterStatut    = trim((string)($_GET['statut'] ?? ''));

// Liste des cours pour le filtre
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
        <h1 class="h5 mb-0">📜 Historique complet des journaux de classe</h1>
        <a href="journal_de_classe.php" class="btn btn-secondary btn-md">
            ← Saisir le journal du jour
        </a>
    </div>

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
                            <option value="<?= (int)$co['id'] ?>" <?= $filterCoursId === (int)$co['id'] ? 'selected' : '' ?>>
                                <?= e($co['intitule']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Du</label>
                    <input type="date" name="date_debut" class="form-control form-control-sm" value="<?= e($filterDateDebut) ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Au</label>
                    <input type="date" name="date_fin" class="form-control form-control-sm" value="<?= e($filterDateFin) ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Statut</label>
                    <select name="statut" class="form-select form-select-sm">
                        <option value="">Tous les statuts</option>
                        <option value="en attente" <?= $filterStatut === 'en attente' ? 'selected' : '' ?>>En attente</option>
                        <option value="valider" <?= $filterStatut === 'valider' ? 'selected' : '' ?>>Validé</option>
                        <option value="rejeter" <?= $filterStatut === 'rejeter' ? 'selected' : '' ?>>Rejeté</option>
                    </select>
                </div>

                <div class="col-12 text-end">
                    <a href="historique_journal_de_classe.php" class="btn btn-outline-secondary btn-sm">Réinitialiser</a>
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