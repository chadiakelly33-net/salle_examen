<?php
session_start();
if(!isset($_SESSION["id_admin"]) || $_SESSION["role"] != "admin"){
    header("Location: connexion.php");
    exit();
}

include("connexionDB.php");

if(!isset($_SESSION["id_surv"])){
    header("Location: login_surv.php");
    exit();
}

if($_SERVER["REQUEST_METHOD"]!="POST"){
    header("Location: changer_mdp_surv.php");
    exit();
}

$id = $_SESSION["id_surv"];

$ancien = $_POST["ancien"];
$nouveau = $_POST["nouveau"];
$confirm = $_POST["confirm"];

if($nouveau != $confirm){

    header("Location: changer_mdp_surv.php?erreur=confirm");
    exit();

}

$req = $bdd->prepare("
SELECT motdepasse
FROM surveillant
WHERE id_surv=?
");

$req->execute([$id]);

$user = $req->fetch();

if(!$user){

    header("Location: login_surv.php");
    exit();

}

if(!password_verify($ancien,$user["motdepasse"])){

    header("Location: espace_surv.php?erreur=ancien");
    exit();

}

$hash = password_hash($nouveau,PASSWORD_DEFAULT);

$update = $bdd->prepare("
UPDATE surveillant
SET motdepasse=?
WHERE id_surv=?
");

$update->execute([
    $hash,
    $id
]);

header("Location: changer_mdp_surv.php?success=1");

exit();

?>