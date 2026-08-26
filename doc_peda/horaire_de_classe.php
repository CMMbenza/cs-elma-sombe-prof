<?php
// /prof/doc_peda/horaire.php
declare(strict_types=1);

require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/helpers.php';
require_prof();

include __DIR__.'/../get_annee_en_cours.php';

$prof    = current_prof();
$agentId = (int)($prof['id'] ?? 0);

// Anti-cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// Filtres
$typeFilter = trim((string)($_GET['type'] ?? ''));
$jourFilter = trim((string)($_GET['jour'] ?? ''));

// Construction de la requête SQL
$sql = "
    SELECT 
        h.id, 
        h.type,
        h.jour_semaine,
        h.date_evenement,
        h.heure_debut,
        h.heure_fin, 
        CONCAT(c.description ,' - ', cy.description) AS classe_nom,
        co.intitule AS cours_nom
    FROM horaire h
    INNER JOIN classe c ON c.id = h.classe_id
    INNER JOIN cours co ON co.id = h.cours_id
    INNER JOIN cycle cy ON cy.id = c.cycle
    WHERE h.prof_id = ?
";

$params = [$agentId];
$types  = 'i';

if ($typeFilter !== '') {
    $sql .= " AND h.type = ?";
    $params[] = $typeFilter;
    $types .= 's';
}

if ($jourFilter !== '') {
    $sql .= " AND h.jour_semaine = ?";
    $params[] = $jourFilter;
    $types .= 's';
}

$sql .= " ORDER BY FIELD(h.jour_semaine, 'Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'), h.heure_debut ASC";

$stmt = $con->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$horaires = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

include __DIR__.'/../layout/header.php';
include __DIR__.'/../layout/navbar.php';
?>

<div class="container mt-3">
    <div class="d-flex justify-content-between align-items-center mb-3 d-print-none">
        <h1 class="h5 mb-0">Mon Horaire de Cours & Évaluations</h1>
        <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">🖨️ Imprimer</button>
    </div>

    <!-- Filtres -->
    <div class="card mb-3 d-print-none">
        <div class="card-body">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Type</label>
                    <select name="type" class="form-select" onchange="this.form.submit()">
                        <option value="">-- Tous les types --</option>
                        <option value="Cours" <?= $typeFilter === 'Cours' ? 'selected' : '' ?>>Cours</option>
                        <option value="Interrogation" <?= $typeFilter === 'Interrogation' ? 'selected' : '' ?>>
                            Interrogation</option>
                        <option value="Examen" <?= $typeFilter === 'Examen' ? 'selected' : '' ?>>Examen</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">Jour</label>
                    <select name="jour" class="form-select" onchange="this.form.submit()">
                        <option value="">-- Tous les jours --</option>
                        <?php foreach (['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'] as $j): ?>
                        <option value="<?= $j ?>" <?= $jourFilter === $j ? 'selected' : '' ?>><?= $j ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <a href="/prof/doc_peda/horaire.php" class="btn btn-light border w-100">Réinitialiser</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Affichage Horaire -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <?php if (empty($horaires)): ?>
            <div class="p-3 text-muted text-center">Aucun horaire programmé pour le moment.</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Jour / Date</th>
                            <th>Heure</th>
                            <th>Classe</th>
                            <th>Cours (Branche)</th>
                             <th>Type</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($horaires as $h): ?>
                        <tr>
                            <td>
                                <strong><?= e($h['jour_semaine']) ?></strong>
                                <?php if (!empty($h['date_evenement'])): ?>
                                <br><small
                                    class="text-muted"><?= date('d/m/Y', strtotime($h['date_evenement'])) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <?= date('H:i', strtotime($h['heure_debut'])) ?> -
                                    <?= date('H:i', strtotime($h['heure_fin'])) ?>
                                </span>
                            </td>
                            <td><?= e($h['classe_nom']) ?></td>
                            <td><strong><?= e($h['cours_nom']) ?></strong></td>
                            <td>
                                <?php if ($h['type'] === 'Examen'): ?>
                                <span class="badge bg-danger">Examen</span>
                                <?php elseif ($h['type'] === 'Interrogation'): ?>
                                <span class="badge bg-warning text-dark">Interrogation</span>
                                <?php else: ?>
                                <span class="badge bg-info text-dark">Cours</span>
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