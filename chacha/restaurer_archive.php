<?php
session_start();

if(!isset($_SESSION["id_admin"]) || $_SESSION["role"]!="admin"){
    header("Location: connexion.php");
    exit();
}

include("connexionDB.php");

if(isset($_GET["id"])){

    $id = $_GET["id"];

    $req = $bdd->prepare("CALL restaurer_affectation(?)");
    $req->execute([$id]);

}

header("Location: afficher_archives.php");
exit();
?>