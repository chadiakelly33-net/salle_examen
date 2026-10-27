<?php
session_start();

require_once __DIR__ . '/connexionDB.php';

// 1. Vérifier la connexion de l'étudiant
if (
    !isset($_SESSION['id_etu']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'etudiant'
) {
    header('Location: login_etu.php');
    exit;
}

// 2. Vérifier le délai d'inactivité : 5 minutes
$timeout = 300;

if (
    isset($_SESSION['last_activity']) &&
    time() - $_SESSION['last_activity'] > $timeout
) {
    session_unset();
    session_destroy();

    header('Location: login_etu.php?timeout=1');
    exit;
}

$_SESSION['last_activity'] = time();

$id_etu = (int) $_SESSION['id_etu'];

// 3. Récupérer la faculté et le niveau de l'étudiant
$requete = $bdd->prepare("
    SELECT id_fac, id_niveau
    FROM etudiant
    WHERE id_etu = ?
");

$requete->execute([$id_etu]);
$etudiant = $requete->fetch(PDO::FETCH_ASSOC);

if (!$etudiant) {
    http_response_code(403);
    exit('Accès refusé.');
}

// 4. Vérifier l'identifiant du document
$id_document = isset($_GET['id'])
    && ctype_digit((string) $_GET['id'])
    ? (int) $_GET['id']
    : 0;

if ($id_document <= 0) {
    http_response_code(400);
    exit('Identifiant du document invalide.');
}

// 5. Récupérer le document uniquement s'il est actif
// et partagé avec l'étudiant, sa faculté ou son niveau
$requete = $bdd->prepare("
    SELECT DISTINCT
        d.id_document,
        d.nom_document,
        d.fichier,
        d.description
    FROM document d
    INNER JOIN partage_document p
        ON p.id_document = d.id_document
    WHERE d.id_document = :id_document
      AND d.archive = 0
      AND p.type_destinataire = 'etudiant'
      AND (
          p.id_etu = :id_etu
          OR (
              p.id_fac IS NOT NULL
              AND p.id_fac = :id_fac
          )
          OR (
              p.id_niveau IS NOT NULL
              AND p.id_niveau = :id_niveau
          )
      )
    LIMIT 1
");

$requete->execute([
    'id_document' => $id_document,
    'id_etu' => $id_etu,
    'id_fac' => $etudiant['id_fac'],
    'id_niveau' => $etudiant['id_niveau']
]);

$document = $requete->fetch(PDO::FETCH_ASSOC);

if (!$document) {
    http_response_code(403);
    exit('Ce document est indisponible ou vous n’êtes pas autorisé à le consulter.');
}

// 6. Vérifier le fichier enregistré sur le serveur
$nom_fichier = basename($document['fichier']);

$chemin_fichier = __DIR__
    . '/chacha/documents/'
    . $nom_fichier;

if (
    !is_file($chemin_fichier) ||
    !is_readable($chemin_fichier)
) {
    http_response_code(404);
    exit('Le fichier est introuvable.');
}

// 7. Déterminer le type du fichier
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
    exit('Ce format de document n’est pas pris en charge.');
}

// 8. Afficher le document sans forcer son téléchargement
while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: ' . $types_mime[$extension]);
header(
    'Content-Disposition: inline; filename="' .
    str_replace('"', '', $nom_fichier) .
    '"'
);
header('Content-Length: ' . filesize($chemin_fichier));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');

readfile($chemin_fichier);
exit;

