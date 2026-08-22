<?php

session_start();

if(!isset($_SESSION["id_admin"]) || $_SESSION["role"] != "admin"){
    header("Location: connexion.php");
    exit();
}

include("connexionDB.php");
include("menu_principal.php");


// ===============================
// Récupérer les facultés
// ===============================

$facultes = $bdd->query("
    SELECT id_fac, nom_fac
    FROM faculte
    ORDER BY nom_fac
")->fetchAll();


// ===============================
// Récupérer les niveaux
// ===============================

$niveaux = $bdd->query("
    SELECT id_niveau, nom_niveau
    FROM niveau_etude
    ORDER BY nom_niveau
")->fetchAll();


// ===============================
// Valeurs sélectionnées
// ===============================

$id_fac = $_GET["id_fac"] ?? "";
$id_niveau = $_GET["id_niveau"] ?? "";


// ===============================
// Requête étudiants
// ===============================

$sql = "
    SELECT
        e.id_etu,
        e.nom_etu,
        e.prenom_etu,
        e.login,
        f.nom_fac,
        n.nom_niveau

    FROM etudiant e

    JOIN faculte f
        ON e.id_fac = f.id_fac

    JOIN niveau_etude n
        ON e.id_niveau = n.id_niveau

    WHERE 1=1
";

$params = [];


// Filtre faculté

if($id_fac != ""){
    $sql .= " AND e.id_fac = ?";
    $params[] = $id_fac;
}


// Filtre niveau

if($id_niveau != ""){
    $sql .= " AND e.id_niveau = ?";
    $params[] = $id_niveau;
}


// Tri

$sql .= "
    ORDER BY
        f.nom_fac ASC,
        n.nom_niveau ASC,
        e.nom_etu ASC,
        e.prenom_etu ASC
";


$req = $bdd->prepare($sql);
$req->execute($params);

$etudiants = $req->fetchAll();

?>

<!DOCTYPE html>

<html lang="fr">

<head>

<meta charset="UTF-8">

<title>Rapport des étudiants</title>

<style>

body{
    margin:0;
    font-family:Arial, sans-serif;
    background:#f4f6f9;
}


/* Conteneur */

.container{
    width:90%;
    margin:30px auto;
}


/* Titre */

h2{
    text-align:center;
    color:#003366;
    margin-bottom:25px;
}


/* Zone de recherche */

.filtres{
    background:#fff;
    padding:20px;
    border-radius:8px;
    box-shadow:0 2px 8px rgba(0,0,0,0.1);

    display:flex;
    align-items:end;
    gap:20px;
    justify-content:center;
}


.champ{
    display:flex;
    flex-direction:column;
}


.champ label{
    margin-bottom:6px;
    font-weight:bold;
    color:#333;
}


select{
    width:220px;
    padding:10px;

    border:1px solid #ccc;
    border-radius:5px;

    background:white;
}


/* Bouton */

button{
    padding:10px 20px;

    border:none;
    border-radius:5px;

    background:#003366;
    color:white;

    cursor:pointer;
}


button:hover{
    background:#0055aa;
}


/* Tableau */

.tableau{
    margin-top:25px;

    background:white;

    padding:20px;

    border-radius:8px;

    box-shadow:0 2px 8px rgba(0,0,0,0.1);
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
    padding:10px;
    border:1px solid #ddd;
    text-align:center;
}


tr:nth-child(even){
    background:#f4f6f9;
}


.message{
    text-align:center;
    padding:20px;
    color:#666;
}

/* =========================
   BOUTONS DE TELECHARGEMENT
========================= */

.telechargements{
    text-align:center;
    margin-top:25px;
}

.telechargements a{
    display:inline-block;
    padding:11px 20px;
    margin:5px;

    background:#003366;
    color:white;

    text-decoration:none;

    border-radius:5px;

    font-size:14px;

    transition:0.3s;
}

.telechargements a:hover{
    background:#0055aa;
}

</style>

</head>

<body>

<div class="container">

<h2>
Rapport des étudiants
</h2>

<!-- =========================
     FILTRES
========================= -->

<form method="GET" class="filtres">

<div class="champ">

<label>Faculté</label>

<select name="id_fac">

<option value="">
Toutes les facultés
</option>

<?php foreach($facultes as $fac): ?>

<option
    value="<?= $fac["id_fac"] ?>"
    <?= ($id_fac == $fac["id_fac"]) ? "selected" : "" ?>
>

<?= htmlspecialchars($fac["nom_fac"]) ?>

</option>

<?php endforeach; ?>

</select>

</div>

<div class="champ">

<label>Niveau</label>

<select name="id_niveau">

<option value="">
Tous les niveaux
</option>

<?php foreach($niveaux as $niv): ?>

<option
    value="<?= $niv["id_niveau"] ?>"
    <?= ($id_niveau == $niv["id_niveau"]) ? "selected" : "" ?>
>

<?= htmlspecialchars($niv["nom_niveau"]) ?>

</option>

<?php endforeach; ?>

</select>

</div>

<button type="submit">
Afficher
</button>

<div class="telechargements">

    <a href="telecharger_etudiants.php?id_fac=<?= urlencode($id_fac) ?>&id_niveau=<?= urlencode($id_niveau) ?>">
        Télécharger Excel
    </a>

    <a href="telecharger_etudiant_pdf.php?id_fac=<?= urlencode($id_fac) ?>&id_niveau=<?= urlencode($id_niveau) ?>">
        Télécharger PDF
    </a>

</div>


</form>

<!-- =========================
     RESULTATS
========================= -->

<div class="tableau">

<?php if(count($etudiants) > 0): ?>

<table>

<tr>

<th>N°</th>
<th>Nom</th>
<th>Prénom</th>
<th>Login</th>
<th>Faculté</th>
<th>Niveau</th>

</tr>

<?php $numero = 1; ?>

<?php foreach($etudiants as $e): ?>

<tr>

<td>
<?= $numero++ ?>
</td>

<td>
<?= htmlspecialchars($e["nom_etu"]) ?>
</td>

<td>
<?= htmlspecialchars($e["prenom_etu"]) ?>
</td>

<td>
<?= htmlspecialchars($e["login"]) ?>
</td>

<td>
<?= htmlspecialchars($e["nom_fac"]) ?>
</td>

<td>
<?= htmlspecialchars($e["nom_niveau"]) ?>
</td>

</tr>

<?php endforeach; ?>

</table>

<?php else: ?>

<div class="message">

Aucun étudiant trouvé pour les critères sélectionnés.

</div>

<?php endif; ?>

</div>

</div>

</body>

</html>
