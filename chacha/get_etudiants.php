<?php

session_start();

if(!isset($_SESSION["id_admin"]) || $_SESSION["role"] != "admin"){
    exit("Accès refusé");
}


include("connexionDB.php");



// Vérifier les paramètres reçus

if(!isset($_GET["fac"]) || !isset($_GET["niv"])){

    exit("Paramètres manquants");

}


$fac = $_GET["fac"];

$niv = $_GET["niv"];





// Récupérer les étudiants

$req = $bdd->prepare("
    SELECT 
        id_etu,
        nom_etu,
        prenom_etu
    FROM etudiant
    WHERE id_fac = ?
    AND id_niveau = ?
    ORDER BY nom_etu ASC, prenom_etu ASC
");


$req->execute([

    $fac,

    $niv

]);


$etudiants = $req->fetchAll();





if(count($etudiants) == 0){

    echo "<p style='color:red'>
    Aucun étudiant trouvé.
    </p>";

    exit();

}




$i = 1;



foreach($etudiants as $e){


    echo "

    <label>

    <input 
    type='checkbox'
    name='etudiants[".$fac."-".$niv."][]'
    value='".$e["id_etu"]."'>

    ".$i." - ".
    htmlspecialchars($e["nom_etu"]." ".$e["prenom_etu"])
    ."

    </label>

    <br>

    ";


    $i++;

}


?>