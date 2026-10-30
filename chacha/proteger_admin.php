<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (
    !isset($_SESSION["id_admin"]) ||
    ($_SESSION["role"] ?? "") !== "admin"
) {
    header("Location: connexion.php");
    exit();
}

// Déconnexion après 5 minutes d'inactivité
$timeout = 300;

if (
    isset($_SESSION["last_activity"]) &&
    time() - $_SESSION["last_activity"] > $timeout
) {
    session_unset();
    session_destroy();

    header("Location: connexion.php?timeout=1");
    exit();
}

$_SESSION["last_activity"] = time();