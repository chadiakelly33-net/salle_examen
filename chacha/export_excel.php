<?php
session_start();

/* Vérifier la connexion de l'administrateur */
if (
    !isset($_SESSION['id_admin']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin'
) {
    header("Location: connexion.php");
    exit();
}

/* Vérifier le délai d'inactivité */
$timeout = 300;

if (
    isset($_SESSION['last_activity']) &&
    time() - $_SESSION['last_activity'] > $timeout
) {
    session_unset();
    session_destroy();

    header("Location: connexion.php?timeout=1");
    exit();
}

$_SESSION['last_activity'] = time();

require_once("connexionDB.php");

/* Récupérer les filtres */
$date_debut = $_GET['date_debut'] ?? '';
$date_fin   = $_GET['date_fin'] ?? '';
$id_salle   = $_GET['id_salle'] ?? '';
$id_surv    = $_GET['id_surv'] ?? '';

/* Vérifier les formats des dates */
if (
    ($date_debut !== '' &&
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_debut)) ||
    ($date_fin !== '' &&
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_fin))
) {
    exit("Format de date invalide.");
}

if (
    $date_debut !== '' &&
    $date_fin !== '' &&
    $date_debut > $date_fin
) {
    exit("La date de début doit précéder la date de fin.");
}

/* Si l'utilisateur clique sur Exporter */
if (isset($_GET['export']) && $_GET['export'] === 'excel') {

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

    /* Préparer le téléchargement compatible avec Excel */
    $nom_fichier = "affectations_examens_" . date('Y-m-d') . ".xls";

    header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
    header(
        'Content-Disposition: attachment; filename="' .
        $nom_fichier . '"'
    );
    header("Pragma: no-cache");
    header("Expires: 0");

    /* BOM UTF-8 pour les accents */
    echo "\xEF\xBB\xBF";
    ?>
    <table border="1">
        <thead>
            <tr>
                <th>Numéro affectation</th>
                <th>Date examen</th>
                <th>Heure début</th>
                <th>Heure fin</th>
                <th>Nom étudiant</th>
                <th>Prénom étudiant</th>
                <th>Cours</th>
                <th>Salle</th>
                <th>Nom surveillant</th>
                <th>Prénom surveillant</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($affectations as $a): ?>
            <tr>
                <td><?= (int)$a['id_affectation'] ?></td>
                <td><?= htmlspecialchars((string)($a['date_exam'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string)($a['heure_debut'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string)($a['heure_fin'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string)($a['nom_etu'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string)($a['prenom_etu'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string)($a['nom_cours'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string)($a['nom_salle'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string)($a['nom_surv'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string)($a['prenom_surv'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php
    exit();
}

/* Charger les listes pour les filtres */
$salles = $bdd->query("
    SELECT id_salle, nom_salle
    FROM salle
    ORDER BY nom_salle
")->fetchAll(PDO::FETCH_ASSOC);

$surveillants = $bdd->query("
    SELECT id_surv, nom_surv, prenom_surv
    FROM surveillant
    ORDER BY nom_surv, prenom_surv
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Export Excel des affectations</title>

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
    max-width: 950px;
    margin: 35px auto;
    padding: 20px;
}

.carte {
    background: white;
    padding: 28px;
    border-radius: 10px;
    box-shadow: 0 3px 12px #00000010;
}

h2 {
    color: #003366;
}

.description {
    color: #64748b;
    line-height: 1.6;
}

.filtres {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
    margin-top: 25px;
}

label {
    display: block;
    font-weight: bold;
    margin-bottom: 8px;
}

input, select {
    width: 100%;
    padding: 12px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font-size: 14px;
}

.boutons {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-top: 25px;
}

.btn {
    display: inline-block;
    padding: 12px 18px;
    border: none;
    border-radius: 6px;
    text-decoration: none;
    font-weight: bold;
    cursor: pointer;
    font-size: 14px;
}

.bleu {
    background: #003366;
    color: white;
}

.gris {
    background: #e2e8f0;
    color: #1e293b;
}

@media (max-width: 600px) {
    .filtres {
        grid-template-columns: 1fr;
    }
}
</style>
</head>

<body>

<?php include("menu_principal.php"); ?>

<main class="conteneur">
    <section class="carte">

        <h2>Export Excel des affectations</h2>

        <p class="description">
            Sélectionne les critères souhaités pour exporter
            les affectations des examens vers un fichier compatible
            avec Microsoft Excel.
        </p>

        <form method="GET" action="export_excel.php">

            <div class="filtres">

                <div>
                    <label for="date_debut">Date de début</label>
                    <input
                        type="date"
                        id="date_debut"
                        name="date_debut"
                        value="<?= htmlspecialchars($date_debut, ENT_QUOTES, 'UTF-8') ?>"
                    >
                </div>

                <div>
                    <label for="date_fin">Date de fin</label>
                    <input
                        type="date"
                        id="date_fin"
                        name="date_fin"
                        value="<?= htmlspecialchars($date_fin, ENT_QUOTES, 'UTF-8') ?>"
                    >
                </div>

                <div>
                    <label for="id_salle">Salle</label>
                    <select id="id_salle" name="id_salle">
                        <option value="">Toutes les salles</option>

                        <?php foreach ($salles as $s): ?>
                            <option
                                value="<?= (int)$s['id_salle'] ?>"
                                <?= (string)$id_salle === (string)$s['id_salle'] ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars($s['nom_salle'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="id_surv">Surveillant</label>
                    <select id="id_surv" name="id_surv">
                        <option value="">Tous les surveillants</option>

                        <?php foreach ($surveillants as $s): ?>
                            <option
                                value="<?= (int)$s['id_surv'] ?>"
                                <?= (string)$id_surv === (string)$s['id_surv'] ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars(
                                    $s['nom_surv'] . ' ' . $s['prenom_surv'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

            </div>

            <div class="boutons">
                <button
                    type="submit"
                    name="export"
                    value="excel"
                    class="btn bleu"
                >
                    Exporter vers Excel
                </button>

                <a href="export_excel.php" class="btn gris">
                    Réinitialiser
                </a>
            </div>

        </form>

    </section>
</main>

</body>
</html>

