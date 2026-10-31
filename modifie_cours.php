<?php
session_start();

// 1. Vérifier la connexion de l'administrateur
if (
    !isset($_SESSION["id_admin"]) ||
    ($_SESSION["role"] ?? "") !== "admin"
) {
    header("Location: connexion.php");
    exit();
}

// 2. Connexion à la base de données
require_once __DIR__ . "/connexionDB.php";

// 3. Vérifier l'identifiant du cours
if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    header("Location: afficher_cours.php");
    exit();
}

$id = (int) $_GET["id"];

// 4. Récupérer les informations du cours
$req = $bdd->prepare(
    "SELECT * FROM cours WHERE id_cours = ?"
);
$req->execute([$id]);

$data = $req->fetch(PDO::FETCH_ASSOC);

// 5. Vérifier si le cours existe
if (!$data) {
    header("Location: afficher_cours.php?erreur=introuvable");
    exit();
}

// Fonction de sécurisation
function securiser($valeur) {
    return htmlspecialchars(
        (string)($valeur ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}
?>

<!DOCTYPE html>

<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

```
<title>Modifier un cours</title>

<style>
    * {
        box-sizing: border-box;
    }

    body {
        font-family: Arial, sans-serif;
        background: #f4f6f9;
        margin: 0;
        color: #333;
    }

    .container {
        max-width: 650px;
        margin: 40px auto;
        margin-left: calc(230px + (100% - 230px - 650px) / 2);
        background: white;
        padding: 30px;
        border-radius: 10px;
        box-shadow: 0 3px 12px rgba(0,0,0,0.08);
    }

    h2 {
        color: #003366;
        text-align: center;
        margin-bottom: 25px;
    }

    label {
        display: block;
        margin-top: 17px;
        font-weight: bold;
        color: #003366;
    }

    input,
    select {
        display: block;
        width: 100%;
        padding: 12px;
        margin-top: 7px;
        border: 1px solid #ccc;
        border-radius: 6px;
        font-size: 14px;
    }

    input:focus,
    select:focus {
        outline: none;
        border-color: #003366;
        box-shadow: 0 0 4px rgba(0,51,102,0.15);
    }

    button {
        width: 100%;
        padding: 13px;
        margin-top: 25px;
        background: #003366;
        color: white;
        border: none;
        border-radius: 6px;
        font-size: 15px;
        font-weight: bold;
        cursor: pointer;
        transition: 0.3s;
    }

    button:hover {
        background: #d4a72c;
        color: #003366;
    }

    .retour {
        display: block;
        text-align: center;
        margin-top: 20px;
        color: #003366;
        text-decoration: none;
        font-weight: bold;
    }

    .retour:hover {
        color: #d4a72c;
    }

    @media (max-width: 768px) {
        .container {
            width: calc(100% - 30px);
            margin: 25px auto;
            padding: 20px;
        }
    }
</style>
```

</head>

<body>

<?php
// 6. Afficher le menu principal
require_once __DIR__ . "/menu_principal.php";
?>

<div class="container">

```
<h2>Modifier le cours</h2>

<form method="POST" action="update_cours.php">

    <input
        type="hidden"
        name="id"
        value="<?= securiser($data['id_cours']) ?>"
    >

    <label>Nom du cours</label>
    <input
        type="text"
        name="nom_cours"
        value="<?= securiser($data['nom_cours']) ?>"
        required
    >

    <label>Crédit</label>
    <input
        type="number"
        name="credit"
        value="<?= securiser($data['credit']) ?>"
        required
    >

    <label>Niveau d'étude</label>
    <select name="id_niveau" required>

        <?php
        $niv = $bdd->query(
            "SELECT * FROM niveau_etude"
        );

        while ($n = $niv->fetch(PDO::FETCH_ASSOC)) {
            $selected = (
                $n['id_niveau'] == $data['id_niveau']
            ) ? "selected" : "";
        ?>

            <option
                value="<?= securiser($n['id_niveau']) ?>"
                <?= $selected ?>
            >
                <?= securiser($n['nom_niveau']) ?>
            </option>

        <?php } ?>

    </select>

    <label>Faculté</label>
    <select name="id_fac" required>

        <?php
        $fac = $bdd->query(
            "SELECT * FROM faculte"
        );

        while ($f = $fac->fetch(PDO::FETCH_ASSOC)) {
            $selected = (
                $f['id_fac'] == $data['id_fac']
            ) ? "selected" : "";
        ?>

            <option
                value="<?= securiser($f['id_fac']) ?>"
                <?= $selected ?>
            >
                <?= securiser($f['nom_fac']) ?>
            </option>

        <?php } ?>

    </select>

    <button type="submit">
        Enregistrer les modifications
    </button>

</form>

<a href="afficher_cours.php" class="retour">
    Retour à la liste des cours
</a>
```

</div>

</body>
</html>
