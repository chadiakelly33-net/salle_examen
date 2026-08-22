<?php
session_start();

if(!isset($_SESSION["id_admin"]) || $_SESSION["role"] != "admin"){
    header("Location: connexion.php");
    exit();
}

include("connexionDB.php");

$bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
?>

<!DOCTYPE html>
<html lang="fr">

<head>

<meta charset="UTF-8">

<title>Liste des surveillants</title>

<style>

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}

body{
    font-family:Arial, sans-serif;
    background:#f4f6f9;
}

.container{
    width:calc(100% - 250px);
    max-width:1200px;
    margin:30px auto;
    margin-left:240px;
    padding:0 20px;
}

.titre{
    text-align:center;
    color:#003366;
    margin-bottom:20px;
}

.btn-afficher{
    display:inline-block;
    text-decoration:none;
    background:#003366;
    color:white;
    padding:10px 20px;
    border-radius:5px;
    margin-bottom:20px;
}

.btn-afficher:hover{
    background:#0055aa;
}

table{
    width:100%;
    border-collapse:collapse;
    background:white;
    box-shadow:0 2px 10px rgba(0,0,0,0.1);
}

th{
    background:#003366;
    color:white;
    padding:12px;
}

td{
    padding:12px;
    text-align:center;
    border-bottom:1px solid #ddd;
}

tr:hover{
    background:#f5f5f5;
}

/* PHOTO */

.photo-profil{
    width:70px;
    height:70px;
    border-radius:50%;
    object-fit:cover;
    border:2px solid #003366;
}

.photo-vide{
    width:70px;
    height:70px;
    border-radius:50%;
    background:#ddd;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    color:#666;
    font-size:12px;
}

.btn-modifier{
    text-decoration:none;
    background:#28a745;
    color:white;
    padding:8px 15px;
    border-radius:5px;
}

.btn-modifier:hover{
    background:#218838;
}

.btn-supprimer{
    text-decoration:none;
    background:#dc3545;
    color:white;
    padding:8px 15px;
    border-radius:5px;
}

.btn-supprimer:hover{
    background:#c82333;
}

</style>

</head>


<body>


<!-- MENU ADMIN -->

<aside id="menu" class="menu">

<?php
include("Menu_principal.php");
?>

</aside>


<div class="container">


<h1 class="titre">
Liste des surveillants
</h1>


<a href="ajouter_sur.php" class="btn-afficher">
+ Ajouter un surveillant
</a>


<table>

<tr>

<th>Photo</th>

<th>Nom</th>

<th>Prénom</th>

<th>Login</th>

<th>Actions</th>

</tr>


<?php

$sql = "
SELECT
    id_surv,
    nom_surv,
    prenom_surv,
    login,
    photo
FROM surveillant
ORDER BY nom_surv ASC, prenom_surv ASC
";

$stmt = $bdd->query($sql);


while($row = $stmt->fetch(PDO::FETCH_ASSOC)){

?>


<tr>


<!-- PHOTO -->

<td>

<?php

if(
    !empty($row["photo"]) &&
    file_exists("uploads/".$row["photo"])
){

?>

<img
    src="uploads/<?= htmlspecialchars($row["photo"]) ?>"
    alt="Photo de profil"
    class="photo-profil"
>

<?php

}else{

?>

<div class="photo-vide">
    Pas de photo
</div>

<?php

}

?>

</td>


<!-- NOM -->

<td>

<?= htmlspecialchars($row["nom_surv"]) ?>

</td>


<!-- PRENOM -->

<td>

<?= htmlspecialchars($row["prenom_surv"]) ?>

</td>


<!-- LOGIN -->

<td>

<?= htmlspecialchars($row["login"]) ?>

</td>


<!-- ACTIONS -->

<td>

<a
    href="modifie_sur.php?id=<?= $row["id_surv"] ?>"
    class="btn-modifier"
>
    Modifier
</a>


<a
    href="supprimer_sur.php?id=<?= $row["id_surv"] ?>"
    class="btn-supprimer"
    onclick="return confirm('Voulez-vous vraiment supprimer ce surveillant ?');"
>
    Supprimer
</a>

</td>


</tr>


<?php

}

?>


</table>


</div>


</body>

</html>
```
