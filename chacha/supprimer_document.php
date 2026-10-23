<?php
session_start();

if (
    !isset($_SESSION["id_admin"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: connexion.php");
    exit();
}

require_once("connexionDB.php");

// Vérifier l'identifiant du document
$id_document = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$id_document || $id_document <= 0) {
    header("Location: afficher_documents.php");
    exit();
}

// Rechercher le document actif
$req = $bdd->prepare("
    SELECT *
    FROM document
    WHERE id_document = ?
");
$req->execute([$id_document]);
$document = $req->fetch(PDO::FETCH_ASSOC);

if (!$document) {
    header("Location: afficher_documents.php?erreur=document_introuvable");
    exit();
}

$erreur = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (($_POST["confirmation"] ?? "") !== "oui") {
        header("Location: afficher_documents.php");
        exit();
    }

    try {
        $bdd->beginTransaction();

        // 1. Archiver les informations du document
        $archive = $bdd->prepare("
            INSERT INTO archive_suppression_document
            (
                id_document_original,
                nom_document,
                fichier,
                description,
                date_ajout_original,
                id_admin,
                id_surv,
                date_suppression,
                supprime_par
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)
        ");

        $archive->execute([
            $document["id_document"],
            $document["nom_document"],
            $document["fichier"],
            $document["description"],
            $document["date_ajout"],
            $document["id_admin"],
            $document["id_surv"],
            $_SESSION["id_admin"]
        ]);

        $id_archive = (int) $bdd->lastInsertId();

        // 2. Récupérer les anciens destinataires
        $partages = $bdd->prepare("
            SELECT
                type_destinataire,
                id_etu,
                id_surv,
                id_fac,
                id_niveau
            FROM partage_document
            WHERE id_document = ?
        ");
        $partages->execute([$id_document]);

        $destinataires = $partages->fetchAll(PDO::FETCH_ASSOC);

        // 3. Archiver les destinataires
        $ajoutDestinataire = $bdd->prepare("
            INSERT INTO archive_destinataires_document
            (
                id_archive,
                type_destinataire,
                id_etu,
                id_surv,
                id_fac,
                id_niveau
            )
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        foreach ($destinataires as $destinataire) {
            $ajoutDestinataire->execute([
                $id_archive,
                $destinataire["type_destinataire"],
                $destinataire["id_etu"],
                $destinataire["id_surv"],
                $destinataire["id_fac"],
                $destinataire["id_niveau"]
            ]);
        }

        // 4. Supprimer les partages actifs
        $suppPartages = $bdd->prepare("
            DELETE FROM partage_document
            WHERE id_document = ?
        ");
        $suppPartages->execute([$id_document]);

        // 5. Retirer le document de la liste active
        $suppDocument = $bdd->prepare("
            DELETE FROM document
            WHERE id_document = ?
        ");
        $suppDocument->execute([$id_document]);

        // 6. Valider les opérations
        $bdd->commit();

        // IMPORTANT : ne pas utiliser unlink().
        // Le fichier physique reste dans le dossier documents.

        header("Location: afficher_documents.php?suppression=archive");
        exit();

    } catch (Throwable $e) {

        if ($bdd->inTransaction()) {
            $bdd->rollBack();
        }

        error_log("Erreur d'archivage du document : " . $e->getMessage());

        $erreur = "L'archivage a échoué. Vérifiez les tables d'archive et les relations de la base de données.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Archiver un document</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            padding: 25px;
        }

        .container {
            max-width: 600px;
            margin: 60px auto;
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px #ddd;
        }

        h1 {
            color: #a82323;
        }

        .btn {
            display: inline-block;
            padding: 12px 16px;
            border: none;
            border-radius: 5px;
            color: white;
            text-decoration: none;
            cursor: pointer;
            margin-top: 15px;
            font-size: 15px;
        }

        .danger {
            background: #a82323;
        }

        .retour {
            background: #003366;
        }

        .erreur {
            color: #a82323;
            background: #ffe5e5;
            padding: 12px;
            border-radius: 5px;
        }

        .information {
            color: #245b2a;
            background: #e8f5e9;
            padding: 12px;
            border-radius: 5px;
            line-height: 1.5;
        }

        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
    </style>
</head>

<body>

<div class="container">

    <h1>Confirmer l'archivage</h1>

    <?php if ($erreur !== ""): ?>
        <p class="erreur">
            <?= htmlspecialchars($erreur, ENT_QUOTES, "UTF-8") ?>
        </p>
    <?php endif; ?>

    <p>
        Voulez-vous vraiment archiver le document
        <strong>
            <?= htmlspecialchars(
                $document["nom_document"],
                ENT_QUOTES,
                "UTF-8"
            ) ?>
        </strong> ?
    </p>

    <div class="information">
        Les informations et les destinataires seront archivés.
        Le document disparaîtra de la liste active, mais son fichier
        physique sera conservé dans le dossier documents afin de faciliter
        sa restauration ultérieure.
    </div>

    <div class="actions">

        <form method="POST">
            <input type="hidden" name="confirmation" value="oui">

            <button type="submit" class="btn danger">
                Oui, archiver le document
            </button>
        </form>

        <a href="afficher_documents.php" class="btn retour">
            Retour à la liste
        </a>

    </div>

</div>

</body>
</html>

