<?php
function gerarEtiquetaMelhorEnvio($pedido_id, $dados_cliente, $endereco_destino, $itens, $servico_id) {
    
    $volumes = [];
    $products_payload = []; 

    foreach ($itens as $item) {

        $volumes[] = [
            'width' => 20, 
            'height' => 5, 
            'length' => 20,
            'weight' => 0.3, 
            'insurance_value' => (float)$item['preco'],
            'quantity' => (int)$item['quantidade']
        ];

        $products_payload[] = [
            'name' => $item['nome'], 
            'quantity' => (int)$item['quantidade'],
            'unitary_value' => (float)$item['preco']
        ];
    }

    //  Payload do Carrinho
    $payload_cart = [
        'service' => $servico_id, 
        'from' => [
            'name' => 'Lion Company',
            'phone' => '74999564070', 
            'email' => 'contato@lioncompany.com',
            'document' => '00000000000', 
            'address' => 'Av. Adolfo Moitinho',
            'complement' => '',
            'number' => '395',
            'district' => 'Centro', 
            'city' => 'Irecê',
            'country_id' => 'BR',
            'postal_code' => ME_CEP_ORIGEM, 
            'state_abbr' => 'BA'
        ],
        'to' => [
            'name' => $dados_cliente['cliente_nome'] . ' ' . $dados_cliente['cliente_sobrenome'],
            'phone' => $dados_cliente['cliente_contato'], 
            'email' => $dados_cliente['cliente_email'],
            'document' => preg_replace('/[^0-9]/', '', $dados_cliente['cliente_cpf']), 
            'address' => $endereco_destino['endereco'],
            'complement' => $endereco_destino['complemento'] ?? '',
            'number' => $endereco_destino['numero'],
            'district' => $endereco_destino['bairro'],
            'city' => $endereco_destino['cidade'],
            'country_id' => 'BR',
            'postal_code' => preg_replace('/[^0-9]/', '', $endereco_destino['cep']),
            'state_abbr' => $endereco_destino['estado'],
            'note' => "Pedido #$pedido_id"
        ],
        'products' => $products_payload, 
        'volumes' => $volumes,
        'options' => [
            'insurance_value' => (float)$item['preco'], 
            'receipt' => false,
            'own_hand' => false,
            'reverse' => false,
            'non_commercial' => true 
        ]
    ];

    //  Adicionar ao Carrinho 
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => ME_URL . '/api/v2/me/cart',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode($payload_cart),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . ME_TOKEN,
            'Content-Type: application/json',
            'User-Agent: LionCompany/1.0'
        ],
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $resp = curl_exec($curl);
    $info = curl_getinfo($curl);
    curl_close($curl);
    
    $cart_data = json_decode($resp, true);

    if ($info['http_code'] != 201 || !isset($cart_data['id'])) {

        echo "Erro ao adicionar ao carrinho: "; print_r($cart_data);
        return false; 
    }
    
    $id_etiqueta_carrinho = $cart_data['id'];

    // Checkout (Pagar) 
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => ME_URL . '/api/v2/me/shipment/checkout',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode(['orders' => [$id_etiqueta_carrinho]]),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . ME_TOKEN,
            'Content-Type: application/json',
            'User-Agent: LionCompany/1.0'
        ],
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $resp_checkout_raw = curl_exec($curl);
    $resp_checkout = json_decode($resp_checkout_raw, true);
    curl_close($curl);
    
    if (isset($resp_checkout['purchase']['error'])) {
        echo "Erro no Checkout: " . $resp_checkout['purchase']['error'];
        return false;
    }

    //  Gerar Etiqueta 
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => ME_URL . '/api/v2/me/shipment/generate',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode(['orders' => [$id_etiqueta_carrinho]]),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . ME_TOKEN,
            'Content-Type: application/json',
            'User-Agent: LionCompany/1.0'
        ],
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    curl_exec($curl);
    curl_close($curl);

    //  Imprimir 
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => ME_URL . '/api/v2/me/shipment/print',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode(['mode' => 'public', 'orders' => [$id_etiqueta_carrinho]]),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . ME_TOKEN,
            'Content-Type: application/json',
            'User-Agent: LionCompany/1.0'
        ],
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $resp_print_raw = curl_exec($curl);
    $resp_print = json_decode($resp_print_raw, true);
    curl_close($curl);

    if (isset($resp_print['url'])) {
        return $resp_print['url']; 
    }

    return false; 
}
?>