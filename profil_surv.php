<?php

session_start();

include("connexionDB.php");
include("journal.php");


// Vérifier connexion surveillant

if (
    !isset($_SESSION["id_surv"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "surveillant"
) {
    header("Location: login_surv.php");
    exit();
}

$id_surv = $_SESSION["id_surv"];


// Récupérer les informations du surveillant

$req = $bdd->prepare("
SELECT 
    nom_surv,
    prenom_surv,
    login,
    photo
FROM surveillant
WHERE id_surv = ?
");


$req->execute([$id_surv]);


$surv = $req->fetch();


if(!$surv){

    die("Surveillant introuvable");

}


// Journalisation

journal(
    $bdd,
    $_SESSION["id_surv"],
    $surv["nom_surv"]." ".$surv["prenom_surv"],
    "surveillant",
    "Consultation du profil"
);

?>

<!DOCTYPE html>

<html lang="fr">

<head>

<meta charset="UTF-8">

<title>Mon profil surveillant</title>


<style>

/* =========================
   PAGE
========================= */

*{

    margin:0;

    padding:0;

    box-sizing:border-box;

}


body{

    font-family:Arial, sans-serif;

    background:#f4f6f9;

    min-height:100vh;

}


/* =========================
   CONTAINER
========================= */

.container{

    width:500px;

    max-width:90%;

    margin:50px auto;

    background:white;

    padding:30px;

    border-radius:10px;

    box-shadow:0 0 10px rgba(0,0,0,.1);

}


/* =========================
   TITRE
========================= */

h2{

    text-align:center;

    color:#003366;

    margin-bottom:25px;

}


/* =========================
   PHOTO
========================= */

.photo-container{

    text-align:center;

    margin-bottom:25px;

}


.photo-profil{

    width:140px;

    height:140px;

    border-radius:50%;

    object-fit:cover;

    border:4px solid #003366;

}


.photo-vide{

    width:140px;

    height:140px;

    border-radius:50%;

    background:#ddd;

    display:flex;

    align-items:center;

    justify-content:center;

    margin:auto;

    color:#666;

    font-size:14px;

    border:4px solid #ccc;

}


/* =========================
   INFORMATIONS
========================= */

.info{

    font-size:18px;

    margin:15px 0;

    padding:10px;

    border-bottom:1px solid #eee;

}


.label{

    font-weight:bold;

    color:#003366;

}


/* =========================
   BOUTONS
========================= */

.btn{

    display:block;

    text-align:center;

    background:#003366;

    color:white;

    padding:12px;

    margin-top:20px;

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


<h2>

Mon profil

</h2>


<!-- =========================
     PHOTO DE PROFIL
========================= -->

<div class="photo-container">


<?php

if(

    !empty($surv["photo"]) &&

    file_exists(__DIR__ . "/uploads/" . $surv["photo"])

){

?>

<img

    src="uploads/<?= htmlspecialchars($surv["photo"]) ?>"

    alt="Photo de profil"

    class="photo-profil"

>


<?php

}else{

?>

<div class="photo-vide">

    Aucune photo

</div>

<?php

}

?>

</div>


<!-- =========================
     NOM
========================= -->

<div class="info">

<span class="label">

Nom :

</span>

<?= htmlspecialchars($surv["nom_surv"]) ?>

</div>


<!-- =========================
     PRENOM
========================= -->

<div class="info">

<span class="label">

Prénom :

</span>

<?= htmlspecialchars($surv["prenom_surv"]) ?>

</div>


<!-- =========================
     LOGIN
========================= -->

<div class="info">

<span class="label">

Login :

</span>

<?= htmlspecialchars($surv["login"]) ?>

</div>


<!-- =========================
     MOT DE PASSE
========================= -->

<a class="btn" href="changer_mdp_surv.php">

Modifier mon mot de passe

</a>


<!-- =========================
     RETOUR
========================= -->

<a class="btn" href="espace_surv.php">

Retour

</a>


</div>


</body>

</html>

