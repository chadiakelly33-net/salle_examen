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

function echapper($valeur)
{
    return htmlspecialchars(
        (string)$valeur,
        ENT_QUOTES,
        "UTF-8"
    );
}

// Les analyses disponibles
$analyses = [
    "etudiants_faculte" => "Étudiants par faculté",
    "etudiants_niveau" => "Étudiants par niveau",
    "salles" => "Occupation des salles",
    "examens_date" => "Examens par date",
    "cours" => "Affectations par cours",
    "surveillants" => "Charge des surveillants",
    "connexions" => "Connexions réussies et échouées",
    "activites" => "Journal des activités"
];

// Vérifier l'analyse demandée
$type = $_GET["type"] ?? "";

if (!isset($analyses[$type])) {
    header("Location: analyse_comparative.php");
    exit();
}

$titre = $analyses[$type];
$resultats = [];
$colonnes = [];
$message = "";
$typeGraphique = "bar";
$labelsGraphique = [];
$valeursGraphique = [];

try {

    switch ($type) {

        // 1. Étudiants par faculté
        case "etudiants_faculte":

            $sql = "
                SELECT
                    f.nom_fac AS libelle,
                    COUNT(e.id_etu) AS total
                FROM faculte f
                LEFT JOIN etudiant e
                    ON e.id_fac = f.id_fac
                GROUP BY f.id_fac, f.nom_fac
                ORDER BY total DESC
            ";

            $colonnes = [
                "libelle" => "Faculté",
                "total" => "Nombre d'étudiants"
            ];

            break;


        // 2. Étudiants par niveau
        case "etudiants_niveau":

            $sql = "
                SELECT
                    n.nom_niveau AS libelle,
                    COUNT(e.id_etu) AS total
                FROM niveau_etude n
                LEFT JOIN etudiant e
                    ON e.id_niveau = n.id_niveau
                GROUP BY n.id_niveau, n.nom_niveau
                ORDER BY total DESC
            ";

            $colonnes = [
                "libelle" => "Niveau d'étude",
                "total" => "Nombre d'étudiants"
            ];

            break;


        // 3. Occupation des salles
        case "salles":

            $sql = "
                SELECT
                    s.nom_salle AS libelle,
                    s.capacite AS capacite,
                    COUNT(DISTINCT a.id_etu) AS total
                FROM salle s
                LEFT JOIN affectation a
                    ON a.id_salle = s.id_salle
                GROUP BY
                    s.id_salle,
                    s.nom_salle,
                    s.capacite
                ORDER BY total DESC
            ";

            $colonnes = [
                "libelle" => "Salle",
                "capacite" => "Capacité",
                "total" => "Étudiants affectés"
            ];

            break;


        // 4. Examens par date
        case "examens_date":

            $sql = "
                SELECT
                    DATE_FORMAT(date_exam, '%d/%m/%Y') AS libelle,
                    COUNT(*) AS total
                FROM affectation
                GROUP BY date_exam
                ORDER BY date_exam ASC
            ";

            $colonnes = [
                "libelle" => "Date d'examen",
                "total" => "Nombre d'affectations"
            ];

            $typeGraphique = "line";

            break;


        // 5. Affectations par cours
        case "cours":

            $sql = "
                SELECT
                    c.nom_cours AS libelle,
                    COUNT(a.id_affectation) AS total
                FROM cours c
                LEFT JOIN affectation a
                    ON a.id_cours = c.id_cours
                GROUP BY
                    c.id_cours,
                    c.nom_cours
                ORDER BY total DESC
            ";

            $colonnes = [
                "libelle" => "Cours",
                "total" => "Nombre d'affectations"
            ];

            break;


        // 6. Charge des surveillants
        case "surveillants":

            $sql = "
                SELECT
                    CONCAT(
                        s.nom_surv,
                        ' ',
                        s.prenom_surv
                    ) AS libelle,
                    COUNT(a.id_affectation) AS total
                FROM surveillant s
                LEFT JOIN affectation a
                    ON a.id_surv = s.id_surv
                GROUP BY
                    s.id_surv,
                    s.nom_surv,
                    s.prenom_surv
                ORDER BY total DESC
            ";

            $colonnes = [
                "libelle" => "Surveillant",
                "total" => "Nombre d'affectations"
            ];

            break;


        // 7. Connexions réussies et échouées
        case "connexions":

            $sql = "
                SELECT
                    statut AS libelle,
                    COUNT(*) AS total
                FROM connexion_log
                GROUP BY statut
                ORDER BY total DESC
            ";

            $colonnes = [
                "libelle" => "Statut de connexion",
                "total" => "Nombre de connexions"
            ];

            $typeGraphique = "doughnut";

            break;


        // 8. Journal des activités
        case "activites":

            $sql = "
                SELECT *
                FROM journal_activite
                ORDER BY 1 DESC
                LIMIT 200
            ";

            break;
    }

    $requete = $bdd->query($sql);
    $resultats = $requete->fetchAll(PDO::FETCH_ASSOC);

    // Construire les colonnes du journal des activités
    if ($type === "activites" && !empty($resultats)) {

        foreach (array_keys($resultats[0]) as $colonne) {

            $colonnes[$colonne] = ucfirst(
                str_replace("_", " ", $colonne)
            );
        }
    }

    // Préparer les données du graphique
    foreach ($resultats as $ligne) {

        if (
            isset($ligne["libelle"]) &&
            isset($ligne["total"])
        ) {
            $labelsGraphique[] = (string)$ligne["libelle"];
            $valeursGraphique[] = (int)$ligne["total"];
        }
    }

} catch (PDOException $e) {

    // Ne pas exposer les détails SQL aux utilisateurs
    $message = "Impossible de charger cette analyse. Vérifie la structure de la base de données.";
}

