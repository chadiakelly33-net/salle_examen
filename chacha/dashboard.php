<?php

session_start();

// Vérifier que l'administrateur est connecté
if (
    !isset($_SESSION["id_admin"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: connexion.php");
    exit();
}

require_once __DIR__ . "/connexionDB.php";

// Récupérer les statistiques
$totalEtu = $bdd->query(
    "SELECT COUNT(*) FROM etudiant"
)->fetchColumn();

$totalSalle = $bdd->query(
    "SELECT COUNT(*) FROM salle"
)->fetchColumn();

$totalSurv = $bdd->query(
    "SELECT COUNT(*) FROM surveillant"
)->fetchColumn();

$totalAff = $bdd->query(
    "SELECT COUNT(*) FROM affectation"
)->fetchColumn();


// Disponibilité des salles aujourd'hui
$reqSalles = $bdd->query("
    SELECT
        s.id_salle,
        s.nom_salle,
        CASE
            WHEN EXISTS (
                SELECT 1
                FROM affectation a
                WHERE a.id_salle = s.id_salle
                  AND a.date_exam = CURDATE()
                  AND CURTIME() >= a.heure_debut
                  AND CURTIME() < a.heure_fin
            )
            THEN 'occupée'
            ELSE 'libre'
        END AS statut
    FROM salle s
    ORDER BY s.nom_salle ASC
");

$salles = $reqSalles->fetchAll(PDO::FETCH_ASSOC);


// Disponibilité des surveillants aujourd'hui
$reqSurveillants = $bdd->query("
    SELECT
        sv.id_surv,
        sv.nom_surv,
        sv.prenom_surv,
        CASE
            WHEN EXISTS (
                SELECT 1
                FROM affectation a
                WHERE a.id_surv = sv.id_surv
                  AND a.date_exam = CURDATE()
                  AND CURTIME() >= a.heure_debut
                  AND CURTIME() < a.heure_fin
            )
            THEN 'occupé'
            ELSE 'libre'
        END AS statut
    FROM surveillant sv
    ORDER BY sv.nom_surv ASC
");

$surveillants = $reqSurveillants->fetchAll(PDO::FETCH_ASSOC);


// Sécuriser les textes affichés
function e($valeur)
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

    <title>Tableau de bord administrateur</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            color: #1e293b;
        }

        main {
            width: 100%;
            max-width: 1500px;
            margin: auto;
            padding: 30px;
        }

        /* EN-TÊTE */

        .header {
            margin-bottom: 30px;
        }

        .header h1 {
            margin: 0 0 8px;
            color: #1e3a5f;
            font-size: 28px;
        }

        .header p {
            margin: 0;
            color: #64748b;
            font-size: 15px;
        }

        /* STATISTIQUES */

        .stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 30px;
        }

        .card {
            background: white;
            padding: 22px 15px;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.05);
            border-top: 4px solid #355c7d;
            text-align: center;
        }

        .card h2 {
            margin: 0 0 10px;
            color: #1e3a5f;
            font-size: 30px;
        }

        .card p {
            margin: 0;
            color: #64748b;
            font-size: 14px;
        }

        /* ACCÈS RAPIDES */

        .quick-access {
            background: white;
            padding: 22px;
            margin-bottom: 30px;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.05);
        }

        .quick-access h2,
        .box h2 {
            margin: 0 0 20px;
            color: #1e3a5f;
            font-size: 20px;
        }

        .quick-links {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 15px;
        }

        .quick-link {
            display: block;
            padding: 18px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            background: #f8fafc;
            color: #1e3a5f;
            text-decoration: none;
            font-weight: bold;
            transition: background 0.2s, transform 0.2s;
        }

        .quick-link:hover {
            background: #e8f1f8;
            transform: translateY(-2px);
        }

        .quick-link small {
            display: block;
            margin-top: 8px;
            color: #64748b;
            font-weight: normal;
            line-height: 1.5;
        }

        /* DISPONIBILITÉS */

        .grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 22px;
        }

        .box {
            background: white;
            padding: 22px;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.05);
            min-width: 0;
        }

        .item {
            padding: 13px;
            margin-bottom: 10px;
            border-radius: 8px;
            background: #f8fafc;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }

        .item:last-child {
            margin-bottom: 0;
        }

        .nom {
            overflow-wrap: anywhere;
        }

        .statut {
            font-size: 13px;
            font-weight: bold;
            padding: 6px 10px;
            border-radius: 20px;
            white-space: nowrap;
        }

        .green {
            color: #166534;
            background: #dcfce7;
        }

        .red {
            color: #991b1b;
            background: #fee2e2;
        }

        .empty {
            padding: 15px;
            background: #f8fafc;
            border-radius: 8px;
            color: #64748b;
            text-align: center;
        }

        /* RESPONSIVE */

        @media (max-width: 1000px) {

            .stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .quick-links {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

        }

        @media (max-width: 750px) {

            main {
                padding: 18px;
            }

            .grid {
                grid-template-columns: 1fr;
            }

            .header h1 {
                font-size: 23px;
            }

        }

        @media (max-width: 450px) {

            .stats,
            .quick-links {
                grid-template-columns: 1fr;
            }

            .card,
            .quick-access,
            .box {
                padding: 16px;
            }

        }

    </style>

</head>

<body>

<main>

    <!-- EN-TÊTE -->

    <div class="header">

        <h1>Tableau de bord administrateur</h1>

        <p>
            Vue générale de la gestion des examens
        </p>

    </div>


    <!-- STATISTIQUES GÉNÉRALES -->

    <section class="stats">

        <div class="card">
            <h2><?= (int) $totalEtu ?></h2>
            <p>Étudiants</p>
        </div>

        <div class="card">
            <h2><?= (int) $totalSurv ?></h2>
            <p>Surveillants</p>
        </div>

        <div class="card">
            <h2><?= (int) $totalSalle ?></h2>
            <p>Salles</p>
        </div>

        <div class="card">
            <h2><?= (int) $totalAff ?></h2>
            <p>Affectations</p>
        </div>

    </section>


    <!-- ACCÈS RAPIDES -->

    <section class="quick-access">

        <h2>Accès rapides</h2>

        <div class="quick-links">

            <a href="get_etudiants.php" class="quick-link">
                Gestion des étudiants
                <small>Consulter les étudiants enregistrés</small>
            </a>

            <a href="afficher_surv.php" class="quick-link">
                Gestion des surveillants
                <small>Consulter les surveillants enregistrés</small>
            </a>

            <a href="afficher_cours.php" class="quick-link">
                Gestion des cours
                <small>Consulter les cours enregistrés</small>
            </a>

            <a href="afficher_salle.php" class="quick-link">
                Gestion des salles
                <small>Consulter les salles disponibles</small>
            </a>

            <a href="affectation.php" class="quick-link">
                Gestion des affectations
                <small>Accéder à la gestion des examens</small>
            </a>

            <a href="admin_connexions.php" class="quick-link">
                Historique des connexions
                <small>Consulter les tentatives de connexion</small>
            </a>

        </div>

    </section>


    <!-- DISPONIBILITÉ DES SALLES ET DES SURVEILLANTS -->

    <div class="grid">

        <!-- SALLES -->

        <section class="box">

            <h2>Disponibilité des salles aujourd'hui</h2>

            <?php if (!empty($salles)): ?>

                <?php foreach ($salles as $s): ?>

                    <div class="item">

                        <span class="nom">
                            <?= e($s["nom_salle"]) ?>
                        </span>

                        <span class="statut <?= $s["statut"] === "libre" ? "green" : "red" ?>">
                            <?= ucfirst(e($s["statut"])) ?>
                        </span>

                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <div class="empty">
                    Aucune salle enregistrée.
                </div>

            <?php endif; ?>

        </section>


        <!-- SURVEILLANTS -->

        <section class="box">

            <h2>Disponibilité des surveillants aujourd'hui</h2>

            <?php if (!empty($surveillants)): ?>

                <?php foreach ($surveillants as $s): ?>

                    <div class="item">

                        <span class="nom">
                            <?= e($s["nom_surv"]) ?>
                            <?= e($s["prenom_surv"]) ?>
                        </span>

                        <span class="statut <?= $s["statut"] === "libre" ? "green" : "red" ?>">
                            <?= ucfirst(e($s["statut"])) ?>
                        </span>

                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <div class="empty">
                    Aucun surveillant enregistré.
                </div>

            <?php endif; ?>

        </section>

    </div>

</main>

</body>

</html>

