<?php

function journal($bdd, $user_id, $nom_utilisateur, $type_user, $action)
{

    $req = $bdd->prepare("
        INSERT INTO journal_activite
        (user_id, nom_utilisateur, type_user, action, date_action)
        VALUES (?, ?, ?, ?, NOW())
    ");

    $req->execute([
        $user_id,
        $nom_utilisateur,
        $type_user,
        $action
    ]);

}

?>