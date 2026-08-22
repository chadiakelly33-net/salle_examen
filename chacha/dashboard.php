<?php
session_start();

if(!isset($_SESSION["id_admin"]) || $_SESSION["role"] != "admin"){
    header("Location: connexion.php");
    exit();
}

include("connexionDB.php");
include("menu_principal.php");
?>

<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Dashboard Admin</title>

<style>

body{
    margin:0;
    font-family:Arial;
    background:#f4f6f9;
}

.wrapper{
    display:flex;
}

/* MENU */
aside{
    width:220px;
    background:#111827;
    color:white;
    min-height:100vh;
    padding:20px;
}

/* CONTENT */
main{
    flex:1;
    padding:25px;
}

/* HEADER */
.header{
    text-align:center;
    margin-bottom:25px;
}

.header h1{
    margin:0;
    font-size:24px;
}

.header p{
    color:#6b7280;
}

/* STATS */
.stats{
    display:flex;
    gap:15px;
    margin-bottom:25px;
}

.card{
    flex:1;
    background:white;
    padding:15px;
    border-radius:10px;
    box-shadow:0 2px 10px rgba(0,0,0,0.05);
    text-align:center;
}

.card h2{
    margin:0;
    font-size:20px;
}

/* GRID */
.grid{
    display:flex;
    gap:20px;
}

.box{
    flex:1;
    background:white;
    padding:18px;
    border-radius:10px;
    box-shadow:0 2px 10px rgba(0,0,0,0.05);
}

.item{
    padding:10px;
    margin-bottom:8px;
    border-radius:6px;
    background:#f9fafb;
    display:flex;
    justify-content:space-between;
}

.green{color:#16a34a;font-weight:bold;}
.red{color:#dc2626;font-weight:bold;}

</style>
</head>

<body>

<div class="wrapper">


<main>

<div class="header">
    <h1>Dashboard Administrateur</h1>
    <p>Gestion intelligente des examens</p>
</div>

<!-- STATS 
<div class="stats">

<?php
$totalEtu = $bdd->query("SELECT COUNT(*) AS t FROM etudiant")->fetch()["t"];
$totalSalle = $bdd->query("SELECT COUNT(*) AS t FROM salle")->fetch()["t"];
$totalSurv = $bdd->query("SELECT COUNT(*) AS t FROM surveillant")->fetch()["t"];
$totalAff = $bdd->query("SELECT COUNT(*) AS t FROM affectation")->fetch()["t"];
?>

<div class="card"><h2><?= $totalEtu ?></h2><p>Étudiants</p></div>
<div class="card"><h2><?= $totalSalle ?></h2><p>Salles</p></div>
<div class="card"><h2><?= $totalSurv ?></h2><p>Surveillants</p></div>
<div class="card"><h2><?= $totalAff ?></h2><p>Affectations</p></div>

</div>-->

<div class="grid">

<!-- SALLES -->
<div class="box">
<h2>Disponibilite des salles</h2>

<?php
$salles = $bdd->query("
SELECT s.id_salle, s.nom_salle,
CASE
    WHEN EXISTS (
        SELECT 1 FROM affectation a
        WHERE a.id_salle = s.id_salle
        AND CURDATE() = a.date_exam
        AND CURTIME() BETWEEN a.heure_debut AND a.heure_fin
    )
    THEN 'occupée'
    ELSE 'libre'
END AS statut
FROM salle s
");

while($s = $salles->fetch()){
?>
<div class="item">
    <span><?= $s["nom_salle"] ?></span>
    <span class="<?= $s["statut"]=='libre'?'green':'red' ?>">
        <?= ucfirst($s["statut"]) ?>
    </span>
</div>
<?php } ?>

</div>

<!-- SURVEILLANTS -->
<div class="box">
<h2>Disponibilite des surveillants</h2>

<?php
$sur = $bdd->query("
SELECT sv.id_surv, sv.nom_surv, sv.prenom_surv,
CASE
    WHEN EXISTS (
        SELECT 1 FROM affectation a
        WHERE a.id_surv = sv.id_surv
        AND CURDATE() = a.date_exam
        AND CURTIME() BETWEEN a.heure_debut AND a.heure_fin
    )
    THEN 'occupé'
    ELSE 'libre'
END AS statut
FROM surveillant sv
");

while($s = $sur->fetch()){
?>
<div class="item">
    <span><?= $s["nom_surv"]." ".$s["prenom_surv"] ?></span>
    <span class="<?= $s["statut"]=='libre'?'green':'red' ?>">
        <?= ucfirst($s["statut"]) ?>
    </span>
</div>
<?php } ?>

</div>

</div>

</main>
</div>

</body>
</html>