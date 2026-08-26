<?php
// Inclusion du fichier de connexion MySQLi
require_once __DIR__ . '/includes/db.php'; 

/**
 * Récupère l'année scolaire active ('encours')
 * 
 * @param mysqli $conn L'instance de connexion MySQLi
 * @return string L'année scolaire (ex: '2025-2026') ou une valeur par défaut
 */
function getAnneeScolaireEnCours(mysqli $conn): string {
    try {
        $sql = "SELECT annee_scolaire 
                FROM annee_scolaire 
                WHERE status = 'encours' 
                LIMIT 1";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            if (!empty($row['annee_scolaire'])) {
                return $row['annee_scolaire'];
            }
        }
        
        // Fallback si aucune année n'est marquée 'encours'
        return '2025-2026';

    } catch (Exception $e) {
        error_log("Erreur lors de la récupération de l'année scolaire : " . $e->getMessage());
        return '2025-2026';
    }
}

// --- EXEMPLE D'UTILISATION ---

// On passe la variable $con définie dans includes/db.php
$anneeEnCours = getAnneeScolaireEnCours($con);

// Stockage en session pour réutilisation facile dans vos pages
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['anneeScolaire'] = $anneeEnCours;

// Optionnel : afficher pour tester
// echo "Année scolaire en cours : " . $anneeEnCours;