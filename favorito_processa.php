<?php
session_start();
require_once 'bd/config.php';


header('Content-Type: application/json');

$response = ['sucesso' => false, 'mensagem' => '', 'acao' => ''];


if (!isset($_SESSION['cliente_logado']) || !isset($_SESSION['cliente_id'])) {
    $response['mensagem'] = 'Você precisa fazer login.';
    $response['erro_login'] = true; 
    echo json_encode($response);
    exit;
}


$cliente_id = (int)$_SESSION['cliente_id'];
$produto_id = (int)($_POST['produto_id'] ?? 0);

if ($produto_id > 0) {
    try {
        
        $stmt_check = $pdo->prepare("SELECT id FROM tb_client_favorites WHERE cliente_id = ? AND produto_id = ?");
        $stmt_check->execute([$cliente_id, $produto_id]);
        $favorito_id = $stmt_check->fetchColumn();

        if ($favorito_id) {
            
            $stmt_delete = $pdo->prepare("DELETE FROM tb_client_favorites WHERE id = ?");
            $stmt_delete->execute([$favorito_id]);
            $response['acao'] = 'removido';
            $response['mensagem'] = 'Removido dos favoritos.';
        } else {
            
            $stmt_insert = $pdo->prepare("INSERT INTO tb_client_favorites (cliente_id, produto_id) VALUES (?, ?)");
            $stmt_insert->execute([$cliente_id, $produto_id]);
            $response['acao'] = 'adicionado';
            $response['mensagem'] = 'Adicionado aos favoritos!';
        }
        $response['sucesso'] = true;

    } catch (PDOException $e) {
        $response['mensagem'] = 'Erro no banco de dados.';
    }
} else {
    $response['mensagem'] = 'Produto inválido.';
}

echo json_encode($response);
exit;
?>