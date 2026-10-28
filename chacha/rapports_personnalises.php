<?php
session_start();
require_once("connexionDB.php");

/* 1. SÉCURITÉ */
if (!isset($_SESSION["id_admin"]) || ($_SESSION["role"] ?? "") !== "admin") {
    header("Location: connexion.php");
    exit();
}

/* 2. FONCTIONS UTILITAIRES */
function echapper($valeur) {
    return htmlspecialchars((string)($valeur ?? ""), ENT_QUOTES, "UTF-8");
}

function ouiNon($valeur) {
    return ((string)$valeur === "1") ? "Oui" : "Non";
}

function dateFr($valeur) {
    if (empty($valeur)) return "-";
    $timestamp = strtotime($valeur);
    return $timestamp !== false ? date("d/m/Y", $timestamp) : "-";
}

function heureFr($valeur) {
    if (empty($valeur)) return "-";
    $timestamp = strtotime($valeur);
    return $timestamp !== false ? date("H:i", $timestamp) : "-";
}

/* 3. FONCTIONS DE CRÉATION DU PDF */
function pdfEscape($texte) {
    $texte = (string)$texte;

    if (function_exists("iconv")) {
        $converti = @iconv("UTF-8", "Windows-1252//TRANSLIT//IGNORE", $texte);
        if ($converti !== false) {
            $texte = $converti;
        }
    }

    $texte = str_replace(
        ["\\", "(", ")", "\r", "\n"],
        ["\\\\", "\\(", "\\)", "", ""],
        $texte
    );

    $sortie = "";

    for ($i = 0, $n = strlen($texte); $i < $n; $i++) {
        $code = ord($texte[$i]);

        $sortie .= ($code < 32 || $code > 126)
            ? "\\" . str_pad(decoct($code), 3, "0", STR_PAD_LEFT)
            : $texte[$i];
    }

    return $sortie;
}

function pdfTexte(
    $texte,
    $x,
    $y,
    $taille = 9,
    $couleur = [0.15, 0.19, 0.25],
    $gras = false
) {
    $police = $gras ? "/F2" : "/F1";

    return sprintf(
        "%.3f %.3f %.3f rg\nBT %s %.1f Tf %.1f %.1f Td (%s) Tj ET\n",
        $couleur[0],
        $couleur[1],
        $couleur[2],
        $police,
        $taille,
        $x,
        $y,
        pdfEscape($texte)
    );
}

function pdfRectangle($x, $y, $largeur, $hauteur, $couleur) {
    return sprintf(
        "%.3f %.3f %.3f rg %.1f %.1f %.1f %.1f re f\n",
        $couleur[0],
        $couleur[1],
        $couleur[2],
        $x,
        $y,
        $largeur,
        $hauteur
    );
}

function pdfLigne($x1, $y1, $x2, $y2, $couleur) {
    return sprintf(
        "%.3f %.3f %.3f RG 0.6 w %.1f %.1f m %.1f %.1f l S\n",
        $couleur[0],
        $couleur[1],
        $couleur[2],
        $x1,
        $y1,
        $x2,
        $y2
    );
}

function textePDFCourt($valeur, $maximum) {
    $valeur = trim((string)($valeur ?? ""));

    if ($maximum < 1) {
        return "";
    }

    if (function_exists("iconv")) {
        $converti = @iconv(
            "UTF-8",
            "Windows-1252//TRANSLIT//IGNORE",
            $valeur
        );

        if ($converti !== false) {
            $valeur = $converti;
        }
    }

    if (strlen($valeur) > $maximum) {
        $valeur = substr($valeur, 0, max(1, $maximum - 3)) . "...";
    }

    return $valeur;
}

