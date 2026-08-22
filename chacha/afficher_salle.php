<?php
session_start();
if(!isset($_SESSION["id_admin"]) || $_SESSION["role"] != "admin"){
    header("Location: connexion.php");
    exit();
}

include("connexionDB.php");
?>
<?php
include("connexionDB.php");

/* =========================
   INSERTION SALLE
========================= */
if (isset($_POST['ajouter'])) {

    $nom = $_POST['nom_salle'];
    $capacite = $_POST['capacite'];

    $sql = "INSERT INTO salle (nom_salle, capacite) VALUES (:nom, :capacite)";
    $stmt = $bdd->prepare($sql);

    $stmt->execute([
        ':nom' => $nom,
        ':capacite' => $capacite
    ]);

    echo "<script>
        alert('Salle ajoutée avec succès');
        window.location.href='afficher_salle.php';
    </script>";
}
?>

<html lang="fr">

<head>
<meta charset="UTF-8">
<title>Gestion des Salles</title>

<style>

body{
    margin:0;
    font-family:Arial;
    background:#f6f7fb;
}

.wrapper{
    display:flex;
}

/* MENU */
aside{
    width:220px;
}

/* CONTENU */
main{
    flex:1;
    padding:20px;
}

/* FORMULAIRE */
.form-box{
    background:white;
    padding:15px;
    border-radius:10px;
    margin-bottom:20px;
    box-shadow:0 2px 10px rgba(0,0,0,0.05);
}

input{
    width:100%;
    padding:10px;
    margin:8px 0;
}

button{
    padding:10px;
    background:#1f2937;
    color:white;
    border:none;
    width:100%;
    cursor:pointer;
}

button:hover{
    background:#111827;
}

/* TABLE */
table{
    width:100%;
    border-collapse:collapse;
    background:white;
    border-radius:10px;
    overflow:hidden;
    box-shadow:0 2px 10px rgba(0,0,0,0.05);
}

th{
    background:#1f2937;
    color:white;
    padding:10px;
}

td{
    padding:10px;
    text-align:center;
}

a{
    text-decoration:none;
    padding:6px 10px;
    border-radius:5px;
}

.edit{
    background:#16a34a;
    color:white;
}

.delete{
    background:#dc2626;
    color:white;
}

</style>

</head>

<body>

<div class="wrapper">

<!-- MENU -->
<aside>
<?php include("Menu_principal.php"); ?>
</aside>

<!-- CONTENU -->
<main>

<h2>Gestion des Salles</h2>

<!-- ================= FORMULAIRE AJOUT ================= 
<div class="form-box">

    <h3>Ajouter une salle</h3>

    <form method="POST">

        <input type="text" name="nom_salle" placeholder="Nom de la salle" required>

        <input type="number" name="capacite" placeholder="Capacité" required>

        <button type="submit" name="ajouter">Ajouter</button>

    </form>

</div>

<!-- ================= TABLEAU ================= -->
<table>

<tr>
    <th>Nom salle</th>
    <th>Capacité</th>
    <th>Statut</th>
    <th>Actions</th>
</tr>

<?php
$sql = "SELECT * FROM salle";
$stmt = $bdd->query($sql);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
?>

<tr>
    <td><?= $row['nom_salle'] ?></td>
    <td><?= $row['capacite'] ?></td>

    <td>
        <?php if($row['statut'] == 'disponible'){ ?>
             Libre
        <?php } else { ?>
             Occupée
        <?php } ?>
    </td>

    <td>
        <a class="edit" href="modifier_salle.php?id=<?= $row['id_salle'] ?>">Modifier</a>
        <a class="delete" href="supprimer_salle.php?id=<?= $row['id_salle'] ?>"
           onclick="return confirm('Supprimer cette salle ?')">
           Supprimer
        </a>
    </td>
</tr>

<?php } ?>

</table>

</main>

</div>

</body>
</html>