<?php
session_start();

include("connexionDB.php");
include("journal.php");


if(!isset($_SESSION["id_surv"])){
    header("Location: espace_surv.php");
    exit();
}
?>

<html lang="fr">

<head>

<meta charset="UTF-8">
<title>Changer le mot de passe</title>

<style>

<style>
    * {
        box-sizing: border-box;
    }

    body {
        margin: 0;
        font-family: Arial, sans-serif;
        background: #f4f6f9;
        color: #333;
    }

    /* MENU LATÉRAL */
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
    }

    .sidebar h3 {
        color: white;
        text-align: center;
        margin-bottom: 30px;
        font-size: 19px;
    }

    .sidebar a {
        display: block;
        color: white;
        text-decoration: none;
        padding: 13px 12px;
        margin-bottom: 7px;
        border-radius: 6px;
        font-size: 14px;
        transition: 0.3s;
    }

    .sidebar a:hover,
    .sidebar a.active {
        background: #d4a72c;
        color: #003366;
        font-weight: bold;
    }

    .sidebar .logout {
        margin-top: 25px;
        border-top: 1px solid rgba(255,255,255,0.25);
        padding-top: 18px;
    }

    .badge-menu {
        background: #d4a72c;
        color: #003366;
        padding: 3px 7px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: bold;
        float: right;
    }

    /* CONTENU PRINCIPAL */
    .container {
        width: 420px;
        max-width: calc(100% - 270px);
        margin: 70px auto 40px;
        margin-left: calc(230px + (100% - 230px - 420px) / 2);
        background: white;
        padding: 30px;
        border-radius: 12px;
        box-shadow: 0 3px 15px rgba(0,0,0,0.08);
    }

    h2 {
        text-align: center;
        color: #003366;
        margin: 0 0 25px;
        font-size: 23px;
    }

    /* CHAMPS DU FORMULAIRE */
    input {
        display: block;
        width: 100%;
        padding: 12px;
        margin-top: 15px;
        border: 1px solid #ccc;
        border-radius: 6px;
        font-size: 14px;
        outline: none;
        transition: 0.3s;
    }

    input:focus {
        border-color: #003366;
        box-shadow: 0 0 5px rgba(0,51,102,0.15);
    }

    /* BOUTON */
    button {
        width: 100%;
        padding: 13px;
        margin-top: 22px;
        border: none;
        background: #003366;
        color: white;
        font-size: 15px;
        font-weight: bold;
        border-radius: 6px;
        cursor: pointer;
        transition: 0.3s;
    }

    button:hover {
        background: #d4a72c;
        color: #003366;
    }

    /* LIEN RETOUR */
    .retour {
        display: block;
        text-align: center;
        margin-top: 20px;
        text-decoration: none;
        color: #003366;
        font-size: 14px;
        font-weight: bold;
    }

    .retour:hover {
        color: #d4a72c;
    }

    /* MESSAGES */
    .erreur {
        background: #ffdede;
        color: #b00020;
        padding: 12px;
        border-radius: 6px;
        text-align: center;
        margin-bottom: 15px;
        font-size: 14px;
    }

    .success {
        background: #d4edda;
        color: #155724;
        padding: 12px;
        border-radius: 6px;
        text-align: center;
        margin-bottom: 15px;
        font-size: 14px;
    }

    /* RESPONSIVE */
    @media (max-width: 768px) {
        .sidebar {
            position: relative;
            width: 100%;
            height: auto;
            padding: 15px;
        }

        .sidebar h3 {
            margin-bottom: 15px;
        }

        .container {
            width: calc(100% - 30px);
            max-width: 420px;
            margin: 30px auto;
            padding: 25px 20px;
        }
    }
</style>

</style>

</head>

<body>

<div class="container">

<h2>Changer le mot de passe</h2>

<?php

if(isset($_GET["erreur"])){

    if($_GET["erreur"]=="ancien"){

        echo "<div class='erreur'>
        Ancien mot de passe incorrect.
        </div>";

    }

    if($_GET["erreur"]=="confirm"){

        echo "<div class='erreur'>
        Les deux mots de passe sont différents.
        </div>";

    }

}

if(isset($_GET["success"])){

    echo "<div class='success'>
    Mot de passe modifié avec succès.
    </div>";

}

?>

<form action="traitement_mdp_surv.php" method="POST">

<input
type="password"
name="ancien"
placeholder="Ancien mot de passe"
required>

<input
type="password"
name="nouveau"
placeholder="Nouveau mot de passe"
required>

<input
type="password"
name="confirm"
placeholder="Confirmer le nouveau mot de passe"
required>

<button type="submit">

Changer le mot de passe

</button>

</form>

<a class="retour" href="espace_surv.php">

Retour

</a>

</div>

</body>

</html>