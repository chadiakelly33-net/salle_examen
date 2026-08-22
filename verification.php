<?php
session_start();
include("connexionDB.php");


$message = "";


/* Vérifier que l'étudiant vient bien de login_etu.php */
if(!isset($_SESSION["verification_user"]) || 
   !isset($_SESSION["verification_type"])){

    header("Location: login_etu.php");
    exit();
}


// Affichage du code seulement pour test local
if(isset($_SESSION["code_test"])){
    echo "<p style='color:green;'>
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

// Récupérer les informations de l'étudiant
$reqUser = $bdd->prepare("
    SELECT nom_etu, prenom_etu
    FROM etudiant
    WHERE id_etu = ?
");

$reqUser->execute([$_SESSION["verification_user"]]);

$user = $reqUser->fetch(PDO::FETCH_ASSOC);

// Création de la session définitive étudiant
$_SESSION["id_etu"] = $_SESSION["verification_user"];
$_SESSION["role"] = "etudiant";
$_SESSION["nom_etu"] = $user["nom_etu"];
$_SESSION["prenom_etu"] = $user["prenom_etu"];
		


        // Supprimer les informations temporaires
        unset($_SESSION["verification_user"]);
        unset($_SESSION["verification_type"]);
        unset($_SESSION["code_test"]);


        header("Location: espace_etu.php");
        exit();


    }else{

        $message = "Code incorrect.";

    }

}

?>


<html lang="fr">

<head>
<style>
<style>

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:Arial, Helvetica, sans-serif;
}


body{

    height:100vh;
    background:#eef4f7;

    display:flex;
    flex-direction:column;

    justify-content:center;
    align-items:center;

}


/* Titre */

h2{

    color:#355c7d;

    margin-bottom:25px;

    font-size:24px;

}



/* Formulaire */

form{

    background:white;

    width:380px;

    padding:35px;

    border-radius:15px;

    box-shadow:0 8px 20px rgba(0,0,0,0.1);

    text-align:center;

}



/* Champ code */

input{

    width:100%;

    padding:13px;

    border:1px solid #cfd8dc;

    border-radius:8px;

    font-size:16px;

    text-align:center;

    letter-spacing:3px;

    outline:none;

}



input:focus{

    border-color:#7aa6c2;

    box-shadow:0 0 5px rgba(122,166,194,0.5);

}



/* Bouton */

button{

    width:100%;

    padding:12px;

    margin-top:20px;

    background:#7aa6c2;

    color:white;

    border:none;

    border-radius:8px;

    font-size:16px;

    cursor:pointer;

    transition:0.3s;

}



button:hover{

    background:#5c8aaa;

}



/* Message d'erreur */

p{

    margin-top:20px;

    background:#ffe8e8;

    color:#c0392b !important;

    padding:10px 20px;

    border-radius:8px;

    font-size:14px;

}


</style>
</style>

<meta charset="UTF-8">

<title>Vérification 2FA</title>

</head>


<body>


<h2>Authentification à deux facteurs</h2>


<form method="POST">

<input type="text" 
       name="code" 
       placeholder="Entrer le code reçu"
       required>


<br><br>


<button type="submit">
Valider
</button>


</form>


<p style="color:red;">
<?= htmlspecialchars($message) ?>
</p>


</body>

</html>