$totalLignes = count($resultats);
$totalValeurs = array_sum($valeursGraphique);

$maximum = !empty($valeursGraphique)
    ? max($valeursGraphique)
    : 0;
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title><?= echapper($titre) ?></title>

    <!-- Bibliothèque Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        * {
            box-sizing: border-box;
        }

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

        .bouton-retour {
            display: inline-block;
            padding: 12px 18px;
            margin-bottom: 22px;
            background: #003366;
            color: white;
            text-decoration: none;
            border-radius: 7px;
            transition: background 0.2s;
        }

        .bouton-retour:hover {
            background: #004b8d;
        }

        .entete {
            background: #003366;
            color: white;
            padding: 28px;
            border-radius: 12px;
            margin-bottom: 22px;
        }

        .entete h1 {
            margin-top: 0;
            margin-bottom: 12px;
        }

        .entete p {
            margin-bottom: 0;
            line-height: 1.7;
        }

        .statistiques {
            display: grid;
            grid-template-columns: repeat(
                auto-fit,
                minmax(180px, 1fr)
            );
            gap: 16px;
            margin-bottom: 22px;
        }

        .stat {
            background: white;
            border: 1px solid #dce4ed;
            border-radius: 10px;
            padding: 22px;
        }

        .stat span {
            color: #667085;
            font-size: 14px;
        }

        .stat strong {
            display: block;
            color: #003366;
            font-size: 30px;
            margin-top: 10px;
        }

        .resultat {
            background: white;
            border: 1px solid #dce4ed;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 22px;
        }

        .resultat h2 {
            margin-top: 0;
            color: #003366;
        }

        .graphique-conteneur {
            position: relative;
            width: 100%;
            max-width: 1000px;
            height: 380px;
            margin: 25px auto 35px;
        }

        .table-conteneur {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 18px;
        }

        th,
        td {
            padding: 13px;
            border: 1px solid #dce4ed;
            text-align: left;
        }

        th {
            background: #003366;
            color: white;
        }

        tr:nth-child(even) {
            background: #f7f9fc;
        }

        .barre {
            width: 100%;
            min-width: 80px;
            height: 12px;
            background: #e4eaf2;
            border-radius: 8px;
            overflow: hidden;
        }

        .barre-interne {
            height: 100%;
            background: #d6a328;
            border-radius: 8px;
        }

        .message {
            padding: 16px;
            background: #fff4df;
            color: #7a4b00;
            border-radius: 8px;
            line-height: 1.6;
        }

        .pied {
            text-align: center;
            color: #667085;
            font-size: 13px;
            margin: 25px 0 10px;
        }

        @media (max-width: 600px) {
            body {
                padding: 12px;
            }

            .entete,
            .resultat {
                padding: 18px;
            }

            .graphique-conteneur {
                height: 300px;
            }
        }
    </style>
</head>

<body>

