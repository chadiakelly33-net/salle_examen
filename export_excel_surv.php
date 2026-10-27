<?php

session_start();

require_once __DIR__ . "/connexionDB.php";

// Vérifier l'inactivité de la session : 5 minutes
$timeout = 300;

if (isset($_SESSION["last_activity"])) {

    if (time() - $_SESSION["last_activity"] > $timeout) {

        session_unset();
        session_destroy();

        header("Location: login_surv.php?timeout=1");
        exit();
    }
}

// Vérifier que le surveillant est connecté
if (
    !isset($_SESSION["id_surv"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "surveillant"
) {
    header("Location: login_surv.php");
    exit();
}

// Actualiser l'activité de la session
$_SESSION["last_activity"] = time();

// Récupérer les affectations du surveillant connecté
// La faculté et le niveau proviennent de la table cours.

$sql = "
    SELECT
        c.nom_cours,
        f.nom_fac,
        n.nom_niveau,
        s.nom_salle,
        a.date_exam,
        a.heure_debut,
        a.heure_fin

    FROM affectation a

    INNER JOIN cours c
        ON a.id_cours = c.id_cours

    INNER JOIN salle s
        ON a.id_salle = s.id_salle

    LEFT JOIN faculte f
        ON c.id_fac = f.id_fac

    LEFT JOIN niveau_etude n
        ON c.id_niveau = n.id_niveau

    WHERE a.id_surv = ?

      AND a.date_exam IS NOT NULL
      AND a.heure_fin IS NOT NULL

      AND CONCAT(a.date_exam, ' ', a.heure_fin) >= NOW()

    ORDER BY
        a.date_exam ASC,
        a.heure_debut ASC
";

$req = $bdd->prepare($sql);

$req->execute([
    $_SESSION["id_surv"]
]);

$affectations = $req->fetchAll(PDO::FETCH_ASSOC);

// Préparer le téléchargement Excel
$nomFichier = "mes_affectations_" . date("Y-m-d") . ".xls";

// Éviter que des messages PHP perturbent le téléchargement.
if (headers_sent()) {
    exit("Erreur : des données ont été envoyées avant la création du fichier Excel.");
}

header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"$nomFichier\"");
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// BOM UTF-8 pour préserver les accents dans Excel
echo "\xEF\xBB\xBF";

?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Mes affectations</title>
</head>

<body>

<table border="1">

    <thead>
        <tr>
            <th>Cours</th>
            <th>Faculté</th>
            <th>Niveau</th>
            <th>Salle</th>
            <th>Date examen</th>
            <th>Heure de début</th>
            <th>Heure de fin</th>
        </tr>
    </thead>

    <tbody>

        <?php if (!empty($affectations)): ?>

            <?php foreach ($affectations as $a): ?>

                <tr>
                    <td><?= htmlspecialchars($a["nom_cours"] ?? "", ENT_QUOTES, "UTF-8") ?></td>

                    <td><?= htmlspecialchars($a["nom_fac"] ?? "Non renseignée", ENT_QUOTES, "UTF-8") ?></td>

                    <td><?= htmlspecialchars($a["nom_niveau"] ?? "Non renseigné", ENT_QUOTES, "UTF-8") ?></td>

                    <td><?= htmlspecialchars($a["nom_salle"] ?? "", ENT_QUOTES, "UTF-8") ?></td>

                    <td><?= htmlspecialchars($a["date_exam"] ?? "", ENT_QUOTES, "UTF-8") ?></td>

                    <td><?= htmlspecialchars($a["heure_debut"] ?? "", ENT_QUOTES, "UTF-8") ?></td>

                    <td><?= htmlspecialchars($a["heure_fin"] ?? "", ENT_QUOTES, "UTF-8") ?></td>
                </tr>

            <?php endforeach; ?>

        <?php else: ?>

            <tr>
                <td colspan="7">Aucune affectation future ou en cours.</td>
            </tr>

        <?php endif; ?>

    </tbody>

</table>

</body>
</html>

