<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Envoie une notification professionnelle par e-mail.
 *
 * @param string $email   Adresse du destinataire
 * @param string $objet   Objet du message
 * @param string $message Contenu de la notification
 * @param string $nomDestinataire Nom complet du destinataire (facultatif)
 *
 * @return bool
 */
function envoyerNotificationEmail(
    string $email,
    string $objet,
    string $message,
    string $nomDestinataire = ''
): bool {

    // 1. Vérifier l'adresse e-mail
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        error_log("CHACHA : adresse e-mail invalide.");
        return false;
    }

    // 2. Configuration SMTP existante
    $smtpHost = getenv('CHACHA_SMTP_HOST')
        ?: ($_SERVER['CHACHA_SMTP_HOST'] ?? '');

    $smtpPort = getenv('CHACHA_SMTP_PORT')
        ?: ($_SERVER['CHACHA_SMTP_PORT'] ?? '');

    $smtpUser = getenv('CHACHA_SMTP_USER')
        ?: ($_SERVER['CHACHA_SMTP_USER'] ?? '');

    $smtpPassword = getenv('CHACHA_SMTP_PASSWORD')
        ?: ($_SERVER['CHACHA_SMTP_PASSWORD'] ?? '');

    $smtpFrom = getenv('CHACHA_SMTP_FROM')
        ?: ($_SERVER['CHACHA_SMTP_FROM'] ?? '');

    if (
        $smtpHost === '' ||
        $smtpPort === '' ||
        $smtpUser === '' ||
        $smtpPassword === '' ||
        $smtpFrom === '' ||
        !filter_var($smtpFrom, FILTER_VALIDATE_EMAIL) ||
        !ctype_digit((string) $smtpPort) ||
        (int) $smtpPort < 1 ||
        (int) $smtpPort > 65535
    ) {
        error_log("CHACHA : configuration SMTP manquante ou invalide.");
        return false;
    }

    // 3. Charger PHPMailer
    $autoload = __DIR__ . '/vendor/autoload.php';

    if (!file_exists($autoload)) {
        error_log("CHACHA : fichier vendor/autoload.php introuvable.");
        return false;
    }

    require_once $autoload;

    if (!class_exists(PHPMailer::class)) {
        error_log("CHACHA : PHPMailer indisponible.");
        return false;
    }

    // 4. Préparer l'e-mail
    try {
        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host       = $smtpHost;
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtpUser;
        $mail->Password   = $smtpPassword;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int) $smtpPort;

        $mail->CharSet  = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->Timeout  = 20;

        $nomPlateforme = 'CHACHA - Salle d’examen';

        $mail->setFrom($smtpFrom, $nomPlateforme);
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = $objet;

        // 5. Préparer le contenu sécurisé
        $nomHTML = htmlspecialchars(
            $nomDestinataire !== '' ? $nomDestinataire : 'Utilisateur',
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );

        $objetHTML = htmlspecialchars(
            $objet,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );

        $messageHTML = nl2br(
            htmlspecialchars(
                $message,
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            )
        );

        // IMPORTANT : adapter ce lien à l'adresse réelle de l'application.
        $urlApplication = 'http://localhost/salleexamen/chacha/';

        $urlHTML = htmlspecialchars(
            $urlApplication,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );

        // 6. Modèle HTML inspiré de l'exemple fourni
        $mail->Body = '
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . $objetHTML . '</title>
</head>

<body style="margin:0;padding:0;background-color:#f4f6f9;
             font-family:Arial,Helvetica,sans-serif;color:#333333;">

    <div style="max-width:600px;margin:25px auto;background:#ffffff;
                border:1px solid #e4e8ee;border-radius:8px;overflow:hidden;">

        <div style="padding:24px 30px;background:#ffffff;
                    border-bottom:1px solid #eeeeee;">
            <h2 style="margin:0;color:#003366;font-size:22px;">
                CHACHA - Salle d’examen
            </h2>
        </div>

        <div style="padding:30px;">

            <p style="font-size:16px;line-height:1.7;margin-top:0;">
                Bonjour ' . $nomHTML . ',
            </p>

            <h3 style="font-size:20px;color:#222222;margin-bottom:12px;">
                ' . $objetHTML . '
            </h3>

            <div style="font-size:15px;line-height:1.8;color:#4b5563;">
                ' . $messageHTML . '
            </div>

            <div style="margin:28px 0;">
                <a href="' . $urlHTML . '"
                   style="display:inline-block;background:#003366;
                          color:#ffffff;text-decoration:none;
                          padding:13px 22px;border-radius:5px;
                          font-size:15px;font-weight:bold;">
                    Ouvrir dans l’application
                </a>
            </div>

            <p style="font-size:12px;line-height:1.8;color:#777777;
                      border-top:1px solid #eeeeee;padding-top:18px;">
                Cet e-mail est envoyé automatiquement par la plateforme
                CHACHA - Salle d’examen. Vous pouvez gérer vos préférences
                de notification dans votre profil.
            </p>

        </div>

        <div style="padding:18px 30px;background:#f8f9fb;
                    color:#777777;font-size:12px;">
            CHACHA - Salle d’examen
        </div>

    </div>

</body>
</html>';

        // Version texte
        $mail->AltBody =
            "Bonjour " .
            ($nomDestinataire !== '' ? $nomDestinataire : 'Utilisateur') .
            ",\n\n" .
            $objet . "\n\n" .
            $message . "\n\n" .
            "Ouvrir dans l’application : " . $urlApplication . "\n\n" .
            "Cet e-mail est envoyé automatiquement par la plateforme " .
            $nomPlateforme .
            ". Vous pouvez gérer vos préférences de notification dans votre profil.";

        // 7. Envoyer
        $mail->send();

        error_log("CHACHA : notification envoyée avec succès.");

        return true;

    } catch (\Throwable $e) {
        $erreur = isset($mail) && $mail instanceof PHPMailer
            ? $mail->ErrorInfo
            : '';

        error_log(
            "CHACHA : échec d'envoi. Erreur PHPMailer : " .
            $erreur . " | Exception : " . $e->getMessage()
        );

        return false;
    }
}

