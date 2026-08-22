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

body{
    margin:0;
    font-family:Arial;
    background:#f4f6f9;
}

.container{

    width:420px;
    margin:70px auto;
    background:white;
    padding:25px;
    border-radius:10px;
    box-shadow:0 2px 10px rgba(0,0,0,.15);

}

h2{

    text-align:center;
    color:#003366;
    margin-bottom:20px;

}

input{

    width:100%;
    padding:10px;
    margin-top:12px;
    border:1px solid #ccc;
    border-radius:5px;
    box-sizing:border-box;

}

button{

    width:100%;
    padding:12px;
    margin-top:20px;
    border:none;
    background:#003366;
    color:white;
    font-size:16px;
    border-radius:5px;
    cursor:pointer;

}

button:hover{

    background:#0055aa;

}

.retour{

    display:block;
    text-align:center;
    margin-top:15px;
    text-decoration:none;
    color:#003366;

}

.erreur{

    background:#ffdede;
    color:red;
    padding:10px;
    border-radius:5px;
    text-align:center;
    margin-bottom:15px;

}

.success{

    background:#d4edda;
    color:green;
    padding:10px;
    border-radius:5px;
    text-align:center;
    margin-bottom:15px;

}

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