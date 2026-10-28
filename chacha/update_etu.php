<?php
session_start();

if (
    !isset($_SESSION["id_admin"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] != "admin"
) {
    header("Location: connexion.php");
    exit();
}

include("connexionDB.php");

if (isset($_POST["id"])) {

    $id = (int) $_POST["id"];
    $nom = trim($_POST["nom"] ?? "");
    $prenom = trim($_POST["prenom"] ?? "");
    $id_niveau = $_POST["id_niveau"] ?? "";
    $id_fac = $_POST["id_fac"] ?? "";
    $email = trim($_POST["email"] ?? "");

    // Vérifier les champs obligatoires
    if (
        empty($nom) ||
        empty($prenom) ||
        empty($id_niveau) ||
        empty($id_fac) ||
        empty($email)
    ) {
        die("Erreur : veuillez remplir tous les champs obligatoires.");
    }

    // Vérifier le format de l'e-mail
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        die("Erreur : l'adresse e-mail n'est pas valide.");
    }

    // Vérifier si l'e-mail est déjà utilisé par un autre étudiant
    $verifEmail = $bdd->prepare("
        SELECT id_etu
        FROM etudiant
        WHERE email = ? AND id_etu != ?
    ");

    $verifEmail->execute([$email, $id]);

    if ($verifEmail->fetch()) {
        die("Erreur : cette adresse e-mail est déjà utilisée par un autre étudiant.");
    }

    // Récupérer la photo actuelle
    $req = $bdd->prepare("
        SELECT photo
        FROM etudiant
        WHERE id_etu = ?
    ");

    $req->execute([$id]);
    $etu = $req->fetch(PDO::FETCH_ASSOC);

    if (!$etu) {
        die("Erreur : étudiant introuvable.");
    }

    $photo = $etu["photo"];

    // Vérifier si une nouvelle photo a été choisie
    if (isset($_FILES["photo"]) && $_FILES["photo"]["error"] == 0) {

        $extensions = ["jpg", "jpeg", "png", "gif"];

        $extension = strtolower(
            pathinfo($_FILES["photo"]["name"], PATHINFO_EXTENSION)
        );

        if (!in_array($extension, $extensions, true)) {
            die("Format de photo non autorisé.");
        }

        if (!is_dir("uploads")) {
            mkdir("uploads", 0755, true);
        }

        $nouveauNom = uniqid("etu_", true) . "." . $extension;

        if (
            !move_uploaded_file(
                $_FILES["photo"]["tmp_name"],
                "uploads/" . $nouveauNom
            )
        ) {
            die("Erreur lors de l'enregistrement de la nouvelle photo.");
        }

        // Supprimer l'ancienne photo après l'enregistrement réussi
        if (!empty($photo) && file_exists("uploads/" . $photo)) {
            unlink("uploads/" . $photo);
        }

        $photo = $nouveauNom;
    }

    // Mettre à jour les informations de l'étudiant
    $update = $bdd->prepare("
        UPDATE etudiant
        SET
            nom_etu = ?,
            prenom_etu = ?,
            id_niveau = ?,
            id_fac = ?,
            email = ?,
            photo = ?
        WHERE id_etu = ?
    ");

    $update->execute([
        $nom,
        $prenom,
        $id_niveau,
        $id_fac,
        $email,
        $photo,
        $id
    ]);

    header("Location: afficher_etu.php");
    exit();

} else {
    header("Location: afficher_etu.php");
    exit();
}
?>

