<?php
session_start();
require 'bd/config.php';

header('Content-Type: application/json');

$mp_access_token = 'APP_USR-7259438993484884-120114-6fc14ac7f6ba9ebe2a5e501f184247cf-3032062927'; 

$pasta_projeto = "/LionCompany"; 
// CORREÇÃO: Usar ':' e não '=>' na URL
$base_url = "http://127.0.0.1" . $pasta_projeto;

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    // CORREÇÃO: Sintaxe do header
    header("Location: carrinho.php");
    exit;
}

// Coleta Dados Básicos 
$cliente_id = $_SESSION['cliente_id'];
$endereco_id = $_POST['endereco_id'];
$valor_produtos = (float)$_POST['valor_produtos'];
$valor_frete = (float)$_POST['valor_frete'];
$tipo_frete = $_POST['tipo_frete'];

// CORREÇÃO: Lógica ternária correta (? :) em vez de (??)
$frete_servico_id = !empty($_POST['frete_servico_id']) ? (int)$_POST['frete_servico_id'] : null;
$valor_total = $valor_produtos + $valor_frete;

// Dados Específicos do Checkout Transparente 
$token_cartao = $_POST['token'] ?? null;
$payment_method_id = $_POST['paymentMethodId'] ?? null; 
$issuer_id = $_POST['issuer'] ?? null; 
$installments = $_POST['installments'] ?? 1; 

// Busca Endereço e Cliente
$stmt_end = $pdo->prepare("SELECT * FROM tb_client_addresses WHERE id = ?");
$stmt_end->execute([$endereco_id]);
$end = $stmt_end->fetch();

$stmt_cli = $pdo->prepare("SELECT cliente_nome, cliente_sobrenome, cliente_email, cliente_cpf FROM tb_client_users WHERE id = ?");
$stmt_cli->execute([$cliente_id]);
$cliente_dados = $stmt_cli->fetch();

