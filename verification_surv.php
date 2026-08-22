<?php

session_start();

include("connexionDB.php");


$message = "";

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


// Vérifier que le surveillant vient bien de login_surv.php

if(!isset($_SESSION["verification_user"]) || 
   !isset($_SESSION["verification_type"])){

    header("Location: login_surv.php");
    exit();

}



// Affichage du code en test local uniquement

if(isset($_SESSION["code_test"])){

    echo "
    <p style='color:green;text-align:center;'>
    Code de test : ".$_SESSION["code_test"]."
    </p>";

}




if($_SERVER["REQUEST_METHOD"] == "POST"){


    $code = $_POST["code"];




    // Chercher le code correspondant

    $req = $bdd->prepare("

        SELECT * FROM verification_code

        WHERE user_id=?

        AND type_user=?

        AND code=?

        ORDER BY date_creation DESC

        LIMIT 1

    ");




    $req->execute([


        $_SESSION["verification_user"],

        $_SESSION["verification_type"],

        $code


    ]);



    $verification = $req->fetch();





    if($verification){



        // Mettre à jour l'historique connexion

        if(isset($_SESSION["connexion_id"])){


            $updateLog = $bdd->prepare("

                UPDATE connexion_log

                SET statut='success'

                WHERE id=?

            ");


            $updateLog->execute([

                $_SESSION["connexion_id"]

            ]);


        }





        // Création session définitive surveillant


        $_SESSION["id_surv"] = $_SESSION["verification_user"];

        $_SESSION["role"] = "surveillant";




        // Nettoyer les sessions temporaires


        unset($_SESSION["verification_user"]);

        unset($_SESSION["verification_type"]);

        unset($_SESSION["code_test"]);





        header("Location: espace_surv.php");

        exit();




    }else{


        $message="Code incorrect.";

    }


}


?>



<!DOCTYPE html>

<html lang="fr">

<head>

<meta charset="UTF-8">

<title>Vérification 2FA Surveillant</title>



<style>


*{

margin:0;
padding:0;
box-sizing:border-box;
font-family:Arial,sans-serif;

}



body{

height:100vh;
background:#eef4f7;
display:flex;
justify-content:center;
align-items:center;

}




.container{


width:380px;
background:white;
padding:35px;
border-radius:15px;
box-shadow:0 8px 20px rgba(0,0,0,0.1);


}





h2{

    color:#355c7d;

    margin-bottom:25px;

    font-size:24px;

}




input{


width:100%;
padding:13px;
border:1px solid #ccc;
border-radius:8px;
margin-bottom:15px;


}




button{


width:100%;
padding:12px;
background:#7aa6c2;
color:white;
border:none;
border-radius:8px;
font-size:16px;
cursor:pointer;


}




button:hover{


background:#5c8aaa;


}




.message{


margin-top:20px;
padding:10px;
background:#ffe8e8;
color:#c0392b;
border-radius:8px;
text-align:center;


}



</style>



</head>



<body>



<div class="container">


<h2>
Authentification à deux facteurs
</h2>



<form method="POST">


<input 
type="text"
name="code"
placeholder="Entrer le code reçu"
required
>



<button type="submit">

Valider

</button>



</form>



<?php if(!empty($message)): ?>

<div class="message">

<?= htmlspecialchars($message) ?>

</div>

<?php endif; ?>



</div>



</body>


</html>