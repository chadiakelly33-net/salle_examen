<?php

session_start();

if(!isset($_SESSION["id_admin"]) || $_SESSION["role"]!="admin"){
    header("Location: connexion.php");
    exit();
}

include("connexionDB.php");
include("menu_principal.php");


$req = $bdd->query("
    SELECT *
    FROM journal_activite
    ORDER BY date_action DESC
");

$journal = $req->fetchAll();

?>


<html>

<head>

<meta charset="UTF-8">

<title>Journal des activités</title>


<style>

body{
    font-family:Arial;
    background:#f4f6f9;
}


h2{
    text-align:center;
    color:#003366;
    margin-top:30px;
}


table{

    width:90%;
    margin:30px auto;
    border-collapse:collapse;
    background:white;
}


th{

    background:#003366;
    color:white;
    padding:12px;

}


td{

    padding:10px;
    text-align:center;
    border:1px solid #ddd;

}


tr:nth-child(even){

    background:#f2f2f2;

}


</style>


</head>


<body>


<h2>
Journal des activités
</h2>



<?php if(count($journal)>0){ ?>


<table>


<tr>

<th>ID</th>

<th>Utilisateur</th>

<th>Type</th>

<th>Action</th>

<th>Date</th>


</tr>



<?php foreach($journal as $j){ ?>


<tr>


<td>
<?= $j["id"] ?>
</td>


<td><?= htmlspecialchars($j["nom_utilisateur"]) ?></td>


<td>
<?= $j["type_user"] ?>
</td>


<td>
<?= $j["action"] ?>
</td>


<td>
<?= $j["date_action"] ?>
</td>


</tr>


<?php } ?>


</table>


<?php }else{ ?>


<p style="text-align:center;color:red;">
Aucune activité enregistrée.
</p>


<?php } ?>


</body>

</html>