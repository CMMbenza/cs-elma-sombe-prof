<?php
// /prof/doc_peda/fiche_de_suivi.php
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

// 2) Cours enseignés par ce prof dans cette classe
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
$eleveId   = (int)($_GET['eleve_id'] ?? 0);
$periodeId = (int)($_GET['periode_id'] ?? 0);
$coursId   = (int)($_GET['cours_id'] ?? 0);
$typeApp   = trim((string)($_GET['type_app'] ?? ''));

// Valeurs par défaut
if ($eleveId === 0 && !empty($eleves)) {
    $eleveId = (int)$eleves[0]['id'];
}
if ($periodeId === 0 && !empty($periodes)) {
    $periodeId = (int)$periodes[0]['id'];
}

// Détails élève actif
$selectedEleve = null;
foreach ($eleves as $e) {
    if ((int)$e['id'] === $eleveId) {
        $selectedEleve = $e;
        break;
    }
}

// =================== CALCUL DE LA MOYENNE ET RÉCUPÉRATION TRAVAUX + QUIZ ===================
$travaux = [];
$stats   = [
    'nb_travaux'   => 0,
    'total_points' => 0.0,
    'moyenne'      => null,
    'note_max'     => null,
    'note_min'     => null
];

if ($eleveId > 0 && $periodeId > 0) {
    
    // --- 1. Cahier des cotes ---
    $wheresCC = ["cc.eleve_id = ?", "cc.classe_id = ?", "cc.periode_id = ?", "cc.anneeScolaire = ?"];
    $paramsCC = [$eleveId, $classeId, $periodeId, $anneeEnCours];
    $typesCC  = 'iiis';

    if ($coursId > 0) {
        $wheresCC[] = "cc.cours_id = ?";
        $paramsCC[] = $coursId;
        $typesCC   .= 'i';
    }

    if ($typeApp !== '') {
        $wheresCC[] = "cc.type_app LIKE ?";
        $paramsCC[] = '%' . $typeApp . '%';
        $typesCC   .= 's';
    }

    $sqlCC = "
        SELECT 
            cc.id,
            cc.type_app,
            cc.points,
            cc.remarque,
            cc.created_at,
            co.intitule AS cours_intitule,
            cc.points AS max_ponderation,
            'cahier' AS source
        FROM cahier_cotes cc
        INNER JOIN cours co ON co.id = cc.cours_id
        LEFT JOIN cours_ponderations cp ON cp.cours_id = co.id AND cp.periode_id = cc.periode_id
        WHERE " . implode(' AND ', $wheresCC) . "
    ";

    $stmtCC = $con->prepare($sqlCC);
    if ($stmtCC) {
        $stmtCC->bind_param($typesCC, ...$paramsCC);
        $stmtCC->execute();
        $resCC = $stmtCC->get_result()->fetch_all(MYSQLI_ASSOC);
        $travaux = array_merge($travaux, $resCC);
        $stmtCC->close();
    }

    // --- 2. Quiz Submissions (Module Quiz) ---
    if ($typeApp === '' || stripos($typeApp, 'quiz') !== false) {
        $wheresQ = [
            "qs.eleve_id = ?", 
            "qc.classe_id = ?", 
            "qs.periode_id = ?", 
            "qs.anneeScolaire = ?"
        ];
        $paramsQ = [$eleveId, $classeId, $periodeId, $anneeEnCours];
        $typesQ  = 'iiis';

        if ($coursId > 0) {
            $wheresQ[] = "q.cours_id = ?";
            $paramsQ[] = $coursId;
            $typesQ   .= 'i';
        }

        $sqlQ = "
            SELECT 
                qs.id,
                CONCAT('Quiz (', q.type_quiz, ')') AS type_app,
                qs.note_totale AS points,
                CONCAT('Titre: ', q.titre) AS remarque,
                qs.date_submitted AS created_at,
                co.intitule AS cours_intitule,
                COALESCE(cp.points, 20) AS max_ponderation,
                'quiz' AS source
            FROM quiz_submission qs
            INNER JOIN quiz q ON q.id = qs.quiz_id
            INNER JOIN quiz_classe qc ON qc.quiz_id = q.id
            INNER JOIN cours co ON co.id = q.cours_id
            LEFT JOIN cours_ponderations cp ON cp.cours_id = co.id AND cp.periode_id = qs.periode_id
            WHERE " . implode(' AND ', $wheresQ) . "
        ";

        $stmtQ = $con->prepare($sqlQ);
        if ($stmtQ) {
            $stmtQ->bind_param($typesQ, ...$paramsQ);
            $stmtQ->execute();
            $resQ = $stmtQ->get_result()->fetch_all(MYSQLI_ASSOC);
            $travaux = array_merge($travaux, $resQ);
            $stmtQ->close();
        }
    }

    // Tri global par date récente
    usort($travaux, function ($a, $b) {
        return strtotime($b['created_at']) <=> strtotime($a['created_at']);
    });

    // Calcul des statistiques
    $validPoints = [];
    foreach ($travaux as $t) {
        if ($t['points'] !== null) {
            $val = (float)$t['points'];
            $validPoints[] = $val;
            $stats['total_points'] += $val;
        }
    }

    $stats['nb_travaux'] = count($validPoints);
    if ($stats['nb_travaux'] > 0) {
        $stats['moyenne']  = $stats['total_points'] / $stats['nb_travaux'];
        $stats['note_max'] = max($validPoints);
        $stats['note_min'] = min($validPoints);
    }
}

