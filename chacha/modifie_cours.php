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
    $_SESSION["nom_admin"],
    "admin",
    "Modification du cours ".$nom_cours
);

header("Location: afficher_cours.php");
exit();

$id = $_GET["id"];

$req = $bdd->prepare("SELECT * FROM cours WHERE id_cours=?");
$req->execute([$id]);
$data = $req->fetch();
?>

<h2>Modifier cours</h2>

<form method="POST" action="update_cours.php">

<input type="hidden" name="id" value="<?= $data['id_cours'] ?>">

<input type="text" name="nom_cours" value="<?= $data['nom_cours'] ?>">
<input type="number" name="credit" value="<?= $data['credit'] ?>">

<!-- Niveau -->
<select name="id_niveau">
<?php
$niv = $bdd->query("SELECT * FROM niveau_etude");
while($n = $niv->fetch()){
$selected = ($n['id_niveau'] == $data['id_niveau']) ? "selected" : "";
echo "<option value='{$n['id_niveau']}' $selected>{$n['nom_niveau']}</option>";
}
?>
</select>

<!-- Faculté -->
<select name="id_fac">
<?php
$fac = $bdd->query("SELECT * FROM faculte");
while($f = $fac->fetch()){
$selected = ($f['id_fac'] == $data['id_fac']) ? "selected" : "";
echo "<option value='{$f['id_fac']}' $selected>{$f['nom_fac']}</option>";
}
?>
</select>

<input type="date" name="date_exam" value="<?= $data['date_exam'] ?>">
<input type="time" name="heure_debut" value="<?= $data['heure_debut'] ?>">
<input type="time" name="heure_fin" value="<?= $data['heure_fin'] ?>">

<button type="submit">Modifier</button>

</form>