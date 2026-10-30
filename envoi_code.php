<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/connexionDB.php';

/**
 * Génère un code à six chiffres, l'enregistre en base
 * et l'envoie par e-mail.
 *
 * Retourne true si l'envoi réussit, false sinon.
 */
function envoyerCodeVerification(
    PDO $bdd,
    int $userId,
    string $typeUser,
    string $email
): bool {

    // 1. Vérifier le type d'utilisateur.
    $typesAutorises = ['etudiant', 'surveillant', 'admin'];

    if (!in_array($typeUser, $typesAutorises, true)) {
        error_log("CHACHA : type d'utilisateur invalide.");
        return false;
    }

    // 2. Vérifier l'identifiant et l'adresse e-mail.
    if (
        $userId <= 0 ||
        !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {
        error_log("CHACHA : identifiant ou adresse e-mail invalide.");
        return false;
    }

    // 3. Récupérer la configuration SMTP.
    $smtpHost = getenv('CHACHA_SMTP_HOST');
    $smtpPort = getenv('CHACHA_SMTP_PORT');
    $smtpUser = getenv('CHACHA_SMTP_USER');
    $smtpPassword = getenv('CHACHA_SMTP_PASSWORD');
    $smtpFrom = getenv('CHACHA_SMTP_FROM');

    // 4. Vérifier la configuration SMTP.
    if (
        $smtpHost === false || trim($smtpHost) === '' ||
        $smtpPort === false || trim($smtpPort) === '' ||
        $smtpUser === false || trim($smtpUser) === '' ||
        $smtpPassword === false || $smtpPassword === '' ||
        $smtpFrom === false || trim($smtpFrom) === '' ||
        !ctype_digit((string) $smtpPort) ||
        (int) $smtpPort < 1 ||
        (int) $smtpPort > 65535 ||
        !filter_var($smtpUser, FILTER_VALIDATE_EMAIL) ||
        !filter_var($smtpFrom, FILTER_VALIDATE_EMAIL)
    ) {
        error_log("CHACHA : configuration SMTP absente ou invalide.");
        return false;
    }

    // 5. Définir explicitement le fuseau horaire de Bujumbura.
    $fuseau = new DateTimeZone('Africa/Bujumbura');

    // Capturer l'heure une seule fois pour garantir la cohérence.
    $maintenant = new DateTimeImmutable('now', $fuseau);

    // Générer un code sécurisé à six chiffres.
    $code = (string) random_int(100000, 999999);

    // Calculer l'expiration : cinq minutes après la création.
    $dateCreation = $maintenant->format('Y-m-d H:i:s');

    $dateExpiration = $maintenant
        ->modify('+5 minutes')
        ->format('Y-m-d H:i:s');

    /*
     * 6. Enregistrer le code dans la base de données.
     */
    try {

        $bdd->beginTransaction();

        // Invalider les anciens codes non utilisés de cet utilisateur.
        $req = $bdd->prepare("
            UPDATE verification_code
            SET utilise = 1
            WHERE user_id = ?
              AND type_user = ?
              AND utilise = 0
        ");

        $req->execute([$userId, $typeUser]);

        // Enregistrer le nouveau code avec des dates explicites.
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
            "CHACHA : erreur lors de l'enregistrement du code : "
            . $e->getMessage()
        );

        return false;
    }

    /*
     * 7. Préparer et envoyer l'e-mail.
     */
    $mail = new PHPMailer(true);

    try {

        $mail->isSMTP();

        $mail->Host = $smtpHost;
        $mail->SMTPAuth = true;
        $mail->Username = $smtpUser;
        $mail->Password = $smtpPassword;

        // Connexion SMTP sécurisée.
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = (int) $smtpPort;

        $mail->CharSet = 'UTF-8';

        $mail->setFrom(
            $smtpFrom,
            'CHACHA - Salle d’examen'
        );

        $mail->addAddress($email);

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
                    Ce code expire dans cinq minutes et ne peut être
                    utilisé qu’une seule fois.
                </p>

                <p>
                    Si vous n’êtes pas à l’origine de cette demande,
                    ignorez ce message.
                </p>
            </div>
        ';

        $mail->AltBody =
            "Bonjour. Votre code de vérification CHACHA est : "
            . $code
            . ". Il expire dans cinq minutes et ne peut être utilisé qu'une seule fois.";

        $mail->send();

        return true;

    } catch (Exception $e) {

        // Si l'envoi échoue, invalider le code enregistré.
        try {

            $req = $bdd->prepare("
                UPDATE verification_code
                SET utilise = 1
                WHERE id = ?
            ");

            $req->execute([$idCode]);

        } catch (Throwable $dbError) {

            error_log(
                "CHACHA : impossible d'invalider le code après l'échec de l'envoi."
            );
        }

        error_log(
            "CHACHA : erreur SMTP PHPMailer : "
            . $mail->ErrorInfo
        );

        return false;
    }
}

