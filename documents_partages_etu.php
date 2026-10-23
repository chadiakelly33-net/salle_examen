<?php
session_start();

require_once __DIR__ . '/connexionDB.php';

// 1. Vérifier que l'étudiant est connecté
if (
    !isset($_SESSION['id_etu']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'etudiant'
) {
    header('Location: login_etu.php');
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

    header('Location: login_etu.php?timeout=1');
    exit;
}

$_SESSION['last_activity'] = time();

$id_etu = (int) $_SESSION['id_etu'];

// 3. Récupérer la faculté et le niveau de l'étudiant
$requete = $bdd->prepare("
    SELECT id_fac, id_niveau
    FROM etudiant
    WHERE id_etu = ?
");

$requete->execute([$id_etu]);
$etudiant = $requete->fetch(PDO::FETCH_ASSOC);

if (!$etudiant) {
    session_unset();
    session_destroy();

    header('Location: login_etu.php');
    exit;
}

$id_fac = $etudiant['id_fac'];
$id_niveau = $etudiant['id_niveau'];

// 4. Récupérer les documents partagés avec cet étudiant,
// sa faculté ou son niveau
$sql = "
    SELECT DISTINCT
        d.id_document,
        d.nom_document,
        d.fichier,
        d.description,
        d.date_ajout
    FROM document d
    INNER JOIN partage_document p
        ON p.id_document = d.id_document
    WHERE p.type_destinataire = 'etudiant'
      AND (
          p.id_etu = :id_etu
          OR (
              p.id_fac IS NOT NULL
              AND p.id_fac = :id_fac
          )
          OR (
              p.id_niveau IS NOT NULL
              AND p.id_niveau = :id_niveau
          )
      )
    ORDER BY d.date_ajout DESC
";

$requete = $bdd->prepare($sql);

$requete->execute([
    'id_etu' => $id_etu,
    'id_fac' => $id_fac,
    'id_niveau' => $id_niveau
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
            background: #f2f5f9;
            margin: 0;
            padding: 25px;
        }

        .conteneur {
            max-width: 1000px;
            margin: 20px auto;
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.1);
        }

        h2 {
            text-align: center;
            color: #003366;
        }

        .retour {
            display: inline-block;
            background: #52677f;
            color: white;
            padding: 11px 16px;
            text-decoration: none;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .retour:hover {
            background: #3b4f66;
        }

        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th, td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: left;
        }

        th {
            background: #003366;
            color: white;
        }

        tr:nth-child(even) {
            background: #f7f9fc;
        }

        .action {
            display: inline-block;
            background: #176b3a;
            color: white;
            text-decoration: none;
            padding: 8px 12px;
            border-radius: 5px;
            margin: 3px;
            white-space: nowrap;
        }

        .action:hover {
            background: #10532c;
        }

        .vide {
            background: #fff4d6;
            padding: 15px;
            border-radius: 5px;
            color: #765b12;
        }

        @media (max-width: 600px) {
            body {
                padding: 10px;
            }

            .conteneur {
                padding: 15px;
                margin: 10px auto;
            }

            th, td {
                padding: 8px;
            }
        }
    </style>
</head>

<body>

<div class="conteneur">

    <h2>Mes documents partagés</h2>

    <a class="retour" href="espace_etu.php">
        Retour à mon espace
    </a>

    <?php if (empty($documents)): ?>

        <p class="vide">
            Aucun document ne vous a été partagé pour le moment.
        </p>

    <?php else: ?>

        <p>
            Nombre de documents disponibles :
            <strong><?= count($documents) ?></strong>
        </p>

        <div class="table-responsive">

            <table>
                <thead>
                    <tr>
                        <th>Nom du document</th>
                        <th>Description</th>
                        <th>Date d'ajout</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($documents as $document): ?>

                    <?php
                    $id_document = (int) $document['id_document'];
                    ?>

                    <tr>
                        <td>
                            <?= htmlspecialchars(
                                $document['nom_document'] ?? '',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $document['description'] ?? 'Aucune description',
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
                                class="action"
                                href="consulter_document_etu.php?id=<?= $id_document ?>"
                                target="_blank"
                                rel="noopener"
                            >
                                Consulter
                            </a>

                            <a
                                class="action"
                                href="telecharger_document_etu.php?id=<?= $id_document ?>"
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

