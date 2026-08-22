```php
<?php

session_start();

if (
    !isset($_SESSION["id_admin"]) ||
    $_SESSION["role"] != "admin"
) {
    header("Location: connexion.php");
    exit();
}

include("connexionDB.php");
include("journal.php");

error_reporting(E_ALL);
ini_set("display_errors", 1);

$bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);


/* =========================================================
   VARIABLES
========================================================= */

$message = "";

$id_fac = $_POST["id_fac"] ?? "";
$id_niveau = $_POST["id_niveau"] ?? "";

$id_cours = $_POST["id_cours"] ?? "";
$id_salle = $_POST["id_salle"] ?? "";
$id_surv = $_POST["id_surv"] ?? "";

$date_exam = $_POST["date_exam"] ?? "";
$heure_debut = $_POST["heure_debut"] ?? "";
$heure_fin = $_POST["heure_fin"] ?? "";

$cours = [];
$etudiants = [];


/* =========================================================
   RECUPERATION DES FACULTES
========================================================= */

$facultes = $bdd->query("
    SELECT id_fac, nom_fac
    FROM faculte
    ORDER BY nom_fac ASC
")->fetchAll();


/* =========================================================
   RECUPERATION DES NIVEAUX
========================================================= */

$niveaux = $bdd->query("
    SELECT id_niveau, nom_niveau
    FROM niveau_etude
    ORDER BY nom_niveau ASC
")->fetchAll();


/* =========================================================
   RECUPERATION DES SALLES
========================================================= */

$salles = $bdd->query("
    SELECT id_salle, nom_salle
    FROM salle
    ORDER BY nom_salle ASC
")->fetchAll();


/* =========================================================
   RECUPERATION DES SURVEILLANTS
========================================================= */

$surveillants = $bdd->query("
    SELECT
        id_surv,
        nom_surv,
        prenom_surv
    FROM surveillant
    ORDER BY nom_surv ASC, prenom_surv ASC
")->fetchAll();


/* =========================================================
   CHARGER LES COURS ET LES ETUDIANTS
========================================================= */

if (isset($_POST["charger"])) {

    if (empty($id_fac) || empty($id_niveau)) {

        $message = "Veuillez choisir une faculté et un niveau.";

    } else {

        /* -----------------------------------------------------
           COURS DE LA FACULTE + DU NIVEAU
        ----------------------------------------------------- */

        $req = $bdd->prepare("
            SELECT
                id_cours,
                nom_cours,
                credit
            FROM cours
            WHERE id_fac = ?
            AND id_niveau = ?
            ORDER BY nom_cours ASC
        ");

        $req->execute([
            $id_fac,
            $id_niveau
        ]);

        $cours = $req->fetchAll();


        /* -----------------------------------------------------
           ETUDIANTS DE LA FACULTE + DU NIVEAU
        ----------------------------------------------------- */

        $req = $bdd->prepare("
            SELECT
                id_etu,
                nom_etu,
                prenom_etu
            FROM etudiant
            WHERE id_fac = ?
            AND id_niveau = ?
            ORDER BY nom_etu ASC, prenom_etu ASC
        ");

        $req->execute([
            $id_fac,
            $id_niveau
        ]);

        $etudiants = $req->fetchAll();


        if (empty($cours)) {

            $message =
                "Aucun cours n'est enregistré pour cette faculté et ce niveau.";

        } elseif (empty($etudiants)) {

            $message =
                "Aucun étudiant n'est enregistré pour cette faculté et ce niveau.";
        }
    }
}


/* =========================================================
   ENREGISTRER L'AFFECTATION
========================================================= */

if (isset($_POST["enregistrer"])) {

    try {

        $id_fac = $_POST["id_fac"] ?? "";
        $id_niveau = $_POST["id_niveau"] ?? "";

        $id_cours = $_POST["id_cours"] ?? "";
        $id_salle = $_POST["id_salle"] ?? "";
        $id_surv = $_POST["id_surv"] ?? "";

        $date_exam = $_POST["date_exam"] ?? "";
        $heure_debut = $_POST["heure_debut"] ?? "";
        $heure_fin = $_POST["heure_fin"] ?? "";

        $etudiants_selectionnes =
            $_POST["etudiants"] ?? [];


        /* -----------------------------------------------------
           VERIFICATION DES CHAMPS
        ----------------------------------------------------- */

        if (
            empty($id_fac) ||
            empty($id_niveau) ||
            empty($id_cours) ||
            empty($id_salle) ||
            empty($id_surv) ||
            empty($date_exam) ||
            empty($heure_debut) ||
            empty($heure_fin)
        ) {

            throw new Exception(
                "Veuillez remplir tous les champs."
            );
        }


        /* -----------------------------------------------------
           VERIFICATION DES ETUDIANTS
        ----------------------------------------------------- */

        if (empty($etudiants_selectionnes)) {

            throw new Exception(
                "Veuillez sélectionner au moins un étudiant."
            );
        }


        /* -----------------------------------------------------
           VERIFICATION DES HEURES
        ----------------------------------------------------- */

        if ($heure_debut >= $heure_fin) {

            throw new Exception(
                "L'heure de fin doit être supérieure à l'heure de début."
            );
        }


        /* -----------------------------------------------------
           VERIFIER QUE LE COURS CORRESPOND
           A LA FACULTE ET AU NIVEAU
        ----------------------------------------------------- */

        $req = $bdd->prepare("
            SELECT id_cours
            FROM cours
            WHERE id_cours = ?
            AND id_fac = ?
            AND id_niveau = ?
        ");

        $req->execute([
            $id_cours,
            $id_fac,
            $id_niveau
        ]);


        if (!$req->fetch()) {

            throw new Exception(
                "Le cours choisi ne correspond pas à la faculté et au niveau."
            );
        }


        /* -----------------------------------------------------
           TRANSACTION
        ----------------------------------------------------- */

        $bdd->beginTransaction();


        /* -----------------------------------------------------
           PREPARER LA PROCEDURE
        ----------------------------------------------------- */

        $reqAffectation = $bdd->prepare("
            CALL affecter_examen(
                ?, ?, ?, ?, ?, ?, ?
            )
        ");


        /* -----------------------------------------------------
           VERIFIER CHAQUE ETUDIANT
        ----------------------------------------------------- */

        $verifEtudiant = $bdd->prepare("
            SELECT id_etu
            FROM etudiant
            WHERE id_etu = ?
            AND id_fac = ?
            AND id_niveau = ?
        ");


        foreach ($etudiants_selectionnes as $id_etu) {


            /* Vérifier que l'étudiant appartient
               bien à la faculté et au niveau */

            $verifEtudiant->execute([
                $id_etu,
                $id_fac,
                $id_niveau
            ]);


            if (!$verifEtudiant->fetch()) {

                throw new Exception(
                    "Un étudiant sélectionné ne correspond pas à la faculté et au niveau."
                );
            }


            /* -------------------------------------------------
               APPEL DE LA PROCEDURE
            ------------------------------------------------- */

            $reqAffectation->execute([

                $id_etu,

                $id_cours,

                $id_salle,

                $id_surv,

                $date_exam,

                $heure_debut,

                $heure_fin

            ]);


            /*
             * Très important avec CALL :
             * on vide le résultat avant le prochain appel.
             */

            $reqAffectation->closeCursor();
        }


        /* -----------------------------------------------------
           VALIDER LA TRANSACTION
        ----------------------------------------------------- */

        $bdd->commit();


        /* -----------------------------------------------------
           JOURNAL
        ----------------------------------------------------- */

        journal(
            $bdd,
            $_SESSION["id_admin"],
            "Administrateur",
			"Admin",
            "Création d'une affectation d'examen"
        );


        /* -----------------------------------------------------
           REDIRECTION
        ----------------------------------------------------- */

        header(
            "Location: afficher_affectation_admin.php?success=1"
        );

        exit();


    } catch (PDOException $e) {

        if ($bdd->inTransaction()) {
            $bdd->rollBack();
        }

        $message =
            "Erreur SQL : " . $e->getMessage();


    } catch (Exception $e) {

        if ($bdd->inTransaction()) {
            $bdd->rollBack();
        }

        $message =
            $e->getMessage();
    }


    /* =========================================================
       RECHARGER COURS + ETUDIANTS EN CAS D'ERREUR
    ========================================================= */

    if (!empty($id_fac) && !empty($id_niveau)) {

        $req = $bdd->prepare("
            SELECT
                id_cours,
                nom_cours,
                credit
            FROM cours
            WHERE id_fac = ?
            AND id_niveau = ?
            ORDER BY nom_cours ASC
        ");

        $req->execute([
            $id_fac,
            $id_niveau
        ]);

        $cours = $req->fetchAll();


        $req = $bdd->prepare("
            SELECT
                id_etu,
                nom_etu,
                prenom_etu
            FROM etudiant
            WHERE id_fac = ?
            AND id_niveau = ?
            ORDER BY nom_etu ASC, prenom_etu ASC
        ");

        $req->execute([
            $id_fac,
            $id_niveau
        ]);

        $etudiants = $req->fetchAll();
    }
}

?>


<!DOCTYPE html>

<html lang="fr">

<head>

<meta charset="UTF-8">

<title>Affectation des examens</title>


<style>

/* =========================================================
   GENERAL
========================================================= */

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:Arial, Helvetica, sans-serif;
}


body{

    background:#f4f6f9;

}


.wrapper{

    display:flex;

    min-height:100vh;

}


aside{

    width:220px;

    background:#111827;

    min-height:100vh;

}


main{

    flex:1;

    padding:30px;

}


/* =========================================================
   CONTAINER
========================================================= */

.container{

    max-width:900px;

    margin:auto;

    background:white;

    padding:30px;

    border-radius:10px;

    box-shadow:0 3px 12px rgba(0,0,0,.10);

}


h2{

    text-align:center;

    color:#003366;

    margin-bottom:25px;

}


h3{

    color:#003366;

    margin-top:20px;

    margin-bottom:15px;

    border-bottom:1px solid #ddd;

    padding-bottom:8px;

}


/* =========================================================
   FORMULAIRE
========================================================= */

label{

    display:block;

    font-weight:bold;

    margin-top:15px;

    margin-bottom:6px;

}


select,
input[type="date"],
input[type="time"]{

    width:100%;

    padding:11px;

    border:1px solid #ccc;

    border-radius:6px;

    font-size:15px;

    background:white;

}


select:focus,
input:focus{

    outline:none;

    border-color:#003366;

}


/* =========================================================
   BOUTONS
========================================================= */

button{

    border:none;

    border-radius:6px;

    padding:12px;

    cursor:pointer;

    font-size:15px;

}


.charger{

    width:100%;

    margin-top:20px;

    background:#198754;

    color:white;

}


.charger:hover{

    background:#157347;

}


.enregistrer{

    width:100%;

    margin-top:25px;

    background:#003366;

    color:white;

    font-size:17px;

}


.enregistrer:hover{

    background:#00264d;

}


/* =========================================================
   MESSAGE
========================================================= */

.message{

    padding:12px;

    margin-bottom:20px;

    border-radius:6px;

    background:#fff3cd;

    color:#856404;

    text-align:center;

}


/* =========================================================
   INFORMATIONS
========================================================= */

.info{

    background:#e7f1ff;

    color:#003366;

    padding:12px;

    border-radius:6px;

    margin:15px 0;

}


/* =========================================================
   LISTE DES ETUDIANTS
========================================================= */

.studentList{

    margin-top:15px;

    padding:15px;

    border:1px solid #ddd;

    border-radius:8px;

    background:#f8fafc;

    max-height:300px;

    overflow-y:auto;

}


.student{

    padding:9px;

    border-bottom:1px solid #e5e7eb;

}


.student:last-child{

    border-bottom:none;

}


.student label{

    margin:0;

    font-weight:normal;

    cursor:pointer;

}


.student input[type="checkbox"]{

    width:auto;

    margin-right:10px;

    cursor:pointer;

}


/* =========================================================
   SEPARATION
========================================================= */

hr{

    border:0;

    border-top:1px solid #ddd;

    margin:30px 0;

}

</style>

</head>


<body>


<div class="wrapper">


<aside>

<?php include("menu_principal.php"); ?>

</aside>


<main>


<div class="container">


<h2>
Affectation des examens
</h2>


<?php if(!empty($message)){ ?>

<div class="message">

<?= htmlspecialchars($message) ?>

</div>

<?php } ?>


<!-- =====================================================
     ETAPE 1 : FACULTE + NIVEAU
===================================================== -->

<form method="POST">


<h3>
1. Choisir la faculté et le niveau
</h3>


<label>
Faculté
</label>


<select name="id_fac" required>

<option value="">
-- Choisir une faculté --
</option>


<?php foreach($facultes as $f){ ?>

<option
value="<?= $f["id_fac"] ?>"
<?= ($id_fac == $f["id_fac"]) ? "selected" : "" ?>
>

<?= htmlspecialchars($f["nom_fac"]) ?>

</option>

<?php } ?>

</select>


<label>
Niveau
</label>


<select name="id_niveau" required>

<option value="">
-- Choisir un niveau --
</option>


<?php foreach($niveaux as $n){ ?>

<option
value="<?= $n["id_niveau"] ?>"
<?= ($id_niveau == $n["id_niveau"]) ? "selected" : "" ?>
>

<?= htmlspecialchars($n["nom_niveau"]) ?>

</option>

<?php } ?>

</select>


<button
type="submit"
name="charger"
class="charger"
>

Afficher les cours et les étudiants

</button>


</form>



<?php if(
    !empty($id_fac) &&
    !empty($id_niveau) &&
    (isset($_POST["charger"]) || isset($_POST["enregistrer"]))
){ ?>


<hr>


<!-- =====================================================
     ETAPE 2 : AFFECTATION
===================================================== -->

<form method="POST">


<input
type="hidden"
name="id_fac"
value="<?= htmlspecialchars($id_fac) ?>"
>


<input
type="hidden"
name="id_niveau"
value="<?= htmlspecialchars($id_niveau) ?>"
>


<h3>
2. Choisir le cours
</h3>


<label>
Cours
</label>


<select name="id_cours" required>

<option value="">
-- Choisir un cours --
</option>


<?php foreach($cours as $c){ ?>

<option
value="<?= $c["id_cours"] ?>"
<?= ($id_cours == $c["id_cours"]) ? "selected" : "" ?>
>

<?= htmlspecialchars($c["nom_cours"]) ?>

&nbsp; - &nbsp;

<?= htmlspecialchars($c["credit"]) ?> crédit(s)

</option>

<?php } ?>

</select>



<h3>
3. Informations de l'examen
</h3>


<label>
Salle
</label>


<select name="id_salle" required>

<option value="">
-- Choisir une salle --
</option>


<?php foreach($salles as $s){ ?>

<option
value="<?= $s["id_salle"] ?>"
<?= ($id_salle == $s["id_salle"]) ? "selected" : "" ?>
>

<?= htmlspecialchars($s["nom_salle"]) ?>

</option>

<?php } ?>

</select>


<label>
Surveillant
</label>


<select name="id_surv" required>

<option value="">
-- Choisir un surveillant --
</option>


<?php foreach($surveillants as $sv){ ?>

<option
value="<?= $sv["id_surv"] ?>"
<?= ($id_surv == $sv["id_surv"]) ? "selected" : "" ?>
>

<?= htmlspecialchars(
    $sv["nom_surv"] . " " . $sv["prenom_surv"]
) ?>

</option>

<?php } ?>

</select>


<label>
Date de l'examen
</label>


<input
type="date"
name="date_exam"
value="<?= htmlspecialchars($date_exam) ?>"
required
>


<label>
Heure de début
</label>


<input
type="time"
name="heure_debut"
value="<?= htmlspecialchars($heure_debut) ?>"
required
>


<label>
Heure de fin
</label>


<input
type="time"
name="heure_fin"
value="<?= htmlspecialchars($heure_fin) ?>"
required
>



<h3>
4. Sélectionner les étudiants
</h3>


<div class="info">

Les étudiants affichés appartiennent à la faculté et au niveau sélectionnés.

</div>


<div class="studentList">


<?php if(empty($etudiants)){ ?>


<p style="color:red;">

Aucun étudiant trouvé pour cette faculté et ce niveau.

</p>


<?php } else { ?>


<?php

$i = 1;

foreach($etudiants as $e){

?>


<div class="student">


<label>


<input
type="checkbox"
name="etudiants[]"
value="<?= $e["id_etu"] ?>"
>


<?= $i ?> -

<?= htmlspecialchars(
    $e["nom_etu"] . " " . $e["prenom_etu"]
) ?>


</label>


</div>


<?php

$i++;

}

?>


<?php } ?>


</div>


<button
type="submit"
name="enregistrer"
class="enregistrer"
>

Enregistrer l'affectation

</button>


</form>


<?php } ?>


</div>


</main>


</div>


</body>

</html>
```
