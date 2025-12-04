<?php 
session_start();
require '../bd/config.php';

//  CONFIGURAÇÕES  
$url_base = 'https://sandbox.melhorenvio.com.br/api/v2/me'; // Mude para 'https://melhorenvio.com.br/api/v2/me' em produção

// Dados do Remetente 
$remetente = [
    'name' => 'Lion Company',
    'phone' => '(74) 99956-4070', 
    'email' => 'contato@LionCompany.com',
    'document' => '28296272881', 
    'address' => 'Av. Adolfo Moitinho',
    'complement' => '',
    'number' => '100', 
    'district' => 'Centro', 
    'city' => 'Irecê',
    'state_abbr' => 'BA',
    'country_id' => 'BR',
    'postal_code' => '44860157', 
];

//  FUNÇÃO PARA CHAMADAS NA API 
function requestMelhorEnvio($endpoint, $method = 'GET', $data = [], $token = ME_TOKEN) {
    global $url_base;
    
    $curl = curl_init();
    
    $headers = [
        "Accept: application/json",
        "Content-Type: application/json",
        "Authorization: Bearer " . $token, 
        "User-Agent: LionCompany (contato@lioncompany.com)"
    ];

    $options = [
        CURLOPT_URL => $url_base . $endpoint,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => false // Em produção, mude para true
    ];

    if (!empty($data)) {
        $options[CURLOPT_POSTFIELDS] = json_encode($data);
    }

    curl_setopt_array($curl, $options);
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    
    curl_close($curl);

    if ($err) {
        die("Erro cURL: $err");
    }

    return ['code' => $http_code, 'body' => json_decode($response, true)];
}

// 1. BUSCAR DADOS DO PEDIDO 
$id_pedido = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id_pedido) die("ID do pedido inválido.");

// Busca pedido e cliente
$stmt = $pdo->prepare("
    SELECT p.*, c.cliente_nome, c.cliente_sobrenome, c.cliente_email, c.cliente_contato, c.cliente_cpf
    FROM tb_pedidos p
    JOIN tb_client_users c ON p.cliente_id = c.id
    WHERE p.id = ?
");
$stmt->execute([$id_pedido]);
$pedido = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pedido) die("Pedido não encontrado.");
if (!$pedido['frete_servico_id']) die("Erro: Este pedido não possui um serviço de frete selecionado.");

