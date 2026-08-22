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
// Récupérer le nom de la faculté
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
// Récupérer le nom du niveau
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


// ===============================
// Filtre faculté
// ===============================

if($id_fac != ""){

    $sql .= " AND e.id_fac = ?";

    $params[] = $id_fac;

}


// ===============================
// Filtre niveau
// ===============================

if($id_niveau != ""){

    $sql .= " AND e.id_niveau = ?";

    $params[] = $id_niveau;

}


// ===============================
// Tri
// ===============================

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


// ===============================
// Nom du fichier
// ===============================

$filename = "rapport_etudiants.xls";


// ===============================
// En-têtes pour Excel
// ===============================

header("Content-Type: application/vnd.ms-excel; charset=UTF-8");

header(
    "Content-Disposition: attachment; filename=\"$filename\""
);

header("Pragma: no-cache");

header("Expires: 0");


// ===============================
// Début du fichier Excel
// ===============================

echo '<html>';

echo '<head>';

echo '<meta charset="UTF-8">';

echo '<style>

body{
    font-family: Arial;
}

.titre{
    font-size:20px;
    font-weight:bold;
    text-align:center;
}

.information{
    font-size:14px;
}

table{
    border-collapse:collapse;
    width:100%;
}

th{
    background:#003366;
    color:white;
    font-weight:bold;
    border:1px solid #000;
    padding:8px;
}

td{
    border:1px solid #000;
    padding:8px;
}

</style>';

echo '</head>';

echo '<body>';


// ===============================
// TITRE
// ===============================

echo '<table>';

echo '<tr>';

echo '<td colspan="6" class="titre">';

echo 'RAPPORT DES ÉTUDIANTS';

echo '</td>';

echo '</tr>';


// ===============================
// Informations
// ===============================

echo '<tr>';

echo '<td colspan="6" class="information">';

echo '<b>Faculté :</b> ' . htmlspecialchars($nom_fac);

echo '</td>';

echo '</tr>';


echo '<tr>';

echo '<td colspan="6" class="information">';

echo '<b>Niveau :</b> ' . htmlspecialchars($nom_niveau);

echo '</td>';

echo '</tr>';

echo '</table>';

echo '<br>';


// ===============================
// TABLEAU DES ÉTUDIANTS
// ===============================

echo '<table>';


// En-têtes

echo '<tr>';

echo '<th>N°</th>';

echo '<th>Nom</th>';

echo '<th>Prénom</th>';

echo '<th>Login</th>';

echo '<th>Faculté</th>';

echo '<th>Niveau</th>';

echo '</tr>';


// ===============================
// Données
// ===============================

$numero = 1;


foreach($etudiants as $e){

    echo '<tr>';

    echo '<td>' . $numero++ . '</td>';

    echo '<td>' . htmlspecialchars($e["nom_etu"]) . '</td>';

    echo '<td>' . htmlspecialchars($e["prenom_etu"]) . '</td>';

    echo '<td>' . htmlspecialchars($e["login"]) . '</td>';

    echo '<td>' . htmlspecialchars($e["nom_fac"]) . '</td>';

    echo '<td>' . htmlspecialchars($e["nom_niveau"]) . '</td>';

    echo '</tr>';

}


echo '</table>';


// ===============================
// Fin
// ===============================

echo '</body>';

echo '</html>';

exit();

?>