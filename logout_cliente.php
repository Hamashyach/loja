<?php
session_start(); 
unset($_SESSION['cliente_logado']);
unset($_SESSION['cliente_id']);
unset($_SESSION['cliente_nome']);
session_destroy();

header("Location: login.php");
exit;
?>