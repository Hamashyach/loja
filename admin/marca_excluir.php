<?php
require_once 'auth_check.php';
require_once '../bd/config.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: marcas.php?erro=id_invalido');
    exit;
}

$marca_id = (int)$_GET['id'];

try {
    // Soft Delete: Apenas inativa
    $stmt = $pdo->prepare("UPDATE tb_marcas SET ativo = 0 WHERE id = ?");
    $stmt->execute([$marca_id]);

    // Redireciona com sucesso=2 (inativado)
    header('Location: marcas.php?sucesso=2');
    exit;

} catch (PDOException $e) {
    die("Erro ao inativar marca: " . $e->getMessage());
}
?>