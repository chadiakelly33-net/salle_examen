<?php
session_start();

include("connexionDB.php");
include("menu_etu.php");
include("journal.php");

// Vérifier la connexion de l'étudiant
if (
    !isset($_SESSION["id_etu"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "etudiant"
) {
    header("Location: login_etu.php");
    exit();
}


$id_etu = $_SESSION["id_etu"];

// Récupérer les informations de l'étudiant
$req = $bdd->prepare("
SELECT
    e.nom_etu,
    e.prenom_etu,
    e.login,
    e.photo,
    f.nom_fac,
    n.nom_niveau
FROM etudiant e
JOIN faculte f
ON e.id_fac = f.id_fac
JOIN niveau_etude n
ON e.id_niveau = n.id_niveau
WHERE e.id_etu = ?
");

$req->execute([$id_etu]);

$etu = $req->fetch(PDO::FETCH_ASSOC);

if(!$etu){
    die("Étudiant introuvable.");
}

// Journal d'activité
journal(
    $bdd,
    $_SESSION["id_etu"],
    $etu["nom_etu"]." ".$etu["prenom_etu"],
    "etudiant",
    "Consultation du profil"
);
?>

<!DOCTYPE html>
<html lang="fr">

<head>

<meta charset="UTF-8">

<title>Mon profil</title>

<style>

body{
    font-family:Arial, Helvetica, sans-serif;
    background:#f4f6f9;
    margin:0;
}

.container{
    width:500px;
    margin:50px auto;
    background:white;
    padding:30px;
    border-radius:10px;
    box-shadow:0 0 10px rgba(0,0,0,.1);
}

h2{
    text-align:center;
    color:#003366;
    margin-bottom:25px;
}

.photo{
    text-align:center;
    margin-bottom:25px;
}

.photo img{
    width:150px;
    height:150px;
    border-radius:50%;
    object-fit:cover;
    border:4px solid #003366;
}

.photo .vide{
    width:150px;
    height:150px;
    border-radius:50%;
    margin:auto;
    background:#ddd;
    display:flex;
    align-items:center;
    justify-content:center;
    color:#666;
    font-size:14px;
    border:4px solid #003366;
}

.info{
    margin:15px 0;
    font-size:17px;
}

.label{
    font-weight:bold;
    color:#003366;
}

.btn{
    display:block;
    text-align:center;
    background:#003366;
    color:white;
    padding:12px;
    margin-top:15px;
    text-decoration:none;
    border-radius:6px;
}

.btn:hover{
    background:#0055aa;
}

</style>

</head>

<body>

<div class="container">

<h2>Mon profil</h2>

<div class="photo">

<?php
if(!empty($etu["photo"]) && file_exists("chacha/uploads/".$etu["photo"])){
?>

<img
src="chacha/uploads/<?= htmlspecialchars($etu["photo"]) ?>"
alt="Photo de profil">

<?php
}else{
?>

<div class="vide">
Aucune photo
</div>

<?php
}
?>

</div>

<div class="info">
<span class="label">Nom :</span>
<?= htmlspecialchars($etu["nom_etu"]) ?>
</div>

<div class="info">
<span class="label">Prénom :</span>
<?= htmlspecialchars($etu["prenom_etu"]) ?>
</div>

<div class="info">
<span class="label">Login :</span>
<?= htmlspecialchars($etu["login"]) ?>
</div>

<div class="info">
<span class="label">Faculté :</span>
<?= htmlspecialchars($etu["nom_fac"]) ?>
</div>

<div class="info">
<span class="label">Niveau :</span>
<?= htmlspecialchars($etu["nom_niveau"]) ?>
</div>

<a class="btn" href="espace_etu.php">
Retour
</a>

<a class="btn" href="changer_motdepasse.php">
Modifier mon mot de passe
</a>

</div>

</body>
</html>