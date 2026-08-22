<?php

include("connexionDB.php");


if(isset($_GET["id"])){

    $id_salle=$_GET["id"];


    $req=$bdd->prepare("
        DELETE FROM salle
        WHERE id_salle=?
    ");


    $req->execute([
        $id_salle
    ]);
	 journal(
        $bdd,
        $_SESSION["id_admin"],
        "Administrateur",
        "Suppression d'une salle"
    );

}


header("Location: afficher_salle.php");
exit();

?>