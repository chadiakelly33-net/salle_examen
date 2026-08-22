 
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

    $Nom = $_POST['nometu'] ?? '';
    $Prenom = $_POST['prenometu'] ?? '';
	$idniveau=$_POST['id_niveau'] ?? '';
	$idfac=$_POST['id_fac'] ?? '';
	$idsalle=$_POST['id_salle'] ?? '';
	$idcours=$_POST['id_cours'] ?? '';
	$logi=$_POST['login'] ?? '';
	$mot=$_POST['motdepasse'] ?? '';

    $sql = "INSERT INTO etudiant (nom_etu,prenom_etu,id_niveau,id_fac,id_salle,id_cours,login,motdepasse) VALUES (:nometu, :prenometu, :id_niveau, :id_fac, :id_salle, :id_cours, :login, :motdepasse)";
    $stmt = $bdd->prepare($sql);

    $stmt->execute([
        ':nometu' => $Nom,
        ':prenometu' => $Prenom,
		':id_niveau' => $idniveau,
		':id_fac' => $idfac,
		':id_salle' => $idsalle,
		':id_cours' => $idcours,
		':login' => $logi,
		':motdepasse' => $mot,
		
    ]);

   echo "<script>
	alert('Insertion réussie ');
	window.location.href = 'afficher_etu.php';
	</script>";
}
?>

<body>



<?php
include("menu.php");
?>


<div class="container">

    <h1 class="titre">Liste des Étudiants</h1>

    <a href="afficher_etu.php" class="btn-afficher">
        Afficher les étudiants
    </a>

    <table>

        <tr>
            <th>Nom Étudiant</th>
            <th>Prénom Étudiant</th>
            <th>Nom niveau</th>
            <th>Nom faculte</th>
            <th>Nom salle</th>
			<th>Nom du cours</th>
            <!--<th colspan="2">Actions</th>-->
        </tr>
		<?php 
		$sql = "SELECT et.*,n.nom_niveau,f.nom_fac,s.nom_salle,c.nom_cours FROM etudiant et
		inner join niveau_etude n on et.id_niveau = n.id_niveau
		inner join faculte f on et.id_fac = f.id_fac
		inner join salle s on et.id_salle = s.id_salle
		inner join cours c on et.id_cours=c.id_cours order by et.id_etu desc";
		$stmt = $bdd->query($sql);
		while($row = $stmt->fetch(PDO::FETCH_ASSOC)) { 
		?>

        <tr>
            <td><?php echo $row['nom_etu']; ?></td>
            <td><?php echo $row['prenom_etu']; ?></td>
            <td><?php echo $row['nom_niveau']; ?></td>
            <td><?php echo $row['nom_fac']; ?></td>
            <td><?php echo $row['nom_salle']; ?></td>
			<td><?php echo $row['nom_cours']; ?></td>
			<td><?php echo $row['login']; ?></td>
			<td><?php echo $row['motdepasse']; ?></td>

            <!--<td>
                <a href="modifie_etu.php" class="btn-modifier">
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