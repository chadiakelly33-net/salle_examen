<?php
session_start();
if(!isset($_SESSION["id_admin"]) || $_SESSION["role"] != "admin"){
    header("Location: connexion.php");
    exit();
}

include("connexionDB.php");
include("journal.php");
journal(
    $bdd,
        $_SESSION["id_admin"],
        "Administrateur",
		"Admin",
    "Suppression du cours ".$nom_cours
);
header("Location: afficher_cours.php");
exit();

$id = $_GET["id"];

$req = $bdd->prepare("DELETE FROM cours WHERE id_cours=?");
$req->execute([$id]);

header("Location: afficher_cours.php");
exit;
?>