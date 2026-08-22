<?php

session_start();

if(!isset($_SESSION["id_admin"]) || $_SESSION["role"]!="admin"){
    header("Location: connexion.php");
    exit();
}

include("connexionDB.php");
include("journal.php");

error_reporting(E_ALL);
ini_set('display_errors',1);


// Vérifier qu'un id est reçu
if(!isset($_GET["id"])){

    die("Aucune affectation sélectionnée.");

}


$id_affectation = $_GET["id"];


try{

    // Appel de la procédure stockée

    $req = $bdd->prepare("
        CALL supprimer_affectation(?)
    ");

    $req->execute([
        $id_affectation
    ]);


    // Journaliser l'action

    journal(
        $bdd,
        $_SESSION["id_admin"],
        "Administrateur",
        "Suppression d'une affectation d'examen"
    );


    header("Location: afficher_affectation_admin.php");
    exit();

}
catch(PDOException $e){

    die("Erreur : ".$e->getMessage());

}

?>