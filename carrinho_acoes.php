<?php
session_start();

require_once 'bd/config.php';

// Inicializa carrinho
if (!isset($_SESSION['carrinho'])) {
    $_SESSION['carrinho'] = [];
}

// Verifica se é AJAX (Agora funcionará com o ajuste no JS)
$is_ajax = false;
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    $is_ajax = true;
}

$acao = $_REQUEST['acao'] ?? ''; 
$response = ['sucesso' => false, 'mensagem' => '', 'nova_contagem' => 0];

// --- ADICIONAR ---
if ($acao == 'adicionar') {
    $produto_id = (int)($_POST['produto_id'] ?? 0);
    $quantidade = (int)($_POST['quantidade'] ?? 1);

    if ($produto_id > 0 && $quantidade > 0) {
        if (isset($_SESSION['carrinho'][$produto_id])) {
            $_SESSION['carrinho'][$produto_id]['quantidade'] += $quantidade;
        } else {
            $_SESSION['carrinho'][$produto_id] = [
                'id' => $produto_id,
                'quantidade' => $quantidade
            ];
        }
        $response['sucesso'] = true;
        $response['mensagem'] = 'Produto adicionado ao carrinho!';
    }
}

// --- REMOVER ---
if ($acao == 'remover') {
    $produto_id = (int)($_REQUEST['id'] ?? 0);
    if ($produto_id > 0 && isset($_SESSION['carrinho'][$produto_id])) {
        unset($_SESSION['carrinho'][$produto_id]);
        $response['sucesso'] = true;
        $response['mensagem'] = 'Produto removido.';
    }
    
    // Se NÃO for AJAX, redireciona. Se for AJAX, segue para o JSON.
    if (!$is_ajax) {
        header("Location: carrinho.php");
        exit;
    }
}

// --- ATUALIZAR ---
if ($acao == 'atualizar') {
    $produto_id = (int)($_POST['produto_id'] ?? 0);
    $nova_qtd = (int)($_POST['quantidade'] ?? 1);

    if ($produto_id > 0 && isset($_SESSION['carrinho'][$produto_id])) {
        if ($nova_qtd > 0) {
            $_SESSION['carrinho'][$produto_id]['quantidade'] = $nova_qtd;
        } else {
            unset($_SESSION['carrinho'][$produto_id]);
        }
    }
    
    // CORREÇÃO AQUI: Só redireciona se NÃO for AJAX
    if (!$is_ajax) {
        header("Location: carrinho.php");
        exit;
    }
}

// --- LIMPAR ---
if ($acao == 'limpar') {
    unset($_SESSION['carrinho']);
    header("Location: carrinho.php");
    exit;
}

// Calcula totais para retorno
$total_itens = 0;
if (!empty($_SESSION['carrinho'])) {
    foreach ($_SESSION['carrinho'] as $item) {
        $total_itens += $item['quantidade'];
    }
}
$response['nova_contagem'] = $total_itens;

// Retorno final
if ($is_ajax) {
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
} else {
    // Fallback para requisições normais
    header("Location: carrinho.php");
    exit;
}
?>