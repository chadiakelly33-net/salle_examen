<?php
session_start();

/* Vérifier que le surveillant est connecté */
if (
    !isset($_SESSION["id_surv"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "surveillant"
) {
    header("Location: login_surv.php");
    exit();
}

/* Connexion à la base de données */
require_once __DIR__ . '/connexionDB.php';

$idSurveillant = (int) $_SESSION['id_surv'];
$notifications = [];
$messageErreur = '';

function h($valeur): string
{
    return htmlspecialchars((string)($valeur ?? ''), ENT_QUOTES, 'UTF-8');
}

try {
    /* Marquer une notification comme lue */
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
                  AND type_destinataire = 'surveillant'
                  AND id_destinataire = ?
            ");

            $requete->execute([$idNotification, $idSurveillant]);
        }

        header('Location: mes_notifications_surv.php');
        exit();
    }

    /* Charger uniquement les notifications de ce surveillant */
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
        WHERE n.type_destinataire = 'surveillant'
          AND n.id_destinataire = ?
        ORDER BY n.date_creation DESC, n.id_notification DESC
    ");

    $requete->execute([$idSurveillant]);
    $notifications = $requete->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('Erreur notifications surveillant : ' . $e->getMessage());
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
    <title>Mes notifications - Surveillant</title>

    <style>
       /* ==============================
   STYLE GENERAL
============================== */

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: Arial, Helvetica, sans-serif;
    background: #f4f6f9;
    color: #333;
    min-height: 100vh;
}

/* ==============================
   BARRE LATERALE
============================== */

.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: 230px;
    height: 100vh;
    background: #003366;
    padding: 25px 15px;
    overflow-y: auto;
    z-index: 1000;
    box-shadow: 3px 0 12px rgba(0, 0, 0, 0.12);
}

.sidebar h3 {
    color: white;
    text-align: center;
    font-size: 20px;
    margin-bottom: 35px;
    padding-bottom: 20px;
    border-bottom: 1px solid rgba(255,255,255,0.2);
}

.sidebar a {
    display: flex;
    align-items: center;
    justify-content: space-between;
    text-decoration: none;
    color: #e8eef7;
    padding: 13px 14px;
    margin-bottom: 8px;
    border-radius: 7px;
    font-size: 14px;
    transition: 0.3s ease;
}

.sidebar a:hover,
.sidebar a.active {
    background: #d4a72c;
    color: #003366;
    font-weight: bold;
}

.sidebar a.logout {
    margin-top: 25px;
    border-top: 1px solid rgba(255,255,255,0.2);
    padding-top: 18px;
}

/* Compteur dans le menu */

.badge-menu {
    background: #d4a72c;
    color: #003366;
    padding: 3px 8px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: bold;
}

/* ==============================
   CONTENU PRINCIPAL
============================== */

.conteneur {
    max-width: 1100px;
    margin-left: 230px;
    padding: 35px 40px;
}

/* Bouton retour */

.retour {
    display: inline-block;
    margin-bottom: 25px;
    color: #003366;
    text-decoration: none;
    font-weight: bold;
    font-size: 14px;
}

.retour:hover {
    color: #d4a72c;
}

/* Titres */

h1 {
    color: #003366;
    font-size: 29px;
    margin-bottom: 10px;
}

.description {
    color: #667085;
    margin-bottom: 30px;
    line-height: 1.6;
    font-size: 15px;
}

/* ==============================
   STATISTIQUES
============================== */

