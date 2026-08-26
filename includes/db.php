<?php
// prof/includes/db.php
declare(strict_types=1);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/**
 * Lit et charge le fichier .env dans $_ENV et $_SERVER
 */
function loadEnv(string $envPath): void {
    if (!file_exists($envPath)) {
        throw new Exception("Le fichier .env est introuvable à l'adresse : {$envPath}");
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        $line = trim($line);

        // Ignorer les commentaires et lignes vides
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }

        // Vérifier la présence du signe "="
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            
            $key = trim($key);
            $value = trim($value);

            // Retirer les guillemets simples ou doubles autour de la valeur si présents
            $value = trim($value, '"\'');

            // Enregistrer dans $_ENV et $_SERVER
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv("{$key}={$value}");
        }
    }
}

// Chargement du fichier .env
loadEnv(__DIR__ . '/../.env');

// Connexion MySQLi directe à partir des variables d'environnement
$con = new mysqli($_ENV['DB_HOST'], $_ENV['DB_USER'], $_ENV['DB_PASS'], $_ENV['DB_NAME']);
$con->set_charset('utf8mb4');