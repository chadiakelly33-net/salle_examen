<?php
session_start();
require_once "connexionDB.php";

if (!isset($_SESSION['role'])) {
    header("Location: login_etu.php");
    exit();
}

$role = $_SESSION['role'];

if ($role === 'admin') {
    $id = 0;
} elseif ($role === 'etudiant' && isset($_SESSION['id_etu'])) {
    $id = (int) $_SESSION['id_etu'];
} elseif ($role === 'surveillant' && isset($_SESSION['id_surv'])) {
    $id = (int) $_SESSION['id_surv'];
} else {
    exit("Session invalide.");
}

$message = $_SESSION['message_document'] ?? '';
unset($_SESSION['message_document']);

$sql = "SELECT DISTINCT d.*
        FROM document d
        LEFT JOIN partage_document p ON p.id_document = d.id_document
        WHERE d.archive = 0 AND (
            d.id_admin = :admin
            OR (p.type_destinataire = 'etudiant'
                AND p.id_etu = :etu)
            OR (p.type_destinataire = 'surveillant'
                AND p.id_surv = :surv)
        )
        ORDER BY d.date_ajout DESC";

$stmt = $bdd->prepare($sql);
$stmt->execute([
    'admin' => $role === 'admin' ? $id : -1,
    'etu' => $role === 'etudiant' ? $id : -1,
    'surv' => $role === 'surveillant' ? $id : -1
]);

$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Documents</title>
<style>
body{font-family:Arial;background:#f4f6f9;padding:20px}
main{max-width:900px;margin:auto;background:white;padding:20px;border-radius:8px}
h2{color:#003366}
table{width:100%;border-collapse:collapse}
th,td{padding:10px;border:1px solid #ddd;text-align:left}
th{background:#003366;color:white}
a{color:#003366}
.message{color:green}
</style>
</head>
<body>
<main>
<h2>Gestion des documents</h2>

<?php if ($message): ?>
<p class="message"><?= htmlspecialchars($message) ?></p>
<?php endif; ?>

<?php if ($role === 'admin'): ?>
<p><a href="envoyer_document.php">Envoyer un document</a></p>
<?php endif; ?>

<table>
<thead>
<tr>
<th>Document</th>
<th>Description</th>
<th>Date</th>
<th>Action</th>
</tr>
</thead>
<tbody>
<?php foreach ($documents as $doc): ?>
<tr>
<td><?= htmlspecialchars($doc['nom_document']) ?></td>
<td><?= htmlspecialchars($doc['description'] ?? '') ?></td>
<td><?= htmlspecialchars($doc['date_ajout'] ?? '') ?></td>
<td>
<a href="telecharger_document.php?id=<?= (int)$doc['id_document'] ?>">
Télécharger
</a>
</td>
</tr>
<?php endforeach; ?>

<?php if (!$documents): ?>
<tr><td colspan="4">Aucun document disponible.</td></tr>
<?php endif; ?>
</tbody>
</table>
</main>
</body>
</html>