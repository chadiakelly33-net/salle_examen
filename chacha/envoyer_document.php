<?php
session_start();
require_once "../connexionDB.php";

if (($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: connexion.php");
    exit();
}

$message = "";
$erreur = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom_document'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $type = $_POST['type_destinataire'] ?? '';
    $destinataires = $_POST['destinataires'] ?? [];

    if ($nom === '' || !in_array($type, ['etudiant', 'surveillant'], true)) {
        $erreur = "Veuillez remplir les informations correctement.";
    } elseif (!is_array($destinataires) || empty($destinataires)) {
        $erreur = "Sélectionnez au moins un destinataire.";
    } elseif (!isset($_FILES['fichier']) ||
              $_FILES['fichier']['error'] !== UPLOAD_ERR_OK) {
        $erreur = "Veuillez sélectionner un fichier valide.";
    } elseif ($_FILES['fichier']['size'] > 10 * 1024 * 1024) {
        $erreur = "Le fichier ne doit pas dépasser 10 Mo.";
    } else {
        $extension = strtolower(
            pathinfo($_FILES['fichier']['name'], PATHINFO_EXTENSION)
        );

        $extensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt'];

        if (!in_array($extension, $extensions, true)) {
            $erreur = "Format non autorisé.";
        } else {
            $ids = [];

            foreach ($destinataires as $dest) {
                $idDest = filter_var($dest, FILTER_VALIDATE_INT);

                if ($idDest === false || $idDest < 1) {
                    $erreur = "Un destinataire est invalide.";
                    break;
                }

                $ids[] = $idDest;
            }

            $ids = array_values(array_unique($ids));

            if (!$erreur) {
                // Vérifier que les destinataires existent réellement.
                $table = $type === 'etudiant' ? 'etudiant' : 'surveillant';
                $colonne = $type === 'etudiant' ? 'id_etu' : 'id_surv';
                $placeholders = implode(',', array_fill(0, count($ids), '?'));

                $check = $bdd->prepare(
                    "SELECT `$colonne` FROM `$table`
                     WHERE `$colonne` IN ($placeholders)"
                );
                $check->execute($ids);

                $idsExistants = array_map(
                    'intval',
                    $check->fetchAll(PDO::FETCH_COLUMN)
                );

                if (count($idsExistants) !== count($ids)) {
                    $erreur = "Un ou plusieurs destinataires n'existent pas.";
                } else {
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

                    if (!isset($mimes[$extension]) || $mime !== $mimes[$extension]) {
                        $erreur = "Le contenu du fichier ne correspond pas à son extension.";
                    } else {
                        $dossier = dirname(__DIR__) . '/documents_prives/';

                        if (!is_dir($dossier)) {
                            mkdir($dossier, 0700, true);
                        }

                        $nomStockage = bin2hex(random_bytes(20)) . '.' . $extension;
                        $chemin = $dossier . $nomStockage;

                        if (move_uploaded_file($_FILES['fichier']['tmp_name'], $chemin)) {
                            try {
                                $bdd->beginTransaction();

                                $stmt = $bdd->prepare(
                                    "INSERT INTO document
                                    (nom_document, fichier, description, id_admin, archive)
                                    VALUES (?, ?, ?, 0, 0)"
                                );
                                $stmt->execute([$nom, $nomStockage, $description]);

                                $idDocument = (int)$bdd->lastInsertId();

                                $stmtPartage = $bdd->prepare(
                                    "INSERT INTO partage_document
                                    (id_document, type_destinataire, id_etu, id_surv)
                                    VALUES (?, ?, ?, ?)"
                                );

                                foreach ($ids as $idDest) {
                                    $stmtPartage->execute([
                                        $idDocument,
                                        $type,
                                        $type === 'etudiant' ? $idDest : null,
                                        $type === 'surveillant' ? $idDest : null
                                    ]);
                                }

                                $bdd->commit();

                                header("Location: documents_recus.php?partage=ok");
                                exit();
                            } catch (Throwable $e) {
                                if ($bdd->inTransaction()) {
                                    $bdd->rollBack();
                                }

                                if (is_file($chemin)) {
                                    unlink($chemin);
                                }

                                $erreur = "Erreur lors de l'enregistrement du partage.";
                            }
                        } else {
                            $erreur = "Impossible de stocker le fichier.";
                        }
                    }
                }
            }
        }
    }
}

