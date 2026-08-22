<?php
session_start();

if(!isset($_SESSION["id_admin"]) || $_SESSION["role"]!="admin"){
    header("Location: connexion.php");
    exit();
}

include("connexionDB.php");
include("menu_principal.php");

/* Listes */

$salles=$bdd->query("SELECT * FROM salle ORDER BY nom_salle")->fetchAll();

$facultes=$bdd->query("SELECT * FROM faculte ORDER BY nom_fac")->fetchAll();

$niveaux=$bdd->query("SELECT * FROM niveau_etude ORDER BY nom_niveau")->fetchAll();

$cours=$bdd->query("SELECT * FROM cours ORDER BY nom_cours")->fetchAll();

$surveillants=$bdd->query("
SELECT *
FROM surveillant
ORDER BY nom_surv
")->fetchAll();


$resultats=[];

if(isset($_GET["rechercher"])){

$id_salle=$_GET["id_salle"];
$id_fac=$_GET["id_fac"];
$id_niveau=$_GET["id_niveau"];
$id_cours=$_GET["id_cours"];
$id_surv=$_GET["id_surv"];
$date_exam=$_GET["date_exam"];

$sql="

SELECT

a.id_affectation,

e.nom_etu,
e.prenom_etu,

f.nom_fac,

n.nom_niveau,

c.nom_cours,

s.nom_salle,

sv.nom_surv,
sv.prenom_surv,

a.date_exam,
a.heure_debut,
a.heure_fin

FROM affectation a

JOIN etudiant e
ON a.id_etu=e.id_etu

JOIN faculte f
ON e.id_fac=f.id_fac

JOIN niveau_etude n
ON e.id_niveau=n.id_niveau

JOIN cours c
ON a.id_cours=c.id_cours

JOIN salle s
ON a.id_salle=s.id_salle

JOIN surveillant sv
ON a.id_surv=sv.id_surv

WHERE

(:salle='' OR a.id_salle=:salle)

AND

(:fac='' OR e.id_fac=:fac)

AND

(:niveau='' OR e.id_niveau=:niveau)

AND

(:cours='' OR a.id_cours=:cours)

AND

(:surv='' OR a.id_surv=:surv)

AND

(:date='' OR a.date_exam=:date)

ORDER BY

a.date_exam,
a.heure_debut,
e.nom_etu

";

$req=$bdd->prepare($sql);

$req->execute([

"salle"=>$id_salle,

"fac"=>$id_fac,

"niveau"=>$id_niveau,

"cours"=>$id_cours,

"surv"=>$id_surv,

"date"=>$date_exam

]);

$resultats=$req->fetchAll();

}
?>

<!DOCTYPE html>

<html>

<head>

<meta charset="UTF-8">

<title>Recherche avancée</title>

<style>

body{

font-family:Arial;

background:#f4f6f9;

margin:0;

}

.container{

width:95%;

margin:20px auto;

background:white;

padding:20px;

border-radius:10px;

box-shadow:0 0 10px rgba(0,0,0,.1);

}

h2{

text-align:center;

color:#003366;

}

form{

display:grid;

grid-template-columns:repeat(3,1fr);

gap:15px;

margin-bottom:20px;

}

select,input{

padding:10px;

border:1px solid #ccc;

border-radius:5px;

}

button{

grid-column:span 3;

padding:12px;

background:#003366;

color:white;

border:none;

border-radius:6px;

cursor:pointer;

}

button:hover{

background:#0055aa;

}

table{

width:100%;

border-collapse:collapse;

margin-top:20px;

}

th,td{

padding:10px;

border:1px solid #ddd;

text-align:center;

}

th{

background:#003366;

color:white;

}

tr:nth-child(even){

background:#f2f2f2;

}

</style>

</head>

<body>

<div class="container">

<h2>Recherche avancée des affectations</h2>

<form method="GET">

<select name="id_salle">

<option value="">Toutes les salles</option>

<?php foreach($salles as $s){ ?>

<option value="<?= $s["id_salle"] ?>">

<?= $s["nom_salle"] ?>

</option>

<?php } ?>

</select>



<select name="id_fac">

<option value="">Toutes les facultés</option>

<?php foreach($facultes as $f){ ?>

<option value="<?= $f["id_fac"] ?>">

<?= $f["nom_fac"] ?>

</option>

<?php } ?>

</select>



<select name="id_niveau">

<option value="">Tous les niveaux</option>

<?php foreach($niveaux as $n){ ?>

<option value="<?= $n["id_niveau"] ?>">

<?= $n["nom_niveau"] ?>

</option>

<?php } ?>

</select>



<select name="id_cours">

<option value="">Tous les cours</option>

<?php foreach($cours as $c){ ?>

<option value="<?= $c["id_cours"] ?>">

<?= $c["nom_cours"] ?>

</option>

<?php } ?>

</select>



<select name="id_surv">

<option value="">Tous les surveillants</option>

<?php foreach($surveillants as $sv){ ?>

<option value="<?= $sv["id_surv"] ?>">

<?= $sv["nom_surv"]." ".$sv["prenom_surv"] ?>

</option>

<?php } ?>

</select>



<input
type="date"
name="date_exam"
>



<button
type="submit"
name="rechercher">

Rechercher

</button>

</form>

<?php

if(isset($_GET["rechercher"])){

echo "<h3>";

echo count($resultats)." résultat(s) trouvé(s).";

echo "</h3>";

}

?>

<table>

<tr>

<th>Étudiant</th>

<th>Faculté</th>

<th>Niveau</th>

<th>Cours</th>

<th>Salle</th>

<th>Surveillant</th>

<th>Date</th>

<th>Début</th>

<th>Fin</th>

</tr>

<?php

if(count($resultats)>0){

foreach($resultats as $r){

?>

<tr>

<td><?= $r["nom_etu"]." ".$r["prenom_etu"] ?></td>

<td><?= $r["nom_fac"] ?></td>

<td><?= $r["nom_niveau"] ?></td>

<td><?= $r["nom_cours"] ?></td>

<td><?= $r["nom_salle"] ?></td>

<td><?= $r["nom_surv"]." ".$r["prenom_surv"] ?></td>

<td><?= $r["date_exam"] ?></td>

<td><?= $r["heure_debut"] ?></td>

<td><?= $r["heure_fin"] ?></td>

</tr>

<?php

}

}else{

?>

<tr>

<td colspan="9">

Aucun résultat.

</td>

</tr>

<?php

}

?>

</table>

</div>

</body>

</html>