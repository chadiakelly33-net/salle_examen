<?php
session_start();

if (
    !isset($_SESSION["id_admin"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: connexion.php");
    exit();
}

require_once("connexionDB.php");
require_once("journal.php");

if (!isset($_GET["id"]) || !ctype_digit($_GET["id"])) {
    header("Location: afficher_sur.php");
    exit();
}

$id_surv = (int) $_GET["id"];

try {
    // Vérifier que le surveillant existe
    $req = $bdd->prepare(
        "SELECT id_surv FROM surveillant WHERE id_surv = ?"
    );
    $req->execute([$id_surv]);

    if (!$req->fetch()) {
        header("Location: afficher_sur.php?erreur=introuvable");
        exit();
    }

    // Supprimer le surveillant
    $req = $bdd->prepare(
        "DELETE FROM surveillant WHERE id_surv = ?"
    );
    $req->execute([$id_surv]);

    // Enregistrer l'action dans le journal
    journal(
        $bdd,
        $_SESSION["id_admin"],
        "Administrateur",
        "Suppression d'un surveillant"
    );

    // Retourner à la liste
    header("Location: afficher_sur.php?suppression=success");
    exit();

} catch (PDOException $e) {
    error_log("Erreur suppression surveillant : " . $e->getMessage());

    header("Location: afficher_sur.php?erreur=suppression");
    exit();
}
?>

