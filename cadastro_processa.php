<?php
session_start();
require_once 'bd/config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Dados Pessoais
    $nome = $_POST['nome'] ?? '';
    $sobrenome = $_POST['sobrenome'] ?? '';
    $email = $_POST['email'] ?? '';
    $contato = $_POST['contato'] ?? '';
    $cpf = $_POST['cpf'] ?? '';
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

    // Validações 
    if (empty($nome) || empty($email) || empty($contato) ||empty($cpf) || empty($senha_digitada) || empty($cep) || empty($endereco)) {
        die("Por favor, preencha todos os campos obrigatórios.");
    }
    if ($senha_digitada !== $confirmar_senha) {
        die("As senhas não conferem.");
    }

    $pdo->beginTransaction();

    try {
        $stmt_check = $pdo->prepare("SELECT id FROM tb_client_users WHERE cliente_email = ?");
        $stmt_check->execute([$email]);
        if ($stmt_check->fetch()) {
            die("Este e-mail já está cadastrado. Tente fazer login.");
        }

        $senha_hash = password_hash($senha_digitada, PASSWORD_DEFAULT);

        $stmt_insert = $pdo->prepare("
            INSERT INTO tb_client_users (cliente_nome, cliente_sobrenome, cliente_email, cliente_contato, cliente_cpf, cliente_senha_hash) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt_insert->execute([$nome, $sobrenome, $email, $contato, $senha_hash]);

        $cliente_id = $pdo->lastInsertId();

        $stmt_addr = $pdo->prepare("
            INSERT INTO tb_client_addresses (cliente_id, cep, endereco, numero, complemento, bairro, cidade, estado, tipo)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Principal')
        ");
        $stmt_addr->execute([$cliente_id, $cep, $endereco, $numero, $complemento, $bairro, $cidade, $estado]);

        $pdo->commit();

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