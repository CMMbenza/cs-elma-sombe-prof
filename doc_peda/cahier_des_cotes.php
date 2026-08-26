<?php
// /prof/doc_peda/cahier_des_cotes.php
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
    echo '<div class="container mt-3"><div class="alert alert-info">Aucune classe sélectionnée. <a href="/prof/switch_classe.php">Choisir une classe</a></div></div>';
    include __DIR__.'/../layout/footer.php';
    exit;
}

// Infos classe
$classeMeta = current_classe_meta($con, $classeId);
$cycleId    = (int)($classeMeta['cycle_id'] ?? 0);

// 1) Périodes du cycle
$periodes = [];
if ($cycleId > 0) {
    $stmt = $con->prepare("
        SELECT id, CODE, libelle, actif 
        FROM periodes 
        WHERE cycle_id = ? 
        ORDER BY ordre, id
    ");
    $stmt->bind_param('i', $cycleId);
    $stmt->execute();
    $periodes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// 2) Cours enseignés dans cette classe par ce prof
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

// ------ Filtres sélectionnés ------
$periodeId = (int)($_REQUEST['periode_id'] ?? 0);
$coursId   = (int)($_REQUEST['cours_id'] ?? 0);
$eleveId   = (int)($_REQUEST['eleve_id'] ?? 0);
$typeApp   = trim((string)($_REQUEST['type_app'] ?? ''));

// Valeurs par défaut
if (!$periodeId && $periodes)  $periodeId = (int)$periodes[0]['id'];
if (!$coursId && $coursList)   $coursId   = (int)$coursList[0]['id'];

$msg = '';
$err = '';

// =================== ENREGISTREMENT D'UNE NOUVELLE APPRÉCIATION ===================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $periodeId = (int)($_POST['periode_id'] ?? 0);
    $coursId   = (int)($_POST['cours_id'] ?? 0);
    $eleveId   = (int)($_POST['eleve_id'] ?? 0);
    $typeApp   = trim((string)($_POST['type_app'] ?? ''));
    $pointsRaw = trim((string)($_POST['points'] ?? ''));
    $remarque  = trim((string)($_POST['remarque'] ?? ''));

    if ($eleveId <= 0 || $coursId <= 0 || $periodeId <= 0) {
        $err = "Données manquantes (période, cours ou élève).";
    } elseif ($pointsRaw === '' && $remarque === '' && $typeApp === '') {
        $msg = "Aucune donnée significative saisie.";
    // --- AMÉLIORATION : Validation PHP des chiffres et du symbole / ---
    } elseif ($pointsRaw !== '' && !preg_match('#^[0-9/]+$#', $pointsRaw)) {
        $err = "Le champ points ne peut contenir que des chiffres et le caractère '/'.";
    } else {
        $pVal = null;
        if ($pointsRaw !== '') {
            if (str_contains($pointsRaw, '/')) {
                $parts = explode('/', $pointsRaw);
                $pVal = (float)$parts[0];
            } else {
                $pVal = (float)$pointsRaw;
            }
        }

        $sql = "
            INSERT INTO cahier_cotes (
                eleve_id, classe_id, cours_id, periode_id,
                type_app, points, remarque, anneeScolaire, created_at
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ";
        $stmt = $con->prepare($sql);
        $stmt->bind_param(
            'iiiisdss',
            $eleveId,
            $classeId,
            $coursId,
            $periodeId,
            $typeApp,
            $pVal,
            $remarque,
            $anneeEnCours
        );

        if ($stmt->execute()) {
            $msg = "Appréciation ajoutée avec succès.";
        } else {
            $err = "Erreur SQL : ".$stmt->error;
        }
        $stmt->close();
    }
}

// =================== RÉCAP DE LA CLASSE ===================
$classCotes = [];
if ($coursId > 0 && $periodeId > 0) {
    $wheres = ["cc.classe_id = ?", "cc.cours_id = ?", "cc.periode_id = ?"];
    $params = [$classeId, $coursId, $periodeId];
    $types  = 'iii';

    if ($typeApp !== '') {
        $wheres[] = "cc.type_app = ?";
        $params[] = $typeApp;
        $types   .= 's';
    }

    $sql = "
        SELECT 
            cc.eleve_id,
            e.nom, e.postnom, e.prenom,
            COUNT(*) AS nb_app,
            SUM(CASE WHEN cc.points IS NULL THEN 0 ELSE cc.points END) AS total_points,
            AVG(cc.points) AS moyenne_points
        FROM cahier_cotes cc
        INNER JOIN eleve e ON e.id = cc.eleve_id
        WHERE ".implode(' AND ', $wheres)."
        GROUP BY cc.eleve_id, e.nom, e.postnom, e.prenom
        ORDER BY e.nom, e.postnom, e.prenom
    ";

    $stmt = $con->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $classCotes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

// =================== HISTORIQUE ÉLÈVE ===================
$cotesEleve = [];
if ($eleveId > 0 && $coursId > 0 && $periodeId > 0) {
    $wheres = ["eleve_id = ?", "classe_id = ?", "cours_id = ?", "periode_id = ?"];
    $params = [$eleveId, $classeId, $coursId, $periodeId];
    $types  = 'iiii';

    if ($typeApp !== '') {
        $wheres[] = "type_app = ?";
        $params[] = $typeApp;
        $types   .= 's';
    }

    $sql = "
        SELECT type_app, points, remarque, created_at
        FROM cahier_cotes
        WHERE ".implode(' AND ', $wheres)."
        ORDER BY created_at DESC
    ";
    $stmt = $con->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $cotesEleve = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

include __DIR__.'/../layout/header.php';
include __DIR__.'/../layout/navbar.php';
?>

<div class="container-fluid mt-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h5 mb-0">Cahier des cotes</h1>
        <div>
            <a href="stastique_de_mes_codes.php" class="btn btn-outline-primary btn-sm">
                ➕ Consulter vos côtes
            </a>
        </div>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endif; ?>

    <!-- Filtres -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Période</label>
                    <select name="periode_id" class="form-select" onchange="this.form.submit()">
                        <?php foreach ($periodes as $p): ?>
                        <option value="<?= (int)$p['id'] ?>" <?= $p['id']===$periodeId?'selected':'' ?>>
                            <?= e($p['libelle']) ?> (<?= e($p['CODE']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Cours</label>
                    <select name="cours_id" class="form-select" onchange="this.form.submit()">
                        <?php foreach ($coursList as $co): ?>
                        <option value="<?= (int)$co['id'] ?>" <?= $co['id']===$coursId?'selected':'' ?>>
                            <?= e($co['intitule']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Élève (détails)</label>
                    <select name="eleve_id" class="form-select" onchange="this.form.submit()">
                        <option value="0" <?= $eleveId===0?'selected':'' ?>>— Tous (classe) —</option>
                        <?php foreach ($eleves as $e): ?>
                        <?php $nom = trim($e['nom'].' '.$e['postnom'].' '.$e['prenom']); ?>
                        <option value="<?= (int)$e['id'] ?>" <?= $eleveId===(int)$e['id']?'selected':'' ?>>
                            <?= e($nom) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Type d’appréciation</label>
                    <input type="text" name="type_app" class="form-control" value="<?= e($typeApp) ?>"
                        placeholder="Ex: Cahier, Application..." onblur="this.form.submit()">
                </div>
            </form>
        </div>
    </div>

    <!-- BANDEAU TYPE D'APPRÉCIATION GÉNÉRAL -->
    <div class="alert alert-secondary py-2 mb-3">
        <strong>Le type d’appréciation général utilisé :</strong>
        <?= $typeApp !== '' ? e($typeApp) : '<em>(Tous les types)</em>' ?>
    </div>

    <!-- RECAP CLASSE -->
    <div class="card mb-3">
        <div class="card-header">
            Récapitulatif de la classe — Période & cours sélectionnés
            (Type : <?= $typeApp !== '' ? e($typeApp) : 'Tous' ?>)
        </div>
        <div class="card-body p-0">
            <?php if (!$classCotes): ?>
            <div class="p-3 text-muted">Aucune appréciation enregistrée pour cette sélection.</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-bordered mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Élève</th>
                            <th class="text-center">Appréciations</th>
                            <th class="text-center">Total</th>
                            <th class="text-center">Moyenne</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($classCotes as $row): ?>
                        <tr>
                            <td><?= e(trim($row['nom'].' '.$row['postnom'].' '.$row['prenom'])) ?></td>
                            <td class="text-center"><?= (int)$row['nb_app'] ?></td>
                            <td class="text-center"><?= number_format((float)$row['total_points'], 2, ',', ' ') ?></td>
                            <td class="text-center">
                                <?= $row['moyenne_points'] !== null ? number_format((float)$row['moyenne_points'], 2, ',', ' ') : '—' ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- HISTORIQUE / DÉTAILS ÉLÈVE SÉLECTIONNÉ -->
    <?php if ($eleveId > 0): ?>
    <?php
        $selectedName = '';
        foreach ($eleves as $e) {
            if ((int)$e['id'] === $eleveId) {
                $selectedName = trim($e['nom'].' '.$e['postnom'].' '.$e['prenom']);
                break;
            }
        }
        ?>
    <div class="card mb-3">
        <div class="card-header">
            Détails des appréciations de l’élève sélectionné — <?= e($selectedName) ?>
        </div>
        <div class="card-body p-0">
            <?php if (!$cotesEleve): ?>
            <div class="p-3 text-muted">Aucune donnée enregistrée pour cet élève avec les filtres actuels.</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th class="text-center">Points</th>
                            <th>Remarque</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cotesEleve as $c): ?>
                        <tr>
                            <td><?= e($c['created_at']) ?></td>
                            <td><?= e($c['type_app'] ?? '—') ?></td>
                            <td class="text-center">
                                <?= $c['points'] !== null ? number_format((float)$c['points'], 2, ',', ' ') : '—' ?>
                            </td>
                            <td><?= e($c['remarque'] ?? '—') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- FORMULAIRE DE SAISIE -->
    <form method="post" class="card mb-3">
        <div class="card-header">Nouvelle appréciation</div>

        <input type="hidden" name="periode_id" value="<?= (int)$periodeId ?>">
        <input type="hidden" name="cours_id" value="<?= (int)$coursId ?>">
        <input type="hidden" name="eleve_id" value="<?= (int)$eleveId ?>">
        <input type="hidden" name="type_app" value="<?= e($typeApp) ?>">

        <div class="card-body row g-3">
            <div class="col-md-3">
                <label class="form-label">Élève</label>
                <select class="form-select" disabled>
                    <?php if ($eleveId === 0): ?>
                    <option>— Choisissez un élève en haut —</option>
                    <?php else: ?>
                    <option><?= e($selectedName) ?></option>
                    <?php endif; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">Points ex : 7/10</label>
                <!-- AMÉLIORATION : Restriction aux chiffres et au caractère / uniquement -->
                <input type="text" name="points" class="form-control" placeholder="Exemple : 7/10" pattern="[0-9/]+"
                    oninput="this.value = this.value.replace(/[^0-9/]/g, '');" <?= $eleveId===0 ? 'disabled' : '' ?>>
            </div>

            <div class="col-md-6">
                <label class="form-label">Remarque</label>
                <input type="text" name="remarque" class="form-control" placeholder="Remarque éventuelle..."
                    <?= $eleveId===0 ? 'disabled' : '' ?>>
            </div>
        </div>

        <div class="card-footer text-end">
            <button class="btn btn-primary" <?= $eleveId===0 ? 'disabled' : '' ?>>
                💾 Enregistrer
            </button>
        </div>
    </form>
</div>

<?php include __DIR__.'/../layout/footer.php'; ?>