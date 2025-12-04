<?php
require_once 'auth_check.php';
require_once '../bd/config.php';

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: pedidos.php");
    exit;
}

$pedido_id = (int)($_POST['pedido_id'] ?? 0);
$status_pagamento = $_POST['status_pagamento'] ?? '';
$status_entrega = $_POST['status_entrega'] ?? '';

if ($pedido_id <= 0) {
    header("Location: pedidos.php?erro=id_invalido");
    exit;
}

try {
    $sql = "UPDATE tb_pedidos 
            SET 
                status_pagamento = ?, 
                status_entrega = ?
            WHERE 
                id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $status_pagamento, 
        $status_entrega, 
        $pedido_id
    ]);

} catch (PDOException $e) {
    header("Location: pedido_detalhe.php?id=" . $pedido_id . "&erro=atualizar");
    exit;
}

header("Location: pedido_detalhe.php?id=" . $pedido_id . "&sucesso=1");
exit;
?>