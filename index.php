<?php
session_start();
?>

<html lang="fr">
<head>

<meta name="google-site-verification" content="ifoXriRfwY6W6gSgZlDZUPU4PLiW3nl3z_5PN9qIGME" />

<meta charset="UTF-8">
<title>Choix du rôle</title>

<style>

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}

body{
    font-family:Arial, sans-serif;
    background:#f4f6f9;
}

.container{
    width:700px;
    margin:100px auto;
    text-align:center;
}

h1{
    color:#003366;
    margin-bottom:15px;
}

p{
    color:#555;
    margin-bottom:40px;
    font-size:18px;
}

.roles{
    display:flex;
    justify-content:center;
    gap:20px;
    flex-wrap:wrap;
}

.role{
    width:200px;
    background:white;
    padding:25px;
    border-radius:10px;
    box-shadow:0 2px 10px rgba(0,0,0,0.15);
}

.role h2{
    color:#003366;
    margin-bottom:15px;
}

.role a{
    display:block;
    text-decoration:none;
    background:#003366;
    color:white;
    padding:12px;
    border-radius:5px;
    font-weight:bold;
}

.role a:hover{
    background:#0055aa;
}

</style>

</head>

<body>

<div class="container">

    <h1>Système de Gestion des Salles d'Examen</h1>

    <p>Choisissez votre espace de connexion</p>

    <div class="roles">

        <div class="role">
            <h2>Étudiant</h2>
            <a href="login_etu.php">Se connecter</a>
        </div>

        <div class="role">
            <h2>Surveillant</h2>
            <a href="login_surv.php">Se connecter</a>
        </div>

        <div class="role">
            <h2> Administrateur</h2>
            <a href="chacha/connexion.php">Se connecter</a>
        </div>

    </div>

</div>

</body>
</html>