<?php

session_start();

include("connexionDB.php");


// Enregistrer la déconnexion si une connexion est en cours

if(isset($_SESSION["connexion_id"])){

    $req = $bdd->prepare("
        UPDATE connexion_log
        SET
            date_deconnexion = NOW(),
            duree_connexion = TIMEDIFF(NOW(), date_connexion)
        WHERE id = ?
    ");

    $req->execute([
        $_SESSION["connexion_id"]
    ]);

}


// Détruire la session

session_unset();
session_destroy();

header("Location: index.php");
exit();

?>