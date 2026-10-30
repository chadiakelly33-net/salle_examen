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
require_once __DIR__ . "/connexionDB.php";

/* Durée maximale d'inactivité : 5 minutes */
$timeout = 300;

if (isset($_SESSION["last_activity"])) {
    if (time() - $_SESSION["last_activity"] > $timeout) {
        session_unset();
        session_destroy();

        header("Location: login_surv.php?timeout=1");
        exit();
    }
}

/* Actualiser l'activité */
$_SESSION["last_activity"] = time();

$id_surv = (int) $_SESSION["id_surv"];

/* Informations du surveillant */
$req = $bdd->prepare("
    SELECT nom_surv, prenom_surv, login
    FROM surveillant
    WHERE id_surv = ?
");

$req->execute([$id_surv]);
$surv = $req->fetch(PDO::FETCH_ASSOC);

if (!$surv) {
    session_unset();
    session_destroy();

    header("Location: login_surv.php");
    exit();
}

/* Affectations actuelles et futures */
$sql = "
    SELECT
        a.id_affectation,
        c.nom_cours,
        f.nom_fac,
        n.nom_niveau,
        s.nom_salle,
        a.date_exam,
        a.heure_debut,
        a.heure_fin
    FROM affectation a
    INNER JOIN cours c
        ON a.id_cours = c.id_cours
    INNER JOIN salle s
        ON a.id_salle = s.id_salle
    LEFT JOIN faculte f
        ON c.id_fac = f.id_fac
    LEFT JOIN niveau_etude n
        ON c.id_niveau = n.id_niveau
    WHERE a.id_surv = ?
      AND a.date_exam IS NOT NULL
      AND a.heure_fin IS NOT NULL
      AND CONCAT(a.date_exam, ' ', a.heure_fin) >= NOW()
    ORDER BY a.date_exam ASC, a.heure_debut ASC
";

$reqAffectations = $bdd->prepare($sql);
$reqAffectations->execute([$id_surv]);
$affectations = $reqAffectations->fetchAll(PDO::FETCH_ASSOC);

/* Statistiques des notifications du surveillant */
$reqNotifications = $bdd->prepare("
    SELECT
        COUNT(*) AS total,
        COALESCE(
            SUM(CASE WHEN lu = 0 THEN 1 ELSE 0 END),
            0
        ) AS non_lues
    FROM notification
    WHERE type_destinataire = 'surveillant'
      AND id_destinataire = ?
");

$reqNotifications->execute([$id_surv]);
$statsNotifications = $reqNotifications->fetch(PDO::FETCH_ASSOC);

$totalNotifications = (int) ($statsNotifications["total"] ?? 0);
$notificationsNonLues = (int) ($statsNotifications["non_lues"] ?? 0);

/* Sécuriser l'affichage */
function h($valeur): string
{
    return htmlspecialchars(
        (string) ($valeur ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Espace Surveillant</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #eef4f7;
            color: #333;
        }

        /* Barre supérieure */
        .header {
            background: #355c7d;
            color: white;
            padding: 18px 25px;
            margin-left: 230px;
        }

        .header h2 {
            margin: 0;
            font-size: 23px;
        }

        /* Menu latéral */
        .sidebar {
            width: 230px;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            background: #003366;
            padding-top: 20px;
            overflow-y: auto;
        }

        .sidebar h3 {
            color: white;
            text-align: center;
            padding: 0 12px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.2);
            font-size: 18px;
        }

        .sidebar a {
            display: block;
            padding: 14px 18px;
            color: white;
            text-decoration: none;
            font-size: 14px;
            transition: background 0.2s;
        }

        .sidebar a:hover {
            background: #355c7d;
        }

        .sidebar a.active {
            background: #355c7d;
            border-left: 4px solid #f1c40f;
        }

        .sidebar a.logout {
            margin-top: 15px;
            background: #a93226;
        }

        .sidebar a.logout:hover {
            background: #922b21;
        }

        .badge-menu {
            display: inline-block;
            min-width: 23px;
            padding: 3px 7px;
            margin-left: 5px;
            background: #f1c40f;
            color: #003366;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            text-align: center;
        }

        /* Contenu principal */
        .main {
            margin-left: 230px;
            padding: 25px;
        }

        .welcome {
            background: white;
            padding: 22px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }

        .welcome h3 {
            margin-top: 0;
            color: #355c7d;
        }

        .welcome p {
            margin-bottom: 0;
            line-height: 1.6;
        }

        /* Cartes statistiques */
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 18px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            border-left: 5px solid #355c7d;
            min-width: 0;
        }

        .stat-card h4 {
            margin: 0 0 12px;
            color: #666;
            font-size: 14px;
        }

        .stat-card .number {
            font-size: 28px;
            font-weight: bold;
            color: #355c7d;
        }

        .notification-card {
            border-left-color: #d4a72c;
        }

        /* Tableau des affectations */
        .section {
            background: white;
            padding: 22px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            margin-bottom: 25px;
        }

        .section h3 {
            color: #355c7d;
            margin-top: 0;
            margin-bottom: 18px;
        }

        .table-container {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        th {
            background: #355c7d;
            color: white;
            padding: 12px;
            white-space: nowrap;
            text-align: center;
        }

        td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: center;
            font-size: 14px;
        }

        tbody tr:hover {
            background: #f1f7fa;
        }

        .message {
            background: #fff3cd;
            color: #664d03;
            padding: 15px;
            border-radius: 8px;
            line-height: 1.5;
        }

        .btn {
            display: inline-block;
            padding: 10px 15px;
            background: #355c7d;
            color: white;
            text-decoration: none;
            border-radius: 7px;
            margin-top: 15px;
        }

        .btn:hover {
            background: #24445f;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                padding-top: 5px;
            }

            .sidebar h3 {
                padding-bottom: 10px;
            }

            .sidebar a {
                padding: 11px 15px;
            }

            .header {
                margin-left: 0;
                padding: 15px;
            }

            .header h2 {
                font-size: 20px;
            }

            .main {
                margin-left: 0;
                padding: 15px;
            }

            .stats {
                grid-template-columns: 1fr;
            }

            .section {
                padding: 15px;
            }

            th, td {
                padding: 8px;
                font-size: 12px;
            }
        }
    </style>
</head>

<body>

    <!-- Menu latéral -->
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

        <a href="changer_mdp_surv.php">
            Changer mon mot de passe
        </a>

        <a href="logout_surv.php" class="logout">
            Déconnexion
        </a>

    </aside>

    <!-- Barre supérieure -->
    <header class="header">
        <h2>Tableau de bord du surveillant</h2>
    </header>

    <!-- Contenu principal -->
    <main class="main">

        <section class="welcome">

            <h3>
                Bienvenue
                <?= h($surv["prenom_surv"]) ?>
                <?= h($surv["nom_surv"]) ?>
            </h3>

            <p>
                Retrouvez ici vos affectations et les informations qui vous sont destinées.
            </p>

        </section>

        <!-- Statistiques -->
        <section class="stats">

            <div class="stat-card">
                <h4>Mes affectations à venir</h4>

                <div class="number">
                    <?= count($affectations) ?>
                </div>
            </div>

            <div class="stat-card">
                <h4>Mon compte</h4>

                <div class="number" style="font-size: 19px;">
                    Actif
                </div>
            </div>

            <div class="stat-card notification-card">
                <h4>Notifications non lues</h4>

                <div class="number">
                    <?= $notificationsNonLues ?>
                </div>

                <p style="margin-top: 8px;">
                    <?= $totalNotifications ?> notification(s) au total.
                </p>

                <a href="mes_notifications_surv.php" class="btn">
                    Consulter mes notifications
                </a>
            </div>

        </section>

        <!-- Liste des affectations -->
        <section class="section">

            <h3>Mes affectations actuelles et futures</h3>

            <?php if (!empty($affectations)): ?>

                <div class="table-container">

                    <table>

                        <thead>
                            <tr>
                                <th>Cours</th>
                                <th>Faculté</th>
                                <th>Niveau</th>
                                <th>Salle</th>
                                <th>Date d'examen</th>
                                <th>Heure de début</th>
                                <th>Heure de fin</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($affectations as $a): ?>

                                <tr>

                                    <td><?= h($a["nom_cours"]) ?></td>

                                    <td><?= h($a["nom_fac"] ?? "Non renseignée") ?></td>

                                    <td><?= h($a["nom_niveau"] ?? "Non renseigné") ?></td>

                                    <td><?= h($a["nom_salle"]) ?></td>

                                    <td><?= h($a["date_exam"]) ?></td>

                                    <td><?= h($a["heure_debut"]) ?></td>

                                    <td><?= h($a["heure_fin"]) ?></td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

                <a href="affectations_surv.php" class="btn">
                    Voir toutes mes affectations
                </a>

            <?php else: ?>

                <div class="message">
                    Aucune affectation future ou en cours pour le moment.
                </div>

            <?php endif; ?>

        </section>

    </main>

</body>
</html>

