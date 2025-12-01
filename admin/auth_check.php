<?php
session_start();

// Verifica se a sessão 'admin_logado' existe e é verdadeira
if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    
    // Se não estiver logado, destrói qualquer sessão e redireciona
    session_unset();
    session_destroy();
    
    // Redireciona para o login com uma mensagem de erro (erro=2 = acesso negado)
    header('Location: login.php?erro=2');
    exit;
}

// Se chegou até aqui, o admin está logado.
?>