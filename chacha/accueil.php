<!DOCTYPE html>
<html lang="fr">

<head>
<meta charset="UTF-8">
<title>Accueil - Université</title>

<style>

body{
    margin:0;
    font-family:Arial, sans-serif;
    background:linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.4)),
    url("https://images.unsplash.com/photo-1523050854058-8df90110c9f1");
    background-size:cover;
    background-position:center;
    height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
}

.container{
    text-align:center;
    color:white;
    background:rgba(0,0,0,0.6);
    padding:40px;
    border-radius:10px;
}

h1{
    font-size:40px;
    margin-bottom:10px;
}

p{
    font-size:18px;
    margin-bottom:30px;
}

a button{
    width:220px;
    height:50px;
    border:none;
    border-radius:8px;
    background:#ffd700;
    color:#003366;
    font-size:18px;
    font-weight:bold;
    cursor:pointer;
    transition:0.3s;
}

a button:hover{
    background:white;
}

</style>

</head>

<body>

<div class="container">

    <h1>WELCOME TO OUR UNIVERSITY</h1>

    <p>
        Bienvenue dans le système de gestion des salles d'examen.<br>
   
    </p>

    <a href="Menu_principal.php">
        <button>Menu Principal</button>
    </a>

</div>

</body>

</html>