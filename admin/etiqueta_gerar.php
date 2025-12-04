<?php 
session_start();
require '../bd/config.php';

header('Content-Type: application/json');

$cep_origem = '18550636';
$nome_remetente = 'Lion Company';
$cpf_cnpj_remetente = '00000000000'; 

$id_pedido = (int)$_GET['id'];

if (!$id_pedido) die("ID do pedido inválido.");

$stmt = $pdo->prepare("
    SELECT p.*, c.cliente_nome, c.cliente_sobrenome, c.cliente_email, c.cliente_contato
    FROM tb_pedidos p
    JOIN tb_client_users c ON p.cliente_id = c.id
    WHERE p.id = ?
");
$stmt->execute([$id_pedido]);
$pedido = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pedido['frete_servico_id']) die("Este pedido não tem um ID de frete salvo (foi feito antes da atualização).");

$stmt_itens = $pdo->prepare("
    SELECT i.quantidade, i.preco_unitario, pr.nome, pr.peso_kg, pr.altura_cm, pr.largura_cm, pr.comprimento_cm 
    FROM tb_itens_pedido i
    JOIN tb_produtos pr ON i.produto_id = pr.id
    WHERE i.pedido_id = ?
");
$stmt_itens->execute([$id_pedido]);
$itens = $stmt_itens->fetchAll(PDO::FETCH_ASSOC);

$produtos_api = [];
foreach ($itens as $item) {
    $produtos_api[] = [
        'name' => $item['nome'],
        'quantity' => (int)$item['quantidade'],
        'unitary_value' => (float)$item['preco_unitario'],
        'weight' => (float)$peso,
        'height' => (int)$alt,
        'width'  => (int)$larg,
        'length' => (int)$comp
    ];
}

// 3. Montar Payload para ADICIONAR AO CARRINHO DO MELHOR ENVIO
$payload_cart = [
    'service' => (int)$pedido['frete_servico_id'],
    'from' => [
        'name' => $nome_remetente,
        'phone' => '(74) 99956-4070', 
        'email' => 'contato@LionCompany.com',
        'document' => $cpf_cnpj_remetente,
        'address' => 'Av. Adolfo Mofithinho',
        'complement' => '',
        'number' => 'Av. Adolfo Mofithinho',
        'district' => 'Irecê',
        'city' => 'Bahia',
        'state_abbr' => 'BA',
        'country_id' => 'BR',
        'postal_code' => $cep_origem,
    ],
    'to' => [
        'name' => $pedido['cliente_nome'] . ' ' . $pedido['cliente_sobrenome'],
        'phone' => $pedido['cliente_contato'], 
        'email' => $pedido['cliente_email'],
        'document' => '00000000000', 
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
    'volumes' => [ 
        [
            'height' => 15, 'width' => 20, 'length' => 20, 'weight' => 1
        ]
    ],
    'options' => [
        'receipt' => false,
        'own_hand' => false,
        'insurance_value' => (float)$pedido['valor_total']
    ]
];

$curl = curl_init();

curl_setopt_array($curl, [
  CURLOPT_URL => "https://sandbox.melhorenvio.com.br/api/v2/me/shipment/generate",
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => "",
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 30,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => "POST",
  CURLOPT_POSTFIELDS => json_encode([
    'orders' => [
        'string'
    ]
  ]),
  CURLOPT_HTTPHEADER => [
    "Accept: application/json",
    "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiI5NTYiLCJqdGkiOiJjOGU2MGJkNmYzNzhmNzkzOGZiZWZmNTFlZjFlN2UyOWEzNzgxZmYzY2YwNGEzZDNhZDk1MWJjNDc0MDFlZjEzMGUxMjQ2NDI4ODc5YWY2NCIsImlhdCI6MTc2NDU5NTc4Ny4wOTE3NTksIm5iZiI6MTc2NDU5NTc4Ny4wOTE3NjIsImV4cCI6MTc5NjEzMTc4Ny4wODQ1Niwic3ViIjoiYTA3MzkzYTMtNmRkNy00NGU0LWI0NzUtNGZjOGMwYjkyNzAzIiwic2NvcGVzIjpbImNhcnQtcmVhZCIsImNhcnQtd3JpdGUiLCJjb21wYW5pZXMtcmVhZCIsImNvbXBhbmllcy13cml0ZSIsImNvdXBvbnMtcmVhZCIsImNvdXBvbnMtd3JpdGUiLCJub3RpZmljYXRpb25zLXJlYWQiLCJvcmRlcnMtcmVhZCIsInByb2R1Y3RzLXJlYWQiLCJwcm9kdWN0cy1kZXN0cm95IiwicHJvZHVjdHMtd3JpdGUiLCJwdXJjaGFzZXMtcmVhZCIsInNoaXBwaW5nLWNhbGN1bGF0ZSIsInNoaXBwaW5nLWNhbmNlbCIsInNoaXBwaW5nLWNoZWNrb3V0Iiwic2hpcHBpbmctY29tcGFuaWVzIiwic2hpcHBpbmctZ2VuZXJhdGUiLCJzaGlwcGluZy1wcmV2aWV3Iiwic2hpcHBpbmctcHJpbnQiLCJzaGlwcGluZy1zaGFyZSIsInNoaXBwaW5nLXRyYWNraW5nIiwiZWNvbW1lcmNlLXNoaXBwaW5nIiwidHJhbnNhY3Rpb25zLXJlYWQiLCJ1c2Vycy1yZWFkIiwidXNlcnMtd3JpdGUiLCJ3ZWJob29rcy1yZWFkIiwid2ViaG9va3Mtd3JpdGUiLCJ3ZWJob29rcy1kZWxldGUiLCJ0ZGVhbGVyLXdlYmhvb2siXX0.ZB4fMEnxgJPkOJjbjXyrz-wFEAorSwGsHPktnsaICgRirqmK8wmRBIkRNxKpxhBdekvoY5zRjlwC0Jcgp3hLwBZTkkRkcQ3HE0PIxlPMf4tUF5ChNqiRwD25yo9SiUozTK2E-KFQZ-BWvg3ixmTtvZM0WNooiOyQIcvaeyjbRw2DyT2Z7rhxSkkMsfqu9FUMKon0sesoSvUx58jfiPuls7cFYBHlJ4rdE75SF3TEVL0y8EDypw6Sk4v0qU3gTNFxUQuCraykmM4w3GTesjtOv3hAtkejdzblj5xKuD8C0JBb_VIIGDa99mdT-LxmDtLn5kDmhlitN7f5nIvY2n0rHg02wbX46ATrJvPQ52ACi3-PWlJ6M4AJwtMQVNIZNDS8KxCw8j-vcfdS2y3QBQ7UVzOZVW7pPR22Tdci4qGYpSZKzrlajW0KemHvtR8kJ6PeEa97kO3NFnQAdJLC5o__i7CLc6kZ-PrnJK_Uvb45E7jqpRl6P6_k9y3BggValPghKOSXWieIUT9RlZqiovZF-JtXT6Dvco15V3ReDkibBmVaAONwlnPNpEHwNmnEtVFrok4CoUWmmjvqwqU3PhB9iNX9inxoENZIHV9ETOLv5j1jRPpZQnLEnzWc85ZtuK4ojP8s3TYP5v9SNjJCjli4xLeEPxDUcr_4QNH2dC3imoM",
    "Content-Type: application/json",
    "User-Agent: Aplicação suporte1rpvtecnologia@gmail.com"
  ],
  CURLOPT_SSL_VERIFYPEER => false
]);

$response = curl_exec($curl);
$err = curl_error($curl);

curl_close($curl);

if ($err) {
  echo "cURL Error #:" . $err;
} else {
  echo $response;
}

// Adicionar ao Carrinho
$resp_cart = callApi("$url_base/cart", $payload_cart, $token);

if (isset($resp_cart['errors'])) {
    echo "<pre>"; print_r($resp_cart); echo "</pre>";
    die("Erro ao adicionar ao carrinho do Melhor Envio.");
}

$id_etiqueta_me = $resp_cart['id']; 


$resp_checkout = callApi("$url_base/shipment/checkout", ['orders' => [$id_etiqueta_me]], $token);

if (isset($resp_checkout['errors'])) {

    echo "Erro no checkout (Saldo insuficiente na Sandbox?): ";
    print_r($resp_checkout);
    exit;
}

//  Gerar Etiqueta
$resp_generate = callApi("$url_base/shipment/generate", ['orders' => [$id_etiqueta_me]], $token);
$resp_print = callApi("$url_base/shipment/print", ['mode' => 'public', 'orders' => [$id_etiqueta_me]], $token);

if (isset($resp_print['url'])) {
    $link_pdf = $resp_print['url'];
    
    $pdo->prepare("UPDATE tb_pedidos SET etiqueta_url = ? WHERE id = ?")->execute([$link_pdf, $id_pedido]);
    header("Location: $link_pdf");
    exit;
} else {
    echo "Etiqueta gerada, mas link ainda não disponível. Tente novamente em alguns segundos.";
}
?>
?>