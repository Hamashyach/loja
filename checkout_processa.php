<?php
session_start();
require 'bd/config.php';

header('Content-Type: application/json');

// VERIFICAÇÕES 
if ($_SERVER["REQUEST_METHOD"] != "POST") {
    echo json_encode(['sucesso' => false, 'msg' => 'Método inválido']);
    exit;
}

$cliente_id = $_SESSION['cliente_id'] ?? null;
if (!$cliente_id) { echo json_encode(['sucesso' => false, 'msg' => 'Sessão expirada.']); exit; }

if (empty($_SESSION['carrinho'])) {
    echo json_encode(['sucesso' => false, 'msg' => 'Carrinho vazio.']);
    exit;
}

// DADOS DO POST
$endereco_id = filter_input(INPUT_POST, 'endereco_id', FILTER_SANITIZE_NUMBER_INT);
$valor_frete = filter_input(INPUT_POST, 'valor_frete', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
$frete_servico_id = $_POST['frete_servico_id'] ?? null;
$tipo_frete = $_POST['tipo_frete'] ?? 'Envio';

//  BUSCA CLIENTE E ENDEREÇO 
$stmt = $pdo->prepare("SELECT * FROM tb_client_users WHERE id = ?");
$stmt->execute([$cliente_id]);
$cliente = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT * FROM tb_client_addresses WHERE id = ?");
$stmt->execute([$endereco_id]);
$end = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cliente || !$end) {
    echo json_encode(['sucesso' => false, 'msg' => 'Dados incompletos.']);
    exit;
}

// PREPARA ITENS PARA O MERCADO PAGO 
$total_produtos = 0;
$items_mp = [];

foreach ($_SESSION['carrinho'] as $prod_id => $item_carrinho) {
    $prod_id = $item_carrinho['id'];
    $stmt_p = $pdo->prepare("SELECT id, nome, preco, preco_promocional FROM tb_produtos WHERE id = ?");
    $stmt_p->execute([$prod_id]);
    $prod = $stmt_p->fetch(PDO::FETCH_ASSOC);
    
    if ($prod) {
        $preco_unitario = ($prod['preco_promocional'] > 0) ? (float)$prod['preco_promocional'] : (float)$prod['preco'];
        $quantidade = (int)$item_carrinho['quantidade'];
        $total_produtos += $preco_unitario * $quantidade;
        
 
        $items_mp[] = [
            "id" => (string)$prod['id'],
            "title" => $prod['nome'] . " (" . ($item_carrinho['cor']??'') . " - " . ($item_carrinho['tamanho']??'') . ")",
            "description" => "Produto ID: " . $prod['id'],
            "quantity" => $quantidade,
            "unit_price" => $preco_unitario,
            "currency_id" => "BRL"
        ];
    }
}

// Adiciona Frete como Item 
if ($valor_frete > 0) {
    $items_mp[] = [
        "id" => "FRETE",
        "title" => "Frete - " . $tipo_frete,
        "quantity" => 1,
        "unit_price" => (float)$valor_frete,
        "currency_id" => "BRL"
    ];
}

$valor_total_pedido = $total_produtos + $valor_frete;

try {
    $pdo->beginTransaction();

    // CRIA PEDIDO NO BANCO (PENDENTE) 
    $sql = "INSERT INTO tb_pedidos 
        (cliente_id, valor_total, frete_servico_id, status_pagamento, status_entrega, metodo_pagamento, entrega_cep, entrega_endereco, entrega_numero, entrega_bairro, entrega_cidade, entrega_estado) 
        VALUES (?, ?, ?, 'Pendente', 'Nao Enviado', 'Checkout Pro', ?, ?, ?, ?, ?, ?)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $cliente_id, $valor_total_pedido, $frete_servico_id, 
        $end['cep'], $end['endereco'], $end['numero'], $end['bairro'], $end['cidade'], $end['estado']
    ]);
    $pedido_id = $pdo->lastInsertId();

    // Salva Itens no Banco
    foreach ($items_mp as $index => $item) {
        if ($item['id'] === 'FRETE') continue; 

        $prod_id_real = $item['id']; 
        
        $sessao_item = $_SESSION['carrinho'][$prod_id_real] ?? [];
        $tamanho = $sessao_item['tamanho'] ?? 'Padrão';
        $cor = $sessao_item['cor'] ?? 'Padrão';
        
        $pdo->prepare("INSERT INTO tb_itens_pedido (pedido_id, produto_id, quantidade, preco_unitario, tamanho, cor) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([
                $pedido_id, 
                $prod_id_real, 
                $item['quantity'], 
                $item['unit_price'],
                $tamanho,
                $cor
            ]);
    }
    
    $pdo->commit();

    // CRIA PREFERÊNCIA MP 
    $preference_data = [
        "items" => $items_mp,
        "payer" => [
            "name" => $cliente['cliente_nome'],
            "surname" => $cliente['cliente_sobrenome'],
            "email" => $cliente['cliente_email'],
            "identification" => [
                "type" => "CPF",
                "number" => preg_replace('/[^0-9]/', '', $cliente['cliente_cpf'] ?? '')
            ],
            // Endereço do pagador 
            "address" => [
                "street_name" => $end['endereco'],
                "street_number" => (int)$end['numero'],
                "zip_code" => preg_replace('/[^0-9]/', '', $end['cep'])
            ]
        ],
        // CONFIGURAÇÃO DE PAGAMENTO
        "payment_methods" => [
            "excluded_payment_types" => [
                ["id" => "ticket"] 
            ],
            "installments" => 12
        ],
        "back_urls" => [
            "success" => BASE_URL . "/pedido_sucesso.php",
            "failure" => BASE_URL . "/perfil.php",
            "pending" => BASE_URL . "/pedido_sucesso.php"
        ],
        "auto_return" => "approved",
        "external_reference" => (string)$pedido_id,
        "statement_descriptor" => "LION STORE", 
        "expires" => true,
        "expiration_date_to" => date('Y-m-d\TH:i:s.000-03:00', strtotime('+24 hours'))
    ];

    // Envio cURL
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => "https://api.mercadopago.com/checkout/preferences",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($preference_data),
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer " . MP_ACCESS_TOKEN,
            "Content-Type: application/json"
        ],
        CURLOPT_SSL_VERIFYPEER => false // Sandbox = false
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
        echo json_encode(['sucesso' => false, 'msg' => 'Erro cURL: ' . $err]);
        exit;
    }

    $retorno = json_decode($response, true);

    if (isset($retorno['init_point'])) {
        $link = (strpos(MP_ACCESS_TOKEN, 'TEST') === 0) ? $retorno['sandbox_init_point'] : $retorno['init_point'];
        
        unset($_SESSION['carrinho']);

        echo json_encode([
            'sucesso' => true,
            'redirect' => $link
        ]);
    } else {
        $msg = $retorno['message'] ?? 'Erro ao criar preferência MP.';
        // Debug  se der erro
        if(isset($retorno['cause'])) $msg .= ' - ' . json_encode($retorno['cause']);
        echo json_encode(['sucesso' => false, 'msg' => $msg]);
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['sucesso' => false, 'msg' => 'Erro interno: ' . $e->getMessage()]);
}
?>