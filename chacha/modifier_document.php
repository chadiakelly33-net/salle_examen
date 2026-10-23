<?php
session_start();

// Vérifier que l'utilisateur est administrateur
if (
    !isset($_SESSION["id_admin"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: connexion.php");
    exit();
}

require_once("connexionDB.php");

$erreur = "";

// Récupérer l'identifiant du document
$id_document = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id_document = filter_input(INPUT_POST, "id_document", FILTER_VALIDATE_INT);
}

if (!$id_document || $id_document <= 0) {
    header("Location: afficher_documents.php");
    exit();
}

// Rechercher le document
$req = $bdd->prepare("
    SELECT id_document, nom_document, fichier, description
    FROM document
    WHERE id_document = ?
");
$req->execute([$id_document]);
$document = $req->fetch(PDO::FETCH_ASSOC);

if (!$document) {
    header("Location: afficher_documents.php");
    exit();
}

// Enregistrer les modifications
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nom = trim($_POST["nom_document"] ?? "");
    $description = trim($_POST["description"] ?? "");

    if ($nom === "") {
        $erreur = "Le nom du document est obligatoire.";
    } else {
        $nouveauFichier = $document["fichier"];
        $nouveauChemin = null;

        // Vérifier si un nouveau fichier a été choisi
        if (
            isset($_FILES["fichier"]) &&
            $_FILES["fichier"]["error"] !== UPLOAD_ERR_NO_FILE
        ) {
            $fichier = $_FILES["fichier"];

            if ($fichier["error"] !== UPLOAD_ERR_OK) {
                $erreur = "Une erreur est survenue lors de l'envoi du fichier.";
            } elseif ($fichier["size"] > 10 * 1024 * 1024) {
                $erreur = "Le fichier ne doit pas dépasser 10 Mo.";
            } else {
                $extensionsAutorisees = [
                    "pdf", "doc", "docx", "xls",
                    "xlsx", "ppt", "pptx"
                ];

                $extension = strtolower(
                    pathinfo($fichier["name"], PATHINFO_EXTENSION)
                );

                if (!in_array($extension, $extensionsAutorisees, true)) {
                    $erreur = "Format non autorisé. Utilisez PDF, DOC, DOCX, XLS, XLSX, PPT ou PPTX.";
                } elseif (!is_uploaded_file($fichier["tmp_name"])) {
                    $erreur = "Le fichier envoyé est invalide.";
                } else {
                    $dossier = __DIR__ . "/documents/";

                    if (!is_dir($dossier) && !mkdir($dossier, 0755, true)) {
                        $erreur = "Impossible de créer le dossier documents.";
                    } else {
                        $nouveauFichier = bin2hex(random_bytes(16))
                            . "." . $extension;

                        $nouveauChemin = $dossier . $nouveauFichier;

                        if (!move_uploaded_file(
                            $fichier["tmp_name"],
                            $nouveauChemin
                        )) {
                            $erreur = "Impossible d'enregistrer le nouveau fichier.";
                        }
                    }
                }
            }
        }

        if ($erreur === "") {
            try {
                $bdd->beginTransaction();

                $maj = $bdd->prepare("
                    UPDATE document
                    SET nom_document = ?,
                        description = ?,
                        fichier = ?
                    WHERE id_document = ?
                ");

                $maj->execute([
                    $nom,
                    $description !== "" ? $description : null,
                    $nouveauFichier,
                    $id_document
                ]);

                $bdd->commit();

                // Supprimer l'ancien fichier seulement après réussite
                if ($nouveauChemin !== null) {
                    $ancienChemin = __DIR__ . "/documents/"
                        . basename($document["fichier"]);

                    if (
                        is_file($ancienChemin) &&
                        realpath($ancienChemin) !== realpath($nouveauChemin)
                    ) {
                        unlink($ancienChemin);
                    }
                }

                header("Location: afficher_documents.php");
                exit();

            } catch (Throwable $e) {
                if ($bdd->inTransaction()) {
                    $bdd->rollBack();
                }

                if ($nouveauChemin !== null && is_file($nouveauChemin)) {
                    unlink($nouveauChemin);
                }

                $erreur = "La modification a échoué. Vérifiez la base de données.";
            }
        }
    }

    // Conserver les valeurs saisies si une erreur survient
    $document["nom_document"] = $nom;
    $document["description"] = $description;
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Modifier un document</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            margin: 0;
            padding: 25px;
        }

        .container {
            max-width: 650px;
            margin: 40px auto;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px #ddd;
        }

        h1 {
            text-align: center;
            color: #003366;
        }

        label {
            display: block;
            margin-top: 20px;
            font-weight: bold;
        }

        input[type="text"],
        textarea,
        input[type="file"] {
            box-sizing: border-box;
            width: 100%;
            padding: 12px;
            margin-top: 8px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        textarea {
            min-height: 120px;
            resize: vertical;
        }

        .btn {
            display: inline-block;
            padding: 12px 16px;
            margin-top: 20px;
            border: none;
            border-radius: 5px;
            background: #003366;
            color: white;
            text-decoration: none;
            cursor: pointer;
        }

        .erreur {
            padding: 12px;
            color: #a82323;
            background: #ffe5e5;
            border-radius: 5px;
        }

        .info {
            color: #555;
            font-size: 13px;
        }
    </style>
</head>

<body>

<div class="container">

    <h1>Modifier un document</h1>

    <?php if ($erreur !== ""): ?>
        <p class="erreur">
            <?= htmlspecialchars($erreur, ENT_QUOTES, "UTF-8") ?>
        </p>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">

        <input
            type="hidden"
            name="id_document"
            value="<?= (int)$document["id_document"] ?>"
        >

        <label for="nom_document">Nom du document *</label>

        <input
            type="text"
            id="nom_document"
            name="nom_document"
            maxlength="255"
            required
            value="<?= htmlspecialchars($document["nom_document"], ENT_QUOTES, "UTF-8") ?>"
        >

        <label for="description">Description</label>

        <textarea
            id="description"
            name="description"
        ><?= htmlspecialchars($document["description"] ?? "", ENT_QUOTES, "UTF-8") ?></textarea>

        <label>Fichier actuel</label>

        <p class="info">
            <?= htmlspecialchars(basename($document["fichier"]), ENT_QUOTES, "UTF-8") ?>
        </p>

        <label for="fichier">Remplacer le fichier (facultatif)</label>

        <input
            type="file"
            id="fichier"
            name="fichier"
            accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx"
        >

        <p class="info">
            Formats acceptés : PDF, DOC, DOCX, XLS, XLSX, PPT et PPTX.
            Taille maximale : 10 Mo.
        </p>

        <button type="submit" class="btn">
            Enregistrer les modifications
        </button>

        <a href="affichage_documents.php" class="btn">
            Annuler
        </a>

    </form>

</div>

</body>
</html>

