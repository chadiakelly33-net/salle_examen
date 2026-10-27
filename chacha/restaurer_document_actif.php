<?php
session_start();

// Vérifier que l'utilisateur est un administrateur
if (
    !isset($_SESSION["id_admin"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: connexion.php");
    exit();
}

require_once("connexionDB.php");

// Autoriser uniquement les requêtes POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: documents_archives.php");
    exit();
}

// Vérifier le jeton de sécurité
$csrf_recu = $_POST["csrf_token"] ?? "";
$csrf_session = $_SESSION["csrf_token"] ?? "";

if (
    $csrf_session === "" ||
    $csrf_recu === "" ||
    !hash_equals($csrf_session, $csrf_recu)
) {
    http_response_code(403);
    exit("Erreur de sécurité. Rechargez la page et réessayez.");
}

// Vérifier l'identifiant
$id_document = filter_input(
    INPUT_POST,
    "id_document",
    FILTER_VALIDATE_INT
);

if (!$id_document || $id_document <= 0) {
    header("Location: documents_archives.php?message=introuvable");
    exit();
}

try {
    $req = $bdd->prepare("
        UPDATE document
        SET archive = 0
        WHERE id_document = ?
          AND archive = 1
    ");

    $req->execute([$id_document]);

    if ($req->rowCount() > 0) {
        header("Location: documents_archives.php?message=restaure");
        exit();
    }

    header("Location: documents_archives.php?message=introuvable");
    exit();

} catch (PDOException $e) {
    error_log("Erreur de restauration : " . $e->getMessage());
    http_response_code(500);
    exit("Une erreur est survenue pendant la restauration du document.");
}

