<?php
session_start();

require_once __DIR__ . '/connexionDB.php';

// 1. Vérifier la connexion du surveillant
if (
    !isset($_SESSION['id_surv']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'surveillant'
) {
    header('Location: login_surv.php');
    exit;
}

// 2. Vérifier le délai d'inactivité : 5 minutes
$timeout = 300;

if (
    isset($_SESSION['last_activity']) &&
    time() - $_SESSION['last_activity'] > $timeout
) {
    session_unset();
    session_destroy();

    header('Location: login_surv.php?timeout=1');
    exit;
}

$_SESSION['last_activity'] = time();

$id_surv = (int) $_SESSION['id_surv'];

// 3. Récupérer le texte recherché
$recherche = trim($_GET['recherche'] ?? '');

// 4. Récupérer uniquement les documents actifs
// partagés avec ce surveillant
$sql = "
    SELECT DISTINCT
        d.id_document,
        d.nom_document,
        d.description,
        d.date_ajout
    FROM document d
    INNER JOIN partage_document p
        ON p.id_document = d.id_document
    WHERE d.archive = 0
      AND p.type_destinataire = 'surveillant'
      AND p.id_surv = :id_surv
";

$params = [
    'id_surv' => $id_surv
];

// 5. Ajouter la recherche par nom ou description
if ($recherche !== '') {
    $sql .= "
        AND (
            d.nom_document LIKE :recherche_nom
            OR d.description LIKE :recherche_description
        )
    ";

    $params['recherche_nom'] = '%' . $recherche . '%';
    $params['recherche_description'] = '%' . $recherche . '%';
}

$sql .= " ORDER BY d.date_ajout DESC";

$requete = $bdd->prepare($sql);
$requete->execute($params);

$documents = $requete->fetchAll(PDO::FETCH_ASSOC);
?>

<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Documents partagés</title>

    <style>
        <style>
    * {
        box-sizing: border-box;
    }

    body {
        font-family: Arial, sans-serif;
        background: #f4f6f9;
        margin: 0;
        padding: 0;
        color: #333;
    }

    /* MENU LATÉRAL */
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
    }

    .sidebar h3 {
        color: white;
        text-align: center;
        margin-bottom: 30px;
        font-size: 19px;
    }

    .sidebar a {
        display: block;
        color: white;
        text-decoration: none;
        padding: 13px 12px;
        margin-bottom: 7px;
        border-radius: 6px;
        font-size: 14px;
        transition: 0.3s;
    }

    .sidebar a:hover,
    .sidebar a.active {
        background: #d4a72c;
        color: #003366;
        font-weight: bold;
    }

    .sidebar .logout {
        margin-top: 25px;
        border-top: 1px solid rgba(255,255,255,0.25);
        padding-top: 18px;
    }

    .badge-menu {
        background: #d4a72c;
        color: #003366;
        padding: 3px 7px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: bold;
        float: right;
    }

    /* CONTENU PRINCIPAL */
    .container {
        max-width: 1200px;
        margin-left: 230px;
        padding: 35px 40px;
    }

    .container h2 {
        color: #003366;
        font-size: 27px;
        margin-bottom: 10px;
    }

    .description {
        color: #666;
        margin-bottom: 25px;
        line-height: 1.6;
    }

    .navigation {
        margin-bottom: 22px;
    }

    .btn {
        display: inline-block;
        padding: 11px 17px;
        border: none;
        border-radius: 6px;
        background: #003366;
        color: white;
        text-decoration: none;
        cursor: pointer;
        font-size: 14px;
        transition: 0.3s;
    }

    .btn:hover {
        background: #d4a72c;
        color: #003366;
    }

    .reinitialiser {
        background: #6c757d;
    }

    .reinitialiser:hover {
        background: #555;
        color: white;
    }

    /* RECHERCHE */
    .search-form {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin: 25px 0;
    }

    .search-form input {
        flex: 1;
        min-width: 220px;
        padding: 12px;
        border: 1px solid #ccc;
        border-radius: 6px;
        font-size: 14px;
        outline: none;
    }

    .search-form input:focus {
        border-color: #003366;
        box-shadow: 0 0 4px rgba(0,51,102,0.2);
    }

    /* TABLEAU */
    .table-container {
        overflow-x: auto;
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.06);
        margin-top: 15px;
    }

    table {
        width: 100%;
        min-width: 750px;
        border-collapse: collapse;
    }

    th, td {
        padding: 15px;
        border-bottom: 1px solid #e5e5e5;
        text-align: left;
        line-height: 1.5;
    }

    th {
        background: #003366;
        color: white;
        font-size: 14px;
    }

    tbody tr:hover {
        background: #fff9e8;
    }

    td {
        font-size: 14px;
    }

    /* ACTIONS */
    .action {
        display: inline-block;
        padding: 8px 12px;
        margin: 3px;
        border-radius: 5px;
        text-decoration: none;
        color: white;
        font-size: 13px;
        transition: 0.3s;
    }

    .consulter {
        background: #003366;
    }

    .telecharger {
        background: #198754;
    }

    .action:hover {
        opacity: 0.8;
        transform: translateY(-1px);
    }

    /* RÉSULTATS */
    .vide {
        background: white;
        padding: 30px;
        text-align: center;
        color: #666;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        margin-top: 15px;
    }

    /* RESPONSIVE */
    @media (max-width: 768px) {
        .sidebar {
            position: relative;
            width: 100%;
            height: auto;
            padding: 15px;
        }

        .sidebar h3 {
            margin-bottom: 15px;
        }

        .container {
            margin-left: 0;
            padding: 20px 15px;
        }

        .container h2 {
            font-size: 23px;
        }

        .search-form {
            flex-direction: column;
        }

        .search-form input {
            width: 100%;
        }

        .search-form .btn {
            text-align: center;
        }
    }
