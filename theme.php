<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/session_timeout.php";

$role = $_SESSION["role"] ?? "";

if ($role === "etudiant") {
    $lienRetour = "espace_etu.php";
} elseif ($role === "surveillant") {
    $lienRetour = "espace_surv.php";
} else {
    $lienRetour = "login_etu.php";
}
?>

<style>
#theme-control {
    position: fixed;
    top: 15px;
    right: 20px;
    z-index: 2000;
    background: white;
    color: #003366;
    padding: 10px;
    border-radius: 6px;
    border: 1px solid #ccc;
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}

#theme-control select,
#theme-control a {
    padding: 8px;
    border-radius: 5px;
}

#theme-control a {
    background: #003366;
    color: white;
    text-decoration: none;
}

body.theme-sombre {
    background: #1e1e1e !important;
    color: #f1f1f1 !important;
}

body.theme-sombre .main,
body.theme-sombre .page-content,
body.theme-sombre .header,
body.theme-sombre .container,
body.theme-sombre form,
body.theme-sombre table {
    background: #2d2d2d !important;
    color: #f1f1f1 !important;
}

body.theme-sombre input,
body.theme-sombre select,
body.theme-sombre textarea {
    background: #3b3b3b !important;
    color: white !important;
}
</style>

<div id="theme-control">
    <label for="choix-theme">Thème :</label>

    <select id="choix-theme">
        <option value="clair">Clair</option>
        <option value="sombre">Sombre</option>
    </select>

    
</div>

<script>
(function () {
    const choix = document.getElementById("choix-theme");

    function appliquerTheme(theme) {
        document.body.classList.toggle(
            "theme-sombre",
            theme === "sombre"
        );

        choix.value = theme;
        localStorage.setItem("theme_salleexamen", theme);
    }

    appliquerTheme(
        localStorage.getItem("theme_salleexamen") || "clair"
    );

    choix.addEventListener("change", function () {
        appliquerTheme(this.value);
    });
})();
</script>