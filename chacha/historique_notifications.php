<?php
session_start();

if (
    !isset($_SESSION['id_admin']) ||
    ($_SESSION['role'] ?? '') !== 'admin'
) {
    header('Location: ../connexion.php');
    exit();
}

require_once __DIR__ . '/../connexionDB.php';

$messageErreur = '';
$filtreStatut = $_GET['statut'] ?? '';
$filtreLecture = $_GET['lecture'] ?? '';
$recherche = trim($_GET['q'] ?? '');

$statutsAutorises = ['envoye', 'echec', 'en_attente'];

if (!in_array($filtreStatut, $statutsAutorises, true)) {
    $filtreStatut = '';
}

if (!in_array($filtreLecture, ['', 'lu', 'non_lu'], true)) {
    $filtreLecture = '';
}

try {
    $statistiques = $bdd->query("
        SELECT
            COUNT(*) AS total,
            COALESCE(SUM(statut_envoi = 'envoye'), 0) AS envoyes,
            COALESCE(SUM(statut_envoi = 'echec'), 0) AS echecs,
            COALESCE(SUM(statut_envoi = 'en_attente'), 0) AS en_attente,
            COALESCE(SUM(lu = 1), 0) AS lues,
            COALESCE(SUM(lu = 0), 0) AS non_lues
        FROM notification
    ")->fetch(PDO::FETCH_ASSOC);

    $conditions = [];
    $parametres = [];

    if ($filtreStatut !== '') {
        $conditions[] = 'n.statut_envoi = ?';
        $parametres[] = $filtreStatut;
    }

    if ($filtreLecture === 'lu') {
        $conditions[] = 'n.lu = 1';
    } elseif ($filtreLecture === 'non_lu') {
        $conditions[] = 'n.lu = 0';
    }

    if ($recherche !== '') {
        $conditions[] = "(
            n.email_destinataire LIKE ?
            OR n.objet LIKE ?
            OR e.titre LIKE ?
            OR CAST(n.id_destinataire AS CHAR) LIKE ?
            OR CONCAT(et.nom_etu, ' ', et.prenom_etu) LIKE ?
            OR CONCAT(sv.nom_surv, ' ', sv.prenom_surv) LIKE ?
        )";

        $mot = '%' . $recherche . '%';

        for ($i = 0; $i < 6; $i++) {
            $parametres[] = $mot;
        }
    }

    $sql = "
        SELECT
            n.id_notification,
            n.id_evenement,
            n.type_destinataire,
            n.id_destinataire,
            n.email_destinataire,
            n.objet,
            n.message,
            n.statut_envoi,
            n.date_creation,
            n.date_envoi,
            n.lu,
            e.titre AS titre_evenement,
            et.nom_etu,
            et.prenom_etu,
            sv.nom_surv,
            sv.prenom_surv
        FROM notification n
        LEFT JOIN evenement e
            ON e.id_evenement = n.id_evenement
        LEFT JOIN etudiant et
            ON n.type_destinataire = 'etudiant'
            AND et.id_etu = n.id_destinataire
        LEFT JOIN surveillant sv
            ON n.type_destinataire = 'surveillant'
            AND sv.id_surv = n.id_destinataire
    ";

    if (!empty($conditions)) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }

    $sql .= ' ORDER BY n.date_creation DESC, n.id_notification DESC';

    $requete = $bdd->prepare($sql);
    $requete->execute($parametres);
    $notifications = $requete->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('Historique notifications : ' . $e->getMessage());

    $messageErreur = "Impossible de charger l'historique. Vérifie que la colonne lu existe dans la table notification.";

    $notifications = [];
    $statistiques = [
        'total' => 0,
        'envoyes' => 0,
        'echecs' => 0,
        'en_attente' => 0,
        'lues' => 0,
        'non_lues' => 0
    ];
}

