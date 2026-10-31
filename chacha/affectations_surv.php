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

<style>
/* ==============================
   STYLE GENERAL
============================== */

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: Arial, Helvetica, sans-serif;
    background: #f4f6f9;
    color: #333;
    min-height: 100vh;
}

/* ==============================
   BARRE LATERALE
============================== */

.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: 230px;
    height: 100vh;
    background: #003366;
    padding: 25px 15px;
    overflow-y: auto;
    z-index: 1000;
    box-shadow: 3px 0 12px rgba(0,0,0,0.12);
}

.sidebar h3 {
    color: white;
    text-align: center;
    font-size: 20px;
    margin-bottom: 35px;
    padding-bottom: 20px;
    border-bottom: 1px solid rgba(255,255,255,0.2);
}

.sidebar a {
    display: flex;
    align-items: center;
    justify-content: space-between;
    text-decoration: none;
    color: #e8eef7;
    padding: 13px 14px;
    margin-bottom: 8px;
    border-radius: 7px;
    font-size: 14px;
    transition: 0.3s ease;
}

.sidebar a:hover,
.sidebar a.active {
    background: #d4a72c;
    color: #003366;
    font-weight: bold;
}

.sidebar a.logout {
    margin-top: 25px;
    border-top: 1px solid rgba(255,255,255,0.2);
    padding-top: 18px;
}

.badge-menu {
    background: #d4a72c;
    color: #003366;
    padding: 3px 8px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: bold;
}

/* ==============================
   CONTENU PRINCIPAL
============================== */

.container {
    margin-left: 230px;
    padding: 35px 40px;
    max-width: 1500px;
}

h2 {
    color: #003366;
    font-size: 27px;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 2px solid #d4a72c;
}

/* ==============================
   ACTIONS
============================== */

.actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 25px;
}

.btn-export,
.btn-retour {
    display: inline-block;
    text-decoration: none;
    padding: 12px 18px;
    border-radius: 7px;
    font-size: 14px;
    font-weight: bold;
    transition: 0.3s ease;
}

.btn-export {
    background: #003366;
    color: white;
}

.btn-export:hover {
    background: #d4a72c;
    color: #003366;
}

.btn-retour {
    background: #d4a72c;
    color: #003366;
    margin-top: 25px;
}

.btn-retour:hover {
    background: #003366;
    color: white;
}

/* ==============================
   TABLEAU
============================== */

.table-container {
    width: 100%;
    overflow-x: auto;
    background: white;
    border-radius: 10px;
    border: 1px solid #e5eaf0;
    box-shadow: 0 3px 12px rgba(0,0,0,0.05);
}

table {
    width: 100%;
    border-collapse: collapse;
    background: white;
}

thead th {
    background: #003366;
    color: white;
    padding: 16px 13px;
    text-align: center;
    font-size: 14px;
    white-space: nowrap;
}

tbody td {
    padding: 15px 13px;
    border-bottom: 1px solid #e8edf3;
    text-align: center;
    font-size: 14px;
    white-space: nowrap;
}

tbody tr:nth-child(even) {
    background: #f8fafc;
}

tbody tr:hover {
    background: #fff8e5;
}

tbody tr:last-child td {
    border-bottom: none;
}

/* ==============================
   MESSAGE VIDE
============================== */

.message {
    background: #fffdf6;
    color: #805b00;
    padding: 20px;
    border-left: 5px solid #d4a72c;
    border-radius: 8px;
    margin-top: 20px;
    line-height: 1.6;
}

/* ==============================
   RESPONSIVE
============================== */

@media (max-width: 850px) {

    .container {
        padding: 25px;
    }

    h2 {
        font-size: 23px;
    }
}

@media (max-width: 650px) {

    .sidebar {
        position: relative;
        width: 100%;
        height: auto;
        padding: 15px;
    }

    .sidebar h3 {
        margin-bottom: 15px;
        padding-bottom: 12px;
    }

    .sidebar a {
        margin-bottom: 5px;
    }

    .container {
        margin-left: 0;
        padding: 22px 15px;
    }

    h2 {
        font-size: 21px;
    }

    .btn-export,
    .btn-retour {
        font-size: 13px;
    }

    thead th,
    tbody td {
        padding: 11px;
        font-size: 12px;
    }
}
</style>

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