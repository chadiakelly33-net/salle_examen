<?php

session_start();

require_once __DIR__ . "/connexionDB.php";

$message = "";

/*
|--------------------------------------------------------------------------
| 1. Vérifier que l'étudiant vient de login_etu.php
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION["verification_user"]) ||
    !isset($_SESSION["verification_type"]) ||
    $_SESSION["verification_type"] !== "etudiant"
) {
    header("Location: login_etu.php");
    exit();
}

$userId = (int) $_SESSION["verification_user"];

/*
|--------------------------------------------------------------------------
| 2. Vérifier le code soumis
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $code = trim($_POST["code"] ?? "");

    if (!preg_match('/^\d{6}$/', $code)) {

        $message = "Veuillez saisir un code valide à 6 chiffres.";

    } else {

        try {

            /*
             * Rechercher un code :
             * - appartenant à cet étudiant ;
             * - correspondant au type étudiant ;
             * - non utilisé ;
             * - non expiré.
             */
            $req = $bdd->prepare("
                SELECT id, user_id, type_user, code,
                       date_expiration, utilise
                FROM verification_code
                WHERE user_id = ?
                  AND type_user = 'etudiant'
                  AND code = ?
                  AND utilise = 0
                  AND date_expiration IS NOT NULL
                  AND date_expiration >= NOW()
                ORDER BY date_creation DESC
                LIMIT 1
            ");

            $req->execute([
                $userId,
                $code
            ]);

            $verification = $req->fetch(PDO::FETCH_ASSOC);

            if (!$verification) {

                $message = "Code incorrect, expiré ou déjà utilisé.";

            } else {

                /*
                 * Vérifier que l'étudiant existe toujours.
                 */
                $reqUser = $bdd->prepare("
                    SELECT id_etu, nom_etu, prenom_etu
                    FROM etudiant
                    WHERE id_etu = ?
                    LIMIT 1
                ");

                $reqUser->execute([$userId]);

                $user = $reqUser->fetch(PDO::FETCH_ASSOC);

                if (!$user) {

                    $message = "Compte étudiant introuvable.";

                } else {

                    /*
                     * Marquer le code comme utilisé.
                     *
                     * La condition utilise = 0 empêche de valider
                     * deux fois le même code.
                     */
                    $majCode = $bdd->prepare("
                        UPDATE verification_code
                        SET utilise = 1
                        WHERE id = ?
                          AND user_id = ?
                          AND type_user = 'etudiant'
                          AND utilise = 0
                          AND date_expiration >= NOW()
                    ");

                    $majCode->execute([
                        $verification["id"],
                        $userId
                    ]);

                    if ($majCode->rowCount() !== 1) {

                        $message = "Ce code a expiré ou a déjà été utilisé. Demandez un nouveau code.";

                    } else {

                        /*
                         * Mettre à jour l'historique de connexion.
                         */
                        if (isset($_SESSION["connexion_id"])) {

                            $updateLog = $bdd->prepare("
                                UPDATE connexion_log
                                SET statut = 'success'
                                WHERE id = ?
                                  AND user_id = ?
                                  AND type_user = 'etudiant'
                                  AND statut = 'verification'
                            ");

                            $updateLog->execute([
                                $_SESSION["connexion_id"],
                                $userId
                            ]);
                        }

                        /*
                         * Créer la session définitive étudiant.
                         */
                        session_regenerate_id(true);

                        $_SESSION["id_etu"] = (int) $user["id_etu"];
                        $_SESSION["role"] = "etudiant";
                        $_SESSION["nom_etu"] = $user["nom_etu"];
                        $_SESSION["prenom_etu"] = $user["prenom_etu"];

                        /*
                         * Nettoyer les informations temporaires.
                         */
                        unset($_SESSION["verification_user"]);
                        unset($_SESSION["verification_type"]);
                        unset($_SESSION["code_test"]);
                        unset($_SESSION["connexion_id"]);

                        /*
                         * Accès à l'espace étudiant.
                         */
                        header("Location: espace_etu.php");
                        exit();
                    }
                }
            }

        } catch (PDOException $e) {

            error_log(
                "CHACHA - Erreur de vérification 2FA étudiant : "
                . $e->getMessage()
            );

            $message = "Une erreur technique est survenue. Veuillez réessayer.";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="fr">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Vérification 2FA</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, Helvetica, sans-serif;
        }

        body {
            min-height: 100vh;
            background: #eef4f7;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        h2 {
            color: #355c7d;
            margin-bottom: 25px;
            font-size: 24px;
            text-align: center;
        }

        form {
            background: white;
            width: 380px;
            max-width: 100%;
            padding: 35px;
            border-radius: 15px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        input {
            width: 100%;
            padding: 13px;
            border: 1px solid #cfd8dc;
            border-radius: 8px;
            font-size: 16px;
            text-align: center;
            letter-spacing: 3px;
            outline: none;
        }

        input:focus {
            border-color: #7aa6c2;
            box-shadow: 0 0 5px rgba(122, 166, 194, 0.5);
        }

        button {
            width: 100%;
            padding: 12px;
            margin-top: 20px;
            background: #7aa6c2;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            transition: 0.3s;
        }

        button:hover {
            background: #5c8aaa;
        }

        .message {
            margin-top: 20px;
            background: #ffe8e8;
            color: #c0392b;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            text-align: center;
            max-width: 380px;
        }
    </style>

</head>

<body>

    <h2>Authentification à deux facteurs</h2>

    <form method="POST" action="verification.php">

        <input
            type="text"
            name="code"
            placeholder="Entrer le code reçu par e-mail"
            inputmode="numeric"
            pattern="[0-9]{6}"
            minlength="6"
            maxlength="6"
            autocomplete="one-time-code"
            required
        >

        <button type="submit">
            Valider
        </button>

    </form>

    <?php if (!empty($message)): ?>

        <div class="message" role="alert">
            <?= htmlspecialchars($message, ENT_QUOTES, "UTF-8") ?>
        </div>

    <?php endif; ?>

</body>

</html>