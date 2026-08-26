<?php
// prof/quiz_list.php
declare(strict_types=1);

require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/helpers.php';
require_prof();

include __DIR__.'/get_annee_en_cours.php';

$prof    = current_prof();
$agentId = (int)$prof['id'];

// Pas de cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// ----------------------
// Filtres & Année Scolaire
// ----------------------
$anneeScolaire = $_GET['annee_scolaire'] ?? ($anneeEnCours ?? '');

$status = $_GET['statut'] ?? '';
$allowedStatus = ['', 'brouillon', 'en attente', 'approuvé', 'rejeter', 'à revoir'];
if (!in_array($status, $allowedStatus, true)) {
    $status = '';
}

$classeFilter  = isset($_GET['classe_id'])   ? (int)$_GET['classe_id']   : 0;
$periodeFilter = isset($_GET['periode_id']) ? (int)$_GET['periode_id'] : 0;

$dateFrom = $_GET['date_from'] ?? '';
$dateTo   = $_GET['date_to'] ?? '';

// ----------------------
// Liste des années scolaires enregistrées (Archives)
// ----------------------
$anneesDisponibles = [];
$stmtA = $con->prepare("
    SELECT DISTINCT anneeScolaire 
    FROM quiz 
    WHERE agent_id = ? AND anneeScolaire IS NOT NULL AND anneeScolaire != ''
    ORDER BY anneeScolaire DESC
");
if ($stmtA) {
    $stmtA->bind_param('i', $agentId);
    $stmtA->execute();
    $resA = $stmtA->get_result();
    if ($resA) {
        $anneesDisponibles = array_column($resA->fetch_all(MYSQLI_ASSOC), 'anneeScolaire');
    }
    $stmtA->close();
}

if (!empty($anneeEnCours) && !in_array($anneeEnCours, $anneesDisponibles, true)) {
    array_unshift($anneesDisponibles, $anneeEnCours);
}

// ----------------------
// Classes & Périodes
// ----------------------
$myClasses = classes_of_agent($con, $agentId);

$periodes = [];
$stmtP = $con->prepare("
    SELECT DISTINCT periode_id
    FROM quiz
    WHERE agent_id = ? AND periode_id IS NOT NULL
    ORDER BY periode_id
");
if ($stmtP) {
    $stmtP->bind_param('i', $agentId);
    $stmtP->execute();
    $resP = $stmtP->get_result();
    if ($resP) {
        $periodes = $resP->fetch_all(MYSQLI_ASSOC);
    }
    $stmtP->close();
}

// ----------------------
// Requête SQL
// ----------------------
$wheres = ["q.agent_id = ?"];
$params = [$agentId];
$types   = 'i';

if (!empty($anneeScolaire)) {
    $wheres[] = "q.anneeScolaire = ?";
    $params[] = $anneeScolaire;
    $types   .= 's';
}
if ($status !== '') {
    $wheres[] = "q.statut = ?";
    $params[] = $status;
    $types   .= 's';
}
if ($classeFilter > 0) {
    $wheres[] = "qc.classe_id = ?";
    $params[] = $classeFilter;
    $types   .= 'i';
}
if ($periodeFilter > 0) {
    $wheres[] = "q.periode_id = ?";
    $params[] = $periodeFilter;
    $types   .= 's';
}
if (!empty($dateFrom)) {
    $wheres[] = "DATE(q.created_at) >= ?";
    $params[] = $dateFrom;
    $types   .= 's';
}
if (!empty($dateTo)) {
    $wheres[] = "DATE(q.created_at) <= ?";
    $params[] = $dateTo;
    $types   .= 's';
}

$sql = "
    SELECT 
        q.id,
        q.type_quiz,
        q.format,
        q.titre,
        q.description,
        q.statut,
        q.date_limite,
        q.created_at,
        q.periode_id,
        q.anneeScolaire,
        COALESCE(co.intitule, 'Général / Non spécifié') AS nom_cours,

        GROUP_CONCAT(
            DISTINCT CONCAT(c.description, ' (', cy.description, ')')
            SEPARATOR ', '
        ) AS classes_cycles,

        (SELECT COUNT(*) FROM quiz_question qq WHERE qq.quiz_id = q.id) AS nb_questions,
        (SELECT COUNT(*) FROM quiz_attachment qa WHERE qa.quiz_id = q.id) AS nb_pj,
        (SELECT COUNT(*) FROM quiz_submission qs WHERE qs.quiz_id = q.id) AS nb_submissions

    FROM quiz q
    LEFT JOIN cours co ON co.id = q.cours_id
    LEFT JOIN quiz_classe qc ON qc.quiz_id = q.id
    LEFT JOIN classe c ON c.id = qc.classe_id
    LEFT JOIN cycle cy ON cy.id = c.cycle
    WHERE ".implode(' AND ', $wheres)."
    GROUP BY q.id
    ORDER BY nom_cours ASC, q.created_at DESC
";

$stmt = $con->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res  = $stmt->get_result();
$rawRows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();

// ----------------------
// Regroupement par cours pour l'accordéon
// ----------------------
$groupedQuiz = [];
foreach ($rawRows as $r) {
    $coursKey = !empty($r['nom_cours']) ? $r['nom_cours'] : 'Général / Non spécifié';
    $groupedQuiz[$coursKey][] = $r;
}

include __DIR__.'/layout/header.php';
include __DIR__.'/layout/navbar.php';
?>

<style>
.quiz-card {
    transition: all 0.2s ease;
    border-radius: 12px;
}

.quiz-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
}

.accordion-button:not(.collapsed) {
    background-color: #e7f1ff;
    color: #0c63e4;
    font-weight: 600;
}
</style>

<div class="container-fluid py-3">

    <!-- HEADER -->
    <!-- <div class="card shadow-sm border-0 mb-3">
        <div class="card-body d-flex align-items-center flex-wrap gap-2">
            <h2 class="h5 mb-0 fw-bold">
                <i class="bi bi-journal-bookmark-fill me-1"></i> Mes quiz
            </h2>
            <span class="badge bg-primary fs-6 ms-2">
                Année Scolaire : <?= e($anneeScolaire) ?>
            </span>
            <a class="btn btn-primary btn-sm ms-auto px-3" href="/prof/quiz_create.php">
                <i class="bi bi-plus-circle"></i> Créer un quiz
            </a>
        </div>
    </div> -->

    <!-- FILTRES -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body d-flex align-items-center flex-wrap gap-2">
            <h2 class="h5 mb-0 fw-bold">
                <i class="bi bi-journal-bookmark-fill me-1"></i> Mes quiz
            </h2>
            <span class="badge bg-primary fs-6 ms-2">
                Année Scolaire : <?= e($anneeScolaire) ?>
            </span>
            <a class="btn btn-primary btn-sm ms-auto px-3" href="/prof/quiz_create.php">
                <i class="bi bi-plus-circle"></i> Créer un quiz
            </a>
        </div>

        <div class="card-body">
            <form class="row g-3" method="get" action="quiz_list.php">

                <!-- Filtre Année Scolaire -->
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-primary">Année Scolaire</label>
                    <select name="annee_scolaire" class="form-select form-select-sm" onchange="this.form.submit()">
                        <?php foreach ($anneesDisponibles as $a): ?>
                        <option value="<?= e($a) ?>" <?= $anneeScolaire === $a ? 'selected' : '' ?>>
                            <?= e($a) ?> <?= ($a === $anneeEnCours) ? '(En cours)' : '(Archive)' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label small">Statut</label>
                    <select name="statut" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">Tous</option>
                        <option value="brouillon" <?= $status === 'brouillon' ? 'selected' : '' ?>>Brouillon</option>
                        <option value="en attente" <?= $status === 'en attente' ? 'selected' : '' ?>>En attente</option>
                        <option value="approuvé" <?= $status === 'approuvé' ? 'selected' : '' ?>>Approuvé</option>
                        <option value="rejeter" <?= $status === 'rejeter' ? 'selected' : '' ?>>Rejeté</option>
                        <option value="à revoir" <?= $status === 'à revoir' ? 'selected' : '' ?>>À revoir</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label small">Classe</label>
                    <select name="classe_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="0">Toutes</option>
                        <?php foreach ($myClasses as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= $classeFilter === (int)$c['id'] ? 'selected' : '' ?>>
                            <?= e($c['description']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label small">Période</label>
                    <select name="periode_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="0">Toutes</option>
                        <?php foreach ($periodes as $p): ?>
                        <option value="<?= (int)$p['periode_id'] ?>"
                            <?= $periodeFilter === (int)$p['periode_id'] ? 'selected' : '' ?>>
                            Période <?= (int)$p['periode_id'] ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label small">Créé à partir du</label>
                    <input type="date" name="date_from" value="<?= e($dateFrom) ?>"
                        class="form-control form-control-sm">
                </div>

                <div class="col-md-2 d-flex align-items-end gap-1">
                    <button type="submit" class="btn btn-dark btn-sm w-100">
                        <i class="bi bi-funnel"></i> Filtrer
                    </button>
                    <a href="quiz_list.php" class="btn btn-outline-danger btn-sm w-100">
                        <i class="bi bi-arrow-counterclockwise"></i> Réinitialiser
                    </a>
                </div>

            </form>
        </div>
    </div>

    <!-- LISTE DES QUIZ AVEC ACCORDÉON (FERMÉ PAR DÉFAUT) -->
    <?php if (empty($groupedQuiz)): ?>
    <div class="alert alert-info shadow-sm text-center py-4">
        <i class="bi bi-info-circle fs-4 d-block mb-2"></i>
        Aucun quiz trouvé pour l'année scolaire <strong><?= e($anneeScolaire) ?></strong>.
    </div>
    <?php else: ?>

    <div class="accordion shadow-sm rounded border-0" id="accordionQuizByCours">
        <?php 
            $index = 0;
            foreach ($groupedQuiz as $nomCours => $quizzes): 
                $index++;
                $accordionId = "cours_collapse_" . $index;
                $headingId   = "cours_heading_" . $index;
            ?>
        <div class="accordion-item border-0 border-bottom">
            <h2 class="accordion-header" id="<?= $headingId ?>">
                <button class="accordion-button collapsed py-3" type="button" data-bs-toggle="collapse"
                    data-bs-target="#<?= $accordionId ?>" aria-expanded="false" aria-controls="<?= $accordionId ?>">
                    <i class="bi bi-book me-2 text-primary"></i>
                    <span class="fw-bold me-2"><?= e($nomCours) ?></span>
                    <span class="badge bg-secondary rounded-pill ms-auto me-3">
                        <?= count($quizzes) ?> Quiz disponibles
                    </span>
                </button>
            </h2>

            <div id="<?= $accordionId ?>" class="accordion-collapse collapse" aria-labelledby="<?= $headingId ?>"
                data-bs-parent="#accordionQuizByCours">
                <div class="accordion-body bg-light p-3">

                    <div class="row g-3">
                        <?php foreach ($quizzes as $q): ?>
                        <?php
                                    $badgeClass = match($q['statut']) {
                                        'brouillon'   => 'secondary',
                                        'en attente'  => 'warning',
                                        'approuvé'    => 'success',
                                        'rejeter'     => 'danger',
                                        'à revoir'    => 'info',
                                        default       => 'dark'
                                    };

                                    $badgeIcon = match($q['statut']) {
                                        'brouillon'   => 'bi-pencil',
                                        'en attente'  => 'bi-hourglass-split',
                                        'approuvé'    => 'bi-check-circle',
                                        'rejeter'     => 'bi-x-circle',
                                        'à revoir'    => 'bi-arrow-repeat',
                                        default       => 'bi-question-circle'
                                    };
                                    ?>

                        <div class="col-lg-3 col-md-6 col-sm-12">
                            <div class="card h-100 border-0 shadow-sm quiz-card">
                                <div class="card-body d-flex flex-column">

                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h5 class="h6 fw-semibold mb-0 text-truncate" style="max-width: 70%;"
                                            title="<?= e($q['titre']) ?>">
                                            <?= e($q['titre']) ?>
                                        </h5>

                                        <span
                                            class="badge bg-<?= $badgeClass ?> px-2 py-1 d-inline-flex align-items-center gap-1">
                                            <i class="bi <?= $badgeIcon ?>"></i>
                                            <small><?= ucfirst(e($q['statut'])) ?></small>
                                        </span>
                                    </div>

                                    <div class="small text-muted mb-3">
                                        <div>
                                            <strong>Classes :</strong>
                                            <span
                                                class="text-dark fw-medium"><?= e($q['classes_cycles'] ?? '— Aucune classe') ?></span>
                                        </div>
                                        <div>Type/Format : <?= e($q['type_quiz']) ?> — <?= e($q['format']) ?></div>
                                        <div class="text-secondary mt-1">
                                            📅 <?= e($q['created_at']) ?>
                                        </div>
                                    </div>

                                    <div
                                        class="d-flex justify-content-between text-center small mb-3 bg-white p-2 rounded border">
                                        <div>
                                            <div class="fw-bold text-dark"><?= (int)$q['nb_questions'] ?></div>
                                            <div class="text-muted" style="font-size: 0.72rem;">Questions</div>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark"><?= (int)$q['nb_pj'] ?></div>
                                            <div class="text-muted" style="font-size: 0.72rem;">PJ</div>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark"><?= (int)$q['nb_submissions'] ?></div>
                                            <div class="text-muted" style="font-size: 0.72rem;">Réponses</div>
                                        </div>
                                    </div>

                                    <div class="mt-auto">
                                        <a class="btn btn-outline-primary btn-sm w-100"
                                            href="/prof/quiz_view.php?id=<?= (int)$q['id'] ?>">
                                            <i class="bi bi-eye"></i> Voir
                                        </a>
                                    </div>

                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php endif; ?>

</div>

<?php include __DIR__.'/layout/footer.php'; ?>