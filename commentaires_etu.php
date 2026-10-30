<?php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/* Protection de l'accès */
if (
    !isset($_SESSION["id_etu"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "etudiant"
) {
    header("Location: login_etu.php");
    exit();
}

require_once __DIR__ . "/connexionDB.php";

$id_etu = (int) $_SESSION["id_etu"];

/* Protection CSRF */
if (empty($_SESSION["csrf_commentaires"])) {
    $_SESSION["csrf_commentaires"] = bin2hex(random_bytes(32));
}

/* Informations de l'étudiant */
$reqEtu = $bdd->prepare("
    SELECT nom_etu, prenom_etu
    FROM etudiant
    WHERE id_etu = ?
");

$reqEtu->execute([$id_etu]);
$etu = $reqEtu->fetch(PDO::FETCH_ASSOC);

if (!$etu) {
    session_unset();
    session_destroy();

    header("Location: login_etu.php");
    exit();
}

$nomAuteur = $etu["prenom_etu"] . " " . $etu["nom_etu"];

/* Traitement des formulaires */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals(
            $_SESSION["csrf_commentaires"],
            $_POST["csrf_token"]
        )
    ) {
        die("Requête invalide. Veuillez actualiser la page.");
    }

    $action = $_POST["action"] ?? "";

    /* 1. Publication d'un commentaire */
    if ($action === "commenter") {

        $commentaire = trim($_POST["commentaire"] ?? "");

        if ($commentaire !== "" && mb_strlen($commentaire) <= 2000) {

            $req = $bdd->prepare("
                INSERT INTO commentaires
                (auteur_id, nom_auteur, type_espace, commentaire, parent_id)
                VALUES (?, ?, 'etudiant', ?, NULL)
            ");

            $req->execute([
                $id_etu,
                $nomAuteur,
                $commentaire
            ]);
        }
    }

    /* 2. Répondre à un commentaire */
    elseif ($action === "repondre") {

        $reponse = trim($_POST["reponse"] ?? "");
        $parentId = (int) ($_POST["parent_id"] ?? 0);

        if ($reponse !== "" && mb_strlen($reponse) <= 2000 && $parentId > 0) {

            // Vérifier que le commentaire parent appartient à l'espace étudiant
            $reqParent = $bdd->prepare("
                SELECT id_commentaire
                FROM commentaires
                WHERE id_commentaire = ?
                  AND type_espace = 'etudiant'
                  AND parent_id IS NULL
            ");

            $reqParent->execute([$parentId]);

            if ($reqParent->fetch()) {

                $req = $bdd->prepare("
                    INSERT INTO commentaires
                    (auteur_id, nom_auteur, type_espace, commentaire, parent_id)
                    VALUES (?, ?, 'etudiant', ?, ?)
                ");

                $req->execute([
                    $id_etu,
                    $nomAuteur,
                    $reponse,
                    $parentId
                ]);
            }
        }
    }

    /* 3. Like ou Dislike */
    elseif ($action === "reaction") {

        $commentaireId = (int) ($_POST["commentaire_id"] ?? 0);
        $typeReaction = $_POST["reaction"] ?? "";

        if (
            $commentaireId > 0 &&
            in_array($typeReaction, ["like", "dislike"], true)
        ) {

            // Vérifier que le commentaire appartient à l'espace étudiant
            $reqVerif = $bdd->prepare("
                SELECT id_commentaire
                FROM commentaires
                WHERE id_commentaire = ?
                  AND type_espace = 'etudiant'
            ");

            $reqVerif->execute([$commentaireId]);

            if ($reqVerif->fetch()) {

                // Vérifier si l'étudiant a déjà réagi
                $reqExist = $bdd->prepare("
                    SELECT id_reaction, reaction
                    FROM commentaire_reactions
                    WHERE commentaire_id = ?
                      AND utilisateur_id = ?
                      AND type_espace = 'etudiant'
                ");

                $reqExist->execute([
                    $commentaireId,
                    $id_etu
                ]);

                $reactionExistante = $reqExist->fetch(PDO::FETCH_ASSOC);

                if ($reactionExistante) {

                    if ($reactionExistante["reaction"] === $typeReaction) {

                        // Deuxième clic : retirer la réaction
                        $reqDelete = $bdd->prepare("
                            DELETE FROM commentaire_reactions
                            WHERE id_reaction = ?
                        ");

                        $reqDelete->execute([
                            $reactionExistante["id_reaction"]
                        ]);

                    } else {

                        // Changer Like en Dislike ou inversement
                        $reqUpdate = $bdd->prepare("
                            UPDATE commentaire_reactions
                            SET reaction = ?
                            WHERE id_reaction = ?
                        ");

                        $reqUpdate->execute([
                            $typeReaction,
                            $reactionExistante["id_reaction"]
                        ]);
                    }

                } else {

                    // Première réaction
                    $reqInsert = $bdd->prepare("
                        INSERT INTO commentaire_reactions
                        (commentaire_id, utilisateur_id, type_espace, reaction)
                        VALUES (?, ?, 'etudiant', ?)
                    ");

                    $reqInsert->execute([
                        $commentaireId,
                        $id_etu,
                        $typeReaction
                    ]);
                }
            }
        }
    }

    header("Location: commentaires_etu.php");
    exit();
}

/* Récupération des commentaires principaux */
$reqCommentaires = $bdd->prepare("
    SELECT
        c.id_commentaire,
        c.auteur_id,
        c.nom_auteur,
        c.commentaire,
        c.date_publication,

        (
            SELECT COUNT(*)
            FROM commentaire_reactions r
            WHERE r.commentaire_id = c.id_commentaire
              AND r.type_espace = 'etudiant'
              AND r.reaction = 'like'
        ) AS total_likes,

        (
            SELECT COUNT(*)
            FROM commentaire_reactions r
            WHERE r.commentaire_id = c.id_commentaire
              AND r.type_espace = 'etudiant'
              AND r.reaction = 'dislike'
        ) AS total_dislikes,

        (
            SELECT r.reaction
            FROM commentaire_reactions r
            WHERE r.commentaire_id = c.id_commentaire
              AND r.utilisateur_id = ?
              AND r.type_espace = 'etudiant'
            LIMIT 1
        ) AS ma_reaction

    FROM commentaires c

    WHERE c.type_espace = 'etudiant'
      AND c.parent_id IS NULL

    ORDER BY c.date_publication DESC
");

$reqCommentaires->execute([$id_etu]);

$commentaires = $reqCommentaires->fetchAll(PDO::FETCH_ASSOC);

/* Récupération des réponses */
$reqReponses = $bdd->prepare("
    SELECT
        c.id_commentaire,
        c.parent_id,
        c.nom_auteur,
        c.commentaire,
        c.date_publication,

        (
            SELECT COUNT(*)
            FROM commentaire_reactions r
            WHERE r.commentaire_id = c.id_commentaire
              AND r.type_espace = 'etudiant'
              AND r.reaction = 'like'
        ) AS total_likes,

        (
            SELECT COUNT(*)
            FROM commentaire_reactions r
            WHERE r.commentaire_id = c.id_commentaire
              AND r.type_espace = 'etudiant'
              AND r.reaction = 'dislike'
        ) AS total_dislikes,

        (
            SELECT r.reaction
            FROM commentaire_reactions r
            WHERE r.commentaire_id = c.id_commentaire
              AND r.utilisateur_id = ?
              AND r.type_espace = 'etudiant'
            LIMIT 1
        ) AS ma_reaction

    FROM commentaires c

    WHERE c.type_espace = 'etudiant'
      AND c.parent_id IS NOT NULL

    ORDER BY c.date_publication ASC
");

$reqReponses->execute([$id_etu]);

$toutesReponses = $reqReponses->fetchAll(PDO::FETCH_ASSOC);

/* Regrouper les réponses sous leurs commentaires */
$reponsesParCommentaire = [];

foreach ($toutesReponses as $reponse) {

    $parentId = $reponse["parent_id"];

    $reponsesParCommentaire[$parentId][] = $reponse;
}

/* Protection HTML */
function h($texte) {
    return htmlspecialchars(
        (string) ($texte ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

?>

<!DOCTYPE html>
<html lang="fr">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Commentaires - Espace étudiant</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f4f6f9;
    color: #333;
}

.container {
    max-width: 950px;
    margin: 30px auto;
    padding: 20px;
}

h2 {
    color: #003366;
    margin-bottom: 20px;
}

.comment-form {
    background: white;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 30px;
}

textarea {
    width: 100%;
    min-height: 110px;
    padding: 12px;
    border: 1px solid #ccc;
    border-radius: 5px;
    resize: vertical;
    font-family: Arial, sans-serif;
    font-size: 14px;
}

button {
    cursor: pointer;
}

.publish-btn {
    margin-top: 12px;
    padding: 12px 20px;
    background: #003366;
    color: white;
    border: none;
    border-radius: 5px;
}

.publish-btn:hover {
    background: #0055aa;
}

.comment-item {
    background: white;
    padding: 20px;
    margin-bottom: 20px;
    border-radius: 8px;
    border-left: 4px solid #003366;
}

.comment-author {
    font-weight: bold;
    color: #003366;
}

.comment-date {
    font-size: 12px;
    color: #777;
    margin-top: 5px;
}

.comment-text {
    margin-top: 12px;
    line-height: 1.6;
    overflow-wrap: anywhere;
}

.reactions {
    display: flex;
    gap: 12px;
    margin-top: 15px;
    flex-wrap: wrap;
}

.reaction-form {
    display: inline;
}

.reaction-btn {
    border: 1px solid #ddd;
    background: #f8f9fa;
    padding: 8px 13px;
    border-radius: 20px;
    color: #333;
}

.reaction-btn:hover {
    background: #e5edf5;
}

.reaction-btn.active {
    background: #dceaff;
    border-color: #003366;
    color: #003366;
    font-weight: bold;
}

.reply-section {
    margin-top: 20px;
    margin-left: 25px;
    padding-left: 15px;
    border-left: 2px solid #d4a72c;
}

.reply-item {
    background: #f7f9fc;
    padding: 15px;
    border-radius: 6px;
    margin-bottom: 12px;
}

.reply-form {
    margin-top: 15px;
}

.reply-form textarea {
    min-height: 75px;
}

.reply-form button {
    margin-top: 8px;
    padding: 9px 15px;
    background: #003366;
    color: white;
    border: none;
    border-radius: 5px;
}

.reply-form button:hover {
    background: #0055aa;
}

.empty {
    text-align: center;
    color: #777;
    padding: 25px;
    background: white;
    border-radius: 8px;
}

@media(max-width: 600px) {

    .container {
        padding: 12px;
    }

    .reply-section {
        margin-left: 8px;
        padding-left: 10px;
    }

}

</style>

</head>

<body>

<?php include("menu_etu.php"); ?>

<div class="container">

    <h2>Commentaires des étudiants</h2>

    <!-- FORMULAIRE DE PUBLICATION -->
    <div class="comment-form">

        <form method="POST" action="commentaires_etu.php">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= h($_SESSION["csrf_commentaires"]) ?>"
            >

            <input
                type="hidden"
                name="action"
                value="commenter"
            >

            <label for="commentaire">
                Écrire un commentaire :
            </label>

            <br><br>

            <textarea
                name="commentaire"
                id="commentaire"
                maxlength="2000"
                required
                placeholder="Écris ton commentaire ici..."
            ></textarea>

            <button type="submit" class="publish-btn">
                Publier le commentaire
            </button>

        </form>

    </div>

    <h2>Commentaires publiés</h2>

    <?php if (!empty($commentaires)): ?>

        <?php foreach ($commentaires as $com): ?>

            <div class="comment-item">

                <!-- COMMENTAIRE PRINCIPAL -->
                <div class="comment-author">
                    <?= h($com["nom_auteur"]) ?>
                </div>

                <div class="comment-date">
                    <?= h($com["date_publication"]) ?>
                </div>

                <div class="comment-text">
                    <?= nl2br(h($com["commentaire"])) ?>
                </div>

                <!-- LIKE ET DISLIKE -->
                <div class="reactions">

                    <form method="POST" class="reaction-form">

                        <input type="hidden" name="csrf_token"
                               value="<?= h($_SESSION["csrf_commentaires"]) ?>">

                        <input type="hidden" name="action" value="reaction">

                        <input type="hidden" name="commentaire_id"
                               value="<?= (int) $com["id_commentaire"] ?>">

                        <input type="hidden" name="reaction" value="like">

                        <button
                            type="submit"
                            class="reaction-btn <?= $com["ma_reaction"] === "like" ? "active" : "" ?>"
                        >
                            👍 J'aime (<?= (int) $com["total_likes"] ?>)
                        </button>

                    </form>

                    <form method="POST" class="reaction-form">

                        <input type="hidden" name="csrf_token"
                               value="<?= h($_SESSION["csrf_commentaires"]) ?>">

                        <input type="hidden" name="action" value="reaction">

                        <input type="hidden" name="commentaire_id"
                               value="<?= (int) $com["id_commentaire"] ?>">

                        <input type="hidden" name="reaction" value="dislike">

                        <button
                            type="submit"
                            class="reaction-btn <?= $com["ma_reaction"] === "dislike" ? "active" : "" ?>"
                        >
                            👎 Je n'aime pas (<?= (int) $com["total_dislikes"] ?>)
                        </button>

                    </form>

                </div>

                <!-- REPONSES -->
                <div class="reply-section">

                    <strong>Réponses</strong>

                    <?php if (!empty($reponsesParCommentaire[$com["id_commentaire"]])): ?>

                        <?php foreach ($reponsesParCommentaire[$com["id_commentaire"]] as $rep): ?>

                            <div class="reply-item">

                                <div class="comment-author">
                                    <?= h($rep["nom_auteur"]) ?>
                                </div>

                                <div class="comment-date">
                                    <?= h($rep["date_publication"]) ?>
                                </div>

                                <div class="comment-text">
                                    <?= nl2br(h($rep["commentaire"])) ?>
                                </div>

                                <div class="reactions">

                                    <form method="POST" class="reaction-form">

                                        <input type="hidden" name="csrf_token"
                                               value="<?= h($_SESSION["csrf_commentaires"]) ?>">

                                        <input type="hidden" name="action" value="reaction">

                                        <input type="hidden" name="commentaire_id"
                                               value="<?= (int) $rep["id_commentaire"] ?>">

                                        <input type="hidden" name="reaction" value="like">

                                        <button
                                            type="submit"
                                            class="reaction-btn <?= $rep["ma_reaction"] === "like" ? "active" : "" ?>"
                                        >
                                            👍 J'aime (<?= (int) $rep["total_likes"] ?>)
                                        </button>

                                    </form>

                                    <form method="POST" class="reaction-form">

                                        <input type="hidden" name="csrf_token"
                                               value="<?= h($_SESSION["csrf_commentaires"]) ?>">

                                        <input type="hidden" name="action" value="reaction">

                                        <input type="hidden" name="commentaire_id"
                                               value="<?= (int) $rep["id_commentaire"] ?>">

                                        <input type="hidden" name="reaction" value="dislike">

                                        <button
                                            type="submit"
                                            class="reaction-btn <?= $rep["ma_reaction"] === "dislike" ? "active" : "" ?>"
                                        >
                                            👎 Je n'aime pas (<?= (int) $rep["total_dislikes"] ?>)
                                        </button>

                                    </form>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <p style="color:#777; margin-top:10px;">
                            Aucune réponse pour le moment.
                        </p>

                    <?php endif; ?>

                    <!-- FORMULAIRE DE REPONSE -->
                    <form method="POST" class="reply-form">

                        <input type="hidden" name="csrf_token"
                               value="<?= h($_SESSION["csrf_commentaires"]) ?>">

                        <input type="hidden" name="action" value="repondre">

                        <input type="hidden" name="parent_id"
                               value="<?= (int) $com["id_commentaire"] ?>">

                        <textarea
                            name="reponse"
                            maxlength="2000"
                            required
                            placeholder="Écris une réponse..."
                        ></textarea>

                        <button type="submit">
                            Répondre
                        </button>

                    </form>

                </div>

            </div>

        <?php endforeach; ?>

    <?php else: ?>

        <p class="empty">
            Aucun commentaire pour le moment.
        </p>

    <?php endif; ?>

</div>

</body>
</html>