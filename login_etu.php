<?php

session_start();

require_once __DIR__ . "/connexionDB.php";
require_once __DIR__ . "/envoi_code.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $login = trim($_POST["login"] ?? "");
    $password = $_POST["password"] ?? "";

    // Recherche de l'étudiant
    $req = $bdd->prepare("
        SELECT *
        FROM etudiant
        WHERE login = ?
        LIMIT 1
    ");

    $req->execute([$login]);
    $user = $req->fetch(PDO::FETCH_ASSOC);

    if ($user) {

        // Vérifier si le compte est bloqué
        if ((int)$user["compte_bloque"] === 1) {

            $dateBlocage = $user["date_blocage"] ?? null;
            $tempsBlocage = $dateBlocage ? strtotime($dateBlocage) : false;

            if ($tempsBlocage !== false && $tempsBlocage + 60 > time()) {

                $message = "Votre compte est temporairement verrouillé. Réessayez dans 1 minute.";

            } else {

                // Déblocage automatique après 60 secondes
                $deblocage = $bdd->prepare("
                    UPDATE etudiant
                    SET tentatives = 0,
                        compte_bloque = 0,
                        date_blocage = NULL
                    WHERE id_etu = ?
                ");

                $deblocage->execute([$user["id_etu"]]);

                $user["tentatives"] = 0;
                $user["compte_bloque"] = 0;
            }
        }

        // Continuer seulement si le compte n'est pas bloqué
        if ((int)$user["compte_bloque"] === 0) {

            // Vérification du mot de passe
            if (password_verify($password, $user["motdepasse"])) {

                // Réinitialiser les tentatives échouées
                $reset = $bdd->prepare("
                    UPDATE etudiant
                    SET tentatives = 0
                    WHERE id_etu = ?
                ");

                $reset->execute([$user["id_etu"]]);

                // Vérifier que l'étudiant possède une adresse e-mail
                $email = trim($user["email"] ?? "");

                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

                    $message = "Aucune adresse e-mail valide n'est enregistrée pour votre compte. Contactez l'administrateur.";

                } else {

                    try {

                        // Créer l'entrée d'historique avant la 2FA
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
                            $user["id_etu"],
                            trim($user["nom_etu"] . " " . $user["prenom_etu"]),
                            $_SERVER["REMOTE_ADDR"] ?? "",
                            "etudiant",
                            "verification"
                        ]);

                        $connexionId = $bdd->lastInsertId();

                        // Envoyer le code de vérification par e-mail
                        $envoiReussi = envoyerCodeVerification(
                            $bdd,
                            (int)$user["id_etu"],
                            "etudiant",
                            $email
                        );

                        if ($envoiReussi) {

                            // Régénérer l'identifiant de session
                            session_regenerate_id(true);

                            // Conserver uniquement les informations temporaires de 2FA
                            $_SESSION["verification_user"] = (int)$user["id_etu"];
                            $_SESSION["verification_type"] = "etudiant";
                            $_SESSION["connexion_id"] = $connexionId;

                            // Le code n'est ni affiché ni stocké dans la session.
                            unset($_SESSION["code_test"]);

                            header("Location: verification.php");
                            exit();

                        } else {

                            // L'envoi a échoué : marquer la connexion comme échouée
                            $updateLog = $bdd->prepare("
                                UPDATE connexion_log
                                SET statut = 'failed'
                                WHERE id = ?
                            ");

                            $updateLog->execute([$connexionId]);

                            $message = "Impossible d'envoyer le code de vérification. Vérifiez la configuration e-mail ou contactez l'administrateur.";

                        }

                    } catch (Throwable $e) {

                        error_log("CHACHA - Erreur de connexion étudiant : " . $e->getMessage());

                        $message = "Une erreur technique est survenue. Veuillez réessayer.";
                    }
                }

            } else {

                // Mot de passe incorrect : enregistrer l'échec
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
                    $user["id_etu"],
                    trim($user["nom_etu"] . " " . $user["prenom_etu"]),
                    $_SERVER["REMOTE_ADDR"] ?? "",
                    "etudiant",
                    "failed"
                ]);

                // Incrémenter les tentatives
                $tentatives = (int)$user["tentatives"] + 1;

                if ($tentatives >= 3) {

                    $bloquer = $bdd->prepare("
                        UPDATE etudiant
                        SET tentatives = ?,
                            compte_bloque = 1,
                            date_blocage = NOW()
                        WHERE id_etu = ?
                    ");

                    $bloquer->execute([
                        $tentatives,
                        $user["id_etu"]
                    ]);

                    $message = "Compte verrouillé après 3 tentatives.";

                } else {

                    $majTentatives = $bdd->prepare("
                        UPDATE etudiant
                        SET tentatives = ?
                        WHERE id_etu = ?
                    ");

                    $majTentatives->execute([
                        $tentatives,
                        $user["id_etu"]
                    ]);

                    $reste = 3 - $tentatives;

                    $message = "Mot de passe incorrect. Il vous reste "
                        . $reste . " tentative(s).";
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
            "etudiant",
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

    <title>Connexion Étudiant</title>

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
            border: 1px solid #cfd8dc;
            border-radius: 8px;
        }

        input:focus {
            outline: none;
            border-color: #7aa6c2;
        }

        button {
            width: 100%;
            padding: 12px;
            background: #7aa6c2;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
        }

        button:hover {
            background: #5c8aaa;
        }

        .message {
            margin-top: 20px;
            padding: 10px;
            text-align: center;
            background: #ffe8e8;
            color: #c0392b;
            border-radius: 8px;
        }
    </style>
</head>

<body>

    <div class="container">

        <h2>Connexion Étudiant</h2>

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

            <button type="submit">
                Se connecter
            </button>

        </form>

        <?php if (!empty($message)): ?>
            <div class="message" role="alert">
                <?= htmlspecialchars($message, ENT_QUOTES, "UTF-8") ?>
            </div>
        <?php endif; ?>

    </div>

</body>
</html>