<?php
session_start();
require_once "connexionDB.php";

if (($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit("Accès réservé à l'administration.");
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom_document'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $type = $_POST['type_destinataire'] ?? '';
    $destinataire = filter_var(
        $_POST['id_destinataire'] ?? '',
        FILTER_VALIDATE_INT
    );

    if ($nom === '' || $destinataire === false || $destinataire < 1 ||
        !in_array($type, ['etudiant', 'surveillant'], true)) {
        $message = "Informations invalides.";
    } elseif (!isset($_FILES['fichier']) ||
              $_FILES['fichier']['error'] !== UPLOAD_ERR_OK) {
        $message = "Choisissez un fichier valide.";
    } elseif ($_FILES['fichier']['size'] > 10 * 1024 * 1024) {
        $message = "Maximum 10 Mo.";
    } else {
        $extensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt'];
        $original = basename($_FILES['fichier']['name']);
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));

        $mime = (new finfo(FILEINFO_MIME_TYPE))
            ->file($_FILES['fichier']['tmp_name']);

        $mimes = [
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'txt' => 'text/plain'
        ];

        if (!in_array($extension, $extensions, true) ||
            !isset($mimes[$extension]) || $mime !== $mimes[$extension]) {
            $message = "Format non autorisé.";
        } else {
            $dossier = __DIR__ . '/documents_prives/';

            if (!is_dir($dossier)) {
                mkdir($dossier, 0700, true);
            }

            $stockage = bin2hex(random_bytes(20)) . '.' . $extension;
            $chemin = $dossier . $stockage;

            if (move_uploaded_file($_FILES['fichier']['tmp_name'], $chemin)) {
                try {
                    $bdd->beginTransaction();

                    $stmt = $bdd->prepare(
                        "INSERT INTO document
                        (nom_document, fichier, description, id_admin, archive)
                        VALUES (?, ?, ?, ?, 0)"
                    );
                    $stmt->execute([$nom, $stockage, $description, 0]);

                    $idDoc = (int)$bdd->lastInsertId();

                    $stmt = $bdd->prepare(
                        "INSERT INTO partage_document
                        (id_document, type_destinataire, id_etu, id_surv)
                        VALUES (?, ?, ?, ?)"
                    );

                    $stmt->execute([
                        $idDoc,
                        $type,
                        $type === 'etudiant' ? $destinataire : null,
                        $type === 'surveillant' ? $destinataire : null
                    ]);

                    $bdd->commit();
                    $_SESSION['message_document'] = "Document envoyé.";
                    header("Location: documents.php");
                    exit();
                } catch (Throwable $e) {
                    if ($bdd->inTransaction()) {
                        $bdd->rollBack();
                    }
                    if (is_file($chemin)) {
                        unlink($chemin);
                    }
                    $message = "Erreur lors de l'enregistrement.";
                }
            } else {
                $message = "Échec du stockage du fichier.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Envoyer un document</title>
</head>
<body>
<h2>Envoyer un document</h2>

<p style="color:red"><?= htmlspecialchars($message) ?></p>

<form method="POST" enctype="multipart/form-data">
    <label>Nom du document</label><br>
    <input type="text" name="nom_document" required><br><br>

    <label>Description</label><br>
    <textarea name="description"></textarea><br><br>

    <label>Destinataire</label><br>
    <select name="type_destinataire" required>
        <option value="etudiant">Étudiant</option>
        <option value="surveillant">Surveillant</option>
    </select><br><br>

    <label>ID de l'étudiant ou du surveillant</label><br>
    <input type="number" name="id_destinataire" min="1" required><br><br>

    <label>Fichier (PDF, Word, Excel, PowerPoint ou TXT — 10 Mo max.)</label><br>
    <input type="file" name="fichier"
           accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt" required><br><br>

    <button type="submit">Envoyer</button>
</form>

<p><a href="documents.php">Retour aux documents</a></p>
</body>
</html>