<?php
session_start();
require_once "connexionDB.php";

$role = $_SESSION['role'] ?? '';

if ($role === 'admin') {
    $id = 0;
} elseif ($role === 'etudiant' && isset($_SESSION['id_etu'])) {
    $id = (int) $_SESSION['id_etu'];
} elseif ($role === 'surveillant' && isset($_SESSION['id_surv'])) {
    $id = (int) $_SESSION['id_surv'];
} else {
    http_response_code(403);
    exit("Accès refusé.");
}

$idDoc = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$idDoc || $idDoc < 1) {
    http_response_code(400);
    exit("Document invalide.");
}

/* Vérifier que l'utilisateur a reçu ou envoyé ce document */
$sql = "SELECT d.*
        FROM document d
        WHERE d.id_document = ?
          AND d.archive = 0
          AND (
              d.id_admin = ?
              OR EXISTS (
                  SELECT 1
                  FROM partage_document p
                  WHERE p.id_document = d.id_document
                    AND (
                        (? = 'etudiant' AND p.type_destinataire = 'etudiant'
                         AND p.id_etu = ?)
                        OR
                        (? = 'surveillant' AND p.type_destinataire = 'surveillant'
                         AND p.id_surv = ?)
                    )
              )
          )";

$stmt = $bdd->prepare($sql);
$stmt->execute([
    $idDoc,
    $role === 'admin' ? 0 : -1,
    $role, $id,
    $role, $id
]);

$doc = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$doc) {
    http_response_code(403);
    exit("Vous n'êtes pas autorisé à télécharger ce document.");
}

$nomStockage = basename($doc['fichier']);
$chemin = __DIR__ . '/documents_prives/' . $nomStockage;

if (!is_file($chemin)) {
    http_response_code(404);
    exit("Fichier introuvable. Vérifiez le dossier documents_prives.");
}

$nom = basename($doc['nom_document']);
$nom = str_replace(["\r", "\n", '"'], '', $nom);

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $nom . '"');
header('X-Content-Type-Options: nosniff');
header('Content-Length: ' . filesize($chemin));

readfile($chemin);
exit;