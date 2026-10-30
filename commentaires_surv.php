<?php
session_start();

/* Vérification de la session */
if (
    !isset($_SESSION["id_surv"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "surveillant"
) {
    header("Location: login_surv.php");
    exit();
}

/* Timeout : 5 minutes */
$timeout = 300;

if (isset($_SESSION["last_activity"]) &&
    time() - $_SESSION["last_activity"] > $timeout) {

    session_unset();
    session_destroy();

    header("Location: login_surv.php?timeout=1");
    exit();
}

$_SESSION["last_activity"] = time();

require_once __DIR__ . "/connexionDB.php";

/* Informations du surveillant */
$id_surv = $_SESSION["id_surv"];

$req = $bdd->prepare("
    SELECT nom_surv, prenom_surv
    FROM surveillant
    WHERE id_surv = ?
");
$req->execute([$id_surv]);
$surveillant = $req->fetch(PDO::FETCH_ASSOC);

if (!$surveillant) {
    die("Compte surveillant introuvable.");
}

$nom_auteur = $surveillant["prenom_surv"] . " " . $surveillant["nom_surv"];

/* Protection CSRF */
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

/* Fonction de sécurisation */
function h($valeur) {
    return htmlspecialchars((string)$valeur, ENT_QUOTES, "UTF-8");
}

/* Traitement des formulaires */
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

        $commentaire = trim($_POST["commentaire"] ?? "");
        $parent_id = !empty($_POST["parent_id"])
            ? (int)$_POST["parent_id"]
            : null;

        if ($commentaire !== "") {

            /* Vérifier que le parent appartient à l'espace surveillant */
            if ($parent_id !== null) {
                $check = $bdd->prepare("
                    SELECT id_commentaire
                    FROM commentaires
                    WHERE id_commentaire = ?
                    AND type_espace = 'surveillant'
                ");
                $check->execute([$parent_id]);

                if (!$check->fetch()) {
                    die("Commentaire parent introuvable.");
                }
            }

            $insert = $bdd->prepare("
                INSERT INTO commentaires
                (auteur_id, nom_auteur, type_espace, commentaire, parent_id)
                VALUES (?, ?, 'surveillant', ?, ?)
            ");

            $insert->execute([
                $id_surv,
                $nom_auteur,
                $commentaire,
                $parent_id
            ]);
        }
    }

    /* Like ou dislike */
    if ($action === "reaction") {

        $commentaire_id = (int)($_POST["commentaire_id"] ?? 0);
        $reaction = $_POST["reaction"] ?? "";

        if (in_array($reaction, ["like", "dislike"], true)) {

            $check = $bdd->prepare("
                SELECT id_commentaire
                FROM commentaires
                WHERE id_commentaire = ?
                AND type_espace = 'surveillant'
            ");
            $check->execute([$commentaire_id]);

            if ($check->fetch()) {

                $checkReaction = $bdd->prepare("
                    SELECT id_reaction, reaction
                    FROM commentaire_reactions
                    WHERE commentaire_id = ?
                    AND utilisateur_id = ?
                    AND type_espace = 'surveillant'
                ");

                $checkReaction->execute([
                    $commentaire_id,
                    $id_surv
                ]);

                $ancienne = $checkReaction->fetch(PDO::FETCH_ASSOC);

                if ($ancienne) {

                    if ($ancienne["reaction"] === $reaction) {
                        /* Deuxième clic : retirer la réaction */
                        $delete = $bdd->prepare("
                            DELETE FROM commentaire_reactions
                            WHERE id_reaction = ?
                        ");
                        $delete->execute([$ancienne["id_reaction"]]);

                    } else {
                        /* Changer de réaction */
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
                        VALUES (?, ?, 'surveillant', ?)
                    ");

                    $insert->execute([
                        $commentaire_id,
                        $id_surv,
                        $reaction
                    ]);
                }
            }
        }
    }

    header("Location: commentaires_surv.php");
    exit();
}

/* Récupération des commentaires et réactions */
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
         AND r.type_espace = 'surveillant'
         LIMIT 1) AS ma_reaction

    FROM commentaires c
    WHERE c.type_espace = 'surveillant'
    ORDER BY c.date_publication ASC
");

$req->execute([$id_surv]);
$commentaires = $req->fetchAll(PDO::FETCH_ASSOC);

/* Organiser les commentaires et leurs réponses */
$discussions = [];

foreach ($commentaires as $commentaire) {
    $discussions[$commentaire["id_commentaire"]] = $commentaire;
    $discussions[$commentaire["id_commentaire"]]["reponses"] = [];
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

/* Affichage récursif des discussions */
function afficherDiscussion($commentaire, $csrf_token) {
    ?>
    <div class="commentaire">

        <div class="commentaire-entete">
            <strong><?= h($commentaire["nom_auteur"]) ?></strong>
            <small><?= h($commentaire["date_publication"]) ?></small>
        </div>

        <p class="texte-commentaire">
            <?= nl2br(h($commentaire["commentaire"])) ?>
        </p>

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

        <form method="POST" class="form-reponse">
            <input type="hidden" name="csrf_token"
                   value="<?= h($csrf_token) ?>">
            <input type="hidden" name="action" value="publier">
            <input type="hidden" name="parent_id"
                   value="<?= (int)$commentaire["id_commentaire"] ?>">

            <textarea name="commentaire"
                      placeholder="Écrire une réponse..."
                      required></textarea>

            <button type="submit" class="btn-repondre">
                Répondre
            </button>
        </form>

        <?php if (!empty($commentaire["reponses"])): ?>
            <div class="reponses">
                <?php
                foreach ($commentaire["reponses"] as $reponse) {
                    afficherDiscussion($reponse, $csrf_token);
                }
                ?>
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
    <title>Commentaires - Espace Surveillant</title>

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

        /* Menu latéral existant */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 230px;
            height: 100vh;
            background: #003366;
            padding: 20px 0;
            overflow-y: auto;
        }

        .sidebar h3 {
            color: white;
            text-align: center;
            margin-bottom: 25px;
        }

        .sidebar a {
            display: block;
            padding: 13px 20px;
            color: white;
            text-decoration: none;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: #d4a017;
            color: #003366;
        }

        .sidebar .logout {
            margin-top: 15px;
        }

        .contenu {
            max-width: 1100px;
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

        .entete h2 {
            margin-top: 0;
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
            min-height: 100px;
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

        .btn-publier {
            background: #003366;
            color: white;
            margin-top: 10px;
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
            background: #fff;
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

        .texte-commentaire {
            line-height: 1.6;
            overflow-wrap: anywhere;
        }

        .reactions {
            display: flex;
            gap: 8px;
            margin-bottom: 12px;
        }

        .reactions form {
            display: inline;
        }

        .reactions button {
            background: #f0f2f5;
        }

        .reactions button.selectionne {
            background: #d4a017;
        }

        .form-reponse {
            margin-top: 12px;
        }

        .form-reponse textarea {
            min-height: 65px;
        }

        .btn-repondre {
            margin-top: 7px;
            background: #003366;
            color: white;
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

            .sidebar {
                position: relative;
                width: 100%;
                height: auto;
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

<!-- Menu latéral déjà utilisé dans espace_surv.php -->
<aside class="sidebar">

    <h3>Espace Surveillant</h3>

    <a href="espace_surv.php">Tableau de bord</a>

    <a href="mes_notifications_surv.php">Mes notifications</a>

    <a href="profil_surv.php">Mon profil</a>

    <a href="affectations_surv.php">Mes affectations</a>

    <a href="documents_partages_surv.php">Documents partagés</a>

    <a href="rapports_surv.php">Mes rapports PDF</a>

    <a href="commentaires_surv.php" class="active">Commentaires</a>

    <a href="changer_mdp_surv.php">Changer mon mot de passe</a>

    <a href="logout_surv.php" class="logout">Déconnexion</a>

</aside>

<main class="contenu">

    <a href="espace_surv.php" class="retour">
        ← Retour au tableau de bord
    </a>

    <div class="entete">
        <h2>Espace commentaires</h2>
        <p>
            Bienvenue <?= h($nom_auteur) ?>.
            Partagez vos idées et échangez avec les autres surveillants.
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
        <h3>Les discussions</h3>

        <?php if (empty($racines)): ?>

            <p class="vide">
                Aucun commentaire pour le moment.
                Soyez le premier à publier !
            </p>

        <?php else: ?>

            <?php
            foreach ($racines as $commentaire) {
                afficherDiscussion(
                    $commentaire,
                    $_SESSION["csrf_token"]
                );
            }
            ?>

        <?php endif; ?>

    </section>

</main>

</body>
</html>