</style>

    </style>
</head>

<body>

<div class="container">

    <h2>Documents partagés</h2>

    <p class="description">
        Consultez et téléchargez les documents actifs mis à votre disposition
        par l'administration.
    </p>

    <div class="navigation">
        <a class="btn" href="espace_surv.php">
            Retour à mon espace
        </a>
    </div>

    <!-- Formulaire de recherche -->
    <form
        method="GET"
        action="documents_partages_surv.php"
        class="search-form"
    >

        <input
            type="text"
            name="recherche"
            placeholder="Rechercher par nom ou description..."
            value="<?= htmlspecialchars(
                $recherche,
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
        >

        <button type="submit" class="btn">
            Rechercher
        </button>

        <a
            href="documents_partages_surv.php"
            class="btn reinitialiser"
        >
            Réinitialiser
        </a>

    </form>

    <p>
        Documents trouvés :
        <strong><?= count($documents) ?></strong>
    </p>

    <?php if (empty($documents)): ?>

        <div class="vide">
            <?php if ($recherche !== ''): ?>
                Aucun document actif ne correspond à votre recherche.
            <?php else: ?>
                Aucun document actif ne vous a été partagé pour le moment.
            <?php endif; ?>
        </div>

    <?php else: ?>

        <div class="table-container">

            <table>
                <thead>
                    <tr>
                        <th>N°</th>
                        <th>Nom du document</th>
                        <th>Description</th>
                        <th>Date d'ajout</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($documents as $document): ?>

                    <?php
                    $id_document = (int) $document['id_document'];
                    ?>

                    <tr>
                        <td>
                            <?= $id_document ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $document['nom_document'] ?? '',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $document['description'] ?? '',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $document['date_ajout'] ?? '',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </td>

                        <td>
                            <a
                                class="action consulter"
                                href="consulter_document_surv.php?id=<?= $id_document ?>"
                                target="_blank"
                                rel="noopener"
                            >
                                Consulter
                            </a>

                            <a
                                class="action telecharger"
                                href="telecharger_document_surv.php?id=<?= $id_document ?>"
                            >
                                Télécharger
                            </a>
                        </td>
                    </tr>

                <?php endforeach; ?>

                </tbody>
            </table>

        </div>

    <?php endif; ?>

</div>

</body>
</html>

