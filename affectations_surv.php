<?php

session_start();

include("connexionDB.php");

$timeout = 300; // 5 minutes


if(isset($_SESSION["last_activity"])){

    if(time() - $_SESSION["last_activity"] > $timeout){

        session_unset();
        session_destroy();

        header("Location: login_surv.php?timeout=1");
        exit();

    }

}


$_SESSION["last_activity"] = time();


// Vérifier connexion surveillant

if(!isset($_SESSION["id_surv"]) || $_SESSION["role"]!="surveillant"){

    header("Location: login_surv.php");
    exit();

}



// Récupérer les affectations du surveillant connecté

$req = $bdd->prepare("

SELECT 
    a.id_affectation,
    c.nom_cours,
    c.date_exam,
    c.heure_debut,
    c.heure_fin,
    s.nom_salle,
    f.nom_fac,
    n.nom_niveau

FROM affectation a

JOIN cours c 
ON a.id_cours = c.id_cours

JOIN salle s
ON a.id_salle = s.id_salle

JOIN faculte f
ON c.id_fac = f.id_fac

JOIN niveau_etude n
ON c.id_niveau = n.id_niveau


WHERE a.id_surv = ?

AND CONCAT(c.date_exam,' ',c.heure_fin) >= NOW()


ORDER BY c.date_exam, c.heure_debut

");


$req->execute([

    $_SESSION["id_surv"]

]);


$affectations = $req->fetchAll();


?>




<html lang="fr">

<head>

<meta charset="UTF-8">

<title>Mes affectations</title>


<style>


body{

    margin:0;
    font-family:Arial;
    background:#eef4f7;

}



.container{

    padding:30px;

}



h2{

    color:#355c7d;

}



table{

    width:100%;
    border-collapse:collapse;
    background:white;
    margin-top:20px;

}



th{

    background:#355c7d;
    color:white;
    padding:12px;

}



td{

    padding:12px;
    border:1px solid #ddd;
    text-align:center;

}



tr:hover{

    background:#f1f7fa;

}



.message{

    background:#fff3cd;
    padding:15px;
    border-radius:8px;
    margin-top:20px;

}



a{

    text-decoration:none;
    color:white;
    background:#7aa6c2;
    padding:10px 15px;
    border-radius:8px;

}



</style>

</head>



<body>



<div class="container">





<h2>
Mes affectations actuelles et futures
</h2>



<?php if(count($affectations)>0): ?>



<table>


<tr>

<th>Cours</th>
<th>Faculté</th>
<th>Niveau</th>
<th>Salle</th>
<th>Date examen</th>
<th>Début</th>
<th>Fin</th>

</tr>




<?php foreach($affectations as $a): ?>

<tr>


<td>
<?= htmlspecialchars($a["nom_cours"]) ?>
</td>


<td>
<?= htmlspecialchars($a["nom_fac"]) ?>
</td>


<td>
<?= htmlspecialchars($a["nom_niveau"]) ?>
</td>


<td>
<?= htmlspecialchars($a["nom_salle"]) ?>
</td>


<td>
<?= htmlspecialchars($a["date_exam"]) ?>
</td>


<td>
<?= htmlspecialchars($a["heure_debut"]) ?>
</td>


<td>
<?= htmlspecialchars($a["heure_fin"]) ?>
</td>



</tr>


<?php endforeach; ?>


</table>



<?php else: ?>


<div class="message">

Aucune affectation future ou en cours.

</div>

<a href="espace_surv.php">
Retour
</a>


<?php endif; ?>



</div>


</body>

</html>