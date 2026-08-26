<?php
// /prof/mon_profil.php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_prof();

$prof    = current_prof();
$agentId = (int)($prof['id'] ?? 0);

$msgSuccess = '';
$msgError   = '';

// 1) Traitement de la mise à jour des informations personnelles
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_update_info'])) {
    $email    = trim((string)($_POST['email'] ?? ''));
    $telephone = trim((string)($_POST['telephone'] ?? ''));

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msgError = "L'adresse email saisie n'est pas valide.";
    } else {
        $stmt = $con->prepare("
            UPDATE agent 
            SET email = ?, telephone = ? 
            WHERE id = ?
        ");
        $stmt->bind_param('ssi', $email, $telephone, $agentId);

        if ($stmt->execute()) {
            $msgSuccess = "Vos informations ont été mises à jour avec succès.";
            // Rafraîchir les données de la session
            $_SESSION['prof']['email'] = $email;
            $_SESSION['prof']['telephone'] = $telephone;
            $prof = current_prof();
        } else {
            $msgError = "Une erreur est survenue lors de la mise à jour.";
        }
        $stmt->close();
    }
}

// 2) Traitement du changement de mot de passe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_change_password'])) {
    $currentPassword = (string)($_POST['current_password'] ?? '');
    $newPassword     = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $msgError = "Veuillez remplir tous les champs du formulaire de mot de passe.";
    } elseif ($newPassword !== $confirmPassword) {
        $msgError = "Le nouveau mot de passe et la confirmation ne correspondent pas.";
    } elseif (strlen($newPassword) < 6) {
        $msgError = "Le nouveau mot de passe doit contenir au moins 6 caractères.";
    } else {
        // Vérification de l'ancien mot de passe en BDD
        $stmt = $con->prepare("SELECT password_hash FROM agent WHERE id = ?");
        $stmt->bind_param('i', $agentId);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $hashInDb = $res['password_hash'] ?? '';

        // Verification (supporte password_verify ou md5/sha1 selon votre legacy)
        if (password_verify($currentPassword, $hashInDb) || $hashInDb === md5($currentPassword)) {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);

            $updateStmt = $con->prepare("UPDATE agent SET password_hash = ? WHERE id = ?");
            $updateStmt->bind_param('si', $newHash, $agentId);

            if ($updateStmt->execute()) {
                $msgSuccess = "Votre mot de passe a été modifié avec succès.";
            } else {
                $msgError = "Erreur lors de la modification du mot de passe.";
            }
            $updateStmt->close();
        } else {
            $msgError = "L'actuel mot de passe est incorrect.";
        }
    }
}

include __DIR__ . '/../layout/header.php';
include __DIR__ . '/../layout/navbar.php';
?>

<div class="container py-3">
    <div class="row g-4">

        <!-- Colonne Gauche : Carte d'identité Enseignant -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 text-center p-3 mb-4">
                <div class="card-body">
                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 80px; height: 80px; font-size: 2rem; font-weight: bold;">
                        <?= strtoupper(substr($prof['nom'] ?? 'P', 0, 1)) ?>
                    </div>

                    <h5 class="fw-bold mb-1">
                        <?= e(trim(($prof['nom'] ?? '') . ' ' . ($prof['postnom'] ?? '') . ' ' . ($prof['prenom'] ?? ''))) ?>
                    </h5>
                    <p class="text-muted small mb-2">Enseignant / Agent ID #<?= (int)$prof['id'] ?></p>

                    <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill">
                        Compte Actif
                    </span>

                    <hr class="my-4">

                    <div class="text-start small">
                        <div class="mb-2">
                            <strong>Matricule :</strong> <?= e($prof['matricule'] ?? 'N/A') ?>
                        </div>
                        <div class="mb-2">
                            <strong>Genre :</strong> <?= e($prof['sexe'] ?? 'Non précisé') ?>
                        </div>
                        <div>
                            <strong>Fonction :</strong> <?= e($prof['fonction'] ?? 'Professeur') ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Colonne Droite : Formulaires de Modification -->
        <div class="col-lg-8">

            <?php if (!empty($msgSuccess)): ?>
                <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
                    <?= e($msgSuccess) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($msgError)): ?>
                <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
                    <?= e($msgError) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
                </div>
            <?php endif; ?>

            <!-- Formulaire 1 : Informations personnelles -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 fw-bold">
                    ⚙️ Informations personnelles
                </div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="action_update_info" value="1">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Nom</label>
                                <input type="text" class="form-control bg-light" value="<?= e($prof['nom'] ?? '') ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Prénom / Postnom</label>
                                <input type="text" class="form-control bg-light" value="<?= e(trim(($prof['postnom'] ?? '') . ' ' . ($prof['prenom'] ?? ''))) ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Adresse Email</label>
                                <input type="email" name="email" class="form-control" value="<?= e($prof['email'] ?? '') ?>" placeholder="exemple@ecole.com">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Numéro de Téléphone</label>
                                <input type="text" name="telephone" class="form-control" value="<?= e($prof['telephone'] ?? '') ?>" placeholder="+243 ...">
                            </div>
                        </div>

                        <div class="mt-4 text-end">
                            <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Formulaire 2 : Sécurité / Mot de passe -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 fw-bold text-danger">
                    🔒 Sécurité & Mot de passe
                </div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="action_change_password" value="1">

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Mot de passe actuel</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Nouveau mot de passe</label>
                                <input type="password" name="new_password" class="form-control" minlength="6" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Confirmer le mot de passe</label>
                                <input type="password" name="confirm_password" class="form-control" minlength="6" required>
                            </div>
                        </div>

                        <div class="text-end">
                            <button type="submit" class="btn btn-outline-danger">Changer le mot de passe</button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<?php include __DIR__ . '/../layout/footer.php'; ?>