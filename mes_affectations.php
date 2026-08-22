<?php
session_start();
if(!isset($_SESSION["id_etu"])){
    header("Location: login_etu.php");
    exit();
}


$timeout = 300; // 2 minutes (test)

if (!isset($_SESSION["id_etu"])) {
    header("Location: /salleexamen/login.php");
    exit();
}

if (isset($_SESSION['last_activity'])) {

    $temps_ecoule = time() - $_SESSION['last_activity'];

    if ($temps_ecoule > $timeout) {

        session_unset();
        session_destroy();

        header("Location: /salleexamen/login.php?timeout=1");
        exit();
    }
}

$_SESSION['last_activity'] = time();
include("connexionDB.php");
include("menu_etu.php");
include("journal.php");

if(!isset($_SESSION["id_etu"])){
die("Accès refusé");
}



$id_etu=$_SESSION["id_etu"];

/* infos étudiant */

$etu=$bdd->prepare("
SELECT
e.nom_etu,
e.prenom_etu,
f.nom_fac,
n.nom_niveau
FROM etudiant e
JOIN faculte f ON e.id_fac=f.id_fac
JOIN niveau_etude n ON e.id_niveau=n.id_niveau
WHERE id_etu=?
");

$etu->execute([$id_etu]);

$info=$etu->fetch();
journal(
    $bdd,
    $_SESSION["id_etu"],
    $info["nom_etu"]." ".$info["prenom_etu"],
    "etudiant",
    "Consultation des affectations"
);
/* examens */

$req=$bdd->prepare("
SELECT
c.nom_cours,
s.nom_salle,
a.date_exam,
a.heure_debut,
a.heure_fin
FROM affectation a
JOIN cours c ON a.id_cours=c.id_cours
JOIN salle s ON a.id_salle=s.id_salle
WHERE
a.id_etu=?
AND (
    a.date_exam > CURDATE()
    OR (a.date_exam = CURDATE() AND a.heure_fin >= CURTIME())
)
ORDER BY
a.date_exam,
a.heure_debut
");

$req->execute([$id_etu]);

$examens=$req->fetchAll();


?>


<html>

<head>

<meta charset="UTF-8">

<title>Mes examens</title>

<style>

body{

font-family:Arial;

background:#f4f6f9;

margin:0;

}

.container{

width:90%;

margin:30px auto;

}

.card{

background:white;

padding:20px;

border-radius:10px;

box-shadow:0 0 10px rgba(0,0,0,.1);

}

table{

width:100%;

border-collapse:collapse;

margin-top:20px;

}

th,td{

padding:12px;

border:1px solid #ddd;

text-align:center;

}

th{

background:#003366;

color:white;

}

a{

text-decoration:none;

background:#003366;

color:white;

padding:10px 20px;

border-radius:5px;

display:inline-block;

margin-bottom:20px;

}

h2{

text-align:center;

color:#003366;

}

</style>

</head>

<body>

<div class="container">

<a href="espace_etu.php"> Retour</a>

<div class="card">

<h2>Mes affectations d'examens</h2>

<p>

<b>Étudiant :</b>

<?= $info["nom_etu"] ?>

<?= $info["prenom_etu"] ?>

</p>

<p>

<b>Faculté :</b>

<?= $info["nom_fac"] ?>

</p>

<p>

<b>Niveau :</b>

<?= $info["nom_niveau"] ?>

</p>

<table>

<tr>

<th>Cours</th>

<th>Salle</th>

<th>Date</th>

<th>Début</th>

<th>Fin</th>

</tr>

<?php

if(count($examens)>0){

foreach($examens as $e){

?>

<tr>

<td><?= $e["nom_cours"] ?></td>

<td><?= $e["nom_salle"] ?></td>

<td><?= $e["date_exam"] ?></td>

<td><?= $e["heure_debut"] ?></td>

<td><?= $e["heure_fin"] ?></td>

</tr>

<?php

}

}else{

?>

<tr>

<td colspan="5">

Aucun examen programmé.

</td>

</tr>

<?php } ?>

</table>

</div>

</div>

</body>

</html>