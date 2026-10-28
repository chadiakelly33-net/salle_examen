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

if ($_SERVER["REQUEST_METHOD"] !== "POST" || !isset($_POST["modifier"])) {
    header("Location: afficher_sur.php");
    exit();
}

$id_surv = filter_input(INPUT_POST, "id_surv", FILTER_VALIDATE_INT);
$nom = trim($_POST["nom"] ?? "");
$prenom = trim($_POST["prenom"] ?? "");
$login = trim($_POST["login"] ?? "");
$email = trim($_POST["email"] ?? "");

if (!$id_surv || $nom === "" || $prenom === "" || $login === "" || $email === "") {
    die("Erreur : tous les champs obligatoires doivent être remplis.");
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die("Erreur : l'adresse e-mail n'est pas valide.");
}

try {
    // Vérifier que le surveillant existe
    $req = $bdd->prepare(
        "SELECT id_surv, photo FROM surveillant WHERE id_surv = ?"
    );
    $req->execute([$id_surv]);
    $surv = $req->fetch(PDO::FETCH_ASSOC);

    if (!$surv) {
        die("Erreur : surveillant introuvable.");
    }

    // Vérifier que le login n'est pas utilisé par un autre surveillant
    $req = $bdd->prepare(
        "SELECT id_surv FROM surveillant
         WHERE login = ? AND id_surv != ?"
    );
    $req->execute([$login, $id_surv]);

    if ($req->fetch()) {
        die("Erreur : ce login est déjà utilisé par un autre surveillant.");
    }

    // Vérifier que l'e-mail n'est pas utilisé par un autre surveillant
    $req = $bdd->prepare(
        "SELECT id_surv FROM surveillant
         WHERE email = ? AND id_surv != ?"
    );
    $req->execute([$email, $id_surv]);

    if ($req->fetch()) {
        die("Erreur : cette adresse e-mail est déjà utilisée par un autre surveillant.");
    }

    // Conserver la photo actuelle si aucune nouvelle photo n'est envoyée
    $photo = $surv["photo"];

    // Traiter la nouvelle photo si elle a été envoyée
    if (
        isset($_FILES["photo"]) &&
        $_FILES["photo"]["error"] !== UPLOAD_ERR_NO_FILE
    ) {
        if ($_FILES["photo"]["error"] !== UPLOAD_ERR_OK) {
            die("Erreur lors de l'envoi de la photo.");
        }

        // Limiter la taille à 2 Mo
        if ($_FILES["photo"]["size"] > 2 * 1024 * 1024) {
            die("Erreur : la photo ne doit pas dépasser 2 Mo.");
        }

        // Vérifier le type réel du fichier
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $type = $finfo->file($_FILES["photo"]["tmp_name"]);

        $extensions = [
            "image/jpeg" => "jpg",
            "image/png"  => "png",
            "image/webp" => "webp"
        ];

        if (!isset($extensions[$type])) {
            die("Erreur : choisissez une image JPG, PNG ou WEBP.");
        }

        // Dossier de destination
        $dossier = __DIR__ . "/uploads/";

        if (!is_dir($dossier) && !mkdir($dossier, 0755, true)) {
            die("Erreur : impossible de créer le dossier uploads.");
        }

        $nouveauNom = "surv_" . $id_surv . "_" .
                      bin2hex(random_bytes(8)) . "." . $extensions[$type];

        if (!move_uploaded_file(
            $_FILES["photo"]["tmp_name"],
            $dossier . $nouveauNom
        )) {
            die("Erreur : impossible d'enregistrer la nouvelle photo.");
        }

        $photo = $nouveauNom;
    }

    // Mettre à jour les informations du surveillant
    $req = $bdd->prepare(
        "UPDATE surveillant
         SET nom_surv = ?,
             prenom_surv = ?,
             login = ?,
             email = ?,
             photo = ?
         WHERE id_surv = ?"
    );

    $req->execute([
        $nom,
        $prenom,
        $login,
        $email,
        $photo,
        $id_surv
    ]);

header("Location: afficher_sur.php?modification=success");
exit();


    exit();

} catch (PDOException $e) {
    error_log("Erreur update_surv.php : " . $e->getMessage());
    die("Une erreur est survenue lors de la modification du surveillant.");
}
?>

