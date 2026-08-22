<?php

session_start();

include("connexionDB.php");

$message = "";


if($_SERVER["REQUEST_METHOD"] == "POST"){


    $login = trim($_POST["login"]);
    $password = $_POST["password"];



    // Recherche étudiant

    $req = $bdd->prepare("
        SELECT * FROM etudiant 
        WHERE login=?
    ");

    $req->execute([$login]);

    $user = $req->fetch();



    if($user){



        // Vérifier blocage

        if($user["compte_bloque"] == 1){


            if(strtotime($user["date_blocage"]) + 60 > time()){


                $message="Votre compte est temporairement verrouillé. Réessayez dans 1 minute.";


            }else{


                $bdd->prepare("
                    UPDATE etudiant
                    SET tentatives=0,
                    compte_bloque=0,
                    date_blocage=NULL
                    WHERE id_etu=?
                ")->execute([$user["id_etu"]]);


                $user["tentatives"]=0;
                $user["compte_bloque"]=0;


            }

        }



        if($user["compte_bloque"] == 0){



            // Vérification mot de passe

            if(password_verify($password,$user["motdepasse"])) {



                // remettre les tentatives à zéro

                $bdd->prepare("
                    UPDATE etudiant
                    SET tentatives=0
                    WHERE id_etu=?
                ")->execute([$user["id_etu"]]);




                /*
                    Enregistrer la tentative de connexion
                    Le statut sera changé après validation 2FA
                */


                $log = $bdd->prepare("

                    INSERT INTO connexion_log
                    (
                        user_id,
                        nom_utilisateur,
                        adresse_ip,
                        type_user,
                        statut,
                        date_connexion
                    )

                    VALUES(?,?,?,?,?,NOW())

                ");



                $log->execute([


                    $user["id_etu"],

                    trim($user["nom_etu"]." ".$user["prenom_etu"]),

                    $_SERVER["REMOTE_ADDR"],

                    "etudiant",

                    "verification"


                ]);



                // Garder l'id du log

                $_SESSION["connexion_id"]=$bdd->lastInsertId();





                // Générer code 2FA

                $code = rand(100000,999999);



                $reqCode = $bdd->prepare("

                    INSERT INTO verification_code
                    (
                        user_id,
                        type_user,
                        code,
                        date_creation
                    )

                    VALUES(?,?,?,NOW())

                ");



                $reqCode->execute([

                    $user["id_etu"],

                    "etudiant",

                    $code

                ]);




                // Session temporaire

                $_SESSION["verification_user"]=$user["id_etu"];

                $_SESSION["verification_type"]="etudiant";

                $_SESSION["code_test"]=$code;



                header("Location: verification.php");

                exit();



            }else{



                // Mot de passe incorrect


                $log=$bdd->prepare("

                    INSERT INTO connexion_log
                    (
                        user_id,
                        nom_utilisateur,
                        adresse_ip,
                        type_user,
                        statut,
                        date_connexion
                    )

                    VALUES(?,?,?,?,?,NOW())

                ");



                $log->execute([


                    $user["id_etu"],

                    trim($user["nom_etu"]." ".$user["prenom_etu"]),

                    $_SERVER["REMOTE_ADDR"],

                    "etudiant",

                    "failed"


                ]);




                $tentatives=$user["tentatives"]+1;



                if($tentatives>=3){


                    $bdd->prepare("

                        UPDATE etudiant
                        SET tentatives=?,
                        compte_bloque=1,
                        date_blocage=NOW()
                        WHERE id_etu=?

                    ")->execute([

                        $tentatives,

                        $user["id_etu"]

                    ]);



                    $message="Compte verrouillé après 3 tentatives.";



                }else{


                    $bdd->prepare("

                        UPDATE etudiant
                        SET tentatives=?
                        WHERE id_etu=?

                    ")->execute([

                        $tentatives,

                        $user["id_etu"]

                    ]);



                    $reste=3-$tentatives;

                    $message="Mot de passe incorrect. Il vous reste ".$reste." tentative(s).";


                }


            }


        }




    }else{


        // Login inexistant


        $log=$bdd->prepare("

            INSERT INTO connexion_log
            (
                user_id,
                nom_utilisateur,
                adresse_ip,
                type_user,
                statut,
                date_connexion
            )

            VALUES(?,?,?,?,?,NOW())

        ");



        $log->execute([

            NULL,

            "Utilisateur inconnu",

            $_SERVER["REMOTE_ADDR"],

            "etudiant",

            "failed"

        ]);



        $message="Login introuvable.";


    }


}

?>



<!DOCTYPE html>

<html lang="fr">

<head>

<meta charset="UTF-8">

<title>Connexion Étudiant</title>


<style>


*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:Arial, sans-serif;
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

    text-align:center;

    color:#355c7d;

    margin-bottom:25px;

}



input{

    width:100%;

    padding:13px;

    margin-bottom:15px;

    border:1px solid #cfd8dc;

    border-radius:8px;

}



input:focus{

    outline:none;

    border-color:#7aa6c2;

}



button{

    width:100%;

    padding:12px;

    background:#7aa6c2;

    color:white;

    border:none;

    border-radius:8px;

    cursor:pointer;

    font-size:16px;

}



button:hover{

    background:#5c8aaa;

}



.message{

    margin-top:20px;

    padding:10px;

    text-align:center;

    background:#ffe8e8;

    color:#c0392b;

    border-radius:8px;

}


</style>


</head>



<body>


<div class="container">


<h2>
Connexion Étudiant
</h2>



<form method="POST">


<input 
type="text"
name="login"
placeholder="Login"
required
>



<input
type="password"
name="password"
placeholder="Mot de passe"
required
>



<button type="submit">

Se connecter

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