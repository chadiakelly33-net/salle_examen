<?php
session_start();
if(!isset($_SESSION["id_admin"]) || $_SESSION["role"] != "admin"){
    header("Location: connexion.php");
    exit();
}

include("connexionDB.php");
include("journal.php");

journal(
    $bdd,
    $_SESSION["id_admin"],
    "Administrateur",
    "admin",
    "Consultation de la liste des cours"
);
?>


<html>
<head>
<meta charset="UTF-8">
<title>Liste des cours</title>

<style>

body{
    font-family: Arial;
    background: #f5f7fb;
    margin:0;
}

/* ===== MENU ===== */
header{
    background:#003366;
}

.menu{
    list-style:none;
    margin:0;
    padding:0;
    display:flex;
    justify-content:center;
}

.menu li{
    position:relative;
}

.menu li a{
    display:block;
    color:white;
    text-decoration:none;
    padding:15px 25px;
    font-weight:bold;
}

.menu li a:hover{
    background:#0055aa;
}

/* ===== TITRE ===== */
h2{
    text-align:center;
    margin:20px 0;
    color:#003366;
}

/* ===== TABLE ===== */
table{
    width:95%;
    margin:auto;
    border-collapse:collapse;
    background:white;
    box-shadow:0 2px 8px rgba(0,0,0,0.1);
}

th,td{
    border:1px solid #ddd;
    padding:10px;
    text-align:center;
}

th{
    background:#003366;
    color:white;
}

/* ===== ACTIONS ===== */
a{
    text-decoration:none;
    padding:6px 10px;
    border-radius:4px;
    font-size:14px;
}

/* modifier */
.mod{
    background:#e6f0ff;
    color:#003366;
    border:1px solid #003366;
}

/* supprimer */
.sup{
    background:#ffebeb;
    color:#b30000;
    border:1px solid #b30000;
}

a:hover{
    opacity:0.8;
}

</style>
</head>

<body>

<!-- MENU 
<header>
    <ul class="menu">
        <li><a href="accueil.php">Accueil</a></li>
        <li><a href="afficher_etu.php">Étudiants</a></li>
        <li><a href="afficher_cours.php">Cours</a></li>
        <li><a href="afficher_fac.php">Facultés</a></li>
        <li><a href="afficher_salle.php">Salles</a></li>
        <li><a href="deconnexion.php">Déconnexion</a></li>
    </ul>
</header> -->
<aside id="menu" class="menu">
<?php include 'Menu_principal.php';?>
</aside>
<h2>Liste des cours</h2>

<table>

<tr>
<th>Nom</th>
<th>Crédit</th>
<th>Niveau</th>
<th>Faculté</th>
<th>Date</th>
<th>Début</th>
<th>Fin</th>
<th>Actions</th>
</tr>

<?php
$req = $bdd->query("
SELECT 
cours.*,
niveau_etude.nom_niveau,
faculte.nom_fac
FROM cours
JOIN niveau_etude ON cours.id_niveau = niveau_etude.id_niveau
JOIN faculte ON cours.id_fac = faculte.id_fac
");

while($row = $req->fetch()){
?>

<tr>
<td><?= $row["nom_cours"] ?></td>
<td><?= $row["credit"] ?></td>
<td><?= $row["nom_niveau"] ?></td>
<td><?= $row["nom_fac"] ?></td>
<td><?= $row["date_exam"] ?></td>
<td><?= $row["heure_debut"] ?></td>
<td><?= $row["heure_fin"] ?></td>

<td>
<a class="mod" href="modifie_cours.php?id=<?= $row['id_cours'] ?>">Modifier</a>
<a class="sup" href="supprimer_cours.php?id=<?= $row['id_cours'] ?>" onclick="return confirm('Supprimer ?')">Supprimer</a>
</td>

</tr>

<?php } ?>

</table>

</body>
</html>