try {
    $pdo->beginTransaction();

    // Cria o Pedido no Banco (Status Pendente)
    $sql_pedido = "INSERT INTO tb_pedidos 
        (cliente_id, valor_total, frete_servico_id, status_pagamento, status_entrega, metodo_pagamento, entrega_cep, entrega_endereco, entrega_numero, entrega_bairro, entrega_cidade, entrega_estado) 
        VALUES (?, ?, ?, 'Pendente', 'Nao Enviado', ?, ?, ?, ?, ?, ?, ?)";
    
    // CORREÇÃO: Lógica ternária para o nome do método
    $metodo_nome = ($payment_method_id == 'pix') ? 'Pix' : 'Cartão';

    $stmt = $pdo->prepare($sql_pedido);
    $stmt->execute([
        $cliente_id, $valor_total, $frete_servico_id, $metodo_nome,
        $end['cep'], $end['endereco'], $end['numero'], $end['bairro'], $end['cidade'], $end['estado']
    ]);
    $pedido_id = $pdo->lastInsertId();

    // Salva Itens
    foreach ($_SESSION['carrinho'] as $prod_id => $item) {
        $stmt_prod = $pdo->prepare("SELECT preco, preco_promocional FROM tb_produtos WHERE id = ?");
        $stmt_prod->execute([$prod_id]);
        $prod = $stmt_prod->fetch();
        
        // CORREÇÃO: Lógica de preço
        $preco = ($prod['preco_promocional'] > 0) ? $prod['preco_promocional'] : $prod['preco'];
        
        $pdo->prepare("INSERT INTO tb_itens_pedido (pedido_id, produto_id, quantidade, preco_unitario) VALUES (?, ?, ?, ?)")
            ->execute([$pedido_id, $prod_id, $item['quantidade'], $preco]);
    }
    $pdo->commit();

    // ==========================================================================
    // 🚀 MONTAGEM DO JSON (API de Orders)
    // ==========================================================================

    // Define o tipo
    $tipo_pagamento = ($payment_method_id == 'pix') ? 'bank_transfer' : 'credit_card';
    
    // Dados do Pagamento (dentro da transação)
    $payment_data = [
        "amount" => number_format($valor_total, 2, '.', ''), 
        "payment_method" => [
            "id" => $payment_method_id, 
            "type" => $tipo_pagamento   
        ]
    ];

    if ($payment_method_id != 'pix') {
        $payment_data["token"] = $token_cartao;
        $payment_data["installments"] = (int)$installments;
        $payment_data["issuer_id"] = (int)$issuer_id;
    }

    // Estrutura Principal da Order
    $payload = [
        "type" => "online",
        "processing_mode" => "automatic",
        "external_reference" => (string)$pedido_id,
        "total_amount" => number_format($valor_total, 2, '.', ''), // Valor total na raiz
        "transactions" => [
            "payments" => [ $payment_data ] 
        ],
        "payer" => [
            "email" => $cliente_dados['cliente_email'],
            "entity_type" => "individual",
            "identification" => [
                "type" => "CPF",
                "number" => preg_replace('/[^0-9]/', '', $cliente_dados['cliente_cpf'])
            ]
        ],
        // CORREÇÃO: Estrutura do array shipment consertada
        "shipment" => [
            "address" => [
                "zip_code" => $end['cep'],
                "street_name" => $end['endereco'],
                "street_number" => (string)$end['numero'],
                "neighborhood" => $end['bairro'],
                "city" => $end['cidade'],
                "state_name" => $end['estado']
                // "complement" => $end['complemento']
            ]
        ]
    ];

    // CORREÇÃO: Data de expiração na raiz do payload (se for Pix)
    if ($payment_method_id == 'pix') {
        // Formato correto ISO 8601 com ':' e não '=>'
        $payload["expiration_time"] = date('Y-m-d\TH:i:s.000-03:00', strtotime('+24 hours'));
    }

    // Envio para a API
    $curl = curl_init();

    curl_setopt_array($curl, [
        // CORREÇÃO: URL e Headers com sintaxe correta
        CURLOPT_URL => "https://api.mercadopago.com/v1/orders",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer " . $mp_access_token,
            "Content-Type: application/json",
            "X-Idempotency-Key: " . uniqid('', true)
        ],
        CURLOPT_SSL_VERIFYPEER => false 
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
        // Retorna erro em JSON para o front tratar
        echo json_encode(['erro' => true, 'msg' => "Erro cURL: $err"]);
        exit;
    }

    $retorno = json_decode($response, true);

    // Verifica Sucesso
    if (isset($retorno['status']) && ($retorno['status'] == 'processed' || $retorno['status'] == 'open' || $retorno['status'] == 'closed')) {
        
        $pagamento = $retorno['transactions'][0]['payments'][0] ?? [];
        $status_pag = $pagamento['status'] ?? 'pending';
        
        // Atualiza banco
        $pdo->prepare("UPDATE tb_pedidos SET status_pagamento = ? WHERE id = ?")
            ->execute([$status_pag, $pedido_id]);

        unset($_SESSION['carrinho']);

        // Se for Pix, retorna o QR Code para o front-end
        if ($payment_method_id == 'pix') {
            $qr_code = $pagamento['transaction_details']['qr_code'] ?? null; 
            $qr_code_base64 = $pagamento['transaction_details']['qr_code_base64'] ?? null;
            
            // Salva na sessão para exibir na próxima tela
            $_SESSION['pix_copia_cola'] = $qr_code;
            $_SESSION['pix_imagem'] = $qr_code_base64;
            
            header("Location: " . $base_url . "/pedido_pix.php?id=$pedido_id");
        } else {
            header("Location: " . $base_url . "/perfil.php?sucesso=pedido_realizado");
        }
        exit;

    } else {
        // Exibe erro na tela
        echo "<h2>Erro na API de Orders</h2>";
        echo "<pre>"; print_r($retorno); echo "</pre>";
        echo "<p>JSON Enviado:</p>";
        echo "<pre>"; echo json_encode($payload, JSON_PRETTY_PRINT); echo "</pre>";
        exit;
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    die("Erro interno: " . $e->getMessage());
}
?>