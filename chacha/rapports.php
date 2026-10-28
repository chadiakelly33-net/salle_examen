<?php
session_start();

/* 1. Sécurité : accès réservé à l'administrateur */
if (
    !isset($_SESSION['id_admin']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin'
) {
    header('Location: connexion.php');
    exit();
}

/* Déconnexion après 5 minutes d'inactivité */
$delai_inactivite = 300;

if (
    isset($_SESSION['derniere_activite']) &&
    time() - $_SESSION['derniere_activite'] > $delai_inactivite
) {
    session_unset();
    session_destroy();
    header('Location: connexion.php');
    exit();
}

$_SESSION['derniere_activite'] = time();

require_once 'connexionDB.php';

/* 2. Fonctions utilitaires */
function h($valeur) {
    return htmlspecialchars((string)$valeur, ENT_QUOTES, 'UTF-8');
}

function dateFr($valeur) {
    if (empty($valeur)) return '-';

    $timestamp = strtotime($valeur);

    return $timestamp !== false
        ? date('d/m/Y', $timestamp)
        : '-';
}

function heureFr($valeur) {
    if (empty($valeur)) return '-';

    $timestamp = strtotime($valeur);

    return $timestamp !== false
        ? date('H:i', $timestamp)
        : '-';
}

function dateValide($date) {
    if ($date === '') return true;

    $objetDate = DateTime::createFromFormat('!Y-m-d', $date);

    return $objetDate !== false
        && $objetDate->format('Y-m-d') === $date;
}

/* =========================================================
   3. FONCTIONS DE GENERATION DU PDF PROFESSIONNEL
   Aucun logiciel ni bibliothèque supplémentaire nécessaire.
   ========================================================= */

function pdfEscape($texte) {
    $texte = (string)$texte;

    if (function_exists('iconv')) {
        $converti = @iconv(
            'UTF-8',
            'Windows-1252//TRANSLIT//IGNORE',
            $texte
        );

        if ($converti !== false) {
            $texte = $converti;
        }
    }

    $texte = str_replace(
        ["\\", "(", ")", "\r", "\n"],
        ["\\\\", "\\(", "\\)", "", ""],
        $texte
    );

    $sortie = '';

    for ($i = 0, $n = strlen($texte); $i < $n; $i++) {
        $code = ord($texte[$i]);

        $sortie .= ($code < 32 || $code > 126)
            ? '\\' . str_pad(decoct($code), 3, '0', STR_PAD_LEFT)
            : $texte[$i];
    }

    return $sortie;
}

function pdfTexte(
    $texte,
    $x,
    $y,
    $taille = 10,
    $couleur = [0.15, 0.19, 0.25],
    $gras = false
) {
    $police = $gras ? '/F2' : '/F1';

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

function pdfLigne(
    $x1,
    $y1,
    $x2,
    $y2,
    $couleur = [0.85, 0.87, 0.90]
) {
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

function pdfLargeurTexte($texte, $taille) {
    return strlen(pdfEscape($texte)) * $taille * 0.48;
}

function pdfTexteCentre(
    $texte,
    $centreX,
    $y,
    $taille,
    $couleur,
    $gras = false
) {
    $x = $centreX - pdfLargeurTexte($texte, $taille) / 2;

    return pdfTexte(
        $texte,
        $x,
        $y,
        $taille,
        $couleur,
        $gras
    );
}

/* Générer toutes les pages du PDF */
function genererPDFRapport($affectations, $infos) {

    $largeur = 842;
    $hauteur = 595;

    /* Palette professionnelle */
    $bleuMarine = [0.02, 0.12, 0.25];
    $bleu = [0.06, 0.24, 0.43];
    $or = [0.82, 0.65, 0.29];
    $blanc = [1, 1, 1];
    $texte = [0.18, 0.22, 0.28];
    $gris = [0.45, 0.49, 0.55];
    $grisClair = [0.95, 0.96, 0.98];
    $bordure = [0.86, 0.88, 0.91];

    /*
     * 12 affectations maximum par page pour conserver
     * une bonne lisibilité.
     */
    $groupes = array_chunk($affectations, 12);

    if (empty($groupes)) {
        $groupes = [[]];
    }

    $pages = [];
    $totalPages = count($groupes);

    foreach ($groupes as $numeroPage => $lignes) {

        $flux = pdfRectangle(
            0, 0, $largeur, $hauteur, $blanc
        );

        /* En-tête bleu marine */
        $flux .= pdfRectangle(
            0, 490, $largeur, 105, $bleuMarine
        );

        /* Ligne dorée */
        $flux .= pdfRectangle(
            0, 486, $largeur, 4, $or
        );

        $flux .= pdfTexte(
            "SYSTEME DE GESTION DES SALLES D'EXAMEN",
            38, 562, 9,
            [0.84, 0.88, 0.93],
            true
        );

        $flux .= pdfTexte(
            "RAPPORT DES EXAMENS",
            38, 530, 23,
            $blanc,
            true
        );

        $flux .= pdfTexte(
            "Rapport administratif des affectations",
            39, 510, 10,
            [0.84, 0.88, 0.93]
        );

        /* Encadré de la période */
        $flux .= pdfRectangle(
            655, 515, 150, 42, $bleu
        );

        $flux .= pdfTexteCentre(
            "PERIODE",
            730, 542, 8,
            [0.84, 0.88, 0.93],
            true
        );

        $flux .= pdfTexteCentre(
            $infos['periode'],
            730, 525, 11,
            $blanc,
            true
        );

        /* Informations générales */
        $flux .= pdfTexte(
            "Date de debut :",
            38, 462, 9, $gris, true
        );

        $flux .= pdfTexte(
            $infos['date_debut'],
            115, 462, 9, $texte
        );

        $flux .= pdfTexte(
            "Date de fin :",
            280, 462, 9, $gris, true
        );

        $flux .= pdfTexte(
            $infos['date_fin'],
            350, 462, 9, $texte
        );

        $flux .= pdfTexte(
            "Genere le :",
            535, 462, 9, $gris, true
        );

        $flux .= pdfTexte(
            $infos['date_generation'],
            600, 462, 9, $texte
        );

        $flux .= pdfLigne(
            38, 448, 804, 448, $bordure
        );

        /* Cartes statistiques sur la première page */
        if ($numeroPage === 0) {

            $cartes = [
                [
                    'AFFECTATIONS',
                    $infos['total_affectations']
                ],
                [
                    'ETUDIANTS',
                    $infos['total_etudiants']
                ],
                [
                    'COURS',
                    $infos['total_cours']
                ],
                [
                    'SALLES',
                    $infos['total_salles']
                ],
                [
                    'SURVEILLANTS',
                    $infos['total_surveillants']
                ]
            ];

            $x = 38;

            foreach ($cartes as $carte) {

                $flux .= pdfRectangle(
                    $x, 390, 146, 43, $grisClair
                );

                $flux .= pdfRectangle(
                    $x, 390, 3, 43, $or
                );

                $flux .= pdfTexte(
                    $carte[0],
                    $x + 12, 416, 7,
                    $gris,
                    true
                );

                $flux .= pdfTexte(
                    (string)$carte[1],
                    $x + 12, 397, 16,
                    $bleuMarine,
                    true
                );

                $x += 162;
            }

            $titreTableauY = 365;

            $flux .= pdfTexte(
                "DETAIL DES AFFECTATIONS",
                38, $titreTableauY, 12,
                $bleuMarine,
                true
            );

        } else {

            $flux .= pdfTexte(
                "DETAIL DES AFFECTATIONS - SUITE",
                38, 415, 12,
                $bleuMarine,
                true
            );

            $titreTableauY = 390;
        }

        /* Colonnes du tableau */
        $colonnes = [
            ['N°', 38, 28],
            ['DATE', 66, 72],
            ['HORAIRES', 138, 88],
            ['ETUDIANT', 226, 135],
            ['COURS', 361, 137],
            ['SALLE', 498, 88],
            ['SURVEILLANT', 586, 218]
        ];

        $enteteY = $titreTableauY - 25;
        $hauteurLigne = 21;

        /* En-tête du tableau */
        $flux .= pdfRectangle(
            38,
            $enteteY - 5,
            766,
            23,
            $bleuMarine
        );

        foreach ($colonnes as $colonne) {

            $flux .= pdfTexte(
                $colonne[0],
                $colonne[1] + 6,
                $enteteY + 3,
                7.5,
                $blanc,
                true
            );
        }

        $y = $enteteY - 17;

        foreach ($lignes as $index => $a) {

            $yLigne = $y - $hauteurLigne + 5;

            /* Alternance des lignes */
            if ($index % 2 === 0) {
                $flux .= pdfRectangle(
                    38,
                    $yLigne,
                    766,
                    $hauteurLigne,
                    $grisClair
                );
            }

            $flux .= pdfLigne(
                38,
                $yLigne,
                804,
                $yLigne,
                $bordure
            );

            $valeurs = [
                (string)(($numeroPage * 12) + $index + 1),
                dateFr($a['date_exam']),
                heureFr($a['heure_debut']) .
                    ' - ' .
                    heureFr($a['heure_fin']),
                $a['nom_etu'] . ' ' . $a['prenom_etu'],
                $a['nom_cours'],
                $a['nom_salle'],
                $a['nom_surv'] . ' ' . $a['prenom_surv']
            ];

            foreach ($colonnes as $i => $colonne) {

                $valeur = $valeurs[$i];
                $maxCaracteres = (int)($colonne[2] / 4.2);

                if (strlen($valeur) > $maxCaracteres) {
                    $valeur = substr(
                        $valeur,
                        0,
                        max(1, $maxCaracteres - 3)
                    ) . '...';
                }

                $flux .= pdfTexte(
                    $valeur,
                    $colonne[1] + 6,
                    $y - 9,
                    7.5,
                    $texte
                );
            }

            $y -= $hauteurLigne;
        }

        /* Aucune donnée */
        if (empty($lignes)) {

            $flux .= pdfRectangle(
                38,
                $enteteY - 47,
                766,
                35,
                $grisClair
            );

            $flux .= pdfTexteCentre(
                "Aucune affectation ne correspond aux filtres selectionnes.",
                421,
                $enteteY - 34,
                10,
                $gris
            );
        }

        /* Pied de page */
        $flux .= pdfLigne(
            38, 35, 804, 35, $bordure
        );

        $flux .= pdfTexte(
            "SYSTEME DE GESTION DES SALLES D'EXAMEN",
            38, 20, 7,
            $gris,
            true
        );

        $flux .= pdfTexte(
            "Document administratif - Usage interne",
            295, 20, 7, $gris
        );

        $flux .= pdfTexte(
            "Page " . ($numeroPage + 1) . " / " . $totalPages,
            735, 20, 8,
            $bleuMarine,
            true
        );

        $pages[] = $flux;
    }

    /* Construction du fichier PDF */
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
            '<< /Type /Page /Parent 2 0 R ' .
            '/MediaBox [0 0 842 595] ' .
            '/Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> ' .
            '/Contents ' . $objetContenu . ' 0 R >>';

        $objets[$objetContenu] =
            '<< /Length ' . strlen($flux) . " >>\n" .
            "stream\n" . $flux . "\nendstream";

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

        $pdf .= $i . " 0 obj\n" .
            $objets[$i] . "\nendobj\n";
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

    $pdf .= "startxref\n" .
        $xref .
        "\n%%EOF";

    if (ob_get_length()) {
        ob_clean();
    }

    header('Content-Type: application/pdf');

    header(
        'Content-Disposition: attachment; filename="rapport_examens_' .
        date('Y-m-d_H-i-s') . '.pdf"'
    );

    header('Content-Length: ' . strlen($pdf));
    header('Cache-Control: private, max-age=0, must-revalidate');

    echo $pdf;
    exit();
}

/* =========================================================
   4. GESTION DES FILTRES
   ========================================================= */

$periodesAutorisees = [
    'jour',
    'semaine',
    'mois',
    'annee',
    'personnalisee'
];

$periode = $_GET['periode'] ?? 'mois';

if (!in_array($periode, $periodesAutorisees, true)) {
    $periode = 'mois';
}

$date_debut = trim($_GET['date_debut'] ?? '');
$date_fin = trim($_GET['date_fin'] ?? '');
$id_salle = trim($_GET['id_salle'] ?? '');
$id_surv = trim($_GET['id_surv'] ?? '');

$aujourdhui = date('Y-m-d');

/* Calcul des dates selon la période */
switch ($periode) {

    case 'jour':
        $date_debut = $aujourdhui;
        $date_fin = $aujourdhui;
        break;

    case 'semaine':
        $date_debut = date(
            'Y-m-d',
            strtotime('monday this week')
        );

        $date_fin = date(
            'Y-m-d',
            strtotime('sunday this week')
        );
        break;

    case 'mois':
        $date_debut = date('Y-m-01');
        $date_fin = date('Y-m-t');
        break;

    case 'annee':
        $date_debut = date('Y-01-01');
        $date_fin = date('Y-12-31');
        break;
}

/* Vérification des dates */
$erreur = '';

if ($periode === 'personnalisee') {

    if (
        !dateValide($date_debut) ||
        !dateValide($date_fin)
    ) {
        $erreur = 'Une des dates saisies est invalide.';

    } elseif (
        $date_debut !== '' &&
        $date_fin !== '' &&
        $date_debut > $date_fin
    ) {
        $erreur =
            'La date de début doit être antérieure ou égale à la date de fin.';
    }
}

/* Vérification des identifiants */
if ($id_salle !== '' && !ctype_digit($id_salle)) {
    $id_salle = '';
}

if ($id_surv !== '' && !ctype_digit($id_surv)) {
    $id_surv = '';
}

/* =========================================================
   5. CHARGEMENT DES SALLES ET SURVEILLANTS
   ========================================================= */

$salles = $bdd->query(
    'SELECT id_salle, nom_salle
     FROM salle
     ORDER BY nom_salle ASC'
)->fetchAll(PDO::FETCH_ASSOC);

$surveillants = $bdd->query(
    'SELECT id_surv, nom_surv, prenom_surv
     FROM surveillant
     ORDER BY nom_surv ASC, prenom_surv ASC'
)->fetchAll(PDO::FETCH_ASSOC);

/* =========================================================
   6. RECHERCHE DES AFFECTATIONS
   ========================================================= */

$affectations = [];

if ($erreur === '') {

    $sql = "SELECT
                a.id_affectation,
                a.date_exam,
                a.heure_debut,
                a.heure_fin,
                e.id_etu,
                e.nom_etu,
                e.prenom_etu,
                c.id_cours,
                c.nom_cours,
                s.id_salle,
                s.nom_salle,
                sv.id_surv,
                sv.nom_surv,
                sv.prenom_surv
            FROM affectation a
            INNER JOIN etudiant e
                ON a.id_etu = e.id_etu
            INNER JOIN cours c
                ON a.id_cours = c.id_cours
            INNER JOIN salle s
                ON a.id_salle = s.id_salle
            INNER JOIN surveillant sv
                ON a.id_surv = sv.id_surv
            WHERE 1 = 1";

    $parametres = [];

    if ($date_debut !== '') {
        $sql .= ' AND a.date_exam >= :date_debut';
        $parametres[':date_debut'] = $date_debut;
    }

    if ($date_fin !== '') {
        $sql .= ' AND a.date_exam <= :date_fin';
        $parametres[':date_fin'] = $date_fin;
    }

    if ($id_salle !== '') {
        $sql .= ' AND a.id_salle = :id_salle';
        $parametres[':id_salle'] = (int)$id_salle;
    }

    if ($id_surv !== '') {
        $sql .= ' AND a.id_surv = :id_surv';
        $parametres[':id_surv'] = (int)$id_surv;
    }

    $sql .= '
        ORDER BY
            a.date_exam ASC,
            a.heure_debut ASC,
            s.nom_salle ASC
    ';

    $requete = $bdd->prepare($sql);
    $requete->execute($parametres);

    $affectations = $requete->fetchAll(PDO::FETCH_ASSOC);
}

/* =========================================================
   7. CALCUL DES STATISTIQUES
   ========================================================= */

$totalAffectations = count($affectations);

$etudiantsComptes = [];
$coursComptes = [];
$sallesUtilisees = [];
$surveillantsUtilises = [];

foreach ($affectations as $affectation) {

    $etudiantsComptes[$affectation['id_etu']] = true;
    $coursComptes[$affectation['id_cours']] = true;
    $sallesUtilisees[$affectation['id_salle']] = true;
    $surveillantsUtilises[$affectation['id_surv']] = true;
}

$totalEtudiants = count($etudiantsComptes);
$totalCours = count($coursComptes);
$totalSalles = count($sallesUtilisees);
$totalSurveillants = count($surveillantsUtilises);

$nomsPeriodes = [
    'jour' => "Aujourd'hui",
    'semaine' => 'Cette semaine',
    'mois' => 'Ce mois',
    'annee' => 'Cette année',
    'personnalisee' => 'Période personnalisée'
];

$nomPeriode = $nomsPeriodes[$periode];

/* =========================================================
   8. EXPORTATION DU PDF
   ========================================================= */

if (
    isset($_GET['export']) &&
    $_GET['export'] === 'pdf' &&
    $erreur === ''
) {

    $infosPDF = [
        'periode' => $nomPeriode,

        'date_debut' => $date_debut !== ''
            ? dateFr($date_debut)
            : 'Toutes les dates',

        'date_fin' => $date_fin !== ''
            ? dateFr($date_fin)
            : 'Toutes les dates',

        'date_generation' => date('d/m/Y H:i'),

        'total_affectations' => $totalAffectations,
        'total_etudiants' => $totalEtudiants,
        'total_cours' => $totalCours,
        'total_salles' => $totalSalles,
        'total_surveillants' => $totalSurveillants
    ];

    genererPDFRapport($affectations, $infosPDF);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Rapports périodiques des examens</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    padding: 20px;
    font-family: Arial, sans-serif;
    background: #f4f6f9;
    color: #333;
}

.conteneur {
    max-width: 1400px;
    margin: 0 auto;
}

.entete {
    background: #062347;
    color: #fff;
    padding: 28px;
    border-radius: 10px;
    border-bottom: 4px solid #d1a64a;
    margin-bottom: 22px;
}

.entete h1 {
    margin: 0 0 10px;
    font-size: 27px;
}

.entete p {
    margin: 0;
    line-height: 1.6;
    color: #e1e8f0;
}

.carte {
    background: #fff;
    padding: 22px;
    border-radius: 10px;
    margin-bottom: 22px;
    box-shadow: 0 4px 15px rgba(0, 25, 60, 0.06);
}

.carte h2 {
    color: #062347;
    margin: 0 0 18px;
    font-size: 21px;
}

.formulaire {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
}

.champ {
    display: flex;
    flex-direction: column;
}

.champ label {
    font-weight: bold;
    margin-bottom: 8px;
    color: #24364b;
}

.champ input,
.champ select {
    width: 100%;
    min-width: 0;
    padding: 12px;
    border: 1px solid #d7dde5;
    border-radius: 6px;
    background: #fff;
    font-size: 14px;
}

.champ input:focus,
.champ select:focus {
    outline: none;
    border-color: #174b7a;
    box-shadow: 0 0 0 3px rgba(23, 75, 122, 0.1);
}

.actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-top: 20px;
}

.bouton {
    display: inline-block;
    padding: 12px 18px;
    border: 0;
    border-radius: 6px;
    text-decoration: none;
    font-size: 14px;
    font-weight: bold;
    cursor: pointer;
    text-align: center;
    transition: 0.2s;
}

.bouton-principal {
    background: #062347;
    color: #fff;
}

.bouton-principal:hover {
    background: #123e6b;
}

.bouton-secondaire {
    background: #edf0f4;
    color: #28394d;
}

.bouton-impression {
    background: #b58b35;
    color: #fff;
}

.bouton-impression:hover {
    background: #957026;
}

.statistiques {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 14px;
    margin-bottom: 22px;
}

.statistique {
    background: #fff;
    padding: 19px;
    border-radius: 9px;
    border-left: 4px solid #c5a04a;
    box-shadow: 0 4px 15px rgba(0, 25, 60, 0.05);
    overflow-wrap: anywhere;
}

.nombre {
    font-size: 28px;
    font-weight: bold;
    color: #062347;
    margin-bottom: 8px;
}

.libelle {
    font-size: 13px;
    color: #667085;
    line-height: 1.4;
}

.resume {
    line-height: 1.9;
    overflow-wrap: anywhere;
}

.tableau-conteneur {
    width: 100%;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 900px;
}

thead {
    background: #062347;
    color: #fff;
}

th,
td {
    padding: 12px 10px;
    text-align: left;
    border-bottom: 1px solid #e4e8ee;
    font-size: 13px;
}

tbody tr:nth-child(even) {
    background: #f7f9fc;
}

tbody tr:hover {
    background: #eef3f9;
}

.message-erreur {
    background: #fdeaea;
    color: #a61b1b;
    border: 1px solid #f5bcbc;
    padding: 15px;
    border-radius: 6px;
    margin-bottom: 20px;
}

.message-vide {
    padding: 25px;
    text-align: center;
    background: #f7f9fc;
    border-radius: 6px;
    color: #555;
}

.note {
    color: #667085;
    font-size: 13px;
    line-height: 1.6;
    margin-top: 14px;
}

.pied-page {
    text-align: center;
    font-size: 12px;
    color: #777;
    margin: 25px 0 10px;
}

@media (max-width: 1000px) {
    .statistiques {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .formulaire {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 600px) {
    body {
        padding: 10px;
    }

    .entete {
        padding: 18px;
    }

    .entete h1 {
        font-size: 22px;
    }

    .carte {
        padding: 15px;
    }

    .formulaire {
        grid-template-columns: 1fr;
    }

    .statistiques {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
    }

    .statistique {
        padding: 14px;
    }

    .nombre {
        font-size: 24px;
    }

    .actions {
        flex-direction: column;
    }

    .bouton {
        width: 100%;
    }
}
</style>
</head>

<body>

<?php require_once 'menu_principal.php'; ?>

<div class="conteneur">

    <div class="entete">
        <h1>Rapports périodiques des examens</h1>

        <p>
            Consultez les affectations, les étudiants, les cours,
            les salles et les surveillants selon la période choisie.
        </p>
    </div>

    <!-- Filtres -->
    <div class="carte ne-pas-imprimer">

        <h2>Filtrer le rapport</h2>

        <?php if ($erreur !== ''): ?>
            <div class="message-erreur">
                <?= h($erreur) ?>
            </div>
        <?php endif; ?>

        <form method="GET" action="rapports.php">

            <div class="formulaire">

                <div class="champ">
                    <label for="periode">Période</label>

                    <select id="periode" name="periode">

                        <?php foreach ($nomsPeriodes as $cle => $nom): ?>

                            <option
                                value="<?= h($cle) ?>"
                                <?= $periode === $cle ? 'selected' : '' ?>
                            >
                                <?= h($nom) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>
                </div>

                <div class="champ">
                    <label for="date_debut">Date de début</label>

                    <input
                        type="date"
                        id="date_debut"
                        name="date_debut"
                        value="<?= h($date_debut) ?>"
                    >
                </div>

                <div class="champ">
                    <label for="date_fin">Date de fin</label>

                    <input
                        type="date"
                        id="date_fin"
                        name="date_fin"
                        value="<?= h($date_fin) ?>"
                    >
                </div>

                <div class="champ">
                    <label for="id_salle">Salle</label>

                    <select id="id_salle" name="id_salle">

                        <option value="">Toutes les salles</option>

                        <?php foreach ($salles as $salle): ?>

                            <option
                                value="<?= h($salle['id_salle']) ?>"
                                <?= (string)$id_salle === (string)$salle['id_salle'] ? 'selected' : '' ?>
                            >
                                <?= h($salle['nom_salle']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>
                </div>

                <div class="champ">
                    <label for="id_surv">Surveillant</label>

                    <select id="id_surv" name="id_surv">

                        <option value="">Tous les surveillants</option>

                        <?php foreach ($surveillants as $surveillant): ?>

                            <option
                                value="<?= h($surveillant['id_surv']) ?>"
                                <?= (string)$id_surv === (string)$surveillant['id_surv'] ? 'selected' : '' ?>
                            >
                                <?= h(
                                    $surveillant['nom_surv'] . ' ' .
                                    $surveillant['prenom_surv']
                                ) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>
                </div>

            </div>

            <div class="actions">

                <button
                    type="submit"
                    class="bouton bouton-principal"
                >
                    Générer le rapport
                </button>

                <a
                    href="rapports.php"
                    class="bouton bouton-secondaire"
                >
                    Réinitialiser
                </a>

            </div>

        </form>

        <p class="note">
            Pour une période personnalisée, choisissez « Période personnalisée »,
            puis indiquez les dates souhaitées. Laissez une date vide si vous
            ne voulez pas limiter le rapport de ce côté.
        </p>

    </div>

    <?php if ($erreur === ''): ?>

        <!-- Résumé -->
        <div class="carte">

            <h2>Résumé du rapport</h2>

            <div class="resume">

                <strong>Période :</strong>
                <?= h($nomPeriode) ?>
                <br>

                <strong>Du :</strong>
                <?= $date_debut !== ''
                    ? h(dateFr($date_debut))
                    : 'Toutes les dates' ?>
                <br>

                <strong>Au :</strong>
                <?= $date_fin !== ''
                    ? h(dateFr($date_fin))
                    : 'Toutes les dates' ?>
                <br>

                <strong>Généré le :</strong>
                <?= date('d/m/Y à H:i') ?>

            </div>

        </div>

        <!-- Statistiques -->
        <div class="statistiques">

            <div class="statistique">
                <div class="nombre">
                    <?= h($totalAffectations) ?>
                </div>

                <div class="libelle">
                    Affectations trouvées
                </div>
            </div>

            <div class="statistique">
                <div class="nombre">
                    <?= h($totalEtudiants) ?>
                </div>

                <div class="libelle">
                    Étudiants concernés
                </div>
            </div>

            <div class="statistique">
                <div class="nombre">
                    <?= h($totalCours) ?>
                </div>

                <div class="libelle">
                    Cours concernés
                </div>
            </div>

            <div class="statistique">
                <div class="nombre">
                    <?= h($totalSalles) ?>
                </div>

                <div class="libelle">
                    Salles utilisées
                </div>
            </div>

            <div class="statistique">
                <div class="nombre">
                    <?= h($totalSurveillants) ?>
                </div>

                <div class="libelle">
                    Surveillants concernés
                </div>
            </div>

        </div>

        <!-- Exportation PDF -->
        <div class="carte ne-pas-imprimer">

            <h2>Télécharger le rapport PDF</h2>

            <p>
                Téléchargez un rapport professionnel avec les statistiques,
                les détails des affectations et la pagination automatique.
            </p>

            <div class="actions">

                <a
                    class="bouton bouton-impression"
                    href="rapports.php?<?= h(
                        http_build_query(
                            array_merge($_GET, ['export' => 'pdf'])
                        )
                    ) ?>"
                >
                    Télécharger le PDF
                </a>

            </div>

        </div>

        <!-- Tableau des affectations -->
        <div class="carte">

            <h2>Détail des affectations</h2>

            <?php if (count($affectations) > 0): ?>

                <div class="tableau-conteneur">

                    <table>

                        <thead>
                            <tr>
                                <th>N°</th>
                                <th>Date examen</th>
                                <th>Heure début</th>
                                <th>Heure fin</th>
                                <th>Étudiant</th>
                                <th>Cours</th>
                                <th>Salle</th>
                                <th>Surveillant</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($affectations as $index => $affectation): ?>

                                <tr>

                                    <td>
                                        <?= $index + 1 ?>
                                    </td>

                                    <td>
                                        <?= h(dateFr($affectation['date_exam'])) ?>
                                    </td>

                                    <td>
                                        <?= h(heureFr($affectation['heure_debut'])) ?>
                                    </td>

                                    <td>
                                        <?= h(heureFr($affectation['heure_fin'])) ?>
                                    </td>

                                    <td>
                                        <?= h(
                                            $affectation['nom_etu'] . ' ' .
                                            $affectation['prenom_etu']
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= h($affectation['nom_cours']) ?>
                                    </td>

                                    <td>
                                        <?= h($affectation['nom_salle']) ?>
                                    </td>

                                    <td>
                                        <?= h(
                                            $affectation['nom_surv'] . ' ' .
                                            $affectation['prenom_surv']
                                        ) ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

                <p class="note">
                    Nombre total d'affectations :
                    <strong><?= h($totalAffectations) ?></strong>
                </p>

            <?php else: ?>

                <div class="message-vide">
                    Aucune affectation ne correspond aux filtres sélectionnés.
                </div>

            <?php endif; ?>

        </div>

    <?php endif; ?>

    <div class="pied-page">
        Rapport généré par le système de gestion des salles d'examen.
    </div>

</div>

</body>
</html>