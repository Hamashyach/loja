<?php
session_start();
require_once 'bd/config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Dados Pessoais
    $nome = $_POST['nome'] ?? '';
    $sobrenome = $_POST['sobrenome'] ?? '';
    $email = $_POST['email'] ?? '';
    $senha_digitada = $_POST['senha'] ?? '';
    $confirmar_senha = $_POST['confirmar-senha'] ?? '';

    // Dados de Endereço
    $cep = $_POST['cep'] ?? '';
    $endereco = $_POST['endereco'] ?? '';
    $numero = $_POST['numero'] ?? '';
    $complemento = $_POST['complemento'] ?? '';
    $bairro = $_POST['bairro'] ?? '';
    $cidade = $_POST['cidade'] ?? '';
    $estado = $_POST['estado'] ?? '';

    // Validações (simplificado)
    if (empty($nome) || empty($email) || empty($senha_digitada) || empty($cep) || empty($endereco)) {
        die("Por favor, preencha todos os campos obrigatórios.");
    }
    if ($senha_digitada !== $confirmar_senha) {
        die("As senhas não conferem.");
    }

    // Inicia a transação para garantir que ambos sejam salvos
    $pdo->beginTransaction();

    try {
        // 1. Verifica se o e-mail já existe
        $stmt_check = $pdo->prepare("SELECT id FROM tb_client_users WHERE cliente_email = ?");
        $stmt_check->execute([$email]);
        if ($stmt_check->fetch()) {
            die("Este e-mail já está cadastrado. Tente fazer login.");
        }

        // 2. Criptografa a senha
        $senha_hash = password_hash($senha_digitada, PASSWORD_DEFAULT);

        // 3. Insere o cliente na tb_client_users
        $stmt_insert = $pdo->prepare("
            INSERT INTO tb_client_users (cliente_nome, cliente_sobrenome, cliente_email, cliente_senha_hash) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt_insert->execute([$nome, $sobrenome, $email, $senha_hash]);

        // 4. Pega o ID do cliente que acabamos de criar
        $cliente_id = $pdo->lastInsertId();

        // 5. Insere o endereço na tb_client_addresses
        $stmt_addr = $pdo->prepare("
            INSERT INTO tb_client_addresses (cliente_id, cep, endereco, numero, complemento, bairro, cidade, estado, tipo)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Principal')
        ");
        $stmt_addr->execute([$cliente_id, $cep, $endereco, $numero, $complemento, $bairro, $cidade, $estado]);

        // 6. Se tudo deu certo, confirma as alterações
        $pdo->commit();

        // Redireciona para o login com mensagem de sucesso
        header("Location: login.php?sucesso=1");
        exit;

    } catch (PDOException $e) {
        // Se algo deu errado, desfaz tudo
        $pdo->rollBack();
        die("Erro ao cadastrar: ". $e->getMessage());
    }
} else {
    header("Location: cadastro.php");
    exit;
}
?>