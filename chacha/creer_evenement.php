<?php
session_start();

if (
    !isset($_SESSION["id_admin"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: ../connexion.php");
    exit();
}

require_once __DIR__ . "/../connexionDB.php";
require_once __DIR__ . "/../journal.php";

/* Journaliser l'accès à la page. */
journal(
    $bdd,
    $_SESSION["id_admin"],
    "Administrateur",
    "admin",
    "Accès à la création d'un événement"
);

/* Récupérer les étudiants. */
$etudiants = $bdd->query("
    SELECT id_etu, nom_etu, prenom_etu, email
    FROM etudiant
    ORDER BY nom_etu, prenom_etu
")->fetchAll(PDO::FETCH_ASSOC);

/* Récupérer les surveillants. */
$surveillants = $bdd->query("
    SELECT id_surv, nom_surv, prenom_surv, email
    FROM surveillant
    ORDER BY nom_surv, prenom_surv
")->fetchAll(PDO::FETCH_ASSOC);

/* Récupérer les facultés. */
$facultes = $bdd->query("
    SELECT id_fac, nom_fac
    FROM faculte
    ORDER BY nom_fac
")->fetchAll(PDO::FETCH_ASSOC);

/* Récupérer les niveaux d'étude. */
$niveaux = $bdd->query("
    SELECT id_niveau, nom_niveau
    FROM niveau_etude
    ORDER BY nom_niveau
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Créer un événement - CHACHA</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f7fb;
            margin: 0;
            color: #333;
        }

        h2 {
            text-align: center;
            color: #003366;
            margin: 25px 10px;
        }

        .conteneur {
            max-width: 750px;
            margin: 25px auto;
            padding: 25px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        label {
            display: block;
            margin-top: 16px;
            margin-bottom: 7px;
            font-weight: bold;
            color: #003366;
        }

        input, select, textarea {
            box-sizing: border-box;
            width: 100%;
            padding: 11px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 15px;
            background: white;
        }

        textarea {
            min-height: 120px;
            resize: vertical;
        }

        button {
            width: 100%;
            padding: 13px;
            margin-top: 25px;
            background: #003366;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover {
            background: #0055aa;
        }

        .note {
            margin-top: 15px;
            color: #555;
            font-size: 13px;
            line-height: 1.5;
        }

        .groupe-choix {
            margin-top: 15px;
            padding: 15px;
            background: #f5f7fb;
            border-radius: 5px;
        }

        .groupe-choix label:first-child {
            margin-top: 0;
        }

        [hidden] {
            display: none !important;
        }
    </style>
</head>

<body>

<?php include("Menu_principal.php"); ?>

<h2>Créer un événement</h2>

<div class="conteneur">

    <form action="enregistrer_evenement.php" method="POST">

        <label for="titre">Titre de l'événement *</label>

        <input
            type="text"
            id="titre"
            name="titre"
            maxlength="200"
            required
        >

        <label for="type_evenement">Type d'événement *</label>

        <select id="type_evenement" name="type_evenement" required>
            <option value="Annonce">Annonce</option>
            <option value="Examen">Examen</option>
            <option value="Réunion">Réunion</option>
            <option value="Changement de programme">
                Changement de programme
            </option>
            <option value="Urgence">Urgence</option>
            <option value="Autre">Autre</option>
        </select>

        <label for="date_evenement">
            Date et heure de l'événement
        </label>

        <input
            type="datetime-local"
            id="date_evenement"
            name="date_evenement"
        >

        <label for="lieu">Lieu</label>

        <input
            type="text"
            id="lieu"
            name="lieu"
            maxlength="255"
        >

        <label for="description">Description du message *</label>

        <textarea
            id="description"
            name="description"
            required
        ></textarea>

        <label for="destinataire">Destinataires *</label>

        <select id="destinataire" name="destinataire" required>

            <option value="tous">
                Tous les étudiants et surveillants
            </option>

            <option value="etudiants">
                Tous les étudiants
            </option>

            <option value="etudiants_faculte">
                Étudiants d'une faculté
            </option>

            <option value="etudiants_niveau">
                Étudiants d'un niveau
            </option>

            <option value="etudiants_faculte_niveau">
                Étudiants d'une faculté et d'un niveau
            </option>

            <option value="surveillants">
                Tous les surveillants
            </option>

            <option value="etudiant">
                Un étudiant précis
            </option>

            <option value="surveillant">
                Un surveillant précis
            </option>

        </select>

        <!-- Choix de la faculté et du niveau -->

        <div id="choix_faculte" class="groupe-choix" hidden>

            <label for="id_fac">Faculté *</label>

            <select id="id_fac" name="id_fac">

                <option value="">
                    -- Sélectionner une faculté --
                </option>

                <?php foreach ($facultes as $fac): ?>

                    <option value="<?= (int)$fac["id_fac"] ?>">
                        <?= htmlspecialchars(
                            $fac["nom_fac"],
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </div>

        <div id="choix_niveau" class="groupe-choix" hidden>

            <label for="id_niveau">Niveau d'étude *</label>

            <select id="id_niveau" name="id_niveau">

                <option value="">
                    -- Sélectionner un niveau --
                </option>

                <?php foreach ($niveaux as $niv): ?>

                    <option value="<?= (int)$niv["id_niveau"] ?>">
                        <?= htmlspecialchars(
                            $niv["nom_niveau"],
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </div>

        <!-- Choix d'un étudiant précis -->

        <div id="choix_etudiant" class="groupe-choix" hidden>

            <label for="id_etu">Choisir l'étudiant *</label>

            <select name="id_etu" id="id_etu">

                <option value="">
                    -- Sélectionner un étudiant --
                </option>

                <?php foreach ($etudiants as $etu): ?>

                    <option value="<?= (int)$etu["id_etu"] ?>">
                        <?= htmlspecialchars(
                            $etu["nom_etu"] . " " . $etu["prenom_etu"],
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </div>

        <!-- Choix d'un surveillant précis -->

        <div id="choix_surveillant" class="groupe-choix" hidden>

            <label for="id_surv">Choisir le surveillant *</label>

            <select name="id_surv" id="id_surv">

                <option value="">
                    -- Sélectionner un surveillant --
                </option>

                <?php foreach ($surveillants as $surv): ?>

                    <option value="<?= (int)$surv["id_surv"] ?>">
                        <?= htmlspecialchars(
                            $surv["nom_surv"] . " " . $surv["prenom_surv"],
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </div>
		

        <p class="note">
            Les destinataires recevront le message à l'adresse e-mail
            enregistrée dans leur compte. Les personnes sans adresse
            e-mail valide seront comptabilisées séparément.
        </p>

        <button type="submit">
            Enregistrer l'événement
        </button>
		
		<button type="submit">
            Retour
        </button>
		

    </form>

</div>

<script>
const destinataire = document.getElementById("destinataire");

const choixFaculte = document.getElementById("choix_faculte");
const choixNiveau = document.getElementById("choix_niveau");
const choixEtudiant = document.getElementById("choix_etudiant");
const choixSurveillant = document.getElementById("choix_surveillant");

const selectFaculte = document.getElementById("id_fac");
const selectNiveau = document.getElementById("id_niveau");
const selectEtudiant = document.getElementById("id_etu");
const selectSurveillant = document.getElementById("id_surv");

function actualiserDestinataires() {
    const valeur = destinataire.value;

    const avecFaculte = [
        "etudiants_faculte",
        "etudiants_faculte_niveau"
    ].includes(valeur);

    const avecNiveau = [
        "etudiants_niveau",
        "etudiants_faculte_niveau"
    ].includes(valeur);

    const etudiantUnique = valeur === "etudiant";
    const surveillantUnique = valeur === "surveillant";

    choixFaculte.hidden = !avecFaculte;
    choixNiveau.hidden = !avecNiveau;
    choixEtudiant.hidden = !etudiantUnique;
    choixSurveillant.hidden = !surveillantUnique;

    selectFaculte.required = avecFaculte;
    selectNiveau.required = avecNiveau;
    selectEtudiant.required = etudiantUnique;
    selectSurveillant.required = surveillantUnique;
}

destinataire.addEventListener("change", actualiserDestinataires);

actualiserDestinataires();
</script>

</body>
</html>

