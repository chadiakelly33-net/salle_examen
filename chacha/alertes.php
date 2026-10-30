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

require_once __DIR__ . "/../connexionDB.php";

$message = "";
$typeMessage = "";

/* Marquer une alerte comme lue */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["marquer_lue"])) {

    $idAlerte = filter_input(INPUT_POST, "id_alerte", FILTER_VALIDATE_INT);

    if ($idAlerte) {
        $req = $bdd->prepare("
            UPDATE alerte_automatique
            SET statut = 'lue'
            WHERE id_alerte = ?
        ");

        $req->execute([$idAlerte]);

        $message = "L'alerte a été marquée comme lue.";
        $typeMessage = "success";
    }
}

/* Marquer toutes les alertes comme lues */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["tout_lire"])) {

    $bdd->exec("
        UPDATE alerte_automatique
        SET statut = 'lue'
        WHERE statut = 'non_lue'
    ");

    $message = "Toutes les alertes ont été marquées comme lues.";
    $typeMessage = "success";
}

/* Compteurs */
$totalAlertes = (int) $bdd->query("
    SELECT COUNT(*) FROM alerte_automatique
")->fetchColumn();

$alertesNonLues = (int) $bdd->query("
    SELECT COUNT(*)
    FROM alerte_automatique
    WHERE statut = 'non_lue'
")->fetchColumn();

$alertesLues = (int) $bdd->query("
    SELECT COUNT(*)
    FROM alerte_automatique
    WHERE statut = 'lue'
")->fetchColumn();

/* Récupération des alertes */
$req = $bdd->query("
    SELECT
        a.*,
        af.date_exam,
        af.heure_debut,
        af.heure_fin,
        s.nom_salle
    FROM alerte_automatique a
    LEFT JOIN affectation af
        ON af.id_affectation = a.id_affectation
    LEFT JOIN salle s
        ON s.id_salle = af.id_salle
    ORDER BY
        CASE WHEN a.statut = 'non_lue' THEN 0 ELSE 1 END,
        a.date_creation DESC
");

$alertes = $req->fetchAll(PDO::FETCH_ASSOC);

$styles = [
    "capacite" => ["Capacité", "capacity"],
    "salle" => ["Salle", "room"],
    "surveillant" => ["Surveillant", "supervisor"],
    "etudiant" => ["Étudiant", "student"]
];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Alertes automatiques</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f4f6fa;
            font-family: Arial, sans-serif;
            color: #263445;
        }

        main {
            margin-left: 230px;
            padding: 35px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 25px;
        }

        .page-header h1 {
            color: #003366;
            margin: 0 0 8px;
            font-size: 27px;
        }

        .page-header p {
            color: #718096;
            margin: 0;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 22px;
            border: 1px solid #e8edf4;
        }

        .stat-card span {
            color: #718096;
            font-size: 14px;
        }

        .stat-card strong {
            display: block;
            font-size: 30px;
            color: #003366;
            margin-top: 10px;
        }

        .stat-card.warning strong {
            color: #d97706;
        }

        .stat-card.success strong {
            color: #16804a;
        }

        .alert-panel {
            background: white;
            border-radius: 12px;
            border: 1px solid #e8edf4;
            overflow: hidden;
        }

        .panel-header {
            padding: 20px 24px;
            border-bottom: 1px solid #edf0f5;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .panel-header h2 {
            margin: 0;
            color: #003366;
            font-size: 19px;
        }

        .btn {
            border: none;
            border-radius: 7px;
            padding: 10px 15px;
            cursor: pointer;
            font-weight: 600;
            font-size: 13px;
        }

        .btn-primary {
            background: #003366;
            color: white;
        }

        .btn-read {
            background: #eaf1fa;
            color: #003366;
        }

        .alert-list {
            padding: 20px;
        }

        .alert-item {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            padding: 18px;
            border: 1px solid #e8edf4;
            border-left: 5px solid #d97706;
            border-radius: 9px;
            margin-bottom: 13px;
            background: #fff;
        }

        .alert-item.lue {
            border-left-color: #16804a;
            background: #fbfdfc;
        }

        .alert-content {
            min-width: 0;
        }

        .alert-content h3 {
            margin: 0 0 8px;
            font-size: 16px;
            color: #263445;
        }

        .alert-content p {
            margin: 0 0 12px;
            color: #64748b;
            line-height: 1.5;
            font-size: 14px;
        }

        .alert-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            color: #8491a3;
            font-size: 12px;
        }

        .badge {
            display: inline-block;
            border-radius: 20px;
            padding: 5px 10px;
            font-size: 11px;
            font-weight: bold;
            margin-bottom: 8px;
        }

        .badge-non-lue {
            color: #92400e;
            background: #fef3c7;
        }

        .badge-lue {
            color: #166534;
            background: #dcfce7;
        }

        .empty {
            text-align: center;
            padding: 55px 20px;
            color: #718096;
        }

        .empty strong {
            display: block;
            color: #16804a;
            font-size: 18px;
            margin-bottom: 8px;
        }

        .flash {
            padding: 13px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .flash.success {
            background: #dcfce7;
            color: #166534;
        }

        .alert-actions {
            flex-shrink: 0;
        }

        @media(max-width: 900px) {
            main {
                margin-left: 0;
                padding: 20px;
            }

            .stats {
                grid-template-columns: 1fr;
            }
        }

        @media(max-width: 600px) {
            .alert-item {
                flex-direction: column;
            }
        }
    </style>
</head>

<body>

<aside>
    <?php include __DIR__ . "/menu_principal.php"; ?>
</aside>

<main>

    <div class="page-header">
        <div>
            <h1>Alertes automatiques</h1>
            <p>Surveillance des affectations et détection des anomalies.</p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="flash <?= htmlspecialchars($typeMessage) ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <section class="stats">

        <div class="stat-card">
            <span>Total des alertes</span>
            <strong><?= $totalAlertes ?></strong>
        </div>

        <div class="stat-card warning">
            <span>Alertes non lues</span>
            <strong><?= $alertesNonLues ?></strong>
        </div>

        <div class="stat-card success">
            <span>Alertes lues</span>
            <strong><?= $alertesLues ?></strong>
        </div>

    </section>

    <section class="alert-panel">

        <div class="panel-header">
            <h2>Historique des alertes</h2>

            <?php if ($alertesNonLues > 0): ?>
                <form method="POST">
                    <button type="submit" name="tout_lire" class="btn btn-primary">
                        Tout marquer comme lu
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <div class="alert-list">

            <?php if (empty($alertes)): ?>

                <div class="empty">
                    <strong>Aucune alerte enregistrée</strong>
                    Toutes les affectations sont sous surveillance.
                </div>

            <?php else: ?>

                <?php foreach ($alertes as $alerte): ?>

                    <article class="alert-item <?= $alerte["statut"] === "lue" ? "lue" : "" ?>">

                        <div class="alert-content">

                            <span class="badge <?= $alerte["statut"] === "lue" ? "badge-lue" : "badge-non-lue" ?>">
                                <?= $alerte["statut"] === "lue" ? "LUE" : "NON LUE" ?>
                            </span>

                            <h3>
                                <?= htmlspecialchars($alerte["titre"]) ?>
                            </h3>

                            <p>
                                <?= htmlspecialchars($alerte["description"]) ?>
                            </p>

                            <div class="alert-meta">
                                <span>
                                    Type :
                                    <?= htmlspecialchars($styles[$alerte["type_alerte"]][0] ?? $alerte["type_alerte"]) ?>
                                </span>

                                <span>
                                    Affectation :
                                    <?= htmlspecialchars((string)($alerte["id_affectation"] ?? "Supprimée")) ?>
                                </span>

                                <span>
                                    Date :
                                    <?= htmlspecialchars($alerte["date_creation"]) ?>
                                </span>

                                <?php if (!empty($alerte["date_exam"])): ?>
                                    <span>
                                        Examen :
                                        <?= htmlspecialchars($alerte["date_exam"]) ?>
                                    </span>
                                <?php endif; ?>

                                <?php if (!empty($alerte["nom_salle"])): ?>
                                    <span>
                                        Salle :
                                        <?= htmlspecialchars($alerte["nom_salle"]) ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                        </div>

                        <?php if ($alerte["statut"] === "non_lue"): ?>
                            <div class="alert-actions">
                                <form method="POST">
                                    <input
                                        type="hidden"
                                        name="id_alerte"
                                        value="<?= (int)$alerte["id_alerte"] ?>"
                                    >

                                    <button
                                        type="submit"
                                        name="marquer_lue"
                                        class="btn btn-read"
                                    >
                                        Marquer comme lue
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>

                    </article>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>

    </section>

</main>

</body>
</html>