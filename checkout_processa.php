<?php
session_start();
require 'bd/config.php';

$mp_access_token = 'SEU_ACCESS_TOKEN_AQUI_TESTE_OU_PRODUCAO'; 

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: carrinho.php");
    exit;
}

// Coleta Dados do Formulário
$cliente_id = $_SESSION['cliente_id'];
$endereco_id = $_POST['endereco_id'];
$valor_produtos = $_POST['valor_produtos'];
$valor_frete = $_POST['valor_frete'];
$tipo_frete = $_POST['tipo_frete'];
$valor_total = $valor_produtos + $valor_frete;

// Busca Detalhes do Endereço para salvar no pedido (
$stmt_end = $pdo->prepare("SELECT * FROM tb_client_addresses WHERE id = ?");
$stmt_end->execute([$endereco_id]);
$end = $stmt_end->fetch();

//  Cria o Pedido no Banco de Dados (Status: Pendente)
try {
    $pdo->beginTransaction();

    $sql_pedido = "INSERT INTO tb_pedidos 
        (cliente_id, valor_total, status_pagamento, status_entrega, metodo_pagamento, entrega_cep, entrega_endereco, entrega_numero, entrega_bairro, entrega_cidade, entrega_estado) 
        VALUES (?, ?, 'Pendente', 'Nao Enviado', 'Mercado Pago', ?, ?, ?, ?, ?, ?)";
    
    $stmt = $pdo->prepare($sql_pedido);
    $stmt->execute([
        $cliente_id, 
        $valor_total, 
        $end['cep'], 
        $end['endereco'], 
        $end['numero'], 
        $end['bairro'], 
        $end['cidade'], 
        $end['estado']
    ]);
    
    $pedido_id = $pdo->lastInsertId();

    //  Salva os Itens do Pedido e Monta Array para Mercado Pago
    $items_mp = [];

    foreach ($_SESSION['carrinho'] as $prod_id => $item) {
        // Busca dados atualizados do produto
        $stmt_prod = $pdo->prepare("SELECT nome, preco, preco_promocional FROM tb_produtos WHERE id = ?");
        $stmt_prod->execute([$prod_id]);
        $prod_db = $stmt_prod->fetch();
        
        $preco_real = ($prod_db['preco_promocional'] > 0) ? $prod_db['preco_promocional'] : $prod_db['preco'];
        
        // Salva item no banco
        $stmt_item = $pdo->prepare("INSERT INTO tb_itens_pedido (pedido_id, produto_id, quantidade, preco_unitario) VALUES (?, ?, ?, ?)");
        $stmt_item->execute([$pedido_id, $prod_id, $item['quantidade'], $preco_real]);

        // Adiciona ao array do MP
        $items_mp[] = [
            "title" => $prod_db['nome'],
            "quantity" => (int)$item['quantidade'],
            "currency_id" => "BRL",
            "unit_price" => (float)$preco_real
        ];
    }

    // Adiciona o Frete como um "item" no Mercado Pago (forma simples)
    if ($valor_frete > 0) {
        $items_mp[] = [
            "title" => "Frete ($tipo_frete)",
            "quantity" => 1,
            "currency_id" => "BRL",
            "unit_price" => (float)$valor_frete
        ];
    }

    $pdo->commit();

    // --- INTEGRAÇÃO MERCADO PAGO (SEM COMPOSER - VIA CURL) ---
    
    $preference_data = [
        "items" => $items_mp,
        "payer" => [
            "name" => $_SESSION['cliente_nome'],
            "email" => "cliente_exemplo@test.com", 
        ],
        "back_urls" => [
            "success" => BASE_URL . "/perfil.php?sucesso=compra",
            "failure" => BASE_URL . "/checkout.php?erro=pagamento_falhou",
            "pending" => BASE_URL . "/perfil.php?aviso=pagamento_pendente"
        ],
        "auto_return" => "approved",
        "external_reference" => (string)$pedido_id, 
        "statement_descriptor" => "LION COMPANY"
    ];

    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => "https://api.mercadopago.com/checkout/preferences",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => json_encode($preference_data),
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer " . $mp_access_token,
            "Content-Type: application/json"
        ],
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
        echo "Erro ao conectar com Mercado Pago: " . $err;
    } else {
        $mp_obj = json_decode($response);
        
        if (isset($mp_obj->init_point)) {
            unset($_SESSION['carrinho']);
            
            header("Location: " . $mp_obj->init_point); 
            exit;
        } else {
            echo "Erro na resposta do MP: ";
            print_r($mp_obj);
        }
    }

} catch (Exception $e) {
    $pdo->rollBack();
    die("Erro ao processar pedido: " . $e->getMessage());
}
?>