include __DIR__.'/../layout/header.php';
include __DIR__.'/../layout/navbar.php';
?>

<div class="container mt-3">
    <div class="d-flex justify-content-between align-items-center mb-3 d-print-none">
        <h1 class="h5 mb-0">Fiche de suivi & Calcul de moyenne</h1>
        <div>
            <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
                🖨️ Imprimer la fiche
            </button>
            <a href="/prof/doc_peda/cahier_des_cotes.php" class="btn btn-primary btn-sm">
                ➕ Saisir des cotes
            </a>
        </div>
    </div>

    <!-- Filtres -->
    <div class="card mb-3 d-print-none">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label fw-bold">1. Élève</label>
                    <select name="eleve_id" class="form-select" onchange="this.form.submit()">
                        <?php foreach ($eleves as $e): ?>
                        <?php $nom = trim($e['nom'].' '.$e['postnom'].' '.$e['prenom']); ?>
                        <option value="<?= (int)$e['id'] ?>" <?= $eleveId === (int)$e['id'] ? 'selected' : '' ?>>
                            <?= e($nom) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-bold">2. Période</label>
                    <select name="periode_id" class="form-select" onchange="this.form.submit()">
                        <?php foreach ($periodes as $p): ?>
                        <option value="<?= (int)$p['id'] ?>" <?= $p['id'] === $periodeId ? 'selected' : '' ?>>
                            <?= e($p['libelle']) ?> (<?= e($p['CODE']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-bold">3. Cours</label>
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
                    <label class="form-label fw-bold">4. Type</label>
                    <input type="text" name="type_app" class="form-control" value="<?= e($typeApp) ?>"
                        placeholder="Ex: Devoir, Quiz..." onblur="this.form.submit()">
                </div>
            </form>
        </div>
    </div>

    <?php if ($selectedEleve): ?>
    <?php $nomEleve = trim($selectedEleve['nom'].' '.$selectedEleve['postnom'].' '.$selectedEleve['prenom']); ?>

    <!-- Synthèse de la moyenne -->
    <div class="card mb-3">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <span class="fw-bold">Fiche individuelle de l'élève : <?= e($nomEleve) ?></span>
            <span class="badge bg-light text-dark">Année : <?= e($anneeEnCours) ?></span>
        </div>
        <div class="card-body">
            <div class="row text-center g-3">
                <div class="col-md-3">
                    <div class="p-3 border rounded bg-light">
                        <small class="text-muted d-block fw-bold text-uppercase">Moyenne Générale</small>
                        <span class="fs-3 fw-bold text-primary">
                            <?= $stats['moyenne'] !== null ? number_format($stats['moyenne'], 2, ',', ' ') : '—' ?>
                        </span>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-3 border rounded bg-light">
                        <small class="text-muted d-block fw-bold text-uppercase">Évaluations Réalisées</small>
                        <span class="fs-3 fw-bold text-dark"><?= $stats['nb_travaux'] ?></span>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-3 border rounded bg-light">
                        <small class="text-muted d-block fw-bold text-uppercase">Note Maximale</small>
                        <span class="fs-3 fw-bold text-success">
                            <?= $stats['note_max'] !== null ? number_format($stats['note_max'], 2, ',', ' ') : '—' ?>
                        </span>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-3 border rounded bg-light">
                        <small class="text-muted d-block fw-bold text-uppercase">Note Minimale</small>
                        <span class="fs-3 fw-bold text-danger">
                            <?= $stats['note_min'] !== null ? number_format($stats['note_min'], 2, ',', ' ') : '—' ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tableau détaillé -->
    <div class="card">
        <div class="card-header bg-light">
            <strong>Détails des cotes et résultats de quiz</strong>
        </div>
        <div class="card-body p-0">
            <?php if (empty($travaux)): ?>
            <div class="p-3 text-muted text-center">
                Aucun travail ou quiz répertorié pour cet élève avec les filtres actuels.
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Cours</th>
                            <th>Type</th>
                            <th class="text-center">Note / Poncération</th>
                            <th>Détails</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($travaux as $t): ?>
                        <tr>
                            <td><?= e(date('d/m/Y', strtotime($t['created_at']))) ?></td>
                            <td><strong><?= e($t['cours_intitule']) ?></strong></td>
                            <td>
                                <?php if (($t['source'] ?? '') === 'quiz'): ?>
                                <span class="badge bg-info text-dark">🎯 <?= e($t['type_app']) ?></span>
                                <?php else: ?>
                                <span class="badge bg-secondary"><?= e($t['type_app'] ?: 'Devoir') ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center fw-bold fs-6">
                                <?php if ($t['points'] !== null): ?>
                                <!-- <?= number_format((float)$t['points'], 2, ',', ' ') ?>/ -->
                                <small class="text">
                                    <?= $t['max_ponderation'] ?></small>
                                <?php else: ?>
                                <span class="text-muted">En attente</span>
                                <?php endif; ?>
                            </td>
                            <td><?= e($t['remarque'] ?: '—') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__.'/../layout/footer.php'; ?>