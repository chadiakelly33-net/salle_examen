<?php
require_once __DIR__ . "/proteger_admin.php";

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

// Protection CSRF
if (empty($_SESSION["csrf_restauration"])) {
    $_SESSION["csrf_restauration"] = bin2hex(random_bytes(32));
}

$message = "";
$erreur = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals($_SESSION["csrf_restauration"], $_POST["csrf_token"])
    ) {
        $erreur = "Requête invalide. Veuillez réessayer.";
    } elseif (
        !isset($_FILES["fichier_sql"]) ||
        $_FILES["fichier_sql"]["error"] !== UPLOAD_ERR_OK
    ) {
        $erreur = "Erreur lors de l'envoi du fichier.";
    } else {

        $fichier = $_FILES["fichier_sql"]["tmp_name"];
        $nomFichier = $_FILES["fichier_sql"]["name"];

        $extension = strtolower(pathinfo($nomFichier, PATHINFO_EXTENSION));

        if ($extension !== "enc" || !is_uploaded_file($fichier)) {
            $erreur = "Veuillez sélectionner une sauvegarde chiffrée (.sql.enc).";
        } else {

            $clePath = "C:\\xampp\\securite\\cle_secrete.key";
            $mysqlPath = "C:\\xampp\\mysql\\bin\\mysql.exe";
            $dossierBackup = "C:\\xampp\\backups\\salleexamen";

            if (!is_file($clePath) || !is_file($mysqlPath)) {
                $erreur = "Clé secrète ou programme MySQL introuvable.";
            } else {

                $cle = file_get_contents($clePath);
                $donnees = file_get_contents($fichier);

                if (
                    $cle === false ||
                    strlen($cle) !== 32 ||
                    $donnees === false ||
                    strlen($donnees) < 29
                ) {
                    $erreur = "Fichier chiffré ou clé invalide.";
                } else {

                    // Extraire les éléments AES-256-GCM
                    $iv = substr($donnees, 0, 12);
                    $tag = substr($donnees, 12, 16);
                    $chiffre = substr($donnees, 28);

                    // Déchiffrer la sauvegarde
                    $sql = openssl_decrypt(
                        $chiffre,
                        "aes-256-gcm",
                        $cle,
                        OPENSSL_RAW_DATA,
                        $iv,
                        $tag
                    );

                    if ($sql === false) {
                        $erreur = "Échec du déchiffrement. Vérifiez la sauvegarde et la clé.";
                    } else {

                        if (!is_dir($dossierBackup)) {
                            mkdir($dossierBackup, 0700, true);
                        }

                        // Créer un fichier SQL temporaire hors du dossier public
                        $fichierTemp = $dossierBackup . "\\restauration_" . bin2hex(random_bytes(8)) . ".sql";

                        if (file_put_contents($fichierTemp, $sql) === false) {
                            $erreur = "Impossible de créer le fichier temporaire.";
                        } else {

                            // Restaurer la base de données
                            $commande = '"' . $mysqlPath . '" --host=localhost --user=root < "' . $fichierTemp . '" 2>&1';

                            exec($commande, $sortie, $codeRetour);

                            // Supprimer le fichier SQL temporaire
                            unlink($fichierTemp);

                            if ($codeRetour === 0) {
                                $message = "Restauration de la sauvegarde réussie.";
                            } else {
                                $erreur = "La restauration a échoué. Vérifiez le journal MySQL.";
                            }
                        }

                        // Effacer le contenu SQL de la variable
                        unset($sql);
                    }
                }
            }
        }
    }
}
?>

<?php include("menu_principal.php");
?>;

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Restauration de sauvegarde</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            padding: 30px;
        }

        .conteneur {
            max-width: 600px;
            margin: 40px auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
        }

        h2 {
            color: #003366;
        }

        input, button {
            margin-top: 15px;
        }

        button {
            background: #003366;
            color: white;
            border: none;
            padding: 12px 20px;
            cursor: pointer;
            border-radius: 5px;
        }

        .succes {
            color: green;
            margin-bottom: 15px;
        }

        .erreur {
            color: red;
            margin-bottom: 15px;
        }
    </style>
</head>

<body>

<div class="conteneur">

    <h2>Restauration de sauvegarde</h2>

    <?php if ($message): ?>
        <p class="succes">
            <?= htmlspecialchars($message) ?>
        </p>
    <?php endif; ?>

    <?php if ($erreur): ?>
        <p class="erreur">
            <?= htmlspecialchars($erreur) ?>
        </p>
    <?php endif; ?>

    <p>
        Sélectionnez une sauvegarde chiffrée au format
        <strong>.sql.enc</strong>.
    </p>

    <form method="POST" enctype="multipart/form-data"
          onsubmit="return confirm('Attention ! Cette opération va restaurer la base de données. Voulez-vous continuer ?');">

        <input type="hidden" name="csrf_token"
               value="<?= htmlspecialchars($_SESSION["csrf_restauration"]) ?>">

        <input type="file" name="fichier_sql"
               accept=".enc" required>

        <br>

        <button type="submit">Restaurer la sauvegarde</button>

    </form>

    <br>

    <a href="dashboard.php">Retour au tableau de bord</a>

</div>

</body>
</html>