function h($valeur): string
{
    return htmlspecialchars(
        (string) ($valeur ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function nomDestinataire(array $n): string
{
    if ($n['type_destinataire'] === 'etudiant') {
        $nom = trim(
            ($n['nom_etu'] ?? '') . ' ' . ($n['prenom_etu'] ?? '')
        );

        return $nom !== '' ? $nom : 'Étudiant';
    }

    $nom = trim(
        ($n['nom_surv'] ?? '') . ' ' . ($n['prenom_surv'] ?? '')
    );

    return $nom !== '' ? $nom : 'Surveillant';
}

function libelleStatut(string $statut): string
{
    return match ($statut) {
        'envoye' => 'Envoyé',
        'echec' => 'Échec',
        'en_attente' => 'En attente',
        default => $statut
    };
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Historique des notifications - CHACHA</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 25px;
            font-family: Arial, sans-serif;
            background: #f3f6fa;
            color: #263445;
        }

        .conteneur {
            max-width: 1350px;
            margin: auto;
        }

        h1 {
            color: #003366;
            margin-bottom: 8px;
        }

        .introduction {
            color: #64748b;
            margin-bottom: 25px;
        }

        .retour {
            display: inline-block;
            margin-bottom: 20px;
            color: #003366;
            text-decoration: none;
            font-weight: bold;
        }

        .statistiques {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .carte {
            background: white;
            padding: 20px;
            border-radius: 10px;
            border-left: 5px solid #003366;
            box-shadow: 0 2px 8px #0000000a;
        }

        .carte span {
            display: block;
            color: #64748b;
            margin-bottom: 10px;
        }

        .carte strong {
            font-size: 28px;
        }

        .carte.echec {
            border-left-color: #dc2626;
        }

        .carte.attente {
            border-left-color: #d97706;
        }

        .carte.lue {
            border-left-color: #16a34a;
        }

        .carte.non-lue {
            border-left-color: #7c3aed;
        }

        .filtres {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
            background: white;
            padding: 18px;
            border-radius: 10px;
        }

        .filtres input,
        .filtres select,
        .filtres button,
        .bouton-reset {
            min-height: 42px;
            padding: 10px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 14px;
        }

        .filtres input {
            flex: 1;
            min-width: 180px;
        }

        .filtres button {
            background: #003366;
            color: white;
            border: none;
            cursor: pointer;
        }

        .bouton-reset {
            display: inline-flex;
            align-items: center;
            text-decoration: none;
            color: #003366;
            background: white;
        }

        .table-conteneur {
            background: white;
            border-radius: 10px;
            overflow-x: auto;
            box-shadow: 0 2px 8px #0000000a;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1100px;
        }

        th, td {
            padding: 13px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
            vertical-align: top;
            font-size: 13px;
        }

        th {
            background: #003366;
            color: white;
        }

        tr:hover td {
            background: #f8fafc;
        }

        .badge {
            display: inline-block;
            padding: 6px 9px;
            border-radius: 20px;
            font-weight: bold;
            white-space: nowrap;
        }

        .envoye {
            background: #dcfce7;
            color: #166534;
        }

        .echec {
            background: #fee2e2;
            color: #991b1b;
        }

        .en_attente {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-lu {
            background: #dcfce7;
            color: #166534;
        }

        .badge-non-lu {
            background: #ede9fe;
            color: #5b21b6;
        }

        .erreur {
            padding: 15px;
            background: #fee2e2;
            color: #991b1b;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .vide {
            padding: 30px;
            text-align: center;
            color: #64748b;
        }

        details {
            margin-top: 8px;
        }

        summary {
            cursor: pointer;
            color: #003366;
        }

        @media (max-width: 700px) {
            body {
                padding: 15px;
            }

            .statistiques {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
    </style>
</head>

<body>
<div class="conteneur">

    <a class="retour" href="dashboard.php">
        &larr; Retour au tableau de bord
    </a>

    <h1>Historique des notifications</h1>

    <p class="introduction">
        Consultez les e-mails envoyés et l'état de lecture des notifications internes.
    </p>

    <?php if ($messageErreur !== ''): ?>
        <div class="erreur"><?= h($messageErreur) ?></div>
    <?php endif; ?>

    <div class="statistiques">
        <div class="carte">
            <span>Total des notifications</span>
            <strong><?= (int) $statistiques['total'] ?></strong>
        </div>

        <div class="carte">
            <span>E-mails envoyés</span>
            <strong><?= (int) $statistiques['envoyes'] ?></strong>
        </div>

        <div class="carte echec">
            <span>Échecs</span>
            <strong><?= (int) $statistiques['echecs'] ?></strong>
        </div>

        <div class="carte attente">
            <span>En attente</span>
            <strong><?= (int) $statistiques['en_attente'] ?></strong>
        </div>

        <div class="carte lue">
            <span>Notifications lues</span>
            <strong><?= (int) $statistiques['lues'] ?></strong>
        </div>

        <div class="carte non-lue">
            <span>Notifications non lues</span>
            <strong><?= (int) $statistiques['non_lues'] ?></strong>
        </div>
    </div>

    <form method="GET" class="filtres">

        <input
            type="text"
            name="q"
            placeholder="Rechercher un destinataire, un e-mail..."
            value="<?= h($recherche) ?>"
        >

        <select name="statut">
            <option value="">Tous les statuts d'envoi</option>
            <option value="envoye" <?= $filtreStatut === 'envoye' ? 'selected' : '' ?>>
                Envoyés
            </option>
            <option value="echec" <?= $filtreStatut === 'echec' ? 'selected' : '' ?>>
                Échecs
            </option>
            <option value="en_attente" <?= $filtreStatut === 'en_attente' ? 'selected' : '' ?>>
                En attente
            </option>
        </select>

        <select name="lecture">
            <option value="">Toutes les notifications</option>
            <option value="lu" <?= $filtreLecture === 'lu' ? 'selected' : '' ?>>
                Lues
            </option>
            <option value="non_lu" <?= $filtreLecture === 'non_lu' ? 'selected' : '' ?>>
                Non lues
            </option>
        </select>

        <button type="submit">Rechercher</button>

        <a class="bouton-reset" href="historique_notifications.php">
            Réinitialiser
        </a>
    </form>

    <div class="table-conteneur">

        <?php if (empty($notifications)): ?>

            <div class="vide">
                Aucune notification ne correspond à votre recherche.
            </div>

        <?php else: ?>

            <table>
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Événement</th>
                    <th>Destinataire</th>
                    <th>E-mail</th>
                    <th>Objet et message</th>
                    <th>Statut d'envoi</th>
                    <th>Lecture</th>
                    <th>Date de création</th>
                    <th>Date d'envoi</th>
                </tr>
                </thead>

                <tbody>
                <?php foreach ($notifications as $n): ?>
                    <tr>
                        <td><?= (int) $n['id_notification'] ?></td>

                        <td>
                            <?= h($n['titre_evenement'] ?? 'Événement supprimé') ?>
                        </td>

                        <td>
                            <?= h(nomDestinataire($n)) ?><br>
                            <small>
                                <?= h($n['type_destinataire']) ?>
                                #<?= (int) $n['id_destinataire'] ?>
                            </small>
                        </td>

                        <td><?= h($n['email_destinataire']) ?></td>

                        <td>
                            <strong><?= h($n['objet']) ?></strong>

                            <details>
                                <summary>Voir le message</summary>
                                <p><?= nl2br(h($n['message'])) ?></p>
                            </details>
                        </td>

                        <td>
                            <span class="badge <?= h($n['statut_envoi']) ?>">
                                <?= h(libelleStatut($n['statut_envoi'])) ?>
                            </span>
                        </td>

                        <td>
                            <?php if ((int) $n['lu'] === 1): ?>
                                <span class="badge badge-lu">Lue</span>
                            <?php else: ?>
                                <span class="badge badge-non-lu">Non lue</span>
                            <?php endif; ?>
                        </td>

                        <td><?= h($n['date_creation']) ?></td>

                        <td><?= h($n['date_envoi'] ?: 'Non envoyé') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

        <?php endif; ?>

    </div>

</div>
</body>
</html>

