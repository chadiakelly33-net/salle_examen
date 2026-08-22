
<?php include("connexionDB.php");
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
include("connexionDB.php");

if (isset($_POST['Ajout'])) {

    $Nom = $_POST['nomcours'] ?? '';
    $Credit = $_POST['credit'] ?? '';
	$date=$_POST['date_exam'] ?? '';
	$heure_d=$_POST['heure_debut'] ?? '';
	$heure_f=$_POST['heure_fin'] ?? '';
	$idniveau=$_POST['id_niveau'] ?? '';
	$idfac=$_POST['id_fac'] ?? '';

    $sql = "INSERT INTO cours (nom_cours,credit,date_exam,heure_debut,heure_fin,id_niveau,id_fac) VALUES (:nomcours, :credit, :date_exam, :heure_debut, :heure_fin, :id_niveau, :id_fac)";
    $stmt = $bdd->prepare($sql);

    $stmt->execute([
        ':nomcours' => $Nom,
        ':credit' => $Credit,
		':date_exam' => $date,
		':heure_debut' => $heure_d,
		':heure_fin' => $heure_f,
		':id_niveau' => $idniveau,
		':id_fac' => $idfac,
		
    ]);

   echo "<script>
	alert('Insertion réussie ');
	window.location.href = 'afficher_cours.php';
	</script>";
}
?>

<body>



<?php
include("menu.php");
?>


<div class="container">

    <h1 class="titre">Liste des cours</h1>

    <a href="afficher_cours.php" class="btn-afficher">
        Afficher les cours
    </a>

    <table>

        <tr>
            <th>Nom du cours</th>
            <th>Credit du cours</th>
			<th>Date examen</th>
			<th>Heure debut</th>
			<th>Heure fin</th>
            <th>Nom niveau</th>
            <th>Nom faculte</th>
            <!--<th colspan="2">Actions</th>-->
        </tr>
		<?php 
		$sql = "SELECT co.*,n.nom_niveau,f.nom_fac FROM cours co
		inner join niveau_etude n on co.id_niveau = n.id_niveau
		inner join faculte f on co.id_fac = f.id_fac order by co.id_cours desc";
		$stmt = $bdd->query($sql);
		while($row = $stmt->fetch(PDO::FETCH_ASSOC)) { 
		?>

        <tr>
            <td><?php echo $row['nom_cours']; ?></td>
            <td><?php echo $row['credit']; ?></td>
			<td><?php echo $row['date_exam'];?></td>
			<td><?php echo $row['heure_debut'];?></td>
			<td><?php echo $row['heure_fin'];?></td>
            <td><?php echo $row['nom_niveau']; ?></td>
            <td><?php echo $row['nom_fac']; ?></td>

            <!--<td>
                <a href="modifie_cours.php" class="btn-modifier">
                    Modifier
                </a>
            </td>

            <td>
                <a href="#" class="btn-supprimer">
                    Supprimer
                </a>
            </td>-->
        </tr>
		<?php }?>
    </table>

</div>

</body>

</html>