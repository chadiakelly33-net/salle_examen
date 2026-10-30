<?php
session_start();

/* Vérification administrateur */
if (
    !isset($_SESSION["id_admin"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: connexion.php");
    exit();
}

require_once __DIR__ . "/connexionDB.php";

/* Identité administrateur */
$id_admin = (string) $_SESSION["id_admin"];
$nom_admin = "Administrateur";

/* Protection CSRF */
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

function h($valeur) {
    return htmlspecialchars((string)$valeur, ENT_QUOTES, "UTF-8");
}

/* Supprimer un commentaire et toutes ses réponses */
function supprimerDiscussion($bdd, $id) {

    $req = $bdd->prepare("
        SELECT id_commentaire
        FROM commentaires
        WHERE parent_id = ?
    ");
    $req->execute([$id]);

    $enfants = $req->fetchAll(PDO::FETCH_COLUMN);

    foreach ($enfants as $enfant) {
        supprimerDiscussion($bdd, $enfant);
    }

    $delete = $bdd->prepare("
        DELETE FROM commentaires
        WHERE id_commentaire = ?
    ");
    $delete->execute([$id]);
}

/* Traitement des actions */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"])
    ) {
        die("Requête invalide. Rechargez la page.");
    }

    $action = $_POST["action"] ?? "";

    /* Publier un commentaire ou une réponse */
    if ($action === "publier") {

        $texte = trim($_POST["commentaire"] ?? "");
        $parent_id = !empty($_POST["parent_id"])
            ? (int)$_POST["parent_id"]
            : null;

        if ($texte !== "") {

            if ($parent_id !== null) {
                $check = $bdd->prepare("
                    SELECT id_commentaire
                    FROM commentaires
                    WHERE id_commentaire = ?
                ");
                $check->execute([$parent_id]);

                if (!$check->fetch()) {
                    die("Commentaire parent introuvable.");
                }
            }

            $insert = $bdd->prepare("
                INSERT INTO commentaires
                (auteur_id, nom_auteur, type_espace, commentaire, parent_id)
                VALUES (?, ?, 'admin', ?, ?)
            ");

            $insert->execute([
                $id_admin,
                $nom_admin,
                $texte,
                $parent_id
            ]);
        }
    }

    /* Like / Dislike */
    if ($action === "reaction") {

        $commentaire_id = (int)($_POST["commentaire_id"] ?? 0);
        $reaction = $_POST["reaction"] ?? "";

        if (in_array($reaction, ["like", "dislike"], true)) {

            $check = $bdd->prepare("
                SELECT id_commentaire
                FROM commentaires
                WHERE id_commentaire = ?
            ");
            $check->execute([$commentaire_id]);

            if ($check->fetch()) {

                $req = $bdd->prepare("
                    SELECT id_reaction, reaction
                    FROM commentaire_reactions
                    WHERE commentaire_id = ?
                    AND utilisateur_id = ?
                    AND type_espace = 'admin'
                ");

                $req->execute([$commentaire_id, $id_admin]);
                $ancienne = $req->fetch(PDO::FETCH_ASSOC);

                if ($ancienne) {

                    if ($ancienne["reaction"] === $reaction) {

                        $delete = $bdd->prepare("
                            DELETE FROM commentaire_reactions
                            WHERE id_reaction = ?
                        ");
                        $delete->execute([$ancienne["id_reaction"]]);

                    } else {

                        $update = $bdd->prepare("
                            UPDATE commentaire_reactions
                            SET reaction = ?
                            WHERE id_reaction = ?
                        ");
                        $update->execute([
                            $reaction,
                            $ancienne["id_reaction"]
                        ]);
                    }

                } else {

                    $insert = $bdd->prepare("
                        INSERT INTO commentaire_reactions
                        (commentaire_id, utilisateur_id, type_espace, reaction)
                        VALUES (?, ?, 'admin', ?)
                    ");

                    $insert->execute([
                        $commentaire_id,
                        $id_admin,
                        $reaction
                    ]);
                }
            }
        }
    }

    /* Supprimer un commentaire */
    if ($action === "supprimer") {

        $commentaire_id = (int)($_POST["commentaire_id"] ?? 0);

        $check = $bdd->prepare("
            SELECT id_commentaire
            FROM commentaires
            WHERE id_commentaire = ?
        ");
        $check->execute([$commentaire_id]);

        if ($check->fetch()) {

            $bdd->beginTransaction();

            try {
                supprimerDiscussion($bdd, $commentaire_id);
                $bdd->commit();

            } catch (Exception $e) {
                $bdd->rollBack();
                die("Erreur lors de la suppression.");
            }
        }
    }

    header("Location: commentaires_admin.php");
    exit();
}

/* Récupérer TOUS les commentaires */
$req = $bdd->prepare("
    SELECT
        c.*,

        (SELECT COUNT(*)
         FROM commentaire_reactions r
         WHERE r.commentaire_id = c.id_commentaire
         AND r.reaction = 'like') AS total_likes,

        (SELECT COUNT(*)
         FROM commentaire_reactions r
         WHERE r.commentaire_id = c.id_commentaire
         AND r.reaction = 'dislike') AS total_dislikes,

        (SELECT r.reaction
         FROM commentaire_reactions r
         WHERE r.commentaire_id = c.id_commentaire
         AND r.utilisateur_id = ?
         AND r.type_espace = 'admin'
         LIMIT 1) AS ma_reaction

    FROM commentaires c
    ORDER BY c.date_publication ASC
");

$req->execute([$id_admin]);
$commentaires = $req->fetchAll(PDO::FETCH_ASSOC);

/* Construire l'arborescence */
$discussions = [];

foreach ($commentaires as $commentaire) {
    $id = $commentaire["id_commentaire"];
    $discussions[$id] = $commentaire;
    $discussions[$id]["reponses"] = [];
}

$racines = [];

foreach ($discussions as $id => &$commentaire) {

    $parent = $commentaire["parent_id"];

    if ($parent !== null && isset($discussions[$parent])) {
        $discussions[$parent]["reponses"][] = &$commentaire;
    } else {
        $racines[] = &$commentaire;
    }
}
unset($commentaire);

/* Affichage récursif */
function afficherDiscussionAdmin($commentaire, $csrf_token) {

    $type = $commentaire["type_espace"];

    $libelles = [
        "etudiant" => "Étudiant",
        "surveillant" => "Surveillant",
        "admin" => "Administrateur"
    ];

    ?>
    <div class="commentaire">

        <div class="commentaire-entete">

            <div>
                <strong><?= h($commentaire["nom_auteur"]) ?></strong>

                <span class="badge <?= h($type) ?>">
                    <?= h($libelles[$type] ?? $type) ?>
                </span>
            </div>

            <small><?= h($commentaire["date_publication"]) ?></small>

        </div>

        <p class="texte-commentaire">
            <?= nl2br(h($commentaire["commentaire"])) ?>
        </p>

        <div class="actions">

            <div class="reactions">

                <form method="POST">
                    <input type="hidden" name="csrf_token"
                           value="<?= h($csrf_token) ?>">
                    <input type="hidden" name="action" value="reaction">
                    <input type="hidden" name="commentaire_id"
                           value="<?= (int)$commentaire["id_commentaire"] ?>">
                    <input type="hidden" name="reaction" value="like">

                    <button type="submit"
                        class="<?= $commentaire["ma_reaction"] === "like" ? "selectionne" : "" ?>">
                        👍 <?= (int)$commentaire["total_likes"] ?>
                    </button>
                </form>

                <form method="POST">
                    <input type="hidden" name="csrf_token"
                           value="<?= h($csrf_token) ?>">
                    <input type="hidden" name="action" value="reaction">
                    <input type="hidden" name="commentaire_id"
                           value="<?= (int)$commentaire["id_commentaire"] ?>">
                    <input type="hidden" name="reaction" value="dislike">

                    <button type="submit"
                        class="<?= $commentaire["ma_reaction"] === "dislike" ? "selectionne" : "" ?>">
                        👎 <?= (int)$commentaire["total_dislikes"] ?>
                    </button>
                </form>

            </div>

            <form method="POST"
                  onsubmit="return confirm('Supprimer ce commentaire et toutes ses réponses ?');">

                <input type="hidden" name="csrf_token"
                       value="<?= h($csrf_token) ?>">
                <input type="hidden" name="action" value="supprimer">
                <input type="hidden" name="commentaire_id"
                       value="<?= (int)$commentaire["id_commentaire"] ?>">

                <button type="submit" class="btn-supprimer">
                    Supprimer
                </button>

            </form>

        </div>

        <form method="POST" class="form-reponse">

            <input type="hidden" name="csrf_token"
                   value="<?= h($csrf_token) ?>">
            <input type="hidden" name="action" value="publier">
            <input type="hidden" name="parent_id"
                   value="<?= (int)$commentaire["id_commentaire"] ?>">

            <textarea name="commentaire"
                      placeholder="Répondre à ce commentaire..."
                      required></textarea>

            <button type="submit" class="btn-repondre">
                Répondre
            </button>

        </form>

        <?php if (!empty($commentaire["reponses"])): ?>

            <div class="reponses">

                <?php foreach ($commentaire["reponses"] as $reponse): ?>

                    <?php afficherDiscussionAdmin($reponse, $csrf_token); ?>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </div>
    <?php
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Gestion des commentaires - Administration</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding-left: 230px;
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            color: #333;
        }

        .contenu {
            max-width: 1150px;
            margin: auto;
            padding: 30px;
        }

        .retour {
            display: inline-block;
            background: #003366;
            color: white;
            padding: 11px 18px;
            border-radius: 6px;
            text-decoration: none;
            margin-bottom: 20px;
        }

        .retour:hover {
            background: #d4a017;
            color: #003366;
        }

        .entete {
            background: #003366;
            color: white;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 25px;
        }

        .bloc {
            background: white;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }

        textarea {
            width: 100%;
            min-height: 90px;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            resize: vertical;
            font-family: Arial, sans-serif;
        }

        button {
            cursor: pointer;
            border: none;
            border-radius: 5px;
            padding: 9px 14px;
        }

        .btn-publier,
        .btn-repondre {
            background: #003366;
            color: white;
            margin-top: 8px;
        }

        .btn-publier:hover,
        .btn-repondre:hover {
            background: #d4a017;
            color: #003366;
        }

        .commentaire {
            border: 1px solid #e1e5eb;
            border-radius: 7px;
            padding: 18px;
            margin-bottom: 15px;
            background: white;
        }

        .commentaire-entete {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: wrap;
        }

        .commentaire-entete strong {
            color: #003366;
        }

        .commentaire-entete small {
            color: #777;
        }

        .badge {
            display: inline-block;
            padding: 4px 8px;
            margin-left: 6px;
            border-radius: 12px;
            font-size: 12px;
        }

        .badge.etudiant {
            background: #dceeff;
            color: #07518b;
        }

        .badge.surveillant {
            background: #e4f4e7;
            color: #216b31;
        }

        .badge.admin {
            background: #fff0d0;
            color: #805600;
        }

        .texte-commentaire {
            line-height: 1.6;
            overflow-wrap: anywhere;
        }

        .actions,
        .reactions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .reactions button {
            background: #f0f2f5;
        }

        .reactions button.selectionne {
            background: #d4a017;
        }

        .btn-supprimer {
            background: #b42318;
            color: white;
        }

        .btn-supprimer:hover {
            background: #8f1b13;
        }

        .form-reponse {
            margin-top: 15px;
        }

        .form-reponse textarea {
            min-height: 60px;
        }

        .reponses {
            margin: 18px 0 0 20px;
            padding-left: 15px;
            border-left: 3px solid #d4a017;
        }

        .vide {
            text-align: center;
            color: #777;
            padding: 25px;
        }

        @media (max-width: 768px) {
            body {
                padding-left: 0;
            }

            .contenu {
                padding: 15px;
            }

            .reponses {
                margin-left: 8px;
                padding-left: 8px;
            }
        }
    </style>
</head>

<body>

<?php
/* Menu latéral existant de l'administration */
include("menu_principal.php");
?>

<main class="contenu">

    <a href="dashboard.php" class="retour">
        ← Retour au tableau de bord
    </a>

    <div class="entete">
        <h2>Gestion des commentaires</h2>
        <p>
            Consultez et gérez les discussions des étudiants,
            des surveillants et des administrateurs.
        </p>
    </div>

    <section class="bloc">

        <h3>Publier un commentaire</h3>

        <form method="POST">

            <input type="hidden" name="csrf_token"
                   value="<?= h($_SESSION["csrf_token"]) ?>">

            <input type="hidden" name="action" value="publier">

            <textarea name="commentaire"
                      placeholder="Écrivez votre commentaire..."
                      required></textarea>

            <button type="submit" class="btn-publier">
                Publier
            </button>

        </form>

    </section>

    <section class="bloc">

        <h3>Toutes les discussions</h3>

        <?php if (empty($racines)): ?>

            <p class="vide">Aucun commentaire pour le moment.</p>

        <?php else: ?>

            <?php foreach ($racines as $commentaire): ?>

                <?php
                afficherDiscussionAdmin(
                    $commentaire,
                    $_SESSION["csrf_token"]
                );
                ?>

            <?php endforeach; ?>

        <?php endif; ?>

    </section>

</main>

</body>
</html>