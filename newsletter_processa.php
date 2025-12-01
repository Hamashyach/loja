<?php
session_start();
require 'bd/config.php';

header('Content-Type: application/json');
$response = ['sucesso' => false, 'mensagem' => ''];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $nome = filter_input(INPUT_POST, 'nome', FILTER_SANITIZE_SPECIAL_CHARS) ?? null; 
    $telefone = filter_input(INPUT_POST, 'telefone', FILTER_SANITIZE_SPECIAL_CHARS) ?? null;

    if (!$email) {
        $email = filter_input(INPUT_POST, 'email_inscrito', FILTER_SANITIZE_EMAIL);
    }

    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'E-mail inválido.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT id FROM tb_inscritos WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            if ($nome || $telefone) {
                $sql_up = "UPDATE tb_inscritos SET ";
                $params = [];
                
                if ($nome) { $sql_up .= "nome = ?, "; $params[] = $nome; }
                if ($telefone) { $sql_up .= "telefone = ?, "; $params[] = $telefone; }
                
                $sql_up = rtrim($sql_up, ", ") . " WHERE email = ?";
                $params[] = $email;
                
                $pdo->prepare($sql_up)->execute($params);
            }
            
            echo json_encode(['sucesso' => true, 'mensagem' => 'Você já estava inscrito! Dados atualizados.', 'acao' => 'atualizado']);
            exit;
        }

        // 3. Insere Novo
        $stmt_insert = $pdo->prepare("INSERT INTO tb_inscritos (nome, email, telefone) VALUES (?, ?, ?)");
        $stmt_insert->execute([$nome, $email, $telefone]);

        // Enviar E-mail de Boas-vindas aqui usando PHPMailer

        echo json_encode(['sucesso' => true, 'mensagem' => 'Inscrição realizada com sucesso! Bem-vindo(a)!', 'acao' => 'inscrito']);
        exit;

    } catch (PDOException $e) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro no banco de dados.']);
        exit;
    }
}
?>