<?php
require_once 'auth_check.php';
require_once '../bd/config.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: categorias.php?erro=id_invalido');
    exit;
}

$categoria_id = (int)$_GET['id'];

try {
    // Soft Delete: Apenas inativa
    $stmt = $pdo->prepare("UPDATE tb_categorias SET ativo = 0 WHERE id = ?");
    $stmt->execute([$categoria_id]);

    // Redireciona com sucesso=2 (inativado)
    header('Location: categorias.php?sucesso=2');
    exit;

} catch (PDOException $e) {
    die("Erro ao inativar categoria: " . $e->getMessage());
}
?>