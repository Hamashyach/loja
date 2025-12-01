<?php
require_once 'auth_check.php';
require_once '../bd/config.php';

$cliente_id = (int)($_GET['id'] ?? 0);
$acao = $_GET['acao'] ?? '';

if ($cliente_id <= 0 || ($acao != 'ativar' && $acao != 'desativar')) {
    header("Location: clientes.php?erro=invalido");
    exit;
}

$novo_status = ($acao == 'ativar') ? 1 : 0;

try {
    $stmt = $pdo->prepare("UPDATE tb_client_users SET ativo = ? WHERE id = ?");
    $stmt->execute([$novo_status, $cliente_id]);

} catch (PDOException $e) {
    die("Erro ao atualizar status do cliente: " . $e->getMessage());
}

header("Location: clientes.php?sucesso=status");
exit;
?>