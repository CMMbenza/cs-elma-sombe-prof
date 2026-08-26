<?php
// /prof/doc_peda/vusialisation_de_mes_cours.php
declare(strict_types=1);

require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/helpers.php';
require_prof();

include __DIR__.'/../get_annee_en_cours.php';

$prof    = current_prof();
$agentId = (int)$prof['id'];

$classeId = get_current_classe();
if (!$classeId) {
    include __DIR__.'/../layout/header.php';
    include __DIR__.'/../layout/navbar.php';
    echo '<div class="container mt-3"><div class="alert alert-info">
            Aucune classe sélectionnée. <a href="/prof/switch_classe.php">Choisir une classe</a>
          </div></div>';
    include __DIR__.'/../layout/footer.php';
    exit;
}

// Meta classe
$classeMeta = current_classe_meta($con, $classeId);
$cycleId    = (int)($classeMeta['cycle_id'] ?? 0);

// 1) Périodes du cycle
$periodes = [];
if ($cycleId > 0) {
    $stmt = $con->prepare("
        SELECT id, CODE, libelle 
        FROM periodes 
        WHERE cycle_id = ? 
        ORDER BY ordre, id
    ");
    $stmt->bind_param('i', $cycleId);
    $stmt->execute();
    $periodes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// 2) Cours enseignés par ce prof dans la classe
$coursList = [];
$stmt = $con->prepare("
    SELECT co.id, co.intitule
    FROM cours co
    INNER JOIN affectation_prof_classe apc
      ON apc.cours_id = co.id
     AND apc.agent_id = ?
    WHERE co.classe_id = ?
    ORDER BY co.intitule
");
$stmt->bind_param('ii', $agentId, $classeId);
$stmt->execute();
$coursList = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// 3) Élèves de la classe
$eleves = [];
$stmt = $con->prepare("
    SELECT id, nom, postnom, prenom 
    FROM eleve 
    WHERE classe = ? 
    ORDER BY nom, postnom, prenom
");
$stmt->bind_param('i', $classeId);
$stmt->execute();
$eleves = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ------ Filtres ------
$periodeId = (int)($_GET['periode_id'] ?? 0);
$coursId   = (int)($_GET['cours_id'] ?? 0);
$eleveId   = (int)($_GET['eleve_id'] ?? 0);
$typeApp   = trim((string)($_GET['type_app'] ?? ''));

$msg = '';
$err = '';

// =================== SUPPRESSION D'UNE COTE ===================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $coteId = (int)($_POST['cote_id'] ?? 0);
    if ($coteId > 0) {
        $stmt = $con->prepare("DELETE FROM cahier_cotes WHERE id = ? AND classe_id = ?");
        $stmt->bind_param('ii', $coteId, $classeId);
        if ($stmt->execute()) {
            $msg = "Appréciation / cote supprimée avec succès.";
        } else {
            $err = "Erreur lors de la suppression : " . $stmt->error;
        }
        $stmt->close();
    }
}

// =================== REQUÊTE DE RÉCUPÉRATION DES COTES ===================
$wheres = ["cc.classe_id = ?", "cc.anneeScolaire = ?"];
$params = [$classeId, $anneeEnCours];
$types  = 'is';

if ($periodeId > 0) {
    $wheres[] = "cc.periode_id = ?";
    $params[] = $periodeId;
    $types   .= 'i';
}
if ($coursId > 0) {
    $wheres[] = "cc.cours_id = ?";
    $params[] = $coursId;
    $types   .= 'i';
}
if ($eleveId > 0) {
    $wheres[] = "cc.eleve_id = ?";
    $params[] = $eleveId;
    $types   .= 'i';
}
if ($typeApp !== '') {
    $wheres[] = "cc.type_app LIKE ?";
    $params[] = '%' . $typeApp . '%';
    $types   .= 's';
}

$sql = "
    SELECT 
        cc.id,
        cc.type_app,
        cc.points,
        cc.remarque,
        cc.created_at,
        e.nom, e.postnom, e.prenom,
        p.libelle AS periode_libelle, p.CODE AS periode_code,
        co.intitule AS cours_intitule
    FROM cahier_cotes cc
    INNER JOIN eleve e ON e.id = cc.eleve_id
    INNER JOIN periodes p ON p.id = cc.periode_id
    INNER JOIN cours co ON co.id = cc.cours_id
    WHERE " . implode(' AND ', $wheres) . "
    ORDER BY cc.created_at DESC, e.nom, e.postnom
";

$cotesList = [];
$stmt = $con->prepare($sql);
if ($stmt) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $cotesList = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// =================== EXPORT CSV ===================
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=cahier_des_cotes_'.date('Y-m-d').'.csv');
    
    $output = fopen('php://output', 'w');
    // En-tête du fichier CSV (BOM pour UTF-8 sous Excel)
    fputs($output, "\xEF\xBB\xBF");
    fputcsv($output, ['Date', 'Élève', 'Période', 'Cours', 'Type', 'Points', 'Remarque'], ';');

    foreach ($cotesList as $c) {
        $nomComplet = trim($c['nom'].' '.$c['postnom'].' '.$c['prenom']);
        fputcsv($output, [
            $c['created_at'],
            $nomComplet,
            $c['periode_code'],
            $c['cours_intitule'],
            $c['type_app'] ?? '—',
            $c['points'] !== null ? $c['points'] : '—',
            $c['remarque'] ?? '—'
        ], ';');
    }
    fclose($output);
    exit;
}

include __DIR__.'/../layout/header.php';
include __DIR__.'/../layout/navbar.php';
?>

<div class="container-fluid mt-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h5 mb-0">Visualisation du cahier des cotes</h1>
        <div>
            <a href="cahier_des_cotes.php" class="btn btn-outline-primary btn-sm">
                ➕ Saisir de nouvelles cotes
            </a>
            <?php if (!empty($cotesList)): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn btn-success btn-sm">
                📥 Exporter en CSV
            </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endif; ?>

    <!-- Barre de Filtres -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="get" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-bold">Période</label>
                    <select name="periode_id" class="form-select" onchange="this.form.submit()">
                        <option value="0">— Toutes les périodes —</option>
                        <?php foreach ($periodes as $p): ?>
                        <option value="<?= (int)$p['id'] ?>" <?= $p['id'] === $periodeId ? 'selected' : '' ?>>
                            <?= e($p['libelle']) ?> (<?= e($p['CODE']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-bold">Cours</label>
                    <select name="cours_id" class="form-select" onchange="this.form.submit()">
                        <option value="0">— Tous les cours —</option>
                        <?php foreach ($coursList as $co): ?>
                        <option value="<?= (int)$co['id'] ?>" <?= $co['id'] === $coursId ? 'selected' : '' ?>>
                            <?= e($co['intitule']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-bold">Élève</label>
                    <select name="eleve_id" class="form-select" onchange="this.form.submit()">
                        <option value="0">— Tous les élèves —</option>
                        <?php foreach ($eleves as $e): ?>
                        <?php $nom = trim($e['nom'].' '.$e['postnom'].' '.$e['prenom']); ?>
                        <option value="<?= (int)$e['id'] ?>" <?= $eleveId === (int)$e['id'] ? 'selected' : '' ?>>
                            <?= e($nom) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-bold">Type d'appréciation</label>
                    <div class="input-group">
                        <input type="text" name="type_app" class="form-control" value="<?= e($typeApp) ?>"
                            placeholder="Ex: Devoir, Examen...">
                        <button type="submit" class="btn btn-primary">🔍</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Tableau d'affichage des Cotes -->
    <div class="card mb-3">
        <div class="card-header bg-light d-flex justify-content-between align-middle">
            <span><strong>Liste des enregistrements</strong> (<?= count($cotesList) ?>
                cote<?= count($cotesList) > 1 ? 's' : '' ?> trouvée<?= count($cotesList) > 1 ? 's' : '' ?>)</span>
            <?php if ($periodeId > 0 || $coursId > 0 || $eleveId > 0 || $typeApp !== ''): ?>
            <a href="stastique_de_mes_codes.php"
                class="btn btn-link btn-sm text-decoration-none p-0">Réinitialiser les filtres</a>
            <?php endif; ?>
        </div>
        <div class="card-body p-0">
            <?php if (empty($cotesList)): ?>
            <div class="p-3 text-muted text-center">Aucune cote enregistrée pour ces critères de recherche.</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Date</th>
                            <th>Élève</th>
                            <th>Période</th>
                            <th>Cours</th>
                            <th>Type</th>
                            <th class="text-center">Points</th>
                            <th>Remarque</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cotesList as $c): ?>
                        <tr>
                            <td><small><?= e(date('d/m/Y H:i', strtotime($c['created_at']))) ?></small></td>
                            <td><strong><?= e(trim($c['nom'].' '.$c['postnom'].' '.$c['prenom'])) ?></strong></td>
                            <td><span class="badge bg-info text-dark"><?= e($c['periode_code']) ?></span></td>
                            <td><?= e($c['cours_intitule']) ?></td>
                            <td><?= e($c['type_app'] ?: '—') ?></td>
                            <td class="text-center fw-bold">
                                <?= $c['points'] !== null ? number_format((float)$c['points'], 2, ',', ' ') : '—' ?>
                            </td>
                            <td><small><?= e($c['remarque'] ?: '—') ?></small></td>
                            <td class="text-center">
                                <form method="post"
                                    onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette cote ?');"
                                    class="d-inline">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="cote_id" value="<?= (int)$c['id'] ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-sm" title="Supprimer">
                                        🗑️
                                    </button>
                                </form>
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