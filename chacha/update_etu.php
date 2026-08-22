<?php
session_start();

if(!isset($_SESSION["id_admin"]) || $_SESSION["role"] != "admin"){
    header("Location: connexion.php");
    exit();
}

include("connexionDB.php");

if(isset($_POST["id"])){

    $id = $_POST["id"];
    $nom = trim($_POST["nom"]);
    $prenom = trim($_POST["prenom"]);
    $id_niveau = $_POST["id_niveau"];
    $id_fac = $_POST["id_fac"];

    if(empty($nom) || empty($prenom) || empty($id_niveau) || empty($id_fac)){
        die("Erreur : un ou plusieurs champs sont vides.");
    }

    // Récupérer la photo actuelle
    $req = $bdd->prepare("SELECT photo FROM etudiant WHERE id_etu=?");
    $req->execute([$id]);
    $etu = $req->fetch();

    $photo = $etu["photo"];

    // Vérifier si une nouvelle photo a été choisie
    if(isset($_FILES["photo"]) && $_FILES["photo"]["error"] == 0){

        $extensions = ["jpg","jpeg","png","gif"];

        $extension = strtolower(pathinfo($_FILES["photo"]["name"], PATHINFO_EXTENSION));

        if(in_array($extension,$extensions)){

            $nouveauNom = time()."_".uniqid().".".$extension;

            move_uploaded_file(
                $_FILES["photo"]["tmp_name"],
                "uploads/".$nouveauNom
            );

            // Supprimer l'ancienne photo
            if(!empty($photo) && file_exists("uploads/".$photo)){
                unlink("uploads/".$photo);
            }

            $photo = $nouveauNom;

        }else{

            die("Format de photo non autorisé.");

        }

    }

    // Mise à jour
    $update = $bdd->prepare("
        UPDATE etudiant
        SET
            nom_etu=?,
            prenom_etu=?,
            id_niveau=?,
            id_fac=?,
            photo=?
        WHERE id_etu=?
    ");

    $update->execute([
        $nom,
        $prenom,
        $id_niveau,
        $id_fac,
        $photo,
        $id
    ]);

    header("Location: afficher_etu.php");
    exit();

}else{

    header("Location: afficher_etu.php");
    exit();

}
?>