<?php
include("connexionDB.php");

if (isset($_POST['Ajout'])) {

    $Nom = $_POST['nometu'] ?? '';
    $Prenom = $_POST['prenometu'] ?? '';
	$idniveau=$_POST['id_niveau'] ?? '';
	$idfac=$_POST['id_fac'] ?? '';
	$idsalle=$_POST['id_salle'] ?? '';

    $sql = "INSERT INTO etudiant (nom_etu,prenom_etu,id_niveau,id_fac,id_salle) VALUES (:nometu, :prenometu, :id_niveau, :id_fac, :id_salle)";
    $stmt = $bdd->prepare($sql);

    $stmt->execute([
        ':nometu' => $Nom,
        ':prenometu' => $Prenom,
		':id_niveau' => $idniveau,
		':id_fac' => $idfac,
		':id_salle' => $idsalle
		
    ]);

   echo "<script>
	alert('Insertion réussie ');
	window.location.href = 'ajoute_etu.php';
	</script>";
}
?>