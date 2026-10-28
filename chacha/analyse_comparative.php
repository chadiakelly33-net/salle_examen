<?php
session_start();

if (
    !isset($_SESSION["id_admin"]) ||
    ($_SESSION["role"] ?? "") !== "admin"
) {
    header("Location: connexion.php");
    exit();
}

require_once("connexionDB.php");

function echapper($valeur) {
    return htmlspecialchars((string)$valeur, ENT_QUOTES, "UTF-8");
}

$analyses = [
    "etudiants_faculte" => [
        "titre" => "Étudiants par faculté",
        "description" => "Comparer les effectifs des facultés.",
        "icone" => "01"
    ],
    "etudiants_niveau" => [
        "titre" => "Étudiants par niveau",
        "description" => "Observer la répartition par niveau d'étude.",
        "icone" => "02"
    ],
    "salles" => [
        "titre" => "Occupation des salles",
        "description" => "Comparer les étudiants affectés à chaque salle.",
        "icone" => "03"
    ],
    "examens_date" => [
        "titre" => "Examens par date",
        "description" => "Observer la répartition des examens par date.",
        "icone" => "04"
    ],
    "cours" => [
        "titre" => "Affectations par cours",
        "description" => "Comparer les affectations par cours.",
        "icone" => "05"
    ],
    "surveillants" => [
        "titre" => "Charge des surveillants",
        "description" => "Comparer la charge de travail des surveillants.",
        "icone" => "06"
    ],
    "connexions" => [
        "titre" => "Connexions réussies et échouées",
        "description" => "Examiner les résultats des tentatives de connexion.",
        "icone" => "07"
    ],
    "activites" => [
        "titre" => "Journal des activités",
        "description" => "Consulter les activités enregistrées dans le système.",
        "icone" => "08"
    ]
];

$statistiques = [
    "Étudiants" => "etudiant",
    "Cours" => "cours",
    "Salles" => "salle",
    "Surveillants" => "surveillant",
    "Affectations" => "affectation"
];

$totaux = [];

try {
    $requeteTables = $bdd->query("SHOW TABLES");
    $tables = $requeteTables->fetchAll(PDO::FETCH_COLUMN);

    foreach ($statistiques as $libelle => $table) {
        if (in_array($table, $tables, true)) {
            $totaux[$libelle] = (int)$bdd
                ->query("SELECT COUNT(*) FROM `$table`")
                ->fetchColumn();
        } else {
            $totaux[$libelle] = 0;
        }
    }
} catch (PDOException $e) {
    $totaux = [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Analyse comparative</title>

<style>
* { box-sizing: border-box; }

body {
    margin: 0;
    padding: 25px;
    font-family: Arial, sans-serif;
    background: #f3f6fa;
    color: #243447;
}

.conteneur {
    max-width: 1150px;
    margin: auto;
}

.entete {
    background: #003366;
    color: white;
    padding: 28px;
    border-radius: 12px;
    margin-bottom: 25px;
}

.entete h1 { margin-top: 0; }
.entete p { line-height: 1.7; margin-bottom: 0; }

.statistiques {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin-bottom: 30px;
}

.stat {
    background: white;
    padding: 20px;
    border: 1px solid #dce4ed;
    border-radius: 10px;
}

.stat span {
    color: #667085;
    font-size: 14px;
}

.stat strong {
    display: block;
    margin-top: 10px;
    color: #003366;
    font-size: 30px;
}

.titre-section {
    color: #003366;
    margin: 30px 0 15px;
}

.grille {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(235px, 1fr));
    gap: 18px;
}

.carte {
    display: block;
    background: white;
    color: #243447;
    padding: 22px;
    border: 1px solid #dce4ed;
    border-radius: 10px;
    text-decoration: none;
    transition: 0.2s;
}

.carte:hover {
    border-color: #003366;
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(0, 51, 102, 0.08);
}

.numero {
    display: inline-block;
    background: #eaf2fc;
    color: #003366;
    font-weight: bold;
    padding: 9px 12px;
    border-radius: 7px;
}

.carte h3 {
    color: #003366;
    font-size: 17px;
    margin: 18px 0 10px;
}

.carte p {
    color: #667085;
    font-size: 14px;
    line-height: 1.6;
    min-height: 44px;
}

.lien {
    display: inline-block;
    color: #003366;
    font-weight: bold;
    margin-top: 8px;
}

.bouton {
    display: inline-block;
    margin-top: 28px;
    padding: 12px 18px;
    border-radius: 6px;
    background: #003366;
    color: white;
    text-decoration: none;
}

.bouton:hover { background: #004b8d; }

@media(max-width: 600px) {
    body { padding: 12px; }
    .entete { padding: 20px; }
}
</style>
</head>

<body>
<div class="conteneur">

    <header class="entete">
        <h1>Analyse comparative</h1>
        <p>
            Tableau de bord analytique : consultez les indicateurs
            et explorez les différentes analyses de votre système
            de gestion des examens.
        </p>
    </header>

    <h2 class="titre-section">Vue d'ensemble</h2>

    <div class="statistiques">
        <?php foreach ($statistiques as $libelle => $table): ?>
            <div class="stat">
                <span><?= echapper($libelle) ?></span>
                <strong><?= $totaux[$libelle] ?? "—" ?></strong>
            </div>
        <?php endforeach; ?>
    </div>

    <h2 class="titre-section">Toutes les analyses</h2>

    <div class="grille">
        <?php foreach ($analyses as $cle => $analyse): ?>
            <a class="carte"
               href="detail_analyse.php?type=<?= urlencode($cle) ?>">

                <span class="numero">
                    <?= echapper($analyse["icone"]) ?>
                </span>

                <h3><?= echapper($analyse["titre"]) ?></h3>

                <p><?= echapper($analyse["description"]) ?></p>

                <span class="lien">Consulter l'analyse</span>
            </a>
        <?php endforeach; ?>
    </div>

    <a href="dashboard.php" class="bouton">
         Retour au tableau de bord
    </a>

</div>
</body>
</html>

