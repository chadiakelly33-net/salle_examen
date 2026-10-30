<?php

session_start();
if(!isset($_SESSION["id_admin"]) || $_SESSION["role"] != "admin"){
    header("Location: connexion.php");
    exit();
}

include("connexionDB.php");
?>
<?php
include("connexionDB.php");

$req = $bdd->query("
SELECT
a.*,
c.nom_cours,
s.nom_salle,
sv.nom_surv,
sv.prenom_surv
FROM affectation a
JOIN cours c ON a.id_cours = c.id_cours
JOIN salle s ON a.id_salle = s.id_salle
JOIN surveillant sv ON a.id_surv = sv.id_surv
");
?>
<aside id="menu" class="menu">
<?php include 'Menu_principal.php';?>
</aside>

<style>
table{
    width:90%;
    margin:auto;
    border-collapse:collapse;
    background:white;
}

th,td{
    border:1px solid #ddd;
    padding:10px;
    text-align:center;
}

th{
    background:#003366;
    color:white;
}
</style>

<table>
<tr>
<th>Cours</th>
<th>Salle</th>
<th>Surveillant</th>
<th>Date</th>
<th>Début</th>
<th>Fin</th>
</tr>

<?php while($row=$req->fetch()){ ?>

<tr>
<td><?= $row["nom_cours"] ?></td>
<td><?= $row["nom_salle"] ?></td>
<td><?= $row["nom_surv"]." ".$row["prenom_surv"] ?></td>
<td><?= $row["date_exam"] ?></td>
<td><?= $row["heure_debut"] ?></td>
<td><?= $row["heure_fin"] ?></td>
</tr>

<?php } ?>

</table>