<?php
session_start();
if(!isset($_SESSION["id_etu"])){
    header("Location: login_etu.php");
    exit();
}

include("connexionDB.php");

include("menu_etu.php");
include("journal.php");


if(!isset($_SESSION["id_etu"])){
    header("Location: login_etu.php");
    exit();
}
journal(
    $bdd,
    $_SESSION["id_etu"],
    $_SESSION["nom_etu"]." ".$_SESSION["prenom_etu"],
    "etudiant",
    "Changement de mot de passe"
);
?>

<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Changer mot de passe</title>

<style>
body{
    font-family:Arial;
    background:#f4f6f9;
}

.box{
    width:400px;
    margin:80px auto;
    background:white;
    padding:25px;
    border-radius:10px;
    box-shadow:0 0 10px rgba(0,0,0,.1);
}

input{
    width:100%;
    padding:12px;
    margin-top:10px;
    border:1px solid #ccc;
    border-radius:5px;
}

button{
    width:100%;
    margin-top:20px;
    padding:12px;
    background:#003366;
    color:white;
    border:none;
    border-radius:5px;
    cursor:pointer;
}

button:hover{
    background:#0055aa;
}
</style>
</head>

<body>

<div class="box">

<a href="espace_etu.php"> Retour</a>

<h2>Changer mot de passe</h2>

<form method="POST" action="traitement_mdp.php">

<input type="password" name="ancien" placeholder="Ancien mot de passe" required>

<input type="password" name="nouveau" placeholder="Nouveau mot de passe" required>

<input type="password" name="confirm" placeholder="Confirmer mot de passe" required>

<button type="submit" name="changer">Modifier</button>

</form>

</div>

</body>
</html>