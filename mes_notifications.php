<?php
session_start();

/* Vérifier que l'étudiant est connecté */
if (!isset($_SESSION['id_etu']) || empty($_SESSION['id_etu'])) {
    header('Location: login_etu.php');
    exit();
}

/* Connexion à la base de données */
require_once __DIR__ . '/connexionDB.php';

$idEtudiant = (int) $_SESSION['id_etu'];
$notifications = [];
$messageErreur = '';

/* Protection contre les caractères HTML */
function h($valeur): string
{
    return htmlspecialchars((string)($valeur ?? ''), ENT_QUOTES, 'UTF-8');
}

try {
    /*
     * Marquer une notification comme lue.
     * L'étudiant ne peut modifier que ses propres notifications.
     */
    if ($_SERVER['REQUEST_METHOD'] === 'POST'
        && isset($_POST['id_notification'])) {

        $idNotification = filter_var(
            $_POST['id_notification'],
            FILTER_VALIDATE_INT
        );

        if ($idNotification !== false && $idNotification > 0) {
            $requete = $bdd->prepare("
                UPDATE notification
                SET lu = 1
                WHERE id_notification = ?
                  AND type_destinataire = 'etudiant'
                  AND id_destinataire = ?
            ");

            $requete->execute([$idNotification, $idEtudiant]);
        }

        header('Location: mes_notifications.php');
        exit();
    }

    /* Charger uniquement les notifications de cet étudiant */
    $requete = $bdd->prepare("
        SELECT
            n.id_notification,
            n.objet,
            n.message,
            n.statut_envoi,
            n.date_creation,
            n.date_envoi,
            n.lu,
            e.titre AS titre_evenement,
            e.date_evenement,
            e.lieu
        FROM notification n
        LEFT JOIN evenement e
            ON e.id_evenement = n.id_evenement
        WHERE n.type_destinataire = 'etudiant'
          AND n.id_destinataire = ?
        ORDER BY n.date_creation DESC, n.id_notification DESC
    ");

    $requete->execute([$idEtudiant]);
    $notifications = $requete->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('Erreur notifications étudiant : ' . $e->getMessage());
    $messageErreur = "Impossible de charger les notifications. Vérifie la structure des tables notification et evenement.";
}

/* Compter les notifications non lues */
$nonLues = 0;

foreach ($notifications as $notification) {
    if ((int)($notification['lu'] ?? 0) === 0) {
        $nonLues++;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Mes notifications</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 25px;
            background: #f4f7fb;
            color: #243247;
            font-family: Arial, sans-serif;
        }

        .conteneur {
            max-width: 1000px;
            margin: 0 auto;
        }

        .retour {
            display: inline-block;
            margin-bottom: 20px;
            color: #003366;
            text-decoration: none;
            font-weight: bold;
        }

        .retour:hover {
            text-decoration: underline;
        }

        h1 {
            margin-bottom: 8px;
            color: #003366;
        }

        .description {
            margin-bottom: 25px;
            color: #667085;
        }

        .statistiques {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 25px;
        }

        .statistique {
            flex: 1;
            min-width: 180px;
            padding: 20px;
            background: #fff;
            border: 1px solid #e1e7ef;
            border-radius: 10px;
        }

        .statistique span {
            display: block;
            margin-bottom: 8px;
            color: #667085;
        }

        .statistique strong {
            color: #003366;
            font-size: 26px;
        }

        .notification {
            margin-bottom: 18px;
            padding: 22px;
            background: #fff;
            border: 1px solid #e1e7ef;
            border-left: 5px solid #003366;
            border-radius: 10px;
        }

        .notification.non-lue {
            background: #f0f6ff;
            border-left-color: #d4a72c;
        }

        .entete-notification {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
        }

        .entete-notification h2 {
            margin: 0;
            color: #003366;
            font-size: 19px;
        }

        .badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .badge-lue {
            background: #e4f5e9;
            color: #176b35;
        }

        .badge-non-lue {
            background: #fff0c2;
            color: #805b00;
        }

        .message {
            margin: 15px 0;
            line-height: 1.7;
            overflow-wrap: anywhere;
        }

        .informations {
            padding-top: 12px;
            border-top: 1px solid #e8edf3;
            color: #667085;
            font-size: 13px;
            line-height: 1.8;
        }

        .bouton {
            margin-top: 15px;
            padding: 10px 15px;
            border: none;
            border-radius: 6px;
            background: #003366;
            color: white;
            cursor: pointer;
            font-size: 14px;
        }

        .bouton:hover {
            background: #064b8e;
        }

        .vide,
        .erreur {
            padding: 25px;
            background: white;
            border-radius: 10px;
            text-align: center;
        }

        .erreur {
            margin-bottom: 20px;
            color: #a32121;
            background: #fff0f0;
        }

        @media (max-width: 600px) {
            body {
                padding: 15px;
            }

            .notification {
                padding: 16px;
            }
        }
    </style>
</head>

<body>

<div class="conteneur">

    <a class="retour" href="espace_etu.php">
        &larr; Retour à mon espace
    </a>

    <h1>Mes notifications</h1>

    <p class="description">
        Retrouvez ici les informations et les annonces qui vous sont destinées.
    </p>

    <?php if ($messageErreur !== ''): ?>
        <div class="erreur">
            <?= h($messageErreur) ?>
        </div>
    <?php endif; ?>

    <div class="statistiques">
        <div class="statistique">
            <span>Total des notifications</span>
            <strong><?= count($notifications) ?></strong>
        </div>

        <div class="statistique">
            <span>Notifications non lues</span>
            <strong><?= $nonLues ?></strong>
        </div>
    </div>

    <?php if (empty($notifications)): ?>

        <div class="vide">
            <h2>Aucune notification</h2>
            <p>Tu n'as pas encore reçu de notification.</p>
        </div>

    <?php else: ?>

        <?php foreach ($notifications as $notification): ?>

            <?php
                $estLue = (int)($notification['lu'] ?? 0) === 1;
            ?>

            <article class="notification <?= $estLue ? '' : 'non-lue' ?>">

                <div class="entete-notification">
                    <h2>
                        <?= h($notification['objet'] ?: 'Notification') ?>
                    </h2>

                    <?php if ($estLue): ?>
                        <span class="badge badge-lue">Lue</span>
                    <?php else: ?>
                        <span class="badge badge-non-lue">Non lue</span>
                    <?php endif; ?>
                </div>

                <?php if (!empty($notification['titre_evenement'])): ?>
                    <p>
                        <strong>Événement :</strong>
                        <?= h($notification['titre_evenement']) ?>
                    </p>
                <?php endif; ?>

                <div class="message">
                    <?= nl2br(h($notification['message'])) ?>
                </div>

                <div class="informations">

                    <div>
                        <strong>Date de réception :</strong>
                        <?= h($notification['date_creation']) ?>
                    </div>

                    <?php if (!empty($notification['date_evenement'])): ?>
                        <div>
                            <strong>Date de l'événement :</strong>
                            <?= h($notification['date_evenement']) ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($notification['lieu'])): ?>
                        <div>
                            <strong>Lieu :</strong>
                            <?= h($notification['lieu']) ?>
                        </div>
                    <?php endif; ?>

                </div>

                <?php if (!$estLue): ?>
                    <form method="POST" action="mes_notifications.php">
                        <input
                            type="hidden"
                            name="id_notification"
                            value="<?= (int)$notification['id_notification'] ?>"
                        >

                        <button class="bouton" type="submit">
                            Marquer comme lue
                        </button>
                    </form>
                <?php endif; ?>

            </article>

        <?php endforeach; ?>

    <?php endif; ?>

</div>

</body>
</html>

