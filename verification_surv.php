<?php

session_start();

require_once("connexionDB.php");

$message = "";

$timeout = 300; // 5 minutes d'inactivité

// Vérifier le délai d'inactivité
if (
    isset($_SESSION["last_activity"]) &&
    time() - $_SESSION["last_activity"] > $timeout
) {

    // Supprimer uniquement les données de vérification temporaires
    unset(
        $_SESSION["verification_user"],
        $_SESSION["verification_type"],
        $_SESSION["connexion_id"],
        $_SESSION["code_test"],
        $_SESSION["last_activity"]
    );

    header("Location: login_surv.php?timeout=1");
    exit();
}

$_SESSION["last_activity"] = time();

// Vérifier que la demande vient bien de login_surv.php
if (
    !isset($_SESSION["verification_user"]) ||
    !isset($_SESSION["verification_type"]) ||
    $_SESSION["verification_type"] !== "surveillant"
) {

    header("Location: login_surv.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $code = trim($_POST["code"] ?? "");

    // Vérifier que le code contient exactement 6 chiffres
    if (!preg_match('/^[0-9]{6}$/', $code)) {

        $message = "Veuillez entrer un code valide de 6 chiffres.";

    } else {

        // Chercher un code valide, non utilisé et non expiré
        $req = $bdd->prepare("
            SELECT id
            FROM verification_code
            WHERE user_id = ?
              AND type_user = 'surveillant'
              AND code = ?
              AND utilise = 0
              AND date_expiration IS NOT NULL
              AND date_expiration >= NOW()
            ORDER BY date_creation DESC
            LIMIT 1
        ");

        $req->execute([
            $_SESSION["verification_user"],
            $code
        ]);

        $verification = $req->fetch(PDO::FETCH_ASSOC);

        if ($verification) {

            // Marquer le code comme utilisé une seule fois
            $updateCode = $bdd->prepare("
                UPDATE verification_code
                SET utilise = 1
                WHERE id = ?
                  AND user_id = ?
                  AND type_user = 'surveillant'
                  AND utilise = 0
                  AND date_expiration >= NOW()
            ");

            $updateCode->execute([
                $verification["id"],
                $_SESSION["verification_user"]
            ]);

            if ($updateCode->rowCount() === 1) {

                // Mettre à jour l'historique de connexion
                if (isset($_SESSION["connexion_id"])) {

                    $updateLog = $bdd->prepare("
                        UPDATE connexion_log
                        SET statut = 'success'
                        WHERE id = ?
                          AND user_id = ?
                          AND type_user = 'surveillant'
                          AND statut = 'verification'
                    ");

                    $updateLog->execute([
                        $_SESSION["connexion_id"],
                        $_SESSION["verification_user"]
                    ]);
                }

                // Récupérer les informations du surveillant
                $reqUser = $bdd->prepare("
                    SELECT id_surv, nom_surv, prenom_surv
                    FROM surveillant
                    WHERE id_surv = ?
                    LIMIT 1
                ");

                $reqUser->execute([
                    $_SESSION["verification_user"]
                ]);

                $surveillant = $reqUser->fetch(PDO::FETCH_ASSOC);

                if ($surveillant) {

                    // Créer une nouvelle session authentifiée
                    session_regenerate_id(true);

                    $_SESSION["id_surv"] = (int)$surveillant["id_surv"];
                    $_SESSION["role"] = "surveillant";

                    // Conserver les noms si d'autres pages en ont besoin
                    $_SESSION["nom_surv"] = $surveillant["nom_surv"];
                    $_SESSION["prenom_surv"] = $surveillant["prenom_surv"];

                    // Réinitialiser le délai d'inactivité
                    $_SESSION["last_activity"] = time();

                    // Nettoyer les données temporaires de vérification
                    unset(
                        $_SESSION["verification_user"],
                        $_SESSION["verification_type"],
                        $_SESSION["connexion_id"],
                        $_SESSION["code_test"]
                    );

                    header("Location: espace_surv.php");
                    exit();

                } else {

                    $message = "Compte surveillant introuvable.";
                }

            } else {

                $message = "Ce code a expiré ou a déjà été utilisé. Demandez un nouveau code.";
            }

        } else {

            $message = "Code incorrect, expiré ou déjà utilisé.";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="fr">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Vérification 2FA Surveillant</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            min-height: 100vh;
            background: #eef4f7;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .container {
            width: 380px;
            max-width: 100%;
            background: white;
            padding: 35px;
            border-radius: 15px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }

        h2 {
            color: #355c7d;
            margin-bottom: 15px;
            font-size: 24px;
            text-align: center;
        }

        p {
            color: #555;
            text-align: center;
            margin-bottom: 20px;
            line-height: 1.5;
        }

        input {
            width: 100%;
            padding: 13px;
            border: 1px solid #ccc;
            border-radius: 8px;
            margin-bottom: 15px;
            text-align: center;
            letter-spacing: 4px;
            font-size: 18px;
        }

        button {
            width: 100%;
            padding: 12px;
            background: #7aa6c2;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
        }

        button:hover {
            background: #5c8aaa;
        }

        .message {
            margin-top: 20px;
            padding: 10px;
            background: #ffe8e8;
            color: #c0392b;
            border-radius: 8px;
            text-align: center;
        }
    </style>

</head>

<body>

    <div class="container">

        <h2>Authentification à deux facteurs</h2>

        <p>Un code de vérification a été envoyé à votre adresse e-mail. Entrez-le pour accéder à votre espace.</p>

        <form method="POST">

            <input
                type="text"
                name="code"
                placeholder="Code à 6 chiffres"
                inputmode="numeric"
                pattern="[0-9]{6}"
                maxlength="6"
                autocomplete="one-time-code"
                required
            >

            <button type="submit">Valider</button>

        </form>

        <?php if (!empty($message)): ?>

            <div class="message">
                <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
            </div>

        <?php endif; ?>

    </div>

</body>

</html>