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
<title>Liste des Étudiants</title>

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
    width:90%;
    margin:30px auto;
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

<?php

if (isset($_POST['Ajout'])) {

    $Nom = $_POST['nom_fac'] ?? '';

    $sql = "INSERT INTO faculte (nom_fac) VALUES (:nom_fac)";
    $stmt = $bdd->prepare($sql);

    $stmt->execute([
        ':nom_fac' => $Nom,
		
    ]);

   echo "<script>
	alert('Insertion réussie ');
	window.location.href = 'afficher_fac.php';
	</script>";
}
?>

<?php
if (isset($_POST['modif'])) {
	$id_faculte = $_POST['id'];
    $Nom_mod = $_POST['nom_f'];

    try{
		$requt = $bdd->exec("UPDATE faculte SET nom_fac= ".$Nom_mod." WHERE id_fac=".$id_faculte."");
	
	if($requt->rowCount()>0){
		echo "Modifie avec succes";
	}else{
		echo "Aucune modification detecte";
	}

    echo "<script>
	alert('modification réussie ');
	window.location.href = 'afficher_fac.php';
	</script>";
	}catch(PDOException $e){
		echo "Erreur : SQL" .$e->getMessage();
	}
}
?>

<body>

<aside id="menu" class="menu">
<?php include 'Menu_principal.php';?>
</aside>


<div class="container">

    <h1 class="titre">Liste des facultes</h1>

    <!--<a href="afficher_fac.php" class="btn-afficher">
        Afficher les facultes
    </a>-->

    <table>

        <tr>
            <th>Nom de la faculte</th>
            <th colspan="2">Actions</th>
        </tr>
		<?php 
		$sql = "SELECT * FROM faculte";
		$stmt = $bdd->query($sql);
		while($row = $stmt->fetch(PDO::FETCH_ASSOC)) { 
		?>

        <tr>
            <td><?php echo $row['nom_fac']; ?></td>

            <td>
                <a href="modifie_fac.php?mod_fac=<?php echo $row['id_fac'];?>" class="btn-modifier">
                    Modifier
                </a>
            </td>

            <td>
                <a href="afficher_fac.php?supp=<?php echo $row['id_fac'];?>" class="btn-supprimer">
                    Supprimer
                </a>
            </td>
        </tr>
		<?php }?>
    </table>

</div>

</body>

</html>