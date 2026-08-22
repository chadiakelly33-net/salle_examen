<?php
session_start();
if(!isset($_SESSION["id_admin"]) || $_SESSION["role"] != "admin"){
    header("Location: connexion.php");
    exit();
}

include("connexionDB.php");

if (isset($_POST['Ajout'])) {

    $nom = $_POST['nom'] ?? '';
    $capacite = $_POST['capacite'] ?? '';
	$idsalle=$_POST['id'] ?? '';

    $sql = "INSERT INTO salle (nom_salle,capacite,id_surv) VALUES (:nom, :capac, :id)";
    $stmt = $bdd->prepare($sql);

    $stmt->execute([
        ':nom' => $nom,
        ':capac' => $capacite,
		'id' => $idsalle
    ]);

   echo "<script>
	alert('Insertion réussie ');
	window.location.href = 'ajouter_salle.php';
	</script>";
}
?>