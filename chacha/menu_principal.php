<?php
// Démarrage de la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérification de l'accès administrateur
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
    (time() - $_SESSION["last_activity"]) > $timeout
) {
    session_unset();
    session_destroy();

    header("Location: connexion.php?timeout=1");
    exit();
}

// Actualisation de la dernière activité
$_SESSION["last_activity"] = time();
?>



<style>
/* Mise en page générale */
body {
    margin: 0;
    padding-left: 230px;
    font-family: Arial, sans-serif;
    background: #f4f6f9;
}

/* Menu latéral */
.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: 230px;
    height: 100vh;
    background: #003366;
    overflow-y: auto;
    box-sizing: border-box;
    padding: 20px 0;
    z-index: 1000;
}

/* Titre du menu */
.sidebar h2 {
    color: white;
    text-align: center;
    margin: 0 0 25px;
    font-size: 23px;
    letter-spacing: 2px;
}

/* Liens principaux */
.sidebar a {
    display: block;
    color: white;
    text-decoration: none;
    padding: 12px 18px;
    font-size: 14px;
    transition: 0.2s;
    box-sizing: border-box;
}

.sidebar a:hover {
    background: #ffd700;
    color: #003366;
}

/* Rubriques déroulantes */
.sidebar details {
    width: 100%;
}

.sidebar summary {
    display: block;
    color: white;
    padding: 13px 18px;
    font-size: 14px;
    cursor: pointer;
    list-style: none;
    transition: 0.2s;
    box-sizing: border-box;
}

.sidebar summary::-webkit-details-marker {
    display: none;
}

.sidebar summary::after {
    content: "＋";
    float: right;
    font-size: 14px;
}

.sidebar details[open] summary::after {
    content: "−";
}

.sidebar summary:hover {
    background: #ffd700;
    color: #003366;
}

/* Sous-menu */
.sidebar .submenu {
    background: #002244;
}

.sidebar .submenu a {
    padding: 10px 12px 10px 35px;
    font-size: 13px;
}

/* Séparateur */
.sidebar .separator {
    height: 1px;
    background: rgba(255,255,255,0.2);
    margin: 12px 15px;
}

/* Déconnexion */
.sidebar .logout {
    color: #ffd700;
    margin-top: 10px;
}

/* Contenu des pages */
.page-content {
    width: 100%;
    padding: 20px;
    box-sizing: border-box;
}

/* Adaptation aux petits écrans */
@media screen and (max-width: 768px) {
    body {
        padding-left: 190px;
    }

    .sidebar {
        width: 190px;
    }

    .sidebar a,
    .sidebar summary {
        font-size: 13px;
        padding-left: 12px;
        padding-right: 12px;
    }

    .sidebar .submenu a {
        padding-left: 25px;
    }
}
</style>

<aside class="sidebar">

    <h2>MENU</h2>

    <a href="dashboard.php">Dashboard</a>

    <details>
        <summary>Étudiants</summary>
        <div class="submenu">
            <a href="ajouter_etu.php">Ajouter un étudiant</a>
            <a href="afficher_etu.php">Afficher les étudiants</a>
            <a href="rapport_etudiants.php">Rapport des étudiants</a>
        </div>
    </details>

    <details>
        <summary>Cours</summary>
        <div class="submenu">
            <a href="ajouter_cours.php">Ajouter un cours</a>
            <a href="afficher_cours.php">Afficher les cours</a>
        </div>
    </details>

    <details>
        <summary>Facultés</summary>
        <div class="submenu">
            <a href="ajouter_fac.php">Ajouter une faculté</a>
            <a href="afficher_fac.php">Afficher les facultés</a>
        </div>
    </details>

    <details>
        <summary>Salles</summary>
        <div class="submenu">
            <a href="ajouter_salle.php">Ajouter une salle</a>
            <a href="afficher_salle.php">Afficher les salles</a>
        </div>
    </details>

    <details>
        <summary>Surveillants</summary>
        <div class="submenu">
            <a href="ajouter_sur.php">Ajouter un surveillant</a>
            <a href="afficher_sur.php">Afficher les surveillants</a>
            <a href="gerer_disponibilite_surv.php">Disponibilité des surveillants</a>
        </div>
    </details>

    <details>
        <summary>Affectations</summary>
        <div class="submenu">
            <a href="ajouter_affectation.php">Ajouter une affectation</a>
            <a href="afficher_affectation_admin.php">Afficher les affectations</a>
            <a href="surveillants_affectes.php">Surveillants affectés</a>
            <a href="consulter_etudiants_salle.php">Étudiants par salle</a>
            <a href="recherche_affectation.php">Rechercher les affectations</a>
            <a href="rapport_affectations.php">Rapport des affectations</a>
            <a href="tri_affectation.php">Trier les affectations</a>
            <a href="filtrage_affectation.php">Filtrer les affectations</a>
        </div>
    </details>

    <details>
        <summary>Documents</summary>
        <div class="submenu">
            <a href="ajouter_document.php">Ajouter un document</a>
            <a href="afficher_documents.php">Documents partagés</a>
            <a href="envoyer_document.php">Envoyer un document</a>
            <a href="documents_recus.php">Documents reçus</a>
        </div>
    </details>

    <details>
        <summary>Communication</summary>
        <div class="submenu">
            <a href="chat_admin.php">Chat en temps réel</a>
        </div>
    </details>

    <details>
        <summary>Notifications par e-mail</summary>
        <div class="submenu">
            <a href="creer_evenement.php">Créer un événement</a>
            <a href="historique_notifications.php">Historique des notifications</a>
        </div>
    </details>

    <details>
        <summary>Rapports et statistiques</summary>
        <div class="submenu">
            <a href="rapports.php">Rapports PDF</a>
            <a href="rapports_personnalises.php">Rapports personnalisés</a>
            <a href="export_excel.php">Export Excel</a>
            <a href="analyse_comparative.php">Comparaisons</a>
            <a href="previsions_statistiques.php">Prévisions statistiques</a>
        </div>
    </details>

    <details>
        <summary>Historique et sécurité</summary>
        <div class="submenu">
            <a href="admin_connexions.php">Historique des connexions</a>
            <a href="historique_activites.php">Journal des activités</a>
            <a href="afficher_archives.php">Archivage</a>
        </div>
    </details>

    <a href="alertes.php">Alertes automatiques</a>
	<a href="commentaires_admin.php">Gestion des commentaires</a>
	<a href="restaurer_sauvegarde.php">Restauration de sauvegarde</a>
	<a href="parametres.php">Paramétrage du système</a>

    <div class="separator"></div>

    <a href="deconnexion.php" class="logout">Déconnexion</a>

</aside>

<?php require_once __DIR__ . "/../theme.php"; ?>

<script>
document.querySelectorAll(".sidebar details").forEach(function(groupe) {

    groupe.addEventListener("toggle", function() {

        if (groupe.open) {

            document.querySelectorAll(".sidebar details").forEach(function(autre) {

                if (autre !== groupe) {
                    autre.open = false;
                }

            });

        }

    });

});
</script>