.statistiques {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.statistique {
    background: white;
    padding: 24px;
    border-radius: 10px;
    border: 1px solid #e5eaf0;
    border-left: 5px solid #003366;
    box-shadow: 0 3px 12px rgba(0,0,0,0.05);
    transition: transform 0.3s ease;
}

.statistique:hover {
    transform: translateY(-3px);
}

.statistique span {
    display: block;
    color: #667085;
    font-size: 14px;
    margin-bottom: 12px;
}

.statistique strong {
    display: block;
    color: #003366;
    font-size: 30px;
}

/* ==============================
   CARTES NOTIFICATIONS
============================== */

.notification {
    background: white;
    padding: 25px;
    margin-bottom: 20px;
    border-radius: 10px;
    border: 1px solid #e5eaf0;
    border-left: 5px solid #003366;
    box-shadow: 0 3px 12px rgba(0,0,0,0.04);
    transition: 0.3s ease;
}

.notification:hover {
    box-shadow: 0 5px 18px rgba(0,0,0,0.09);
}

/* Notification non lue */

.notification.non-lue {
    background: #fffdf6;
    border-left-color: #d4a72c;
}

/* En-tête */

.entete-notification {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 18px;
}

.entete-notification h2 {
    color: #003366;
    font-size: 19px;
}

/* Badges */

.badge {
    display: inline-block;
    padding: 7px 13px;
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

/* Message */

.message {
    margin: 15px 0 20px;
    color: #444;
    line-height: 1.8;
    font-size: 15px;
    overflow-wrap: anywhere;
}

/* Informations */

.informations {
    padding-top: 15px;
    border-top: 1px solid #e8edf3;
    color: #667085;
    font-size: 13px;
    line-height: 2;
}

.informations strong {
    color: #003366;
}

/* ==============================
   BOUTONS
============================== */

.bouton {
    margin-top: 18px;
    padding: 11px 18px;
    border: none;
    border-radius: 6px;
    background: #003366;
    color: white;
    cursor: pointer;
    font-size: 13px;
    font-weight: bold;
    transition: 0.3s ease;
}

.bouton:hover {
    background: #d4a72c;
    color: #003366;
}

/* ==============================
   MESSAGE VIDE ET ERREUR
============================== */

.vide,
.erreur {
    padding: 35px 25px;
    background: white;
    border-radius: 10px;
    text-align: center;
    border: 1px solid #e5eaf0;
}

.vide h2 {
    color: #003366;
    margin-bottom: 12px;
}

.vide p {
    color: #667085;
}

.erreur {
    margin-bottom: 20px;
    color: #a32121;
    background: #fff0f0;
    border-color: #f5caca;
}

/* ==============================
   RESPONSIVE
============================== */

@media (max-width: 850px) {

    .conteneur {
        padding: 25px;
    }

    .statistiques {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 650px) {

    .sidebar {
        position: relative;
        width: 100%;
        height: auto;
        padding: 15px;
    }

    .sidebar h3 {
        margin-bottom: 15px;
        padding-bottom: 12px;
    }

    .sidebar a {
        margin-bottom: 5px;
    }

    .conteneur {
        margin-left: 0;
        padding: 22px 15px;
    }

    h1 {
        font-size: 23px;
    }

    .notification {
        padding: 18px;
    }

    .entete-notification h2 {
        font-size: 17px;
    }
}
    </style>
</head>

<body>
    
    <aside class="sidebar">

        <h3>Espace Surveillant</h3>

        <a href="espace_surv.php" class="active">
            Tableau de bord
        </a>

        <a href="mes_notifications_surv.php">
            Mes notifications

            <?php if ($notificationsNonLues > 0): ?>
                <span class="badge-menu">
                    <?= $notificationsNonLues ?>
                </span>
            <?php endif; ?>
        </a>

        <a href="profil_surv.php">
            Mon profil
        </a>

        <a href="affectations_surv.php">
            Mes affectations
        </a>

        <a href="documents_partages_surv.php">
            Documents partagés
        </a>

        <a href="rapports_surv.php">
            Mes rapports PDF
        </a>

        <!-- Accès à la page des commentaires -->
        <a href="commentaires_surv.php">
            Commentaires
        </a>

        <a href="changer_mdp_surv.php">
            Changer mon mot de passe
        </a>

        <a href="logout_surv.php" class="logout">
            Déconnexion
        </a>

    </aside>
    
<div class="conteneur">

    <a class="retour" href="espace_surv.php">
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
                    <form method="POST" action="mes_notifications_surv.php">
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

