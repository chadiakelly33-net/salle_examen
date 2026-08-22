<?php
session_start();

if(!isset($_SESSION["id_admin"]) || $_SESSION["role"] != "admin"){
    header("Location: connexion.php");
    exit();
}

include("connexionDB.php");

if(isset($_POST["ajouter"])){

    // Récupération des données
    $nom = trim($_POST["nom"]);
    $prenom = trim($_POST["prenom"]);
    $id_fac = $_POST["id_fac"];
    $id_niveau = $_POST["id_niveau"];
    $login = trim($_POST["login"]);

    // Vérifier si le login existe déjà
    $verif = $bdd->prepare("
        SELECT id_etu
        FROM etudiant
        WHERE login=?
    ");

    $verif->execute([$login]);

    if($verif->rowCount() > 0){

        $_SESSION["erreur"] = "Ce login existe déjà.";

        header("Location: ajouter_etu.php");
        exit();
    }

    // ==========================
    // Téléversement de la photo
    // ==========================

    $photo = NULL;

    if(isset($_FILES["photo"]) && $_FILES["photo"]["error"] == 0){

        $extension = pathinfo($_FILES["photo"]["name"], PATHINFO_EXTENSION);

        $nomPhoto = time().".".$extension;

        move_uploaded_file(
            $_FILES["photo"]["tmp_name"],
            "uploads/".$nomPhoto
        );

        $photo = $nomPhoto;
    }

    // Mot de passe par défaut
    $motdepasse = password_hash("123", PASSWORD_DEFAULT);

    // Insertion
    $req = $bdd->prepare("
        INSERT INTO etudiant
        (
            nom_etu,
            prenom_etu,
            id_niveau,
            id_fac,
            login,
            motdepasse,
            premiere_connexion,
            photo
        )

        VALUES
        (?,?,?,?,?,?,?,?)
    ");

    $req->execute([
        $nom,
        $prenom,
        $id_niveau,
        $id_fac,
        $login,
        $motdepasse,
        1,
        $photo
    ]);
	
	include("journal.php");

journal(
    $bdd,
    $_SESSION["id_admin"],
    "Administrateur",
    "admin",
    "Ajout de l'étudiant ".$nom." ".$prenom
);

    $_SESSION["success"] = "Étudiant ajouté avec succès.";

    header("Location: afficher_etu.php");
    exit();

}else{

    header("Location: ajouter_etu.php");
    exit();

}
?>