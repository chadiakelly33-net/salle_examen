<?php

session_start();

if(!isset($_SESSION["id_admin"]) || $_SESSION["role"]!="admin"){
    header("Location: connexion.php");
    exit();
}


include("connexionDB.php");
include("menu_principal.php");



$req=$bdd->query("

SELECT

sv.nom_surv,
sv.prenom_surv,

c.nom_cours,

s.nom_salle,

a.date_exam,

a.heure_debut,

a.heure_fin


FROM affectation a


JOIN surveillant sv
ON a.id_surv=sv.id_surv


JOIN cours c
ON a.id_cours=c.id_cours


JOIN salle s
ON a.id_salle=s.id_salle


WHERE a.date_exam >= CURDATE()


ORDER BY 
a.date_exam,
a.heure_debut

");


$affectations=$req->fetchAll();


?>


<!DOCTYPE html>

<html>

<head>

<title>Surveillants affectés</title>


<style>

body{

font-family:Arial;
background:#f4f6f9;

}


h2{

text-align:center;
color:#003366;

}


table{

width:90%;
margin:30px auto;
border-collapse:collapse;
background:white;

}



th{

background:#003366;
color:white;
padding:12px;

}


td{

padding:10px;
text-align:center;
border:1px solid #ddd;

}


tr:nth-child(even){

background:#f2f2f2;

}


</style>

</head>


<body>


<h2>
Liste des surveillants affectés
</h2>


<table>


<tr>

<th>Surveillant</th>
<th>Cours</th>
<th>Salle</th>
<th>Date</th>
<th>Début</th>
<th>Fin</th>

</tr>


<?php foreach($affectations as $a){ ?>


<tr>

<td>
<?= $a["nom_surv"]." ".$a["prenom_surv"] ?>
</td>


<td>
<?= $a["nom_cours"] ?>
</td>


<td>
<?= $a["nom_salle"] ?>
</td>


<td>
<?= $a["date_exam"] ?>
</td>


<td>
<?= $a["heure_debut"] ?>
</td>


<td>
<?= $a["heure_fin"] ?>
</td>


</tr>


<?php } ?>


</table>


</body>

</html>