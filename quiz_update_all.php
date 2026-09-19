<?php
declare(strict_types=1);

header('Content-Type: application/json');

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__.'/includes/db.php';
require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/helpers.php';

require_prof();

try {

    $raw = file_get_contents('php://input');

    if (!$raw) {
        throw new Exception("Aucune donnée reçue");
    }

    $data = json_decode($raw, true);

    if (!$data) {
        throw new Exception("JSON invalide");
    }

    $quizId = (int)($data['quiz_id'] ?? 0);
    $prof   = current_prof();
    $agentId = (int)$prof['id'];

    if ($quizId <= 0) {
        throw new Exception("Quiz invalide");
    }

    // 🔒 vérifier propriétaire
    $stmt = $con->prepare("SELECT id, format FROM quiz WHERE id=? AND agent_id=?");
    $stmt->bind_param('ii', $quizId, $agentId);
    $stmt->execute();

    $quizData = $stmt->get_result()->fetch_assoc();

    if (!$quizData) {
        throw new Exception("Accès refusé");
    }

    $quizFormat = $quizData['format'];

    // 🔥 START TRANSACTION
    $con->begin_transaction();

    /* ==========================
    UPDATE CLASSES
    ========================== */

    $con->query("DELETE FROM quiz_classe WHERE quiz_id = $quizId");

    if (!empty($data['classe_ids'])) {

        $stmtC = $con->prepare("
            INSERT INTO quiz_classe (quiz_id, classe_id)
            VALUES (?, ?)
        ");

        foreach ($data['classe_ids'] as $cid) {

            $cid = (int)$cid;

            $stmtC->bind_param('ii', $quizId, $cid);

            if (!$stmtC->execute()) {
                throw new Exception("Erreur insertion classe");
            }
        }

        $stmtC->close();
    }

    /* ==========================
       UPDATE QUIZ (AVEC CREATED_AT ET COURS_ID)
    ========================== */
    $coursId   = (int)($data['cours_id'] ?? 0);
    $createdAt = !empty($data['created_at']) ? date('Y-m-d H:i:s', strtotime($data['created_at'])) : date('Y-m-d H:i:s');
    $dateLimite = !empty($data['date_limite']) ? $data['date_limite'] : null;

    $stmt = $con->prepare("
        UPDATE quiz 
        SET cours_id=?, description=?, type_quiz=?, created_at=?, date_limite=?
        WHERE id=?
    ");

    $stmt->bind_param(
        'issssi',
        $coursId,
        $data['description'],
        $data['type_quiz'],
        $createdAt,
        $dateLimite,
        $quizId
    );

    $stmt->execute();

    /* ==========================
       CLEAN DB
    ========================== */
    $con->query("
        DELETE FROM quiz_question_keyword 
        WHERE question_id IN (
            SELECT id FROM quiz_question WHERE quiz_id=$quizId
        )
    ");

    $con->query("
        DELETE FROM quiz_choice 
        WHERE question_id IN (
            SELECT id FROM quiz_question WHERE quiz_id=$quizId
        )
    ");

    $con->query("
        DELETE FROM quiz_question 
        WHERE quiz_id=$quizId
    ");

    /* ==========================
       INSERT QUESTIONS
    ========================== */
    $order = 1;

    if (!empty($data['questions']) && is_array($data['questions'])) {
        foreach ($data['questions'] as $q) {

            $text   = trim($q['text'] ?? '');
            $points = (int)($q['points'] ?? 1);

            if ($text === '') continue;

            $stmt = $con->prepare("
                INSERT INTO quiz_question 
                (quiz_id, TYPE, question_text, points, sort_order)
                VALUES (?, ?, ?, ?, ?)
            ");

            $stmt->bind_param(
                'issii',
                $quizId,
                $quizFormat,
                $text,
                $points,
                $order
            );

            if (!$stmt->execute()) {
                throw new Exception("Erreur insertion question");
            }

            $questionId = $stmt->insert_id;

            /* ===== RQ ===== */
            if ($quizFormat === 'RQ' && !empty($q['keywords'])) {

                $keywords = explode(',', $q['keywords']);

                foreach ($keywords as $k) {

                    $k = trim($k);
                    if ($k === '') continue;

                    $stmtK = $con->prepare("
                        INSERT INTO quiz_question_keyword 
                        (question_id, keyword, poids)
                        VALUES (?, ?, 1)
                    ");

                    $stmtK->bind_param('is', $questionId, $k);
                    $stmtK->execute();
                }
            }

            /* ===== QCM ===== */
            if ($quizFormat === 'QCM' && !empty($q['choices'])) {

                $cOrder = 1;

                foreach ($q['choices'] as $c) {

                    $textChoice = trim($c['text'] ?? '');
                    if ($textChoice === '') continue;

                    $correct = (int)($c['correct'] ?? 0);

                    $stmtC = $con->prepare("
                        INSERT INTO quiz_choice
                        (question_id, choice_text, is_correct, sort_order)
                        VALUES (?, ?, ?, ?)
                    ");

                    $stmtC->bind_param(
                        'isii',
                        $questionId,
                        $textChoice,
                        $correct,
                        $cOrder
                    );

                    if (!$stmtC->execute()) {
                        throw new Exception("Erreur insertion choix");
                    }

                    $cOrder++;
                }
            }

            $order++;
        }
    }

    $con->commit();

    echo json_encode([
        'success' => true
    ]);

} catch (Throwable $e) {

    if (isset($con) && $con instanceof mysqli) {
        $con->rollback();
    }

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}