/* 4. GÉNÉRATION DU PDF */
function genererPDFPersonnalise($titre, $colonnes, $lignes) {
    $largeur = 842;
    $hauteur = 595;

    $bleuMarine = [0.02, 0.12, 0.25];
    $or = [0.82, 0.65, 0.29];
    $blanc = [1, 1, 1];
    $couleurTexte = [0.18, 0.22, 0.28];
    $gris = [0.45, 0.49, 0.55];
    $grisClair = [0.95, 0.96, 0.98];
    $bordure = [0.86, 0.88, 0.91];

    $groupes = array_chunk($lignes, 18);

    if (empty($groupes)) {
        $groupes = [[]];
    }

    $pages = [];
    $totalPages = count($groupes);

    /*
     * CORRECTION IMPORTANTE :
     * Le titre dépend du rapport sélectionné.
     */
    $titrePDF = strtoupper($titre);

    if (function_exists("iconv")) {
        $converti = @iconv(
            "UTF-8",
            "Windows-1252//TRANSLIT//IGNORE",
            $titrePDF
        );

        if ($converti !== false) {
            $titrePDF = $converti;
        }
    }

    if (strlen($titrePDF) > 60) {
        $titrePDF = substr($titrePDF, 0, 57) . "...";
    }

    foreach ($groupes as $numeroPage => $groupe) {
        $flux = pdfRectangle(0, 0, $largeur, $hauteur, $blanc);
        $flux .= pdfRectangle(0, 490, $largeur, 105, $bleuMarine);
        $flux .= pdfRectangle(0, 486, $largeur, 4, $or);

        $flux .= pdfTexte(
            "SYSTEME DE GESTION DES SALLES D'EXAMEN",
            38,
            562,
            9,
            [0.84, 0.88, 0.93],
            true
        );

        /* TITRE DYNAMIQUE DU RAPPORT */
        $flux .= pdfTexte(
            $titrePDF,
            38,
            530,
            18,
            $blanc,
            true
        );

        $flux .= pdfTexte(
            "Document genere le : " . date("d/m/Y H:i"),
            38,
            510,
            9,
            [0.84, 0.88, 0.93]
        );

        $flux .= pdfTexte(
            "Nombre de resultats : " . count($lignes),
            38,
            462,
            9,
            $gris,
            true
        );

        $flux .= pdfLigne(38, 448, 804, 448, $bordure);

        $flux .= pdfTexte(
            $numeroPage === 0
                ? "DETAIL DU RAPPORT"
                : "DETAIL DU RAPPORT - SUITE",
            38,
            425,
            12,
            $bleuMarine,
            true
        );

        $nombreColonnes = max(1, count($colonnes));
        $largeurTableau = 766;
        $largeurColonne = $largeurTableau / $nombreColonnes;
        $enteteY = 390;
        $hauteurLigne = 19;

        $flux .= pdfRectangle(
            38,
            $enteteY - 5,
            $largeurTableau,
            23,
            $bleuMarine
        );

        foreach ($colonnes as $i => $colonne) {
            $x = 38 + ($i * $largeurColonne) + 5;

            $maximum = max(
                5,
                (int)(($largeurColonne - 10) / 4.2)
            );

            $nomColonne = textePDFCourt($colonne, $maximum);

            $flux .= pdfTexte(
                $nomColonne,
                $x,
                $enteteY + 3,
                7,
                $blanc,
                true
            );
        }

        $y = $enteteY - 17;

        foreach ($groupe as $index => $ligne) {
            $yLigne = $y - $hauteurLigne + 5;

            if ($index % 2 === 0) {
                $flux .= pdfRectangle(
                    38,
                    $yLigne,
                    $largeurTableau,
                    $hauteurLigne,
                    $grisClair
                );
            }

            $flux .= pdfLigne(
                38,
                $yLigne,
                38 + $largeurTableau,
                $yLigne,
                $bordure
            );

            foreach ($colonnes as $i => $colonne) {
                $x = 38 + ($i * $largeurColonne) + 5;

                $maximum = max(
                    5,
                    (int)(($largeurColonne - 10) / 4.0)
                );

                $valeur = textePDFCourt(
                    $ligne[$i] ?? "",
                    $maximum
                );

                $flux .= pdfTexte(
                    $valeur,
                    $x,
                    $y - 9,
                    7,
                    $couleurTexte
                );
            }

            $y -= $hauteurLigne;
        }

        if (empty($groupe)) {
            $flux .= pdfRectangle(
                38,
                $enteteY - 47,
                $largeurTableau,
                35,
                $grisClair
            );

            $flux .= pdfTexte(
                "Aucun resultat ne correspond aux filtres selectionnes.",
                50,
                $enteteY - 34,
                9,
                $gris
            );
        }

        $flux .= pdfLigne(38, 35, 804, 35, $bordure);

        $flux .= pdfTexte(
            "SYSTEME DE GESTION DES SALLES D'EXAMEN",
            38,
            20,
            7,
            $gris,
            true
        );

        $flux .= pdfTexte(
            "Document administratif - Usage interne",
            295,
            20,
            7,
            $gris
        );

        $flux .= pdfTexte(
            "Page " . ($numeroPage + 1) . " / " . $totalPages,
            735,
            20,
            8,
            $bleuMarine,
            true
        );

        $pages[] = $flux;
    }

    /* CONSTRUCTION DU DOCUMENT PDF */
    $objets = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>'
    ];

    $objetsPages = [];
    $numeroObjet = 5;

    foreach ($pages as $flux) {
        $objetPage = $numeroObjet;
        $objetContenu = $numeroObjet + 1;

        $objetsPages[] = $objetPage . ' 0 R';

        $objets[$objetPage] =
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] ' .
            '/Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> ' .
            '/Contents ' . $objetContenu . ' 0 R >>';

        $objets[$objetContenu] =
            '<< /Length ' . strlen($flux) . " >>\nstream\n" .
            $flux . "\nendstream";

        $numeroObjet += 2;
    }

    $objets[2] =
        '<< /Type /Pages /Kids [' .
        implode(' ', $objetsPages) .
        '] /Count ' . count($pages) . ' >>';

    ksort($objets);

    $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
    $offsets = [0];
    $maxObjet = max(array_keys($objets));

    for ($i = 1; $i <= $maxObjet; $i++) {
        if (!isset($objets[$i])) {
            $objets[$i] = '<< >>';
        }
    }

    ksort($objets);

    for ($i = 1; $i <= $maxObjet; $i++) {
        $offsets[$i] = strlen($pdf);

        $pdf .= $i . " 0 obj\n";
        $pdf .= $objets[$i] . "\n";
        $pdf .= "endobj\n";
    }

    $xref = strlen($pdf);

    $pdf .= "xref\n0 " . ($maxObjet + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";

    for ($i = 1; $i <= $maxObjet; $i++) {
        $pdf .= sprintf(
            "%010d 00000 n \n",
            $offsets[$i]
        );
    }

    $pdf .= "trailer\n<< /Size " .
        ($maxObjet + 1) .
        " /Root 1 0 R >>\n";

    $pdf .= "startxref\n" . $xref . "\n%%EOF";

    while (ob_get_level() > 0) {
        if (!@ob_end_clean()) {
            break;
        }
    }

    $nomFichier = 'rapport_' .
        preg_replace(
            '/[^a-zA-Z0-9_-]/',
            '_',
            strtolower($titre)
        ) .
        '_' . date("Y-m-d_H-i-s") . '.pdf';

    header("Content-Type: application/pdf");
    header('Content-Disposition: attachment; filename="' . $nomFichier . '"');
    header("Content-Length: " . strlen($pdf));
    header("Cache-Control: private, max-age=0, must-revalidate");

    echo $pdf;
    exit();
}

