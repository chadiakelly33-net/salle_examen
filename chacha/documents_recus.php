<?php
session_start();
require_once "../connexionDB.php";
include("menu_principal.php");

if (($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: connexion.php");
    exit();
}

$sql = "SELECT d.*, p.type_destinataire AS type_expediteur,
               p.id_etu, p.id_surv
        FROM document d
        LEFT JOIN partage_document p
            ON p.id_document = d.id_document
        WHERE d.archive = 0
          AND d.id_admin = 0
        ORDER BY d.date_ajout DESC";

$stmt = $bdd->prepare($sql);
$stmt->execute();
$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Documents reçus - Administration</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f3f6fa;
    color: #263445;
}

.container {
    max-width: 1200px;
    margin: 35px auto;
    padding: 0 20px;
}

.header {
    background: #003366;
    color: white;
    padding: 25px 30px;
    border-radius: 10px;
    margin-bottom: 25px;
}

.header h1 {
    margin: 0 0 8px;
    font-size: 26px;
}

.header p {
    margin: 0;
    color: #dce8f5;
    font-size: 14px;
}

.actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}

.btn {
    display: inline-block;
    padding: 11px 18px;
    border-radius: 6px;
    text-decoration: none;
    font-size: 14px;
    font-weight: bold;
    border: none;
    cursor: pointer;
}

.btn-retour {
    background: #e3eaf2;
    color: #003366;
}

.btn-retour:hover {
    background: #d1dce9;
}

.card {
    background: white;
    border-radius: 10px;
    padding: 22px;
    box-shadow: 0 3px 12px rgba(0, 0, 0, 0.05);
}

.card h2 {
    color: #003366;
    font-size: 19px;
    margin: 0 0 20px;
}

.compteur {
    display: inline-block;
    background: #e6eef8;
    color: #003366;
    padding: 5px 11px;
    border-radius: 20px;
    font-size: 13px;
    margin-left: 7px;
}

.table-wrapper {
    width: 100%;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 700px;
}

thead {
    background: #003366;
    color: white;
}

th, td {
    padding: 15px 12px;
    text-align: left;
    border-bottom: 1px solid #e8edf3;
    font-size: 14px;
}

tbody tr:hover {
    background: #f7faff;
}

.badge {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 5px;
    background: #e6eef8;
    color: #003366;
    font-size: 12px;
    font-weight: bold;
}

.btn-telecharger {
    background: #003366;
    color: white;
}

.btn-telecharger:hover {
    background: #004b8d;
}

.vide {
    text-align: center;
    padding: 35px 15px;
    color: #718096;
}

.footer {
    margin-top: 20px;
    text-align: center;
    color: #7b8795;
    font-size: 12px;
}

@media (max-width: 600px) {
    .container {
        margin: 20px auto;
        padding: 0 12px;
    }

    .header {
        padding: 20px;
    }

    .header h1 {
        font-size: 22px;
    }

    .card {
        padding: 15px;
    }
}
</style>
</head>

<body>

<div class="container">

    <div class="header">
        <h1>Documents reçus</h1>
        <p>Administration · Consultation des documents transmis par les étudiants et les surveillants.</p>
    </div>

    <div class="actions">
        <a href="dashboard.php" class="btn btn-retour">
            &larr; Retour au tableau de bord
        </a>
    </div>

    <div class="card">

        <h2>
            Liste des documents
            <span class="compteur"><?= count($documents) ?></span>
        </h2>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>N°</th>
                        <th>Nom du document</th>
                        <th>Description</th>
                        <th>Expéditeur</th>
                        <th>Date de réception</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                <?php if ($documents): ?>

                    <?php foreach ($documents as $doc): ?>
                    <tr>
                        <td><?= (int)$doc['id_document'] ?></td>

                        <td>
                            <strong>
                                <?= htmlspecialchars($doc['nom_document']) ?>
                            </strong>
                        </td>

                        <td>
                            <?= htmlspecialchars($doc['description'] ?? 'Aucune description') ?>
                        </td>

                        <td>
                            <?php
                            if ($doc['type_expediteur'] === 'etudiant') {
                                echo '<span class="badge">Étudiant</span> ';
                                echo 'N° ' . (int)$doc['id_etu'];
                            } elseif ($doc['type_expediteur'] === 'surveillant') {
                                echo '<span class="badge">Surveillant</span> ';
                                echo 'N° ' . (int)$doc['id_surv'];
                            } else {
                                echo 'Non identifié';
                            }
                            ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($doc['date_ajout'] ?? '') ?>
                        </td>

                        <td>
                            <a
                                class="btn btn-telecharger"
                                href="../telecharger_document.php?id=<?= (int)$doc['id_document'] ?>"
                            >
                                Télécharger
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="6" class="vide">
                            Aucun document reçu pour le moment.
                        </td>
                    </tr>

                <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

    <div class="footer">
        Gestion des documents · Administration
    </div>

</div>

</body>
</html>