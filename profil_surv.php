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

<style>
/* ==============================
   STYLE GENERAL
============================== */

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: Arial, Helvetica, sans-serif;
    background: #f4f6f9;
    color: #333;
    min-height: 100vh;
}

/* ==============================
   BARRE LATERALE
============================== */

.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: 230px;
    height: 100vh;
    background: #003366;
    padding: 25px 15px;
    overflow-y: auto;
    z-index: 1000;
    box-shadow: 3px 0 12px rgba(0,0,0,0.12);
}

.sidebar h3 {
    color: white;
    text-align: center;
    font-size: 20px;
    margin-bottom: 35px;
    padding-bottom: 20px;
    border-bottom: 1px solid rgba(255,255,255,0.2);
}

.sidebar a {
    display: flex;
    align-items: center;
    justify-content: space-between;
    text-decoration: none;
    color: #e8eef7;
    padding: 13px 14px;
    margin-bottom: 8px;
    border-radius: 7px;
    font-size: 14px;
    transition: 0.3s ease;
}

.sidebar a:hover,
.sidebar a.active {
    background: #d4a72c;
    color: #003366;
    font-weight: bold;
}

.sidebar a.logout {
    margin-top: 25px;
    border-top: 1px solid rgba(255,255,255,0.2);
    padding-top: 18px;
}

.badge-menu {
    background: #d4a72c;
    color: #003366;
    padding: 3px 8px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: bold;
}

/* ==============================
   CONTENEUR PRINCIPAL
============================== */

.container {
    width: 500px;
    max-width: 90%;
    margin: 50px auto;
    transform: translateX(115px);
    background: white;
    padding: 35px;
    border-radius: 12px;
    border: 1px solid #e5eaf0;
    box-shadow: 0 4px 18px rgba(0,0,0,0.07);
}

/* ==============================
   TITRE
============================== */

h2 {
    text-align: center;
    color: #003366;
    font-size: 25px;
    margin-bottom: 30px;
    padding-bottom: 18px;
    border-bottom: 2px solid #d4a72c;
}

/* ==============================
   PHOTO DE PROFIL
============================== */

.photo-container {
    text-align: center;
    margin-bottom: 30px;
}

.photo-profil {
    width: 145px;
    height: 145px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid #003366;
    padding: 3px;
    background: white;
    box-shadow: 0 4px 12px rgba(0,0,0,0.12);
}

.photo-vide {
    width: 145px;
    height: 145px;
    border-radius: 50%;
    background: #f0f3f7;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: auto;
    color: #667085;
    font-size: 14px;
    border: 4px solid #d4a72c;
}

/* ==============================
   INFORMATIONS
============================== */

.info {
    font-size: 15px;
    margin: 15px 0;
    padding: 16px;
    background: #f8fafc;
    border: 1px solid #e8edf3;
    border-radius: 7px;
    line-height: 1.5;
    overflow-wrap: anywhere;
}

.label {
    display: inline-block;
    min-width: 85px;
    font-weight: bold;
    color: #003366;
}

/* ==============================
   BOUTONS
============================== */

.btn {
    display: block;
    text-align: center;
    background: #003366;
    color: white;
    padding: 13px;
    margin-top: 18px;
    text-decoration: none;
    border-radius: 7px;
    font-size: 14px;
    font-weight: bold;
    transition: 0.3s ease;
}

.btn:hover {
    background: #d4a72c;
    color: #003366;
}

/* ==============================
   RESPONSIVE
============================== */

@media (max-width: 650px) {

    .sidebar {
        position: relative;
        width: 100%;
        height: auto;
        padding: 15px;
    }

    .sidebar h3 {
        margin-bottom: 15px;
        padding-bottom: 12px;
    }

    .sidebar a {
        margin-bottom: 5px;
    }

    .container {
        width: 90%;
        max-width: 500px;
        margin: 30px auto;
        padding: 25px 20px;
        transform: none;
    }

    h2 {
        font-size: 22px;
    }

    .info {
        font-size: 14px;
    }
}
</style>


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

