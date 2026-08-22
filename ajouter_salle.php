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

<form action="afficher_salle.php" method="POST">

<fieldset>

<legend>Ajouter une salle</legend>

<table>

<tr>
    <th>Nom de la salle</th>
</tr>
<tr>
    <td><input type="text" name="nom_salle" required></td>
</tr>

<tr>
    <th>Capacite de la salle</th>
</tr>
<tr>
    <td><input type="number" name="capacite" required></td>
</tr>

<tr>
    <th>Surveillant</th>
</tr>
<tr>
    <td>
		<select name="id_surv" required>
		<option value="">-----selectionner le surveillant -----</option>
		<?php 
			$liste_surveillant = $bdd->query("select id_surv,nom_surv,prenom_surv from surveillant");
			while($surv = $liste_surveillant->fetch()){
				echo "<option value='" . $surv['id_surv'] . "'>" . htmlspecialchars($surv['nom_surv']) . htmlspecialchars($surv['prenom_surv']) . "</option>";
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