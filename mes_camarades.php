<?php
session_start();

if(!isset($_SESSION["id_etu"])){
    header("Location: login_etu.php");
    exit();
}

$timeout = 300; // 5 minutes

if(isset($_SESSION['last_activity'])){

    $temps_ecoule = time() - $_SESSION['last_activity'];

    if($temps_ecoule > $timeout){

        session_unset();
        session_destroy();

        header("Location: /salleexamen/login_etu.php?timeout=1");
        exit();
    }
}

$_SESSION['last_activity'] = time();

include("connexionDB.php");
include("menu_etu.php");
include("journal.php");

$id_etu = $_SESSION["id_etu"];

/* Informations de l'étudiant connecté */
$req = $bdd->prepare("
SELECT
    nom_etu,
    prenom_etu,
    id_fac,
    id_niveau
FROM etudiant
WHERE id_etu=?
");

$req->execute([$id_etu]);
$etu = $req->fetch(PDO::FETCH_ASSOC);

if(!$etu){
    die("Étudiant introuvable.");
}

$id_fac = $etu["id_fac"];
$id_niveau = $etu["id_niveau"];

/* Liste des camarades */
$req = $bdd->prepare("
SELECT
    nom_etu,
    prenom_etu,
    photo
FROM etudiant
WHERE id_fac=?
AND id_niveau=?
ORDER BY nom_etu ASC, prenom_etu ASC
");

$req->execute([$id_fac, $id_niveau]);

$camarades = $req->fetchAll(PDO::FETCH_ASSOC);

/* Journal */
journal(
    $bdd,
    $_SESSION["id_etu"],
    $etu["nom_etu"]." ".$etu["prenom_etu"],
    "etudiant",
    "Consultation de la liste des camarades de classe"
);
?>

<!DOCTYPE html>
<html lang="fr">

<head>

<meta charset="UTF-8">

<title>Mes camarades</title>

<style>

body{
    font-family:Arial, Helvetica, sans-serif;
    background:#f4f6f9;
    margin:0;
}

.container{
    width:85%;
    margin:30px auto;
}

.card{
    background:white;
    padding:25px;
    border-radius:10px;
    box-shadow:0 0 10px rgba(0,0,0,.1);
}

h2{
    text-align:center;
    color:#003366;
}

table{
    width:100%;
    border-collapse:collapse;
    margin-top:20px;
}

th,
td{
    padding:12px;
    border:1px solid #ddd;
    text-align:center;
}

th{
    background:#003366;
    color:white;
}

.photo{
    width:60px;
    height:60px;
    border-radius:50%;
    object-fit:cover;
    border:2px solid #003366;
}

.photo-vide{
    width:60px;
    height:60px;
    border-radius:50%;
    background:#ddd;
    color:#666;
    display:flex;
    align-items:center;
    justify-content:center;
    margin:auto;
    font-size:11px;
}

.btn{
    text-decoration:none;
    background:#003366;
    color:white;
    padding:10px 20px;
    border-radius:5px;
    display:inline-block;
    margin-bottom:20px;
}

.btn:hover{
    background:#0055aa;
}

tr:nth-child(even){
    background:#f8f8f8;
}

</style>

</head>

<body>

<div class="container">

<a class="btn" href="espace_etu.php">Retour</a>

<div class="card">

<h2>Mes camarades de classe</h2>

<table>

<tr>
    <th>N°</th>
    <th>Photo</th>
    <th>Nom</th>
    <th>Prénom</th>
</tr>

<?php
$i = 1;

foreach($camarades as $c){
?>

<tr>

<td><?= $i++ ?></td>

<td>

<?php
if(!empty($c["photo"]) && file_exists("chacha/uploads/".$c["photo"])){
?>

<img
src="chacha/uploads/<?= htmlspecialchars($c["photo"]) ?>"
class="photo"
alt="Photo">

<?php
}else{
?>

<div class="photo-vide">
Aucune
</div>

<?php
}
?>

</td>

<td><?= htmlspecialchars($c["nom_etu"]) ?></td>

<td><?= htmlspecialchars($c["prenom_etu"]) ?></td>

</tr>

<?php
}
?>

</table>

</div>

</div>

</body>

</html>