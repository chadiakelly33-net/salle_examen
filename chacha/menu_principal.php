<?php
/*session_start();*/
if(!isset($_SESSION["id_admin"]) || $_SESSION["role"] != "admin"){
    header("Location: connexion.php");
    exit();
}

include("connexionDB.php");

$timeout = 300; // 5 minutes


if(isset($_SESSION["last_activity"])){

    if(time() - $_SESSION["last_activity"] > $timeout){

        session_unset();
        session_destroy();

        header("Location: connexion.php?timeout=1");
        exit();

    }

}


$_SESSION["last_activity"] = time();

?>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Menu Sidebar</title>

<style>

/* RESET */
body{
    margin:0;
    font-family:Arial;
    background:#f4f6f9;
	padding-left:230px;
}

/* SIDEBAR */
.sidebar{
    position:fixed;
    top:0;
    left:0;
    width:230px;
    height:100%;
    background:#003366;
    padding-top:20px;
    overflow-y:auto;
}

/* TITRE MENU */
.sidebar h2{
    color:white;
    text-align:center;
    margin-bottom:20px;
}

/* LIENS PRINCIPAUX */
.sidebar a{
    display:block;
    color:white;
    text-decoration:none;
    padding:12px 18px;
    font-size:15px;
    transition:0.3s;
}

/* HOVER */
.sidebar a:hover{
    background:#ffd700;
    color:#003366;
}

/* DROPDOWN */
.dropdown{
    position:relative;
}

/* SOUS MENU */
.dropdown-content{
    display:none;
    background:#002244;
}

.dropdown-content a{
    padding-left:35px;
    font-size:14px;
}

/* AFFICHAGE AU SURVOL */
.dropdown:hover .dropdown-content{
    display:block;
}

/* CONTENU PAGE */
.content{
    margin-left:230px;
    padding:20px;
    width:calc(100% - 230px);
    box-sizing:border-box;
}

/* TITRE */
.title{
    text-align:center;
    color:#003366;
    margin-top:20px;
    font-size:28px;
}

/* TABLEAUX */
table{
    width:100%;
    border-collapse:collapse;
    background:white;
}

th,td{
    border:1px solid #ddd;
    padding:10px;
    text-align:center;
}

th{
    background:#003366;
    color:white;
}

</style>
</head>

<body>

<div class="sidebar">

    <h2>MENU</h2>

    <a href="dashboard.php">Dashboard</a>

    <div class="dropdown">
        <a href="#">Étudiants</a>
        <div class="dropdown-content">
            <a href="ajouter_etu.php">Ajouter</a>
            <a href="afficher_etu.php">Afficher</a>
			<a href="rapport_etudiants.php">Rapport</a>
        </div>
    </div>

    <div class="dropdown">
        <a href="#">Cours</a>
        <div class="dropdown-content">
            <a href="ajouter_cours.php">Ajouter</a>
            <a href="afficher_cours.php">Afficher</a>
        </div>
    </div>

    <div class="dropdown">
        <a href="#">Facultés</a>
        <div class="dropdown-content">
            <a href="ajouter_fac.php">Ajouter</a>
            <a href="afficher_fac.php">Afficher</a>
        </div>
    </div>

    <div class="dropdown">
        <a href="#">Salles</a>
        <div class="dropdown-content">
            <a href="ajouter_salle.php">Ajouter</a>
            <a href="afficher_salle.php">Afficher</a>
        </div>
    </div>

    <div class="dropdown">
        <a href="#">Surveillants</a>
        <div class="dropdown-content">
            <a href="ajouter_sur.php">Ajouter</a>
            <a href="afficher_sur.php">Afficher</a>
        </div>
    </div>

    <div class="dropdown">
        <a href="#">Affectations</a>
        <div class="dropdown-content">
            <a href="ajouter_affectation.php">Affectations</a>
            <a href="afficher_affectation_admin.php">Affichage des affectations</a>
			<a href="gerer_disponibilite_surv.php">Disponibilite des surveillants</a>
			<a href="surveillants_affectes.php">Liste des surveillants affectes</a>
			<a href="consulter_etudiants_salle.php">Etudiants par salle</a>
			<a href="recherche_affectation.php">Rechercher les affectations</a>
			<a href="rapport_affectations.php">Rapport d'affectation</a>
        </div>
    </div>
	<div class="dropdown">
		<a href="#">Documents</a>
		<div class="dropdown-content">
			<a href="ajouter_document.php">Partager un document</a>
			<a href="afficher_documents.php">Liste des documents partages</a>
		</div>
	</div>
	<a href="admin_connexions.php">Historique de connexion</a>
	
	<a href="tri_affectation.php">Trie des affectations</a>
	<a href="filtrage_affectation.php">Filtrer les affectations</a>
	<a href="historique_activites.php">Journal des activités</a>
	<a href="afficher_archives.php">Archivage</a>
	<a href="deconnexion.php">Deconnexion</a>

</div>

</body>
</html>