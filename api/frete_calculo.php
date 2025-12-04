<?php
session_start();
require '../bd/config.php';

header('Content-Type: application/json');

// --- CONFIGURAÇÕES DO MELHOR ENVIO  ---
$token_melhor_envio = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiI5NTYiLCJqdGkiOiJjOGU2MGJkNmYzNzhmNzkzOGZiZWZmNTFlZjFlN2UyOWEzNzgxZmYzY2YwNGEzZDNhZDk1MWJjNDc0MDFlZjEzMGUxMjQ2NDI4ODc5YWY2NCIsImlhdCI6MTc2NDU5NTc4Ny4wOTE3NTksIm5iZiI6MTc2NDU5NTc4Ny4wOTE3NjIsImV4cCI6MTc5NjEzMTc4Ny4wODQ1Niwic3ViIjoiYTA3MzkzYTMtNmRkNy00NGU0LWI0NzUtNGZjOGMwYjkyNzAzIiwic2NvcGVzIjpbImNhcnQtcmVhZCIsImNhcnQtd3JpdGUiLCJjb21wYW5pZXMtcmVhZCIsImNvbXBhbmllcy13cml0ZSIsImNvdXBvbnMtcmVhZCIsImNvdXBvbnMtd3JpdGUiLCJub3RpZmljYXRpb25zLXJlYWQiLCJvcmRlcnMtcmVhZCIsInByb2R1Y3RzLXJlYWQiLCJwcm9kdWN0cy1kZXN0cm95IiwicHJvZHVjdHMtd3JpdGUiLCJwdXJjaGFzZXMtcmVhZCIsInNoaXBwaW5nLWNhbGN1bGF0ZSIsInNoaXBwaW5nLWNhbmNlbCIsInNoaXBwaW5nLWNoZWNrb3V0Iiwic2hpcHBpbmctY29tcGFuaWVzIiwic2hpcHBpbmctZ2VuZXJhdGUiLCJzaGlwcGluZy1wcmV2aWV3Iiwic2hpcHBpbmctcHJpbnQiLCJzaGlwcGluZy1zaGFyZSIsInNoaXBwaW5nLXRyYWNraW5nIiwiZWNvbW1lcmNlLXNoaXBwaW5nIiwidHJhbnNhY3Rpb25zLXJlYWQiLCJ1c2Vycy1yZWFkIiwidXNlcnMtd3JpdGUiLCJ3ZWJob29rcy1yZWFkIiwid2ViaG9va3Mtd3JpdGUiLCJ3ZWJob29rcy1kZWxldGUiLCJ0ZGVhbGVyLXdlYmhvb2siXX0.ZB4fMEnxgJPkOJjbjXyrz-wFEAorSwGsHPktnsaICgRirqmK8wmRBIkRNxKpxhBdekvoY5zRjlwC0Jcgp3hLwBZTkkRkcQ3HE0PIxlPMf4tUF5ChNqiRwD25yo9SiUozTK2E-KFQZ-BWvg3ixmTtvZM0WNooiOyQIcvaeyjbRw2DyT2Z7rhxSkkMsfqu9FUMKon0sesoSvUx58jfiPuls7cFYBHlJ4rdE75SF3TEVL0y8EDypw6Sk4v0qU3gTNFxUQuCraykmM4w3GTesjtOv3hAtkejdzblj5xKuD8C0JBb_VIIGDa99mdT-LxmDtLn5kDmhlitN7f5nIvY2n0rHg02wbX46ATrJvPQ52ACi3-PWlJ6M4AJwtMQVNIZNDS8KxCw8j-vcfdS2y3QBQ7UVzOZVW7pPR22Tdci4qGYpSZKzrlajW0KemHvtR8kJ6PeEa97kO3NFnQAdJLC5o__i7CLc6kZ-PrnJK_Uvb45E7jqpRl6P6_k9y3BggValPghKOSXWieIUT9RlZqiovZF-JtXT6Dvco15V3ReDkibBmVaAONwlnPNpEHwNmnEtVFrok4CoUWmmjvqwqU3PhB9iNX9inxoENZIHV9ETOLv5j1jRPpZQnLEnzWc85ZtuK4ojP8s3TYP5v9SNjJCjli4xLeEPxDUcr_4QNH2dC3imoM'; 
$cep_origem = '44860157'; 
$url_api = 'https://sandbox.melhorenvio.com.br/api/v2/me/shipment/calculate'; 

