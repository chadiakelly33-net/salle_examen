<?php
session_start();
include("connexionDB.php");

if(!isset($_SESSION["id_etu"])){
    header("Location: login_etu.php");
    exit();
}

if(isset($_POST["changer"])){

    $id_etu = $_SESSION["id_etu"];

    $ancien = $_POST["ancien"];
    $nouveau = $_POST["nouveau"];
    $confirm = $_POST["confirm"];

    if($nouveau != $confirm){
        die("Les mots de passe ne correspondent pas.");
    }

    // récupérer mot de passe actuel
    $req = $bdd->prepare("SELECT motdepasse FROM etudiant WHERE id_etu=?");
    $req->execute([$id_etu]);
    $user = $req->fetch();

    // vérifier ancien mot de passe
    if(!password_verify($ancien, $user["motdepasse"])){
        die("Ancien mot de passe incorrect.");
    }

    // hash nouveau mot de passe
    $hash = password_hash($nouveau, PASSWORD_DEFAULT);

    // mise à jour
    $update = $bdd->prepare("
        UPDATE etudiant
        SET motdepasse=?
        WHERE id_etu=?
    ");

    $update->execute([$hash, $id_etu]);

    /*echo "Mot de passe modifié avec succès.";*/
	header("Location: espace_etu.php?success=1");
exit();

}else{
    header("Location: espace_etu.php");
    exit();
}
?>