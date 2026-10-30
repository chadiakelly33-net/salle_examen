<?php
session_start();
require_once "connexionDB.php";

/* 1. Vérifier la session */
$role = $_SESSION['role'] ?? '';

if ($role === 'admin') {
    $id = 0;
} elseif ($role === 'etudiant' && isset($_SESSION['id_etu'])) {
    $id = (int) $_SESSION['id_etu'];
} elseif ($role === 'surveillant' && isset($_SESSION['id_surv'])) {
    $id = (int) $_SESSION['id_surv'];
} else {
    http_response_code(403);
    exit("Accès refusé. Veuillez vous connecter.");
}

/* 2. Vérifier l'identifiant du document */
$idDocument = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$idDocument || $idDocument < 1) {
    http_response_code(400);
    exit("Identifiant du document invalide.");
}

/* 3. Vérifier que le document est accessible à cet utilisateur */
if ($role === 'admin') {
    $sql = "SELECT d.*
            FROM document d
            WHERE d.id_document = ?
              AND d.archive = 0";
    $stmt = $bdd->prepare($sql);
    $stmt->execute([$idDocument]);
} else {
    $sql = "SELECT d.*
            FROM document d
            INNER JOIN partage_document p
                ON p.id_document = d.id_document
            WHERE d.id_document = ?
              AND d.archive = 0
              AND (
                  (p.type_destinataire = 'etudiant'
                   AND p.id_etu = ?
                   AND ? = 'etudiant')
                  OR
                  (p.type_destinataire = 'surveillant'
                   AND p.id_surv = ?
                   AND ? = 'surveillant')
              )";

    $stmt = $bdd->prepare($sql);
    $stmt->execute([
        $idDocument,
        $id,
        $role,
        $id,
        $role
    ]);
}

$document = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$document) {
    http_response_code(403);
    exit("Vous n'êtes pas autorisé à télécharger ce document.");
}

/* 4. Vérifier le fichier stocké */
$nomStockage = basename($document['fichier']);
$chemin = __DIR__ . '/documents_prives/' . $nomStockage;

if (!is_file($chemin)) {
    http_response_code(404);
    exit("Fichier introuvable sur le serveur.");
}

/* 5. Télécharger le document */
$nomTelechargement = basename($document['nom_document']);
$nomTelechargement = preg_replace(
    '/[\r\n"\\\\]/',
    '_',
    $nomTelechargement
);

header('Content-Type: application/octet-stream');
header(
    "Content-Disposition: attachment; filename*=UTF-8''" .
    rawurlencode($nomTelechargement)
);
header('Content-Length: ' . filesize($chemin));
header('X-Content-Type-Options: nosniff');

readfile($chemin);
exit;