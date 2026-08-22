<?php

session_start();


// Protection accès direct
if(!isset($_SESSION["id_etu"]) ||
   !isset($_SESSION["role"]) ||
   $_SESSION["role"]!="etudiant"){

    header("Location: login_etu.php");
    exit();

}


include("connexionDB.php");


// Gestion timeout
$timeout = 300; // 5 minutes


if(isset($_SESSION["last_activity"])){

    if(time() - $_SESSION["last_activity"] > $timeout){

        session_unset();
        session_destroy();

        header("Location: login_etu.php?timeout=1");
        exit();

    }

}


$_SESSION["last_activity"] = time();



include("menu_etu.php");



$id_etu = $_SESSION["id_etu"];



$req = $bdd->prepare("
SELECT nom_etu, prenom_etu
FROM etudiant
WHERE id_etu=?
");


$req->execute([$id_etu]);


$etu = $req->fetch();



if(!$etu){

    die("Etudiant introuvable");

}


?>


<html lang="fr">

<head>

<meta charset="UTF-8">

<title>Espace étudiant</title>


<style>


body{

font-family:Arial;
background:#f4f6f9;

}


.container{

width:500px;
margin:60px auto;

}


.card{

background:white;
padding:30px;
border-radius:10px;
box-shadow:0 0 10px #ccc;

}


h2{

text-align:center;
color:#003366;

}


.btn{

display:block;
background:#003366;
color:white;
text-decoration:none;
padding:15px;
margin:15px 0;
text-align:center;
border-radius:6px;

}


.btn:hover{

background:#0055aa;

}


</style>


</head>


<body>


<div class="container">


<div class="card">


<h2>
Bienvenue 
<?= htmlspecialchars($etu["nom_etu"]) ?>
<?= htmlspecialchars($etu["prenom_etu"]) ?>
</h2>


<p>
Choisissez une option
</p>



<a class="btn" href="mes_camarades.php">
Liste des étudiants
</a>



<a class="btn" href="mes_affectations.php">
Mes affectations
</a>



<a class="btn" href="changer_motdepasse.php">
Modifier mot de passe
</a>



</div>


</div>


</body>

</html>