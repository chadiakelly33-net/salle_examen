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

include("connexionDB.php");

if (isset($_POST["ajouter"])) {

    // Récupérer les données
    $nom = trim($_POST["nom"] ?? "");
    $prenom = trim($_POST["prenom"] ?? "");
    $login = trim($_POST["login"] ?? "");
    $email = trim($_POST["email"] ?? "");

    // Vérifier les champs
    if ($nom === "" || $prenom === "" || $login === "" || $email === "") {
        echo "<script>
            alert('Veuillez remplir tous les champs obligatoires.');
            window.location.href='ajouter_sur.php';
        </script>";
        exit();
    }

    // Vérifier le format de l'adresse e-mail
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "<script>
            alert('Veuillez saisir une adresse e-mail valide.');
            window.location.href='ajouter_sur.php';
        </script>";
        exit();
    }

    // Vérifier si le login existe déjà
    $check = $bdd->prepare("
        SELECT id_surv
        FROM surveillant
        WHERE login = ?
    ");
    $check->execute([$login]);

    if ($check->fetch()) {
        echo "<script>
            alert('Ce login existe déjà !');
            window.location.href='ajouter_sur.php';
        </script>";
        exit();
    }

    // Vérifier si l'e-mail est déjà utilisé
    $checkEmail = $bdd->prepare("
        SELECT id_surv
        FROM surveillant
        WHERE email = ?
    ");
    $checkEmail->execute([$email]);

    if ($checkEmail->fetch()) {
        echo "<script>
            alert('Cette adresse e-mail est déjà utilisée par un surveillant.');
            window.location.href='ajouter_sur.php';
        </script>";
        exit();
    }

    // Mot de passe initial
    $motdepasse = "1234";
    $hash = password_hash($motdepasse, PASSWORD_DEFAULT);

    // Insérer le surveillant avec son adresse e-mail
    $insert = $bdd->prepare("
        INSERT INTO surveillant (
            nom_surv,
            prenom_surv,
            login,
            email,
            motdepasse,
            premiere_connexion,
            tentatives,
            compte_bloque,
            date_blocage
        )
        VALUES (?, ?, ?, ?, ?, 1, 0, 0, NULL)
    ");

    $result = $insert->execute([
        $nom,
        $prenom,
        $login,
        $email,
        $hash
    ]);

    if ($result) {
       
header("Location: afficher_sur.php?ajout=success");
exit();


        exit();
    }

    echo "<script>
        alert('Erreur lors de l’ajout du surveillant.');
        window.location.href='ajouter_sur.php';
    </script>";
    exit();

} else {
    header("Location: ajouter_sur.php");
    exit();
}
?>
