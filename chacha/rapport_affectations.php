<?php

session_start();

if(!isset($_SESSION["id_admin"]) || $_SESSION["role"] != "admin"){
    header("Location: connexion.php");
    exit();
}

include("connexionDB.php");
include("menu_principal.php");


// =====================================================
// 1. RÉCUPÉRER LES FACULTÉS
// =====================================================

$facultes = $bdd->query("
    SELECT id_fac, nom_fac
    FROM faculte
    ORDER BY nom_fac ASC
")->fetchAll(PDO::FETCH_ASSOC);


// =====================================================
// 2. RÉCUPÉRER LES NIVEAUX
// =====================================================

$niveaux = $bdd->query("
    SELECT id_niveau, nom_niveau
    FROM niveau_etude
    ORDER BY nom_niveau ASC
")->fetchAll(PDO::FETCH_ASSOC);


// =====================================================
// 3. RÉCUPÉRER LES FILTRES
// =====================================================

$id_fac = $_GET["id_fac"] ?? "";
$id_niveau = $_GET["id_niveau"] ?? "";


// =====================================================
// 4. TABLEAU QUI CONTIENDRA LES AFFECTATIONS
// =====================================================

$affectations = [];


// =====================================================
// 5. SI UNE FACULTÉ ET UN NIVEAU SONT SÉLECTIONNÉS
// =====================================================

if($id_fac != "" && $id_niveau != ""){


    $sql = "

        SELECT

            a.id_affectation,

            e.id_etu,
            e.nom_etu,
            e.prenom_etu,

            f.nom_fac,

            n.nom_niveau,

            c.nom_cours,

            s.id_salle,
            s.nom_salle,

            sv.id_surv,
            sv.nom_surv,
            sv.prenom_surv,

            a.date_exam,
            a.heure_debut,
            a.heure_fin

        FROM affectation a


        INNER JOIN etudiant e
            ON a.id_etu = e.id_etu


        INNER JOIN faculte f
            ON e.id_fac = f.id_fac


        INNER JOIN niveau_etude n
            ON e.id_niveau = n.id_niveau


        INNER JOIN cours c
            ON a.id_cours = c.id_cours


        INNER JOIN salle s
            ON a.id_salle = s.id_salle


        INNER JOIN surveillant sv
            ON a.id_surv = sv.id_surv


        WHERE e.id_fac = ?
        AND e.id_niveau = ?


        ORDER BY

            s.nom_salle ASC,
            a.date_exam ASC,
            a.heure_debut ASC,
            c.nom_cours ASC,
            e.nom_etu ASC,
            e.prenom_etu ASC

    ";


    $req = $bdd->prepare($sql);

    $req->execute([
        $id_fac,
        $id_niveau
    ]);


    $affectations = $req->fetchAll(PDO::FETCH_ASSOC);

}


// =====================================================
// 6. NOM DE LA FACULTÉ SÉLECTIONNÉE
// =====================================================

$nom_fac = "";

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


// =====================================================
// 7. NOM DU NIVEAU SÉLECTIONNÉ
// =====================================================

$nom_niveau = "";

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


// =====================================================
// 8. REGROUPER LES ÉTUDIANTS PAR SALLE
// =====================================================

$salles = [];


foreach($affectations as $a){

    $cle = $a["id_salle"];

    if(!isset($salles[$cle])){

        $salles[$cle] = [

            "nom_salle" => $a["nom_salle"],

            "nom_cours" => $a["nom_cours"],

            "nom_surv" =>
                $a["nom_surv"]." ".$a["prenom_surv"],

            "date_exam" =>
                $a["date_exam"],

            "heure_debut" =>
                $a["heure_debut"],

            "heure_fin" =>
                $a["heure_fin"],

            "etudiants" => []

        ];

    }


    $salles[$cle]["etudiants"][] = [

        "nom" => $a["nom_etu"],

        "prenom" => $a["prenom_etu"]

    ];

}

?>

<!DOCTYPE html>

<html lang="fr">

<head>

<meta charset="UTF-8">

<title>Rapport des affectations</title>


<style>

/* =====================================================
   STYLE GÉNÉRAL
===================================================== */

*{
    box-sizing:border-box;
}


body{

    margin:0;

    font-family:Arial, sans-serif;

    background:#f4f6f9;

    color:#222;

}


/* =====================================================
   CONTENEUR
===================================================== */

.container{

    width:92%;

    max-width:1200px;

    margin:30px auto;

}


/* =====================================================
   TITRE
===================================================== */

h1{

    text-align:center;

    color:#003366;

    margin-bottom:25px;

}


h2{

    color:#003366;

    margin-top:0;

}


/* =====================================================
   FORMULAIRE
===================================================== */

.filtres{

    background:white;

    padding:25px;

    border-radius:10px;

    box-shadow:0 2px 8px rgba(0,0,0,0.1);

    display:flex;

    justify-content:center;

    align-items:end;

    gap:20px;

    margin-bottom:30px;

}


.champ{

    display:flex;

    flex-direction:column;

}


.champ label{

    font-weight:bold;

    margin-bottom:7px;

    color:#003366;

}


.champ select{

    width:250px;

    padding:11px;

    border:1px solid #ccc;

    border-radius:5px;

    background:white;

    font-size:14px;

}


.btn-afficher{

    padding:11px 22px;

    background:#003366;

    color:white;

    border:none;

    border-radius:5px;

    cursor:pointer;

    font-size:14px;

}


.btn-afficher:hover{

    background:#0055aa;

}


/* =====================================================
   INFORMATIONS DU RAPPORT
===================================================== */

.informations{

    background:white;

    padding:20px;

    border-radius:10px;

    box-shadow:0 2px 8px rgba(0,0,0,0.1);

    margin-bottom:25px;

    text-align:center;

}


.informations strong{

    color:#003366;

}


/* =====================================================
   BLOC SALLE
===================================================== */

.salle{

    background:white;

    margin-bottom:30px;

    border-radius:10px;

    box-shadow:0 2px 8px rgba(0,0,0,0.1);

    overflow:hidden;

}


/* =====================================================
   EN-TÊTE SALLE
===================================================== */

.salle-header{

    background:#003366;

    color:white;

    padding:18px;

}


.salle-header h2{

    color:white;

    margin:0 0 10px 0;

}


.details{

    display:flex;

    flex-wrap:wrap;

    gap:25px;

    font-size:14px;

}


.details span{

    display:inline-block;

}


/* =====================================================
   CONTENU SALLE
===================================================== */

.salle-contenu{

    padding:20px;

}


.nombre{

    margin-bottom:15px;

    color:#003366;

    font-weight:bold;

}


/* =====================================================
   TABLEAU ÉTUDIANTS
===================================================== */

table{

    width:100%;

    border-collapse:collapse;

}


th{

    background:#e9eef3;

    color:#003366;

    padding:11px;

    border:1px solid #ccc;

}


td{

    padding:10px;

    border:1px solid #ddd;

    text-align:center;

}


tr:nth-child(even){

    background:#f8f9fa;

}


/* =====================================================
   MESSAGE
===================================================== */

.message{

    background:white;

    padding:30px;

    text-align:center;

    border-radius:10px;

    color:#777;

    box-shadow:0 2px 8px rgba(0,0,0,0.1);

}


/* =====================================================
   RESPONSIVE
===================================================== */

@media(max-width:700px){

    .filtres{

        flex-direction:column;

        align-items:stretch;

    }


    .champ select{

        width:100%;

    }


    .btn-afficher{

        width:100%;

    }


    .details{

        flex-direction:column;

        gap:8px;

    }

}

</style>

</head>


<body>


<div class="container">


<!-- =====================================================
     TITRE
===================================================== -->

<h1>

RAPPORT DES AFFECTATIONS D'EXAMENS

</h1>


<!-- =====================================================
     FORMULAIRE
===================================================== -->

<form method="GET" class="filtres">


<div class="champ">

<label for="id_fac">

Faculté

</label>


<select
    name="id_fac"
    id="id_fac"
    required
>

<option value="">

-- Choisir une faculté --

</option>


<?php foreach($facultes as $fac): ?>

<option

    value="<?= htmlspecialchars($fac["id_fac"]) ?>"

    <?= ($id_fac == $fac["id_fac"]) ? "selected" : "" ?>

>

<?= htmlspecialchars($fac["nom_fac"]) ?>

</option>

<?php endforeach; ?>


</select>

</div>



<div class="champ">

<label for="id_niveau">

Niveau

</label>


<select
    name="id_niveau"
    id="id_niveau"
    required
>

<option value="">

-- Choisir un niveau --

</option>


<?php foreach($niveaux as $niv): ?>

<option

    value="<?= htmlspecialchars($niv["id_niveau"]) ?>"

    <?= ($id_niveau == $niv["id_niveau"]) ? "selected" : "" ?>

>

<?= htmlspecialchars($niv["nom_niveau"]) ?>

</option>

<?php endforeach; ?>


</select>

</div>



<button
    type="submit"
    class="btn-afficher"
>

Afficher les affectations

</button>


</form>


<?php if($id_fac != "" && $id_niveau != ""): ?>


<!-- =====================================================
     INFORMATIONS SÉLECTION
===================================================== -->

<div class="informations">

<strong>Faculté :</strong>

<?= htmlspecialchars($nom_fac) ?>

&nbsp;&nbsp;&nbsp; | &nbsp;&nbsp;&nbsp;

<strong>Niveau :</strong>

<?= htmlspecialchars($nom_niveau) ?>

<br>

<br>

<strong>Rapport généré le :</strong>

<?= date("d/m/Y à H:i") ?>

</div>


<?php if(count($salles) > 0): ?>


<!-- =====================================================
     AFFICHAGE DES SALLES
===================================================== -->

<?php foreach($salles as $salle): ?>


<div class="salle">


<!-- EN-TÊTE -->

<div class="salle-header">


<h2>

Salle :

<?= htmlspecialchars($salle["nom_salle"]) ?>

</h2>


<div class="details">


<span>

<strong>Cours :</strong>

<?= htmlspecialchars($salle["nom_cours"]) ?>

</span>


<span>

<strong>Surveillant :</strong>

<?= htmlspecialchars($salle["nom_surv"]) ?>

</span>


<span>

<strong>Date :</strong>

<?= htmlspecialchars($salle["date_exam"]) ?>

</span>


<span>

<strong>Horaire :</strong>

<?= htmlspecialchars($salle["heure_debut"]) ?>

-

<?= htmlspecialchars($salle["heure_fin"]) ?>

</span>


</div>


</div>


<!-- CONTENU -->

<div class="salle-contenu">


<div class="nombre">

Nombre d'étudiants :

<?= count($salle["etudiants"]) ?>

</div>


<table>


<thead>

<tr>

<th>N°</th>

<th>Nom</th>

<th>Prénom</th>

</tr>

</thead>


<tbody>


<?php $numero = 1; ?>


<?php foreach($salle["etudiants"] as $etu): ?>


<tr>

<td>

<?= $numero++ ?>

</td>


<td>

<?= htmlspecialchars($etu["nom"]) ?>

</td>


<td>

<?= htmlspecialchars($etu["prenom"]) ?>

</td>

</tr>


<?php endforeach; ?>


</tbody>


</table>


</div>


</div>


<?php endforeach; ?>


<?php else: ?>


<div class="message">

Aucune affectation trouvée pour cette faculté et ce niveau.

</div>


<?php endif; ?>


<?php else: ?>


<div class="message">

Veuillez sélectionner une <strong>faculté</strong> et un <strong>niveau</strong> pour afficher les salles affectées.

</div>


<?php endif; ?>


</div>


</body>

</html>