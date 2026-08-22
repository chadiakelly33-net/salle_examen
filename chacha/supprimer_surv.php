<?php

include("connexionDB.php");


if(isset($_GET["id"])){

    $id_surv=$_GET["id"];


    $req=$bdd->prepare("
        DELETE FROM surveillant
        WHERE id_surv=?
    ");


    $req->execute([
        $id_surv
    ]);
	
	 journal(
        $bdd,
        $_SESSION["id_admin"],
        "Administrateur",
        "Suppression d'une salle"
    );

}


header("Location: afficher_surv.php");
exit();

?>