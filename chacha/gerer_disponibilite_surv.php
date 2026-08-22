<?php
session_start();

if(!isset($_SESSION["id_admin"]) || $_SESSION["role"]!="admin"){
    header("Location: connexion.php");
    exit();
}

include("connexionDB.php");
include("menu_principal.php");


$message="";


if($_SERVER["REQUEST_METHOD"]=="POST"){

    $id_surv = $_POST["id_surv"];
    $date = $_POST["date_exam"];
    $debut = $_POST["heure_debut"];
    $fin = $_POST["heure_fin"];


    $req=$bdd->prepare("
        SELECT *
        FROM affectation
        WHERE id_surv=?
        AND date_exam=?
        AND (
            ? < heure_fin
            AND ? > heure_debut
        )
    ");


    $req->execute([
        $id_surv,
        $date,
        $debut,
        $fin
    ]);


    $resultat=$req->fetch();


    if($resultat){

        $message="Ce surveillant est déjà affecté durant cette période.";

    }else{

        $message=" Ce surveillant est disponible.";

    }

}


?>


<!DOCTYPE html>
<html>

<head>

<title>Disponibilité surveillant</title>

<style>

body{
    font-family:Arial;
    background:#f4f6f9;
}


.container{

    width:450px;
    margin:50px auto;
    background:white;
    padding:25px;
    border-radius:10px;
}


h2{

text-align:center;
color:#003366;

}


label{

display:block;
margin-top:15px;
font-weight:bold;

}


select,input{

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

}


.message{

margin-top:20px;
padding:10px;
text-align:center;
background:#e8f4ff;

}

</style>

</head>


<body>


<div class="container">


<h2>
Vérifier disponibilité surveillant
</h2>


<form method="POST">


<label>Surveillant</label>

<select name="id_surv" required>

<option value="">
-- Choisir --
</option>


<?php

$surv=$bdd->query("
SELECT * FROM surveillant
ORDER BY nom_surv
");


while($s=$surv->fetch()){

echo "
<option value='{$s['id_surv']}'>
{$s['nom_surv']} {$s['prenom_surv']}
</option>";

}

?>


</select>



<label>Date examen</label>

<input type="date" name="date_exam" required>



<label>Heure début</label>

<input type="time" name="heure_debut" required>



<label>Heure fin</label>

<input type="time" name="heure_fin" required>



<button>
Vérifier
</button>


</form>


<?php if($message!=""){ ?>

<div class="message">

<?= $message ?>

</div>

<?php } ?>


</div>


</body>

</html>