<?php

session_start();

if(!isset($_SESSION["id_admin"]) || $_SESSION["role"]!="admin"){
    header("Location: connexion.php");
    exit();
}


include("connexionDB.php");
/*include("journal.php");*/


if(isset($_GET["id"])){

    $id_etu = $_GET["id"];


    // requête préparée DELETE

    $req = $bdd->prepare("
        DELETE FROM etudiant
        WHERE id_etu = ?
    ");


    $req->execute([
        $id_etu
    ]);



    // journal(
        // $bdd,
        // $_SESSION["id_admin"],
        // "Administrateur",
        // "Suppression d'un étudiant"
    // );

}


header("Location: afficher_etu.php");
exit();

?>