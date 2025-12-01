<?php
session_start();
require_once 'bd/config.php';


if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = $_POST['email'] ?? '';
    $senha_digitada = $_POST['senha'] ?? '';

    try {

        $stmt = $pdo->prepare("SELECT * FROM tb_client_users WHERE cliente_email = ?");
        $stmt->execute([$email]);
        $cliente = $stmt->fetch();

        if ($cliente && password_verify($senha_digitada, $cliente['cliente_senha_hash'])) {
            
            $_SESSION['cliente_logado'] = true;
            $_SESSION['cliente_id'] = $cliente['id'];
            $_SESSION['cliente_nome'] = $cliente['cliente_nome'];
            header("Location: index.php");
            exit;

        } else {
            header("Location: login.php?erro=1");
            exit;
        }

    } catch (PDOException $e) {
        die("Erro ao processar login: " . $e->getMessage());
    }
} else {
    header("Location: login.php");
    exit;
}
?>