<?php

session_start();

if(!isset($_SESSION["id_admin"]) || $_SESSION["role"] != "admin"){
    header("Location: connexion.php");
    exit();
}

include("connexionDB.php");

?>


<html lang="fr">

<head>

<meta charset="UTF-8">

<title>Ajouter Étudiant</title>

<style>

body{
    margin:0;
    font-family:Arial;
    background:#f4f6f9;
    display:flex;
}

aside{
    width:220px;
}

.content{
    flex:1;
    padding:30px;
}

h2{
    color:#003366;
}

.form-box{

    width:500px;
    background:white;
    padding:25px;
    border-radius:10px;
    box-shadow:0 2px 10px rgba(0,0,0,.15);

}

label{

    display:block;
    margin-top:12px;
    font-weight:bold;

}

input,select{

    width:100%;
    padding:10px;
    margin-top:5px;
    border:1px solid #ccc;
    border-radius:5px;
    box-sizing:border-box;

}

.info{

    background:#e8f4ff;
    border-left:5px solid #007bff;
    padding:10px;
    margin-top:15px;
    color:#003366;

}

button{

    width:100%;
    margin-top:20px;
    padding:12px;
    border:none;
    border-radius:5px;
    background:#003366;
    color:white;
    font-size:16px;
    cursor:pointer;

}

button:hover{

    background:#0055aa;

}

</style>

</head>

<body>

<aside>

<?php include("Menu_principal.php"); ?>

</aside>

<div class="content">

<h2>Ajouter un étudiant</h2>

<div class="form-box">

<form action="traitement_etu.php" method="POST" enctype="multipart/form-data">

<label>Nom</label>

<input
type="text"
name="nom"
required>

<label>Prénom</label>

<input
type="text"
name="prenom"
required>

<label>Photo de l'étudiant</label>

<input
type="file"
name="photo"
accept="image/*">

<label>Faculté</label>

<select
name="id_fac"
required>

<option value="">Choisir...</option>

<?php

$fac=$bdd->query("SELECT * FROM faculte");

while($f=$fac->fetch()){

?>

<option value="<?= $f["id_fac"] ?>">

<?= $f["nom_fac"] ?>

</option>

<?php } ?>

</select>

<label>Niveau d'étude</label>

<select
name="id_niveau"
required>

<option value="">Choisir...</option>

<?php

$niv=$bdd->query("SELECT * FROM niveau_etude");

while($n=$niv->fetch()){

?>

<option value="<?= $n["id_niveau"] ?>">

<?= $n["nom_niveau"] ?>

</option>

<?php } ?>

</select>

<label>Login</label>

<input
type="text"
name="login"
required>

<div class="info">

<b>Mot de passe par défaut :</b>

123

<br><br>

L'étudiant devra modifier son mot de passe lors de sa première connexion.

</div>

<button
type="submit"
name="ajouter">

Ajouter l'étudiant

</button>

</form>

</div>

</div>

</body>

</html>