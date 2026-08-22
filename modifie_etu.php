<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Modifier un étudiant</title>

    <style>
        table, th, td{
            border:2px solid black;
            border-collapse:collapse;
            padding:10px;
        }

        table{
            margin:auto;
        }

        h1{
            text-align:center;
        }

        input[type=text]{
            width:250px;
            height:30px;
        }

        input[type=submit],
        input[type=reset]{
            width:120px;
            height:35px;
            cursor:pointer;
        }
    </style>
</head>

<body>

<?php include("menu.php"); ?>

<h1>Modifier un étudiant</h1>

<form action="traitementModification.php" method="POST">

    <table>

        <tr>
            <th>Nom</th>
            <td><input type="text" name="nom"></td>
        </tr>

        <tr>
            <th>Prénom</th>
            <td><input type="text" name="prenom"></td>
        </tr>

        <tr>
            <th>Niveau</th>
            <td><input type="text" name="niveau"></td>
        </tr>

        <tr>
            <th>Faculté</th>
            <td><input type="text" name="faculte"></td>
        </tr>

        <tr>
            <th>Salle</th>
            <td><input type="text" name="salle"></td>
        </tr>

        <tr>
            <td colspan="2" align="center">
                <input type="submit" value="Modifier">
                <input type="reset" value="Annuler">
            </td>
        </tr>

    </table>

</form>

</body>
</html>