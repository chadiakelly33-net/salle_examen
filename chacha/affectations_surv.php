<?php
require_once __DIR__ . "/proteger_admin.php";
session_start();

include("connexionDB.php");

$timeout = 300; // 5 minutes


/* =========================
   VERIFICATION SESSION
========================= */

if(
    !isset($_SESSION["id_surv"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] != "surveillant"
){

    header("Location: login_surv.php");
    exit();

}


/* =========================
   TIMEOUT
========================= */

if(isset($_SESSION["last_activity"])){

    if(time() - $_SESSION["last_activity"] > $timeout){

        session_unset();
        session_destroy();

        header("Location: login_surv.php?timeout=1");
        exit();

    }

}

$_SESSION["last_activity"] = time();


/* =========================
   ID DU SURVEILLANT
========================= */

$id_surv = $_SESSION["id_surv"];


/* =========================
   RECUPERATION DES AFFECTATIONS
========================= */

/*$req = $bdd->prepare("

    SELECT
        a.id_affectation,

        a.id_surv,

        c.nom_cours,

        s.nom_salle,

        sv.nom_surv,
        sv.prenom_surv,

        a.date_exam,
        a.heure_debut,
        a.heure_fin

    FROM affectation a

    JOIN cours c
        ON a.id_cours = c.id_cours

    JOIN salle s
        ON a.id_salle = s.id_salle

    JOIN surveillant sv
        ON a.id_surv = sv.id_surv

    WHERE a.id_surv = ?

    ORDER BY
        a.date_exam ASC,
        a.heure_debut ASC

");


$req->execute([$id_surv]);


$affectations = $req->fetchAll(PDO::FETCH_ASSOC);
*/

echo "<pre>";

echo "====================================\n";
echo "SURVEILLANT CONNECTÉ\n";
echo "====================================\n";

echo "ID en session : ";
var_dump($_SESSION["id_surv"]);

echo "Role : ";
var_dump($_SESSION["role"]);


echo "\n\n====================================\n";
echo "SURVEILLANTS DANS LA BASE\n";
echo "====================================\n";

$testSurv = $bdd->query("
    SELECT 
        id_surv,
        nom_surv,
        prenom_surv,
        login
    FROM surveillant
    ORDER BY id_surv
");

while($s = $testSurv->fetch(PDO::FETCH_ASSOC)){

    var_dump($s);

}


echo "\n\n====================================\n";
echo "AFFECTATIONS DANS LA BASE\n";
echo "====================================\n";

$testAff = $bdd->query("
    SELECT
        a.id_affectation,
        a.id_etu,
        a.id_cours,
        a.id_salle,
        a.id_surv,
        a.date_exam,
        a.heure_debut,
        a.heure_fin,
        sv.nom_surv,
        sv.prenom_surv
    FROM affectation a
    LEFT JOIN surveillant sv
        ON a.id_surv = sv.id_surv
    ORDER BY a.id_affectation DESC
");

while($a = $testAff->fetch(PDO::FETCH_ASSOC)){

    var_dump($a);

}


echo "\n\n====================================\n";
echo "AFFECTATIONS DU SURVEILLANT CONNECTÉ\n";
echo "====================================\n";

$test = $bdd->prepare("
    SELECT *
    FROM affectation
    WHERE id_surv = ?
");

$test->execute([$id_surv]);

$resultats = $test->fetchAll(PDO::FETCH_ASSOC);

echo "Nombre trouvé : ";
var_dump(count($resultats));

print_r($resultats);

echo "</pre>";

exit();

require_once __DIR__ . "/../session_timeout.php";

?>


<!DOCTYPE html>

<html lang="fr">

<head>

<meta charset="UTF-8">

<title>Mes affectations</title>


<style>

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}


body{

    font-family:Arial, sans-serif;

    background:#eef4f7;

    min-height:100vh;

}


.container{

    width:90%;

    max-width:1100px;

    margin:40px auto;

    background:white;

    padding:30px;

    border-radius:10px;

    box-shadow:0 2px 10px rgba(0,0,0,.1);

}


h2{

    color:#355c7d;

    text-align:center;

    margin-bottom:25px;

}


table{

    width:100%;

    border-collapse:collapse;

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

    color:#856404;

    padding:15px;

    border-radius:8px;

    text-align:center;

}


.btn{

    display:block;

    width:150px;

    margin:25px auto 0;

    text-align:center;

    text-decoration:none;

    color:white;

    background:#7aa6c2;

    padding:10px 15px;

    border-radius:8px;

}


.btn:hover{

    background:#5f8da8;

}

</style>

</head>


<body>


<div class="container">


<h2>
Mes affectations
</h2>


<?php if(count($affectations) > 0): ?>


<table>

<tr>

<th>Cours</th>

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

Aucune affectation trouvée pour votre compte.

</div>


<?php endif; ?>


<a href="espace_surv.php" class="btn">

Retour

</a>


</div>


</body>

</html>