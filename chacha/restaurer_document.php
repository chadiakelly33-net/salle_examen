<?php
session_start();

// 1. Vérifier que l'utilisateur est administrateur
if (
    !isset($_SESSION['id_admin']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin'
) {
    header('Location: connexion.php');
    exit();
}

// 2. Accepter uniquement les requêtes POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: archive_documents.php');
    exit();
}

// 3. Vérifier le jeton de sécurité
$jetonSession = $_SESSION['csrf_token'] ?? '';
$jetonFormulaire = $_POST['csrf_token'] ?? '';

if (
    !is_string($jetonSession) ||
    $jetonSession === '' ||
    !is_string($jetonFormulaire) ||
    $jetonFormulaire === '' ||
    !hash_equals($jetonSession, $jetonFormulaire)
) {
    http_response_code(403);

    exit(
        'Session de sécurité invalide. ' .
        'Retournez aux archives, actualisez la page et réessayez.'
    );
}

// 4. Connexion à la base de données
require_once 'connexionDB.php';

// 5. Vérifier l'identifiant de l'archive
$id_archive = filter_input(
    INPUT_POST,
    'id_archive',
    FILTER_VALIDATE_INT
);

if (!$id_archive || $id_archive <= 0) {
    header('Location: archive_documents.php?restauration=introuvable');
    exit();
}

try {

    $bdd->beginTransaction();

    // 6. Récupérer et verrouiller l'archive
    $req = $bdd->prepare("
        SELECT *
        FROM archive_suppression_document
        WHERE id_archive = ?
        FOR UPDATE
    ");

    $req->execute([$id_archive]);

    $archive = $req->fetch(PDO::FETCH_ASSOC);

    if (!$archive) {
        $bdd->rollBack();

        header('Location: archive_documents.php?restauration=introuvable');
        exit();
    }

    // 7. Vérifier le nom du fichier
    $nomFichier = $archive['fichier'] ?? '';

    if (
        $nomFichier === '' ||
        basename($nomFichier) !== $nomFichier ||
        strpos($nomFichier, '/') !== false ||
        strpos($nomFichier, '\\') !== false
    ) {
        $bdd->rollBack();

        header('Location: archive_documents.php?restauration=fichier_invalide');
        exit();
    }

    // 8. Vérifier que le fichier physique existe
    $cheminFichier = __DIR__
        . DIRECTORY_SEPARATOR
        . 'documents'
        . DIRECTORY_SEPARATOR
        . $nomFichier;

    if (!is_file($cheminFichier)) {
        $bdd->rollBack();

        header('Location: archive_documents.php?restauration=fichier_absent');
        exit();
    }

    // 9. Récupérer les anciens destinataires
    $reqDest = $bdd->prepare("
        SELECT
            type_destinataire,
            id_etu,
            id_surv,
            id_fac,
            id_niveau
        FROM archive_destinataires_document
        WHERE id_archive = ?
    ");

    $reqDest->execute([$id_archive]);

    $destinataires = $reqDest->fetchAll(PDO::FETCH_ASSOC);

    // 10. Réinsérer le document dans la table document
    $ajoutDocument = $bdd->prepare("
        INSERT INTO document
        (
            nom_document,
            fichier,
            description,
            date_ajout,
            id_admin,
            id_surv
        )
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $ajoutDocument->execute([
        $archive['nom_document'],
        $archive['fichier'],
        $archive['description'],
        $archive['date_ajout_original'],
        $archive['id_admin'],
        $archive['id_surv']
    ]);

    // Récupérer le nouvel identifiant du document
    $nouvelIdDocument = (int) $bdd->lastInsertId();

    // 11. Restaurer les anciens destinataires
    $ajoutPartage = $bdd->prepare("
        INSERT INTO partage_document
        (
            id_document,
            type_destinataire,
            id_etu,
            id_surv,
            id_fac,
            id_niveau
        )
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    foreach ($destinataires as $destinataire) {

        $ajoutPartage->execute([
            $nouvelIdDocument,
            $destinataire['type_destinataire'],
            $destinataire['id_etu'],
            $destinataire['id_surv'],
            $destinataire['id_fac'],
            $destinataire['id_niveau']
        ]);
    }

    // 12. Supprimer les destinataires de l'archive
    $supprimerDestinataires = $bdd->prepare("
        DELETE FROM archive_destinataires_document
        WHERE id_archive = ?
    ");

    $supprimerDestinataires->execute([$id_archive]);

    // 13. Supprimer l'archive après restauration réussie
    $supprimerArchive = $bdd->prepare("
        DELETE FROM archive_suppression_document
        WHERE id_archive = ?
    ");

    $supprimerArchive->execute([$id_archive]);

    // 14. Valider toutes les opérations
    $bdd->commit();

    // Préparer un nouveau jeton pour la prochaine opération
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    // 15. Retourner à la liste des archives
    header('Location: archive_documents.php?restauration=reussie');
    exit();

} catch (Throwable $e) {

    if ($bdd->inTransaction()) {
        $bdd->rollBack();
    }

    error_log(
        'Erreur restauration document : ' . $e->getMessage()
    );

    header('Location: archive_documents.php?restauration=erreur');
    exit();
}