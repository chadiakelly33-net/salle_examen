
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

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Ajouter Surveillant</title>


<style>

/* =========================
   RESET
========================= */

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}


/* =========================
   PAGE
========================= */

body{

    font-family:Arial, sans-serif;

    background:#f4f6f9;

    min-height:100vh;

}


/* =========================
   MENU
========================= */

aside{

    position:fixed;

    left:0;
    top:0;

    width:220px;

    height:100vh;

    background:#111827;

}


/* =========================
   CONTENU
========================= */

.content{

    margin-left:220px;

    min-height:100vh;

    display:flex;

    flex-direction:column;

    align-items:center;

    padding:40px 30px;

}


/* =========================
   TITRE
========================= */

h2{

    color:#003366;

    text-align:center;

    margin-bottom:25px;

}


/* =========================
   FORMULAIRE
========================= */

.form-box{

    width:500px;

    max-width:100%;

    background:white;

    padding:30px;

    border-radius:10px;

    box-shadow:0 2px 10px rgba(0,0,0,.15);

}


/* =========================
   LABELS
========================= */

label{

    display:block;

    margin-top:15px;

    font-weight:bold;

    color:#333;

}


/* =========================
   CHAMPS
========================= */

input{

    width:100%;

    padding:10px;

    margin-top:5px;

    border:1px solid #ccc;

    border-radius:5px;

    box-sizing:border-box;

}


/* =========================
   CHAMP PHOTO
========================= */

input[type="file"]{

    background:#fff;

    cursor:pointer;

}


/* =========================
   INFORMATION
========================= */

.info{

    background:#e8f4ff;

    border-left:5px solid #007bff;

    padding:12px;

    margin-top:20px;

    color:#003366;

    line-height:1.5;

    border-radius:4px;

}


/* =========================
   BOUTON
========================= */

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


/* =========================
   RESPONSIVE
========================= */

@media(max-width:700px){

    aside{

        position:relative;

        width:100%;

        height:auto;

    }


    .content{

        margin-left:0;

        padding:25px 15px;

    }


    .form-box{

        width:100%;

    }

}

</style>

</head>


<body>


<!-- =========================
     MENU
========================= -->

<aside>

<?php

include("Menu_principal.php");

?>

</aside>


<!-- =========================
     CONTENU
========================= -->

<div class="content">


<h2>

Ajouter un surveillant

</h2>


<div class="form-box">


<form
    action="traitement_surv.php"
    method="POST"
    enctype="multipart/form-data"
>


<label>

Nom

</label>

<input
    type="text"
    name="nom"
    required
>


<label>

Prénom

</label>

<input
    type="text"
    name="prenom"
    required
>


<label>

Login

</label>

<input
    type="text"
    name="login"
    required
>

<label>
    Adresse e-mail
</label>

<input
    type="email"
    name="email"
    placeholder="exemple@gmail.com"
    required
>




<label>

Photo de profil

</label>

<input
    type="file"
    name="photo"
    accept="image/jpeg,image/png,image/webp"
>


<div class="info">

<b>Mot de passe initial :</b> 1234

<br><br>

Le surveillant pourra changer son mot de passe depuis son espace personnel s'il le souhaite.

</div>


<button
    type="submit"
    name="ajouter"
>

Ajouter surveillant

</button>

<a
    class="retour"
    href="afficher_sur.php"
>

Retour à la liste des surveillants

</a>


</form>


</div>


</div>


</body>

</html>
```
