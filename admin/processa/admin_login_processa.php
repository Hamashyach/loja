<?php
session_start(); 
require_once '../../bd/config.php'; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = $_POST['email'] ?? '';
    $senha_digitada = $_POST['senha'] ?? '';

    if (empty($email) || empty($senha_digitada)) {
        header("Location: ../login.php?erro=1");
        exit;
    }

    try {
        // --- MUDANÇA AQUI ---
        $stmt = $pdo->prepare("SELECT * FROM tb_admin_users WHERE admin_email = ?");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        // --- MUDANÇA AQUI ---
        // Verifica a senha contra 'admin_senha_hash'
        if ($admin && password_verify($senha_digitada, $admin['admin_senha_hash'])) {
            
            $_SESSION['admin_logado'] = true;
            $_SESSION['admin_id'] = $admin['id'];
            // --- MUDANÇA AQUI ---
            $_SESSION['admin_nome'] = $admin['admin_nome']; // Pega 'admin_nome'
            
            header("Location: ../dashboard.php");
            exit;

        } else {
            header("Location: ../login.php?erro=1");
            exit;
        }

    } catch (PDOException $e) {
        die("Erro ao processar login do admin: " . $e->getMessage());
    }
} else {
    header("Location: ../login.php");
    exit;
}
?>