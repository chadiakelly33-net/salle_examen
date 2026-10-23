<?php
session_start();

require_once __DIR__ . '/connexionDB.php';

// 1. Vérifier la connexion du surveillant
if (
    !isset($_SESSION['id_surv']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'surveillant'
) {
    header('Location: login_surv.php');
    exit;
}

// 2. Vérifier le délai d'inactivité : 5 minutes
$timeout = 300;

if (
    isset($_SESSION['last_activity']) &&
    time() - $_SESSION['last_activity'] > $timeout
) {
    session_unset();
    session_destroy();

    header('Location: login_surv.php?timeout=1');
    exit;
}

$_SESSION['last_activity'] = time();

$id_surv = (int) $_SESSION['id_surv'];

// 3. Récupérer les documents partagés avec ce surveillant
$requete = $bdd->prepare("
    SELECT DISTINCT
        d.id_document,
        d.nom_document,
        d.description,
        d.date_ajout
    FROM document d
    INNER JOIN partage_document p
        ON p.id_document = d.id_document
    WHERE p.type_destinataire = 'surveillant'
      AND p.id_surv = :id_surv
    ORDER BY d.date_ajout DESC
");

$requete->execute([
    'id_surv' => $id_surv
]);

$documents = $requete->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Documents partagés</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            margin: 0;
            padding: 25px;
            color: #333;
        }

        .container {
            max-width: 1100px;
            margin: auto;
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        }

        h2 {
            color: #003366;
            margin-bottom: 10px;
        }

        .description {
            color: #666;
            margin-bottom: 25px;
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 13px;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }

        th {
            background: #003366;
            color: white;
        }

        tr:hover {
            background: #f5f8fc;
        }

        .action {
            display: inline-block;
            padding: 8px 12px;
            margin: 3px;
            border-radius: 5px;
            text-decoration: none;
            color: white;
            font-size: 13px;
        }

        .consulter {
            background: #003366;
        }

        .telecharger {
            background: #198754;
        }

        .action:hover {
            opacity: 0.85;
        }

        .vide {
            padding: 20px;
            text-align: center;
            color: #666;
        }
    </style>
</head>

<body>

<div class="container">

    <h2>Documents partagés</h2>

    <p class="description">
        Consultez les documents mis à votre disposition par l'administration.
    </p>

    <?php if (empty($documents)): ?>

        <div class="vide">
            Aucun document ne vous a été partagé pour le moment.
        </div>

    <?php else: ?>

        <div class="table-container">

            <table>
                <thead>
                    <tr>
                        <th>N°</th>
                        <th>Nom du document</th>
                        <th>Description</th>
                        <th>Date d'ajout</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($documents as $document): ?>

                    <?php $id_document = (int) $document['id_document']; ?>

                    <tr>
                        <td><?= $id_document ?></td>

                        <td>
                            <?= htmlspecialchars(
                                $document['nom_document'] ?? '',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $document['description'] ?? '',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $document['date_ajout'] ?? '',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </td>

                        <td>
                            <a
                                class="action consulter"
                                href="consulter_document_surv.php?id=<?= $id_document ?>"
                                target="_blank"
                                rel="noopener"
                            >
                                Consulter
                            </a>

                            <a
                                class="action telecharger"
                                href="telecharger_document_surv.php?id=<?= $id_document ?>"
                            >
                                Télécharger
                            </a>
                        </td>
                    </tr>

                <?php endforeach; ?>

                </tbody>
            </table>

        </div>

    <?php endif; ?>

</div>

</body>
</html>