<?php
session_start();

if (
    !isset($_SESSION["id_admin"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: ../connexion.php");
    exit();
}

require_once __DIR__ . "/../connexionDB.php";
require_once __DIR__ . "/../service_notification.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: creer_evenement.php");
    exit();
}

/* =====================================================
   1. RÉCUPÉRER LES DONNÉES
===================================================== */

$titre = trim($_POST["titre"] ?? "");
$typeEvenement = trim($_POST["type_evenement"] ?? "Autre");
$dateEvenement = trim($_POST["date_evenement"] ?? "");
$lieu = trim($_POST["lieu"] ?? "");
$description = trim($_POST["description"] ?? "");
$destinataire = trim($_POST["destinataire"] ?? "");

$idEtu = filter_var($_POST["id_etu"] ?? null, FILTER_VALIDATE_INT);
$idSurv = filter_var($_POST["id_surv"] ?? null, FILTER_VALIDATE_INT);
$idFac = filter_var($_POST["id_fac"] ?? null, FILTER_VALIDATE_INT);
$idNiveau = filter_var($_POST["id_niveau"] ?? null, FILTER_VALIDATE_INT);

if ($titre === "" || $description === "") {
    exit("Erreur : le titre et la description sont obligatoires.");
}

$destinatairesAutorises = [
    "tous",
    "etudiants",
    "etudiants_faculte",
    "etudiants_niveau",
    "etudiants_faculte_niveau",
    "surveillants",
    "etudiant",
    "surveillant"
];

if (!in_array($destinataire, $destinatairesAutorises, true)) {
    exit("Erreur : veuillez sélectionner des destinataires valides.");
}

if (
    in_array($destinataire, ["etudiants_faculte", "etudiants_faculte_niveau"], true) &&
    (!$idFac || $idFac < 1)
) {
    exit("Erreur : veuillez sélectionner une faculté.");
}

if (
    in_array($destinataire, ["etudiants_niveau", "etudiants_faculte_niveau"], true) &&
    (!$idNiveau || $idNiveau < 1)
) {
    exit("Erreur : veuillez sélectionner un niveau d'étude.");
}

if ($destinataire === "etudiant" && (!$idEtu || $idEtu < 1)) {
    exit("Erreur : veuillez sélectionner un étudiant.");
}

if ($destinataire === "surveillant" && (!$idSurv || $idSurv < 1)) {
    exit("Erreur : veuillez sélectionner un surveillant.");
}

$typesAutorises = [
    "Annonce",
    "Examen",
    "Réunion",
    "Changement de programme",
    "Urgence",
    "Autre"
];

if (!in_array($typeEvenement, $typesAutorises, true)) {
    $typeEvenement = "Autre";
}

/* =====================================================
   2. VÉRIFIER LA DATE
===================================================== */

$dateSQL = null;

if ($dateEvenement !== "") {
    $dateObjet = DateTime::createFromFormat("Y-m-d\TH:i", $dateEvenement);
    $erreursDate = DateTime::getLastErrors();

    if (
        !$dateObjet ||
        (
            $erreursDate !== false &&
            (
                $erreursDate["warning_count"] > 0 ||
                $erreursDate["error_count"] > 0
            )
        )
    ) {
        exit("Erreur : la date de l'événement est invalide.");
    }

    $dateSQL = $dateObjet->format("Y-m-d H:i:s");
}

$lieuSQL = $lieu !== "" ? $lieu : null;
$creePar = (string) $_SESSION["id_admin"];

/* =====================================================
   3. SÉLECTIONNER LES DESTINATAIRES
   On récupère leur nom et prénom pour personnaliser
   chaque e-mail.
===================================================== */

