<?php

/**
 * Vérifie les conflits avant une affectation d'examen.
 *
 * Retourne :
 * - null si aucun conflit ;
 * - un message explicatif si un conflit existe.
 */

function verifierConflitsAffectation(
    PDO $bdd,
    array $idsEtudiants,
    int $idCours,
    int $idSalle,
    int $idSurv,
    string $dateExam,
    string $heureDebut,
    string $heureFin
): ?string {

    // Vérification des horaires
    if ($heureDebut >= $heureFin) {
        return "Impossible de faire cette affectation : l'heure de fin doit être postérieure à l'heure de début.";
    }

    // ==================================================
    // 1. CONFLIT ETUDIANT
    // ==================================================

    $sqlEtudiant = "
        SELECT
            e.nom_etu,
            e.prenom_etu,
            a.id_cours,
            a.date_exam,
            a.heure_debut,
            a.heure_fin
        FROM affectation a
        INNER JOIN etudiant e ON e.id_etu = a.id_etu
        INNER JOIN cours c ON c.id_cours = a.id_cours
        WHERE a.id_etu = ?
          AND a.date_exam = ?
          AND a.heure_debut < ?
          AND a.heure_fin > ?
        LIMIT 1
    ";

    $stmtEtudiant = $bdd->prepare($sqlEtudiant);

    foreach (array_unique($idsEtudiants) as $idEtu) {

        $stmtEtudiant->execute([
            $idEtu,
            $dateExam,
            $heureFin,
            $heureDebut
        ]);

        $conflit = $stmtEtudiant->fetch(PDO::FETCH_ASSOC);

        if ($conflit) {
            return "Impossible de faire cette affectation : l'étudiant "
                . $conflit['nom_etu'] . " " . $conflit['prenom_etu']
                . " est déjà affecté à un autre examen le "
                . $conflit['date_exam']
                . " de " . $conflit['heure_debut']
                . " à " . $conflit['heure_fin'] . ".";
        }
    }

    // ==================================================
    // 2. CONFLIT DE SALLE
    // ==================================================

    $sqlSalle = "
        SELECT
            s.nom_salle,
            a.heure_debut,
            a.heure_fin,
            c.nom_cours
        FROM affectation a
        INNER JOIN salle s ON s.id_salle = a.id_salle
        INNER JOIN cours c ON c.id_cours = a.id_cours
        WHERE a.id_salle = ?
          AND a.date_exam = ?
          AND a.heure_debut < ?
          AND a.heure_fin > ?
          AND NOT (
              a.id_cours = ?
              AND a.id_surv = ?
              AND a.heure_debut = ?
              AND a.heure_fin = ?
          )
        LIMIT 1
    ";

    $stmtSalle = $bdd->prepare($sqlSalle);

    $stmtSalle->execute([
        $idSalle,
        $dateExam,
        $heureFin,
        $heureDebut,
        $idCours,
        $idSurv,
        $heureDebut,
        $heureFin
    ]);

    $conflitSalle = $stmtSalle->fetch(PDO::FETCH_ASSOC);

    if ($conflitSalle) {
        return "Impossible de faire cette affectation : la salle "
            . $conflitSalle['nom_salle']
            . " est déjà réservée pour le cours "
            . $conflitSalle['nom_cours']
            . " de " . $conflitSalle['heure_debut']
            . " à " . $conflitSalle['heure_fin'] . ".";
    }

    // ==================================================
    // 3. CONFLIT DE SURVEILLANT
    // ==================================================

    $sqlSurveillant = "
        SELECT
            s.nom_surv,
            s.prenom_surv,
            a.heure_debut,
            a.heure_fin,
            c.nom_cours,
            sa.nom_salle
        FROM affectation a
        INNER JOIN surveillant s ON s.id_surv = a.id_surv
        INNER JOIN cours c ON c.id_cours = a.id_cours
        INNER JOIN salle sa ON sa.id_salle = a.id_salle
        WHERE a.id_surv = ?
          AND a.date_exam = ?
          AND a.heure_debut < ?
          AND a.heure_fin > ?
          AND NOT (
              a.id_cours = ?
              AND a.id_salle = ?
              AND a.heure_debut = ?
              AND a.heure_fin = ?
          )
        LIMIT 1
    ";

    $stmtSurveillant = $bdd->prepare($sqlSurveillant);

    $stmtSurveillant->execute([
        $idSurv,
        $dateExam,
        $heureFin,
        $heureDebut,
        $idCours,
        $idSalle,
        $heureDebut,
        $heureFin
    ]);

    $conflitSurveillant = $stmtSurveillant->fetch(PDO::FETCH_ASSOC);

    if ($conflitSurveillant) {
        return "Impossible de faire cette affectation : le surveillant "
            . $conflitSurveillant['nom_surv'] . " "
            . $conflitSurveillant['prenom_surv']
            . " surveille déjà le cours "
            . $conflitSurveillant['nom_cours']
            . " dans la salle "
            . $conflitSurveillant['nom_salle']
            . " de " . $conflitSurveillant['heure_debut']
            . " à " . $conflitSurveillant['heure_fin'] . ".";
    }

    return null;
}