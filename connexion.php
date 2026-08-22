<?php 

session_start();

$email=$_POST['email'];
$password=$_POST['password'];

$email_admin="admin@gmail.com";
$password_admin="12345";

if($email==$email_admin&&$password=$password_admin){
	$_SESSION['admin']=$email;
	
	header("Location:menu_principal.php");
}
else{
	echo"Email ou mot de passe incorrect";
}



?>