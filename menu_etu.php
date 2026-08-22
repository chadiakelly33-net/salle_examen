<?php

if(session_status() == PHP_SESSION_NONE){
    session_start();
}


if(!isset($_SESSION["id_etu"]) ||
   !isset($_SESSION["role"]) ||
   $_SESSION["role"] != "etudiant"){

    header("Location: login_etu.php");
    exit();

}


?>


<html lang="fr">

<head>

<meta charset="UTF-8">

<title>Menu étudiant</title>


<style>

body{
    margin:0;
    font-family:Arial;
}


.menu{

    background:#003366;
    padding:15px;
    text-align:center;

}


.menu a{

    color:white;
    text-decoration:none;
    padding:12px 20px;
    margin:5px;
    display:inline-block;
    border-radius:5px;

}


.menu a:hover{

    background:#ffd700;
    color:#003366;

}


</style>


</head>


<body>


<div class="menu">


<a href="espace_etu.php">
Accueil
</a>


<a href="mes_camarades.php">
Mes camarades
</a>


<a href="mes_affectations.php">
Mes affectations
</a>


<a href="changer_motdepasse.php">
Changer mot de passe
</a>

<a href="profil_etu.php">
Mon profil
</a>


<a href="logout_etu.php">
Déconnexion
</a>


</div>


</body>

</html>