<?php
// 1. SEGURANÇA E CONEXÃO
require_once 'auth_check.php';
require_once '../bd/config.php';

// 2. APENAS MÉTODO POST
if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: pedidos.php");
    exit;
}

// 3. COLETAR DADOS
$pedido_id = (int)($_POST['pedido_id'] ?? 0);
$status_pagamento = $_POST['status_pagamento'] ?? '';
$status_entrega = $_POST['status_entrega'] ?? '';

// 4. VALIDAÇÃO BÁSICA
if ($pedido_id <= 0) {
    header("Location: pedidos.php?erro=id_invalido");
    exit;
}
// (Num sistema real, validaríamos se $status_pagamento é um valor permitido)

// 5. ATUALIZAR O BANCO DE DADOS
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
    // Se falhar, redireciona de volta com erro
    header("Location: pedido_detalhe.php?id=" . $pedido_id . "&erro=atualizar");
    exit;
}

// 6. REDIRECIONAR COM SUCESSO
// Se tudo deu certo, volta para a página de detalhes
header("Location: pedido_detalhe.php?id=" . $pedido_id . "&sucesso=1");
exit;
?>