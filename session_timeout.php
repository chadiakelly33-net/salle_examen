<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/connexionDB.php";

$stmt = $bdd->query("SELECT duree_session FROM parametres LIMIT 1");
$param = $stmt->fetch(PDO::FETCH_ASSOC);

$timeout = (int)($param["duree_session"] ?? 300);

if ($timeout < 60) {
    $timeout = 300;
}

if (
    isset($_SESSION["last_activity"]) &&
    time() - $_SESSION["last_activity"] > $timeout
) {
    $role = $_SESSION["role"] ?? "";

    session_unset();
    session_destroy();

    switch ($role) {
        case "admin":
            header("Location: connexion.php?timeout=1");
            break;

        case "etudiant":
            header("Location: login_etu.php?timeout=1");
            break;

        case "surveillant":
            header("Location: login_surv.php?timeout=1");
            break;

        default:
            header("Location: connexion.php?timeout=1");
    }

    exit();
}

$_SESSION["last_activity"] = time();