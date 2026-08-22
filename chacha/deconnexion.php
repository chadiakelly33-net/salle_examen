<?php
session_start();

session_unset();      // supprimer les variables de session
session_destroy();    // détruire la session

header("Location: /salleexamen/index.php");
exit();
?>