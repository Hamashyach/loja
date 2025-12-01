<?php
require_once 'auth_check.php';
require_once '../bd/config.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: produtos.php?erro=id_invalido');
    exit;
}

$produto_id = (int)$_GET['id'];

try {
    $stmt = $pdo->prepare("UPDATE tb_produtos SET ativo = 0 WHERE id = ?");
    $stmt->execute([$produto_id]);
    header('Location: produtos.php?sucesso=2');
    exit;

} catch (PDOException $e) {
    die("Erro ao inativar produto: " . $e->getMessage());
}
?>