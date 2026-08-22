 
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

    $Nom = $_POST['nomsurv'] ?? '';
    $Prenom = $_POST['prenomsurv'] ?? '';


    $sql = "INSERT INTO surveillant (nom_surv,prenom_surv) VALUES (:nomsurv, :prenomsurv)";
    $stmt = $bdd->prepare($sql);

    $stmt->execute([
        ':nomsurv' => $Nom,
        ':prenomsurv' => $Prenom,
		
    ]);

   echo "<script>
	alert('Insertion réussie ');
	window.location.href = 'afficher_sur.php';
	</script>";
}
?>

<body>



<?php
include("menu.php");
?>


<div class="container">

    <h1 class="titre">Liste des surveillants</h1>

    <a href="afficher_sur.php" class="btn-afficher">
        Afficher les surveillants
    </a>

    <table>

        <tr>
            <th>Nom du surveillant</th>
            <th>Prénom du surveillant</th>
            <!--<th colspan="2">Actions</th>-->
        </tr>
		<?php 
		$sql = "SELECT * FROM surveillant";

		$stmt = $bdd->query($sql);
		while($row = $stmt->fetch(PDO::FETCH_ASSOC)) { 
		?>

        <tr>
            <td><?php echo $row['nom_surv']; ?></td>
            <td><?php echo $row['prenom_surv']; ?></td>

            <!--<td>
                <a href="modifie_sur.php" class="btn-modifier">
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