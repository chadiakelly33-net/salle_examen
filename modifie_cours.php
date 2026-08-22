

<html>

	<head>
	<style>
	table,th,td{
		border:black 2px solid;
		border-radius: 105px;
		border-collapse:collapse;
		padding:5px;
	}
	</style>

	<head>
    <?php
      include("menu.php");
    ?>
	<body>
	<h1> Modifier Un cours</h1>
	
	<table>
	<tr> <th> Nom_Cours </th>
	<th> <input type="text"/> </th>
	</tr>
	<tr> <th> Credit </th>
	<th> <input type="text"/> </th>
	</tr>

	<tr> <th> Niveau  </th>
	<th> <input type="text"/> </th>
	</tr>
	<tr> <th> Faculte  </th>
	<th> <input type="text"/> </th>
	</tr>
	<tr>
	<th> <input type="submit" value="MOdifier"/> 
	<input type="reset" value="Annuler"/>
	</th>
	</tr>
	
	</table>

	</body>
	<footer>

	</footer>
<html>