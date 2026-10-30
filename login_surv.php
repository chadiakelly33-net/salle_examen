<?php

session_start();

require_once("connexionDB.php");
require_once("envoi_code.php");

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $login = trim($_POST["login"] ?? "");
    $password = $_POST["password"] ?? "";

    // Recherche du surveillant
    $req = $bdd->prepare("
        SELECT *
        FROM surveillant
        WHERE login = ?
        LIMIT 1
    ");

    $req->execute([$login]);
    $user = $req->fetch(PDO::FETCH_ASSOC);

    if ($user) {

        // Vérifier si le compte est bloqué
        if ((int)$user["compte_bloque"] === 1) {

            if (
                !empty($user["date_blocage"]) &&
                strtotime($user["date_blocage"]) + 60 > time()
            ) {

                $message = "Votre compte est temporairement verrouillé. Réessayez dans 1 minute.";

            } else {

                // Déblocage automatique après 60 secondes
                $bdd->prepare("
                    UPDATE surveillant
                    SET tentatives = 0,
                        compte_bloque = 0,
                        date_blocage = NULL
                    WHERE id_surv = ?
                ")->execute([$user["id_surv"]]);

                $user["tentatives"] = 0;
                $user["compte_bloque"] = 0;
            }
        }

        // Continuer seulement si le compte n'est pas bloqué
        if ((int)$user["compte_bloque"] === 0) {

            // Vérifier le mot de passe
            if (password_verify($password, $user["motdepasse"])) {

                // Réinitialiser les tentatives
                $bdd->prepare("
                    UPDATE surveillant
                    SET tentatives = 0
                    WHERE id_surv = ?
                ")->execute([$user["id_surv"]]);

                // Enregistrer la connexion avant la 2FA
                $log = $bdd->prepare("
                    INSERT INTO connexion_log
                    (
                        user_id,
                        nom_utilisateur,
                        adresse_ip,
                        type_user,
                        statut,
                        date_connexion
                    )
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");

                $log->execute([
                    $user["id_surv"],
                    trim($user["nom_surv"] . " " . $user["prenom_surv"]),
                    $_SERVER["REMOTE_ADDR"] ?? "",
                    "surveillant",
                    "verification"
                ]);

                $connexionId = $bdd->lastInsertId();

                // Vérifier que l'adresse e-mail existe
                $email = trim($user["email"] ?? "");

                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

                    $bdd->prepare("
                        UPDATE connexion_log
                        SET statut = 'failed'
                        WHERE id = ?
                    ")->execute([$connexionId]);

                    $message = "Aucune adresse e-mail valide n'est enregistrée pour ce compte. Contactez l'administrateur.";

                } else {

                    try {

                        // Générer et envoyer le code par e-mail
                        $envoye = envoyerCodeVerification(
                            $bdd,
                            (int)$user["id_surv"],
                            "surveillant",
                            $email
                        );

                        if ($envoye) {

                            // Régénérer l'identifiant de session
                            session_regenerate_id(true);

                            // Session temporaire pour la vérification
                            $_SESSION["verification_user"] = (int)$user["id_surv"];
                            $_SESSION["verification_type"] = "surveillant";
                            $_SESSION["connexion_id"] = (int)$connexionId;

                            // Ne jamais conserver ou afficher le code
                            unset($_SESSION["code_test"]);

                            header("Location: verification_surv.php");
                            exit();

                        } else {

                            $bdd->prepare("
                                UPDATE connexion_log
                                SET statut = 'failed'
                                WHERE id = ?
                            ")->execute([$connexionId]);

                            $message = "Impossible d'envoyer le code de vérification. Vérifiez la configuration e-mail ou contactez l'administrateur.";
                        }

                    } catch (Throwable $e) {

                        error_log("Erreur 2FA surveillant : " . $e->getMessage());

                        $bdd->prepare("
                            UPDATE connexion_log
                            SET statut = 'failed'
                            WHERE id = ?
                        ")->execute([$connexionId]);

                        $message = "Une erreur est survenue lors de l'envoi du code. Réessayez plus tard.";
                    }
                }

            } else {

                // Mot de passe incorrect
                $log = $bdd->prepare("
                    INSERT INTO connexion_log
                    (
                        user_id,
                        nom_utilisateur,
                        adresse_ip,
                        type_user,
                        statut,
                        date_connexion
                    )
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");

                $log->execute([
                    $user["id_surv"],
                    trim($user["nom_surv"] . " " . $user["prenom_surv"]),
                    $_SERVER["REMOTE_ADDR"] ?? "",
                    "surveillant",
                    "failed"
                ]);

                $tentatives = (int)$user["tentatives"] + 1;

                if ($tentatives >= 3) {

                    $bdd->prepare("
                        UPDATE surveillant
                        SET tentatives = ?,
                            compte_bloque = 1,
                            date_blocage = NOW()
                        WHERE id_surv = ?
                    ")->execute([
                        $tentatives,
                        $user["id_surv"]
                    ]);

                    $message = "Compte verrouillé après 3 tentatives.";

                } else {

                    $bdd->prepare("
                        UPDATE surveillant
                        SET tentatives = ?
                        WHERE id_surv = ?
                    ")->execute([
                        $tentatives,
                        $user["id_surv"]
                    ]);

                    $reste = 3 - $tentatives;

                    $message = "Mot de passe incorrect. Il vous reste " . $reste . " tentative(s).";
                }
            }
        }

    } else {

        // Login inexistant
        $log = $bdd->prepare("
            INSERT INTO connexion_log
            (
                user_id,
                nom_utilisateur,
                adresse_ip,
                type_user,
                statut,
                date_connexion
            )
            VALUES (?, ?, ?, ?, ?, NOW())
        ");

        $log->execute([
            null,
            "Utilisateur inconnu",
            $_SERVER["REMOTE_ADDR"] ?? "",
            "surveillant",
            "failed"
        ]);

        $message = "Login introuvable.";
    }
}

?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Surveillant</title>

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
            text-align: center;
            color: #355c7d;
            margin-bottom: 25px;
        }

        input {
            width: 100%;
            padding: 13px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 8px;
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

        <h2>Connexion Surveillant</h2>

        <form method="POST">

            <input
                type="text"
                name="login"
                placeholder="Login"
                autocomplete="username"
                required
            >

            <input
                type="password"
                name="password"
                placeholder="Mot de passe"
                autocomplete="current-password"
                required
            >

            <button type="submit">Se connecter</button>

        </form>

        <?php if (!empty($message)): ?>
            <div class="message">
                <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

    </div>

</body>
</html>