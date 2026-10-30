<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function repondre($success, $message = '', $extra = [], $code = 200)
{
    http_response_code($code);
    echo json_encode(
        array_merge(['success' => $success, 'message' => $message], $extra),
        JSON_UNESCAPED_UNICODE
    );
    exit();
}

if (
    !isset($_SESSION['id_admin']) ||
    ($_SESSION['role'] ?? '') !== 'admin'
) {
    repondre(false, 'Connexion administrateur requise.', [], 401);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf'] ?? '';
    $sessionCsrf = $_SESSION['chat_csrf'] ?? '';

    if (
        !is_string($csrf) ||
        !is_string($sessionCsrf) ||
        $sessionCsrf === '' ||
        !hash_equals($sessionCsrf, $csrf)
    ) {
        repondre(false, 'Jeton de sécurité invalide.', [], 403);
    }
}

require_once __DIR__ . '/../connexionDB.php';

$action = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['action'] ?? '')
    : ($_GET['action'] ?? '');

if ($action === 'envoyer' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'] ?? '';
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    $message = trim($_POST['message'] ?? '');

    if (!in_array($type, ['etudiant', 'surveillant'], true) || !$id || $id < 1) {
        repondre(false, 'Destinataire invalide.', [], 400);
    }

    if ($message === '' || (function_exists('mb_strlen')
        ? mb_strlen($message, 'UTF-8')
        : strlen($message)) > 5000) {
        repondre(false, 'Message vide ou trop long.', [], 400);
    }

    $table = $type === 'etudiant' ? 'etudiant' : 'surveillant';
    $colonne = $type === 'etudiant' ? 'id_etu' : 'id_surv';

    $stmt = $bdd->prepare("SELECT `$colonne` FROM `$table` WHERE `$colonne` = ?");
    $stmt->execute([$id]);

    if (!$stmt->fetch()) {
        repondre(false, 'Destinataire introuvable.', [], 404);
    }

    // L'administrateur est identifié par l'ID numérique 0.
    $stmt = $bdd->prepare("
        INSERT INTO messages_chat
        (expediteur_id, expediteur_type, destinataire_id, destinataire_type, message)
        VALUES (0, 'admin', ?, ?, ?)
    ");
    $stmt->execute([$id, $type, $message]);

    repondre(true, 'Message envoyé.');
}

if ($action === 'lire' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $type = $_GET['type'] ?? '';
    $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);

    if (!in_array($type, ['etudiant', 'surveillant'], true) || !$id || $id < 1) {
        repondre(false, 'Contact invalide.', [], 400);
    }

    $stmt = $bdd->prepare("
        SELECT
            id_message,
            expediteur_id,
            expediteur_type,
            destinataire_id,
            destinataire_type,
            message,
            DATE_FORMAT(date_envoi, '%d/%m/%Y %H:%i:%s') AS date_envoi,
            CASE WHEN expediteur_type = 'admin' THEN 1 ELSE 0 END AS est_admin
        FROM messages_chat
        WHERE
            (expediteur_type = 'admin' AND expediteur_id = 0
             AND destinataire_type = ? AND destinataire_id = ?)
            OR
            (expediteur_type = ? AND expediteur_id = ?
             AND destinataire_type = 'admin' AND destinataire_id = 0)
        ORDER BY id_message ASC
        LIMIT 500
    ");

    $stmt->execute([$type, $id, $type, $id]);

    repondre(true, '', ['messages' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

repondre(false, 'Action inconnue.', [], 400);

