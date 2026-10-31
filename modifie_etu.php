<?php
session_start();

if (
    !isset($_SESSION["id_admin"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: connexion.php");
    exit();
}

require_once("connexionDB.php");
require_once("journal.php");

// Vérification de l'identifiant
$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$id) {
    header("Location: afficher_etu.php");
    exit();
}

// Récupération de l'étudiant
$req = $bdd->prepare("SELECT * FROM etudiant WHERE id_etu = ?");
$req->execute([$id]);
$data = $req->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    header("Location: afficher_etu.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Modifier un étudiant</title>

<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: Arial, Helvetica, sans-serif;
}

body {
    background: #f4f6f9;
    color: #333;
}

/* Contenu à droite du menu */
.contenu {
    width: 100%;
    padding: 30px 20px;
    display: flex;
    justify-content: center;
    align-items: flex-start;
}

/* Formulaire */
.form-container {
    width: 100%;
    max-width: 520px;
    background: #fff;
    padding: 30px;
    border-radius: 10px;
    box-shadow: 0 3px 12px rgba(0,0,0,.10);
}

/* Titre */
h2 {
    color: #003366;
    margin-bottom: 25px;
    text-align: center;
    font-size: 24px;
}

/* Labels */
label {
    display: block;
    margin-bottom: 6px;
    color: #333;
    font-weight: 600;
    font-size: 14px;
}

/* Champs */
input,
select {
    width: 100%;
    padding: 11px;
    margin-bottom: 18px;
    border: 1px solid #d6dce5;
    border-radius: 6px;
    font-size: 14px;
    background: #fff;
}

input:focus,
select:focus {
    outline: none;
    border-color: #003366;
    box-shadow: 0 0 0 2px rgba(0,51,102,.08);
}

/* Photo actuelle */
.photo-actuelle {
    display: block;
    width: 110px;
    height: 110px;
    margin: 8px 0 18px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #e5eaf0;
}

.aucune-photo {
    color: #777;
    font-size: 14px;
    margin: 8px 0 18px;
}

/* Bouton */
button {
    width: 100%;
    padding: 12px;
    background: #003366;
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 15px;
    font-weight: bold;
    cursor: pointer;
    transition: .3s;
}

button:hover {
    background: #004b8d;
}

/* Adaptation mobile */
@media screen and (max-width: 768px) {
    .contenu {
        padding: 20px 12px;
    }

    .form-container {
        padding: 22px 18px;
    }

    h2 {
        font-size: 21px;
    }
}

</style>
</head>

<body>

<?php
// Menu latéral administrateur
require_once("Menu_principal.php");
?>

<div class="contenu">

    <div class="form-container">

        <h2>Modifier un étudiant</h2>

        <form
            action="update_etu.php"
            method="POST"
            enctype="multipart/form-data"
        >

            <input
                type="hidden"
                name="id"
                value="<?= (int)$data['id_etu'] ?>"
            >

            <label>Nom :</label>
            <input
                type="text"
                name="nom"
                value="<?= htmlspecialchars($data['nom_etu'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                required
            >

            <label>Prénom :</label>
            <input
                type="text"
                name="prenom"
                value="<?= htmlspecialchars($data['prenom_etu'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                required
            >

            <label>Adresse e-mail :</label>
            <input
                type="email"
                name="email"
                value="<?= htmlspecialchars($data['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                required
            >

            <label>Photo actuelle :</label>

            <?php
            $photoActuelle = $data['photo'] ?? '';
            $cheminPhoto = "uploads/" . $photoActuelle;
            ?>

            <?php if (!empty($photoActuelle) && file_exists($cheminPhoto)): ?>

                <img
                    src="<?= htmlspecialchars($cheminPhoto, ENT_QUOTES, 'UTF-8') ?>"
                    class="photo-actuelle"
                    alt="Photo actuelle de l'étudiant"
                >

            <?php else: ?>

                <p class="aucune-photo">Aucune photo disponible.</p>

            <?php endif; ?>

            <label>Nouvelle photo :</label>
            <input
                type="file"
                name="photo"
                accept="image/*"
            >

            <label>Niveau :</label>

            <select name="id_niveau" required>

                <option value="">-- Choisir un niveau --</option>

                <?php
                $niv = $bdd->query("SELECT * FROM niveau_etude");

                while ($n = $niv->fetch(PDO::FETCH_ASSOC)):

                    $sel = ($n['id_niveau'] == $data['id_niveau'])
                        ? "selected"
                        : "";
                ?>

                    <option
                        value="<?= (int)$n['id_niveau'] ?>"
                        <?= $sel ?>
                    >
                        <?= htmlspecialchars($n['nom_niveau'], ENT_QUOTES, 'UTF-8') ?>
                    </option>

                <?php endwhile; ?>

            </select>

            <label>Faculté :</label>

            <select name="id_fac" required>

                <option value="">-- Choisir une faculté --</option>

                <?php
                $fac = $bdd->query("SELECT * FROM faculte");

                while ($f = $fac->fetch(PDO::FETCH_ASSOC)):

                    $sel = ($f['id_fac'] == $data['id_fac'])
                        ? "selected"
                        : "";
                ?>

                    <option
                        value="<?= (int)$f['id_fac'] ?>"
                        <?= $sel ?>
                    >
                        <?= htmlspecialchars($f['nom_fac'], ENT_QUOTES, 'UTF-8') ?>
                    </option>

                <?php endwhile; ?>

            </select>

            <button type="submit">
                Enregistrer les modifications
            </button>

        </form>

    </div>

</div>

</body>
</html>