<div class="conteneur">


    <header class="entete">

        <h1><?= echapper($titre) ?></h1>

        <p>
            Consultez les données détaillées et leur représentation
            graphique pour faciliter la comparaison des résultats.
        </p>

    </header>

    <?php if ($message !== ""): ?>

        <section class="resultat">
            <div class="message">
                <?= echapper($message) ?>
            </div>
        </section>

    <?php else: ?>

        <div class="statistiques">

            <div class="stat">
                <span>Nombre de lignes</span>
                <strong><?= $totalLignes ?></strong>
            </div>

            <?php if ($type !== "activites"): ?>

                <div class="stat">
                    <span>Total des valeurs comparées</span>
                    <strong><?= $totalValeurs ?></strong>
                </div>

                <div class="stat">
                    <span>Valeur maximale</span>
                    <strong><?= $maximum ?></strong>
                </div>

            <?php endif; ?>

        </div>

        <?php if (empty($resultats)): ?>

            <section class="resultat">
                <div class="message">
                    Aucune donnée à afficher pour cette analyse.
                </div>
            </section>

        <?php else: ?>

            <?php if (!empty($labelsGraphique)): ?>

                <section class="resultat">

                    <h2>Représentation graphique</h2>

                    <p>
                        Comparez visuellement les résultats
                        affichés dans le tableau.
                    </p>

                    <div class="graphique-conteneur">
                        <canvas
                            id="graphiqueAnalyse"
                            role="img"
                            aria-label="<?= echapper($titre) ?>"
                        ></canvas>
                    </div>

                </section>

            <?php endif; ?>

            <section class="resultat">

                <h2>Tableau détaillé</h2>

                <div class="table-conteneur">

                    <table>

                        <thead>
                            <tr>

                                <?php foreach ($colonnes as $libelle): ?>
                                    <th>
                                        <?= echapper($libelle) ?>
                                    </th>
                                <?php endforeach; ?>

                                <?php if (isset($colonnes["total"])): ?>
                                    <th>Comparaison visuelle</th>
                                <?php endif; ?>

                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($resultats as $ligne): ?>

                                <tr>

                                    <?php foreach ($colonnes as $cle => $libelle): ?>

                                        <td>
                                            <?= echapper($ligne[$cle] ?? "") ?>
                                        </td>

                                    <?php endforeach; ?>

                                    <?php if (isset($colonnes["total"])): ?>

                                        <?php
                                        $valeur = (int)($ligne["total"] ?? 0);

                                        $pourcentage = $maximum > 0
                                            ? ($valeur / $maximum) * 100
                                            : 0;
                                        ?>

                                        <td>
                                            <div class="barre">
                                                <div
                                                    class="barre-interne"
                                                    style="width: <?= $pourcentage ?>%;"
                                                ></div>
                                            </div>
                                        </td>

                                    <?php endif; ?>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            </section>

        <?php endif; ?>

    <?php endif; ?>

    <a
        href="analyse_comparative.php"
        class="bouton-retour"
    >
         Retour à l'analyse comparative
    </a>

    <div class="pied">
        Système de gestion des salles d'examen
    </div>

</div>

<?php if (!empty($labelsGraphique)): ?>

<script>
const elementGraphique = document.getElementById('graphiqueAnalyse');

if (elementGraphique && typeof Chart !== 'undefined') {

    const labels = <?= json_encode(
        $labelsGraphique,
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
    ) ?>;

    const valeurs = <?= json_encode(
        $valeursGraphique,
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
    ) ?>;

    const typeGraphique = <?= json_encode($typeGraphique) ?>;
    const titreGraphique = <?= json_encode(
        $titre,
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
    ) ?>;

    new Chart(elementGraphique, {
        type: typeGraphique,

        data: {
            labels: labels,

            datasets: [{
                label: 'Nombre',
                data: valeurs,

                backgroundColor: typeGraphique === 'doughnut'
                    ? [
                        '#003366',
                        '#d6a328',
                        '#3b82a0',
                        '#6b8e23',
                        '#8b5e83',
                        '#cc6b49'
                    ]
                    : 'rgba(0, 51, 102, 0.78)',

                borderColor: '#d6a328',
                borderWidth: typeGraphique === 'doughnut' ? 2 : 1,
                borderRadius: typeGraphique === 'bar' ? 5 : 0,

                tension: 0.25,
                fill: false
            }]
        },

        options: {
            responsive: true,
            maintainAspectRatio: false,

            plugins: {
                legend: {
                    display: typeGraphique === 'doughnut',
                    position: 'bottom'
                },

                title: {
                    display: true,
                    text: titreGraphique,
                    font: {
                        size: 16
                    }
                },

                tooltip: {
                    enabled: true
                }
            },

            scales: typeGraphique === 'doughnut'
                ? {}
                : {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
        }
    });
}
</script>

<?php endif; ?>

</body>
</html>

