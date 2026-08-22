<?php

session_start();

if(!isset($_SESSION["id_admin"]) || $_SESSION["role"] != "admin"){
    header("Location: connexion.php");
    exit();
}

include("connexionDB.php");


// ===============================
// Récupérer les filtres
// ===============================

$id_fac = $_GET["id_fac"] ?? "";
$id_niveau = $_GET["id_niveau"] ?? "";


// ===============================
// Nom de la faculté
// ===============================

$nom_fac = "Toutes les facultés";

if($id_fac != ""){

    $reqFac = $bdd->prepare("
        SELECT nom_fac
        FROM faculte
        WHERE id_fac = ?
    ");

    $reqFac->execute([$id_fac]);

    $fac = $reqFac->fetch();

    if($fac){
        $nom_fac = $fac["nom_fac"];
    }
}


// ===============================
// Nom du niveau
// ===============================

$nom_niveau = "Tous les niveaux";

if($id_niveau != ""){

    $reqNiv = $bdd->prepare("
        SELECT nom_niveau
        FROM niveau_etude
        WHERE id_niveau = ?
    ");

    $reqNiv->execute([$id_niveau]);

    $niv = $reqNiv->fetch();

    if($niv){
        $nom_niveau = $niv["nom_niveau"];
    }
}


// ===============================
// Requête étudiants
// ===============================

$sql = "
    SELECT
        e.nom_etu,
        e.prenom_etu,
        e.login,
        f.nom_fac,
        n.nom_niveau

    FROM etudiant e

    JOIN faculte f
        ON e.id_fac = f.id_fac

    JOIN niveau_etude n
        ON e.id_niveau = n.id_niveau

    WHERE 1=1
";

$params = [];


// Faculté

if($id_fac != ""){

    $sql .= " AND e.id_fac = ?";
    $params[] = $id_fac;

}


// Niveau

if($id_niveau != ""){

    $sql .= " AND e.id_niveau = ?";
    $params[] = $id_niveau;

}


// Tri

$sql .= "
    ORDER BY
        f.nom_fac ASC,
        n.nom_niveau ASC,
        e.nom_etu ASC,
        e.prenom_etu ASC
";


$req = $bdd->prepare($sql);
$req->execute($params);

$etudiants = $req->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>

<html lang="fr">

<head>

<meta charset="UTF-8">

<title>Liste des étudiants</title>


<style>

*{
    box-sizing:border-box;
}


body{

    margin:0;

    font-family:Arial, sans-serif;

    background:#eeeeee;

    color:#222;

}


/* =========================
   PAGE
========================= */

.page{

    width:90%;

    max-width:1100px;

    margin:30px auto;

    background:white;

    padding:40px;

    box-shadow:0 2px 10px rgba(0,0,0,0.15);

}


/* =========================
   TITRE
========================= */

h1{

    text-align:center;

    color:#003366;

    margin:0 0 15px 0;

    font-size:25px;

}


/* =========================
   INFORMATIONS
========================= */

.informations{

    text-align:center;

    margin-bottom:30px;

    font-size:15px;

    line-height:1.8;

}


.informations strong{

    color:#003366;

}


/* =========================
   TABLEAU
========================= */

table{

    width:100%;

    border-collapse:collapse;

}


th{

    background:#003366;

    color:white;

    padding:12px;

    border:1px solid #003366;

    font-size:14px;

}


td{

    padding:10px;

    border:1px solid #cccccc;

    text-align:center;

    font-size:14px;

}


tr:nth-child(even){

    background:#f5f7f9;

}


/* =========================
   MESSAGE
========================= */

.message{

    text-align:center;

    padding:20px;

    color:#777;

}


/* =========================
   BOUTON
========================= */

.actions{

    text-align:center;

    margin-bottom:20px;

}


.btn-imprimer{

    background:#003366;

    color:white;

    border:none;

    padding:11px 20px;

    border-radius:5px;

    cursor:pointer;

    font-size:15px;

}


.btn-imprimer:hover{

    background:#0055aa;

}


/* =========================
   IMPRESSION
========================= */

@media print{

    body{

        background:white;

    }


    .page{

        width:100%;

        max-width:none;

        margin:0;

        padding:10px;

        box-shadow:none;

    }


    .actions{

        display:none;

    }


    h1{

        color:#000;

    }


    th{

        background:#003366 !important;

        color:white !important;

        -webkit-print-color-adjust:exact;

        print-color-adjust:exact;

    }

}

</style>

</head>


<body>


<div class="page">


<!-- =========================
     BOUTON
========================= -->

<div class="actions">

<button
    class="btn-imprimer"
    onclick="window.print()"
>
     Enregistrer en PDF
</button>

</div>


<!-- =========================
     TITRE
========================= -->

<h1>

LISTE DES ÉTUDIANTS

</h1>


<!-- =========================
     INFORMATIONS
========================= -->

<div class="informations">

<div>
<strong>Faculté :</strong>
<?= htmlspecialchars($nom_fac) ?>
</div>


<div>
<strong>Niveau :</strong>
<?= htmlspecialchars($nom_niveau) ?>
</div>


<div>
<strong>Généré le :</strong>
<?= date("d/m/Y") ?>
</div>

</div>


<!-- =========================
     TABLEAU
========================= -->

<?php if(count($etudiants) > 0): ?>


<table>

<thead>

<tr>

<th>N°</th>

<th>Nom</th>

<th>Prénom</th>

<th>Login</th>

<th>Faculté</th>

<th>Niveau</th>

</tr>

</thead>


<tbody>

<?php $numero = 1; ?>


<?php foreach($etudiants as $e): ?>

<tr>

<td>
<?= $numero++ ?>
</td>

<td>
<?= htmlspecialchars($e["nom_etu"]) ?>
</td>

<td>
<?= htmlspecialchars($e["prenom_etu"]) ?>
</td>

<td>
<?= htmlspecialchars($e["login"]) ?>
</td>

<td>
<?= htmlspecialchars($e["nom_fac"]) ?>
</td>

<td>
<?= htmlspecialchars($e["nom_niveau"]) ?>
</td>

</tr>

<?php endforeach; ?>


</tbody>

</table>


<?php else: ?>


<div class="message">

Aucun étudiant trouvé pour les critères sélectionnés.

</div>


<?php endif; ?>


</div>


</body>

</html>