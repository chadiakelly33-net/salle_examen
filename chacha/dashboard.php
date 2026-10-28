<?php
session_start();

/* Vérification de la connexion administrateur */
if (
    !isset($_SESSION["id_admin"]) ||
    ($_SESSION["role"] ?? "") !== "admin"
) {
    header("Location: connexion.php");
    exit();
}

/* Connexion à la base de données */
require_once __DIR__ . "/connexionDB.php";

/* Déconnexion automatique après 5 minutes d'inactivité */
$temps_max = 300;

if (isset($_SESSION["dernier_acces"])) {
    if (time() - $_SESSION["dernier_acces"] > $temps_max) {
        session_unset();
        session_destroy();
        header("Location: connexion.php");
        exit();
    }
}

$_SESSION["dernier_acces"] = time();

/* Fonction de sécurisation */
function e($valeur)
{
    return htmlspecialchars((string) $valeur, ENT_QUOTES, "UTF-8");
}

/* Récupération des statistiques */
try {
    $total_etudiants = (int) $bdd
        ->query("SELECT COUNT(*) FROM etudiant")
        ->fetchColumn();

    $total_salles = (int) $bdd
        ->query("SELECT COUNT(*) FROM salle")
        ->fetchColumn();

    $total_surveillants = (int) $bdd
        ->query("SELECT COUNT(*) FROM surveillant")
        ->fetchColumn();

    $total_affectations = (int) $bdd
        ->query("SELECT COUNT(*) FROM affectation")
        ->fetchColumn();

} catch (PDOException $ex) {
    $total_etudiants = 0;
    $total_salles = 0;
    $total_surveillants = 0;
    $total_affectations = 0;
}

/* Date et heures utilisées pour les deux disponibilités */
$date_disponibilite = $_GET["date_exam"] ?? date("Y-m-d");
$heure_debut = $_GET["heure_debut"] ?? "08:00";
$heure_fin = $_GET["heure_fin"] ?? "10:00";

/* Vérification des valeurs reçues */
if (
    !is_string($date_disponibilite) ||
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_disponibilite) ||
    !checkdate(
        (int) substr($date_disponibilite, 5, 2),
        (int) substr($date_disponibilite, 8, 2),
        (int) substr($date_disponibilite, 0, 4)
    )
) {
    $date_disponibilite = date("Y-m-d");
}

if (
    !is_string($heure_debut) ||
    !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $heure_debut)
) {
    $heure_debut = "08:00";
}

if (
    !is_string($heure_fin) ||
    !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $heure_fin)
) {
    $heure_fin = "10:00";
}

/* Initialisation des disponibilités */
$salles_disponibilite = [];
$surveillants_disponibilite = [];

$erreur_disponibilite = "";
$erreur_surv = "";

/* Vérification des heures */
if ($heure_debut >= $heure_fin) {

    $erreur_disponibilite =
        "L'heure de fin doit être postérieure à l'heure de début.";

    $erreur_surv = $erreur_disponibilite;

} else {

    /* Disponibilité des salles */
    try {
        $sql_disponibilite = "
            SELECT
                s.id_salle,
                s.nom_salle,
                s.capacite,
                CASE
                    WHEN EXISTS (
                        SELECT 1
                        FROM affectation a
                        WHERE a.id_salle = s.id_salle
                          AND a.date_exam = :date_exam
                          AND a.heure_debut < :heure_fin
                          AND a.heure_fin > :heure_debut
                    )
                    THEN 'Occupée'
                    ELSE 'Disponible'
                END AS disponibilite
            FROM salle s
            ORDER BY s.nom_salle ASC
        ";

        $stmt_disponibilite = $bdd->prepare($sql_disponibilite);

        $stmt_disponibilite->execute([
            ":date_exam" => $date_disponibilite,
            ":heure_debut" => $heure_debut,
            ":heure_fin" => $heure_fin
        ]);

        $salles_disponibilite =
            $stmt_disponibilite->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $ex) {
        $erreur_disponibilite =
            "Impossible de récupérer la disponibilité des salles.";
    }

    /* Disponibilité des surveillants */
    try {
        $sql_surv = "
            SELECT
                s.id_surv,
                s.nom_surv,
                s.prenom_surv,
                CASE
                    WHEN EXISTS (
                        SELECT 1
                        FROM affectation a
                        WHERE a.id_surv = s.id_surv
                          AND a.date_exam = :date_exam
                          AND a.heure_debut < :heure_fin
                          AND a.heure_fin > :heure_debut
                    )
                    THEN 'Occupé'
                    ELSE 'Disponible'
                END AS disponibilite
            FROM surveillant s
            ORDER BY s.nom_surv ASC, s.prenom_surv ASC
        ";

        $stmt_surv = $bdd->prepare($sql_surv);

        $stmt_surv->execute([
            ":date_exam" => $date_disponibilite,
            ":heure_debut" => $heure_debut,
            ":heure_fin" => $heure_fin
        ]);

        $surveillants_disponibilite =
            $stmt_surv->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $ex) {
        $erreur_surv =
            "Impossible de récupérer la disponibilité des surveillants.";
    }
}

