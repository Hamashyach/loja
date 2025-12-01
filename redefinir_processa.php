<?php
require 'bd/config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $token = $_POST['token'] ?? '';
    $nova_senha = $_POST['nova_senha'] ?? '';
    $confirmar_senha = $_POST['confirmar_senha'] ?? '';

    if ($nova_senha !== $confirmar_senha) {
        die("As senhas não coincidem. <a href='javascript:history.back()'>Voltar</a>");
    }

    if (strlen($nova_senha) < 6) {
        die("A senha deve ter no mínimo 6 caracteres.");
    }

    try {
        
        $stmt = $pdo->prepare("SELECT id FROM tb_client_users WHERE token_recuperacao = ? AND token_validade > NOW()");
        $stmt->execute([$token]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($usuario) {
            
            $nova_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
      
            $stmt_update = $pdo->prepare("UPDATE tb_client_users SET cliente_senha_hash = ?, token_recuperacao = NULL, token_validade = NULL WHERE id = ?");
            $stmt_update->execute([$nova_hash, $usuario['id']]);

            header("Location: login.php?sucesso=senha_redefinida");
            exit;
        } else {
            echo "Erro: Token inválido ou expirado.";
        }

    } catch (PDOException $e) {
        die("Erro ao atualizar senha.");
    }
}
?>