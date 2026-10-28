<?php

session_start();

require_once __DIR__ . "/connexionDB.php";

// Durée maximale d'inactivité : 5 minutes
$timeout = 300;

// Vérifier le délai d'inactivité
if (isset($_SESSION["last_activity"])) {

    if (time() - $_SESSION["last_activity"] > $timeout) {

        session_unset();
        session_destroy();

        header("Location: login_surv.php?timeout=1");
        exit();
    }
}

// Vérifier que le surveillant est connecté
if (
    !isset($_SESSION["id_surv"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "surveillant"
) {
    header("Location: login_surv.php");
    exit();
}

// Actualiser l'activité de la session
$_SESSION["last_activity"] = time();

// Récupérer les informations du surveillant
$req = $bdd->prepare("
    SELECT nom_surv, prenom_surv, login
    FROM surveillant
    WHERE id_surv = ?
");

$req->execute([$_SESSION["id_surv"]]);

$surv = $req->fetch(PDO::FETCH_ASSOC);

if (!$surv) {
    session_unset();
    session_destroy();

    header("Location: login_surv.php");
    exit();
}

// Récupérer les affectations actuelles et futures
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

$req = $bdd->prepare($sql);
$req->execute([$_SESSION["id_surv"]]);

$affectations = $req->fetchAll(PDO::FETCH_ASSOC);

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
                <?= htmlspecialchars(
                    $surv["prenom_surv"] . " " . $surv["nom_surv"],
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>
            </h3>

            

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

                                    <td>
                                        <?= htmlspecialchars(
                                            $a["nom_cours"] ?? "",
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $a["nom_fac"] ?? "Non renseignée",
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $a["nom_niveau"] ?? "Non renseigné",
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $a["nom_salle"] ?? "",
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $a["date_exam"] ?? "",
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $a["heure_debut"] ?? "",
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $a["heure_fin"] ?? "",
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>
                                    </td>

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

