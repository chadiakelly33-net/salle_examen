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
// RÉCUPÉRER LES NOMS DES FILTRES
// ===============================

$nom_fac = "Toutes les facultés";
$nom_niveau = "Tous les niveaux";
$nom_cours = "Tous les cours";
$nom_salle = "Toutes les salles";
$nom_surv = "Tous les surveillants";
$nom_date = "Toutes les dates";


// Faculté
if($id_fac != ""){

    $req = $bdd->prepare("
        SELECT nom_fac
        FROM faculte
        WHERE id_fac=?
    ");

    $req->execute([$id_fac]);

    $result = $req->fetch();

    if($result){
        $nom_fac = $result["nom_fac"];
    }
}


// Niveau
if($id_niveau != ""){

    $req = $bdd->prepare("
        SELECT nom_niveau
        FROM niveau_etude
        WHERE id_niveau=?
    ");

    $req->execute([$id_niveau]);

    $result = $req->fetch();

    if($result){
        $nom_niveau = $result["nom_niveau"];
    }
}


// Cours
if($id_cours != ""){

    $req = $bdd->prepare("
        SELECT nom_cours
        FROM cours
        WHERE id_cours=?
    ");

    $req->execute([$id_cours]);

    $result = $req->fetch();

    if($result){
        $nom_cours = $result["nom_cours"];
    }
}


// Salle
if($id_salle != ""){

    $req = $bdd->prepare("
        SELECT nom_salle
        FROM salle
        WHERE id_salle=?
    ");

    $req->execute([$id_salle]);

    $result = $req->fetch();

    if($result){
        $nom_salle = $result["nom_salle"];
    }
}


// Surveillant
if($id_surv != ""){

    $req = $bdd->prepare("
        SELECT nom_surv, prenom_surv
        FROM surveillant
        WHERE id_surv=?
    ");

    $req->execute([$id_surv]);

    $result = $req->fetch();

    if($result){
        $nom_surv = $result["nom_surv"]." ".$result["prenom_surv"];
    }
}


// Date
if($date_exam != ""){
    $nom_date = $date_exam;
}


// ===============================
// REQUÊTE AFFECTATIONS
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


// Cours
if($id_cours != ""){
    $sql .= " AND a.id_cours = ?";
    $params[] = $id_cours;
}


// Salle
if($id_salle != ""){
    $sql .= " AND a.id_salle = ?";
    $params[] = $id_salle;
}


// Surveillant
if($id_surv != ""){
    $sql .= " AND a.id_surv = ?";
    $params[] = $id_surv;
}


// Date
if($date_exam != ""){
    $sql .= " AND a.date_exam = ?";
    $params[] = $date_exam;
}


// TRI
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


// ===============================
// TÉLÉCHARGEMENT CSV
// ===============================

header("Content-Type: text/csv; charset=UTF-8");

header(
    "Content-Disposition: attachment; filename=rapport_affectations.csv"
);


// ===============================
// OUVERTURE
// ===============================

$output = fopen("php://output", "w");


// BOM UTF-8
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));


// ===============================
// TITRE
// ===============================

fputcsv(
    $output,
    ["LISTE DES AFFECTATIONS D'EXAMENS"],
    ";"
);


// ===============================
// FILTRES
// ===============================

fputcsv(
    $output,
    ["Faculté", $nom_fac],
    ";"
);

fputcsv(
    $output,
    ["Niveau", $nom_niveau],
    ";"
);

fputcsv(
    $output,
    ["Cours", $nom_cours],
    ";"
);

fputcsv(
    $output,
    ["Salle", $nom_salle],
    ";"
);

fputcsv(
    $output,
    ["Surveillant", $nom_surv],
    ";"
);

fputcsv(
    $output,
    ["Date d'examen", $nom_date],
    ";"
);


// Ligne vide
fputcsv($output, [], ";");


// ===============================
// EN-TÊTES DU TABLEAU
// ===============================

fputcsv(
    $output,
    [
        "N°",
        "Nom",
        "Prénom",
        "Faculté",
        "Niveau",
        "Cours",
        "Salle",
        "Surveillant",
        "Date",
        "Heure début",
        "Heure fin"
    ],
    ";"
);


// ===============================
// DONNÉES
// ===============================

$numero = 1;

foreach($affectations as $a){

    fputcsv(
        $output,
        [
            $numero++,
            $a["nom_etu"],
            $a["prenom_etu"],
            $a["nom_fac"],
            $a["nom_niveau"],
            $a["nom_cours"],
            $a["nom_salle"],
            $a["nom_surv"]." ".$a["prenom_surv"],
            $a["date_exam"],
            $a["heure_debut"],
            $a["heure_fin"]
        ],
        ";"
    );

}


fclose($output);

exit();

?>