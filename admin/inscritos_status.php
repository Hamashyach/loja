<?php
require_once 'auth_check.php';
require_once '../bd/config.php';

$inscrito_id = (int)($_GET['id'] ?? 0);
$acao = $_GET['acao'] ?? '';

if ($inscrito_id <= 0 || ($acao != 'ativar' && $acao != 'desativar')) {
    header("Location: inscritos.php?erro=invalido");
    exit;
}

$novo_status = ($acao == 'ativar') ? 1 : 0;

try {
    $stmt = $pdo->prepare("UPDATE tb_inscritos SET ativo = ? WHERE id = ?");
    $stmt->execute([$novo_status, $inscrito_id]);

} catch (PDOException $e) {
    die("Erro ao atualizar status do inscrito: " . $e->getMessage());
}

header("Location: inscritos.php?sucesso=status");
exit;
?>