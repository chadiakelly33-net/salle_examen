<?php

session_start();

// Vérifier que l'utilisateur est un administrateur
if (
    !isset($_SESSION["id_admin"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: connexion.php");
    exit();
}

require_once("connexionDB.php");

// Créer un jeton de sécurité pour les formulaires
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// Recherche des documents
$recherche = trim($_GET["recherche"] ?? "");

$sql = "
    SELECT id_document, nom_document, fichier, description, date_ajout
    FROM document
    WHERE archive = 0
";

$params = [];

if ($recherche !== "") {
    $sql .= "
        AND (
            nom_document LIKE ?
            OR description LIKE ?
        )
    ";

    $params[] = "%" . $recherche . "%";
    $params[] = "%" . $recherche . "%";
}

$sql .= " ORDER BY date_ajout DESC";

$req = $bdd->prepare($sql);
$req->execute($params);

$documents = $req->fetchAll(PDO::FETCH_ASSOC);
require_once __DIR__ . "/../session_timeout.php";

?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Gestion des documents</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            margin: 0;
            padding: 25px;
            color: #263238;
        }

        .container {
            max-width: 1150px;
            margin: 20px auto;
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px #ddd;
        }

        h1 {
            color: #003366;
            text-align: center;
            margin-bottom: 25px;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 20px 0;
        }

        .btn {
            display: inline-block;
            padding: 10px 14px;
            background: #003366;
            color: white;
            text-decoration: none;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 13px;
            text-align: center;
        }

        .btn:hover {
            background: #0055aa;
        }

        .btn-edit {
            background: #176b4d;
        }

        .btn-edit:hover {
            background: #21845f;
        }

        .btn-delete {
            background: #a82323;
        }

        .btn-delete:hover {
            background: #cf3030;
        }

        .btn-archive {
            background: #805600;
        }

        .btn-archive:hover {
            background: #a87500;
        }

        .search-form {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin: 20px 0;
        }

        .search-form input {
            flex: 1;
            min-width: 180px;
            padding: 11px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th, td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: left;
            vertical-align: middle;
        }

        th {
            background: #003366;
            color: white;
        }

        tr:nth-child(even) {
            background: #f7f9fc;
        }

        .description {
            max-width: 250px;
            overflow-wrap: anywhere;
        }

        .message-vide {
            text-align: center;
            padding: 25px;
            color: #666;
        }

        .nombre-documents {
            margin: 20px 0;
            color: #003366;
        }

        .actions-document {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .form-archive {
            display: inline-block;
            margin: 0;
        }

        @media (max-width: 600px) {
            body {
                padding: 10px;
            }

            .container {
                padding: 15px;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <h1>Gestion des documents</h1>

    <!-- Actions principales -->
    <div class="actions">

        <a class="btn" href="ajouter_document.php">
            + Ajouter un document
        </a>

        <a class="btn" href="documents_archives.php">
            Documents archivés
        </a>

        <a class="btn" href="archive_documents.php">
            Archive des suppressions
        </a>

        <a href="dashboard.php" class="btn">
            Retour au tableau de bord
        </a>

    </div>

    <!-- Formulaire de recherche -->
    <form method="GET" action="afficher_documents.php" class="search-form">

        <input
            type="text"
            name="recherche"
            placeholder="Rechercher par nom ou description..."
            value="<?= htmlspecialchars($recherche, ENT_QUOTES, 'UTF-8') ?>"
        >

        <button type="submit" class="btn">
            Rechercher
        </button>

        <a href="afficher_documents.php" class="btn">
            Réinitialiser
        </a>

    </form>

    <!-- Nombre de documents -->
    <p class="nombre-documents">
        Documents actifs trouvés :
        <strong><?= count($documents) ?></strong>
    </p>

    <!-- Tableau des documents -->
    <div class="table-wrapper">

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

            <?php if (count($documents) > 0): ?>

                <?php foreach ($documents as $doc): ?>

                    <tr>

                        <td>
                            <?= (int)$doc["id_document"] ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $doc["nom_document"],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </td>

                        <td class="description">
                            <?= nl2br(htmlspecialchars(
                                $doc["description"] ?? "",
                                ENT_QUOTES,
                                'UTF-8'
                            )) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $doc["date_ajout"] ?? "",
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </td>

                        <td>

                            <div class="actions-document">

                                <a
                                    class="btn"
                                    href="telecharger_document.php?id=<?= (int)$doc["id_document"] ?>"
                                >
                                    Télécharger
                                </a>

                                <a
                                    class="btn btn-edit"
                                    href="modifier_document.php?id=<?= (int)$doc["id_document"] ?>"
                                >
                                    Modifier
                                </a>

                                <!-- Archiver volontairement un document -->
                                <form
                                    method="POST"
                                    action="archiver_document.php"
                                    class="form-archive"
                                    onsubmit="return confirm('Voulez-vous vraiment archiver ce document ? Il sera retiré de la liste des documents actifs.');"
                                >
                                    <input
                                        type="hidden"
                                        name="id_document"
                                        value="<?= (int)$doc["id_document"] ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= htmlspecialchars(
                                            $_SESSION["csrf_token"],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="btn btn-archive"
                                    >
                                        Archiver
                                    </button>
                                </form>

                                <a
                                    class="btn btn-delete"
                                    href="supprimer_document.php?id=<?= (int)$doc["id_document"] ?>"
                                >
                                    Supprimer
                                </a>

                            </div>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php else: ?>

                <tr>
                    <td colspan="5" class="message-vide">
                        <?php if ($recherche !== ""): ?>
                            Aucun document actif ne correspond à votre recherche.
                        <?php else: ?>
                            Aucun document actif n'est enregistré pour le moment.
                        <?php endif; ?>
                    </td>
                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

</body>
</html>

