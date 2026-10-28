<?php
session_start();

/* =========================================
   1. SECURITE : ACCES ADMINISTRATEUR
========================================= */
if (
    !isset($_SESSION["id_admin"]) ||
    ($_SESSION["role"] ?? "") !== "admin"
) {
    header("Location: connexion.php");
    exit();
}

require_once("connexionDB.php");

/* =========================================
   2. FILTRES
========================================= */
$id_fac = isset($_GET["id_fac"]) ? (int) $_GET["id_fac"] : 0;
$id_niveau = isset($_GET["id_niveau"]) ? (int) $_GET["id_niveau"] : 0;
$id_cours = isset($_GET["id_cours"]) ? (int) $_GET["id_cours"] : 0;

$periode = isset($_GET["periode"]) ? (int) $_GET["periode"] : 30;

if (!in_array($periode, [30, 90, 365], true)) {
    $periode = 30;
}

$date_debut = date("Y-m-d", strtotime("-" . $periode . " days"));
$date_historique = date("Y-m-01", strtotime("-11 months"));

$erreur = "";
$statistiques = [
    "examens" => 0,
    "etudiants" => 0,
    "salles" => 0,
    "surveillants" => 0
];

$historique = [];
$prevision = 0;
$moyenne_mensuelle = 0;

/* =========================================
   3. CHARGEMENT DES FACULTES ET NIVEAUX
========================================= */
try {
    $req = $bdd->query("
        SELECT id_fac, nom_fac
        FROM faculte
        ORDER BY nom_fac
    ");
    $facultes = $req->fetchAll(PDO::FETCH_ASSOC);

    $req = $bdd->query("
        SELECT id_niveau, nom_niveau
        FROM niveau_etude
        ORDER BY id_niveau
    ");
    $niveaux = $req->fetchAll(PDO::FETCH_ASSOC);

    /* =========================================
       4. CHARGEMENT DES COURS SELON LES FILTRES
    ========================================= */
    $cours = [];

    if ($id_fac > 0 && $id_niveau > 0) {
        $req = $bdd->prepare("
            SELECT id_cours, nom_cours
            FROM cours
            WHERE id_fac = :fac
              AND id_niveau = :niveau
            ORDER BY nom_cours
        ");

        $req->execute([
            ":fac" => $id_fac,
            ":niveau" => $id_niveau
        ]);

        $cours = $req->fetchAll(PDO::FETCH_ASSOC);
    }

    /*
     * On vérifie que le cours sélectionné appartient
     * réellement à la faculté et au niveau choisis.
     */
    if ($id_cours > 0) {
        $cours_valide = false;

        foreach ($cours as $un_cours) {
            if ((int) $un_cours["id_cours"] === $id_cours) {
                $cours_valide = true;
                break;
            }
        }

        if (!$cours_valide) {
            $id_cours = 0;
        }
    }

    /* =========================================
       5. CONSTRUCTION DES CONDITIONS SQL
    ========================================= */

    $conditions = [
        "c.id_fac = :id_fac",
        "c.id_niveau = :id_niveau",
        "a.date_exam >= :date_debut",
        "a.date_exam IS NOT NULL"
    ];

    $parametres = [
        ":id_fac" => $id_fac,
        ":id_niveau" => $id_niveau,
        ":date_debut" => $date_debut
    ];

    if ($id_cours > 0) {
        $conditions[] = "a.id_cours = :id_cours";
        $parametres[":id_cours"] = $id_cours;
    }

    $where = implode(" AND ", $conditions);

    /* =========================================
       6. CALCUL DES STATISTIQUES
    ========================================= */

    if ($id_fac > 0 && $id_niveau > 0) {

        /*
         * IMPORTANT :
         * La faculté et le niveau sont récupérés depuis
         * la table cours, et non depuis affectation.
         *
         * Cela permet de compter les anciennes affectations
         * dont a.id_fac et a.id_niveau sont NULL.
         */

        $sql = "
            SELECT
                COUNT(DISTINCT CONCAT_WS(
                    '|',
                    a.id_cours,
                    a.date_exam,
                    a.heure_debut,
                    a.heure_fin
                )) AS examens,

                COUNT(DISTINCT a.id_etu) AS etudiants,

                COUNT(DISTINCT a.id_salle) AS salles,

                COUNT(DISTINCT a.id_surv) AS surveillants

            FROM affectation a

            INNER JOIN cours c
                ON c.id_cours = a.id_cours

            WHERE $where
        ";

        $req = $bdd->prepare($sql);
        $req->execute($parametres);

        $resultat = $req->fetch(PDO::FETCH_ASSOC);

        if ($resultat) {
            $statistiques["examens"] =
                (int) $resultat["examens"];

            $statistiques["etudiants"] =
                (int) $resultat["etudiants"];

            $statistiques["salles"] =
                (int) $resultat["salles"];

            $statistiques["surveillants"] =
                (int) $resultat["surveillants"];
        }

        /* =========================================
           7. HISTORIQUE MENSUEL DES EXAMENS
        ========================================= */

        $conditions_historique = [
            "c.id_fac = :id_fac",
            "c.id_niveau = :id_niveau",
            "a.date_exam >= :date_historique",
            "a.date_exam IS NOT NULL"
        ];

        $params_historique = [
            ":id_fac" => $id_fac,
            ":id_niveau" => $id_niveau,
            ":date_historique" => $date_historique
        ];

        if ($id_cours > 0) {
            $conditions_historique[] =
                "a.id_cours = :id_cours";

            $params_historique[":id_cours"] = $id_cours;
        }

        $where_historique = implode(
            " AND ",
            $conditions_historique
        );

        $sql_historique = "
            SELECT
                DATE_FORMAT(a.date_exam, '%Y-%m') AS mois,

                COUNT(DISTINCT CONCAT_WS(
                    '|',
                    a.id_cours,
                    a.date_exam,
                    a.heure_debut,
                    a.heure_fin
                )) AS nombre_examens

            FROM affectation a

            INNER JOIN cours c
                ON c.id_cours = a.id_cours

            WHERE $where_historique

            GROUP BY DATE_FORMAT(a.date_exam, '%Y-%m')

            ORDER BY mois ASC
        ";

        $req = $bdd->prepare($sql_historique);
        $req->execute($params_historique);

        $resultats_historique = $req->fetchAll(
            PDO::FETCH_ASSOC
        );

        /*
         * On prépare les 12 derniers mois, même ceux
         * pendant lesquels aucun examen n'a été enregistré.
         */
        $historique_par_mois = [];

        foreach ($resultats_historique as $ligne) {
            $historique_par_mois[$ligne["mois"]] =
                (int) $ligne["nombre_examens"];
        }

        $total_mensuel = 0;
        $nombre_mois = 0;

        for ($i = 11; $i >= 0; $i--) {
            $date_mois = strtotime(
                "first day of this month -$i months"
            );

            $cle_mois = date("Y-m", $date_mois);

            $nombre = $historique_par_mois[$cle_mois] ?? 0;

            $historique[] = [
                "mois" => $cle_mois,
                "libelle" => date("m/Y", $date_mois),
                "nombre" => $nombre
            ];

            $total_mensuel += $nombre;
            $nombre_mois++;
        }

        /* =========================================
           8. PREVISION INDICATIVE
        ========================================= */

        if ($nombre_mois > 0) {
            $moyenne_mensuelle =
                $total_mensuel / $nombre_mois;

            $prevision = (int) round(
                $moyenne_mensuelle * ($periode / 30)
            );
        }
    }

} catch (PDOException $e) {
    $erreur = "Une erreur est survenue lors du calcul des statistiques.";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Prévisions statistiques</title>

    <style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    padding: 25px 30px 30px 275px;
    background: #f3f6fb;
    color: #243247;
    font-family: "Segoe UI", Arial, sans-serif;
    line-height: 1.6;
}

.conteneur {
    width: 100%;
    max-width: 1250px;
    margin: 0 auto;
}

h1 {
    margin: 0 0 8px;
    color: #0b3158;
    font-size: 30px;
    font-weight: 750;
    letter-spacing: -0.5px;
}

.introduction {
    margin: 0 0 28px;
    color: #718096;
    font-size: 15px;
}

.bouton-retour {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    margin-bottom: 22px;
    padding: 10px 16px;
    border: 1px solid #d8e1ec;
    border-radius: 8px;
    background: #fff;
    color: #0b3158;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s ease;
}

.bouton-retour:hover {
    border-color: #0b3158;
    background: #0b3158;
    color: #fff;
    transform: translateY(-1px);
}

.bloc {
    padding: 25px;
    margin-bottom: 25px;
    border: 1px solid #e5ebf3;
    border-radius: 13px;
    background: #fff;
    box-shadow: 0 5px 18px rgba(24, 52, 87, 0.045);
}

.filtres {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 18px;
    align-items: end;
}

.champ label {
    display: block;
    margin-bottom: 8px;
    color: #334761;
    font-size: 13px;
    font-weight: 700;
}

.champ select {
    width: 100%;
    min-width: 0;
    min-height: 45px;
    padding: 10px 12px;
    border: 1px solid #d5deea;
    border-radius: 8px;
    outline: none;
    background: #fbfcfe;
    color: #263b53;
    font-size: 14px;
    transition: border-color 0.2s, box-shadow 0.2s;
}

.champ select:focus {
    border-color: #2563a6;
    box-shadow: 0 0 0 3px rgba(37, 99, 166, 0.12);
}

.bouton {
    display: inline-block;
    width: 100%;
    min-height: 45px;
    padding: 11px 16px;
    border: none;
    border-radius: 8px;
    background: #103c69;
    color: #fff;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    transition: background 0.2s, transform 0.2s;
}

.bouton:hover {
    background: #18558f;
    transform: translateY(-1px);
}

.cartes {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 18px;
    margin-bottom: 25px;
}

.carte {
    position: relative;
    overflow: hidden;
    padding: 23px 20px;
    border: 1px solid #e4eaf2;
    border-radius: 12px;
    background: #fff;
    box-shadow: 0 4px 15px rgba(24, 52, 87, 0.04);
}

.carte::before {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: #174d7c;
    content: "";
}

.carte h3 {
    margin: 0 0 14px;
    color: #718096;
    font-size: 13px;
    font-weight: 600;
}

.valeur {
    color: #103c69;
    font-size: 32px;
    font-weight: 800;
    line-height: 1.25;
    overflow-wrap: anywhere;
}

h2 {
    margin: 0 0 12px;
    color: #103c69;
    font-size: 21px;
    font-weight: 750;
}

.description {
    margin: 0 0 15px;
    color: #718096;
    font-size: 14px;
    line-height: 1.7;
}

.table-responsive {
    width: 100%;
    overflow-x: auto;
}

table {
    width: 100%;
    margin-top: 18px;
    border-collapse: collapse;
    font-size: 14px;
}

th,
td {
    padding: 14px 16px;
    border-bottom: 1px solid #e7edf4;
    text-align: left;
}

th {
    background: #103c69;
    color: #fff;
    font-size: 13px;
    font-weight: 700;
}

th:first-child {
    border-radius: 7px 0 0 0;
}

th:last-child {
    border-radius: 0 7px 0 0;
}

tbody tr:nth-child(even) {
    background: #f7f9fc;
}

tbody tr:hover {
    background: #edf4fb;
}

.message {
    padding: 15px 18px;
    margin-bottom: 20px;
    border: 1px solid #f0d99a;
    border-radius: 8px;
    background: #fff9e9;
    color: #795b13;
    font-size: 14px;
}

.erreur {
    border-color: #f1c0c4;
    background: #fff0f1;
    color: #9d2733;
}

.note {
    padding: 15px 18px;
    margin-top: 22px;
    border-left: 4px solid #d4a537;
    border-radius: 5px;
    background: #fff9eb;
    color: #70591f;
    font-size: 13px;
    line-height: 1.7;
}

@media (max-width: 1100px) {
    .filtres {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .cartes {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 700px) {
    body {
        padding: 20px 15px;
    }

    h1 {
        font-size: 25px;
    }

    .bloc {
        padding: 18px;
    }

    .filtres,
    .cartes {
        grid-template-columns: 1fr;
    }

    .valeur {
        font-size: 29px;
    }
}


    </style>
</head>

<body>


<body>

<?php include("menu_principal.php"); ?>

<div class="conteneur">

    <a href="dashboard.php" class="bouton-retour">
         Retour au tableau de bord
    </a>



    <h1>Prévisions statistiques</h1>

    <p class="introduction">
        Consultez les statistiques des examens selon la faculté,
        le niveau d'études, le cours et la période sélectionnés.
    </p>

    <?php if ($erreur !== ""): ?>

        <div class="message erreur">
            <?= htmlspecialchars($erreur) ?>
            Vérifiez la connexion à la base de données.
        </div>

    <?php endif; ?>

    <!-- FILTRES -->
    <div class="bloc">

        <form method="GET" action="previsions_statistiques.php">

            <div class="filtres">

                <div class="champ">
                    <label for="id_fac">Faculté</label>

                    <select name="id_fac" id="id_fac" required>
                        <option value="">Choisir une faculté</option>

                        <?php foreach ($facultes as $fac): ?>
                            <option
                                value="<?= (int) $fac["id_fac"] ?>"
                                <?= $id_fac === (int) $fac["id_fac"] ? "selected" : "" ?>
                            >
                                <?= htmlspecialchars($fac["nom_fac"]) ?>
                            </option>
                        <?php endforeach; ?>

                    </select>
                </div>

                <div class="champ">
                    <label for="id_niveau">Niveau</label>

                    <select name="id_niveau" id="id_niveau" required>
                        <option value="">Choisir un niveau</option>

                        <?php foreach ($niveaux as $niv): ?>
                            <option
                                value="<?= (int) $niv["id_niveau"] ?>"
                                <?= $id_niveau === (int) $niv["id_niveau"] ? "selected" : "" ?>
                            >
                                <?= htmlspecialchars($niv["nom_niveau"]) ?>
                            </option>
                        <?php endforeach; ?>

                    </select>
                </div>

                <div class="champ">
                    <label for="id_cours">Cours</label>

                    <select name="id_cours" id="id_cours">
                        <option value="0">Tous les cours</option>

                        <?php foreach ($cours as $un_cours): ?>
                            <option
                                value="<?= (int) $un_cours["id_cours"] ?>"
                                <?= $id_cours === (int) $un_cours["id_cours"] ? "selected" : "" ?>
                            >
                                <?= htmlspecialchars($un_cours["nom_cours"]) ?>
                            </option>
                        <?php endforeach; ?>

                    </select>
                </div>

                <div class="champ">
                    <label for="periode">Période</label>

                    <select name="periode" id="periode">
                        <option value="30" <?= $periode === 30 ? "selected" : "" ?>>
                            30 derniers jours
                        </option>

                        <option value="90" <?= $periode === 90 ? "selected" : "" ?>>
                            90 derniers jours
                        </option>

                        <option value="365" <?= $periode === 365 ? "selected" : "" ?>>
                            12 derniers mois
                        </option>
                    </select>
                </div>

            </div>

            <div style="margin-top: 20px; max-width: 220px;">
                <button type="submit" class="bouton">
                    Afficher les statistiques
                </button>
            </div>

        </form>

    </div>

    <?php if ($id_fac <= 0 || $id_niveau <= 0): ?>

        <div class="message">
            Sélectionnez une faculté et un niveau, puis cliquez sur
            « Afficher les statistiques ».
        </div>

    <?php else: ?>

        <!-- CARTES STATISTIQUES -->
        <div class="cartes">

            <div class="carte">
                <h3>Examens distincts</h3>
                <div class="valeur">
                    <?= number_format($statistiques["examens"], 0, ",", " ") ?>
                </div>
            </div>

            <div class="carte">
                <h3>Étudiants concernés</h3>
                <div class="valeur">
                    <?= number_format($statistiques["etudiants"], 0, ",", " ") ?>
                </div>
            </div>

            <div class="carte">
                <h3>Salles utilisées</h3>
                <div class="valeur">
                    <?= number_format($statistiques["salles"], 0, ",", " ") ?>
                </div>
            </div>

            <div class="carte">
                <h3>Surveillants concernés</h3>
                <div class="valeur">
                    <?= number_format($statistiques["surveillants"], 0, ",", " ") ?>
                </div>
            </div>

        </div>

        <!-- PREVISION -->
        <div class="bloc">

            <h2>Prévision indicative</h2>

            <p class="description">
                Estimation basée sur le nombre moyen d'examens par mois
                au cours des 12 derniers mois, pour la faculté, le niveau
                et, si sélectionné, le cours choisi.
            </p>

            <div class="valeur">
                <?= number_format($prevision, 0, ",", " ") ?>
                examen(s)
            </div>

            <p class="description">
                Moyenne historique :
                <?= number_format($moyenne_mensuelle, 2, ",", " ") ?>
                examen(s) par mois.
            </p>

            <div class="note">
                Cette prévision est une estimation simple, et non une
                garantie du nombre d'examens futurs. Sa fiabilité dépend
                de la qualité et de la quantité des données historiques.
            </div>

        </div>

        <!-- HISTORIQUE -->
        <div class="bloc">

            <h2>Historique mensuel des examens</h2>

            <p class="description">
                Nombre d'examens distincts enregistrés au cours
                des 12 derniers mois.
            </p>

            <?php if (array_sum(array_column($historique, "nombre")) === 0): ?>

                <div class="message">
                    Aucun examen historique trouvé pour ces filtres.
                    Vérifiez les affectations et les cours associés.
                </div>

            <?php else: ?>

                <div class="table-responsive">

                    <table>
                        <thead>
                            <tr>
                                <th>Mois</th>
                                <th>Nombre d'examens</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($historique as $ligne): ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars($ligne["libelle"]) ?>
                                    </td>

                                    <td>
                                        <?= number_format(
                                            $ligne["nombre"],
                                            0,
                                            ",",
                                            " "
                                        ) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                        </tbody>
                    </table>

                </div>

            <?php endif; ?>

        </div>

    <?php endif; ?>

</div>

</body>
</html>