<?php
session_start();
require_once '../verificar_permissao.php';
protegerPagina('acessar_config'); // Protege a API
require_once '../config.php';

$id = $_GET['id'] ?? null;

if (empty($id)) {
    header("Location: cadastro_primeiro_emplacamento.php?status=config_error&msg=" . urlencode("ID não fornecido."));
    exit;
}

try {
    $stmt = $conn->prepare("DELETE FROM EMPLACAMENTO_TIPO WHERE EMT_CODIGO = ?");
    $stmt->execute([$id]);
    
    header("Location: cadastro_primeiro_emplacamento.php?status=config_deleted");
    exit;

} catch (PDOException $e) {
    header("Location: cadastro_primeiro_emplacamento.php?status=config_error&msg=" . urlencode($e->getMessage()));
    exit;
}
?>