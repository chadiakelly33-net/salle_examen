<?php
session_start();

if(!isset($_SESSION["id_admin"]) || $_SESSION["role"]!="admin"){
    header("Location: connexion.php");
    exit();
}

include("connexionDB.php");
include("menu_principal.php");

/*=========================
   Récupération du tri
=========================*/

$tri = $_GET["tri"] ?? "date";
$ordre = $_GET["ordre"] ?? "ASC";

/* Colonnes autorisées */

switch($tri){

    case "nom":
        $champ = "e.nom_etu";
    break;

    case "faculte":
        $champ = "f.nom_fac";
    break;

    case "niveau":
        $champ = "n.nom_niveau";
    break;

    case "cours":
        $champ = "c.nom_cours";
    break;

    case "salle":
        $champ = "s.nom_salle";
    break;

    case "surveillant":
        $champ = "sv.nom_surv";
    break;

    case "date":
        $champ = "a.date_exam";
    break;

    default:
        $champ = "a.date_exam";

}

/* Ordre */

$ordre = strtoupper($ordre);

if($ordre!="DESC"){
    $ordre="ASC";
}

/*=========================
   Requête
=========================*/

$sql = "
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

ORDER BY $champ $ordre
";

$req = $bdd->prepare($sql);
$req->execute();

$liste = $req->fetchAll();

?>


<html lang="fr">

<head>

<meta charset="UTF-8">

<title>Tri des affectations</title>

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

padding:25px;

border-radius:10px;

box-shadow:0 0 10px rgba(0,0,0,.1);

}

h2{

text-align:center;
color:#003366;

}

form{

text-align:center;

margin-bottom:25px;

}

select{

padding:10px;

width:220px;

margin:10px;

}

button{

padding:10px 25px;

background:#003366;

color:white;

border:none;

border-radius:5px;

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

.retour{

display:inline-block;

padding:10px 20px;

background:#003366;

color:white;

text-decoration:none;

border-radius:5px;

margin-bottom:20px;

}

</style>

</head>

<body>

<div class="container">

<a href="menu_principal.php" class="retour">
Retour
</a>

<h2>
Tri des affectations d'examens
</h2>

<form method="GET">

<label><b>Trier par :</b></label>

<select name="tri">

<option value="nom">Nom étudiant</option>

<option value="faculte">Faculté</option>

<option value="niveau">Niveau</option>

<option value="cours">Cours</option>

<option value="salle">Salle</option>

<option value="surveillant">Surveillant</option>

<option value="date">Date d'examen</option>

</select>

<label><b>Ordre :</b></label>

<select name="ordre">

<option value="ASC">Croissant</option>

<option value="DESC">Décroissant</option>

</select>

<button type="submit">

Trier

</button>

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

<?php foreach($liste as $a){ ?>

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
<?= htmlspecialchars($a["date_exam"]) ?>
</td>

<td>
<?= htmlspecialchars($a["heure_debut"]) ?>
</td>

<td>
<?= htmlspecialchars($a["heure_fin"]) ?>
</td>

</tr>

<?php } ?>

</table>

</div>

</body>

</html>