<?php
session_start();
if(!isset($_SESSION["id_admin"]) || $_SESSION["role"] != "admin"){
    header("Location: connexion.php");
    exit();
}
o
include("connexionDB.php");
include("menu_principal.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

/* =========================
   TRAITEMENT INSERTION
========================= */
if(isset($_POST["ok"])){

    $id_cours = $_POST["id_cours"];
    $id_salle = $_POST["id_salle"];
    $id_surv  = $_POST["id_surv"];
    $date_exam = $_POST["date_exam"];
    $h_debut = $_POST["heure_debut"];
    $h_fin = $_POST["heure_fin"];

    /* vérification simple (éviter doublon même salle même date) */
    $check = $bdd->prepare("
        SELECT id_affectation FROM affectation
        WHERE id_salle = ? AND date_exam = ? AND heure_debut = ?
    ");
    $check->execute([$id_salle, $date_exam, $h_debut]);

    if($check->rowCount() == 0){

        $insert = $bdd->prepare("
            INSERT INTO affectation
            (id_cours, id_salle, id_surv, date_exam, heure_debut, heure_fin)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $insert->execute([
            $id_cours,
            $id_salle,
            $id_surv,
            $date_exam,
            $h_debut,
            $h_fin
        ]);

        header("Location: afficher_affectation_admin.php");
        exit;
    } else {
        echo "<p style='color:red;text-align:center;'>⚠ Salle déjà occupée à cette heure</p>";
    }
}
?>

<!-- =========================
        STYLE
========================= -->
<style>
body{
    font-family:Arial;
    background:#f4f6f9;
}

.container{
    width:500px;
    margin:40px auto;
    background:white;
    padding:25px;
    border-radius:10px;
    box-shadow:0 2px 10px rgba(0,0,0,0.1);
}

h2{
    text-align:center;
    color:#003366;
}

label{
    display:block;
    margin-top:10px;
    font-weight:bold;
}

select, input{
    width:100%;
    padding:10px;
    margin-top:5px;
    margin-bottom:10px;
    border:1px solid #ccc;
    border-radius:6px;
}

button{
    width:100%;
    padding:12px;
    background:#003366;
    color:white;
    border:none;
    border-radius:6px;
    font-size:16px;
    cursor:pointer;
}

button:hover{
    background:#0055aa;
}
</style>

<!-- =========================
        FORMULAIRE
========================= -->
<div class="container">

<h2>Créer une affectation d'examen</h2>

<form method="POST">

<!-- COURS -->
<label>Cours</label>
<select name="id_cours" required>
    <option value="">-- choisir --</option>
    <?php
    $c = $bdd->query("SELECT * FROM cours");
    while($row = $c->fetch()){
        echo "<option value='{$row['id_cours']}'>{$row['nom_cours']}</option>";
    }
    ?>
</select>

<!-- SALLE -->
<label>Salle</label>
<select name="id_salle" required>
    <option value="">-- choisir --</option>
    <?php
    $s = $bdd->query("SELECT * FROM salle");
    while($row = $s->fetch()){
        echo "<option value='{$row['id_salle']}'>{$row['nom_salle']}</option>";
    }
    ?>
</select>

<!-- SURVEILLANT -->
<label>Surveillant</label>
<select name="id_surv" required>
    <option value="">-- choisir --</option>
    <?php
    $sv = $bdd->query("SELECT * FROM surveillant");
    while($row = $sv->fetch()){
        echo "<option value='{$row['id_surv']}'>
                {$row['nom_surv']} {$row['prenom_surv']}
              </option>";
    }
    ?>
</select>

<!-- DATE -->
<label>Date examen</label>
<input type="date" name="date_exam" required>

<!-- HEURE -->
<label>Heure début</label>
<input type="time" name="heure_debut" required>

<label>Heure fin</label>
<input type="time" name="heure_fin" required>

<button type="submit" name="ok">Créer l'affectation</button>

</form>

</div>