<?php
require_once __DIR__ . "/menu_principal.php";
require_once __DIR__ . "/connexionDB.php";

$message = "";

$stmt = $bdd->query("SELECT * FROM parametres LIMIT 1");
$parametres = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $nom = trim($_POST["nom_site"] ?? "");
    $email = trim($_POST["email_contact"] ?? "");
    $telephone = trim($_POST["telephone_contact"] ?? "");
    $duree = (int)($_POST["duree_session"] ?? 300);

    if (
        $nom === "" ||
        ($email !== "" && !filter_var($email, FILTER_VALIDATE_EMAIL)) ||
        $duree < 60
    ) {
        $message = "Veuillez vérifier les informations saisies.";
    } else {
        $update = $bdd->prepare("
            UPDATE parametres
            SET nom_site = ?,
                email_contact = ?,
                telephone_contact = ?,
                duree_session = ?
            WHERE id = ?
        ");

        $update->execute([
            $nom,
            $email,
            $telephone,
            $duree,
            $parametres["id"]
        ]);

        $message = "Paramètres enregistrés avec succès.";

        $stmt = $bdd->query("SELECT * FROM parametres LIMIT 1");
        $parametres = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>

<head>

<style>
* {
    box-sizing: border-box;
}

html, body {
    margin: 0;
    padding: 0;
    width: 100%;
    overflow-x: hidden;
}

body {
    font-family: Arial, sans-serif;
    background: #f4f6f9;
}

/* CONTENU À CÔTÉ DU MENU */
.page-content {
    margin-left: 305px;
    width: calc(100% - 305px);
    padding: 12px 25px 30px;
    min-height: 100vh;
}

/* TITRE */
.page-content h2 {
    color: #003366;
    font-size: 25px;
    margin: 0 0 20px;
    padding-bottom: 12px;
    border-bottom: 3px solid #ffd700;
}

/* FORMULAIRE */
.page-content form {
    width: 100%;
    max-width: 850px;
    margin: 0;
    padding: 25px 30px;
    background: white;
    border-radius: 10px;
    border-top: 5px solid #003366;
    box-shadow: 0 3px 12px rgba(0,0,0,0.08);
}

/* LABELS */
.page-content label {
    display: block;
    color: #003366;
    font-weight: bold;
    font-size: 14px;
    margin-bottom: 8px;
}

/* CHAMPS */
.page-content input {
    display: block;
    width: 100%;
    padding: 12px;
    margin-bottom: 18px;
    border: 1px solid #d5dce5;
    border-radius: 6px;
    background: #f9fafb;
    font-size: 14px;
    outline: none;
}

.page-content input:focus {
    border-color: #003366;
    box-shadow: 0 0 0 3px rgba(0,51,102,0.10);
}

/* BOUTON */
.page-content button {
    width: 100%;
    padding: 13px;
    background: #003366;
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 15px;
    font-weight: bold;
    cursor: pointer;
}

.page-content button:hover {
    background: #ffd700;
    color: #003366;
}

/* MESSAGE */
.page-content .message {
    max-width: 850px;
    padding: 12px;
    margin-bottom: 15px;
    background: #e8f5e9;
    color: #236b38;
    border-left: 4px solid #236b38;
    border-radius: 5px;
}

/* PETITS ÉCRANS */
@media (max-width: 768px) {
    .page-content {
        margin-left: 0;
        width: 100%;
        padding: 15px;
    }

    .page-content form {
        padding: 20px 15px;
    }
}
</style>

</head>

<div class="page-content">

    <h2>Paramétrage du système</h2>

    <?php if ($message !== ""): ?>
        <p style="color:green;">
            <?= htmlspecialchars($message) ?>
        </p>
    <?php endif; ?>

    <form method="POST">

        <label>Nom du système</label><br>
        <input type="text" name="nom_site"
               value="<?= htmlspecialchars($parametres["nom_site"]) ?>"
               required>
        <br><br>

        <label>Email de contact</label><br>
        <input type="email" name="email_contact"
               value="<?= htmlspecialchars($parametres["email_contact"] ?? "") ?>">
        <br><br>

        <label>Téléphone de contact</label><br>
        <input type="text" name="telephone_contact"
               value="<?= htmlspecialchars($parametres["telephone_contact"] ?? "") ?>">
        <br><br>

        <label>Durée de session (en secondes)</label><br>
        <input type="number" name="duree_session" min="60"
               value="<?= (int)$parametres["duree_session"] ?>"
               required>
        <br><br>

        <button type="submit">Enregistrer</button>

    </form>

</div>