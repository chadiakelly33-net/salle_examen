<?php
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
    "Consultation des affectations d'examens"
);

error_reporting(E_ALL);
ini_set('display_errors', 1);

/* =========================
   RECHERCHE (OPTIONNEL)
========================= */
$search = $_GET['search'] ?? '';

$sql = "
SELECT
    a.id_affectation,
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
JOIN etudiant e ON a.id_etu = e.id_etu
JOIN faculte f ON e.id_fac = f.id_fac
JOIN niveau_etude n ON e.id_niveau = n.id_niveau
JOIN cours c ON a.id_cours = c.id_cours
JOIN salle s ON a.id_salle = s.id_salle
JOIN surveillant sv ON a.id_surv = sv.id_surv
WHERE
(
    e.nom_etu LIKE :search
    OR c.nom_cours LIKE :search
    OR s.nom_salle LIKE :search
)
AND a.date_exam >= CURDATE()
ORDER BY
    a.date_exam ASC,
    a.heure_debut ASC
";
$req = $bdd->prepare($sql);
$req->execute([
    "search" => "%$search%"
]);

$affectations = $req->fetchAll();
?>

<style>
body{
    font-family: Arial;
    background:#f4f6f9;
    margin:0;
}

/* container */
.container{
    width:95%;
    margin:30px auto;
    background:white;
    padding:20px;
    border-radius:10px;
    box-shadow:0 0 10px rgba(0,0,0,0.1);
}

/* titre */
h2{
    text-align:center;
    color:#003366;
}

/* search */
.search-box{
    text-align:center;
    margin-bottom:15px;
}

.search-box input{
    width:300px;
    padding:10px;
    border:1px solid #ccc;
    border-radius:5px;
}

/* table */
table{
    width:100%;
    border-collapse:collapse;
}

th, td{
    padding:10px;
    border:1px solid #ddd;
    text-align:center;
    font-size:14px;
}

th{
    background:#003366;
    color:white;
}

tr:nth-child(even){
    background:#f2f2f2;
}

o

/* badge style */
.badge{
    padding:4px 8px;
    border-radius:5px;
    background:#007bff;
    color:white;
}
</style>

<div class="container">

<h2>Gestion des Affectations d'Examens</h2>

<!-- SEARCH -->
<!--<form method="GET" class="search-box">
    <input type="text" name="search" placeholder="Rechercher étudiant, cours ou salle..." value="<?= $search ?>">
    <button type="submit">🔍</button>
</form>-->

<!-- TABLE -->
<table>

<tr>
    <th>Étudiant</th>
    <th>Faculté</th>
    <th>Niveau</th>
    <th>Cours</th>
    <th>Salle</th>
    <th>Surveillant</th>
    <th>Date</th>
    <th>Début</th>
    <th>Fin</th>
	<th>Action</th>
</tr>

<?php if(count($affectations) > 0){ ?>

<?php foreach($affectations as $a){ ?>
<tr>
    <td><?= $a["nom_etu"]." ".$a["prenom_etu"] ?></td>
    <td><span class="badge"><?= $a["nom_fac"] ?></span></td>
    <td><?= $a["nom_niveau"] ?></td>
    <td><?= $a["nom_cours"] ?></td>
    <td><?= $a["nom_salle"] ?></td>
    <td><?= $a["nom_surv"]." ".$a["prenom_surv"] ?></td>
    <td><?= $a["date_exam"] ?></td>
    <td><?= $a["heure_debut"] ?></td>
    <td><?= $a["heure_fin"] ?></td>
	<td>
	<a href="modifier_affectation.php?id=<?= $a["id_affectation"] ?>"
   class="btn-modifier">

   Modifier
   </a>
   
   <a 
   href="supprimer_affectation.php?id=<?= $a["id_affectation"] ?>"
   onclick="return confirm('Voulez-vous vraiment supprimer cette affectation ?');">
   
   Supprimer
   </a>
   </td>
</tr>
<?php } ?>

<?php } else { ?>
<tr>
    <td colspan="9">Aucune affectation trouvée</td>
</tr>
<?php } ?>

</table>

</div>