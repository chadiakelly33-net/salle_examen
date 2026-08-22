<?php

session_start();

if(!isset($_SESSION["id_admin"]) || $_SESSION["role"]!="admin"){
    header("Location: connexion.php");
    exit();
}


include("connexionDB.php");
include("menu_principal.php");
include("journal.php");

error_reporting(E_ALL);
ini_set('display_errors',1);


// Vérifier l'id envoyé
if(!isset($_GET["id"])){

    die("Aucune affectation sélectionnée.");

}


$id_affectation = $_GET["id"];


// =========================
// Récupérer l'affectation
// =========================

$req=$bdd->prepare("
SELECT *
FROM affectation
WHERE id_affectation=?
");

$req->execute([$id_affectation]);

$affectation=$req->fetch();


if(!$affectation){

    die("Affectation introuvable.");

}


// =========================
// Listes
// =========================

$salles=$bdd->query("
SELECT * FROM salle
")->fetchAll();


$surveillants=$bdd->query("
SELECT * FROM surveillant
")->fetchAll();



// =========================
// MODIFICATION
// =========================

$message="";


if(isset($_POST["modifier"])){


try{


    $req=$bdd->prepare("
    CALL modifier_affectation(?,?,?,?,?,?)
    ");


    $req->execute([

        $id_affectation,

        $_POST["id_salle"],

        $_POST["id_surv"],

        $_POST["date_exam"],

        $_POST["heure_debut"],

        $_POST["heure_fin"]

    ]);



    journal(
        $bdd,
        $_SESSION["id_admin"],
        "Administrateur",
		"Admin",
        "Modification d'une affectation d'examen"
    );


    header("Location: afficher_affectation_admin.php");
    exit();



}catch(PDOException $e){


    $message="Erreur : ".$e->getMessage();


}



}


?>



<!DOCTYPE html>
<html lang="fr">

<head>

<meta charset="UTF-8">

<title>Modifier affectation</title>


<style>


body{

    font-family:Arial;

    background:#f4f6f9;

}



.container{

    width:500px;

    margin:40px auto;

    background:white;

    padding:25px;

    border-radius:10px;

    box-shadow:0 0 10px rgba(0,0,0,0.1);

}



h2{

    text-align:center;

    color:#003366;

}



label{

    font-weight:bold;

    display:block;

    margin-top:15px;

}



input,select{

    width:100%;

    padding:10px;

    margin-top:5px;

    border:1px solid #ccc;

    border-radius:5px;

}



button{

    margin-top:20px;

    width:100%;

    padding:12px;

    background:#003366;

    color:white;

    border:none;

    border-radius:5px;

    font-size:16px;

    cursor:pointer;

}



button:hover{

    background:#0055aa;

}



.message{

    text-align:center;

    color:red;

}



.info{

    background:#eef4f7;

    padding:10px;

    border-radius:5px;

    margin-bottom:15px;

}



</style>


</head>


<body>



<div class="container">


<h2>
Modifier une affectation
</h2>



<div class="info">

<b>Étudiant :</b>

<?= $affectation["id_etu"] ?>


<br>

<b>Cours :</b>

<?= $affectation["id_cours"] ?>


</div>




<?php if($message!=""){ ?>

<p class="message">
<?= $message ?>
</p>

<?php } ?>



<form method="POST">



<label>
Salle
</label>


<select name="id_salle" required>


<?php foreach($salles as $s){ ?>


<option value="<?= $s["id_salle"] ?>"
<?php 
if($s["id_salle"]==$affectation["id_salle"])
echo "selected";
?>>

<?= $s["nom_salle"] ?>

</option>


<?php } ?>


</select>





<label>
Surveillant
</label>


<select name="id_surv" required>


<?php foreach($surveillants as $sv){ ?>


<option value="<?= $sv["id_surv"] ?>"
<?php
if($sv["id_surv"]==$affectation["id_surv"])
echo "selected";
?>
>


<?= $sv["nom_surv"]." ".$sv["prenom_surv"] ?>


</option>


<?php } ?>


</select>





<label>
Date examen
</label>


<input type="date"
name="date_exam"
value="<?= $affectation["date_exam"] ?>"
required>




<label>
Heure début
</label>


<input type="time"
name="heure_debut"
value="<?= $affectation["heure_debut"] ?>"
required>




<label>
Heure fin
</label>


<input type="time"
name="heure_fin"
value="<?= $affectation["heure_fin"] ?>"
required>




<button type="submit" name="modifier">

Enregistrer la modification

</button>



</form>



</div>


</body>

</html>