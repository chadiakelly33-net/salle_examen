<?php
session_start();

$message = "";

if($_SERVER["REQUEST_METHOD"] == "POST"){

    $email = $_POST['email'];
    $password = $_POST['password'];

    $email_admin = "admin@gmail.com";
    $password_admin = "12345";

    if ($email == $email_admin && $password == $password_admin) {

        $_SESSION['id_admin'] = $email;
        $_SESSION['role'] = "admin";

        header("Location: dashboard.php");
        exit();

    } else {

        $message = "Email ou mot de passe incorrect";

    }
}

?>

<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Connexion Admin</title>
</head>

<body>
<style>
<style>

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:Arial, sans-serif;
}


body{

    height:100vh;
    background:#f4f6f9;

    display:flex;
    flex-direction:column;

    justify-content:center;
    align-items:center;

}


/* Titre */

h2{

    color:#003366;
    margin-bottom:25px;
    text-align:center;

}


/* Formulaire */

form{

    background:white;

    width:350px;

    padding:30px;

    border-radius:10px;

    box-shadow:0 5px 15px rgba(0,0,0,0.1);

}



/* Labels */

label{

    display:block;

    margin-bottom:8px;

    color:#333;

    font-weight:bold;

}



/* Champs */

input{

    width:100%;

    padding:12px;

    margin-bottom:20px;

    border:1px solid #ccc;

    border-radius:6px;

    font-size:15px;

}



input:focus{

    outline:none;

    border-color:#003366;

}



/* Bouton */

button{

    width:100%;

    padding:12px;

    background:#003366;

    color:white;

    border:none;

    border-radius:6px;

    font-size:16px;

    cursor:pointer;

}



button:hover{

    background:#0055aa;

}



/* Message erreur */

p{

    margin-top:20px;

    color:red;

    font-weight:bold;

    text-align:center;

}


</style>

</style>

<h2>Veuillez vous connecter</h2>

<form method="POST">

    <label>Email :</label>
    <input type="email" name="email" required>
    <br><br>

    <label>Mot de passe :</label>
    <input type="password" name="password" required>
    <br><br>

    <button type="submit">
        Se connecter
    </button>

</form>

<p style="color:red;">
    <?= $message ?>
</p>

</body>
</html>