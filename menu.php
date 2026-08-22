<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Gestion des Salles d'Examen</title>

<style>

body{
    margin:0;
    font-family:Arial, sans-serif;
}

/* Barre de menu */
header{
    background:#003366;
}

.menu{
    list-style:none;
    margin:0;
    padding:0;
    display:flex;
    justify-content:center;
}

.menu li{
    position:relative;
}

.menu li a{
    display:block;
    color:white;
    text-decoration:none;
    padding:15px 25px;
    font-weight:bold;
}

.menu li a:hover{
    background:#ffd700;
    color:#003366;
}

/* Sous-menu déroulant */
.sous-menu{
    display:none;
    position:absolute;
    top:100%;
    left:0;
    background:#004080;
    min-width:220px;
    list-style:none;
    padding:0;
    margin:0;
}

.sous-menu li a{
    padding:12px;
    border-bottom:1px solid rgba(255,255,255,0.2);
}

.menu li:hover .sous-menu{
    display:block;
}

.titre{
    text-align:center;
    background:#003366;
    color:white;
    padding:20px;
    margin:0;
}

</style>
</head>

<body>

<h1 class="titre">Système de Gestion des Salles d'Examen</h1>

<header>
    <ul class="menu">

        <li><a href="accueil.php">Accueil</a></li>

        <li>
            <a href="#">Étudiants</a>
            <ul class="sous-menu">
                <!--<li><a href="ajouter_etu.php">Ajouter un etudiant</a></li>-->
                <li><a href="afficher_etu.php">Afficher la liste des etudiants</a></li>
            </ul>
        </li>

        <li>
            <a href="#">Cours</a>
            <ul class="sous-menu">
                <!--<li><a href="ajouter_cours.php">Ajouter les cours</a></li>-->
                <li><a href="afficher_cours.php">Afficher les cours</a></li>
            </ul>
        </li>

        <li>
            <a href="#">Facultés</a>
            <ul class="sous-menu">
                <!--<li><a href="ajouter_fac.php">Ajouter une faculte</a></li>-->
                <li><a href="afficher_fac.php">Afficher les facultes</a></li>
            </ul>
        </li>

        <li>
            <a href="#">Surveillants</a>
            <ul class="sous-menu">
                <!--<li><a href="ajouter_sur.php">Ajouter des surveillants</a></li>-->
                <li><a href="afficher_sur.php">Afficher la liste des surveillants</a></li>
            </ul>
        </li>

        <li>
            <a href="#">Salles</a>
            <ul class="sous-menu">
                <!--<li><a href="ajouter_salle.php">Ajouter des salles</a></li>-->
                <li><a href="afficher_salle.php">Afficher les salles</a></li>
            </ul>
        </li>

    </ul>
</header>

</body>
</html>