$cep_destino = preg_replace('/[^0-9]/', '', $_POST['cep'] ?? '');

if (strlen($cep_destino) !== 8) {
    echo json_encode(['erro' => true, 'msg' => 'CEP inválido']);
    exit;
}

if (empty($_SESSION['carrinho'])) {
    echo json_encode(['erro' => true, 'msg' => 'Carrinho vazio']);
    exit;
}

// Montar a lista de produtos para a API
$produtos_api = [];

try {
    foreach ($_SESSION['carrinho'] as $produto_id => $item) {
        $stmt = $pdo->prepare("SELECT nome, preco, preco_promocional, peso_kg, altura_cm, largura_cm, comprimento_cm FROM tb_produtos WHERE id = ?");
        $stmt->execute([$produto_id]);
        $prod = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($prod) {
            $preco_final = ($prod['preco_promocional'] > 0) ? $prod['preco_promocional'] : $prod['preco'];
            
            $produtos_api[] = [
                'id' => (string)$produto_id,
                'width' => (int)$prod['largura_cm'],
                'height' => (int)$prod['altura_cm'],
                'length' => (int)$prod['comprimento_cm'],
                'weight' => (float)$prod['peso_kg'],
                'insurance_value' => (float)$preco_final,
                'quantity' => (int)$item['quantidade']
            ];

            $pacotes_api[] =[
                "width" => (int)$prod['largura_cm'],
                "height" => (int)$prod['altura_cm'],
                "length"=>(int)$prod['comprimento_cm'],
                "weight" => (float)$prod['peso_kg'],
                "insurance"=> (float)$preco_final 
            ];
        }
    }
} catch (Exception $e) {
    echo json_encode(['erro' => true, 'msg' => 'Erro ao processar produtos']);
    exit;
}

$payload = [
    'from' => [
        'postal_code' => $cep_origem
    ],
    'to' => [
        'postal_code' => $cep_destino
    ],
    'products' => $produtos_api,
    'options' => [
        'receipt' => false, 
        'own_hand' => false 
    ]
];


$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL => $url_api,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Authorization: Bearer ' . $token_melhor_envio,
        'Content-Type: application/json',
        'User-Agent: Aplicação suporte1rpvtecnologia@gmail.com'
    ],

    CURLOPT_SSL_VERIFYPEER => false
]);

$response = curl_exec($curl);
$err = curl_error($curl);
curl_close($curl);

if ($err) {
    echo json_encode(['erro' => true, 'msg' => 'Erro na conexão com transportadora']);
    exit;
}

$cotacoes = json_decode($response, true);


if (isset($cotacoes['message']) || isset($cotacoes['error'])) {
    echo json_encode(['erro' => true, 'msg' => 'Erro ao calcular frete: ' . ($cotacoes['message'] ?? 'Verifique os dados.')]);
    exit;
}


$opcoes_frete = [];
$servicos_permitidos = [1, 2, 18]; 

if (is_array($cotacoes)) {
    foreach ($cotacoes as $cotacao) {
        if (!isset($cotacao['price']) || isset($cotacao['error'])) continue;
        
        $opcoes_frete[] = [
            'id'    => $cotacao['id'],
            'nome' => $cotacao['company']['name'] . ' - ' . $cotacao['name'], 
            'valor' => (float)$cotacao['price'],
            'prazo' => (int)$cotacao['delivery_time'],
            'preco_formatado' => number_format($cotacao['price'], 2, ',', '.'),
            'foto' => $cotacao['company']['picture'] 
        ];
    }
}


usort($opcoes_frete, function($a, $b) {
    return $a['valor'] - $b['valor'];
});


echo json_encode([
    'erro' => false,
    'opcoes' => $opcoes_frete
]);
?>