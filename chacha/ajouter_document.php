<?php
session_start();
require_once 'connexionDB.php';

$message = '';
$erreur = false;
$afficher_surveillants = false;

// Vérifier que l'utilisateur est administrateur
if (!isset($_SESSION['id_admin'])) {
    header('Location: connexion.php');
    exit;
}

$id_admin = (int) $_SESSION['id_admin'];

// Valeurs du formulaire
$nom_document = '';
$description = '';
$destinataires = [];
$id_fac = '';
$id_niveau = '';
$surveillants_selectionnes = [];

// Déterminer si la liste des surveillants doit être affichée
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action'])
    && $_POST['action'] === 'afficher_surveillants'
) {
    $afficher_surveillants = true;
}

// Charger les facultés
$facultes = $bdd->query(
    "SELECT id_fac, nom_fac
     FROM faculte
     ORDER BY nom_fac"
)->fetchAll(PDO::FETCH_ASSOC);

// Charger les niveaux
$niveaux = $bdd->query(
    "SELECT id_niveau, nom_niveau
     FROM niveau_etude
     ORDER BY nom_niveau"
)->fetchAll(PDO::FETCH_ASSOC);

// Charger les surveillants
$surveillants = $bdd->query(
    "SELECT id_surv, nom_surv, prenom_surv
     FROM surveillant
     ORDER BY nom_surv, prenom_surv"
)->fetchAll(PDO::FETCH_ASSOC);

