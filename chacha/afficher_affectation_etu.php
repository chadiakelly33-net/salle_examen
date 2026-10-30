<?php

session_start();
if(!isset($_SESSION["id_admin"]) || $_SESSION["role"] != "admin"){
    header("Location: connexion.php");
    exit();
}

include("connexionDB.php");
include("menu_principal.php");
include("journal.php");

journal(
    $bdd,
    $_SESSION["id_admin"],
    "Administrateur",
    "admin",
    "Consultation des affectations"
);

error_reporting(E_ALL);
ini_set('display_errors', 1);

// sécurité
if(!isset($_SESSION["id_etu"])){
    die("Accès refusé. Veuillez vous connecter.");
}

$id_etu = $_SESSION["id_etu"];

// récupérer affectations de l'étudiant
$req = $bdd->prepare("
    SELECT 
        a.date_exam,
        a.heure_debut,
        a.heure_fin,
        c.nom_cours,
        s.nom_salle,
        sv.nom_surv,
        sv.prenom_surv
    FROM affectation a
    JOIN cours c ON a.id_cours = c.id_cours
    JOIN salle s ON a.id_salle = s.id_salle
    JOIN surveillant sv ON a.id_surv = sv.id_surv
    WHERE a.id_etu = ?
    ORDER BY a.date_exam ASC
");

$req->execute([$id_etu]);
$affectations = $req->fetchAll();
?>

<style>
body{
    font-family: Arial;
    background:#f4f6f9;
    margin:0;
    padding:0;
}

.container{
    width:90%;
    margin:30px auto;
    background:white;
    padding:20px;
    border-radius:10px;
    box-shadow:0 0 10px rgba(0,0,0,0.1);
}

h2{
    text-align:center;
    color:#333;
    margin-bottom:20px;
}

table{
    width:100%;
    border-collapse:collapse;
}

th, td{
    padding:12px;
    border:1px solid #ddd;
    text-align:center;
}

th{
    background:#007bff;
    color:white;
}

tr:nth-child(even){
    background:#f2f2f2;
}

.empty{
    text-align:center;
    color:red;
    font-weight:bold;
    margin-top:20px;
}
</style>

<div class="container">

<h2>Mes affectations d'examens</h2>

<?php if(count($affectations) > 0){ ?>

<table>
    <tr>
        <th>Cours</th>
        <th>Salle</th>
        <th>Surveillant</th>
        <th>Date</th>
        <th>Heure début</th>
        <th>Heure fin</th>
    </tr>

    <?php foreach($affectations as $a){ ?>
    <tr>
        <td><?= $a["nom_cours"] ?></td>
        <td><?= $a["nom_salle"] ?></td>
        <td><?= $a["nom_surv"] . " " . $a["prenom_surv"] ?></td>
        <td><?= $a["date_exam"] ?></td>
        <td><?= $a["heure_debut"] ?></td>
        <td><?= $a["heure_fin"] ?></td>
    </tr>
    <?php } ?>

</table>

<?php } else { ?>

<p class="empty">Aucune affectation pour le moment.</p>

<?php } ?>

</div>