<?php
// /prof/doc_peda/mes_resumes.php
declare(strict_types=1);

require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/helpers.php';
require_prof();

include __DIR__.'/../get_annee_en_cours.php';

$prof    = current_prof();
$agentId = (int)($prof['id'] ?? 0);

// Récupération de tous les résumés créés par ce professeur
$stmtResumes = $con->prepare("
    SELECT 
        r.id AS resume_id,
        r.fiche_no,
        r.domaine,
        r.discipline,
        r.titre_lecon,
        r.type_lecon,
        r.competence_attendue,
        r.resume_texte,
        r.devoir,
        r.piece_jointe,
        jc.id AS journal_id,
        jc.jour_date,
        cl.description AS classe_nom,
        co.intitule AS cours_nom
    FROM resume_cours r
    INNER JOIN journal_classe jc ON jc.id = r.journal_id
    INNER JOIN classe cl ON cl.id = jc.classe_id
    INNER JOIN cours co ON co.id = jc.cours_id
    WHERE jc.prof_id = ?
    ORDER BY jc.jour_date DESC
");
$stmtResumes->bind_param('i', $agentId);
$stmtResumes->execute();
$lesResumes = $stmtResumes->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtResumes->close();

// Récupération des leçons du journal de classe pour le modal d'ajout
$stmtJournaux = $con->prepare("
    SELECT 
        jc.id AS journal_id,
        jc.jour_date,
        jc.matieres,
        cl.description AS classe_nom,
        co.intitule AS cours_nom
    FROM journal_classe jc
    INNER JOIN classe cl ON cl.id = jc.classe_id
    INNER JOIN cours co ON co.id = jc.cours_id
    WHERE jc.prof_id = ?
    ORDER BY jc.jour_date DESC
");
$stmtJournaux->bind_param('i', $agentId);
$stmtJournaux->execute();
$lesJournaux = $stmtJournaux->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtJournaux->close();

include __DIR__.'/../layout/header.php';
include __DIR__.'/../layout/navbar.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold m-0">📚 Mes Résumés de Cours</h4>
        <button type="button" class="btn btn-success fw-bold" data-bs-toggle="modal" data-bs-target="#modalSelectJournal">
            ➕ Créer un résumé
        </button>
    </div>

    <!-- TABLEAU DES RÉSUMÉS -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>N° Fiche</th>
                            <th>Date</th>
                            <th>Classe</th>
                            <th>Domaine / Discipline</th>
                            <th>Titre Leçon</th>
                            <th>Support / Pièce jointe</th>
                            <th class="text-end" style="min-width: 220px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($lesResumes)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">Aucun résumé créé pour le moment.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($lesResumes as $res): ?>
                                <tr>
                                    <td><span class="badge bg-secondary"><?= e($res['fiche_no']) ?></span></td>
                                    <td><?= date('d/m/Y', strtotime($res['jour_date'])) ?></td>
                                    <td><?= e($res['classe_nom']) ?></td>
                                    <td>
                                        <strong><?= e($res['domaine']) ?></strong><br>
                                        <small class="text-muted"><?= e($res['discipline']) ?></small>
                                    </td>
                                    <td>
                                        <?= e($res['titre_lecon']) ?><br>
                                        <span class="badge bg-info text-dark"><?= e($res['type_lecon']) ?></span>
                                    </td>
                                    <td>
                                        <?php if (!empty($res['piece_jointe'])): ?>
                                            <a href="/uploads/attachement_resume_cours/<?= e($res['piece_jointe']) ?>" target="_blank" class="btn btn-sm btn-success fw-bold">
                                                📎 Consulter le fichier
                                            </a>
                                        <?php else: ?>
                                            <span class="badge bg-light text-muted border">Texte uniquement</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <!-- Aperçu du contenu dans un modal -->
                                        <button type="button" class="btn btn-sm btn-primary text-white fw-bold me-1" data-bs-toggle="modal" data-bs-target="#modalViewResume<?= $res['resume_id'] ?>">
                                            👁️ Aperçu
                                        </button>
                                        
                                        <!-- Modifier -->
                                        <a href="cours_resume.php?journal=<?= $res['journal_id'] ?>" class="btn btn-sm btn-warning text-dark fw-bold">
                                            ✏️ Éditer
                                        </a>
                                    </td>
                                </tr>

                                <!-- MODAL DE LECTURE DU RÉSUMÉ -->
                                <div class="modal fade" id="modalViewResume<?= $res['resume_id'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                        <div class="modal-content">
                                            <div class="modal-header bg-light">
                                                <h5 class="modal-title fw-bold">
                                                    📄 <?= e($res['fiche_no']) ?> - <?= e($res['titre_lecon']) ?>
                                                </h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="row mb-3">
                                                    <div class="col-md-6">
                                                        <strong>Classe :</strong> <?= e($res['classe_nom']) ?><br>
                                                        <strong>Domaine :</strong> <?= e($res['domaine']) ?>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <strong>Discipline :</strong> <?= e($res['discipline']) ?><br>
                                                        <strong>Type :</strong> <?= e($res['type_lecon']) ?>
                                                    </div>
                                                </div>
                                                <hr>
                                                
                                                <div class="mb-3">
                                                    <h6 class="fw-bold">🎯 Compétence attendue :</h6>
                                                    <p class="bg-light p-2 rounded"><?= nl2br(e($res['competence_attendue'])) ?></p>
                                                </div>

                                                <div class="mb-3">
                                                    <h6 class="fw-bold">📝 Contenu du résumé :</h6>
                                                    <div class="p-3 bg-light border rounded">
                                                        <?= !empty($res['resume_texte']) ? nl2br(e($res['resume_texte'])) : '<em>Aucun texte saisi.</em>' ?>
                                                    </div>
                                                </div>

                                                <?php if (!empty($res['devoir'])): ?>
                                                    <div class="mb-3">
                                                        <h6 class="fw-bold">📌 Devoir / Exercice :</h6>
                                                        <p class="bg-light p-2 rounded"><?= e($res['devoir']) ?></p>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if (!empty($res['piece_jointe'])): ?>
                                                    <div class="mt-3 p-2 border border-success rounded text-center bg-light">
                                                        <p class="m-0 fw-bold text-success mb-2">Un fichier joint est associé à ce résumé :</p>
                                                        <a href="/uploads/attachement_resume_cours/<?= e($res['piece_jointe']) ?>" target="_blank" class="btn btn-success btn-sm fw-bold">
                                                            📥 Télécharger / Ouvrir la pièce jointe
                                                        </a>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="modal-footer">
                                                <a href="cours_resume.php?journal=<?= $res['journal_id'] ?>" class="btn btn-warning btn-sm fw-bold">
                                                    ✏️ Modifier cette fiche
                                                </a>
                                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Fermer</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- MODAL SÉLECTION DE LA LEÇON DEPUIS LE JOURNAL DE CLASSE -->
<div class="modal fade" id="modalSelectJournal" tabindex="-1" aria-labelledby="modalSelectJournalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form action="cours_resume.php" method="get">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalSelectJournalLabel">Sélectionner une leçon du Journal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if (empty($lesJournaux)): ?>
                        <div class="alert alert-warning m-0">Aucune entrée trouvée dans votre journal de classe. Saisissez d'abord une leçon dans votre journal.</div>
                    <?php else: ?>
                        <div class="mb-3">
                            <label for="journal_select" class="form-label fw-bold">Choisir le cours / la leçon :</label>
                            <select name="journal" id="journal_select" class="form-select form-select-lg" required>
                                <option value="" disabled selected>-- Choisir une leçon --</option>
                                <?php foreach ($lesJournaux as $j): ?>
                                    <option value="<?= $j['journal_id'] ?>">
                                        <?= date('d/m/Y', strtotime($j['jour_date'])) ?> | <?= e($j['classe_nom']) ?> - <?= e($j['cours_nom']) ?> : <?= e($j['matieres']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <?php if (!empty($lesJournaux)): ?>
                        <button type="submit" class="btn btn-primary fw-bold">Continuer ➡️</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__.'/../layout/footer.php'; ?>