// Traiter le formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Récupérer les valeurs saisies
    $nom_document = trim($_POST['nom_document'] ?? '');
    $description = trim($_POST['description'] ?? '');

    $destinataires = $_POST['destinataires'] ?? [];
    $destinataires = array_values(array_intersect(
        (array) $destinataires,
        ['etudiant', 'surveillant']
    ));

    $id_fac = $_POST['id_fac'] ?? '';
    $id_niveau = $_POST['id_niveau'] ?? '';

    $surveillants_selectionnes = array_values(array_unique(
        array_filter(
            array_map(
                'intval',
                (array) ($_POST['surveillants'] ?? [])
            ),
            function ($id) {
                return $id > 0;
            }
        )
    ));

    // Affichage de la liste : on ne traite pas encore l'enregistrement
    if ($afficher_surveillants) {
        // Conserver les choix déjà effectués.
        // Le fichier devra être sélectionné de nouveau.
    } else {

        $id_fac = ($id_fac !== '') ? (int) $id_fac : null;
        $id_niveau = ($id_niveau !== '') ? (int) $id_niveau : null;

        // Vérifier les champs obligatoires
        if ($nom_document === '') {

            $message = "Veuillez saisir le nom du document.";
            $erreur = true;

        } elseif (empty($destinataires)) {

            $message = "Veuillez choisir au moins un type de destinataire.";
            $erreur = true;

        } elseif (
            in_array('etudiant', $destinataires, true)
            && $id_fac === null
            && $id_niveau === null
        ) {

            $message = "Pour partager avec les étudiants, choisissez au moins une faculté ou un niveau.";
            $erreur = true;

        } elseif (
            in_array('surveillant', $destinataires, true)
            && empty($surveillants_selectionnes)
        ) {

            $message = "Veuillez afficher la liste et sélectionner au moins un surveillant.";
            $erreur = true;

        } elseif (
            !isset($_FILES['fichier'])
            || $_FILES['fichier']['error'] !== UPLOAD_ERR_OK
        ) {

            $message = "Veuillez sélectionner un fichier valide.";
            $erreur = true;

        } else {

            // Vérifier la validité de la faculté
            $ids_facultes_valides = array_map(
                'intval',
                array_column($facultes, 'id_fac')
            );

            // Vérifier la validité du niveau
            $ids_niveaux_valides = array_map(
                'intval',
                array_column($niveaux, 'id_niveau')
            );

            // Vérifier la validité des surveillants
            $ids_surveillants_valides = array_map(
                'intval',
                array_column($surveillants, 'id_surv')
            );

            if (
                $id_fac !== null
                && !in_array($id_fac, $ids_facultes_valides, true)
            ) {

                $message = "La faculté sélectionnée est invalide.";
                $erreur = true;

            } elseif (
                $id_niveau !== null
                && !in_array($id_niveau, $ids_niveaux_valides, true)
            ) {

                $message = "Le niveau sélectionné est invalide.";
                $erreur = true;

            } elseif (
                count(array_diff(
                    $surveillants_selectionnes,
                    $ids_surveillants_valides
                )) > 0
            ) {

                $message = "Un surveillant sélectionné est invalide.";
                $erreur = true;

            } else {

                // Vérifier le format du fichier
                $extensions_autorisees = [
                    'pdf', 'doc', 'docx',
                    'xls', 'xlsx', 'ppt', 'pptx'
                ];

                $extension = strtolower(
                    pathinfo(
                        $_FILES['fichier']['name'],
                        PATHINFO_EXTENSION
                    )
                );

                $taille = (int) $_FILES['fichier']['size'];

                if (!in_array(
                    $extension,
                    $extensions_autorisees,
                    true
                )) {

                    $message = "Format non autorisé. Choisissez un fichier PDF, Word, Excel ou PowerPoint.";
                    $erreur = true;

                } elseif ($taille > 10 * 1024 * 1024) {

                    $message = "Le fichier ne doit pas dépasser 10 Mo.";
                    $erreur = true;

                } else {

                    // Créer le dossier des documents si nécessaire
                    $dossier = __DIR__ . '/documents/';

                    if (
                        !is_dir($dossier)
                        && !mkdir($dossier, 0755, true)
                    ) {

                        $message = "Impossible de créer le dossier documents.";
                        $erreur = true;

                    } else {

                        // Générer un nom de fichier unique
                        $nom_fichier = bin2hex(random_bytes(16))
                            . '.'
                            . $extension;

                        $destination = $dossier . $nom_fichier;

                        // Déplacer le fichier envoyé
                        if (move_uploaded_file(
                            $_FILES['fichier']['tmp_name'],
                            $destination
                        )) {

                            try {

                                $bdd->beginTransaction();

                                // 1. Enregistrer le document
                                $requete = $bdd->prepare(
                                    "INSERT INTO document
                                    (
                                        nom_document,
                                        fichier,
                                        description,
                                        id_admin
                                    )
                                    VALUES (?, ?, ?, ?)"
                                );

                                $requete->execute([
                                    $nom_document,
                                    $nom_fichier,
                                    $description !== ''
                                        ? $description
                                        : null,
                                    $id_admin
                                ]);

                                $id_document = (int) $bdd->lastInsertId();

                                // 2. Préparer l'enregistrement des partages
                                $ajout_partage = $bdd->prepare(
                                    "INSERT INTO partage_document
                                    (
                                        id_document,
                                        type_destinataire,
                                        id_etu,
                                        id_surv,
                                        id_fac,
                                        id_niveau
                                    )
                                    VALUES (?, ?, ?, ?, ?, ?)"
                                );

                                // 3. Partager avec les étudiants
                                // selon la faculté et/ou le niveau
                                if (
                                    in_array(
                                        'etudiant',
                                        $destinataires,
                                        true
                                    )
                                ) {

                                    $ajout_partage->execute([
                                        $id_document,
                                        'etudiant',
                                        null,
                                        null,
                                        $id_fac,
                                        $id_niveau
                                    ]);
                                }

                                // 4. Partager avec les surveillants choisis
                                if (
                                    in_array(
                                        'surveillant',
                                        $destinataires,
                                        true
                                    )
                                ) {

                                    foreach (
                                        $surveillants_selectionnes
                                        as $id_surv
                                    ) {

                                        $ajout_partage->execute([
                                            $id_document,
                                            'surveillant',
                                            null,
                                            $id_surv,
                                            null,
                                            null
                                        ]);
                                    }
                                }

                                // Valider les enregistrements
                                $bdd->commit();

                                $message = "Document partagé avec succès !";

                                // Réinitialiser le formulaire
                                $nom_document = '';
                                $description = '';
                                $destinataires = [];
                                $id_fac = '';
                                $id_niveau = '';
                                $surveillants_selectionnes = [];
                                $afficher_surveillants = false;

                            } catch (Throwable $e) {

                                if ($bdd->inTransaction()) {
                                    $bdd->rollBack();
                                }

                                // Supprimer le fichier en cas d'erreur
                                if (file_exists($destination)) {
                                    unlink($destination);
                                }

                                $message = "Une erreur est survenue pendant l'enregistrement du document ou de ses destinataires.";
                                $erreur = true;
                            }

                        } else {

                            $message = "Impossible d'enregistrer le fichier dans le dossier documents.";
                            $erreur = true;
                        }
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Partager un document</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f2f5f9;
            margin: 0;
            padding: 30px;
        }

        .conteneur {
            max-width: 750px;
            margin: 20px auto;
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.1);
        }

        h2 {
            color: #183b65;
            text-align: center;
        }

        label {
            display: block;
            margin-top: 18px;
            font-weight: bold;
        }

        input[type="text"],
        textarea,
        input[type="file"],
        select {
            width: 100%;
            box-sizing: border-box;
            padding: 10px;
            margin-top: 7px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        select[multiple] {
            min-height: 150px;
        }

        textarea {
            min-height: 90px;
            resize: vertical;
        }

        .info {
            color: #555;
            font-size: 13px;
            margin-top: 8px;
            line-height: 1.5;
        }

        .message {
            margin-top: 15px;
            padding: 12px;
            border-radius: 5px;
            background: #e8f5e9;
            color: #245b2a;
            line-height: 1.5;
        }

        .erreur {
            background: #fdecea;
            color: #a32121;
        }

        button {
            width: 100%;
            margin-top: 20px;
            padding: 13px;
            background: #183b65;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }

        button:hover {
            background: #24558c;
        }

        .bouton-secondaire {
            background: #52677f;
        }

        .bouton-secondaire:hover {
            background: #3b4f66;
        }

        .bloc {
            margin-top: 25px;
            padding: 15px;
            background: #f8fafc;
            border: 1px solid #dbe3ec;
            border-radius: 8px;
        }

        .choix {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 12px;
        }

        .choix input {
            margin: 0;
        }

        .choix label {
            display: inline;
            margin: 0;
            font-weight: normal;
        }

        .liste-cachee {
            display: none;
        }
		
		.bouton-retour {
			display: inline-block;
			background: #52677f;
			color: white;
			padding: 11px 16px;
			text-decoration: none;
			border-radius: 5px;
			margin: 10px 0 15px;
			font-size: 14px;
		}

		.bouton-retour:hover {
			background: #3b4f66;
		}


    </style>
</head>

<body>

<div class="conteneur">

    <h2>Partager un document</h2>

	<a href="dashboard.php" class="bouton-retour">
		 Retour au tableau de bord
	</a>



    <?php if ($message !== ''): ?>
        <div class="message <?= $erreur ? 'erreur' : '' ?>">
            <?= htmlspecialchars(
                $message,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">

        <label for="nom_document">Nom du document :</label>

        <input
            type="text"
            id="nom_document"
            name="nom_document"
            value="<?= htmlspecialchars(
                $nom_document,
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
            required
        >

        <label for="description">Description :</label>

        <textarea
            id="description"
            name="description"
        ><?= htmlspecialchars(
            $description,
            ENT_QUOTES,
            'UTF-8'
        ) ?></textarea>

        <label for="fichier">Sélectionner le fichier :</label>

        <input
            type="file"
            id="fichier"
            name="fichier"
            accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx"
            required
        >

        <p class="info">
            Formats acceptés : PDF, Word, Excel et PowerPoint.
            Taille maximale : 10 Mo.
        </p>

        <div class="bloc">

            <strong>1. Choisir les destinataires</strong>

            <div class="choix">

                <input
                    type="checkbox"
                    id="dest_etudiant"
                    name="destinataires[]"
                    value="etudiant"
                    <?= in_array(
                        'etudiant',
                        $destinataires,
                        true
                    ) ? 'checked' : '' ?>
                >

                <label for="dest_etudiant">Étudiants</label>

            </div>

            <div class="choix">

                <input
                    type="checkbox"
                    id="dest_surveillant"
                    name="destinataires[]"
                    value="surveillant"
                    <?= in_array(
                        'surveillant',
                        $destinataires,
                        true
                    ) ? 'checked' : '' ?>
                >

                <label for="dest_surveillant">Surveillants</label>

            </div>

        </div>

        <div class="bloc">

            <strong>2. Cibler les étudiants</strong>

            <p class="info">
                Choisissez une faculté, un niveau ou les deux.
                Le document sera destiné aux étudiants correspondant
                à ces critères.
            </p>

            <label for="id_fac">Faculté :</label>

            <select id="id_fac" name="id_fac">

                <option value="">
                    Choisir une faculté
                </option>

                <?php foreach ($facultes as $fac): ?>

                    <option
                        value="<?= (int) $fac['id_fac'] ?>"
                        <?= (string) $id_fac ===
                            (string) $fac['id_fac']
                            && $id_fac !== ''
                            ? 'selected'
                            : '' ?>
                    >
                        <?= htmlspecialchars(
                            $fac['nom_fac'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </option>

                <?php endforeach; ?>

            </select>

            <label for="id_niveau">Niveau :</label>

            <select id="id_niveau" name="id_niveau">

                <option value="">
                    Choisir un niveau
                </option>

                <?php foreach ($niveaux as $niv): ?>

                    <option
                        value="<?= (int) $niv['id_niveau'] ?>"
                        <?= (string) $id_niveau ===
                            (string) $niv['id_niveau']
                            && $id_niveau !== ''
                            ? 'selected'
                            : '' ?>
                    >
                        <?= htmlspecialchars(
                            $niv['nom_niveau'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </div>

        <div class="bloc">

            <strong>3. Choisir les surveillants</strong>

            <p class="info">
                Cliquez sur le bouton ci-dessous pour afficher
                la liste des surveillants.
            </p>

            <button
                type="submit"
                name="action"
                value="afficher_surveillants"
                formnovalidate
                class="bouton-secondaire"
            >
                Afficher la liste des surveillants
            </button>

            <div class="<?= $afficher_surveillants
                ? ''
                : 'liste-cachee' ?>">

                <label for="surveillants">
                    Sélectionner les surveillants :
                </label>

                <select
                    id="surveillants"
                    name="surveillants[]"
                    multiple
                >

                    <?php foreach ($surveillants as $surv): ?>

                        <option
                            value="<?= (int) $surv['id_surv'] ?>"
                            <?= in_array(
                                (int) $surv['id_surv'],
                                $surveillants_selectionnes,
                                true
                            ) ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars(
                                $surv['nom_surv']
                                . ' '
                                . $surv['prenom_surv'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

                <p class="info">
                    Pour sélectionner plusieurs surveillants,
                    maintenez la touche Ctrl enfoncée pendant
                    que vous cliquez sur leurs noms.
                </p>

            </div>

        </div>

        <button
            type="submit"
            name="action"
            value="enregistrer"
        >
            Enregistrer et partager
        </button>

    </form>

</div>

</body>
</html>
```
