<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Charger PHPMailer
require_once __DIR__ . '/vendor/autoload.php';

// Charger la configuration SMTP
require_once __DIR__ . '/smtp_config.php';


function envoyerCodeVerification(
    PDO $bdd,
    int $userId,
    string $typeUser,
    string $email
): bool {

    // 1. Vérifier le type d'utilisateur
    $typesAutorises = ['etudiant', 'surveillant', 'admin'];

    if (!in_array($typeUser, $typesAutorises, true)) {
        error_log("CHACHA : type utilisateur invalide.");
        return false;
    }

    // 2. Vérifier les informations
    if (
        $userId <= 0 ||
        !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {
        error_log("CHACHA : identifiant ou email invalide.");
        return false;
    }

    // 3. Récupérer les paramètres SMTP
    $smtpHost = CHACHA_SMTP_HOST;
    $smtpPort = CHACHA_SMTP_PORT;
    $smtpUser = CHACHA_SMTP_USER;
    $smtpPassword = CHACHA_SMTP_PASSWORD;
    $smtpFrom = CHACHA_SMTP_FROM;

    // 4. Vérifier la configuration SMTP
    if (
        empty($smtpHost) ||
        empty($smtpPort) ||
        empty($smtpUser) ||
        empty($smtpPassword) ||
        empty($smtpFrom) ||
        !filter_var($smtpUser, FILTER_VALIDATE_EMAIL) ||
        !filter_var($smtpFrom, FILTER_VALIDATE_EMAIL)
    ) {
        error_log("CHACHA : configuration SMTP invalide.");
        return false;
    }

    // 5. Générer le code
    $fuseau = new DateTimeZone('Africa/Bujumbura');

    $maintenant = new DateTimeImmutable('now', $fuseau);

    $code = (string) random_int(100000, 999999);

    $dateCreation = $maintenant->format('Y-m-d H:i:s');

    $dateExpiration = $maintenant
        ->modify('+5 minutes')
        ->format('Y-m-d H:i:s');

    // 6. Enregistrer le code dans la base de données
    try {

        $bdd->beginTransaction();

        // Désactiver les anciens codes
        $req = $bdd->prepare("
            UPDATE verification_code
            SET utilise = 1
            WHERE user_id = ?
              AND type_user = ?
              AND utilise = 0
        ");

        $req->execute([$userId, $typeUser]);

        // Enregistrer le nouveau code
        $req = $bdd->prepare("
            INSERT INTO verification_code (
                user_id,
                type_user,
                code,
                date_creation,
                date_expiration,
                utilise
            )
            VALUES (?, ?, ?, ?, ?, 0)
        ");

        $req->execute([
            $userId,
            $typeUser,
            $code,
            $dateCreation,
            $dateExpiration
        ]);

        $idCode = (int) $bdd->lastInsertId();

        $bdd->commit();

    } catch (Throwable $e) {

        if ($bdd->inTransaction()) {
            $bdd->rollBack();
        }

        error_log(
            "CHACHA : erreur base de données : " .
            $e->getMessage()
        );

        return false;
    }

    // 7. Préparer l'envoi du mail
    $mail = new PHPMailer(true);

    try {

        $mail->isSMTP();

        // Diagnostic SMTP dans les journaux du serveur
        $mail->SMTPDebug = 2;

        $mail->Debugoutput = function ($str, $level) {
            error_log("CHACHA SMTP [$level] : $str");
        };

        // Configuration SMTP
        $mail->Host = $smtpHost;
        $mail->SMTPAuth = true;

        $mail->Username = $smtpUser;
        $mail->Password = $smtpPassword;

        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = (int) $smtpPort;

        $mail->CharSet = 'UTF-8';

        // Expéditeur
        $mail->setFrom(
            $smtpFrom,
            'CHACHA - Salle d’examen'
        );

        // Destinataire
        $mail->addAddress($email);

        // Contenu du message
        $mail->isHTML(true);

        $mail->Subject = 'Votre code de vérification CHACHA';

        $codeHtml = htmlspecialchars(
            $code,
            ENT_QUOTES,
            'UTF-8'
        );

        $mail->Body = '
            <div style="font-family:Arial,sans-serif;line-height:1.6;">

                <h2>CHACHA - Salle d’examen</h2>

                <p>Bonjour,</p>

                <p>Voici votre code de vérification :</p>

                <p style="
                    font-size:28px;
                    font-weight:bold;
                    letter-spacing:5px;
                ">
                    ' . $codeHtml . '
                </p>

                <p>
                    Ce code expire dans cinq minutes
                    et ne peut être utilisé qu’une seule fois.
                </p>

                <p>
                    Si vous n’êtes pas à l’origine de cette demande,
                    ignorez ce message.
                </p>

            </div>
        ';

        $mail->AltBody =
            "Votre code de vérification CHACHA est : " .
            $code .
            ". Il expire dans cinq minutes.";

        // Envoyer le message
        $mail->send();

        error_log("CHACHA : email envoyé avec succès.");

        return true;

    } catch (Throwable $e) {

        // Invalider le code si l'envoi échoue
        try {

            $req = $bdd->prepare("
                UPDATE verification_code
                SET utilise = 1
                WHERE id = ?
            ");

            $req->execute([$idCode]);

        } catch (Throwable $dbError) {

            error_log(
                "CHACHA : impossible d'invalider le code."
            );
        }

        error_log(
            "CHACHA : erreur SMTP : " .
            $e->getMessage()
        );

        error_log(
            "CHACHA : détails PHPMailer : " .
            $mail->ErrorInfo
        );

        return false;
    }
}