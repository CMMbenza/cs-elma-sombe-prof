<?php
// prof/layout/notification.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (ob_get_length()) {
    ob_clean();
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

$prof = current_prof();
$profId = $prof['id'] ?? null;

if (!$profId) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Non autorisé']);
    exit();
}

try {
    $items = [];
    $totalUnread = 0;

    // Helper pour formater le titre et la couleur de badge selon le statut
    $getStatusMeta = function (string $statut): array {
        return match (strtolower(trim($statut))) {
            'approuver', 'approuvé', 'valide', 'validé' => [
                'label' => 'approuvé(s)',
                'badge' => 'success'
            ],
            'en attente', 'attente', 'pending' => [
                'label' => 'en attente',
                'badge' => 'warning'
            ],
            'à revoir', 'a revoir' => [
                'label' => 'à revoir',
                'badge' => 'info'
            ],
            'rejeter', 'rejeté' => [
                'label' => 'rejeté(s)',
                'badge' => 'danger'
            ],
            default => [
                'label' => $statut,
                'badge' => 'secondary'
            ]
        };
    };

    // 1. STATUTS QUIZ
    $stmtQ = $con->prepare("
        SELECT statut, COUNT(*) AS total 
        FROM quiz 
        WHERE agent_id = ? 
        GROUP BY statut
    ");
    $stmtQ->bind_param('i', $profId);
    $stmtQ->execute();
    $resQ = $stmtQ->get_result();

    while ($row = $resQ->fetch_assoc()) {
        $count = (int)$row['total'];
        if ($count > 0) {
            $meta = $getStatusMeta($row['statut']);
            $items[] = [
                'type'        => 'quiz',
                'statut'      => $row['statut'],
                'titre'       => 'Quiz : ' . ucfirst($meta['label']),
                'message'     => "Vous avez <strong>{$count}</strong> quiz {$meta['label']}.",
                'badge'       => $count,
                'badge_style' => $meta['badge'],
                'lien'        => '/prof/quiz_list.php'
            ];
            $totalUnread += $count;
        }
    }

    // 2. STATUTS JOURNAUX DE CLASSE
    $stmtJ = $con->prepare("
        SELECT statut, COUNT(*) AS total 
        FROM journal_classe 
        WHERE prof_id = ? 
        GROUP BY statut
    ");
    $stmtJ->bind_param('i', $profId);
    $stmtJ->execute();
    $resJ = $stmtJ->get_result();

    while ($row = $resJ->fetch_assoc()) {
        $count = (int)$row['total'];
        if ($count > 0) {
            $meta = $getStatusMeta($row['statut']);
            $items[] = [
                'type'        => 'journal',
                'statut'      => $row['statut'],
                'titre'       => 'Journal : ' . ucfirst($meta['label']),
                'message'     => "Vous avez <strong>{$count}</strong> journal(x) {$meta['label']}.",
                'badge'       => $count,
                'badge_style' => $meta['badge'],
                'lien'        => '/prof/doc_peda/journal_de_classe.php'
            ];
            $totalUnread += $count;
        }
    }

    // 3. ANNONCES RÉCENTES (< 5 jours)
    $stmtA = $con->prepare("
        SELECT COUNT(*) 
        FROM annonces 
        WHERE (dest_type IN ('tous', 'profs') OR (dest_type = 'user' AND dest_id = ?))
          AND created_at >= NOW() - INTERVAL 5 DAY
    ");
    $stmtA->bind_param('i', $profId);
    $stmtA->execute();
    $resA = $stmtA->get_result();
    $countAnnonces = (int)$resA->fetch_row()[0];

    if ($countAnnonces > 0) {
        $items[] = [
            'type'        => 'annonce',
            'titre'       => 'Annonces récentes',
            'message'     => "Vous avez <strong>{$countAnnonces}</strong> annonce(s) reçue(s) ces 5 derniers jours.",
            'badge'       => $countAnnonces,
            'badge_style' => 'primary',
            'lien'        => '/prof/annonces.php'
        ];
        $totalUnread += $countAnnonces;
    }

    echo json_encode([
        'status' => 'success',
        'total'  => $totalUnread,
        'items'  => $items
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
exit();