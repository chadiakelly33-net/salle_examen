<?php
session_start();

require_once __DIR__ . '/connexionDB.php';

// 1. Vérifier la connexion du surveillant
if (
    !isset($_SESSION['id_surv']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'surveillant'
) {
    header('Location: login_surv.php');
    exit;
}

// 2. Vérifier le délai d'inactivité
$timeout = 300;

if (
    isset($_SESSION['last_activity']) &&
    time() - $_SESSION['last_activity'] > $timeout
) {
    session_unset();
    session_destroy();

    header('Location: login_surv.php?timeout=1');
    exit;
}

$_SESSION['last_activity'] = time();

$id_surv = (int) $_SESSION['id_surv'];

// 3. Vérifier l'identifiant du document
$id_document = isset($_GET['id'])
    && ctype_digit((string) $_GET['id'])
    ? (int) $_GET['id']
    : 0;

if ($id_document <= 0) {
    http_response_code(400);
    exit('Identifiant du document invalide.');
}

// 4. Vérifier le partage avec le surveillant
$requete = $bdd->prepare("
    SELECT DISTINCT
        d.id_document,
        d.nom_document,
        d.fichier
    FROM document d
    INNER JOIN partage_document p
        ON p.id_document = d.id_document
    WHERE d.id_document = :id_document
      AND p.type_destinataire = 'surveillant'
      AND p.id_surv = :id_surv
    LIMIT 1
");

$requete->execute([
    'id_document' => $id_document,
    'id_surv' => $id_surv
]);

$document = $requete->fetch(PDO::FETCH_ASSOC);

if (!$document) {
    http_response_code(403);
    exit('Vous n’êtes pas autorisé à télécharger ce document.');
}

// 5. Vérifier que le fichier existe
$nom_fichier = basename($document['fichier']);

$chemin_fichier = __DIR__
    . '/chacha/documents/'
    . $nom_fichier;

if (
    !is_file($chemin_fichier) ||
    !is_readable($chemin_fichier)
) {
    http_response_code(404);
    exit('Le fichier est introuvable sur le serveur.');
}

// 6. Vérifier le format
$extension = strtolower(
    pathinfo($nom_fichier, PATHINFO_EXTENSION)
);

$types_mime = [
    'pdf'  => 'application/pdf',
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls'  => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'ppt'  => 'application/vnd.ms-powerpoint',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation'
];

if (!isset($types_mime[$extension])) {
    http_response_code(415);
    exit('Format de fichier non pris en charge.');
}

// 7. Préparer le téléchargement
while (ob_get_level() > 0) {
    ob_end_clean();
}

$nom_telechargement = basename($document['nom_document']);

// Ajouter l'extension si elle manque
if (
    strtolower(pathinfo($nom_telechargement, PATHINFO_EXTENSION))
    !== $extension
) {
    $nom_telechargement .= '.' . $extension;
}

// Sécuriser le nom du fichier
$nom_telechargement = preg_replace(
    '/[\r\n"\\\\\/]/',
    '_',
    $nom_telechargement
);

header('Content-Type: ' . $types_mime[$extension]);

header(
    'Content-Disposition: attachment; filename="'
    . $nom_telechargement
    . '"; filename*=UTF-8\'\''
    . rawurlencode($nom_telechargement)
);

header('Content-Length: ' . filesize($chemin_fichier));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');

readfile($chemin_fichier);
exit;