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

if (isset($_POST["ajouter"])) {

    // Récupération des données
    $nom = trim($_POST["nom"]);
    $prenom = trim($_POST["prenom"]);
    $id_fac = $_POST["id_fac"];
    $id_niveau = $_POST["id_niveau"];
    $login = trim($_POST["login"]);
    $email = trim($_POST["email"] ?? "");

    // Vérifier que l'adresse e-mail est valide
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION["erreur"] = "Veuillez saisir une adresse e-mail valide.";
        header("Location: ajouter_etu.php");
        exit();
    }

    // Vérifier si le login existe déjà
    $verif = $bdd->prepare("
        SELECT id_etu
        FROM etudiant
        WHERE login = ?
    ");

    $verif->execute([$login]);

    if ($verif->rowCount() > 0) {
        $_SESSION["erreur"] = "Ce login existe déjà.";
        header("Location: ajouter_etu.php");
        exit();
    }

    // Vérifier si l'e-mail existe déjà
    $verifEmail = $bdd->prepare("
        SELECT id_etu
        FROM etudiant
        WHERE email = ?
    ");

    $verifEmail->execute([$email]);

    if ($verifEmail->rowCount() > 0) {
        $_SESSION["erreur"] = "Cette adresse e-mail est déjà utilisée.";
        header("Location: ajouter_etu.php");
        exit();
    }

    // Téléversement de la photo
    $photo = NULL;

    if (isset($_FILES["photo"]) && $_FILES["photo"]["error"] == 0) {

        $extension = strtolower(
            pathinfo($_FILES["photo"]["name"], PATHINFO_EXTENSION)
        );

        $extensionsAutorisees = ["jpg", "jpeg", "png", "gif", "webp"];

        if (!in_array($extension, $extensionsAutorisees)) {
            $_SESSION["erreur"] = "Format de photo non autorisé.";
            header("Location: ajouter_etu.php");
            exit();
        }

        if (!is_dir("uploads")) {
            mkdir("uploads", 0755, true);
        }

        $nomPhoto = uniqid("etu_", true) . "." . $extension;

        if (
            move_uploaded_file(
                $_FILES["photo"]["tmp_name"],
                "uploads/" . $nomPhoto
            )
        ) {
            $photo = $nomPhoto;
        } else {
            $_SESSION["erreur"] = "Impossible d'enregistrer la photo.";
            header("Location: ajouter_etu.php");
            exit();
        }
    }

    // Mot de passe par défaut
    $motdepasse = password_hash("123", PASSWORD_DEFAULT);

    // Insertion de l'étudiant avec son e-mail
    $req = $bdd->prepare("
        INSERT INTO etudiant (
            nom_etu,
            prenom_etu,
            id_niveau,
            id_fac,
            login,
            email,
            motdepasse,
            premiere_connexion,
            photo
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $req->execute([
        $nom,
        $prenom,
        $id_niveau,
        $id_fac,
        $login,
        $email,
        $motdepasse,
        1,
        $photo
    ]);

    // Journal d'activité
    include("journal.php");

    journal(
        $bdd,
        $_SESSION["id_admin"],
        "Administrateur",
        "admin",
        "Ajout de l'étudiant " . $nom . " " . $prenom
    );

    $_SESSION["success"] = "Étudiant ajouté avec succès.";

    header("Location: afficher_etu.php");
    exit();

} else {
    header("Location: ajouter_etu.php");
    exit();
}
?>

