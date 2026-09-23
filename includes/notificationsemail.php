<?php
// includes/notifications_email.php
declare(strict_types=1);

if (!function_exists('sendMailToManagers')) {
    /**
     * Envoie un mail natif aux gestionnaires (Directeur + Responsable du service du prof)
     *
     * @param mysqli $con Connexion MySQLi
     * @param int $profId ID de l'enseignant (table agent)
     * @param string $sujet Sujet du message
     * @param string $contenu Contenu détaillé
     * @return bool
     */
    function sendMailToManagers(mysqli $con, int $profId, string $sujet, string $contenu): bool
    {
        try {
            // 1. Récupération du service du prof
            $serviceProf = null;
            $nomProf = 'Inconnu';

            $stmt = $con->prepare("SELECT service, CONCAT(nom, ' ', prenom) AS nom_prof FROM agent WHERE id = ?");
            $stmt->bind_param('i', $profId);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($res) {
                $serviceProf = $res['service'];
                $nomProf = $res['nom_prof'];
            }

            // 2. Définition des rôles selon la section du prof
            $roles = ["'directeur'"]; // Reçoit toujours

            if ($serviceProf === 'PRIM & MAT') {
                $roles[] = "'primaire'";
            } elseif ($serviceProf === 'SEC & HUM') {
                $roles[] = "'secondaire-humainte'";
            }

            $rolesSql = implode(',', $roles);

            // 3. Récupération des adresses e-mail
            $emails = [];
            $sql = "SELECT email FROM users WHERE role IN ($rolesSql) AND email IS NOT NULL AND email != ''";
            $resMails = $con->query($sql);

            if ($resMails) {
                while ($row = $resMails->fetch_assoc()) {
                    $emails[] = $row['email'];
                }
            }

            if (empty($emails)) {
                return false;
            }

            // 4. Construction et envoi du mail natif
            $destinataires = implode(',', $emails);
            $headers  = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= "From: Notification Scolaire <no-reply@votre-ecole.com>" . "\r\n";

            $body = "
            <html>
            <body style='font-family: Arial, sans-serif; background-color: #f4f6f9; padding: 20px;'>
              <div style='max-width: 600px; background: #ffffff; padding: 20px; border-radius: 8px; border: 1px solid #ddd;'>
                <h3 style='color: #0d6efd; margin-top: 0;'>" . htmlspecialchars($sujet) . "</h3>
                <p><strong>Enseignant :</strong> " . htmlspecialchars($nomProf) . "</p>
                <p><strong>Service :</strong> " . htmlspecialchars((string)$serviceProf) . "</p>
                <div style='background: #f8f9fa; padding: 12px; border-left: 4px solid #0d6efd; margin: 15px 0;'>
                    " . nl2br(htmlspecialchars($contenu)) . "
                </div>
                <hr style='border: none; border-top: 1px solid #eee;'>
                <small style='color: #6c757d;'>Notification automatique de l'établissement.</small>
              </div>
            </body>
            </html>
            ";

            return mail($destinataires, $sujet, $body, $headers);

        } catch (Throwable $e) {
            error_log("Erreur d'envoi d'email : " . $e->getMessage());
            return false;
        }
    }
}