/* 5. FILTRES */
$type = $_GET["type"] ?? "affectations";
$date_debut = trim($_GET["date_debut"] ?? "");
$date_fin = trim($_GET["date_fin"] ?? "");
$recherche = trim($_GET["recherche"] ?? "");
$statut = $_GET["statut"] ?? "";

$types_valides = [
    "affectations",
    "etudiants",
    "cours",
    "salles",
    "surveillants",
    "examens",
    "connexions",
    "activites"
];

if (!in_array($type, $types_valides, true)) {
    $type = "affectations";
}

/* TITRES DES RAPPORTS */
$titres = [
    "affectations" => "Rapport des affectations",
    "etudiants" => "Rapport des étudiants",
    "cours" => "Rapport des cours",
    "salles" => "Rapport des salles",
    "surveillants" => "Rapport des surveillants",
    "examens" => "Rapport des examens par date",
    "connexions" => "Historique des connexions",
    "activites" => "Journal des activités"
];

$titre = $titres[$type];

$colonnes = [];
$resultats = [];
$erreur = "";

try {
    $sql = "";
    $params = [];

    /* AFFECTATIONS ET EXAMENS */
    if ($type === "affectations" || $type === "examens") {
        $sql = "
            SELECT
                a.id_affectation,
                e.nom_etu,
                e.prenom_etu,
                c.nom_cours,
                s.nom_salle,
                sv.nom_surv,
                sv.prenom_surv,
                a.date_exam,
                a.heure_debut,
                a.heure_fin
            FROM affectation a
            LEFT JOIN etudiant e ON a.id_etu = e.id_etu
            LEFT JOIN cours c ON a.id_cours = c.id_cours
            LEFT JOIN salle s ON a.id_salle = s.id_salle
            LEFT JOIN surveillant sv ON a.id_surv = sv.id_surv
            WHERE 1 = 1
        ";

        if ($date_debut !== "") {
            $sql .= " AND a.date_exam >= :date_debut";
            $params["date_debut"] = $date_debut;
        }

        if ($date_fin !== "") {
            $sql .= " AND a.date_exam <= :date_fin";
            $params["date_fin"] = $date_fin;
        }

        if ($recherche !== "") {
            $sql .= "
                AND (
                    e.nom_etu LIKE :recherche1
                    OR e.prenom_etu LIKE :recherche2
                    OR c.nom_cours LIKE :recherche3
                    OR s.nom_salle LIKE :recherche4
                    OR sv.nom_surv LIKE :recherche5
                )
            ";

            $mot = "%" . $recherche . "%";

            for ($i = 1; $i <= 5; $i++) {
                $params["recherche" . $i] = $mot;
            }
        }

        if ($type === "examens") {
            $sql .= " ORDER BY a.date_exam ASC, a.heure_debut ASC";
        } else {
            $sql .= " ORDER BY a.date_exam DESC, a.heure_debut ASC";
        }

        $colonnes = [
            "ID",
            "Étudiant",
            "Cours",
            "Salle",
            "Surveillant",
            "Date examen",
            "Heure début",
            "Heure fin"
        ];
    }

    /* ÉTUDIANTS */
    elseif ($type === "etudiants") {
        $sql = "
            SELECT
                id_etu,
                nom_etu,
                prenom_etu,
                login,
                id_fac,
                id_niveau,
                premiere_connexion,
                compte_bloque
            FROM etudiant
            WHERE 1 = 1
        ";

        if ($recherche !== "") {
            $sql .= "
                AND (
                    nom_etu LIKE :recherche1
                    OR prenom_etu LIKE :recherche2
                    OR login LIKE :recherche3
                )
            ";

            $mot = "%" . $recherche . "%";

            $params["recherche1"] = $mot;
            $params["recherche2"] = $mot;
            $params["recherche3"] = $mot;
        }

        if ($statut === "bloque") {
            $sql .= " AND compte_bloque = 1";
        } elseif ($statut === "actif") {
            $sql .= " AND (compte_bloque = 0 OR compte_bloque IS NULL)";
        }

        $sql .= " ORDER BY nom_etu ASC, prenom_etu ASC";

        $colonnes = [
            "ID",
            "Nom",
            "Prénom",
            "Login",
            "ID faculté",
            "ID niveau",
            "Première connexion",
            "Compte bloqué"
        ];
    }

    /* COURS */
    elseif ($type === "cours") {
        $sql = "
            SELECT
                id_cours,
                nom_cours,
                credit,
                id_fac,
                id_niveau,
                date_exam,
                heure_debut,
                heure_fin
            FROM cours
            WHERE 1 = 1
        ";

        if ($recherche !== "") {
            $sql .= " AND nom_cours LIKE :recherche";
            $params["recherche"] = "%" . $recherche . "%";
        }

        if ($date_debut !== "") {
            $sql .= " AND date_exam >= :date_debut";
            $params["date_debut"] = $date_debut;
        }

        if ($date_fin !== "") {
            $sql .= " AND date_exam <= :date_fin";
            $params["date_fin"] = $date_fin;
        }

        $sql .= " ORDER BY date_exam ASC, nom_cours ASC";

        $colonnes = [
            "ID",
            "Cours",
            "Crédits",
            "ID faculté",
            "ID niveau",
            "Date examen",
            "Heure début",
            "Heure fin"
        ];
    }

    /* SALLES */
    elseif ($type === "salles") {
        $sql = "
            SELECT
                s.id_salle,
                s.nom_salle,
                s.capacite,
                s.statut,
                sv.nom_surv,
                sv.prenom_surv
            FROM salle s
            LEFT JOIN surveillant sv ON s.id_surv = sv.id_surv
            WHERE 1 = 1
        ";

        if ($recherche !== "") {
            $sql .= "
                AND (
                    s.nom_salle LIKE :recherche1
                    OR sv.nom_surv LIKE :recherche2
                    OR sv.prenom_surv LIKE :recherche3
                )
            ";

            $mot = "%" . $recherche . "%";

            $params["recherche1"] = $mot;
            $params["recherche2"] = $mot;
            $params["recherche3"] = $mot;
        }

        if ($statut === "disponible" || $statut === "occupée") {
            $sql .= " AND s.statut = :statut";
            $params["statut"] = $statut;
        }

        $sql .= " ORDER BY s.nom_salle ASC";

        $colonnes = [
            "ID",
            "Salle",
            "Capacité",
            "Statut",
            "Nom surveillant",
            "Prénom surveillant"
        ];
    }

    /* SURVEILLANTS */
    elseif ($type === "surveillants") {
        $sql = "
            SELECT
                id_surv,
                nom_surv,
                prenom_surv,
                login,
                premiere_connexion,
                compte_bloque
            FROM surveillant
            WHERE 1 = 1
        ";

        if ($recherche !== "") {
            $sql .= "
                AND (
                    nom_surv LIKE :recherche1
                    OR prenom_surv LIKE :recherche2
                    OR login LIKE :recherche3
                )
            ";

            $mot = "%" . $recherche . "%";

            $params["recherche1"] = $mot;
            $params["recherche2"] = $mot;
            $params["recherche3"] = $mot;
        }

        if ($statut === "bloque") {
            $sql .= " AND compte_bloque = 1";
        } elseif ($statut === "actif") {
            $sql .= " AND (compte_bloque = 0 OR compte_bloque IS NULL)";
        }

        $sql .= " ORDER BY nom_surv ASC, prenom_surv ASC";

        $colonnes = [
            "ID",
            "Nom",
            "Prénom",
            "Login",
            "Première connexion",
            "Compte bloqué"
        ];
    }

    /* HISTORIQUE DES CONNEXIONS */
    elseif ($type === "connexions") {
        $sql = "
            SELECT
                id,
                user_id,
                nom_utilisateur,
                type_user,
                adresse_ip,
                statut,
                date_connexion,
                date_deconnexion,
                duree_connexion
            FROM connexion_log
            WHERE 1 = 1
        ";

        if ($recherche !== "") {
            $sql .= "
                AND (
                    nom_utilisateur LIKE :recherche1
                    OR type_user LIKE :recherche2
                    OR statut LIKE :recherche3
                    OR adresse_ip LIKE :recherche4
                )
            ";

            $mot = "%" . $recherche . "%";

            for ($i = 1; $i <= 4; $i++) {
                $params["recherche" . $i] = $mot;
            }
        }

        if ($date_debut !== "") {
            $sql .= " AND DATE(date_connexion) >= :date_debut";
            $params["date_debut"] = $date_debut;
        }

        if ($date_fin !== "") {
            $sql .= " AND DATE(date_connexion) <= :date_fin";
            $params["date_fin"] = $date_fin;
        }

        if ($statut !== "") {
            $sql .= " AND statut = :statut";
            $params["statut"] = $statut;
        }

        $sql .= " ORDER BY date_connexion DESC";

        $colonnes = [
            "ID",
            "ID utilisateur",
            "Nom utilisateur",
            "Type",
            "Adresse IP",
            "Statut",
            "Connexion",
            "Déconnexion",
            "Durée"
        ];
    }

    /* JOURNAL DES ACTIVITÉS */
    elseif ($type === "activites") {
        $sql = "
            SELECT
                id,
                user_id,
                nom_utilisateur,
                type_user,
                action,
                date_action
            FROM journal_activite
            WHERE 1 = 1
        ";

        if ($recherche !== "") {
            $sql .= "
                AND (
                    nom_utilisateur LIKE :recherche1
                    OR type_user LIKE :recherche2
                    OR action LIKE :recherche3
                )
            ";

            $mot = "%" . $recherche . "%";

            $params["recherche1"] = $mot;
            $params["recherche2"] = $mot;
            $params["recherche3"] = $mot;
        }

        if ($date_debut !== "") {
            $sql .= " AND DATE(date_action) >= :date_debut";
            $params["date_debut"] = $date_debut;
        }

        if ($date_fin !== "") {
            $sql .= " AND DATE(date_action) <= :date_fin";
            $params["date_fin"] = $date_fin;
        }

        $sql .= " ORDER BY date_action DESC";

        $colonnes = [
            "ID",
            "ID utilisateur",
            "Nom utilisateur",
            "Type",
            "Action effectuée",
            "Date de l'action"
        ];
    }

    $requete = $bdd->prepare($sql);
    $requete->execute($params);
    $resultats = $requete->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $erreur = "Impossible de générer le rapport. Vérifie la structure de la base de données.";
    error_log($e->getMessage());
}

