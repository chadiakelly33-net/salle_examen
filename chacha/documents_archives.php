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

// Créer le jeton de sécurité s'il n'existe pas
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION["csrf_token"];

// Récupérer le texte de recherche
$recherche = trim($_GET["recherche"] ?? "");

// Récupérer les documents archivés
$sql = "SELECT * FROM document WHERE archive = 1";
$params = [];

if ($recherche !== "") {
    $sql .= " AND (nom_document LIKE ? OR description LIKE ?)";
    $params[] = "%" . $recherche . "%";
    $params[] = "%" . $recherche . "%";
}

$sql .= " ORDER BY date_ajout DESC";

try {
    $req = $bdd->prepare($sql);
    $req->execute($params);
    $documents = $req->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur de lecture des documents archivés : " . $e->getMessage());
    exit("Impossible de charger les documents archivés.");
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Documents archivés</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            margin: 0;
            padding: 25px;
            color: #333;
        }

        .container {
            max-width: 1100px;
            margin: auto;
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        h1 {
            color: #003366;
            margin-top: 0;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }

        .btn {
            display: inline-block;
            padding: 10px 14px;
            border: none;
            border-radius: 4px;
            text-decoration: none;
            color: white;
            cursor: pointer;
            font-size: 14px;
        }

        .btn-retour {
            background: #003366;
        }

        .btn-archive {
            background: #6c757d;
        }

        .btn-telecharger {
            background: #198754;
        }

        .btn-restaurer {
            background: #d49a00;
        }

        .btn:hover {
            opacity: 0.85;
        }

        .recherche {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
        }

        .recherche input {
            flex: 1;
            min-width: 0;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: left;
            overflow-wrap: anywhere;
        }

        th {
            background: #003366;
            color: white;
        }

        tr:nth-child(even) {
            background: #f8f9fa;
        }

        .message {
            padding: 12px;
            margin-bottom: 18px;
            border-radius: 4px;
            background: #e8f5e9;
            color: #216e39;
        }

        .message-erreur {
            background: #fff3cd;
            color: #856404;
        }

        .vide {
            text-align: center;
            padding: 25px;
            color: #666;
        }

        form.form-restaurer {
            display: inline;
        }

        @media (max-width: 700px) {
            .container {
                padding: 12px;
            }

            .table-responsive {
                overflow-x: auto;
            }

            table {
                min-width: 700px;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <h1>Documents archivés</h1>


    <?php if (($_GET["message"] ?? "") === "archive"): ?>
        <div class="message">
            Le document a été archivé avec succès.
        </div>
    <?php elseif (($_GET["message"] ?? "") === "restaure"): ?>
        <div class="message">
            Le document a été restauré avec succès.
        </div>
    <?php elseif (($_GET["message"] ?? "") === "introuvable"): ?>
        <div class="message message-erreur">
            Le document est introuvable ou son état a déjà changé.
        </div>
    <?php endif; ?>

    <div class="actions">
        <a href="afficher_documents.php" class="btn btn-retour">
            Documents actifs
        </a>

        <a href="archive_documents.php" class="btn btn-archive">
            Archives des suppressions
        </a>
    </div>

    <form method="GET" action="documents_archives.php" class="recherche">
        <input
            type="text"
            name="recherche"
            placeholder="Rechercher un document..."
            value="<?= htmlspecialchars($recherche, ENT_QUOTES, 'UTF-8') ?>"
        >

        <button type="submit" class="btn btn-retour">
            Rechercher
        </button>
    </form>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nom du document</th>
                    <th>Description</th>
                    <th>Date d'ajout</th>
                    <th>Actions</th>
                </tr>
            </thead>

            <tbody>
            <?php if (count($documents) > 0): ?>

                <?php foreach ($documents as $document): ?>
                    <tr>
                        <td>
                            <?= (int) $document["id_document"] ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $document["nom_document"] ?? "",
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>
                        </td>

                        <td>
                            <?= nl2br(htmlspecialchars(
                                $document["description"] ?? "",
                                ENT_QUOTES,
                                "UTF-8"
                            )) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $document["date_ajout"] ?? "",
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>
                        </td>

                        <td>

                            <form
                                method="POST"
                                action="restaurer_document_actif.php"
                                class="form-restaurer"
                                onsubmit="return confirm('Voulez-vous restaurer ce document ?');"
                            >
                                <input
                                    type="hidden"
                                    name="id_document"
                                    value="<?= (int) $document["id_document"] ?>"
                                >

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= htmlspecialchars(
                                        $csrf_token,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                >

                                <button
                                    type="submit"
                                    class="btn btn-restaurer"
                                >
                                    Restaurer
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>

            <?php else: ?>
                <tr>
                    <td colspan="5" class="vide">
                        Aucun document archivé trouvé.
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

</body>
</html>

