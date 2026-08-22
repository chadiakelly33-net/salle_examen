<?php
include("connexionDB.php");
?>
<html lang="fr">

<head>
<meta charset="UTF-8">
<title>Connexion Administrateur</title>

<style>

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}

body{
    font-family:Arial, sans-serif;
    background-color:#f4f6f9;
    display:flex;
    justify-content:center;
    align-items:center;
    height:100vh;
}

.container{
    background:white;
    padding:30px;
    border-radius:10px;
    box-shadow:0 0 10px rgba(0,0,0,0.2);
    width:400px;
}

h1{
    text-align:center;
    color:#003366;
    margin-bottom:25px;
    font-size:28px;
}

table{
    width:100%;
}

th{
    text-align:left;
    padding:10px 0;
}

input[type="email"],
input[type="password"]{
    width:100%;
    height:40px;
    border:1px solid #ccc;
    border-radius:5px;
    padding-left:10px;
}

input[type="submit"]{
    width:100%;
    height:40px;
    background:#003366;
    color:white;
    border:none;
    border-radius:5px;
    margin-top:20px;
    cursor:pointer;
    font-size:16px;
}

input[type="submit"]:hover{
    background:#0055aa;
}

</style>

</head>

<body>

<div class="content">

    <h1>Connexion</h1>

    <form action="traitementConnexion.php" method="post">

        <table>

            <tr>
                <th>Username</th>
            </tr>

            <tr>
                <td>
                    <input type="email" name="nom" required>
                </td>
            </tr>

            <tr>
                <th>Password</th>
            </tr>

            <tr>
                <td>
                    <input type="password" name="password" required>
                </td>
            </tr>

        </table>

        <input type="submit" value="Connexion" name="conne">

    </form>

</div>

</body>

</html>