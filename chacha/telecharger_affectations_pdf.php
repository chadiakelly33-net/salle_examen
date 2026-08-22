<?php

session_start();

if(!isset($_SESSION["id_admin"]) || $_SESSION["role"] != "admin"){
    header("Location: connexion.php");
    exit();
}

include("connexionDB.php");


// ===============================
// RÉCUPÉRER LES FILTRES
// ===============================

$id_fac = $_GET["id_fac"] ?? "";
$id_niveau = $_GET["id_niveau"] ?? "";
$id_cours = $_GET["id_cours"] ?? "";
$id_salle = $_GET["id_salle"] ?? "";
$id_surv = $_GET["id_surv"] ?? "";
$date_exam = $_GET["date_exam"] ?? "";


// ===============================
// NOMS DES FILTRES
// ===============================

$nom_fac = "Toutes les facultés";
$nom_niveau = "Tous les niveaux";
$nom_cours = "Tous les cours";
$nom_salle = "Toutes les salles";
$nom_surv = "Tous les surveillants";
$nom_date = "Toutes les dates";


// ===============================
// FACULTÉ
// ===============================

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
// NIVEAU
// ===============================

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
// COURS
// ===============================

if($id_cours != ""){

    $reqCours = $bdd->prepare("
        SELECT nom_cours
        FROM cours
        WHERE id_cours = ?
    ");

    $reqCours->execute([$id_cours]);

    $cours = $reqCours->fetch();

    if($cours){
        $nom_cours = $cours["nom_cours"];
    }
}


// ===============================
// SALLE
// ===============================

if($id_salle != ""){

    $reqSalle = $bdd->prepare("
        SELECT nom_salle
        FROM salle
        WHERE id_salle = ?
    ");

    $reqSalle->execute([$id_salle]);

    $salle = $reqSalle->fetch();

    if($salle){
        $nom_salle = $salle["nom_salle"];
    }
}


// ===============================
// SURVEILLANT
// ===============================

if($id_surv != ""){

    $reqSurv = $bdd->prepare("
        SELECT nom_surv, prenom_surv
        FROM surveillant
        WHERE id_surv = ?
    ");

    $reqSurv->execute([$id_surv]);

    $surv = $reqSurv->fetch();

    if($surv){

        $nom_surv =
            $surv["nom_surv"]." ".$surv["prenom_surv"];

    }
}


// ===============================
// DATE
// ===============================

if($date_exam != ""){

    $nom_date = date("d/m/Y", strtotime($date_exam));

}


// ===============================
// REQUÊTE DES AFFECTATIONS
// ===============================

$sql = "

SELECT

    e.nom_etu,
    e.prenom_etu,

    f.nom_fac,

    n.nom_niveau,

    c.nom_cours,

    s.nom_salle,

    sv.nom_surv,
    sv.prenom_surv,

    a.date_exam,
    a.heure_debut,
    a.heure_fin

FROM affectation a

JOIN etudiant e
    ON a.id_etu = e.id_etu

JOIN faculte f
    ON e.id_fac = f.id_fac

JOIN niveau_etude n
    ON e.id_niveau = n.id_niveau

JOIN cours c
    ON a.id_cours = c.id_cours

JOIN salle s
    ON a.id_salle = s.id_salle

JOIN surveillant sv
    ON a.id_surv = sv.id_surv

WHERE 1=1

";

$params = [];


// ===============================
// FILTRE FACULTÉ
// ===============================

if($id_fac != ""){

    $sql .= " AND e.id_fac = ?";

    $params[] = $id_fac;

}


// ===============================
// FILTRE NIVEAU
// ===============================

if($id_niveau != ""){

    $sql .= " AND e.id_niveau = ?";

    $params[] = $id_niveau;

}


// ===============================
// FILTRE COURS
// ===============================

if($id_cours != ""){

    $sql .= " AND a.id_cours = ?";

    $params[] = $id_cours;

}


// ===============================
// FILTRE SALLE
// ===============================

if($id_salle != ""){

    $sql .= " AND a.id_salle = ?";

    $params[] = $id_salle;

}


// ===============================
// FILTRE SURVEILLANT
// ===============================

if($id_surv != ""){

    $sql .= " AND a.id_surv = ?";

    $params[] = $id_surv;

}


// ===============================
// FILTRE DATE
// ===============================

if($date_exam != ""){

    $sql .= " AND a.date_exam = ?";

    $params[] = $date_exam;

}


// ===============================
// TRI
// ===============================

$sql .= "

ORDER BY

    a.date_exam ASC,
    a.heure_debut ASC,
    e.nom_etu ASC,
    e.prenom_etu ASC

";


$req = $bdd->prepare($sql);

$req->execute($params);

$affectations = $req->fetchAll(PDO::FETCH_ASSOC);

?>


<!DOCTYPE html>

<html lang="fr">

<head>

<meta charset="UTF-8">

<title>Liste des affectations</title>


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

    width:95%;

    max-width:1300px;

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

    padding:10px;

    border:1px solid #003366;

    font-size:13px;

}


td{

    padding:8px;

    border:1px solid #cccccc;

    text-align:center;

    font-size:12px;

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

LISTE DES AFFECTATIONS D'EXAMENS

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

<strong>Cours :</strong>

<?= htmlspecialchars($nom_cours) ?>

</div>


<div>

<strong>Salle :</strong>

<?= htmlspecialchars($nom_salle) ?>

</div>


<div>

<strong>Surveillant :</strong>

<?= htmlspecialchars($nom_surv) ?>

</div>


<div>

<strong>Date d'examen :</strong>

<?= htmlspecialchars($nom_date) ?>

</div>


<div>

<strong>Généré le :</strong>

<?= date("d/m/Y") ?>

</div>


</div>


<!-- =========================
     TABLEAU
========================= -->

<?php if(count($affectations) > 0): ?>


<table>

<thead>

<tr>

<th>N°</th>

<th>Nom</th>

<th>Prénom</th>

<th>Faculté</th>

<th>Niveau</th>

<th>Cours</th>

<th>Salle</th>

<th>Surveillant</th>

<th>Date</th>

<th>Début</th>

<th>Fin</th>

</tr>

</thead>


<tbody>


<?php $numero = 1; ?>


<?php foreach($affectations as $a): ?>


<tr>

<td>
<?= $numero++ ?>
</td>

<td>
<?= htmlspecialchars($a["nom_etu"]) ?>
</td>

<td>
<?= htmlspecialchars($a["prenom_etu"]) ?>
</td>

<td>
<?= htmlspecialchars($a["nom_fac"]) ?>
</td>

<td>
<?= htmlspecialchars($a["nom_niveau"]) ?>
</td>

<td>
<?= htmlspecialchars($a["nom_cours"]) ?>
</td>

<td>
<?= htmlspecialchars($a["nom_salle"]) ?>
</td>

<td>
<?= htmlspecialchars(
    $a["nom_surv"]." ".$a["prenom_surv"]
) ?>
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


</tbody>

</table>


<?php else: ?>


<div class="message">

Aucune affectation trouvée pour les critères sélectionnés.

</div>


<?php endif; ?>


</div>


</body>

</html>