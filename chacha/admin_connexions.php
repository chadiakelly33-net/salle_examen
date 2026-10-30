<?php
require_once __DIR__ . "/proteger_admin.php";
session_start();

if(!isset($_SESSION["id_admin"]) || $_SESSION["role"] != "admin"){
    header("Location: connexion.php");
    exit();
}

include("connexionDB.php");

include("menu_principal.php");

include("journal.php");

journal(
    $bdd,
    $_SESSION["id_admin"],
    "Administrateur",
    "admin",
    "Consultation de l'historique des connexions"
);


// Récupérer les historiques de connexion

$req = $bdd->query("
    SELECT *
    FROM connexion_log
    ORDER BY date_connexion DESC
");

$logs = $req->fetchAll();

require_once __DIR__ . "/../session_timeout.php";

?>


<html lang="fr">

<head>

<meta charset="UTF-8">

<title>Historique des connexions</title>


<style>


body{

    font-family:Arial, Helvetica, sans-serif;

    background:#eef4f7;

}



h2{

    text-align:center;

    color:#355c7d;

    margin-top:30px;

}



/* Tableau */

table{

    border-collapse:collapse;

    width:95%;

    margin:30px auto;

    background:white;

    border-radius:10px;

    overflow:hidden;

    box-shadow:0 5px 15px rgba(0,0,0,0.1);

}



th{

    background:#7aa6c2;

    color:white;

    padding:12px;

}



td{

    padding:10px;

    text-align:center;

    border-bottom:1px solid #ddd;

}



tr:hover{

    background:#f4f9fb;

}



/* Statut */

.success{

    color:green;

    font-weight:bold;

}



.failed{

    color:red;

    font-weight:bold;

}



.message{

    text-align:center;

    color:#555;

    font-size:18px;

}


</style>


</head>



<body>


<h2>
Historique des connexions
</h2>



<?php if(count($logs)>0): ?>


<table>


<tr>

<th>ID</th>

<th>Utilisateur</th>

<th>Type</th>

<th>Adresse IP</th>

<th>Statut</th>

<th>Date connexion</th>

<th>Date déconnexion</th>

<th>Durée</th>


</tr>




<?php foreach($logs as $log): ?>


<tr>


<td>

<?= htmlspecialchars($log["id"]) ?>

</td>



<td>

<?= htmlspecialchars($log["nom_utilisateur"]) ?>

</td>



<td>

<?= htmlspecialchars($log["type_user"]) ?>

</td>



<td>

<?= htmlspecialchars($log["adresse_ip"]) ?>

</td>



<td>


<?php if($log["statut"]=="success"): ?>


<span class="success">

Connexion réussie

</span>


<?php else: ?>


<span class="failed">

Échec

</span>


<?php endif; ?>


</td>



<td>

<?= htmlspecialchars($log["date_connexion"]) ?>

</td>



<td>


<?= $log["date_deconnexion"] 
? htmlspecialchars($log["date_deconnexion"]) 
: "-" ?>


</td>




<td>


<?= $log["duree_connexion"] 
? htmlspecialchars($log["duree_connexion"]) 
: "-" ?>


</td>



</tr>


<?php endforeach; ?>



</table>



<?php else: ?>


<p class="message">

Aucune connexion enregistrée.

</p>



<?php endif; ?>



</body>

</html>