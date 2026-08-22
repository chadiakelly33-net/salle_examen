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
    "Consultation des étudiants d'une salle"
);

/* Liste des salles */
$salles = $bdd->query("
SELECT *
FROM salle
ORDER BY nom_salle
")->fetchAll();

$resultats = [];

if(isset($_POST["consulter"])){

    $id_salle = $_POST["id_salle"];
    $date_exam = $_POST["date_exam"];
    $heure_debut = $_POST["heure_debut"];
	
	$reqSalle = $bdd->prepare("
    SELECT nom_salle
    FROM salle
    WHERE id_salle = ?
");

$reqSalle->execute([$id_salle]);

$salle = $reqSalle->fetch();

    $req = $bdd->prepare("
        CALL consulter_etudiants_salle(?,?,?)
    ");

    $req->execute([
        $id_salle,
        $date_exam,
        $heure_debut
    ]);

    $resultats = $req->fetchAll();

    $req->closeCursor();
}
?>


<html lang="fr">

<head>

<meta charset="UTF-8">

<title>Étudiants d'une salle</title>

<style>

body{
    margin:0;
    font-family:Arial;
    background:#f4f6f9;
}

.container{

    width:90%;
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

label{

    display:block;
    margin-top:15px;
    font-weight:bold;

}

select,input{

    width:100%;
    padding:10px;
    margin-top:5px;
    border:1px solid #ccc;
    border-radius:5px;

}

button{

    margin-top:20px;
    width:100%;
    padding:12px;
    background:#003366;
    color:white;
    border:none;
    border-radius:5px;
    cursor:pointer;
    font-size:16px;

}

button:hover{

    background:#0055aa;

}

table{

    width:100%;
    border-collapse:collapse;
    margin-top:30px;

}

th{

    background:#003366;
    color:white;
    padding:10px;

}

td{

    border:1px solid #ddd;
    padding:10px;
    text-align:center;

}

tr:nth-child(even){

    background:#f2f2f2;

}

.message{

    text-align:center;
    color:red;
    margin-top:20px;
    font-weight:bold;

}

</style>

</head>

<body>

<div class="container">

<h2>Consulter les étudiants d'une salle</h2>

<form method="POST">

<label>Salle</label>

<select name="id_salle" required>

<?php foreach($salles as $s){ ?>

<option value="<?= $s["id_salle"] ?>">

<?= htmlspecialchars($s["nom_salle"]) ?>

</option>

<?php } ?>

</select>

<label>Date d'examen</label>

<input
type="date"
name="date_exam"
required>

<label>Heure de début</label>

<input
type="time"
name="heure_debut"
required>

<button
type="submit"
name="consulter">

Consulter

</button>

</form>

<?php if(isset($_POST["consulter"])){ ?>

<?php if(count($resultats)>0){ ?>

<table>

<tr>

<th>Nom</th>

<th>Prénom</th>

<th>Faculté</th>

<th>Niveau</th>

<th>Cours</th>

</tr>

<?php foreach($resultats as $r){ ?>

<tr>

<td><?= htmlspecialchars($r["nom_etu"]) ?></td>

<td><?= htmlspecialchars($r["prenom_etu"]) ?></td>

<td><?= htmlspecialchars($r["nom_fac"]) ?></td>

<td><?= htmlspecialchars($r["nom_niveau"]) ?></td>

<td><?= htmlspecialchars($r["nom_cours"]) ?></td>

</tr>

<?php } ?>

</table>

<?php }else{ ?>

<p class="message">
Aucun étudiant n'est affecté à la salle
<b><?= htmlspecialchars($salle["nom_salle"]) ?></b>
le
<b><?= htmlspecialchars($date_exam) ?></b>
à
<b><?= htmlspecialchars(substr($heure_debut,0,5)) ?></b>.
</p>

<?php } ?>

<?php } ?>

</div>

</body>

</html>