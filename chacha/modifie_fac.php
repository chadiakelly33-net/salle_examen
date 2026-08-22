<?php
session_start();
if(!isset($_SESSION["id_admin"]) || $_SESSION["role"] != "admin"){
    header("Location: connexion.php");
    exit();
}

 include "connexionDB.php";
 ?>
<html>

	<head>
	<style>
	table,th,td{
		border:white 2px solid;
		border-radius: 105px;
		border-collapse:collapse;
		padding:5px;
	}
	</style>

	<head>
<?php
include("menu_principal.php");
?>
	<body>
	<?php 
		$id = $_GET['mod_fac'];
		if(!$id){
			echo "Erreur : id n'existe pas";
		}
		$stmt = $bdd->prepare("select * from faculte where id_fac=?");
		$stmt->execute([$id]);
		$user = $stmt->fetch();
	?>
	<h1> Modifier Un Faculte</h1>
	
	<form action="afficher_fac.php" methode="POST">
	<table>
	<input type="hidden" name="id" value="<?php echo $user['id_fac'];?>"/>
	<tr> <th> Nom_faculte </th>
	<th> <input type="text" name="nom_f" value="<?php echo $user['nom_fac'];?>" /> </th>
	<tr>
	<th> <input type="submit" value="Modifier" name="modif" /> 
	<input type="reset" value="Annuler"/>
	</th>
	</tr>
	</table>
	</form>
	</body>
<html>