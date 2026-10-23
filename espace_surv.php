<?php

session_start();

include("connexionDB.php");


$timeout = 300; // 5 minutes


if(isset($_SESSION["last_activity"])){

    if(time() - $_SESSION["last_activity"] > $timeout){

        session_unset();
        session_destroy();

        header("Location: login_surv.php?timeout=1");
        exit();

    }

}


$_SESSION["last_activity"] = time();


// Vérifier que le surveillant est connecté

if(!isset($_SESSION["id_surv"]) || $_SESSION["role"] != "surveillant"){

    header("Location: login_surv.php");
    exit();

}



// Récupérer les informations du surveillant connecté

$req = $bdd->prepare("
    SELECT nom_surv, prenom_surv, login
    FROM surveillant
    WHERE id_surv=?
");


$req->execute([

    $_SESSION["id_surv"]

]);


$surv = $req->fetch();



if(!$surv){

    echo "Surveillant introuvable.";
    exit();

}


?>



<html lang="fr">

<head>

<meta charset="UTF-8">

<title>Espace Surveillant</title>


<style>


*{

    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:Arial,sans-serif;

}



body{

    background:#eef4f7;
    min-height:100vh;

}



/* Barre du haut */

.header{

    background:#355c7d;
    color:white;
    padding:20px;
    display:flex;
    justify-content:space-between;
    align-items:center;

}



.header h2{

    font-size:22px;

}



.logout{

    background:#e57373;
    color:white;
    text-decoration:none;
    padding:10px 15px;
    border-radius:8px;

}


.logout:hover{

    background:#c62828;

}





.container{

    padding:30px;

}





.card{

    background:white;
    width:500px;
    padding:25px;
    border-radius:15px;
    box-shadow:0 5px 15px rgba(0,0,0,0.1);

}



.card h3{

    color:#355c7d;
    margin-bottom:20px;

}



.info{

    margin:12px 0;
    font-size:16px;

}





.menu{

    margin-top:30px;
    display:flex;
    gap:20px;

}



.menu a{

    text-decoration:none;
    background:#7aa6c2;
    color:white;
    padding:15px 20px;
    border-radius:10px;

}



.menu a:hover{

    background:#5c8aaa;

}



</style>


</head>


<body>



<div class="header">


<h2>
Espace Surveillant
</h2>





</div>





<div class="container">


<div class="card">


<h3>
Bienvenue <?= htmlspecialchars($surv["prenom_surv"]." ".$surv["nom_surv"]) ?>
</h3>



<div class="info">

<b>Nom :</b>

<?= htmlspecialchars($surv["nom_surv"]) ?>

</div>




<div class="info">

<b>Prénom :</b>

<?= htmlspecialchars($surv["prenom_surv"]) ?>

</div>




<div class="info">

<b>Login :</b>

<?= htmlspecialchars($surv["login"]) ?>

</div>



</div>





<div class="menu">


<a href="changer_mdp_surv.php">
Changer mon mot de passe
</a>

<a href="profil_surv.php">
Mon profil
</a>

<a href="affectations_surv.php">
Mes affectations
</a>
<a href="documents_partages_surv.php">
    Documents partagés
</a>

<a href="logout_surv.php" class="logout">
Déconnexion
</a>



</div>



</div>


</body>


</html>