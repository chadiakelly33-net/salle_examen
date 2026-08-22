<?php
session_start();
if(!isset($_SESSION["id_admin"]) || $_SESSION["role"] != "admin"){
    header("Location: connexion.php");
    exit();
}

include("connexionDB.php");

if (isset($_POST["ajouter"])) {

    $nom = trim($_POST["nom"]);
    $prenom = trim($_POST["prenom"]);
    $login = trim($_POST["login"]);

    // mot de passe par défaut
    $motdepasse = "1234";
    $hash = password_hash($motdepasse, PASSWORD_DEFAULT);

    // vérifier si login existe déjà
    $check = $bdd->prepare("SELECT id_surv FROM surveillant WHERE login = ?");
    $check->execute([$login]);

    if ($check->rowCount() > 0) {
        echo "<script>
                alert('Ce login existe déjà !');
                window.location.href='ajouter_surv.php';
              </script>";
        exit();
    }

    // insertion
    $insert = $bdd->prepare("
        INSERT INTO surveillant
        (nom_surv, prenom_surv, login, motdepasse, premiere_connexion, tentatives, compte_bloque, date_blocage)
        VALUES (?, ?, ?, ?, 1, 0, 0, NULL)
    ");

    $result = $insert->execute([
        $nom,
        $prenom,
        $login,
        $hash
    ]);

    if ($result) {
        echo "<script>
                alert('Surveillant ajouté avec succès ! Mot de passe par défaut : 1234');
                window.location.href='afficher_sur.php';
              </script>";
    } else {
        echo "<script>
                alert('Erreur lors de l\'ajout');
                window.location.href='afficher_sur.php';
              </script>";
    }

} else {
    header("Location: ajouter_sur.php");
    exit();
}
?>