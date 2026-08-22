```php
<?php

session_start();

if(!isset($_SESSION["id_admin"]) || $_SESSION["role"] != "admin"){
    header("Location: connexion.php");
    exit();
}

include("connexionDB.php");


// Vérifier l'identifiant du surveillant
if(!isset($_GET["id"]) || empty($_GET["id"])){
    die("Surveillant introuvable.");
}

$id_surv = $_GET["id"];


// Récupérer le surveillant
$req = $bdd->prepare("
    SELECT 
        id_surv,
        nom_surv,
        prenom_surv,
        login,
        photo
    FROM surveillant
    WHERE id_surv = ?
");

$req->execute([$id_surv]);

$surv = $req->fetch(PDO::FETCH_ASSOC);


if(!$surv){
    die("Surveillant introuvable.");
}

?>

<!DOCTYPE html>

<html lang="fr">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Modifier un surveillant</title>


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
    font-family:Arial, Helvetica, sans-serif;
    background:#f4f6f9;
    min-height:100vh;
    overflow-x:hidden;
}


/* =========================
   MENU GAUCHE
========================= */

aside{
    position:fixed;
    top:0;
    left:0;

    width:220px;
    height:100vh;

    overflow-y:auto;
    overflow-x:hidden;
}


/* =========================
   CONTENU
========================= */

.content{

    margin-left:220px;

    width:calc(100% - 220px);

    min-height:100vh;

    padding:40px 20px;

    display:flex;

    justify-content:center;

    align-items:flex-start;

}


/* =========================
   FORMULAIRE
========================= */

.form-box{

    width:100%;

    max-width:500px;

    background:white;

    padding:30px;

    border-radius:10px;

    box-shadow:0 3px 10px rgba(0,0,0,.15);

}


/* =========================
   TITRE
========================= */

h2{

    text-align:center;

    color:#003366;

    margin-bottom:25px;

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

input[type="text"],
input[type="file"]{

    width:100%;

    max-width:100%;

    padding:10px;

    margin-top:5px;

    border:1px solid #ccc;

    border-radius:6px;

    font-size:15px;

}


/* =========================
   FOCUS
========================= */

input[type="text"]:focus,
input[type="file"]:focus{

    outline:none;

    border:1px solid #003366;

}


/* =========================
   PHOTO ACTUELLE
========================= */

.photo-actuelle{

    text-align:center;

    margin:20px 0;

}


/* Photo existante */

.photo-actuelle img{

    width:120px;

    height:120px;

    border-radius:50%;

    object-fit:cover;

    border:3px solid #003366;

}


/* Aucune photo */

.no-photo{

    width:120px;

    height:120px;

    margin:auto;

    border-radius:50%;

    background:#ddd;

    display:flex;

    align-items:center;

    justify-content:center;

    color:#666;

    font-size:13px;

}


/* =========================
   BOUTON
========================= */

button{

    width:100%;

    margin-top:25px;

    padding:12px;

    border:none;

    border-radius:6px;

    background:#003366;

    color:white;

    font-size:16px;

    cursor:pointer;

}


button:hover{

    background:#0055aa;

}


/* =========================
   RETOUR
========================= */

.retour{

    display:block;

    text-align:center;

    margin-top:15px;

    text-decoration:none;

    color:#003366;

}


.retour:hover{

    text-decoration:underline;

}


/* =========================
   RESPONSIVE
========================= */

@media(max-width:700px){

    aside{

        width:180px;

    }


    .content{

        margin-left:180px;

        width:calc(100% - 180px);

        padding:25px 10px;

    }


    .form-box{

        max-width:450px;

        padding:20px;

    }

}

</style>

</head>


<body>


<!-- =========================
     MENU
========================= -->

<aside>

<?php include("menu_principal.php"); ?>

</aside>



<!-- =========================
     CONTENU
========================= -->

<div class="content">


<div class="form-box">


<h2>
Modifier le surveillant
</h2>



<form
    action="update_surv.php"
    method="POST"
    enctype="multipart/form-data"
>


<!-- ID -->

<input
    type="hidden"
    name="id_surv"
    value="<?= htmlspecialchars($surv["id_surv"]) ?>"
>



<!-- NOM -->

<label>
Nom
</label>

<input
    type="text"
    name="nom"
    value="<?= htmlspecialchars($surv["nom_surv"]) ?>"
    required
>



<!-- PRENOM -->

<label>
Prénom
</label>

<input
    type="text"
    name="prenom"
    value="<?= htmlspecialchars($surv["prenom_surv"]) ?>"
    required
>



<!-- LOGIN -->

<label>
Login
</label>

<input
    type="text"
    name="login"
    value="<?= htmlspecialchars($surv["login"]) ?>"
    required
>



<!-- PHOTO ACTUELLE -->

<label>
Photo actuelle
</label>


<div class="photo-actuelle">


<?php

if(
    !empty($surv["photo"]) &&
    file_exists(__DIR__ . "/uploads/" . $surv["photo"])
){

?>

<img
    src="uploads/<?= htmlspecialchars($surv["photo"]) ?>"
    alt="Photo du surveillant"
>


<?php

}else{

?>

<div class="no-photo">
    Aucune photo
</div>


<?php

}

?>


</div>



<!-- NOUVELLE PHOTO -->

<label>
Nouvelle photo
</label>


<input
    type="file"
    name="photo"
    accept="image/jpeg,image/png,image/webp"
>



<!-- BOUTON -->

<button
    type="submit"
    name="modifier"
>

Modifier le surveillant

</button>



<!-- RETOUR -->

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
