<?php
session_start();

/* Protection de l'accès direct */
if (
    !isset($_SESSION["id_etu"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "etudiant"
) {
    header("Location: login_etu.php");
    exit();
}

/* Connexion à la base de données */
require_once __DIR__ . "/connexionDB.php";

/* Gestion du timeout : 5 minutes */
$timeout = 300;

if (isset($_SESSION["last_activity"])) {
    if (time() - $_SESSION["last_activity"] > $timeout) {
        session_unset();
        session_destroy();

        header("Location: login_etu.php?timeout=1");
        exit();
    }
}

$_SESSION["last_activity"] = time();

$id_etu = (int) $_SESSION["id_etu"];

/* Informations de l'étudiant */
$req = $bdd->prepare("
    SELECT
        e.nom_etu,
        e.prenom_etu,
        e.login,
        f.nom_fac,
        n.nom_niveau
    FROM etudiant e
    JOIN faculte f ON e.id_fac = f.id_fac
    JOIN niveau_etude n ON e.id_niveau = n.id_niveau
    WHERE e.id_etu = ?
");

$req->execute([$id_etu]);
$etu = $req->fetch(PDO::FETCH_ASSOC);

if (!$etu) {
    session_unset();
    session_destroy();
    die("Étudiant introuvable.");
}

/* Examens à venir */
$reqExamens = $bdd->prepare("
    SELECT
        c.nom_cours,
        s.nom_salle,
        a.date_exam,
        a.heure_debut,
        a.heure_fin
    FROM affectation a
    JOIN cours c ON a.id_cours = c.id_cours
    JOIN salle s ON a.id_salle = s.id_salle
    WHERE a.id_etu = ?
      AND (
          a.date_exam > CURDATE()
          OR (
              a.date_exam = CURDATE()
              AND a.heure_fin >= CURTIME()
          )
      )
    ORDER BY a.date_exam, a.heure_debut
");

$reqExamens->execute([$id_etu]);
$examens = $reqExamens->fetchAll(PDO::FETCH_ASSOC);

/* Nombre d'examens à venir */
$totalExamens = count($examens);

/* Prochain examen */
$prochainExamen = !empty($examens) ? $examens[0] : null;

/* Notifications de l'étudiant */
$reqNotifications = $bdd->prepare("
    SELECT
        COUNT(*) AS total,
        COALESCE(SUM(CASE WHEN lu = 0 THEN 1 ELSE 0 END), 0) AS non_lues
    FROM notification
    WHERE type_destinataire = 'etudiant'
      AND id_destinataire = ?
");

$reqNotifications->execute([$id_etu]);
$statsNotifications = $reqNotifications->fetch(PDO::FETCH_ASSOC);

$totalNotifications = (int) ($statsNotifications["total"] ?? 0);
$notificationsNonLues = (int) ($statsNotifications["non_lues"] ?? 0);

/* Protection de l'affichage HTML */
function h($valeur): string
{
    return htmlspecialchars((string) ($valeur ?? ""), ENT_QUOTES, "UTF-8");
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Tableau de bord étudiant</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f6f9;
            color: #333;
        }

        /* MENU LATERAL */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 230px;
            height: 100vh;
            background: #003366;
            padding: 25px 15px;
            overflow-y: auto;
        }

        .sidebar h2 {
            color: white;
            text-align: center;
            font-size: 21px;
            margin-bottom: 35px;
        }

        .sidebar a {
            display: block;
            color: white;
            text-decoration: none;
            padding: 14px 12px;
            margin-bottom: 8px;
            border-radius: 5px;
            transition: background 0.2s;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: #0055aa;
        }

        .sidebar .logout {
            margin-top: 30px;
            background: #7f1d1d;
        }

        .sidebar .logout:hover {
            background: #a52a2a;
        }

        .badge-menu {
            display: inline-block;
            min-width: 23px;
            padding: 3px 7px;
            margin-left: 5px;
            background: #d4a72c;
            color: #003366;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            text-align: center;
        }

        /* CONTENU PRINCIPAL */
        .main {
            margin-left: 230px;
            padding: 30px;
        }

        .header {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            margin-bottom: 25px;
        }

        .header h1 {
            color: #003366;
            margin-bottom: 10px;
            font-size: 26px;
        }

        .header p {
            color: #666;
            line-height: 1.7;
            overflow-wrap: anywhere;
        }

        /* CARTES STATISTIQUES */
        .cards {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .card {
            background: white;
            padding: 24px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            border-left: 5px solid #003366;
            min-width: 0;
        }

        .card h3 {
            color: #666;
            font-size: 15px;
            font-weight: normal;
            margin-bottom: 12px;
        }

        .card .number {
            font-size: 30px;
            font-weight: bold;
            color: #003366;
        }

        .card .detail {
            color: #555;
            line-height: 1.7;
            margin-top: 8px;
        }

        .card-notification {
            border-left-color: #d4a72c;
        }

        /* SECTIONS */
        .section {
            background: white;
            padding: 24px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            margin-bottom: 25px;
        }

        .section h2 {
            color: #003366;
            font-size: 20px;
            margin-bottom: 20px;
        }

        /* TABLEAU */
        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 13px;
            border: 1px solid #ddd;
            text-align: left;
        }

        th {
            background: #003366;
            color: white;
        }

        .empty {
            text-align: center;
            color: #777;
            padding: 25px;
        }

        .button {
            display: inline-block;
            padding: 11px 18px;
            background: #003366;
            color: white;
            text-decoration: none;
            border: none;
            border-radius: 5px;
            margin-top: 15px;
            cursor: pointer;
        }

        .button:hover {
            background: #0055aa;
        }

        /* RESPONSIVE */
        @media (max-width: 800px) {
            .sidebar {
                width: 190px;
            }

            .main {
                margin-left: 190px;
                padding: 20px;
            }

            .cards {
                grid-template-columns: 1fr;
            }

            table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
        }

        @media (max-width: 550px) {
            .sidebar {
                position: relative;
                width: 100%;
                height: auto;
            }

            .sidebar h2 {
                margin-bottom: 15px;
            }

            .main {
                margin-left: 0;
                padding: 15px;
            }

            .header h1 {
                font-size: 22px;
            }
        }
    </style>
</head>

<body>

<!-- MENU LATERAL -->
<aside class="sidebar">

    <h2>Espace étudiant</h2>

    <a href="espace_etu.php" class="active">
        Tableau de bord
    </a>

    <a href="mes_notifications.php">
        Mes notifications
        <?php if ($notificationsNonLues > 0): ?>
            <span class="badge-menu">
                <?= $notificationsNonLues ?>
            </span>
        <?php endif; ?>
    </a>

    <a href="mes_camarades.php">
        Liste des étudiants
    </a>

    <a href="mes_affectations.php">
        Mes affectations
    </a>

    <a href="commentaires_etu.php">
        Commentaires
    </a>

    <a href="documents_partages_etu.php">
        Documents partagés
    </a>

    <a href="changer_motdepasse.php">
        Modifier le mot de passe
    </a>

    <a href="logout_etu.php" class="logout">
        Déconnexion
    </a>

</aside>

<!-- CONTENU PRINCIPAL -->
<main class="main">

    <!-- BIENVENUE -->
    <section class="header">

        <h1>
            Bienvenue,
            <?= h($etu["prenom_etu"]) ?>
            <?= h($etu["nom_etu"]) ?>
        </h1>

        <p>
            Heureux de vous retrouver dans votre espace étudiant.
        </p>

        <p>
            <strong>Login :</strong>
            <?= h($etu["login"]) ?>
            &nbsp; | &nbsp;

            <strong>Faculté :</strong>
            <?= h($etu["nom_fac"]) ?>
            &nbsp; | &nbsp;

            <strong>Niveau :</strong>
            <?= h($etu["nom_niveau"]) ?>
        </p>

    </section>

    <!-- STATISTIQUES -->
    <section class="cards">

        <div class="card">
            <h3>Mes examens à venir</h3>

            <div class="number">
                <?= $totalExamens ?>
            </div>

            <a href="mes_affectations.php" class="button">
                Voir mes examens
            </a>
        </div>

        <div class="card">
            <h3>Prochain examen</h3>

            <?php if ($prochainExamen): ?>

                <div class="detail">

                    <strong>
                        <?= h($prochainExamen["nom_cours"]) ?>
                    </strong>

                    <br>

                    Date :
                    <?= h($prochainExamen["date_exam"]) ?>

                    <br>

                    Heure :
                    <?= h($prochainExamen["heure_debut"]) ?>
                    -
                    <?= h($prochainExamen["heure_fin"]) ?>

                    <br>

                    Salle :
                    <?= h($prochainExamen["nom_salle"]) ?>

                </div>

            <?php else: ?>

                <div class="detail">
                    Aucun examen à venir.
                </div>

            <?php endif; ?>
        </div>

        <div class="card card-notification">
            <h3>Notifications non lues</h3>

            <div class="number">
                <?= $notificationsNonLues ?>
            </div>

            <div class="detail">
                <?= $totalNotifications ?> notification(s) au total.
            </div>

            <a href="mes_notifications.php" class="button">
                Consulter mes notifications
            </a>
        </div>

    </section>

    <!-- INFORMATIONS SUR LES EXAMENS -->
    <section class="section">

        <h2>Mes prochains examens</h2>

        <table>
            <thead>
                <tr>
                    <th>Cours</th>
                    <th>Date</th>
                    <th>Heure de début</th>
                    <th>Heure de fin</th>
                    <th>Salle</th>
                </tr>
            </thead>

            <tbody>

            <?php if (!empty($examens)): ?>

                <?php foreach ($examens as $examen): ?>

                    <tr>
                        <td><?= h($examen["nom_cours"]) ?></td>
                        <td><?= h($examen["date_exam"]) ?></td>
                        <td><?= h($examen["heure_debut"]) ?></td>
                        <td><?= h($examen["heure_fin"]) ?></td>
                        <td><?= h($examen["nom_salle"]) ?></td>
                    </tr>

                <?php endforeach; ?>

            <?php else: ?>

                <tr>
                    <td colspan="5" class="empty">
                        Aucun examen programmé pour le moment.
                    </td>
                </tr>

            <?php endif; ?>

            </tbody>
        </table>

        <a href="mes_affectations.php" class="button">
            Consulter toutes mes affectations
        </a>

    </section>

</main>

</body>
</html>