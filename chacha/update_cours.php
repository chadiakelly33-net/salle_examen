<?php
session_start();
if(!isset($_SESSION["id_admin"]) || $_SESSION["role"] != "admin"){
    header("Location: connexion.php");
    exit();
}

include("connexionDB.php");

$req = $bdd->prepare("
UPDATE cours 
SET nom_cours=?, credit=?, id_niveau=?, id_fac=?, date_exam=?, heure_debut=?, heure_fin=?
WHERE id_cours=?
");

$req->execute([
$_POST["nom_cours"],
$_POST["credit"],
$_POST["id_niveau"],
$_POST["id_fac"],
$_POST["date_exam"],
$_POST["heure_debut"],
$_POST["heure_fin"],
$_POST["id"]
]);

header("Location: afficher_cours.php");
exit;
?>