try {
    $personnes = [];

    if ($destinataire === "tous") {
        $requete = $bdd->query("
            SELECT
                id_etu AS id,
                nom_etu AS nom,
                prenom_etu AS prenom,
                email,
                'etudiant' AS type_dest
            FROM etudiant

            UNION ALL

            SELECT
                id_surv AS id,
                nom_surv AS nom,
                prenom_surv AS prenom,
                email,
                'surveillant' AS type_dest
            FROM surveillant
        ");

        $personnes = $requete->fetchAll(PDO::FETCH_ASSOC);

    } elseif ($destinataire === "etudiants") {
        $requete = $bdd->query("
            SELECT
                id_etu AS id,
                nom_etu AS nom,
                prenom_etu AS prenom,
                email,
                'etudiant' AS type_dest
            FROM etudiant
        ");

        $personnes = $requete->fetchAll(PDO::FETCH_ASSOC);

    } elseif ($destinataire === "etudiants_faculte") {
        $requete = $bdd->prepare("
            SELECT
                id_etu AS id,
                nom_etu AS nom,
                prenom_etu AS prenom,
                email,
                'etudiant' AS type_dest
            FROM etudiant
            WHERE id_fac = ?
        ");

        $requete->execute([$idFac]);
        $personnes = $requete->fetchAll(PDO::FETCH_ASSOC);

    } elseif ($destinataire === "etudiants_niveau") {
        $requete = $bdd->prepare("
            SELECT
                id_etu AS id,
                nom_etu AS nom,
                prenom_etu AS prenom,
                email,
                'etudiant' AS type_dest
            FROM etudiant
            WHERE id_niveau = ?
        ");

        $requete->execute([$idNiveau]);
        $personnes = $requete->fetchAll(PDO::FETCH_ASSOC);

    } elseif ($destinataire === "etudiants_faculte_niveau") {
        $requete = $bdd->prepare("
            SELECT
                id_etu AS id,
                nom_etu AS nom,
                prenom_etu AS prenom,
                email,
                'etudiant' AS type_dest
            FROM etudiant
            WHERE id_fac = ?
              AND id_niveau = ?
        ");

        $requete->execute([$idFac, $idNiveau]);
        $personnes = $requete->fetchAll(PDO::FETCH_ASSOC);

    } elseif ($destinataire === "surveillants") {
        $requete = $bdd->query("
            SELECT
                id_surv AS id,
                nom_surv AS nom,
                prenom_surv AS prenom,
                email,
                'surveillant' AS type_dest
            FROM surveillant
        ");

        $personnes = $requete->fetchAll(PDO::FETCH_ASSOC);

    } elseif ($destinataire === "etudiant") {
        $requete = $bdd->prepare("
            SELECT
                id_etu AS id,
                nom_etu AS nom,
                prenom_etu AS prenom,
                email,
                'etudiant' AS type_dest
            FROM etudiant
            WHERE id_etu = ?
        ");

        $requete->execute([$idEtu]);
        $personnes = $requete->fetchAll(PDO::FETCH_ASSOC);

    } elseif ($destinataire === "surveillant") {
        $requete = $bdd->prepare("
            SELECT
                id_surv AS id,
                nom_surv AS nom,
                prenom_surv AS prenom,
                email,
                'surveillant' AS type_dest
            FROM surveillant
            WHERE id_surv = ?
        ");

        $requete->execute([$idSurv]);
        $personnes = $requete->fetchAll(PDO::FETCH_ASSOC);
    }

    if (empty($personnes)) {
        exit("Aucun destinataire ne correspond à votre sélection.");
    }

    /* =================================================
       4. ENREGISTRER L'ÉVÉNEMENT ET LES NOTIFICATIONS
    ================================================= */

    $bdd->beginTransaction();

    $requeteEvenement = $bdd->prepare("
        INSERT INTO evenement (
            titre,
            description,
            type_evenement,
            date_evenement,
            lieu,
            cree_par,
            statut
        )
        VALUES (?, ?, ?, ?, ?, ?, 'actif')
    ");

    $requeteEvenement->execute([
        $titre,
        $description,
        $typeEvenement,
        $dateSQL,
        $lieuSQL,
        $creePar
    ]);

    $idEvenement = (int) $bdd->lastInsertId();

    $requeteNotification = $bdd->prepare("
        INSERT INTO notification (
            id_evenement,
            type_destinataire,
            id_destinataire,
            email_destinataire,
            objet,
            message,
            statut_envoi
        )
        VALUES (?, ?, ?, ?, ?, ?, 'en_attente')
    ");

    foreach ($personnes as $personne) {
        $email = trim($personne["email"] ?? "");

        $requeteNotification->execute([
            $idEvenement,
            $personne["type_dest"],
            (int) $personne["id"],
            $email,
            $titre,
            $description
        ]);
    }

    $bdd->commit();

} catch (Throwable $e) {
    if ($bdd->inTransaction()) {
        $bdd->rollBack();
    }

    error_log("CHACHA - Enregistrement événement : " . $e->getMessage());

    exit("Erreur lors de l'enregistrement de l'événement. Consultez le journal d'erreurs PHP.");
}

/* =====================================================
   5. ENVOYER LES E-MAILS PERSONNALISÉS
===================================================== */

$envoyes = 0;
$echecs = 0;
$sansEmail = 0;

try {
    /*
     * On relit les notifications enregistrées et on associe
     * chaque notification au nom de son destinataire.
     */
    $requeteNotifications = $bdd->prepare("
        SELECT
            n.id_notification,
            n.id_destinataire,
            n.type_destinataire,
            n.email_destinataire,
            n.objet,
            n.message,
            CASE
                WHEN n.type_destinataire = 'etudiant'
                    THEN CONCAT(COALESCE(e.prenom_etu, ''), ' ', COALESCE(e.nom_etu, ''))
                WHEN n.type_destinataire = 'surveillant'
                    THEN CONCAT(COALESCE(s.prenom_surv, ''), ' ', COALESCE(s.nom_surv, ''))
                ELSE ''
            END AS nom_complet
        FROM notification n
        LEFT JOIN etudiant e
            ON n.type_destinataire = 'etudiant'
            AND n.id_destinataire = e.id_etu
        LEFT JOIN surveillant s
            ON n.type_destinataire = 'surveillant'
            AND n.id_destinataire = s.id_surv
        WHERE n.id_evenement = ?
          AND n.statut_envoi = 'en_attente'
        ORDER BY n.id_notification
    ");

    $requeteNotifications->execute([$idEvenement]);

    $miseAJour = $bdd->prepare("
        UPDATE notification
        SET statut_envoi = ?, date_envoi = ?
        WHERE id_notification = ?
    ");

    while ($n = $requeteNotifications->fetch(PDO::FETCH_ASSOC)) {
        $idNotification = (int) $n["id_notification"];
        $email = trim($n["email_destinataire"] ?? "");
        $nomDestinataire = trim($n["nom_complet"] ?? "");

        if ($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $miseAJour->execute([
                "echec",
                null,
                $idNotification
            ]);

            $sansEmail++;
            continue;
        }

        $messagePersonnalise =
            $n["message"] .
            ($lieuSQL ? "\n\nLieu : " . $lieuSQL : "") .
            ($dateSQL ? "\nDate : " . date("d/m/Y à H:i", strtotime($dateSQL)) : "");

        try {
            $succes = envoyerNotificationEmail(
                $email,
                $n["objet"],
                $messagePersonnalise,
                $nomDestinataire
            );
        } catch (Throwable $e) {
            error_log(
                "CHACHA - Échec d'envoi à " . $email . " : " . $e->getMessage()
            );
            $succes = false;
        }

        if ($succes) {
            $miseAJour->execute([
                "envoye",
                date("Y-m-d H:i:s"),
                $idNotification
            ]);

            $envoyes++;
        } else {
            $miseAJour->execute([
                "echec",
                null,
                $idNotification
            ]);

            $echecs++;
        }
    }

} catch (Throwable $e) {
    error_log("CHACHA - Traitement des e-mails : " . $e->getMessage());

    exit("L'événement est enregistré, mais une erreur est survenue pendant le traitement des e-mails.");
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Résultat de l'envoi - CHACHA</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f7fb;
            color: #333;
            margin: 0;
            padding: 25px;
        }

        .conteneur {
            max-width: 650px;
            margin: 40px auto;
            padding: 25px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,.1);
        }

        h2 { color: #003366; }

        .resultat {
            padding: 14px;
            margin: 12px 0;
            border-radius: 5px;
        }

        .ok { background: #e7f6ec; color: #176b35; }
        .erreur { background: #fdeaea; color: #a32121; }
        .info { background: #edf3fa; color: #003366; }

        a {
            display: inline-block;
            margin-top: 18px;
            color: #003366;
            font-weight: bold;
        }
    </style>
</head>

<body>
<div class="conteneur">
    <h2>Résultat de l'enregistrement</h2>

    <p>
        L'événement
        <strong><?= htmlspecialchars($titre, ENT_QUOTES, "UTF-8") ?></strong>
        a été enregistré.
    </p>

    <div class="resultat ok">
        E-mails envoyés avec succès : <strong><?= $envoyes ?></strong>
    </div>

    <div class="resultat <?= $echecs > 0 ? "erreur" : "ok" ?>">
        Échecs réels d'envoi : <strong><?= $echecs ?></strong>
    </div>

    <div class="resultat info">
        Destinataires sans adresse e-mail valide : <strong><?= $sansEmail ?></strong>
    </div>

    <a href="creer_evenement.php">Créer un autre événement</a>
    <br>
    <a href="dashboard.php">Retour au tableau de bord</a>
</div>
</body>
</html>