// Charger les destinataires pour les afficher dans le formulaire.
$etudiants = $bdd->query(
    "SELECT id_etu, nom_etu, prenom_etu FROM etudiant ORDER BY nom_etu"
)->fetchAll(PDO::FETCH_ASSOC);

$surveillants = $bdd->query(
    "SELECT id_surv, nom_surv, prenom_surv FROM surveillant ORDER BY nom_surv"
)->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Partager un document</title>

<style>
*{box-sizing:border-box}
body{margin:0;background:#f3f6fa;font-family:Arial,sans-serif;color:#263445}
.container{max-width:900px;margin:35px auto;padding:0 20px}
.header{background:#003366;color:white;padding:25px;border-radius:10px;margin-bottom:22px}
.header h1{margin:0 0 8px;font-size:25px}
.header p{margin:0;color:#dce8f5;font-size:14px}
.card{background:white;padding:25px;border-radius:10px;box-shadow:0 3px 12px #0000000a}
label{display:block;font-weight:bold;margin:16px 0 7px}
input[type=text],select,textarea{width:100%;padding:12px;border:1px solid #ccd5e0;border-radius:6px;font-size:14px}
select[multiple]{height:180px}
textarea{resize:vertical;min-height:90px}
input[type=file]{width:100%;padding:12px;background:#f7f9fc;border:1px solid #ccd5e0;border-radius:6px}
button,.btn{display:inline-block;padding:12px 18px;border:0;border-radius:6px;background:#003366;color:white;text-decoration:none;font-weight:bold;cursor:pointer}
button:hover,.btn:hover{background:#004b8d}
.btn-retour{background:#e3eaf2;color:#003366}
.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:22px}
.erreur{background:#fdecec;color:#a11;padding:12px;border-radius:6px}
.info{color:#64748b;font-size:13px}
</style>
</head>

<body>
<div class="container">

    <div class="header">
        <h1>Partager un document</h1>
        <p>Envoyer des documents officiels aux étudiants ou aux surveillants.</p>
    </div>

    <div class="card">

        <?php if ($erreur): ?>
            <p class="erreur"><?= htmlspecialchars($erreur) ?></p>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">

            <label>Nom du document</label>
            <input type="text" name="nom_document" required
                   value="<?= htmlspecialchars($_POST['nom_document'] ?? '') ?>">

            <label>Description</label>
            <textarea name="description"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>

            <label>Type de destinataire</label>
            <select name="type_destinataire" id="type_destinataire" required>
                <option value="etudiant">Étudiants</option>
                <option value="surveillant">Surveillants</option>
            </select>

            <label>Destinataires</label>
            <select name="destinataires[]" id="destinataires" multiple required>
                <?php foreach ($etudiants as $e): ?>
                    <option data-type="etudiant" value="<?= (int)$e['id_etu'] ?>">
                        <?= htmlspecialchars($e['nom_etu'] . ' ' . $e['prenom_etu']) ?>
                        (ID <?= (int)$e['id_etu'] ?>)
                    </option>
                <?php endforeach; ?>

                <?php foreach ($surveillants as $s): ?>
                    <option data-type="surveillant" value="<?= (int)$s['id_surv'] ?>" hidden>
                        <?= htmlspecialchars($s['nom_surv'] . ' ' . $s['prenom_surv']) ?>
                        (ID <?= (int)$s['id_surv'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>

            <p class="info">Sur ordinateur, maintiens Ctrl pour sélectionner plusieurs destinataires.</p>

            <label>Choisir le document (10 Mo maximum)</label>
            <input type="file" name="fichier"
                   accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt" required>

            <div class="actions">
                <button type="submit">Partager le document</button>
                <a href="dashboard.php" class="btn btn-retour">Retour au tableau de bord</a>
            </div>

        </form>
    </div>
</div>

<script>
const typeSelect = document.getElementById('type_destinataire');
const destinataires = document.getElementById('destinataires');

function filtrerDestinataires() {
    const type = typeSelect.value;

    Array.from(destinataires.options).forEach(option => {
        const visible = option.dataset.type === type;
        option.hidden = !visible;
        option.selected = false;
    });
}

typeSelect.addEventListener('change', filtrerDestinataires);
filtrerDestinataires();
</script>
</body>
</html>