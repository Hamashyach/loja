<?php
session_start();
require 'bd/config.php';

if (!isset($_SESSION['cliente_logado']) || !isset($_SESSION['cliente_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: perfil.php");
    exit;
}

$cliente_id = (int)$_SESSION['cliente_id'];
$acao = $_POST['acao'] ?? '';


if ($acao == 'dados') {

    $nome = $_POST['nome'] ?? '';
    $sobrenome = $_POST['sobrenome'] ?? '';
    $email = $_POST['email'] ?? '';
    $contato = $_POST['contato'] ?? '';
    $cpf = $_POST['cpf'] ?? '';

    if (empty($nome) || empty($sobrenome) || empty($email) || empty($contato) || empty($cpf)) {
        header("Location: perfil.php?erro=nome_vazio#dados-panel");
        exit;
    }

    try {
        $sql = "UPDATE tb_client_users SET cliente_nome = ?, cliente_sobrenome = ?, cliente_email = ?, cliente_contato = ?, cliente_cpf = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nome, $sobrenome, $email, $contato, $cpf, $cliente_id]);

        $_SESSION['cliente_nome'] = $nome;

        header("Location: perfil.php?sucesso=dados#dados-panel");
        exit;

    } catch (PDOException $e) {
        header("Location: perfil.php?erro=db#dados-panel");
        exit;
    }

} elseif ($acao == 'senha') {
    // --- ATUALIZAR SENHA ---
    $senha_atual = $_POST['senha_atual'] ?? '';
    $nova_senha = $_POST['nova_senha'] ?? '';
    $confirmar_nova_senha = $_POST['confirmar_nova_senha'] ?? '';

    // Validação Novas senhas coincidem?
    if ($nova_senha !== $confirmar_nova_senha) {
        header("Location: perfil.php?erro=senhas_nao_coincidem#dados-panel");
        exit;
    }

    try {
        // Busca a senha atual no banco
        $stmt_check = $pdo->prepare("SELECT cliente_senha_hash FROM tb_client_users WHERE id = ?");
        $stmt_check->execute([$cliente_id]);
        $hash_atual = $stmt_check->fetchColumn();

        if ($hash_atual && password_verify($senha_atual, $hash_atual)) {
   
            $novo_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
            
            $sql_update = "UPDATE tb_client_users SET cliente_senha_hash = ? WHERE id = ?";
            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->execute([$novo_hash, $cliente_id]);

            header("Location: perfil.php?sucesso=senha#dados-panel");
            exit;

        } else {
            header("Location: perfil.php?erro=senha_atual_invalida#dados-panel");
            exit;
        }

    } catch (PDOException $e) {
        header("Location: perfil.php?erro=db#dados-panel");
        exit;
    }

}


header("Location: perfil.php");
exit;
?>