/* 6. PRÉPARATION DU PDF */
if (
    isset($_GET["export"]) &&
    $_GET["export"] === "pdf" &&
    $erreur === ""
) {
    $lignesPDF = [];

    foreach ($resultats as $ligne) {
        if ($type === "affectations" || $type === "examens") {
            $lignesPDF[] = [
                $ligne["id_affectation"] ?? "",
                trim(
                    ($ligne["prenom_etu"] ?? "") . " " .
                    ($ligne["nom_etu"] ?? "")
                ) ?: "Non attribué",
                $ligne["nom_cours"] ?? "Non attribué",
                $ligne["nom_salle"] ?? "Non attribuée",
                trim(
                    ($ligne["prenom_surv"] ?? "") . " " .
                    ($ligne["nom_surv"] ?? "")
                ) ?: "Non attribué",
                dateFr($ligne["date_exam"] ?? ""),
                heureFr($ligne["heure_debut"] ?? ""),
                heureFr($ligne["heure_fin"] ?? "")
            ];
        } elseif ($type === "etudiants") {
            $lignesPDF[] = [
                $ligne["id_etu"] ?? "",
                $ligne["nom_etu"] ?? "",
                $ligne["prenom_etu"] ?? "",
                $ligne["login"] ?? "",
                $ligne["id_fac"] ?? "",
                $ligne["id_niveau"] ?? "",
                ouiNon($ligne["premiere_connexion"] ?? 0),
                ouiNon($ligne["compte_bloque"] ?? 0)
            ];
        } elseif ($type === "cours") {
            $lignesPDF[] = [
                $ligne["id_cours"] ?? "",
                $ligne["nom_cours"] ?? "",
                $ligne["credit"] ?? "",
                $ligne["id_fac"] ?? "",
                $ligne["id_niveau"] ?? "",
                dateFr($ligne["date_exam"] ?? ""),
                heureFr($ligne["heure_debut"] ?? ""),
                heureFr($ligne["heure_fin"] ?? "")
            ];
        } elseif ($type === "salles") {
            $lignesPDF[] = [
                $ligne["id_salle"] ?? "",
                $ligne["nom_salle"] ?? "",
                $ligne["capacite"] ?? "",
                $ligne["statut"] ?? "",
                $ligne["nom_surv"] ?? "Aucun",
                $ligne["prenom_surv"] ?? ""
            ];
        } elseif ($type === "surveillants") {
            $lignesPDF[] = [
                $ligne["id_surv"] ?? "",
                $ligne["nom_surv"] ?? "",
                $ligne["prenom_surv"] ?? "",
                $ligne["login"] ?? "",
                ouiNon($ligne["premiere_connexion"] ?? 0),
                ouiNon($ligne["compte_bloque"] ?? 0)
            ];
        } elseif ($type === "connexions") {
            $lignesPDF[] = [
                $ligne["id"] ?? "",
                $ligne["user_id"] ?? "",
                $ligne["nom_utilisateur"] ?? "",
                $ligne["type_user"] ?? "",
                $ligne["adresse_ip"] ?? "",
                $ligne["statut"] ?? "",
                $ligne["date_connexion"] ?? "",
                $ligne["date_deconnexion"] ?? "",
                $ligne["duree_connexion"] ?? ""
            ];
        } elseif ($type === "activites") {
            $lignesPDF[] = [
                $ligne["id"] ?? "",
                $ligne["user_id"] ?? "",
                $ligne["nom_utilisateur"] ?? "",
                $ligne["type_user"] ?? "",
                $ligne["action"] ?? "",
                $ligne["date_action"] ?? ""
            ];
        }
    }

    genererPDFPersonnalise($titre, $colonnes, $lignesPDF);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= echapper($titre) ?></title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 25px;
            font-family: Arial, sans-serif;
            background: #f3f6fa;
            color: #243447;
        }

        .conteneur {
            max-width: 1400px;
            margin: 0 auto;
            background: #fff;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.08);
        }

        h1 {
            color: #003366;
            margin-top: 0;
            margin-bottom: 8px;
        }

        .description {
            color: #64748b;
            margin-bottom: 25px;
        }

        .filtres {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            padding: 20px;
            margin-bottom: 25px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
        }

        .champ {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        label {
            font-weight: bold;
            font-size: 14px;
        }

        input,
        select {
            width: 100%;
            padding: 11px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            background: white;
            font-size: 14px;
        }

        .boutons {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: end;
        }

        button,
        .bouton {
            display: inline-block;
            padding: 11px 17px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            font-weight: bold;
            font-size: 14px;
        }

        .generer {
            background: #003366;
            color: white;
        }

        .generer:hover {
            background: #004b8d;
        }

        .reinitialiser {
            background: #e2e8f0;
            color: #243447;
        }

        .imprimer {
            background: #b8860b;
            color: white;
        }

        .imprimer:hover {
            background: #956d08;
        }

        .resume {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin: 20px 0;
        }

        .compteur {
            background: #e8f1fb;
            color: #003366;
            padding: 12px 16px;
            border-radius: 8px;
            font-weight: bold;
        }

        .table-conteneur {
            overflow-x: auto;
            width: 100%;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 750px;
        }

        th,
        td {
            border: 1px solid #e2e8f0;
            padding: 11px;
            text-align: left;
            font-size: 13px;
        }

        th {
            background: #003366;
            color: white;
            white-space: nowrap;
        }

        tbody tr:nth-child(even) {
            background: #f8fafc;
        }

        tbody tr:hover {
            background: #edf4fc;
        }

        .aucun {
            text-align: center;
            padding: 30px;
            color: #64748b;
        }

        .erreur {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 6px;
            background: #fee2e2;
            color: #991b1b;
        }

        @media (max-width: 700px) {
            body {
                padding: 10px;
            }

            .conteneur {
                padding: 15px;
            }

            .boutons {
                flex-direction: column;
                align-items: stretch;
            }

            .bouton,
            button {
                text-align: center;
            }
        }
    </style>
</head>

<body>

<?php include_once("menu_principal.php"); ?>

<div class="conteneur">

    <h1><?= echapper($titre) ?></h1>

    <p class="description">
        Sélectionne un rapport, définis les filtres et consulte les résultats.
    </p>

    <?php if ($erreur !== ""): ?>
        <div class="erreur">
            <?= echapper($erreur) ?>
        </div>
    <?php endif; ?>

    <form method="GET" action="rapports_personnalises.php" class="filtres">

        <div class="champ">
            <label for="type">Type de rapport</label>

            <select name="type" id="type">
                <?php foreach ($titres as $valeur => $libelle): ?>
                    <option
                        value="<?= echapper($valeur) ?>"
                        <?= $type === $valeur ? "selected" : "" ?>
                    >
                        <?= echapper($libelle) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="champ">
            <label for="recherche">Recherche</label>

            <input
                type="text"
                id="recherche"
                name="recherche"
                value="<?= echapper($recherche) ?>"
                placeholder="Nom, cours, salle..."
            >
        </div>

        <?php if (
            in_array(
                $type,
                ["affectations", "cours", "examens", "connexions", "activites"],
                true
            )
        ): ?>

            <div class="champ">
                <label for="date_debut">Date de début</label>

                <input
                    type="date"
                    id="date_debut"
                    name="date_debut"
                    value="<?= echapper($date_debut) ?>"
                >
            </div>

            <div class="champ">
                <label for="date_fin">Date de fin</label>

                <input
                    type="date"
                    id="date_fin"
                    name="date_fin"
                    value="<?= echapper($date_fin) ?>"
                >
            </div>

        <?php endif; ?>

        <?php if ($type === "salles"): ?>

            <div class="champ">
                <label for="statut">Statut de la salle</label>

                <select name="statut" id="statut">
                    <option value="">Tous les statuts</option>

                    <option
                        value="disponible"
                        <?= $statut === "disponible" ? "selected" : "" ?>
                    >
                        Disponible
                    </option>

                    <option
                        value="occupée"
                        <?= $statut === "occupée" ? "selected" : "" ?>
                    >
                        Occupée
                    </option>
                </select>
            </div>

        <?php elseif ($type === "etudiants" || $type === "surveillants"): ?>

            <div class="champ">
                <label for="statut">État du compte</label>

                <select name="statut" id="statut">
                    <option value="">Tous les comptes</option>

                    <option
                        value="actif"
                        <?= $statut === "actif" ? "selected" : "" ?>
                    >
                        Non bloqué
                    </option>

                    <option
                        value="bloque"
                        <?= $statut === "bloque" ? "selected" : "" ?>
                    >
                        Bloqué
                    </option>
                </select>
            </div>

        <?php elseif ($type === "connexions"): ?>

            <div class="champ">
                <label for="statut">Statut de connexion</label>

                <select name="statut" id="statut">
                    <option value="">Tous les statuts</option>

                    <option
                        value="réussie"
                        <?= $statut === "réussie" ? "selected" : "" ?>
                    >
                        Réussie
                    </option>

                    <option
                        value="echouee"
                        <?= $statut === "echouee" ? "selected" : "" ?>
                    >
                        Échouée
                    </option>

                    <option
                        value="echec"
                        <?= $statut === "echec" ? "selected" : "" ?>
                    >
                        Échec
                    </option>
                </select>
            </div>

        <?php endif; ?>

        <div class="boutons">
            <button type="submit" class="generer">
                Générer le rapport
            </button>

            <a
                href="rapports_personnalises.php"
                class="bouton reinitialiser"
            >
                Réinitialiser
            </a>
        </div>

    </form>

    <div class="resume">
        <h2><?= echapper($titre) ?></h2>

        <div class="compteur">
            <?= count($resultats) ?> résultat(s)
        </div>
    </div>

    <div class="boutons" style="margin-bottom:20px;">

        <a
            class="bouton imprimer"
            href="rapports_personnalises.php?<?= echapper(
                http_build_query(
                    array_merge($_GET, ["export" => "pdf"])
                )
            ) ?>"
        >
            Télécharger le PDF
        </a>

    </div>

    <div class="table-conteneur">

        <table>
            <thead>
                <tr>
                    <?php foreach ($colonnes as $colonne): ?>
                        <th><?= echapper($colonne) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>

            <tbody>

                <?php if (empty($resultats)): ?>

                    <tr>
                        <td
                            colspan="<?= max(1, count($colonnes)) ?>"
                            class="aucun"
                        >
                            Aucun résultat trouvé pour ces critères.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($resultats as $ligne): ?>

                        <tr>

                            <?php if ($type === "affectations" || $type === "examens"): ?>

                                <td>
                                    <?= echapper($ligne["id_affectation"] ?? "") ?>
                                </td>

                                <td>
                                    <?= echapper(
                                        trim(
                                            ($ligne["prenom_etu"] ?? "") . " " .
                                            ($ligne["nom_etu"] ?? "")
                                        ) ?: "Non attribué"
                                    ) ?>
                                </td>

                                <td>
                                    <?= echapper($ligne["nom_cours"] ?? "Non attribué") ?>
                                </td>

                                <td>
                                    <?= echapper($ligne["nom_salle"] ?? "Non attribuée") ?>
                                </td>

                                <td>
                                    <?= echapper(
                                        trim(
                                            ($ligne["prenom_surv"] ?? "") . " " .
                                            ($ligne["nom_surv"] ?? "")
                                        ) ?: "Non attribué"
                                    ) ?>
                                </td>

                                <td>
                                    <?= echapper(dateFr($ligne["date_exam"] ?? "")) ?>
                                </td>

                                <td>
                                    <?= echapper(heureFr($ligne["heure_debut"] ?? "")) ?>
                                </td>

                                <td>
                                    <?= echapper(heureFr($ligne["heure_fin"] ?? "")) ?>
                                </td>

                            <?php elseif ($type === "etudiants"): ?>

                                <td><?= echapper($ligne["id_etu"] ?? "") ?></td>
                                <td><?= echapper($ligne["nom_etu"] ?? "") ?></td>
                                <td><?= echapper($ligne["prenom_etu"] ?? "") ?></td>
                                <td><?= echapper($ligne["login"] ?? "") ?></td>
                                <td><?= echapper($ligne["id_fac"] ?? "") ?></td>
                                <td><?= echapper($ligne["id_niveau"] ?? "") ?></td>
                                <td><?= ouiNon($ligne["premiere_connexion"] ?? 0) ?></td>
                                <td><?= ouiNon($ligne["compte_bloque"] ?? 0) ?></td>

                            <?php elseif ($type === "cours"): ?>

                                <td><?= echapper($ligne["id_cours"] ?? "") ?></td>
                                <td><?= echapper($ligne["nom_cours"] ?? "") ?></td>
                                <td><?= echapper($ligne["credit"] ?? "") ?></td>
                                <td><?= echapper($ligne["id_fac"] ?? "") ?></td>
                                <td><?= echapper($ligne["id_niveau"] ?? "") ?></td>
                                <td><?= echapper(dateFr($ligne["date_exam"] ?? "")) ?></td>
                                <td><?= echapper(heureFr($ligne["heure_debut"] ?? "")) ?></td>
                                <td><?= echapper(heureFr($ligne["heure_fin"] ?? "")) ?></td>

                            <?php elseif ($type === "salles"): ?>

                                <td><?= echapper($ligne["id_salle"] ?? "") ?></td>
                                <td><?= echapper($ligne["nom_salle"] ?? "") ?></td>
                                <td><?= echapper($ligne["capacite"] ?? "") ?></td>
                                <td><?= echapper($ligne["statut"] ?? "") ?></td>
                                <td><?= echapper($ligne["nom_surv"] ?? "Aucun") ?></td>
                                <td><?= echapper($ligne["prenom_surv"] ?? "") ?></td>

                            <?php elseif ($type === "surveillants"): ?>

                                <td><?= echapper($ligne["id_surv"] ?? "") ?></td>
                                <td><?= echapper($ligne["nom_surv"] ?? "") ?></td>
                                <td><?= echapper($ligne["prenom_surv"] ?? "") ?></td>
                                <td><?= echapper($ligne["login"] ?? "") ?></td>
                                <td><?= ouiNon($ligne["premiere_connexion"] ?? 0) ?></td>
                                <td><?= ouiNon($ligne["compte_bloque"] ?? 0) ?></td>

                            <?php elseif ($type === "connexions"): ?>

                                <td><?= echapper($ligne["id"] ?? "") ?></td>
                                <td><?= echapper($ligne["user_id"] ?? "") ?></td>
                                <td><?= echapper($ligne["nom_utilisateur"] ?? "") ?></td>
                                <td><?= echapper($ligne["type_user"] ?? "") ?></td>
                                <td><?= echapper($ligne["adresse_ip"] ?? "") ?></td>
                                <td><?= echapper($ligne["statut"] ?? "") ?></td>
                                <td><?= echapper($ligne["date_connexion"] ?? "") ?></td>
                                <td><?= echapper($ligne["date_deconnexion"] ?? "") ?></td>
                                <td><?= echapper($ligne["duree_connexion"] ?? "") ?></td>

                            <?php elseif ($type === "activites"): ?>

                                <td><?= echapper($ligne["id"] ?? "") ?></td>
                                <td><?= echapper($ligne["user_id"] ?? "") ?></td>
                                <td><?= echapper($ligne["nom_utilisateur"] ?? "") ?></td>
                                <td><?= echapper($ligne["type_user"] ?? "") ?></td>
                                <td><?= echapper($ligne["action"] ?? "") ?></td>
                                <td><?= echapper($ligne["date_action"] ?? "") ?></td>

                            <?php endif; ?>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

            </tbody>
        </table>

    </div>
</div>

</body>
</html>