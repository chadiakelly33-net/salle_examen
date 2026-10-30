<?php
// try {
    // $bdd = new PDO(
        // 'mysql:host=localhost;dbname=salleexamen;charset=utf8',
        // 'root',
        // ''
    // );

    // $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// } catch (Exception $e) {
    // die("Erreur connexion : " . $e->getMessage());
// }



$host = "sql302.infinityfree.com";
$dbname = "if0_43313169_salleexamen";
$username = "if0_43313169";
$password = "71703302kel";

try {

    $bdd = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password
    );

    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $bdd->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    die("Erreur de connexion à la base de données.");

}




?>