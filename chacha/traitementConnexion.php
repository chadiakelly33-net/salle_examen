<?php
session_start();
if(!isset($_SESSION["id_admin"]) || $_SESSION["role"] != "admin"){
    header("Location: connexion.php");
    exit();
}

include("connexionDB.php");
?>
<?php 

session_start();

if(isset($_POST['conne'])){
	
	$email =$_POST['nom'];
	$password =$_POST['password'];
	
	if($email=="admin@gmail.com"&& $password=="12345"){
		$_SESSION['admin']=$email;
		
		header("Location:dashboard.php");
		exit();
	}
	else{
		echo "Identifiants incorrects";
	}
}

if(isset($_POST['deco'])){
	session_start();
	session_destroy();
	
	header("Location:connexion.php");
	exit();
}
?> 