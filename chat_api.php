<?php
ob_start();
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function repondre($success, $message = '', $extra = [], $code = 200)
{
    if (ob_get_length()) {
        ob_clean();
    }

    http_response_code($code);

    echo json_encode(
        array_merge([
            'success' => $success,
            'message' => $message
        ], $extra),
        JSON_UNESCAPED_UNICODE
    );

    exit;
}

$role = $_SESSION['role'] ?? '';

if (!in_array($role, ['etudiant', 'surveillant'], true)) {
    repondre(false, 'Accès réservé aux étudiants et surveillants.', [], 403);
}

if ($role === 'etudiant') {
    $id = filter_var($_SESSION['id_etu'] ?? null, FILTER_VALIDATE_INT);
} else {
    $id = filter_var($_SESSION['id_surv'] ?? null, FILTER_VALIDATE_INT);
}

if (!$id || $id < 1) {
    repondre(false, 'Session invalide. Reconnecte-toi.', [], 401);
}

if (!isset($_SESSION['chat_csrf'])) {
    $_SESSION['chat_csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf'] ?? '';

    if (
        !is_string($csrf) ||
        !hash_equals($_SESSION['chat_csrf'], $csrf)
    ) {
        repondre(false, 'Jeton de sécurité invalide.', [], 403);
    }
}

try {
    require_once __DIR__ . '/connexionDB.php';

    $action = $_SERVER['REQUEST_METHOD'] === 'POST'
        ? ($_POST['action'] ?? '')
        : ($_GET['action'] ?? '');

    // CONTACTS : uniquement pour les surveillants
    if ($action === 'contacts' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        if ($role !== 'surveillant') {
            repondre(false, 'Fonction réservée aux surveillants.', [], 403);
        }

        $stmt = $bdd->prepare("
            SELECT id_surv AS id, nom_surv AS nom, prenom_surv AS prenom
            FROM surveillant
            WHERE id_surv <> ?
            ORDER BY nom_surv, prenom_surv
        ");

        $stmt->execute([$id]);

        repondre(true, '', [
            'contacts' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ]);
    }

    // ENVOI
    if ($action === 'envoyer' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $message = trim($_POST['message'] ?? '');

        $longueur = function_exists('mb_strlen')
            ? mb_strlen($message, 'UTF-8')
            : strlen($message);

        if ($message === '' || $longueur > 5000) {
            repondre(false, 'Message vide ou trop long.', [], 400);
        }

        // Un étudiant ne peut contacter que l'administration.
        $destinataireType = 'admin';
        $destinataireId = 0;

        // Seuls les surveillants peuvent choisir un autre surveillant.
        if (isset($_POST['destinataire_id'])) {
            if ($role !== 'surveillant') {
                repondre(false, 'Tu peux contacter uniquement l’administration.', [], 403);
            }

            $destinataireId = filter_var(
                $_POST['destinataire_id'],
                FILTER_VALIDATE_INT
            );

            if (
                !$destinataireId ||
                $destinataireId < 1 ||
                $destinataireId === $id
            ) {
                repondre(false, 'Destinataire invalide.', [], 400);
            }

            $verifier = $bdd->prepare(
                'SELECT id_surv FROM surveillant WHERE id_surv = ?'
            );

            $verifier->execute([$destinataireId]);

            if (!$verifier->fetch()) {
                repondre(false, 'Surveillant introuvable.', [], 404);
            }

            $destinataireType = 'surveillant';
        }

        $stmt = $bdd->prepare("
            INSERT INTO messages_chat (
                expediteur_id,
                expediteur_type,
                destinataire_id,
                destinataire_type,
                message
            )
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $id,
            $role,
            $destinataireId,
            $destinataireType,
            $message
        ]);

        repondre(true, 'Message envoyé.');
    }

    // LECTURE
    if ($action === 'lire' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $type = $_GET['type'] ?? 'admin';
        $idContact = 0;

        if ($type === 'admin') {
            $type = 'admin';
        } elseif ($type === 'surveillant' && $role === 'surveillant') {
            $idContact = filter_var(
                $_GET['id'] ?? null,
                FILTER_VALIDATE_INT
            );

            if (
                !$idContact ||
                $idContact < 1 ||
                $idContact === $id
            ) {
                repondre(false, 'Contact invalide.', [], 400);
            }

            $verifier = $bdd->prepare(
                'SELECT id_surv FROM surveillant WHERE id_surv = ?'
            );

            $verifier->execute([$idContact]);

            if (!$verifier->fetch()) {
                repondre(false, 'Surveillant introuvable.', [], 404);
            }
        } else {
            repondre(false, 'Conversation non autorisée.', [], 403);
        }

        if ($type === 'admin') {
            $stmt = $bdd->prepare("
                SELECT
                    id_message,
                    message,
                    DATE_FORMAT(date_envoi, '%d/%m/%Y %H:%i:%s') AS date_envoi,
                    CASE WHEN expediteur_type = 'admin' THEN 1 ELSE 0 END AS est_admin,
                    CASE
                        WHEN expediteur_id = ? AND expediteur_type = ?
                        THEN 1 ELSE 0
                    END AS est_moi
                FROM messages_chat
                WHERE
                    (
                        expediteur_type = 'admin'
                        AND expediteur_id = 0
                        AND destinataire_type = ?
                        AND destinataire_id = ?
                    )
                    OR
                    (
                        expediteur_type = ?
                        AND expediteur_id = ?
                        AND destinataire_type = 'admin'
                        AND destinataire_id = 0
                    )
                ORDER BY id_message ASC
                LIMIT 500
            ");

            $stmt->execute([
                $id, $role,
                $role, $id,
                $role, $id
            ]);
        } else {
            $stmt = $bdd->prepare("
                SELECT
                    id_message,
                    message,
                    DATE_FORMAT(date_envoi, '%d/%m/%Y %H:%i:%s') AS date_envoi,
                    0 AS est_admin,
                    CASE WHEN expediteur_id = ? THEN 1 ELSE 0 END AS est_moi
                FROM messages_chat
                WHERE
                    (
                        expediteur_type = 'surveillant'
                        AND expediteur_id = ?
                        AND destinataire_type = 'surveillant'
                        AND destinataire_id = ?
                    )
                    OR
                    (
                        expediteur_type = 'surveillant'
                        AND expediteur_id = ?
                        AND destinataire_type = 'surveillant'
                        AND destinataire_id = ?
                    )
                ORDER BY id_message ASC
                LIMIT 500
            ");

            $stmt->execute([
                $id,
                $id, $idContact,
                $idContact, $id
            ]);
        }

        repondre(true, '', [
            'messages' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ]);
    }

    repondre(false, 'Action inconnue.', [], 400);

} catch (Throwable $e) {
    error_log('Erreur chat utilisateur : ' . $e->getMessage());
    repondre(false, 'Erreur serveur. Vérifie les logs PHP.', [], 500);
}