// Busca itens do pedido
// Nota: Verifique se suas colunas são peso/altura ou peso_kg/altura_cm no banco
$stmt_itens = $pdo->prepare("
    SELECT i.quantidade, i.preco_unitario, pr.nome, pr.peso, pr.altura, pr.largura, pr.comprimento 
    FROM tb_itens_pedido i
    JOIN tb_produtos pr ON i.produto_id = pr.id
    WHERE i.pedido_id = ?
");
$stmt_itens->execute([$id_pedido]);
$itens = $stmt_itens->fetchAll(PDO::FETCH_ASSOC);

if (empty($itens)) die("Pedido sem itens.");

// 2. PREPARAR PRODUTOS E VOLUME 
$produtos_api = [];
$volume_total = [
    'weight' => 0,
    'height' => 0,
    'width' => 0,
    'length' => 0
];

foreach ($itens as $item) {
    // Adiciona à lista de produtos (para declaração)
    $produtos_api[] = [
        'name' => $item['nome'],
        'quantity' => (int)$item['quantidade'],
        'unitary_value' => (float)$item['preco_unitario'],
    ];

    // Lógica simples de empilhamento
    $qtd = (int)$item['quantidade'];
    $volume_total['weight'] += ((float)$item['peso'] * $qtd);
    $volume_total['height'] += ((int)$item['altura'] * $qtd);
    
    if ($item['largura'] > $volume_total['width']) {
        $volume_total['width'] = (int)$item['largura'];
    }
    
    if ($item['comprimento'] > $volume_total['length']) {
        $volume_total['length'] = (int)$item['comprimento'];
    }
}

// Garante dimensões mínimas
$volume_total['height'] = max($volume_total['height'], 2);
$volume_total['width']  = max($volume_total['width'], 11);
$volume_total['length'] = max($volume_total['length'], 16);


// 3. MONTAR PAYLOAD PARA ADICIONAR AO CARRINHO 
$payload_cart = [
    'service' => (int)$pedido['frete_servico_id'],
    'agency' => null, 
    'from' => $remetente,
    'to' => [
        'name' => $pedido['cliente_nome'] . ' ' . $pedido['cliente_sobrenome'],
        'phone' => preg_replace('/[^0-9]/', '', $pedido['cliente_contato']), 
        'email' => $pedido['cliente_email'],
        'document' => preg_replace('/[^0-9]/', '', $pedido['cliente_cpf']), 
        'address' => $pedido['entrega_endereco'],
        'complement' => $pedido['entrega_complemento'],
        'number' => $pedido['entrega_numero'],
        'district' => $pedido['entrega_bairro'],
        'city' => $pedido['entrega_cidade'],
        'state_abbr' => $pedido['entrega_estado'],
        'country_id' => 'BR',
        'postal_code' => preg_replace('/[^0-9]/', '', $pedido['entrega_cep']),
    ],
    'products' => $produtos_api, 
    'volumes' => [$volume_total], 
    'options' => [
        'receipt' => false, 
        'own_hand' => false, 
        'insurance_value' => (float)$pedido['valor_total'],
        'non_commercial' => true
    ]
];

// 4. EXECUTAR A API (Calls corrigidos)

// A) Adicionar ao Carrinho
// Passamos o token como 4º argumento opcional ou ele pega o global definido no inicio
$resp_cart = requestMelhorEnvio('/cart', 'POST', $payload_cart); 

if ($resp_cart['code'] !== 201 || !isset($resp_cart['body']['id'])) {
    echo "<h3>Erro ao adicionar ao carrinho:</h3>";
    echo "<pre>"; print_r($resp_cart['body']); echo "</pre>";
    exit;
}

$id_pedido_me = $resp_cart['body']['id']; 
echo "<p>Etiqueta adicionada ao carrinho (ID: $id_pedido_me)...</p>";

// B) Checkout 
$resp_checkout = requestMelhorEnvio('/shipment/checkout', 'POST', ['orders' => [$id_pedido_me]]);

if ($resp_checkout['code'] !== 200 || (isset($resp_checkout['body']['purchase']['error']) && !empty($resp_checkout['body']['purchase']['error']))) {
    echo "<h3>Erro no Checkout (Verifique seu saldo no Melhor Envio):</h3>";
    echo "<pre>"; print_r($resp_checkout['body']); echo "</pre>";
    exit;
}
echo "<p>Checkout realizado com sucesso...</p>";

// C) Gerar Etiqueta
$resp_generate = requestMelhorEnvio('/shipment/generate', 'POST', ['orders' => [$id_pedido_me]]);

if ($resp_generate['code'] !== 200) {
    echo "<h3>Erro ao gerar etiqueta:</h3>";
    echo "<pre>"; print_r($resp_generate['body']); echo "</pre>";
    exit;
}
echo "<p>Etiqueta gerada...</p>";

// D) Imprimir (Pegar URL)
$resp_print = requestMelhorEnvio('/shipment/print', 'POST', ['mode' => 'public', 'orders' => [$id_pedido_me]]);

if ($resp_print['code'] === 200 && isset($resp_print['body']['url'])) {
    $link_pdf = $resp_print['body']['url'];
    
    if (is_array($link_pdf)) {
        $link_pdf = reset($link_pdf);
    }
    
    // Atualiza o banco de dados com o link
    $pdo->prepare("UPDATE tb_pedidos SET etiqueta_url = ? WHERE id = ?")->execute([$link_pdf, $id_pedido]);
    
    // Redireciona para a etiqueta
    header("Location: $link_pdf");
    exit;
} else {
    echo "<h3>Erro ao obter link de impressão. Tente novamente em alguns instantes.</h3>";
    echo "<pre>"; print_r($resp_print['body']); echo "</pre>";
}
?>