/* Liens des cartes Accès rapides */
$groupes = [
    "Étudiants" => [
        ["Ajouter un étudiant", "ajouter_etu.php"],
        ["Afficher les étudiants", "afficher_etu.php"],
        ["Rapport des étudiants", "rapport_etudiants.php"]
    ],

    "Cours" => [
        ["Ajouter un cours", "ajouter_cours.php"],
        ["Afficher les cours", "afficher_cours.php"]
    ],

    "Facultés" => [
        ["Ajouter une faculté", "ajouter_fac.php"],
        ["Afficher les facultés", "afficher_fac.php"]
    ],

    "Salles" => [
        ["Ajouter une salle", "ajouter_salle.php"],
        ["Afficher les salles", "afficher_salle.php"]
    ],

    "Surveillants" => [
        ["Ajouter un surveillant", "ajouter_sur.php"],
        ["Afficher les surveillants", "afficher_sur.php"],
        ["Voir la disponibilité des surveillants", "gerer_disponibilite_surv.php"]
    ],

    "Affectations" => [
        ["Ajouter une affectation", "ajouter_affectation.php"],
        ["Afficher les affectations", "afficher_affectation_admin.php"],
        ["Gérer la disponibilité des surveillants", "gerer_disponibilite_surv.php"],
        ["Surveillants affectés", "surveillants_affectes.php"],
        ["Étudiants par salle", "consulter_etudiants_salle.php"],
        ["Rechercher une affectation", "recherche_affectation.php"],
        ["Rapport des affectations", "rapport_affectations.php"]
    ],

    "Documents" => [
        ["Ajouter un document", "ajouter_document.php"],
        ["Afficher les documents", "afficher_documents.php"]
    ],

    "Rapports et historique" => [
        ["Rapports PDF", "rapports.php"],
        ["Export Excel", "export_excel.php"],
        ["Historique des connexions", "admin_connexions.php"],
        ["Journal des activités", "historique_activites.php"],
        ["Archives", "afficher_archives.php"],
        ["Tri des affectations", "tri_affectation.php"],
        ["Filtrer les affectations", "filtrage_affectation.php"]
    ]
];
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Tableau de bord - Salle d'examen</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f6f9;
            color: #263445;
        }

        /* MENU LATÉRAL */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 230px;
            height: 100vh;
            background: #003366;
            padding-top: 20px;
            overflow-y: auto;
            z-index: 1000;
        }

        .sidebar h2 {
            color: white;
            text-align: center;
            font-size: 21px;
            margin: 0 10px 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(255,255,255,0.2);
        }

        .sidebar a {
            display: block;
            color: white;
            text-decoration: none;
            padding: 12px 18px;
            font-size: 14px;
            transition: 0.3s;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: #ffd700;
            color: #003366;
        }

        .sidebar .dropdown {
            position: relative;
        }

        .sidebar .dropdown-title {
            cursor: pointer;
        }

        .sidebar .dropdown-content {
            display: none;
            background: #002244;
        }

        .sidebar .dropdown-content a {
            padding-left: 35px;
            font-size: 13px;
        }

        .sidebar .dropdown:hover .dropdown-content {
            display: block;
        }

        /* CONTENU PRINCIPAL */
        .main {
            margin-left: 230px;
            padding: 28px;
            min-height: 100vh;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 28px;
        }

        .topbar h1 {
            margin: 0;
            color: #003366;
            font-size: 27px;
        }

        .topbar p {
            margin: 8px 0 0;
            color: #687789;
            font-size: 14px;
        }

        .logout-button {
            display: inline-block;
            background: #c0392b;
            color: white;
            padding: 11px 17px;
            border-radius: 7px;
            text-decoration: none;
            font-size: 14px;
        }

        .logout-button:hover {
            background: #962d22;
        }

        /* STATISTIQUES */
        .stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: white;
            padding: 23px 20px;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.05);
            border-top: 4px solid #003366;
        }

        .stat-card h3 {
            margin: 0 0 15px;
            font-size: 14px;
            color: #687789;
            font-weight: normal;
        }

        .stat-card .number {
            color: #003366;
            font-size: 32px;
            font-weight: bold;
        }

        /* DISPONIBILITÉS */
        .section-title {
            color: #003366;
            font-size: 21px;
            margin: 0 0 18px;
        }

        .availability-section {
            background: white;
            padding: 24px;
            margin-bottom: 32px;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.05);
        }

        .availability-form {
            display: flex;
            align-items: end;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 22px;
        }

        .availability-form .form-group {
            display: flex;
            flex-direction: column;
            gap: 7px;
            flex: 1;
            min-width: 140px;
        }

        .availability-form label {
            font-size: 13px;
            font-weight: bold;
            color: #40546b;
        }

        .availability-form input {
            width: 100%;
            padding: 11px;
            border: 1px solid #d5dce5;
            border-radius: 6px;
            font-family: inherit;
            font-size: 14px;
        }

        .availability-form button {
            background: #003366;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 12px 20px;
            cursor: pointer;
            font-size: 14px;
        }

        .availability-form button:hover {
            background: #004b8d;
        }

        .availability-table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        .availability-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 420px;
        }

        .availability-table th,
        .availability-table td {
            padding: 13px 15px;
            text-align: left;
            border-bottom: 1px solid #edf0f4;
            font-size: 14px;
        }

        .availability-table th {
            background: #f0f5fa;
            color: #003366;
        }

        .status {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .status.available {
            background: #e2f6e9;
            color: #18753b;
        }

        .status.occupied {
            background: #fde8e7;
            color: #a52a22;
        }

        .message {
            padding: 13px 15px;
            border-radius: 6px;
            margin-bottom: 18px;
            font-size: 14px;
        }

        .message.error {
            background: #fde8e7;
            color: #a52a22;
        }

        .empty-message {
            color: #687789;
            font-size: 14px;
            padding: 15px 0;
        }

        /* ACCÈS RAPIDES */
        .quick-access {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
        }

        .quick-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .quick-card summary {
            list-style: none;
            cursor: pointer;
            padding: 20px;
            font-size: 15px;
            font-weight: bold;
            color: #003366;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .quick-card summary::-webkit-details-marker {
            display: none;
        }

        .quick-card summary::after {
            content: "+";
            font-size: 22px;
            color: #b38b00;
        }

        .quick-card[open] summary::after {
            content: "−";
        }

        .quick-card summary:hover {
            background: #f0f5fa;
        }

        .quick-links {
            padding: 0 20px 18px;
            border-top: 1px solid #edf0f4;
        }

        .quick-links a {
            display: block;
            color: #40546b;
            text-decoration: none;
            padding: 10px 0;
            border-bottom: 1px solid #edf0f4;
            font-size: 13px;
        }

        .quick-links a:last-child {
            border-bottom: none;
        }

        .quick-links a:hover {
            color: #003366;
            padding-left: 5px;
        }

        .footer {
            margin-top: 35px;
            padding: 18px 0;
            color: #7c8998;
            border-top: 1px solid #e0e5eb;
            font-size: 12px;
            text-align: center;
        }

        /* RESPONSIVE */
        @media (max-width: 1100px) {
            .stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .quick-access {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 700px) {
            .sidebar {
                position: relative;
                width: 100%;
                height: auto;
                max-height: 320px;
            }

            .main {
                margin-left: 0;
                padding: 18px;
            }

            .stats,
            .quick-access {
                grid-template-columns: 1fr;
            }

            .topbar h1 {
                font-size: 23px;
            }

            .availability-section {
                padding: 17px;
            }

            .availability-form {
                align-items: stretch;
            }
        }
    </style>
</head>

<body>

<!-- MENU LATÉRAL -->
<aside class="sidebar">

    <h2>MENU</h2>

    <a href="dashboard.php" class="active">Dashboard</a>

    <div class="dropdown">
        <a href="#">Étudiants</a>
        <div class="dropdown-content">
            <a href="ajouter_etu.php">Ajouter</a>
            <a href="afficher_etu.php">Afficher</a>
            <a href="rapport_etudiants.php">Rapport</a>
        </div>
    </div>

    <div class="dropdown">
        <a href="#">Cours</a>
        <div class="dropdown-content">
            <a href="ajouter_cours.php">Ajouter</a>
            <a href="afficher_cours.php">Afficher</a>
        </div>
    </div>

    <div class="dropdown">
        <a href="#">Facultés</a>
        <div class="dropdown-content">
            <a href="ajouter_fac.php">Ajouter</a>
            <a href="afficher_fac.php">Afficher</a>
        </div>
    </div>

    <div class="dropdown">
        <a href="#">Salles</a>
        <div class="dropdown-content">
            <a href="ajouter_salle.php">Ajouter</a>
            <a href="afficher_salle.php">Afficher</a>
            <a href="disponibilite_salles.php">Disponibilité des salles</a>
        </div>
    </div>

    <div class="dropdown">
        <a href="#">Surveillants</a>
        <div class="dropdown-content">
            <a href="ajouter_sur.php">Ajouter</a>
            <a href="afficher_sur.php">Afficher</a>
            <a href="gerer_disponibilite_surv.php">Disponibilité des surveillants</a>
        </div>
    </div>

    <div class="dropdown">
        <a href="#">Affectations</a>
        <div class="dropdown-content">
            <a href="ajouter_affectation.php">Affectations</a>
            <a href="afficher_affectation_admin.php">Affichage des affectations</a>
            <a href="gerer_disponibilite_surv.php">Disponibilité des surveillants</a>
            <a href="surveillants_affectes.php">Liste des surveillants affectés</a>
            <a href="consulter_etudiants_salle.php">Étudiants par salle</a>
            <a href="recherche_affectation.php">Rechercher les affectations</a>
            <a href="rapport_affectations.php">Rapport d'affectation</a>
        </div>
    </div>

    <div class="dropdown">
        <a href="#">Documents</a>
        <div class="dropdown-content">
            <a href="ajouter_document.php">Partager un document</a>
            <a href="afficher_documents.php">Liste des documents partagés</a>
        </div>
    </div>

    <a href="rapports.php">Rapports PDF</a>
	<a href="rapports_personnalises.php">Rapport personnalise</a>
    <a href="export_excel.php">Export Excel</a>
	<a href="analyse_comparative.php">Comparaison</a>
	<a href="previsions_statistiques.php"> Previsions</a>
    <a href="admin_connexions.php">Historique de connexion</a>
    <a href="tri_affectation.php">Tri des affectations</a>
    <a href="filtrage_affectation.php">Filtrer les affectations</a>
    <a href="historique_activites.php">Journal des activités</a>
    <a href="afficher_archives.php">Archivage</a>
    <a href="deconnexion.php">Déconnexion</a>

</aside>

<!-- CONTENU PRINCIPAL -->
<main class="main">

    <header class="topbar">
        <div>
            <h1>Tableau de bord</h1>
            <p>Bienvenue dans le système de gestion des salles d'examen.</p>
        </div>

        <a href="deconnexion.php" class="logout-button">
            Déconnexion
        </a>
    </header>

    <!-- STATISTIQUES -->
    <section class="stats">

        <div class="stat-card">
            <h3>Total des étudiants</h3>
            <div class="number"><?= e($total_etudiants) ?></div>
        </div>

        <div class="stat-card">
            <h3>Total des salles</h3>
            <div class="number"><?= e($total_salles) ?></div>
        </div>

        <div class="stat-card">
            <h3>Total des surveillants</h3>
            <div class="number"><?= e($total_surveillants) ?></div>
        </div>

        <div class="stat-card">
            <h3>Total des affectations</h3>
            <div class="number"><?= e($total_affectations) ?></div>
        </div>

    </section>

    

    <!-- ACCÈS RAPIDES -->
    <section>
        <h2 class="section-title">Accès rapides</h2>

        <div class="quick-access">

            <?php foreach ($groupes as $titre => $liens): ?>

                <details class="quick-card">

                    <summary><?= e($titre) ?></summary>

                    <div class="quick-links">

                        <?php foreach ($liens as $lien): ?>

                            <a href="<?= e($lien[1]) ?>">
                                <?= e($lien[0]) ?>
                            </a>

                        <?php endforeach; ?>

                    </div>

                </details>

            <?php endforeach; ?>

        </div>
    </section>
	
	<!-- FILTRE COMMUN AUX DEUX DISPONIBILITÉS -->
    <section class="availability-section">

        <h2 class="section-title">Vérifier les disponibilités</h2>

        <form method="GET" action="dashboard.php" class="availability-form">

            <div class="form-group">
                <label for="date_exam">Date de l'examen</label>
                <input
                    type="date"
                    id="date_exam"
                    name="date_exam"
                    value="<?= e($date_disponibilite) ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="heure_debut">Heure de début</label>
                <input
                    type="time"
                    id="heure_debut"
                    name="heure_debut"
                    value="<?= e($heure_debut) ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="heure_fin">Heure de fin</label>
                <input
                    type="time"
                    id="heure_fin"
                    name="heure_fin"
                    value="<?= e($heure_fin) ?>"
                    required
                >
            </div>

            <button type="submit">Vérifier</button>

        </form>

    </section>

	
	
    <!-- DISPONIBILITÉ DES SALLES -->
    <section class="availability-section">

        <h2 class="section-title">Disponibilité des salles</h2>

        <?php if ($erreur_disponibilite !== ""): ?>

            <div class="message error">
                <?= e($erreur_disponibilite) ?>
            </div>

        <?php elseif (empty($salles_disponibilite)): ?>

            <p class="empty-message">
                Aucune salle n'est enregistrée.
            </p>

        <?php else: ?>

            <div class="availability-table-wrapper">

                <table class="availability-table">

                    <thead>
                        <tr>
                            <th>Nom de la salle</th>
                            <th>Capacité</th>
                            <th>Disponibilité</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($salles_disponibilite as $salle): ?>

                            <tr>
                                <td><?= e($salle["nom_salle"]) ?></td>

                                <td><?= e($salle["capacite"]) ?></td>

                                <td>
                                    <?php if ($salle["disponibilite"] === "Disponible"): ?>

                                        <span class="status available">
                                            Disponible
                                        </span>

                                    <?php else: ?>

                                        <span class="status occupied">
                                            Occupée
                                        </span>

                                    <?php endif; ?>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </section>

    <!-- DISPONIBILITÉ DES SURVEILLANTS -->
    <section class="availability-section">

        <h2 class="section-title">Disponibilité des surveillants</h2>

        <?php if ($erreur_surv !== ""): ?>

            <div class="message error">
                <?= e($erreur_surv) ?>
            </div>

        <?php elseif (empty($surveillants_disponibilite)): ?>

            <p class="empty-message">
                Aucun surveillant n'est enregistré.
            </p>

        <?php else: ?>

            <div class="availability-table-wrapper">

                <table class="availability-table">

                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Prénom</th>
                            <th>Disponibilité</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($surveillants_disponibilite as $surv): ?>

                            <tr>
                                <td><?= e($surv["nom_surv"]) ?></td>

                                <td><?= e($surv["prenom_surv"]) ?></td>

                                <td>
                                    <?php if ($surv["disponibilite"] === "Disponible"): ?>

                                        <span class="status available">
                                            Disponible
                                        </span>

                                    <?php else: ?>

                                        <span class="status occupied">
                                            Occupé
                                        </span>

                                    <?php endif; ?>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </section>

    <footer class="footer">
        Système de gestion des salles d'examen
    </footer>

</main>

</body>
</html>