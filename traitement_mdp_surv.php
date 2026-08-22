<?php

session_start();

include("connexionDB.php");
include("journal.php");

if(!isset($_SESSION["id_surv"])){
    header("Location: login_surv.php");
    exit();
}

if($_SERVER["REQUEST_METHOD"] != "POST"){
    header("Location: changer_mdp_surv.php");
    exit();
}

$id = $_SESSION["id_surv"];

$ancien = $_POST["ancien"];
$nouveau = $_POST["nouveau"];
$confirm = $_POST["confirm"];

// Vérifier la confirmation
if($nouveau != $confirm){

    header("Location: changer_mdp_surv.php?erreur=confirm");
    exit();

}

// Récupérer le mot de passe actuel
$req = $bdd->prepare("
    SELECT motdepasse
    FROM surveillant
    WHERE id_surv = ?
");

$req->execute([$id]);

$user = $req->fetch();

if(!$user){

    header("Location: login_surv.php");
    exit();

}

// Vérifier l'ancien mot de passe
if(!password_verify($ancien, $user["motdepasse"])){

    header("Location: changer_mdp_surv.php?erreur=ancien");
    exit();

}

// Hasher le nouveau mot de passe
$hash = password_hash($nouveau, PASSWORD_DEFAULT);

// Mettre à jour le mot de passe
$update = $bdd->prepare("
    UPDATE surveillant
    SET motdepasse = ?
    WHERE id_surv = ?
");

$update->execute([
    $hash,
    $id
]);

// Journaliser l'action
journal(
    $bdd,
    $_SESSION["id_surv"],
    $_SESSION["nom_surv"]." ".$_SESSION["prenom_surv"],
    "surveillant",
    "Modification du mot de passe"
);

// Redirection
header("Location: espace_surv.php?success=1");
exit();

?>