<?php
session_start();
if(!isset($_SESSION["id_admin"]) || $_SESSION["role"] != "admin"){
    header("Location: connexion.php");
    exit();
}

include("connexionDB.php");
include("journal.php");

journal(
    $bdd,
    $_SESSION["id_admin"],
    $_SESSION["nom_admin"],
    "admin",
    "Ajout du cours ".$nom_cours
);

header("Location: afficher_cours.php");
exit();
?>

<?php
if(isset($_POST["ajouter"])){

$req = $bdd->prepare("
INSERT INTO cours(nom_cours,credit,id_niveau,id_fac,date_exam,heure_debut,heure_fin)
VALUES(?,?,?,?,?,?,?)
");

$req->execute([
$_POST["nom_cours"],
$_POST["credit"],
$_POST["id_niveau"],
$_POST["id_fac"],
$_POST["date_exam"],
$_POST["heure_debut"],
$_POST["heure_fin"]
]);

header("Location: afficher_cours.php");
exit;
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
<meta charset="UTF-8">
<title>Ajouter Cours</title>

<style>

/* 🌿 GLOBAL */
body{
    margin:0;
    font-family:Arial;
    background:#f5f6fa;
    display:flex;
}

/* MENU */
aside{
    width:220px;
}

/* CENTRAGE */
.container{
    flex:1;
    display:flex;
    justify-content:center;
    align-items:center;
    height:100vh;
}

/* FORMULAIRE */
.form-box{
    background:white;
    padding:25px;
    border-radius:10px;
    width:350px;
    box-shadow:0 2px 10px rgba(0,0,0,0.05);
}

/* TITRE */
h2{
    text-align:center;
    font-weight:normal;
    margin-bottom:20px;
    color:#333;
}

/* INPUTS */
input, select{
    width:100%;
    padding:10px;
    margin:8px 0;
    border:1px solid #ddd;
    border-radius:6px;
    outline:none;
}

/* BUTTON */
button{
    width:100%;
    padding:10px;
    border:none;
    background:#333;
    color:white;
    border-radius:6px;
    cursor:pointer;
}

button:hover{
    background:#111;
}

</style>

</head>

<body>

<!-- MENU -->
<aside>
<?php include 'Menu_principal.php'; ?>
</aside>

<!-- FORMULAIRE CENTRÉ -->
<div class="container">

    <div class="form-box">

        <h2>Ajouter un cours</h2>

        <form method="POST">

            <input type="text" name="nom_cours" placeholder="Nom cours" required>

            <input type="number" name="credit" placeholder="Crédit" required>

            <select name="id_niveau" required>
                <option value="">Choisir niveau</option>
                <?php
                $niv = $bdd->query("SELECT * FROM niveau_etude");
                while($n = $niv->fetch()){
                    echo "<option value='{$n['id_niveau']}'>{$n['nom_niveau']}</option>";
                }
                ?>
            </select>

            <select name="id_fac" required>
                <option value="">Choisir faculté</option>
                <?php
                $fac = $bdd->query("SELECT * FROM faculte");
                while($f = $fac->fetch()){
                    echo "<option value='{$f['id_fac']}'>{$f['nom_fac']}</option>";
                }
                ?>
            </select>

            <input type="date" name="date_exam" required>

            <input type="time" name="heure_debut" required>

            <input type="time" name="heure_fin" required>

            <button type="submit" name="ajouter">Ajouter</button>

        </form>

    </div>

</div>

</body>
</html>