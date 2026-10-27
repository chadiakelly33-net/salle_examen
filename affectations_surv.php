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

// Récupérer les affectations actuelles et futures
// La faculté et le niveau sont récupérés depuis la table cours.

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

    ORDER BY
        a.date_exam ASC,
        a.heure_debut ASC
";

$req = $bdd->prepare($sql);

$req->execute([
    $_SESSION["id_surv"]
]);

$affectations = $req->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="fr">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Mes affectations</title>

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

        .container {
            padding: 30px;
            max-width: 1400px;
            margin: auto;
        }

        h2 {
            color: #355c7d;
            margin-bottom: 20px;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }

        .btn-export,
        .btn-retour {
            display: inline-block;
            text-decoration: none;
            color: white;
            padding: 10px 15px;
            border-radius: 8px;
            transition: background 0.2s;
        }

        .btn-export {
            background: #217346;
        }

        .btn-export:hover {
            background: #185c37;
        }

        .btn-retour {
            background: #7aa6c2;
            margin-top: 20px;
        }

        .btn-retour:hover {
            background: #355c7d;
        }

        .table-container {
            width: 100%;
            overflow-x: auto;
            background: white;
            border-radius: 8px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            margin-top: 0;
        }

        th {
            background: #355c7d;
            color: white;
            padding: 12px;
            white-space: nowrap;
        }

        td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: center;
        }

        tbody tr:hover {
            background: #f1f7fa;
        }

        .message {
            background: #fff3cd;
            color: #664d03;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
        }

        @media (max-width: 768px) {

            .container {
                padding: 15px;
            }

            h2 {
                font-size: 20px;
            }

            th,
            td {
                padding: 8px;
                font-size: 13px;
            }

        }

    </style>

</head>

<body>

    <div class="container">

        <h2>Mes affectations actuelles et futures</h2>

        <div class="actions">

            <a href="export_excel_surv.php" class="btn-export">
                Exporter vers Excel
            </a>

        </div>

        <?php if (!empty($affectations)): ?>

            <div class="table-container">

                <table>

                    <thead>

                        <tr>
                            <th>Cours</th>
                            <th>Faculté</th>
                            <th>Niveau</th>
                            <th>Salle</th>
                            <th>Date examen</th>
                            <th>Début</th>
                            <th>Fin</th>
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

        <?php else: ?>

            <div class="message">
                Aucune affectation future ou en cours.
            </div>

        <?php endif; ?>

        <a href="espace_surv.php" class="btn-retour">
            Retour
        </a>

    </div>

</body>

</html>

