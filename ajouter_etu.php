<?php include("connexionDB.php");

?>
<html lang="fr">

<head>
<meta charset="UTF-8">
<title>Ajouter un étudiant</title>

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
    width:450px;
    margin:50px auto;
}

fieldset{
    border:none;
    background:white;
    padding:30px;
    border-radius:10px;
    box-shadow:0 4px 10px rgba(0,0,0,0.15);
}

legend{
    text-align:center;
    font-size:24px;
    font-weight:bold;
    color:#003366;
    margin:auto;
    padding:10px;
}

table{
    width:100%;
}

th{
    text-align:left;
    padding:10px 0;
    color:#333;
}

input[type="text"]{
    width:100%;
    height:40px;
    border:1px solid #ccc;
    border-radius:5px;
    padding-left:10px;
    outline:none;
}

input[type="text"]:focus{
    border:2px solid #003366;
}

.boutons{
    text-align:center;
    padding-top:20px;
}

input[type="submit"]{
    background:#003366;
    color:white;
    border:none;
    padding:10px 20px;
    border-radius:5px;
    cursor:pointer;
    margin-right:10px;
}

input[type="submit"]:hover{
    background:#0055aa;
}

input[type="reset"]{
    background:#dc3545;
    color:white;
    border:none;
    padding:10px 20px;
    border-radius:5px;
    cursor:pointer;
}

input[type="reset"]:hover{
    background:#c82333;
}

</style>

</head>

<body>

<?php
include("Menu.php");
?>

<div class="container">

<form action="afficher_etu.php" method="POST">

<fieldset>

<legend>Ajouter un étudiant</legend>

<table>

<tr>
    <th>Nom Étudiant</th>
</tr>
<tr>
    <td><input type="text" name="nometu" required></td>
</tr>

<tr>
    <th>Prénom Étudiant</th>
</tr>
<tr>
    <td><input type="text" name="prenometu" required></td>
</tr>

<tr>
    <th>Niveau</th>
</tr>
<tr>
    <td>
		<select name="id_niveau" required>
		<option value="">-----selectionner le niveau -----</option>
		<?php 
			$liste_niveau = $bdd->query("select id_niveau,nom_niveau from niveau_etude");
			while($niv = $liste_niveau->fetch()){
				echo "<option value='" . $niv['id_niveau'] . "'>" . htmlspecialchars($niv['nom_niveau']) . "</option>";
			}
		?>
	</select>
	
	</td>
</tr>

<tr>
    <th>Faculté</th>
</tr>
<tr>
    <td>
		<select name="id_fac" required>
		<option value="">-----selectionner une faculte -----</option>
		<?php 
			$liste_faculte = $bdd->query("select id_fac,nom_fac from faculte");
			while($facu = $liste_faculte->fetch()){
				echo "<option value='" . $facu['id_fac'] . "'>" . htmlspecialchars($facu['nom_fac']) . "</option>";
			}
		?>
	</select>
	
	</td>
</tr>

<tr>
    <th>Salle</th>
</tr>
<tr>
    <td>
	<select  name="id_salle" required>
		<option value="">-----selectionner une salle -----</option>
		<?php 
			$liste_salle = $bdd->query("select id_salle,nom_salle from salle");
			while($sale = $liste_salle->fetch()){
				echo "<option value='" . $sale['id_salle'] . "'>" . htmlspecialchars($sale['nom_salle']) . "</option>";
			}
		?>
	</select>
	</td>
</tr>

<tr>
    <th>Cours</th>
</tr>
<tr>
    <td>
	<select  name="id_cours" required>
		<option value="">-----selectionner un cours -----</option>
		<?php 
			$liste_cours = $bdd->query("select id_cours,nom_cours from cours");
			while($cour = $liste_cours->fetch()){
				echo "<option value='" . $cour['id_cours'] . "'>" . htmlspecialchars($cour['nom_cours']) . "</option>";
			}
		?>
	</select>
	</td>
</tr>

<tr>
    <td class="boutons">
        <input type="submit" value="Ajouter" name="Ajout">
        <input type="reset" value="Annuler">
    </td>
</tr>

</table>

</fieldset>

</form>

</div>

</body>
</html>