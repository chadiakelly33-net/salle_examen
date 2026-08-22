<?php

session_start();

include("connexionDB.php");


$message = "";


if($_SERVER["REQUEST_METHOD"] == "POST"){


    $login = trim($_POST["login"]);
    $password = $_POST["password"];



    // Recherche du surveillant

    $req = $bdd->prepare("
        SELECT * FROM surveillant 
        WHERE login=?
    ");

    $req->execute([$login]);

    $user = $req->fetch();



    if($user){



        // Vérifier si le compte est bloqué

        if($user["compte_bloque"] == 1){


            if(strtotime($user["date_blocage"]) + 60 > time()){


                $message = "Votre compte est temporairement verrouillé. Réessayez dans 1 minute.";


            }else{


                // Déblocage automatique

                $bdd->prepare("
                    UPDATE surveillant
                    SET tentatives=0,
                    compte_bloque=0,
                    date_blocage=NULL
                    WHERE id_surv=?
                ")->execute([$user["id_surv"]]);


                $user["tentatives"]=0;
                $user["compte_bloque"]=0;


            }

        }



        // Si le compte est disponible

        if($user["compte_bloque"] == 0){



            // Vérification du mot de passe

            if(password_verify($password,$user["motdepasse"])) {



                // Réinitialiser les tentatives

                $bdd->prepare("
                    UPDATE surveillant
                    SET tentatives=0
                    WHERE id_surv=?
                ")->execute([$user["id_surv"]]);





                // Enregistrer tentative de connexion avant 2FA

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


                    $user["id_surv"],

                    trim($user["nom_surv"]." ".$user["prenom_surv"]),

                    $_SERVER["REMOTE_ADDR"],

                    "surveillant",

                    "verification"

                ]);




                // Garder l'id de connexion

                $_SESSION["connexion_id"] = $bdd->lastInsertId();





                // Générer code 2FA

                $code = rand(100000,999999);





                // Enregistrer le code

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


                    $user["id_surv"],

                    "surveillant",

                    $code

                ]);






                // Session temporaire

                $_SESSION["verification_user"] = $user["id_surv"];

                $_SESSION["verification_type"] = "surveillant";

                $_SESSION["code_test"] = $code;



                // Pour test local

                // echo "Code : ".$code;



                header("Location: verification_surv.php");

                exit();



            }else{



                // Mot de passe incorrect


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


                    $user["id_surv"],

                    trim($user["nom_surv"]." ".$user["prenom_surv"]),

                    $_SERVER["REMOTE_ADDR"],

                    "surveillant",

                    "failed"

                ]);





                $tentatives = $user["tentatives"] + 1;



                if($tentatives >= 3){



                    $bdd->prepare("

                        UPDATE surveillant

                        SET tentatives=?,

                        compte_bloque=1,

                        date_blocage=NOW()

                        WHERE id_surv=?

                    ")->execute([

                        $tentatives,

                        $user["id_surv"]

                    ]);



                    $message="Compte verrouillé après 3 tentatives.";



                }else{



                    $bdd->prepare("

                        UPDATE surveillant

                        SET tentatives=?

                        WHERE id_surv=?

                    ")->execute([

                        $tentatives,

                        $user["id_surv"]

                    ]);




                    $reste = 3 - $tentatives;


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

            "surveillant",

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

<title>Connexion Surveillant</title>


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

text-align:center;
color:#355c7d;
margin-bottom:25px;

}




input{

width:100%;
padding:13px;
margin-bottom:15px;
border:1px solid #ccc;
border-radius:8px;

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
Connexion Surveillant
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