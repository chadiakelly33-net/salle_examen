<?php
session_start();

if (!isset($_SESSION['id_surv'])) {
    header("Location: login_surv.php");
    exit();
}

require_once("connexionDB.php");

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

$id_surv = $_SESSION['id_surv'];
$date_debut = $_GET['date_debut'] ?? '';
$date_fin = $_GET['date_fin'] ?? '';
$id_salle = $_GET['id_salle'] ?? '';

// Charger uniquement les salles associées aux affectations du surveillant
$stmtSalles = $bdd->prepare("
    SELECT DISTINCT s.id_salle, s.nom_salle
    FROM affectation a
    INNER JOIN salle s ON a.id_salle = s.id_salle
    WHERE a.id_surv = ?
    ORDER BY s.nom_salle
");
$stmtSalles->execute([$id_surv]);
$salles = $stmtSalles->fetchAll(PDO::FETCH_ASSOC);

$sql = "
    SELECT
        a.id_affectation,
        a.date_exam,
        a.heure_debut,
        a.heure_fin,
        e.nom_etu,
        e.prenom_etu,
        c.nom_cours,
        s.id_salle,
        s.nom_salle,
        sv.nom_surv,
        sv.prenom_surv
    FROM affectation a
    LEFT JOIN etudiant e ON a.id_etu = e.id_etu
    LEFT JOIN cours c ON a.id_cours = c.id_cours
    LEFT JOIN salle s ON a.id_salle = s.id_salle
    LEFT JOIN surveillant sv ON a.id_surv = sv.id_surv
    WHERE a.id_surv = :id_surv
";

$params = [':id_surv' => $id_surv];

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

$sql .= " ORDER BY a.date_exam DESC, a.heure_debut ASC";

$stmt = $bdd->prepare($sql);
$stmt->execute($params);
$affectations = $stmt->fetchAll(PDO::FETCH_ASSOC);

$nomSurveillant = '';
if (!empty($affectations)) {
    $nomSurveillant = trim(
        ($affectations[0]['nom_surv'] ?? '') . ' ' .
        ($affectations[0]['prenom_surv'] ?? '')
    );
}

$total = count($affectations);
$sallesUtilisees = [];

foreach ($affectations as $a) {
    if ($a['nom_salle'] !== null) {
        $sallesUtilisees[$a['nom_salle']] = true;
    }
}

// Si aucune affectation ne correspond aux filtres, récupérer le nom depuis la base.
if ($nomSurveillant === '') {
    $stmtNom = $bdd->prepare("
        SELECT nom_surv, prenom_surv
        FROM surveillant
        WHERE id_surv = ?
    ");
    $stmtNom->execute([$id_surv]);
    $surv = $stmtNom->fetch(PDO::FETCH_ASSOC);

    if ($surv) {
        $nomSurveillant = trim($surv['nom_surv'].' '.$surv['prenom_surv']);
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Mes rapports d'examens</title>

<style>
*{box-sizing:border-box}
body{margin:0;font-family:Arial,sans-serif;background:#f1f5f9;color:#1e293b}
.conteneur{max-width:1200px;margin:30px auto;padding:0 20px}
.entete{background:linear-gradient(135deg,#003366,#075985);color:white;padding:28px;border-radius:12px;margin-bottom:22px}
.entete h2{margin:0 0 10px}
.carte{background:white;padding:24px;border-radius:10px;margin-bottom:22px;box-shadow:0 3px 12px #0000000c}
h3{color:#003366;margin-top:0}
.filtres{display:grid;grid-template-columns:repeat(3,minmax(140px,1fr));gap:14px}
label{display:block;font-weight:bold;font-size:14px;margin-bottom:7px}
input,select{width:100%;padding:11px;border:1px solid #cbd5e1;border-radius:6px;background:white}
.boutons{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px}
.btn{border:0;border-radius:6px;padding:12px 17px;text-decoration:none;cursor:pointer;font-weight:bold;display:inline-block}
.bleu{background:#003366;color:white}
.gris{background:#e2e8f0;color:#1e293b}
.rouge{background:#b91c1c;color:white}
.stats{display:grid;grid-template-columns:repeat(2,1fr);gap:15px}
.stat{background:white;padding:22px;border-left:5px solid #003366;border-radius:8px}
.stat span{color:#64748b;font-size:14px}
.stat strong{display:block;font-size:29px;color:#003366;margin-top:8px}
.table-scroll{overflow-x:auto}
table{width:100%;border-collapse:collapse;font-size:13px}
th,td{padding:12px 9px;border-bottom:1px solid #e2e8f0;text-align:left}
th{background:#003366;color:white}
tr:nth-child(even){background:#f8fafc}
.pied{text-align:center;font-size:12px;color:#64748b;padding:20px}
@media(max-width:700px){.filtres,.stats{grid-template-columns:1fr}}
@media print{
 body{background:white}
 .no-print,header,.titre{display:none!important}
 .conteneur{max-width:none;margin:0;padding:0}
 .entete{background:white!important;color:#003366!important;border:1px solid #003366}
 .carte,.stat{box-shadow:none;border:1px solid #ddd}
 .table-scroll{overflow:visible}
 table{font-size:10px}
 th{print-color-adjust:exact;-webkit-print-color-adjust:exact}
}
</style>
</head>
<body>

<?php include("menu.php"); ?>

<main class="conteneur">

<section class="entete">
    <h2>Mes rapports d'examens</h2>
    <p>Surveillant : <?= h($nomSurveillant) ?></p>
    <p>Ce rapport contient uniquement vos affectations.</p>
</section>

<section class="carte no-print">
    <h3>Filtrer mes affectations</h3>

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
                    <option value="">Toutes mes salles</option>
                    <?php foreach ($salles as $s): ?>
                    <option value="<?= h($s['id_salle']) ?>"
                        <?= (string)$id_salle === (string)$s['id_salle'] ? 'selected' : '' ?>>
                        <?= h($s['nom_salle']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="boutons">
            <button type="submit" class="btn bleu">Générer le rapport</button>
            <a href="rapports_surv.php" class="btn gris">Réinitialiser</a>
            <button type="button" class="btn rouge" onclick="window.print()">
                Imprimer / Enregistrer en PDF
            </button>
        </div>
    </form>
</section>

<section class="stats">
    <div class="stat">
        <span>Mes affectations trouvées</span>
        <strong><?= $total ?></strong>
    </div>

    <div class="stat">
        <span>Mes salles utilisées</span>
        <strong><?= count($sallesUtilisees) ?></strong>
    </div>
</section>

<section class="carte">
    <h3>Détail de mes affectations</h3>

    <p>
        <strong>Période :</strong>
        <?= $date_debut ? dateFr($date_debut) : 'Toutes les dates' ?>
        au
        <?= $date_fin ? dateFr($date_fin) : 'Toutes les dates' ?>
    </p>

    <p><strong>Généré le :</strong> <?= date('d/m/Y à H:i') ?></p>

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
        </tr>
        </thead>

        <tbody>
        <?php if ($affectations): ?>
            <?php foreach ($affectations as $i => $a): ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><?= h(dateFr($a['date_exam'])) ?></td>
                <td><?= h(heureFr($a['heure_debut'])) ?> - <?= h(heureFr($a['heure_fin'])) ?></td>
                <td><?= h(trim(($a['nom_etu'] ?? '').' '.($a['prenom_etu'] ?? '')) ?: 'Non attribué') ?></td>
                <td><?= h($a['nom_cours'] ?: 'Non défini') ?></td>
                <td><?= h($a['nom_salle'] ?: 'Non attribuée') ?></td>
            </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="6">Aucune affectation trouvée pour les filtres choisis.</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</section>

<div class="pied">Rapport personnel du surveillant — Gestion des salles d'examen</div>

</main>
</body>
</html>