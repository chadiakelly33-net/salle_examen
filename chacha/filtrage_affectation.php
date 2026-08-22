<?php

session_start();

if(!isset($_SESSION["id_admin"]) || $_SESSION["role"]!="admin"){
    header("Location: connexion.php");
    exit();
}

include("connexionDB.php");
include("menu_principal.php");


// ========================
// Récupération des listes
// ========================

$facultes = $bdd->query("
SELECT * FROM faculte
ORDER BY nom_fac
")->fetchAll();


$niveaux = $bdd->query("
SELECT * FROM niveau_etude
ORDER BY nom_niveau
")->fetchAll();


$salles = $bdd->query("
SELECT * FROM salle
ORDER BY nom_salle
")->fetchAll();


// ========================
// Valeurs choisies
// ========================

$fac = $_GET["fac"] ?? "";
$niv = $_GET["niv"] ?? "";
$salle = $_GET["salle"] ?? "";


// ========================
// Requête filtrage
// ========================

$sql = "

SELECT

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


WHERE 1=1

";


$params=[];



if($fac!=""){

$sql.=" AND e.id_fac=? ";

$params[]=$fac;

}



if($niv!=""){

$sql.=" AND e.id_niveau=? ";

$params[]=$niv;

}



if($salle!=""){

$sql.=" AND a.id_salle=? ";

$params[]=$salle;

}



$sql.="

ORDER BY 
f.nom_fac,
n.nom_niveau,
e.nom_etu

";



$req=$bdd->prepare($sql);

$req->execute($params);


$resultats=$req->fetchAll();


?>



<!DOCTYPE html>

<html lang="fr">

<head>

<meta charset="UTF-8">

<title>Filtrage des affectations</title>


<style>


body{

font-family:Arial;
background:#f4f6f9;

}


.container{

width:95%;
margin:30px auto;
background:white;
padding:25px;
border-radius:10px;
box-shadow:0 0 10px rgba(0,0,0,.1);

}


h2{

text-align:center;
color:#003366;

}



form{

display:flex;
gap:15px;
justify-content:center;
margin-bottom:25px;

}



select,button{

padding:10px;
border-radius:5px;

}


button{

background:#003366;
color:white;
border:none;
cursor:pointer;

}


button:hover{

background:#0055aa;

}



table{

width:100%;
border-collapse:collapse;

}



th{

background:#003366;
color:white;
padding:10px;

}


td{

padding:10px;
border:1px solid #ddd;
text-align:center;

}


tr:nth-child(even){

background:#f2f2f2;

}


</style>


</head>


<body>



<div class="container">


<h2>
Filtrer les affectations
</h2>



<form method="GET">


<select name="fac">

<option value="">
Toutes les facultés
</option>


<?php foreach($facultes as $f){ ?>

<option 
value="<?= $f["id_fac"] ?>"
<?= ($fac==$f["id_fac"])?"selected":"" ?>
>

<?= $f["nom_fac"] ?>

</option>


<?php } ?>

</select>





<select name="niv">

<option value="">
Tous les niveaux
</option>


<?php foreach($niveaux as $n){ ?>

<option
value="<?= $n["id_niveau"] ?>"
<?= ($niv==$n["id_niveau"])?"selected":"" ?>
>

<?= $n["nom_niveau"] ?>

</option>


<?php } ?>


</select>





<select name="salle">

<option value="">
Toutes les salles
</option>


<?php foreach($salles as $s){ ?>


<option

value="<?= $s["id_salle"] ?>"

<?= ($salle==$s["id_salle"])?"selected":"" ?>

>

<?= $s["nom_salle"] ?>

</option>


<?php } ?>


</select>




<button type="submit">

Filtrer

</button>



<a href="filtrage_affectation.php">

Réinitialiser

</a>


</form>




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



<?php foreach($resultats as $r){ ?>


<tr>


<td>
<?= $r["nom_etu"]." ".$r["prenom_etu"] ?>
</td>


<td>
<?= $r["nom_fac"] ?>
</td>


<td>
<?= $r["nom_niveau"] ?>
</td>


<td>
<?= $r["nom_cours"] ?>
</td>


<td>
<?= $r["nom_salle"] ?>
</td>


<td>
<?= $r["nom_surv"]." ".$r["prenom_surv"] ?>
</td>


<td>
<?= $r["date_exam"] ?>
</td>


<td>
<?= $r["heure_debut"] ?>
</td>


<td>
<?= $r["heure_fin"] ?>
</td>


</tr>


<?php } ?>


</table>


</div>


</body>

</html>