<?php
session_start();

/* Vérification selon la connexion admin existante */
if (
    !isset($_SESSION['id_admin']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin'
) {
    header("Location: connexion.php");
    exit();
}

/* Gestion du délai d'inactivité : 5 minutes */
$timeout = 300;

if (isset($_SESSION['last_activity'])) {
    if (time() - $_SESSION['last_activity'] > $timeout) {
        session_unset();
        session_destroy();

        header("Location: connexion.php?timeout=1");
        exit();
    }
}

$_SESSION['last_activity'] = time();

require_once("connexionDB.php");

/* Fonctions d'affichage */
function h($valeur) {
    return htmlspecialchars((string)($valeur ?? ''), ENT_QUOTES, 'UTF-8');
}

function dateFr($date) {
    if (!$date) {
        return "Non définie";
    }

    $timestamp = strtotime($date);
    return $timestamp ? date('d/m/Y', $timestamp) : "Non définie";
}

function heureFr($heure) {
    return $heure ? substr($heure, 0, 5) : "--:--";
}

/* Filtres */
$date_debut = $_GET['date_debut'] ?? '';
$date_fin = $_GET['date_fin'] ?? '';
$id_salle = $_GET['id_salle'] ?? '';
$id_surv = $_GET['id_surv'] ?? '';

/* Liste des salles */
$salles = $bdd->query("
    SELECT id_salle, nom_salle
    FROM salle
    ORDER BY nom_salle
")->fetchAll(PDO::FETCH_ASSOC);

/* Liste des surveillants */
$surveillants = $bdd->query("
    SELECT id_surv, nom_surv, prenom_surv
    FROM surveillant
    ORDER BY nom_surv, prenom_surv
")->fetchAll(PDO::FETCH_ASSOC);

/* Requête principale */
$sql = "
    SELECT
        a.id_affectation,
        a.date_exam,
        a.heure_debut,
        a.heure_fin,
        e.nom_etu,
        e.prenom_etu,
        c.nom_cours,
        s.nom_salle,
        sv.id_surv,
        sv.nom_surv,
        sv.prenom_surv
    FROM affectation a
    LEFT JOIN etudiant e ON a.id_etu = e.id_etu
    LEFT JOIN cours c ON a.id_cours = c.id_cours
    LEFT JOIN salle s ON a.id_salle = s.id_salle
    LEFT JOIN surveillant sv ON a.id_surv = sv.id_surv
    WHERE 1=1
";

$params = [];

if ($date_debut !== '') {
    $sql .= " AND a.date_exam >= :date_debut";
    $params[':date_debut'] = $date_debut;
}

if ($date_fin !== '') {
    $sql .= " AND a.date_exam <= :date_fin";
    $params[':date_fin'] = $date_fin;
}

if ($id_salle !== '') {
    $sql .= " AND a.id_salle = :id_salle";
    $params[':id_salle'] = $id_salle;
}

if ($id_surv !== '') {
    $sql .= " AND a.id_surv = :id_surv";
    $params[':id_surv'] = $id_surv;
}

$sql .= " ORDER BY a.date_exam DESC, a.heure_debut ASC";

$stmt = $bdd->prepare($sql);
$stmt->execute($params);
$affectations = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Statistiques */
$total = count($affectations);

$sallesUtilisees = [];
$surveillantsUtilises = [];

foreach ($affectations as $a) {
    if ($a['id_surv'] !== null) {
        $surveillantsUtilises[$a['id_surv']] = true;
    }

    if ($a['nom_salle'] !== null) {
        $sallesUtilisees[$a['nom_salle']] = true;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Rapports des examens</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f1f5f9;
    color: #1e293b;
}

.conteneur {
    max-width: 1250px;
    margin: 30px auto;
    padding: 0 20px;
}

.entete {
    background: #003366;
    color: white;
    padding: 28px;
    border-radius: 12px;
    margin-bottom: 22px;
}

.entete h2 {
    margin: 0 0 8px;
}

.carte {
    background: white;
    padding: 24px;
    border-radius: 10px;
    margin-bottom: 22px;
    box-shadow: 0 3px 12px #0000000c;
}

h3 {
    color: #003366;
    margin-top: 0;
}

.filtres {
    display: grid;
    grid-template-columns: repeat(4, minmax(140px, 1fr));
    gap: 14px;
}

label {
    display: block;
    font-weight: bold;
    font-size: 14px;
    margin-bottom: 7px;
}

input, select {
    width: 100%;
    padding: 11px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
}

.boutons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 18px;
}

.btn {
    border: 0;
    border-radius: 6px;
    padding: 12px 17px;
    text-decoration: none;
    cursor: pointer;
    font-weight: bold;
    display: inline-block;
}

.bleu {
    background: #003366;
    color: white;
}

.gris {
    background: #e2e8f0;
    color: #1e293b;
}

.rouge {
    background: #b91c1c;
    color: white;
}

.stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-bottom: 22px;
}

.stat {
    background: white;
    padding: 22px;
    border-left: 5px solid #003366;
    border-radius: 8px;
}

.stat span {
    color: #64748b;
    font-size: 14px;
}

.stat strong {
    display: block;
    font-size: 29px;
    color: #003366;
    margin-top: 8px;
}

.table-scroll {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}

th, td {
    padding: 12px 9px;
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
}

th {
    background: #003366;
    color: white;
}

tr:nth-child(even) {
    background: #f8fafc;
}

.pied {
    text-align: center;
    font-size: 12px;
    color: #64748b;
    padding: 20px;
}

@media (max-width: 800px) {
    .filtres, .stats {
        grid-template-columns: 1fr 1fr;
    }
}

@media print {
    body {
        background: white;
    }

    .no-print, .sidebar {
        display: none !important;
    }

    body {
        padding-left: 0 !important;
    }

    .conteneur {
        max-width: none;
        margin: 0;
        padding: 0;
    }

    .entete {
        background: white !important;
        color: #003366 !important;
        border: 1px solid #003366;
    }

    .carte, .stat {
        box-shadow: none;
        border: 1px solid #ddd;
    }

    .table-scroll {
        overflow: visible;
    }

    table {
        font-size: 10px;
    }

    th {
        print-color-adjust: exact;
        -webkit-print-color-adjust: exact;
    }
}
</style>
</head>

<body>

<?php include("menu_principal.php"); ?>

<main class="conteneur">

<section class="entete">
    <h2>Rapports des examens</h2>
    <p>Rapport administratif général des affectations</p>
</section>

<section class="carte no-print">
    <h3>Filtrer le rapport</h3>

    <form method="GET">
        <div class="filtres">

            <div>
                <label for="date_debut">Date de début</label>
                <input type="date" id="date_debut" name="date_debut"
                       value="<?= h($date_debut) ?>">
            </div>

            <div>
                <label for="date_fin">Date de fin</label>
                <input type="date" id="date_fin" name="date_fin"
                       value="<?= h($date_fin) ?>">
            </div>

            <div>
                <label for="id_salle">Salle</label>
                <select id="id_salle" name="id_salle">
                    <option value="">Toutes les salles</option>

                    <?php foreach ($salles as $s): ?>
                    <option value="<?= h($s['id_salle']) ?>"
                        <?= (string)$id_salle === (string)$s['id_salle'] ? 'selected' : '' ?>>
                        <?= h($s['nom_salle']) ?>
                    </option>
                    <?php endforeach; ?>

                </select>
            </div>

            <div>
                <label for="id_surv">Surveillant</label>
                <select id="id_surv" name="id_surv">
                    <option value="">Tous les surveillants</option>

                    <?php foreach ($surveillants as $s): ?>
                    <option value="<?= h($s['id_surv']) ?>"
                        <?= (string)$id_surv === (string)$s['id_surv'] ? 'selected' : '' ?>>
                        <?= h($s['nom_surv'].' '.$s['prenom_surv']) ?>
                    </option>
                    <?php endforeach; ?>

                </select>
            </div>

        </div>

        <div class="boutons">
            <button class="btn bleu" type="submit">
                Générer le rapport
            </button>

            <a class="btn gris" href="rapports.php">
                Réinitialiser
            </a>

            <button class="btn rouge" type="button" onclick="window.print()">
                Imprimer / Enregistrer en PDF
            </button>
        </div>
    </form>
</section>

<section class="stats">
    <div class="stat">
        <span>Affectations trouvées</span>
        <strong><?= $total ?></strong>
    </div>

    <div class="stat">
        <span>Salles utilisées</span>
        <strong><?= count($sallesUtilisees) ?></strong>
    </div>

    <div class="stat">
        <span>Surveillants concernés</span>
        <strong><?= count($surveillantsUtilises) ?></strong>
    </div>
</section>

<section class="carte">
    <h3>Détail des affectations</h3>

    <p>
        <strong>Période :</strong>
        <?= $date_debut ? dateFr($date_debut) : 'Toutes les dates' ?>
        au
        <?= $date_fin ? dateFr($date_fin) : 'Toutes les dates' ?>
    </p>

    <p>
        <strong>Généré le :</strong>
        <?= date('d/m/Y à H:i') ?>
    </p>

    <div class="table-scroll">
    <table>
        <thead>
        <tr>
            <th>N°</th>
            <th>Date</th>
            <th>Horaire</th>
            <th>Étudiant</th>
            <th>Cours</th>
            <th>Salle</th>
            <th>Surveillant</th>
        </tr>
        </thead>

        <tbody>
        <?php if ($affectations): ?>

            <?php foreach ($affectations as $i => $a): ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><?= h(dateFr($a['date_exam'])) ?></td>

                <td>
                    <?= h(heureFr($a['heure_debut'])) ?>
                    -
                    <?= h(heureFr($a['heure_fin'])) ?>
                </td>

                <td>
                    <?= h(trim(($a['nom_etu'] ?? '').' '.($a['prenom_etu'] ?? '')) ?: 'Non attribué') ?>
                </td>

                <td><?= h($a['nom_cours'] ?: 'Non défini') ?></td>
                <td><?= h($a['nom_salle'] ?: 'Non attribuée') ?></td>

                <td>
                    <?= h(trim(($a['nom_surv'] ?? '').' '.($a['prenom_surv'] ?? '')) ?: 'Non attribué') ?>
                </td>
            </tr>
            <?php endforeach; ?>

        <?php else: ?>

            <tr>
                <td colspan="7">Aucune affectation trouvée.</td>
            </tr>

        <?php endif; ?>
        </tbody>
    </table>
    </div>
</section>

<div class="pied">
    Rapport administratif — Gestion des salles d'examen
</div>

</main>
</body>
</html>

