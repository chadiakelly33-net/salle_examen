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
require_once __DIR__ . "/../service_notification.php";
require_once __DIR__ . "/journal.php";

error_reporting(E_ALL);
ini_set("display_errors", "0");

$bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$message = "";
$typeMessage = "";

$id_fac = $_POST["id_fac"] ?? "";
$id_niveau = $_POST["id_niveau"] ?? "";
$id_cours = $_POST["id_cours"] ?? "";
$id_salle = $_POST["id_salle"] ?? "";
$id_surv = $_POST["id_surv"] ?? "";
$date_exam = $_POST["date_exam"] ?? "";
$heure_debut = $_POST["heure_debut"] ?? "";
$heure_fin = $_POST["heure_fin"] ?? "";

$cours = [];
$etudiants = [];

/* =========================================================
   CHARGEMENT DES LISTES
========================================================= */

$facultes = $bdd->query("
    SELECT id_fac, nom_fac
    FROM faculte
    ORDER BY nom_fac ASC
")->fetchAll(PDO::FETCH_ASSOC);

$niveaux = $bdd->query("
    SELECT id_niveau, nom_niveau
    FROM niveau_etude
    ORDER BY nom_niveau ASC
")->fetchAll(PDO::FETCH_ASSOC);

$salles = $bdd->query("
    SELECT id_salle, nom_salle
    FROM salle
    ORDER BY nom_salle ASC
")->fetchAll(PDO::FETCH_ASSOC);

$surveillants = $bdd->query("
    SELECT id_surv, nom_surv, prenom_surv
    FROM surveillant
    ORDER BY nom_surv ASC, prenom_surv ASC
")->fetchAll(PDO::FETCH_ASSOC);

/* =========================================================
   FONCTION : CHARGER COURS ET ETUDIANTS
========================================================= */

function chargerCoursEtudiants(PDO $bdd, $id_fac, $id_niveau): array
{
    $req = $bdd->prepare("
        SELECT id_cours, nom_cours, credit
        FROM cours
        WHERE id_fac = ?
          AND id_niveau = ?
        ORDER BY nom_cours ASC
    ");

    $req->execute([$id_fac, $id_niveau]);
    $cours = $req->fetchAll(PDO::FETCH_ASSOC);

    $req = $bdd->prepare("
        SELECT id_etu, nom_etu, prenom_etu
        FROM etudiant
        WHERE id_fac = ?
          AND id_niveau = ?
        ORDER BY nom_etu ASC, prenom_etu ASC
    ");

    $req->execute([$id_fac, $id_niveau]);
    $etudiants = $req->fetchAll(PDO::FETCH_ASSOC);

    return [$cours, $etudiants];
}

/* =========================================================
   FONCTION : VERIFIER LES CONFLITS
========================================================= */

function verifierConflitsAffectation(
    PDO $bdd,
    array $idsEtudiants,
    int $idCours,
    int $idSalle,
    int $idSurv,
    string $dateExam,
    string $heureDebut,
    string $heureFin
): ?string {

    /*
     * 1. CONFLIT ETUDIANT
     */

    $req = $bdd->prepare("
        SELECT
            e.nom_etu,
            e.prenom_etu,
            a.date_exam,
            a.heure_debut,
            a.heure_fin,
            c.nom_cours
        FROM affectation a
        INNER JOIN etudiant e ON e.id_etu = a.id_etu
        LEFT JOIN cours c ON c.id_cours = a.id_cours
        WHERE a.id_etu = ?
          AND a.date_exam = ?
          AND a.heure_debut < ?
          AND a.heure_fin > ?
        LIMIT 1
    ");

    foreach ($idsEtudiants as $idEtu) {

        $req->execute([
            $idEtu,
            $dateExam,
            $heureFin,
            $heureDebut
        ]);

        $conflit = $req->fetch(PDO::FETCH_ASSOC);

        if ($conflit) {
            return
                "Impossible de faire cette affectation : l'étudiant " .
                $conflit["nom_etu"] . " " . $conflit["prenom_etu"] .
                " est déjà affecté à l'examen " .
                ($conflit["nom_cours"] ?? "existant") .
                " le " . $conflit["date_exam"] .
                " de " . $conflit["heure_debut"] .
                " à " . $conflit["heure_fin"] . ".";
        }
    }

    /*
     * 2. CONFLIT DE SALLE
     *
     * Les lignes du même examen (même cours, salle,
     * surveillant et horaires) sont considérées comme
     * une seule session d'examen.
     */

    $req = $bdd->prepare("
        SELECT
            s.nom_salle,
            c.nom_cours,
            a.heure_debut,
            a.heure_fin
        FROM affectation a
        INNER JOIN salle s ON s.id_salle = a.id_salle
        LEFT JOIN cours c ON c.id_cours = a.id_cours
        WHERE a.id_salle = ?
          AND a.date_exam = ?
          AND a.heure_debut < ?
          AND a.heure_fin > ?
          AND NOT (
              a.id_cours = ?
              AND a.id_surv = ?
              AND a.heure_debut = ?
              AND a.heure_fin = ?
          )
        LIMIT 1
    ");

    $req->execute([
        $idSalle,
        $dateExam,
        $heureFin,
        $heureDebut,
        $idCours,
        $idSurv,
        $heureDebut,
        $heureFin
    ]);

    $conflit = $req->fetch(PDO::FETCH_ASSOC);

    if ($conflit) {
        return
            "Impossible de faire cette affectation : la salle " .
            $conflit["nom_salle"] .
            " est déjà réservée pour le cours " .
            ($conflit["nom_cours"] ?? "un autre examen") .
            " de " . $conflit["heure_debut"] .
            " à " . $conflit["heure_fin"] . ".";
    }

    /*
     * 3. CONFLIT DE SURVEILLANT
     */

    $req = $bdd->prepare("
        SELECT
            s.nom_surv,
            s.prenom_surv,
            c.nom_cours,
            sa.nom_salle,
            a.heure_debut,
            a.heure_fin
        FROM affectation a
        INNER JOIN surveillant s ON s.id_surv = a.id_surv
        LEFT JOIN cours c ON c.id_cours = a.id_cours
        LEFT JOIN salle sa ON sa.id_salle = a.id_salle
        WHERE a.id_surv = ?
          AND a.date_exam = ?
          AND a.heure_debut < ?
          AND a.heure_fin > ?
          AND NOT (
              a.id_cours = ?
              AND a.id_salle = ?
              AND a.heure_debut = ?
              AND a.heure_fin = ?
          )
        LIMIT 1
    ");

    $req->execute([
        $idSurv,
        $dateExam,
        $heureFin,
        $heureDebut,
        $idCours,
        $idSalle,
        $heureDebut,
        $heureFin
    ]);

    $conflit = $req->fetch(PDO::FETCH_ASSOC);

    if ($conflit) {
        return
            "Impossible de faire cette affectation : le surveillant " .
            $conflit["nom_surv"] . " " . $conflit["prenom_surv"] .
            " surveille déjà le cours " .
            ($conflit["nom_cours"] ?? "un autre examen") .
            " dans la salle " .
            ($conflit["nom_salle"] ?? "inconnue") .
            " de " . $conflit["heure_debut"] .
            " à " . $conflit["heure_fin"] . ".";
    }

    return null;
}

/* =========================================================
   CHARGER LES COURS ET LES ETUDIANTS
========================================================= */

if (isset($_POST["charger"])) {

    if (empty($id_fac) || empty($id_niveau)) {
        $message = "Veuillez choisir une faculté et un niveau.";
        $typeMessage = "erreur";
    } else {
        [$cours, $etudiants] = chargerCoursEtudiants(
            $bdd,
            $id_fac,
            $id_niveau
        );

        if (empty($cours)) {
            $message = "Aucun cours n'est enregistré pour cette faculté et ce niveau.";
            $typeMessage = "erreur";
        } elseif (empty($etudiants)) {
            $message = "Aucun étudiant n'est enregistré pour cette faculté et ce niveau.";
            $typeMessage = "erreur";
        }
    }
}

/* =========================================================
   ENREGISTRER L'AFFECTATION ET NOTIFIER
========================================================= */

if (isset($_POST["enregistrer"])) {

    $etudiants_selectionnes = $_POST["etudiants"] ?? [];

    try {

        /* Vérification des champs */

        if (
            empty($id_fac) ||
            empty($id_niveau) ||
            empty($id_cours) ||
            empty($id_salle) ||
            empty($id_surv) ||
            empty($date_exam) ||
            empty($heure_debut) ||
            empty($heure_fin)
        ) {
            throw new Exception("Veuillez remplir tous les champs.");
        }

        if (
            !is_array($etudiants_selectionnes) ||
            empty($etudiants_selectionnes)
        ) {
            throw new Exception("Veuillez sélectionner au moins un étudiant.");
        }

        if ($heure_debut >= $heure_fin) {
            throw new Exception(
                "Impossible de faire cette affectation : l'heure de fin doit être supérieure à l'heure de début."
            );
        }

        /* Vérifier le cours */

        $req = $bdd->prepare("
            SELECT id_cours, nom_cours
            FROM cours
            WHERE id_cours = ?
              AND id_fac = ?
              AND id_niveau = ?
        ");

        $req->execute([$id_cours, $id_fac, $id_niveau]);
        $coursChoisi = $req->fetch(PDO::FETCH_ASSOC);

        if (!$coursChoisi) {
            throw new Exception(
                "Le cours ne correspond pas à la faculté et au niveau choisis."
            );
        }

        /* Vérifier la salle */

        $req = $bdd->prepare("
            SELECT id_salle, nom_salle
            FROM salle
            WHERE id_salle = ?
        ");

        $req->execute([$id_salle]);
        $salleChoisie = $req->fetch(PDO::FETCH_ASSOC);

        if (!$salleChoisie) {
            throw new Exception("La salle sélectionnée n'existe pas.");
        }

        /* Vérifier le surveillant */

        $req = $bdd->prepare("
            SELECT id_surv, nom_surv, prenom_surv, email
            FROM surveillant
            WHERE id_surv = ?
        ");

        $req->execute([$id_surv]);
        $surveillantChoisi = $req->fetch(PDO::FETCH_ASSOC);

        if (!$surveillantChoisi) {
            throw new Exception("Le surveillant sélectionné n'existe pas.");
        }

        /* Vérifier les étudiants */

        $idsEtudiants = [];

        foreach ($etudiants_selectionnes as $id) {

            $id = filter_var($id, FILTER_VALIDATE_INT);

            if (!$id || $id <= 0) {
                throw new Exception("La sélection des étudiants est invalide.");
            }

            $idsEtudiants[] = $id;
        }

        $idsEtudiants = array_values(array_unique($idsEtudiants));

        $placeholders = implode(
            ",",
            array_fill(0, count($idsEtudiants), "?")
        );

        $req = $bdd->prepare("
            SELECT id_etu, nom_etu, prenom_etu, email
            FROM etudiant
            WHERE id_fac = ?
              AND id_niveau = ?
              AND id_etu IN ($placeholders)
        ");

        $req->execute(array_merge(
            [$id_fac, $id_niveau],
            $idsEtudiants
        ));

        $etudiantsValides = $req->fetchAll(PDO::FETCH_ASSOC);

        if (count($etudiantsValides) !== count($idsEtudiants)) {
            throw new Exception(
                "Un étudiant sélectionné ne correspond pas à la faculté et au niveau."
            );
        }

        /* =====================================================
           VERIFICATION DES CONFLITS AVANT LA TRANSACTION
        ===================================================== */

        $conflit = verifierConflitsAffectation(
            $bdd,
            $idsEtudiants,
            (int) $id_cours,
            (int) $id_salle,
            (int) $id_surv,
            $date_exam,
            $heure_debut,
            $heure_fin
        );

        if ($conflit !== null) {
            throw new Exception($conflit);
        }

        /* =====================================================
           TRANSACTION : ENREGISTRER LES AFFECTATIONS
        ===================================================== */

        $bdd->beginTransaction();

        $reqAffectation = $bdd->prepare("
            CALL affecter_examen(?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($idsEtudiants as $id_etu) {

            $reqAffectation->execute([
                $id_etu,
                $id_cours,
                $id_salle,
                $id_surv,
                $date_exam,
                $heure_debut,
                $heure_fin
            ]);

            $reqAffectation->closeCursor();
        }

        $bdd->commit();

        /* =====================================================
           JOURNAL D'ACTIVITÉ
        ===================================================== */

        try {

            journal(
                $bdd,
                $_SESSION["id_admin"],
                "Administrateur",
                "Admin",
                "Création d'une affectation d'examen"
            );

        } catch (Throwable $e) {
            error_log("CHACHA journal : " . $e->getMessage());
        }

        /* =====================================================
           CRÉER L'ÉVÉNEMENT
        ===================================================== */

        $dateHeureExamen = $date_exam . " " . $heure_debut;

        $titreEvenement =
            "Affectation d'examen : " . $coursChoisi["nom_cours"];

        $descriptionEvenement =
            "Cours : " . $coursChoisi["nom_cours"] . "\n" .
            "Salle : " . $salleChoisie["nom_salle"] . "\n" .
            "Date : " . $date_exam . "\n" .
            "Heure de début : " . $heure_debut . "\n" .
            "Heure de fin : " . $heure_fin;

        $idEvenement = null;

        try {

            $reqEvenement = $bdd->prepare("
                INSERT INTO evenement
                    (titre, description, type_evenement, date_evenement, lieu, cree_par, statut)
                VALUES
                    (?, ?, 'Affectation examen', ?, ?, ?, 'actif')
            ");

            $reqEvenement->execute([
                $titreEvenement,
                $descriptionEvenement,
                $dateHeureExamen,
                $salleChoisie["nom_salle"],
                (string) $_SESSION["id_admin"]
            ]);

            $idEvenement = (int) $bdd->lastInsertId();

        } catch (Throwable $e) {
            error_log("CHACHA événement : " . $e->getMessage());
        }

        /* =====================================================
           NOTIFICATIONS
        ===================================================== */

        $notificationsEnvoyees = 0;
        $notificationsEchouees = 0;
        $notificationsNonEnregistrees = 0;

        if ($idEvenement !== null) {

            $objet = "Nouvelle affectation d'examen - CHACHA";

            $stmtNotification = $bdd->prepare("
                INSERT INTO notification
                    (id_evenement, type_destinataire, id_destinataire,
                     email_destinataire, objet, message, statut_envoi)
                VALUES (?, ?, ?, ?, ?, ?, 'en_attente')
            ");

            $stmtStatut = $bdd->prepare("
                UPDATE notification
                SET statut_envoi = ?,
                    date_envoi = CASE WHEN ? = 'envoye' THEN NOW() ELSE NULL END
                WHERE id_notification = ?
            ");

            $destinataires = [];

            foreach ($etudiantsValides as $etu) {

                $destinataires[] = [
                    "type" => "etudiant",
                    "id" => (int) $etu["id_etu"],
                    "nom" => trim($etu["nom_etu"] . " " . $etu["prenom_etu"]),
                    "email" => trim((string) ($etu["email"] ?? "")),
                    "message" =>
                        "Bonjour " . trim($etu["prenom_etu"] . " " . $etu["nom_etu"]) . ",\n\n" .
                        "Une affectation d'examen a été enregistrée pour vous.\n\n" .
                        "Cours : " . $coursChoisi["nom_cours"] . "\n" .
                        "Salle : " . $salleChoisie["nom_salle"] . "\n" .
                        "Date : " . $date_exam . "\n" .
                        "Heure de début : " . $heure_debut . "\n" .
                        "Heure de fin : " . $heure_fin . "\n\n" .
                        "Veuillez vous présenter à l'heure prévue."
                ];
            }

            $destinataires[] = [
                "type" => "surveillant",
                "id" => (int) $surveillantChoisi["id_surv"],
                "nom" => trim(
                    $surveillantChoisi["prenom_surv"] . " " .
                    $surveillantChoisi["nom_surv"]
                ),
                "email" => trim((string) ($surveillantChoisi["email"] ?? "")),
                "message" =>
                    "Bonjour " . trim(
                        $surveillantChoisi["prenom_surv"] . " " .
                        $surveillantChoisi["nom_surv"]
                    ) . ",\n\n" .
                    "Vous avez été affecté à la surveillance d'un examen.\n\n" .
                    "Cours : " . $coursChoisi["nom_cours"] . "\n" .
                    "Salle : " . $salleChoisie["nom_salle"] . "\n" .
                    "Date : " . $date_exam . "\n" .
                    "Heure de début : " . $heure_debut . "\n" .
                    "Heure de fin : " . $heure_fin . "\n\n" .
                    "Merci de prendre vos dispositions."
            ];

            foreach ($destinataires as $dest) {

                $email = $dest["email"];

                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

                    $notificationsEchouees++;

                    error_log(
                        "CHACHA notification : e-mail manquant ou invalide pour " .
                        $dest["type"] . " ID " . $dest["id"]
                    );

                    continue;
                }

                $idNotification = null;

                try {

                    $stmtNotification->execute([
                        $idEvenement,
                        $dest["type"],
                        $dest["id"],
                        $email,
                        $objet,
                        $dest["message"]
                    ]);

                    $idNotification = (int) $bdd->lastInsertId();

                } catch (Throwable $e) {

                    $notificationsNonEnregistrees++;

                    error_log("CHACHA notification DB : " . $e->getMessage());

                    continue;
                }

                $envoye = envoyerNotificationEmail(
                    $email,
                    $objet,
                    $dest["message"]
                );

                $statut = $envoye ? "envoye" : "echec";

                try {

                    $stmtStatut->execute([
                        $statut,
                        $statut,
                        $idNotification
                    ]);

                } catch (Throwable $e) {
                    error_log("CHACHA statut notification : " . $e->getMessage());
                }

                if ($envoye) {
                    $notificationsEnvoyees++;
                } else {
                    $notificationsEchouees++;
                }
            }

        } else {
            $notificationsNonEnregistrees++;
        }

        /* =====================================================
           MESSAGE DE SUCCÈS
        ===================================================== */

        $message =
            "Affectation enregistrée avec succès. " .
            "E-mails envoyés : " . $notificationsEnvoyees . ". " .
            "Échecs ou adresses manquantes/invalides : " . $notificationsEchouees . ". " .
            "Notifications non enregistrées : " . $notificationsNonEnregistrees . ".";

        $typeMessage = "succes";

        [$cours, $etudiants] = chargerCoursEtudiants(
            $bdd,
            $id_fac,
            $id_niveau
        );

    } catch (PDOException $e) {

        if ($bdd->inTransaction()) {
            $bdd->rollBack();
        }

        error_log("CHACHA affectation SQL : " . $e->getMessage());

        $message =
            "Impossible d'enregistrer l'affectation. " .
            "Une contrainte de la base de données a refusé l'opération. " .
            "Vérifiez les conflits ou consultez le journal PHP.";

        $typeMessage = "erreur";

    } catch (Throwable $e) {

        if ($bdd->inTransaction()) {
            $bdd->rollBack();
        }

        $message = $e->getMessage();
        $typeMessage = "erreur";
    }

    /* Recharger les listes */

    if (!empty($id_fac) && !empty($id_niveau)) {

        [$cours, $etudiants] = chargerCoursEtudiants(
            $bdd,
            $id_fac,
            $id_niveau
        );
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Affectation des examens</title>

<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: Arial, Helvetica, sans-serif;
}

body {
    background: #f4f6f9;
}

.wrapper {
    display: flex;
    min-height: 100vh;
}

aside {
    width: 220px;
    flex-shrink: 0;
    background: #111827;
    min-height: 100vh;
}

main {
    flex: 1;
    min-width: 0;
    padding: 30px;
}

.container {
    max-width: 900px;
    margin: auto;
    background: white;
    padding: 30px;
    border-radius: 10px;
    box-shadow: 0 3px 12px rgba(0,0,0,.10);
}

h2 {
    text-align: center;
    color: #003366;
    margin-bottom: 25px;
}

h3 {
    color: #003366;
    margin-top: 20px;
    margin-bottom: 15px;
    border-bottom: 1px solid #ddd;
    padding-bottom: 8px;
}

label {
    display: block;
    font-weight: bold;
    margin-top: 15px;
    margin-bottom: 6px;
}

select,
input[type="date"],
input[type="time"] {
    width: 100%;
    padding: 11px;
    border: 1px solid #ccc;
    border-radius: 6px;
    font-size: 15px;
    background: white;
}

select:focus,
input:focus {
    outline: none;
    border-color: #003366;
}

button {
    border: none;
    border-radius: 6px;
    padding: 12px;
    cursor: pointer;
    font-size: 15px;
}

.charger {
    width: 100%;
    margin-top: 20px;
    background: #198754;
    color: white;
}

.charger:hover {
    background: #157347;
}

.enregistrer {
    width: 100%;
    margin-top: 25px;
    background: #003366;
    color: white;
    font-size: 17px;
}

.enregistrer:hover {
    background: #00264d;
}

.message {
    padding: 15px 18px;
    margin-bottom: 20px;
    border-radius: 6px;
    overflow-wrap: anywhere;
    line-height: 1.6;
}

.message.erreur {
    background: #fff0f0;
    color: #a61b1b;
    border-left: 5px solid #d62828;
}

.message.succes {
    background: #eaf8ef;
    color: #176b36;
    border-left: 5px solid #28a745;
}

.info {
    background: #e7f1ff;
    color: #003366;
    padding: 12px;
    border-radius: 6px;
    margin: 15px 0;
}

.studentList {
    margin-top: 15px;
    padding: 15px;
    border: 1px solid #ddd;
    border-radius: 8px;
    background: #f8fafc;
    max-height: 300px;
    overflow-y: auto;
}

.student {
    padding: 9px;
    border-bottom: 1px solid #e5e7eb;
}

.student:last-child {
    border-bottom: none;
}

.student label {
    margin: 0;
    font-weight: normal;
    cursor: pointer;
}

.student input[type="checkbox"] {
    width: auto;
    margin-right: 10px;
    cursor: pointer;
}

hr {
    border: 0;
    border-top: 1px solid #ddd;
    margin: 30px 0;
}

@media (max-width: 800px) {

    .wrapper {
        display: block;
    }

    aside {
        width: 100%;
        min-height: 0;
    }

    main {
        padding: 15px;
    }

    .container {
        padding: 18px;
    }
}

</style>
</head>

<body>

<div class="wrapper">

<aside>
    <?php include("menu_principal.php"); ?>
</aside>

<main>

<div class="container">

<h2>Affectation des examens</h2>

<?php if (!empty($message)): ?>

<div class="message <?= $typeMessage === "succes" ? "succes" : "erreur" ?>"
     role="alert">

    <?= htmlspecialchars($message, ENT_QUOTES, "UTF-8") ?>

</div>

<?php endif; ?>

<form method="POST">

    <h3>1. Choisir la faculté et le niveau</h3>

    <label for="id_fac">Faculté</label>

    <select name="id_fac" id="id_fac" required>

        <option value="">-- Choisir une faculté --</option>

        <?php foreach ($facultes as $f): ?>

            <option
                value="<?= (int) $f["id_fac"] ?>"
                <?= ((string) $id_fac === (string) $f["id_fac"]) ? "selected" : "" ?>
            >
                <?= htmlspecialchars($f["nom_fac"], ENT_QUOTES, "UTF-8") ?>
            </option>

        <?php endforeach; ?>

    </select>

    <label for="id_niveau">Niveau</label>

    <select name="id_niveau" id="id_niveau" required>

        <option value="">-- Choisir un niveau --</option>

        <?php foreach ($niveaux as $n): ?>

            <option
                value="<?= (int) $n["id_niveau"] ?>"
                <?= ((string) $id_niveau === (string) $n["id_niveau"]) ? "selected" : "" ?>
            >
                <?= htmlspecialchars($n["nom_niveau"], ENT_QUOTES, "UTF-8") ?>
            </option>

        <?php endforeach; ?>

    </select>

    <button type="submit" name="charger" class="charger">
        Afficher les cours et les étudiants
    </button>

</form>

<?php if (
    !empty($id_fac) &&
    !empty($id_niveau) &&
    (isset($_POST["charger"]) || isset($_POST["enregistrer"]))
): ?>

<hr>

<form method="POST">

    <input
        type="hidden"
        name="id_fac"
        value="<?= htmlspecialchars((string) $id_fac, ENT_QUOTES, "UTF-8") ?>"
    >

    <input
        type="hidden"
        name="id_niveau"
        value="<?= htmlspecialchars((string) $id_niveau, ENT_QUOTES, "UTF-8") ?>"
    >

    <h3>2. Choisir le cours</h3>

    <label for="id_cours">Cours</label>

    <select name="id_cours" id="id_cours" required>

        <option value="">-- Choisir un cours --</option>

        <?php foreach ($cours as $c): ?>

            <option
                value="<?= (int) $c["id_cours"] ?>"
                <?= ((string) $id_cours === (string) $c["id_cours"]) ? "selected" : "" ?>
            >
                <?= htmlspecialchars($c["nom_cours"], ENT_QUOTES, "UTF-8") ?>
                — <?= htmlspecialchars((string) $c["credit"], ENT_QUOTES, "UTF-8") ?>
                crédit(s)
            </option>

        <?php endforeach; ?>

    </select>

    <h3>3. Informations de l'examen</h3>

    <label for="id_salle">Salle</label>

    <select name="id_salle" id="id_salle" required>

        <option value="">-- Choisir une salle --</option>

        <?php foreach ($salles as $s): ?>

            <option
                value="<?= (int) $s["id_salle"] ?>"
                <?= ((string) $id_salle === (string) $s["id_salle"]) ? "selected" : "" ?>
            >
                <?= htmlspecialchars($s["nom_salle"], ENT_QUOTES, "UTF-8") ?>
            </option>

        <?php endforeach; ?>

    </select>

    <label for="id_surv">Surveillant</label>

    <select name="id_surv" id="id_surv" required>

        <option value="">-- Choisir un surveillant --</option>

        <?php foreach ($surveillants as $sv): ?>

            <option
                value="<?= (int) $sv["id_surv"] ?>"
                <?= ((string) $id_surv === (string) $sv["id_surv"]) ? "selected" : "" ?>
            >
                <?= htmlspecialchars(
                    $sv["nom_surv"] . " " . $sv["prenom_surv"],
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>
            </option>

        <?php endforeach; ?>

    </select>

    <label for="date_exam">Date de l'examen</label>

    <input
        type="date"
        id="date_exam"
        name="date_exam"
        value="<?= htmlspecialchars((string) $date_exam, ENT_QUOTES, "UTF-8") ?>"
        required
    >

    <label for="heure_debut">Heure de début</label>

    <input
        type="time"
        id="heure_debut"
        name="heure_debut"
        value="<?= htmlspecialchars((string) $heure_debut, ENT_QUOTES, "UTF-8") ?>"
        required
    >

    <label for="heure_fin">Heure de fin</label>

    <input
        type="time"
        id="heure_fin"
        name="heure_fin"
        value="<?= htmlspecialchars((string) $heure_fin, ENT_QUOTES, "UTF-8") ?>"
        required
    >

    <h3>4. Sélectionner les étudiants</h3>

    <div class="info">
        Les étudiants affichés appartiennent à la faculté et au niveau sélectionnés.
    </div>

    <div class="studentList">

        <?php if (empty($etudiants)): ?>

            <p style="color:red;">
                Aucun étudiant trouvé pour cette faculté et ce niveau.
            </p>

        <?php else: ?>

            <?php foreach ($etudiants as $i => $e): ?>

                <div class="student">

                    <label>

                        <input
                            type="checkbox"
                            name="etudiants[]"
                            value="<?= (int) $e["id_etu"] ?>"
                        >

                        <?= $i + 1 ?> -
                        <?= htmlspecialchars(
                            $e["nom_etu"] . " " . $e["prenom_etu"],
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>

                    </label>

                </div>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>

    <button type="submit" name="enregistrer" class="enregistrer">
        Enregistrer l'affectation
    </button>

</form>

<?php endif; ?>

</div>

</main>

</div>

</body>
</html>

