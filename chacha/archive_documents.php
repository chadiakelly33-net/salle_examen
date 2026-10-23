<?php
session_start();

// 1. Vérifier que l'utilisateur est administrateur
if (
    !isset($_SESSION['id_admin']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin'
) {
    header('Location: connexion.php');
    exit();
}

// 2. Connexion à la base de données
require_once 'connexionDB.php';

// 3. Créer le jeton de sécurité s'il n'existe pas
if (
    !isset($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token']) ||
    $_SESSION['csrf_token'] === ''
) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 4. Initialiser les variables
$message = '';
$archives = [];

function echapper($valeur)
{
    return htmlspecialchars(
        (string) ($valeur ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// 5. Afficher le résultat d'une éventuelle restauration
if (isset($_GET['restauration'])) {
    switch ($_GET['restauration']) {
        case 'reussie':
            $message = 'Le document a été restauré avec succès.';
            break;

        case 'fichier_absent':
            $message = 'Restauration impossible : le fichier physique est introuvable.';
            break;

        case 'introuvable':
            $message = 'Le document archivé est introuvable.';
            break;

        case 'fichier_invalide':
            $message = 'Le nom du fichier archivé est invalide.';
            break;

        case 'erreur':
            $message = 'La restauration a échoué. Vérifiez les informations de la base de données.';
            break;

        default:
            $message = '';
    }
}

// 6. Charger les documents archivés
try {
    $sql = "
        SELECT
            id_archive,
            id_document_original,
            nom_document,
            fichier,
            description,
            date_ajout_original,
            date_suppression,
            supprime_par
        FROM archive_suppression_document
        ORDER BY date_suppression DESC
    ";

    $stmt = $bdd->prepare($sql);
    $stmt->execute();

    $archives = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log(
        'Erreur chargement archives : ' . $e->getMessage()
    );

    $message = 'Impossible de charger les archives. Vérifiez la base de données.';
}
?>

<html lang="fr">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Archive des documents supprimés</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f7fb;
            margin: 0;
            padding: 25px;
            color: #243247;
        }

        .conteneur {
            max-width: 1300px;
            margin: 20px auto;
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.08);
        }

        h1 {
            color: #234b75;
            text-align: center;
        }

        .retour {
            display: inline-block;
            background: #234b75;
            color: white;
            padding: 10px 15px;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 0 20px;
        }

        .retour:hover {
            background: #183653;
        }

        .resume {
            background: #eaf2fb;
            border-left: 4px solid #234b75;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }

        .message {
            background: #e8f5e9;
            color: #245b2a;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 15px;
            line-height: 1.5;
        }

        .erreur {
            background: #ffe5e5;
            color: #a82323;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 15px;
        }

        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        th,
        td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #234b75;
            color: white;
            white-space: nowrap;
        }

        tr:nth-child(even) {
            background: #f7f9fc;
        }

        .vide {
            text-align: center;
            padding: 25px;
            color: #666;
        }

        .description {
            min-width: 180px;
            overflow-wrap: anywhere;
        }

        .nom {
            min-width: 150px;
        }

        .btn-restaurer {
            background: #24743b;
            color: white;
            padding: 9px 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }

        .btn-restaurer:hover {
            background: #19572b;
        }

        .actions {
            white-space: nowrap;
        }
    </style>
</head>

<body>

<div class="conteneur">

    <h1>Archive des documents supprimés</h1>

    <p>
        Consultez les documents archivés et restaurez ceux
        que vous souhaitez remettre dans la liste active.
    </p>

    <a class="retour" href="afficher_documents.php">
        Retour aux documents
    </a>

    <?php if ($message !== ''): ?>

        <div class="<?= isset($_GET['restauration']) && $_GET['restauration'] === 'reussie' ? 'message' : 'erreur' ?>">
            <?= echapper($message) ?>
        </div>

    <?php endif; ?>

    <div class="resume">
        Nombre total de documents archivés :
        <strong><?= count($archives) ?></strong>
    </div>

    <div class="table-responsive">

        <table>

            <thead>
                <tr>
                    <th>N° archive</th>
                    <th>N° document original</th>
                    <th>Nom du document</th>
                    <th>Description</th>
                    <th>Date d'ajout initiale</th>
                    <th>Date de suppression</th>
                    <th>ID administrateur</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>

            <?php if (!empty($archives)): ?>

                <?php foreach ($archives as $archive): ?>

                    <tr>

                        <td>
                            <?= (int) $archive['id_archive'] ?>
                        </td>

                        <td>
                            <?= (int) $archive['id_document_original'] ?>
                        </td>

                        <td class="nom">
                            <?= echapper($archive['nom_document']) ?>
                        </td>

                        <td class="description">
                            <?= nl2br(echapper($archive['description'])) ?>
                        </td>

                        <td>
                            <?= echapper($archive['date_ajout_original']) ?>
                        </td>

                        <td>
                            <?= echapper($archive['date_suppression']) ?>
                        </td>

                        <td>
                            <?= echapper($archive['supprime_par']) ?>
                        </td>

                        <td class="actions">

                            <form
                                method="POST"
                                action="restaurer_document.php"
                                onsubmit="return confirm('Voulez-vous vraiment restaurer ce document ?');"
                            >

                                <input
                                    type="hidden"
                                    name="id_archive"
                                    value="<?= (int) $archive['id_archive'] ?>"
                                >

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= echapper($_SESSION['csrf_token']) ?>"
                                >

                                <button
                                    type="submit"
                                    class="btn-restaurer"
                                >
                                    Restaurer
                                </button>

                            </form>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php else: ?>

                <tr>
                    <td colspan="8" class="vide">
                        Aucune archive disponible.
                    </td>
                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

</body>
</html>