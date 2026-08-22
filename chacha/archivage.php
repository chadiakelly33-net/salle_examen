<?php

session_start();


if(!isset($_SESSION["id_admin"]) || $_SESSION["role"] != "admin"){

    header("Location: connexion.php");
    exit();

}


include("connexionDB.php");
include("menu_principal.php");
include("journal.php");


$message = "";



if(isset($_POST["archiver"])){

    try{


        // Appel de la procédure stockée

        $req = $bdd->prepare("CALL archiver_examens()");
        $req->execute();



        $message = "Les examens terminés ont été archivés avec succès.";



        // Journalisation

        journal(

            $bdd,

            $_SESSION["id_admin"],

            "Administrateur",

            "Archivage des examens terminés"

        );



    }catch(PDOException $e){


        $message = "Erreur lors de l'archivage : ".$e->getMessage();


    }


}


?>




<html lang="fr">


<head>

<meta charset="UTF-8">

<title>Archivage des examens</title>


<style>


body{

    font-family:Arial;

    background:#f4f6f9;

    margin:0;

}



.container{

    width:500px;

    margin:80px auto;

    background:white;

    padding:30px;

    border-radius:10px;

    box-shadow:0 5px 15px rgba(0,0,0,0.1);

    text-align:center;

}



h2{

    color:#003366;

    margin-bottom:25px;

}



p{

    margin:20px;

    font-weight:bold;

}



button{

    background:#003366;

    color:white;

    border:none;

    padding:15px 25px;

    border-radius:8px;

    cursor:pointer;

    font-size:16px;

}



button:hover{

    background:#0055aa;

}



.success{

    color:green;

}



.error{

    color:red;

}



a{

    display:inline-block;

    margin-top:20px;

    text-decoration:none;

    color:#003366;

}



</style>


</head>



<body>



<div class="container">


<h2>

Archivage des examens

</h2>



<p>

Cette opération va déplacer les examens terminés vers les archives.

</p>



<form method="POST">


<button type="submit" name="archiver">

Archiver les examens terminés

</button>


</form>



<?php if($message!=""){ ?>


<p class="<?= strpos($message,'Erreur')===false ? 'success':'error' ?>">

<?= htmlspecialchars($message) ?>

</p>


<?php } ?>



<a href="afficher_archives.php">

Voir les archives

</a>



</div>



</body>


</html>