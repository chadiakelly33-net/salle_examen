<?php
session_start();

require_once __DIR__ . "/connexionDB.php";

// =====================================================
// 1. SECURITE ET SESSION
// =====================================================

$timeout = 300;

if (isset($_SESSION["last_activity"]) &&
    time() - $_SESSION["last_activity"] > $timeout) {

    session_unset();
    session_destroy();

    header("Location: login_surv.php?timeout=1");
    exit();
}

if (
    !isset($_SESSION["id_surv"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "surveillant"
) {
    header("Location: login_surv.php");
    exit();
}

$_SESSION["last_activity"] = time();

$id_surv = (int) $_SESSION["id_surv"];

// =====================================================
// 2. FONCTIONS UTILITAIRES
// =====================================================

function h($valeur)
{
    return htmlspecialchars(
        (string) ($valeur ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

function dateFr($date)
{
    if (empty($date)) {
        return "Non définie";
    }

    $timestamp = strtotime($date);

    return $timestamp ? date("d/m/Y", $timestamp) : "Non définie";
}

function heureFr($heure)
{
    return !empty($heure) ? substr($heure, 0, 5) : "--:--";
}

// =====================================================
// 3. FILTRES
// =====================================================

$date_debut = trim($_GET["date_debut"] ?? "");
$date_fin   = trim($_GET["date_fin"] ?? "");
$id_salle   = trim($_GET["id_salle"] ?? "");

$erreur = "";

if (
    ($date_debut !== "" &&
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_debut)) ||
    ($date_fin !== "" &&
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_fin))
) {
    $erreur = "Veuillez sélectionner des dates valides.";
    $date_debut = "";
    $date_fin = "";
}

if (
    $date_debut !== "" &&
    $date_fin !== "" &&
    $date_debut > $date_fin
) {
    $erreur = "La date de début doit précéder la date de fin.";
    $date_debut = "";
    $date_fin = "";
}

if ($id_salle !== "" &&
    (!ctype_digit($id_salle) || (int)$id_salle <= 0)) {
    $erreur = "La salle sélectionnée est invalide.";
    $id_salle = "";
}

// =====================================================
// 4. INFORMATIONS DU SURVEILLANT
// =====================================================

$stmtNom = $bdd->prepare("
    SELECT nom_surv, prenom_surv
    FROM surveillant
    WHERE id_surv = ?
");

$stmtNom->execute([$id_surv]);
$surveillant = $stmtNom->fetch(PDO::FETCH_ASSOC);

if (!$surveillant) {
    session_unset();
    session_destroy();

    header("Location: login_surv.php");
    exit();
}

$nomSurveillant = trim(
    ($surveillant["nom_surv"] ?? "") . " " .
    ($surveillant["prenom_surv"] ?? "")
);

// =====================================================
// 5. SALLES DU SURVEILLANT
// =====================================================

$stmtSalles = $bdd->prepare("
    SELECT DISTINCT s.id_salle, s.nom_salle
    FROM affectation a
    INNER JOIN salle s ON a.id_salle = s.id_salle
    WHERE a.id_surv = ?
    ORDER BY s.nom_salle ASC
");

$stmtSalles->execute([$id_surv]);
$salles = $stmtSalles->fetchAll(PDO::FETCH_ASSOC);

// Vérifier que la salle demandée appartient bien
// aux salles associées aux affectations du surveillant.

if ($id_salle !== "") {
    $salleAutorisee = false;

    foreach ($salles as $salle) {
        if ((string)$salle["id_salle"] === $id_salle) {
            $salleAutorisee = true;
            break;
        }
    }

    if (!$salleAutorisee) {
        $erreur = "Cette salle ne correspond pas à vos affectations.";
        $id_salle = "";
    }
}

// =====================================================
// 6. RECUPERATION DES AFFECTATIONS
// =====================================================

$sql = "
    SELECT
        a.id_affectation,
        a.date_exam,
        a.heure_debut,
        a.heure_fin,

        e.nom_etu,
        e.prenom_etu,

        c.nom_cours,
        f.nom_fac,
        n.nom_niveau,

        s.id_salle,
        s.nom_salle

    FROM affectation a

    INNER JOIN cours c
        ON a.id_cours = c.id_cours

    INNER JOIN salle s
        ON a.id_salle = s.id_salle

    LEFT JOIN etudiant e
        ON a.id_etu = e.id_etu

    LEFT JOIN faculte f
        ON c.id_fac = f.id_fac

    LEFT JOIN niveau_etude n
        ON c.id_niveau = n.id_niveau

    WHERE a.id_surv = :id_surv
";

$params = [
    ":id_surv" => $id_surv
];

if ($date_debut !== "") {
    $sql .= " AND a.date_exam >= :date_debut";
    $params[":date_debut"] = $date_debut;
}

if ($date_fin !== "") {
    $sql .= " AND a.date_exam <= :date_fin";
    $params[":date_fin"] = $date_fin;
}

if ($id_salle !== "") {
    $sql .= " AND a.id_salle = :id_salle";
    $params[":id_salle"] = (int)$id_salle;
}

$sql .= "
    ORDER BY
        a.date_exam DESC,
        a.heure_debut ASC,
        a.id_affectation DESC
";

$stmt = $bdd->prepare($sql);
$stmt->execute($params);

$affectations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// =====================================================
// 7. STATISTIQUES
// =====================================================

$total = count($affectations);

$sallesUtilisees = [];

foreach ($affectations as $affectation) {
    if (!empty($affectation["id_salle"])) {
        $sallesUtilisees[$affectation["id_salle"]] =
            $affectation["nom_salle"];
    }
}

$totalSalles = count($sallesUtilisees);

$periode = "Toutes les dates";

if ($date_debut !== "" && $date_fin !== "") {
    $periode = dateFr($date_debut) . " au " . dateFr($date_fin);
} elseif ($date_debut !== "") {
    $periode = "À partir du " . dateFr($date_debut);
} elseif ($date_fin !== "") {
    $periode = "Jusqu'au " . dateFr($date_fin);
}

// =====================================================
// 8. GENERATION DU PDF
// PDF A4 paysage, sans bibliothèque externe
// =====================================================

if (isset($_GET["pdf"]) && $_GET["pdf"] === "1") {

    // Les caractères accentués sont convertis pour les
    // polices PDF standard de base.

    function pdfTexte($texte)
    {
        $texte = (string)$texte;

        if (function_exists("iconv")) {
            $converti = @iconv(
                "UTF-8",
                "Windows-1252//TRANSLIT",
                $texte
            );

            if ($converti !== false) {
                $texte = $converti;
            }
        }

        // Échappement des caractères spéciaux PDF.
        $texte = str_replace("\\", "\\\\", $texte);
        $texte = str_replace("(", "\\(", $texte);
        $texte = str_replace(")", "\\)", $texte);

        // Éviter les caractères de contrôle dans le PDF.
        $texte = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $texte);

        return $texte;
    }

    function pdfCouleur($r, $g, $b)
    {
        return sprintf(
            "%.3f %.3f %.3f",
            $r / 255,
            $g / 255,
            $b / 255
        );
    }

    function pdfTextePosition($x, $y, $texte, $taille = 9, $gras = false, $couleur = null)
    {
        $police = $gras ? "F2" : "F1";

        if ($couleur === null) {
            $couleur = pdfCouleur(40, 50, 65);
        }

        return "BT\n" .
            "/" . $police . " " . $taille . " Tf\n" .
            $couleur . " rg\n" .
            $x . " " . $y . " Td\n" .
            "(" . pdfTexte($texte) . ") Tj\n" .
            "ET\n";
    }

    function pdfRectangle($x, $y, $largeur, $hauteur, $couleur)
    {
        return $couleur . " rg\n" .
            $x . " " . $y . " " .
            $largeur . " " . $hauteur . " re f\n";
    }

    function pdfLigne($x1, $y1, $x2, $y2, $couleur, $epaisseur = 0.5)
    {
        return $couleur . " RG\n" .
            $epaisseur . " w\n" .
            $x1 . " " . $y1 . " m\n" .
            $x2 . " " . $y2 . " l S\n";
    }

    function pdfNettoyerCellule($texte, $max = 30)
    {
        $texte = trim((string)$texte);

        if (function_exists("iconv")) {
            $temp = @iconv(
                "UTF-8",
                "ASCII//TRANSLIT//IGNORE",
                $texte
            );

            if ($temp !== false) {
                $texte = $temp;
            }
        }

        $texte = preg_replace('/\s+/', ' ', $texte);

        if (strlen($texte) > $max) {
            $texte = substr($texte, 0, $max - 3) . "...";
        }

        return $texte;
    }

    // A4 paysage : 842 x 595 points.
    $largeurPage = 842;
    $hauteurPage = 595;

    $bleuNuit = pdfCouleur(0, 51, 102);
    $bleuClair = pdfCouleur(235, 243, 250);
    $or = pdfCouleur(204, 164, 74);
    $gris = pdfCouleur(100, 116, 139);
    $noir = pdfCouleur(35, 45, 60);
    $blanc = pdfCouleur(255, 255, 255);
    $ligne = pdfCouleur(220, 228, 236);

    $commandesPages = [];
    $page = "";
    $numeroPage = 0;

    // Largeurs des colonnes.
    $colonnes = [
        ["titre" => "N°",        "largeur" => 30],
        ["titre" => "DATE",      "largeur" => 62],
        ["titre" => "HORAIRE",   "largeur" => 76],
        ["titre" => "ETUDIANT",  "largeur" => 115],
        ["titre" => "COURS",     "largeur" => 115],
        ["titre" => "FACULTE",   "largeur" => 105],
        ["titre" => "NIVEAU",    "largeur" => 72],
        ["titre" => "SALLE",     "largeur" => 75],
    ];

    $margeGauche = 30;
    $largeurTable = 650;
    $debutTable = $margeGauche;

    function pdfNouvellePage(
        &$commandesPages,
        &$page,
        &$numeroPage,
        $largeurPage,
        $hauteurPage,
        $nomSurveillant,
        $periode,
        $dateGeneration,
        $total,
        $totalSalles,
        $colonnes,
        $debutTable,
        $largeurTable,
        $bleuNuit,
        $bleuClair,
        $or,
        $gris,
        $noir,
        $blanc,
        $ligne,
        $premierePage = false
    ) {
        if ($numeroPage > 0) {
            $commandesPages[] = $page;
        }

        $numeroPage++;
        $page = "";

        // Fond blanc.
        $page .= pdfRectangle(
            0, 0,
            $largeurPage,
            $hauteurPage,
            $blanc
        );

        // Bandeau supérieur.
        $page .= pdfRectangle(
            0, 490,
            $largeurPage,
            105,
            $bleuNuit
        );

        $page .= pdfRectangle(
            0, 486,
            $largeurPage,
            4,
            $or
        );

        $page .= pdfTextePosition(
            30, 548,
            "GESTION DES SALLES D'EXAMEN",
            10, true, $or
        );

        $page .= pdfTextePosition(
            30, 520,
            "RAPPORT DES AFFECTATIONS",
            21, true, $blanc
        );

        $page .= pdfTextePosition(
            30, 502,
            "SURVEILLANT : " . $nomSurveillant,
            10, false, $blanc
        );

        $page .= pdfTextePosition(
            650, 548,
            "RAPPORT PERSONNEL",
            9, true, $blanc
        );

        $page .= pdfTextePosition(
            650, 529,
            "Genere le : " . $dateGeneration,
            9, false, $blanc
        );

        $page .= pdfTextePosition(
            650, 510,
            "Page " . $numeroPage,
            9, false, $blanc
        );

        if ($premierePage) {
            // Cartes statistiques.
            $page .= pdfRectangle(
                30, 420,
                240, 45,
                $bleuClair
            );

            $page .= pdfRectangle(
                30, 420,
                4, 45,
                $bleuNuit
            );

            $page .= pdfTextePosition(
                45, 446,
                "TOTAL DES AFFECTATIONS",
                9, true, $gris
            );

            $page .= pdfTextePosition(
                45, 426,
                (string)$total,
                17, true, $bleuNuit
            );

            $page .= pdfRectangle(
                285, 420,
                240, 45,
                $bleuClair
            );

            $page .= pdfRectangle(
                285, 420,
                4, 45,
                $or
            );

            $page .= pdfTextePosition(
                300, 446,
                "NOMBRE DE SALLES",
                9, true, $gris
            );

            $page .= pdfTextePosition(
                300, 426,
                (string)$totalSalles,
                17, true, $bleuNuit
            );

            $page .= pdfTextePosition(
                30, 398,
                "Periode : " . $periode,
                9, true, $noir
            );

            $yEntete = 365;
        } else {
            $page .= pdfTextePosition(
                30, 465,
                "Suite du rapport des affectations",
                10, true, $bleuNuit
            );

            $yEntete = 440;
        }

        // En-tête du tableau.
        $page .= pdfRectangle(
            $debutTable,
            $yEntete - 8,
            $largeurTable,
            23,
            $bleuNuit
        );

        $x = $debutTable;

        foreach ($colonnes as $colonne) {
            $page .= pdfTextePosition(
                $x + 5,
                $yEntete,
                $colonne["titre"],
                7,
                true,
                $blanc
            );

            $x += $colonne["largeur"];
        }

        return $yEntete - 8;
    }

    $dateGeneration = date("d/m/Y H:i");

    $y = pdfNouvellePage(
        $commandesPages,
        $page,
        $numeroPage,
        $largeurPage,
        $hauteurPage,
        $nomSurveillant,
        $periode,
        $dateGeneration,
        $total,
        $totalSalles,
        $colonnes,
        $debutTable,
        $largeurTable,
        $bleuNuit,
        $bleuClair,
        $or,
        $gris,
        $noir,
        $blanc,
        $ligne,
        true
    );

    if (empty($affectations)) {
        $page .= pdfTextePosition(
            45,
            $y - 35,
            "Aucune affectation ne correspond aux filtres selectionnes.",
            11,
            false,
            $gris
        );
    } else {
        foreach ($affectations as $index => $a) {

            // Une nouvelle page si l'espace restant est insuffisant.
            if ($y < 55) {
                $y = pdfNouvellePage(
                    $commandesPages,
                    $page,
                    $numeroPage,
                    $largeurPage,
                    $hauteurPage,
                    $nomSurveillant,
                    $periode,
                    $dateGeneration,
                    $total,
                    $totalSalles,
                    $colonnes,
                    $debutTable,
                    $largeurTable,
                    $bleuNuit,
                    $bleuClair,
                    $or,
                    $gris,
                    $noir,
                    $blanc,
                    $ligne,
                    false
                );
            }

            $y -= 20;

            if ($index % 2 === 0) {
                $page .= pdfRectangle(
                    $debutTable,
                    $y - 5,
                    $largeurTable,
                    20,
                    $bleuClair
                );
            }

            $cellules = [
                (string)($index + 1),
                dateFr($a["date_exam"]),
                heureFr($a["heure_debut"]) . "-" . heureFr($a["heure_fin"]),
                trim(($a["nom_etu"] ?? "") . " " . ($a["prenom_etu"] ?? "")) ?: "Non attribue",
                $a["nom_cours"] ?? "Non defini",
                $a["nom_fac"] ?? "Non renseignee",
                $a["nom_niveau"] ?? "Non renseigne",
                $a["nom_salle"] ?? "Non attribuee"
            ];

            $x = $debutTable;

            foreach ($colonnes as $i => $colonne) {
                $max = max(
                    5,
                    (int)floor(($colonne["largeur"] - 8) / 4.5)
                );

                $texteCellule = pdfNettoyerCellule(
                    $cellules[$i],
                    $max
                );

                $page .= pdfTextePosition(
                    $x + 5,
                    $y + 2,
                    $texteCellule,
                    7,
                    false,
                    $noir
                );

                $x += $colonne["largeur"];
            }

            $page .= pdfLigne(
                $debutTable,
                $y - 5,
                $debutTable + $largeurTable,
                $y - 5,
                $ligne,
                0.4
            );
        }
    }

    // Pied de page.
    $page .= pdfLigne(
        30, 35,
        812, 35,
        $ligne,
        0.7
    );

    $page .= pdfTextePosition(
        30, 20,
        "Document confidentiel - Rapport personnel du surveillant",
        8, false, $gris
    );

    $page .= pdfTextePosition(
        660, 20,
        "Gestion des salles d'examen",
        8, false, $gris
    );

    $commandesPages[] = $page;

    // =================================================
    // CONSTRUCTION DU FICHIER PDF
    // =================================================

    $objets = [];

    $objets[1] = "<< /Type /Catalog /Pages 2 0 R >>";

    $objets[3] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
    $objets[4] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>";

    $pagesReferences = [];

    $numeroObjet = 5;

    foreach ($commandesPages as $contenuPage) {
        $objetPage = $numeroObjet++;
        $objetContenu = $numeroObjet++;

        $pagesReferences[] = $objetPage . " 0 R";

        $objets[$objetPage] =
            "<< /Type /Page /Parent 2 0 R " .
            "/MediaBox [0 0 842 595] " .
            "/Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> " .
            "/Contents " . $objetContenu . " 0 R >>";

        $longueur = strlen($contenuPage);

        $objets[$objetContenu] =
            "<< /Length " . $longueur . " >>\n" .
            "stream\n" .
            $contenuPage .
            "\nendstream";
    }

    $objets[2] =
        "<< /Type /Pages /Kids [" .
        implode(" ", $pagesReferences) .
        "] /Count " . count($pagesReferences) . " >>";

    ksort($objets);

    $pdf = "%PDF-1.4\n";
    $pdf .= "%\xE2\xE3\xCF\xD3\n";

    $offsets = [0];
    $maxObjet = max(array_keys($objets));

    for ($i = 1; $i <= $maxObjet; $i++) {
        $offsets[$i] = strlen($pdf);

        $pdf .= $i . " 0 obj\n";
        $pdf .= $objets[$i] . "\n";
        $pdf .= "endobj\n";
    }

    $debutXref = strlen($pdf);

    $pdf .= "xref\n";
    $pdf .= "0 " . ($maxObjet + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";

    for ($i = 1; $i <= $maxObjet; $i++) {
        $pdf .= sprintf(
            "%010d 00000 n \n",
            $offsets[$i]
        );
    }

    $pdf .= "trailer\n";
    $pdf .= "<< /Size " . ($maxObjet + 1) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n";
    $pdf .= $debutXref . "\n";
    $pdf .= "%%EOF";

    // Nom du fichier téléchargé.
    $nomFichier = "rapport_surveillance_" . date("Y-m-d_H-i-s") . ".pdf";

    // Supprimer tout tampon de sortie pour éviter de corrompre le PDF.
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header("Content-Type: application/pdf");
    header('Content-Disposition: attachment; filename="' . $nomFichier . '"');
    header("Content-Length: " . strlen($pdf));
    header("Cache-Control: private, max-age=0, must-revalidate");
    header("Pragma: public");

    echo $pdf;
    exit();
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Mes rapports d'examens</title>

<style>
/* =====================================================
   1. CONFIGURATION GENERALE
===================================================== */

* {
    box-sizing: border-box;
}

html {
    min-height: 100%;
}

body {
    margin: 0;
    padding: 0;
    font-family: Arial, sans-serif;
    background: #f1f5f9;
    color: #1e293b;
    overflow-x: hidden;
}

/* =====================================================
   2. MENU LATERAL
===================================================== */

.sidebar {
    width: 230px;
    height: 100vh;
    position: fixed;
    top: 0;
    left: 0;
    z-index: 1000;
    background: #003366;
    padding-top: 20px;
    overflow-y: auto;
    overflow-x: hidden;
    box-shadow: 3px 0 12px rgba(0, 0, 0, 0.12);
}

.sidebar h3 {
    color: white;
    text-align: center;
    padding: 0 12px 20px;
    margin: 0 0 10px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.2);
    font-size: 18px;
}

.sidebar a {
    display: block;
    padding: 14px 18px;
    color: white;
    text-decoration: none;
    font-size: 14px;
    line-height: 1.5;
    transition: background 0.2s ease;
    overflow-wrap: break-word;
}

.sidebar a:hover {
    background: #355c7d;
}

.sidebar a.active {
    background: #355c7d;
    border-left: 4px solid #f1c40f;
    padding-left: 14px;
}

.sidebar a.logout {
    margin-top: 15px;
    background: #a93226;
}

.sidebar a.logout:hover {
    background: #922b21;
}

/* =====================================================
   3. CONTENU PRINCIPAL
   Décalage pour éviter le chevauchement du menu
===================================================== */

.conteneur {
    width: calc(100% - 270px);
    max-width: 1250px;
    min-width: 0;
    margin: 30px 20px 30px 250px;
    padding: 0 20px;
}

/* =====================================================
   4. EN-TETE
===================================================== */

.entete {
    background: linear-gradient(135deg, #003366, #075985);
    color: white;
    padding: 30px;
    border-radius: 12px;
    margin-bottom: 22px;
    border-bottom: 5px solid #cba64b;
    overflow-wrap: break-word;
}

.entete h2 {
    margin: 0 0 12px;
    font-size: 27px;
}

.entete p {
    margin: 6px 0;
    line-height: 1.6;
}

/* =====================================================
   5. CARTES
===================================================== */

.carte {
    width: 100%;
    min-width: 0;
    background: white;
    padding: 24px;
    border-radius: 10px;
    margin-bottom: 22px;
    box-shadow: 0 3px 12px rgba(0, 0, 0, 0.05);
}

h3 {
    color: #003366;
    margin-top: 0;
}

/* =====================================================
   6. FORMULAIRE ET FILTRES
===================================================== */

.filtres {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
}

.filtres > div {
    min-width: 0;
}

label {
    display: block;
    font-weight: bold;
    font-size: 14px;
    margin-bottom: 7px;
}

input,
select {
    display: block;
    width: 100%;
    max-width: 100%;
    min-width: 0;
    padding: 12px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    background: white;
    font-size: 14px;
    color: #1e293b;
}

input:focus,
select:focus {
    outline: 2px solid #93c5fd;
    border-color: #2563eb;
}

/* =====================================================
   7. BOUTONS
===================================================== */

.boutons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
    margin-top: 20px;
}

.btn {
    border: 0;
    border-radius: 6px;
    padding: 12px 17px;
    text-decoration: none;
    cursor: pointer;
    font-weight: bold;
    display: inline-block;
    font-size: 14px;
    text-align: center;
    line-height: 1.4;
    max-width: 100%;
}

.bleu {
    background: #003366;
    color: white;
}

.bleu:hover {
    background: #075985;
}

.gris {
    background: #e2e8f0;
    color: #1e293b;
}

.gris:hover {
    background: #cbd5e1;
}

.or {
    background: #b58a2a;
    color: white;
}

.or:hover {
    background: #946c17;
}

/* =====================================================
   8. STATISTIQUES
===================================================== */

.stats {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 22px;
}

.stat {
    min-width: 0;
    background: white;
    padding: 23px;
    border-left: 5px solid #003366;
    border-radius: 9px;
    box-shadow: 0 3px 12px rgba(0, 0, 0, 0.04);
}

.stat span {
    color: #64748b;
    font-size: 14px;
}

.stat strong {
    display: block;
    font-size: 30px;
    color: #003366;
    margin-top: 8px;
}

/* =====================================================
   9. TABLEAU DES AFFECTATIONS
===================================================== */

.table-scroll {
    display: block;
    width: 100%;
    max-width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}

th,
td {
    padding: 12px 10px;
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
    white-space: nowrap;
}

th {
    background: #003366;
    color: white;
}

tbody tr:nth-child(even) {
    background: #f8fafc;
}

tbody tr:hover {
    background: #eff6ff;
}

/* =====================================================
   10. MESSAGES
===================================================== */

.message {
    padding: 16px;
    border-radius: 7px;
    margin: 15px 0;
    overflow-wrap: break-word;
}

.avertissement {
    background: #fff7ed;
    border: 1px solid #fed7aa;
    color: #9a3412;
}

.succes {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    color: #1e40af;
}

/* =====================================================
   11. PIED DE PAGE
===================================================== */

.pied {
    text-align: center;
    font-size: 12px;
    color: #64748b;
    padding: 20px;
    line-height: 1.6;
}

/* =====================================================
   12. ECRANS MOYENS
===================================================== */

@media (max-width: 1000px) {
    .conteneur {
        width: calc(100% - 250px);
        margin-left: 240px;
        margin-right: 10px;
        padding: 0 10px;
    }

    .filtres {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .entete {
        padding: 24px;
    }

    .carte {
        padding: 20px;
    }
}

/* =====================================================
   13. PETITS ECRANS
   Le menu reste visible à gauche
===================================================== */

@media (max-width: 750px) {
    .sidebar {
        width: 200px;
        padding-top: 15px;
    }

    .sidebar h3 {
        font-size: 16px;
        padding: 0 10px 15px;
    }

    .sidebar a {
        padding: 12px;
        font-size: 13px;
    }

    .sidebar a.active {
        padding-left: 8px;
    }

    .conteneur {
        width: calc(100% - 220px);
        max-width: none;
        margin: 18px 10px 18px 210px;
        padding: 0 8px;
    }

    .entete {
        padding: 20px;
    }

    .entete h2 {
        font-size: 22px;
        line-height: 1.4;
    }

    .filtres,
    .stats {
        grid-template-columns: minmax(0, 1fr);
    }

    .carte {
        padding: 16px;
    }

    .boutons {
        flex-direction: column;
        align-items: stretch;
    }

    .btn {
        width: 100%;
    }

    .stat {
        padding: 18px;
    }

    .stat strong {
        font-size: 26px;
    }

    .table-scroll table {
        min-width: 700px;
    }
}

/* =====================================================
   14. TRES PETITS ECRANS
===================================================== */

@media (max-width: 500px) {
    .sidebar {
        width: 165px;
    }

    .sidebar h3 {
        font-size: 14px;
    }

    .sidebar a {
        padding: 11px 9px;
        font-size: 12px;
    }

    .sidebar a.active {
        padding-left: 5px;
    }

    .conteneur {
        width: calc(100% - 180px);
        margin: 12px 5px 12px 170px;
        padding: 0 5px;
    }

    .entete {
        padding: 14px;
        border-radius: 8px;
    }

    .entete h2 {
        font-size: 18px;
    }

    .entete p {
        font-size: 13px;
    }

    .carte {
        padding: 12px;
        border-radius: 8px;
    }

    .carte h3 {
        font-size: 16px;
        line-height: 1.4;
    }

    .filtres {
        gap: 12px;
    }

    input,
    select {
        padding: 10px;
        font-size: 13px;
    }

    .btn {
        padding: 11px 8px;
        font-size: 12px;
    }

    .stat {
        padding: 14px;
    }

    .stat span {
        font-size: 12px;
    }

    .stat strong {
        font-size: 23px;
    }

    .pied {
        font-size: 11px;
        padding: 12px 5px;
    }
}

</style>
</head>

<body>

<!-- Menu latéral -->
    <aside class="sidebar">

        <h3>Espace Surveillant</h3>

        <a href="espace_surv.php" class="active">
            Tableau de bord
        </a>

        <a href="profil_surv.php">
            Mon profil
        </a>

        <a href="affectations_surv.php">
            Mes affectations
        </a>

        <a href="documents_partages_surv.php">
            Documents partagés
        </a>

        <a href="rapports_surv.php">
            Mes rapports PDF
        </a>

        <a href="changer_mdp_surv.php">
            Changer mon mot de passe
        </a>

        <a href="logout_surv.php" class="logout">
            Déconnexion
        </a>

    </aside>



<main class="conteneur">

    <section class="entete">
        <h2>Mes rapports d'examens</h2>

        <p>
            <strong>Surveillant :</strong>
            <?= h($nomSurveillant) ?>
        </p>

        <p>
            Consultez et exportez uniquement les affectations
            qui vous sont attribuées.
        </p>
    </section>

    <section class="carte">
        <h3>Filtrer mes affectations</h3>

        <?php if ($erreur !== ""): ?>
            <div class="message avertissement">
                <?= h($erreur) ?>
            </div>
        <?php endif; ?>

        <form method="GET" action="rapports_surv.php">

            <div class="filtres">

                <div>
                    <label for="date_debut">Date de début</label>

                    <input
                        type="date"
                        id="date_debut"
                        name="date_debut"
                        value="<?= h($date_debut) ?>"
                    >
                </div>

                <div>
                    <label for="date_fin">Date de fin</label>

                    <input
                        type="date"
                        id="date_fin"
                        name="date_fin"
                        value="<?= h($date_fin) ?>"
                    >
                </div>

                <div>
                    <label for="id_salle">Salle</label>

                    <select id="id_salle" name="id_salle">
                        <option value="">Toutes mes salles</option>

                        <?php foreach ($salles as $s): ?>
                            <option
                                value="<?= h($s["id_salle"]) ?>"
                                <?= (string)$id_salle === (string)$s["id_salle"]
                                    ? "selected"
                                    : "" ?>
                            >
                                <?= h($s["nom_salle"]) ?>
                            </option>
                        <?php endforeach; ?>

                    </select>
                </div>

            </div>

            <div class="boutons">

                <button type="submit" class="btn bleu">
                    Afficher le rapport
                </button>

                <a
                    href="rapports_surv.php"
                    class="btn gris"
                >
                    Réinitialiser
                </a>

                <button
                    type="submit"
                    name="pdf"
                    value="1"
                    class="btn or"
                >
                    Télécharger le PDF
                </button>

            </div>

        </form>
    </section>
	
	

    <section class="stats">

        <div class="stat">
            <span>Nombre d'affectations</span>
            <strong><?= $total ?></strong>
        </div>

        <div class="stat">
            <span>Nombre de salles utilisées</span>
            <strong><?= $totalSalles ?></strong>
        </div>

    </section>

    <section class="carte">

        <h3>Détail de mes affectations</h3>

        <p>
            <strong>Période :</strong>
            <?= h($periode) ?>
        </p>

        <p>
            <strong>Rapport généré le :</strong>
            <?= date("d/m/Y à H:i") ?>
        </p>

        <?php if (empty($affectations)): ?>

            <div class="message succes">
                Aucune affectation trouvée pour les filtres choisis.
            </div>

        <?php else: ?>

            <div class="table-scroll">

                <table>

                    <thead>
                        <tr>
                            <th>N°</th>
                            <th>Date</th>
                            <th>Horaire</th>
                            <th>Étudiant</th>
                            <th>Cours</th>
                            <th>Faculté</th>
                            <th>Niveau</th>
                            <th>Salle</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($affectations as $i => $a): ?>

                            <tr>

                                <td><?= $i + 1 ?></td>

                                <td>
                                    <?= h(dateFr($a["date_exam"])) ?>
                                </td>

                                <td>
                                    <?= h(heureFr($a["heure_debut"])) ?>
                                    -
                                    <?= h(heureFr($a["heure_fin"])) ?>
                                </td>

                                <td>
                                    <?= h(
                                        trim(
                                            ($a["nom_etu"] ?? "") . " " .
                                            ($a["prenom_etu"] ?? "")
                                        ) ?: "Non attribué"
                                    ) ?>
                                </td>

                                <td>
                                    <?= h($a["nom_cours"] ?? "Non défini") ?>
                                </td>

                                <td>
                                    <?= h($a["nom_fac"] ?? "Non renseignée") ?>
                                </td>

                                <td>
                                    <?= h($a["nom_niveau"] ?? "Non renseigné") ?>
                                </td>

                                <td>
                                    <?= h($a["nom_salle"] ?? "Non attribuée") ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </section>

    <div class="pied">
        Rapport personnel du surveillant — Gestion des salles d'examen
    </div>

</main>

</body>
</html>

