<?php
session_start();
if(!isset($_SESSION["id_admin"]) || $_SESSION["role"] != "admin"){
    header("Location: connexion.php");
    exit();
}

include("connexionDB.php");

if(isset($_POST["ajouter"])){

    $req = $bdd->prepare("
        INSERT INTO etudiant (nom_etu, prenom_etu, id_niveau, id_fac, login, motdepasse)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $req->execute([
        $_POST["nom"],
        $_POST["prenom"],
        $_POST["id_niveau"],
        $_POST["id_fac"],
        $_POST["login"],
        $_POST["motdepasse"]
    ]);
}

?>

<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Liste étudiants</title>

<style>

body{
    font-family:Arial, sans-serif;
    background:#f9fafb;
    margin:0;
}

/* titre */
h2{
    text-align:center;
    color:#2c3e50;
    margin:20px 0;
}

/* table */
table{
    width:95%;
    margin:auto;
    border-collapse:collapse;
    background:white;
    table-layout:fixed;
}
/* header */
/* header */
th{
    background:#f1f3f5;
    color:#2c3e50;
    padding:12px;
    border-bottom:1px solid #ddd;
    text-align:center;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
}

/* cellules */
td{
    padding:12px;
    text-align:center;
    border-bottom:1px solid #eee;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
}

/* hover */
tr:hover{
    background:#f7f9fc;
}

/* liens actions */
a{
    text-decoration:none;
    padding:6px 10px;
    border-radius:5px;
    margin:2px;
    display:inline-block;
}

/* modifier */
a[href*="modifie"]{
    background:#e3f2fd;
    color:#1565c0;
}

/* supprimer (gris au lieu de rouge) */
a[href*="supprimer"]{
    background:#f5f5f5;
    color:#555;
}

/* hover liens */
a:hover{
    opacity:0.8;
}

</style>

</head>

<body>

<aside id="menu" class="menu">
<?php include 'Menu_principal.php';?>
</aside>


<h2>Liste des étudiants</h2>

<table>

<tr>
<th>Photo</th>
<th>Nom</th>
<th>Prénom</th>
<th>Niveau</th>
<th>Faculté</th>
<th>login</th>
<!--<th>mot de passe</th>-->
<th>Actions</th>
</tr>

<?php
$req = $bdd->query("
SELECT 
e.id_etu,
e.nom_etu,
e.prenom_etu,
e.photo,
e.login,
e.motdepasse,
n.nom_niveau,
f.nom_fac

FROM etudiant e

JOIN niveau_etude n 
ON e.id_niveau=n.id_niveau

JOIN faculte f 
ON e.id_fac=f.id_fac
");

while($row = $req->fetch()){
?>

<tr>
<td>

<?php if(!empty($row["photo"]) && file_exists("uploads/".$row["photo"])){ ?>

<img 
src="uploads/<?= htmlspecialchars($row["photo"]) ?>"
width="70"
height="70"
style="
border-radius:50%;
object-fit:cover;
">

<?php }else{ ?>

<img 
src="uploads/default.png"
width="70"
height="70"
style="
border-radius:50%;
object-fit:cover;
">

<?php } ?>

</td>
<td><?= $row["nom_etu"] ?></td>
<td><?= $row["prenom_etu"] ?></td>
<td><?= $row["nom_niveau"] ?></td>
<td><?= $row["nom_fac"] ?></td>
<td><?= $row["login"] ?></td>
<!--<td>********</td>-->

<td>
<a href="modifie_etu.php?id=<?= $row['id_etu'] ?>">Modifier</a>
<a href="supprimer_etu.php?id=<?= $row['id_etu'] ?>" onclick="return confirm('Supprimer cet étudiant ?')">Supprimer</a>
</td>
</tr>

<?php } ?>

</table>

</body>
</html>