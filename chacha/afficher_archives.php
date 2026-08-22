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
    "Consultation des archives des examens"
);

/* Récupération des archives */

$req = $bdd->query("
SELECT
    a.id_archive,
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
    a.heure_fin,
    a.date_archivage

FROM affectation_archive a

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

ORDER BY a.date_archivage DESC
");

$archives = $req->fetchAll();

?>

<!DOCTYPE html>

<html lang="fr">

<head>

<meta charset="UTF-8">

<title>Archives des examens</title>

<style>

body{
    font-family:Arial;
    background:#f4f6f9;
    margin:0;
}

.container{

    width:95%;
    margin:30px auto;
    background:white;
    padding:20px;
    border-radius:10px;
    box-shadow:0 0 10px rgba(0,0,0,.1);

}

h2{

    text-align:center;
    color:#003366;
    margin-bottom:25px;

}

table{

    width:100%;
    border-collapse:collapse;

}

th{

    background:#003366;
    color:white;
    padding:12px;

}

td{

    border:1px solid #ddd;
    padding:10px;
    text-align:center;

}

tr:nth-child(even){

    background:#f2f2f2;

}

.btn{

    display:inline-block;
    padding:8px 15px;
    background:green;
    color:white;
    text-decoration:none;
    border-radius:5px;
    font-weight:bold;

}

.btn:hover{

    background:#0b7a0b;

}

.message{

    text-align:center;
    color:red;
    font-size:18px;
    font-weight:bold;

}

</style>

</head>

<body>

<div class="container">

<h2>Historique des examens archivés</h2>

<?php if(count($archives)>0){ ?>

<table>

<tr>

<th>Étudiant</th>

<th>Faculté</th>

<th>Niveau</th>

<th>Cours</th>

<th>Salle</th>

<th>Surveillant</th>

<th>Date examen</th>

<th>Début</th>

<th>Fin</th>

<th>Date archivage</th>

<th>Action</th>

</tr>

<?php foreach($archives as $a){ ?>

<tr>

<td>
<?= htmlspecialchars($a["nom_etu"]." ".$a["prenom_etu"]) ?>
</td>

<td>
<?= htmlspecialchars($a["nom_fac"]) ?>
</td>

<td>
<?= htmlspecialchars($a["nom_niveau"]) ?>
</td>

<td>
<?= htmlspecialchars($a["nom_cours"]) ?>
</td>

<td>
<?= htmlspecialchars($a["nom_salle"]) ?>
</td>

<td>
<?= htmlspecialchars($a["nom_surv"]." ".$a["prenom_surv"]) ?>
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

<td>
<?= $a["date_archivage"] ?>
</td>

<td>

<a
class="btn"
href="restaurer_archive.php?id=<?= $a["id_archive"] ?>"
onclick="return confirm('Voulez-vous restaurer cette affectation ?');">

Restaurer

</a>

</td>

</tr>

<?php } ?>

</table>

<?php }else{ ?>

<p class="message">

Aucune affectation archivée.

</p>

<?php } ?>

</div>

</body>

</html>