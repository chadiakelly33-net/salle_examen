<?php
session_start();

if (
    !isset($_SESSION["id_admin"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] != "admin"
) {
    header("Location: connexion.php");
    exit();
}

include("connexionDB.php");
?>

<!DOCTYPE html>
<html lang="fr">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Liste des étudiants</title>

<style>
body {
    font-family: Arial, sans-serif;
    background: #f9fafb;
    margin: 0;
}

h2 {
    text-align: center;
    color: #003366;
    margin: 25px 0;
}

.contenu {
    margin-left: 240px;
    padding: 20px;
}

.table-container {
    width: 100%;
    overflow-x: auto;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,.08);
}

table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    min-width: 850px;
}

th {
    background: #003366;
    color: white;
    padding: 12px;
    border-bottom: 1px solid #ddd;
    text-align: center;
}

td {
    padding: 12px;
    text-align: center;
    border-bottom: 1px solid #eee;
}

tr:hover {
    background: #f1f6fb;
}

td img {
    border-radius: 50%;
    object-fit: cover;
}

a.action {
    text-decoration: none;
    padding: 7px 10px;
    border-radius: 5px;
    margin: 3px;
    display: inline-block;
    font-size: 13px;
}

a.modifier {
    background: #e3f2fd;
    color: #1565c0;
}

a.supprimer {
    background: #f5f5f5;
    color: #555;
}

a.action:hover {
    opacity: .8;
}

@media (max-width: 700px) {
    .contenu {
        margin-left: 0;
        padding: 12px;
    }
}
</style>
</head>

<body>

<aside id="menu" class="menu">
    <?php include("Menu_principal.php"); ?>
</aside>

<div class="contenu">

    <h2>Liste des étudiants</h2>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Photo</th>
                    <th>Nom</th>
                    <th>Prénom</th>
                    <th>Niveau</th>
                    <th>Faculté</th>
                    <th>Login</th>
                    <th>E-mail</th>
                    <th>Actions</th>
                </tr>
            </thead>

            <tbody>
            <?php
            $req = $bdd->query("
                SELECT
                    e.id_etu,
                    e.nom_etu,
                    e.prenom_etu,
                    e.photo,
                    e.login,
                    e.email,
                    n.nom_niveau,
                    f.nom_fac
                FROM etudiant e
                JOIN niveau_etude n
                    ON e.id_niveau = n.id_niveau
                JOIN faculte f
                    ON e.id_fac = f.id_fac
                ORDER BY e.nom_etu ASC
            ");

            while ($row = $req->fetch(PDO::FETCH_ASSOC)) {
            ?>

                <tr>
                    <td>
                        <?php
                        $photoPath = "uploads/" . ($row["photo"] ?? "");
                        if (
                            !empty($row["photo"]) &&
                            file_exists($photoPath)
                        ) {
                        ?>
                            <img
                                src="<?= htmlspecialchars($photoPath, ENT_QUOTES, 'UTF-8') ?>"
                                width="60"
                                height="60"
                                alt="Photo de l'étudiant"
                            >
                        <?php } else { ?>
                            <img
                                src="uploads/default.png"
                                width="60"
                                height="60"
                                alt="Photo par défaut"
                            >
                        <?php } ?>
                    </td>

                    <td><?= htmlspecialchars($row["nom_etu"] ?? "", ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($row["prenom_etu"] ?? "", ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($row["nom_niveau"] ?? "", ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($row["nom_fac"] ?? "", ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($row["login"] ?? "", ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($row["email"] ?? "Non renseigné", ENT_QUOTES, 'UTF-8') ?></td>

                    <td>
                        <a
                            class="action modifier"
                            href="modifie_etu.php?id=<?= (int)$row['id_etu'] ?>"
                        >Modifier</a>

                        <a
                            class="action supprimer"
                            href="supprimer_etu.php?id=<?= (int)$row['id_etu'] ?>"
                            onclick="return confirm('Supprimer cet étudiant ?')"
                        >Supprimer</a>
                    </td>
                </tr>

            <?php } ?>
            </tbody>
        </table>
    </div>

</div>

</body>
</html>

