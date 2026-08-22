<?php
session_start();
if(!isset($_SESSION["id_admin"]) || $_SESSION["role"] != "admin"){
    header("Location: connexion.php");
    exit();
}

include("connexionDB.php");
include("journal.php");


$id = $_GET["id"];

$req = $bdd->prepare("SELECT * FROM etudiant WHERE id_etu=?");
$req->execute([$id]);
$data = $req->fetch();
?>
<head>
<style>

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:Arial, Helvetica, sans-serif;
}

body{
    background:#f8f9fa;
    display:flex;
    flex-direction:column;
    justify-content:center;
    align-items:center;
    min-height:100vh;
}

form{
    width:450px;
    background:#ffffff;
    padding:30px;
    border-radius:10px;
    box-shadow:0 3px 10px rgba(0,0,0,.12);
}

h2{
    color:#4A90E2;
    margin-bottom:20px;
    text-align:center;
}

input,
select{
    width:100%;
    padding:10px;
    margin-top:5px;
    margin-bottom:15px;
    border:1px solid #d6d6d6;
    border-radius:6px;
    font-size:15px;
    background:#fff;
}

input:focus,
select:focus{
    outline:none;
    border:1px solid #4A90E2;
}

button{
    width:100%;
    padding:12px;
    background:#4A90E2;
    color:white;
    border:none;
    border-radius:6px;
    font-size:16px;
    cursor:pointer;
    transition:.3s;
}

button:hover{
    background:#357ABD;
}

br{
    display:none;
}

</style>

</head>

<h2>Modifier un étudiant</h2>


<form action="update_etu.php" method="POST" enctype="multipart/form-data">

<input type="hidden" name="id" value="<?= $data['id_etu'] ?>">


<label>Nom :</label>
<input 
type="text" 
name="nom" 
value="<?= htmlspecialchars($data['nom_etu']) ?>">


<label>Prénom :</label>
<input 
type="text" 
name="prenom" 
value="<?= htmlspecialchars($data['prenom_etu']) ?>">



<label>Photo actuelle :</label>

<?php if(!empty($data['photo'])){ ?>

<img
src="uploads/<?= htmlspecialchars($data['photo']) ?>"
width="100"
height="100"
style="border-radius:50%;object-fit:cover;"
>

<?php }else{ ?>

<p>Aucune photo</p>

<?php } ?>


<label>Nouvelle photo :</label>

<input
type="file"
name="photo"
accept="image/*"
>



<label>Niveau :</label>

<select name="id_niveau">

<option value="">-- choisir --</option>

<?php

$niv = $bdd->query("SELECT * FROM niveau_etude");

while($n = $niv->fetch()){

$sel = ($n['id_niveau'] == $data['id_niveau']) ? "selected" : "";

?>

<option 
value="<?= $n['id_niveau'] ?>"
<?= $sel ?>
>

<?= htmlspecialchars($n['nom_niveau']) ?>

</option>

<?php } ?>

</select>




<label>Faculté :</label>

<select name="id_fac">

<option value="">-- choisir --</option>

<?php

$fac = $bdd->query("SELECT * FROM faculte");

while($f = $fac->fetch()){

$sel = ($f['id_fac'] == $data['id_fac']) ? "selected" : "";

?>

<option 
value="<?= $f['id_fac'] ?>"
<?= $sel ?>
>

<?= htmlspecialchars($f['nom_fac']) ?>

</option>

<?php } ?>

</select>



<button type="submit">
Modifier
</button>


</form>