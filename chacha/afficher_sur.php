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

include("connexionDB.php");

$bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
?>

<!DOCTYPE html>
<html lang="fr">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Liste des surveillants</title>

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: Arial, sans-serif;
    background: #f4f6f9;
}

.container {
    width: calc(100% - 250px);
    max-width: 1200px;
    margin: 30px 0 30px 240px;
    padding: 0 20px;
}

.titre {
    text-align: center;
    color: #003366;
    margin-bottom: 20px;
}

.btn-afficher {
    display: inline-block;
    text-decoration: none;
    background: #003366;
    color: white;
    padding: 10px 20px;
    border-radius: 5px;
    margin-bottom: 20px;
}

.btn-afficher:hover {
    background: #0055aa;
}

.table-container {
    width: 100%;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

th {
    background: #003366;
    color: white;
    padding: 12px;
}

td {
    padding: 12px;
    text-align: center;
    border-bottom: 1px solid #ddd;
}

tr:hover {
    background: #f5f5f5;
}

.photo-profil {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #003366;
}

.photo-vide {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    background: #ddd;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #666;
    font-size: 12px;
}

.btn-modifier {
    display: inline-block;
    text-decoration: none;
    background: #28a745;
    color: white;
    padding: 8px 15px;
    border-radius: 5px;
    margin: 3px;
}

.btn-modifier:hover {
    background: #218838;
}

.btn-supprimer {
    display: inline-block;
    text-decoration: none;
    background: #dc3545;
    color: white;
    padding: 8px 15px;
    border-radius: 5px;
    margin: 3px;
}

.btn-supprimer:hover {
    background: #c82333;
}

@media (max-width: 768px) {
    .container {
        width: 100%;
        margin: 20px 0;
        padding: 0 10px;
    }

    th, td {
        padding: 8px;
    }
}
</style>

</head>

<body>

<!-- MENU ADMIN -->
<aside id="menu" class="menu">
    <?php include("Menu_principal.php"); ?>
</aside>

<div class="container">

    <h1 class="titre">Liste des surveillants</h1>
	
<?php if (isset($_GET["modification"]) && $_GET["modification"] === "success") : ?>
    <div style="
        background:#d4edda;
        color:#155724;
        padding:12px;
        margin-bottom:20px;
        border-radius:5px;
        text-align:center;
    ">
        Les informations du surveillant ont été modifiées avec succès !
    </div>
<?php endif; ?>

<?php if (isset($_GET["suppression"]) && $_GET["suppression"] === "success") : ?>
    <div style="background:#d4edda;color:#155724;padding:12px;margin-bottom:20px;border-radius:5px;text-align:center;">
        Le surveillant a été supprimé avec succès !
    </div>
<?php endif; ?>

<?php if (isset($_GET["erreur"]) && $_GET["erreur"] === "introuvable") : ?>
    <div style="background:#f8d7da;color:#721c24;padding:12px;margin-bottom:20px;border-radius:5px;text-align:center;">
        Ce surveillant n'existe pas ou a déjà été supprimé.
    </div>
<?php endif; ?>

<?php if (isset($_GET["erreur"]) && $_GET["erreur"] === "suppression") : ?>
    <div style="background:#f8d7da;color:#721c24;padding:12px;margin-bottom:20px;border-radius:5px;text-align:center;">
        Impossible de supprimer ce surveillant. Vérifie s'il est lié à des affectations.
    </div>
<?php endif; ?>



	
<?php if (isset($_GET["ajout"]) && $_GET["ajout"] === "success") : ?>
    <div style="
        background:#d4edda;
        color:#155724;
        padding:12px;
        margin-bottom:20px;
        border-radius:5px;
        text-align:center;
    ">
        Surveillant ajouté avec succès !
    </div>
<?php endif; ?>


    <a href="ajouter_sur.php" class="btn-afficher">
        + Ajouter un surveillant
    </a>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Photo</th>
                    <th>Nom</th>
                    <th>Prénom</th>
                    <th>Login</th>
                    <th>Adresse e-mail</th>
                    <th>Actions</th>
                </tr>
            </thead>

            <tbody>
            <?php
            $sql = "
                SELECT
                    id_surv,
                    nom_surv,
                    prenom_surv,
                    login,
                    email,
                    photo
                FROM surveillant
                ORDER BY nom_surv ASC, prenom_surv ASC
            ";

            $stmt = $bdd->query($sql);

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            ?>

                <tr>
                    <!-- PHOTO -->
                    <td>
                        <?php
                        if (
                            !empty($row["photo"]) &&
                            file_exists("uploads/" . $row["photo"])
                        ) {
                        ?>
                            <img
                                src="uploads/<?= htmlspecialchars($row["photo"], ENT_QUOTES, 'UTF-8') ?>"
                                alt="Photo de profil"
                                class="photo-profil"
                            >
                        <?php
                        } else {
                        ?>
                            <div class="photo-vide">Pas de photo</div>
                        <?php
                        }
                        ?>
                    </td>

                    <!-- NOM -->
                    <td>
                        <?= htmlspecialchars($row["nom_surv"], ENT_QUOTES, 'UTF-8') ?>
                    </td>

                    <!-- PRENOM -->
                    <td>
                        <?= htmlspecialchars($row["prenom_surv"], ENT_QUOTES, 'UTF-8') ?>
                    </td>

                    <!-- LOGIN -->
                    <td>
                        <?= htmlspecialchars($row["login"], ENT_QUOTES, 'UTF-8') ?>
                    </td>

                    <!-- EMAIL -->
                    <td>
                        <?= !empty($row["email"])
                            ? htmlspecialchars($row["email"], ENT_QUOTES, 'UTF-8')
                            : "Non renseigné" ?>
                    </td>

                    <!-- ACTIONS -->
                    <td>
                        <a
                            href="modifie_sur.php?id=<?= (int)$row["id_surv"] ?>"
                            class="btn-modifier"
                        >
                            Modifier
                        </a>

                        <a
                            href="supprimer_surv.php?id=<?= (int)$row["id_surv"] ?>"
                            class="btn-supprimer"
                            onclick="return confirm('Voulez-vous vraiment supprimer ce surveillant ?');"
                        >
                            Supprimer
                        </a>
                    </td>
                </tr>

            <?php
            }
            ?>
            </tbody>
        </table>
    </div>

</div>

</body>
</html>

