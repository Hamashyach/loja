<?php
session_start();
require '../bd/config.php';

header('Content-Type: application/json');

$cep_destino = preg_replace('/[^0-9]/', '', $_POST['cep'] ?? '');

if (strlen($cep_destino) !== 8) {
    echo json_encode(['erro' => true, 'msg' => 'CEP inválido']);
    exit;
}

if (empty($_SESSION['carrinho'])) {
    echo json_encode(['erro' => true, 'msg' => 'Carrinho vazio']);
    exit;
}

$produtos_api = [];

try {
    foreach ($_SESSION['carrinho'] as $produto_id => $item) {
        $stmt = $pdo->prepare("SELECT nome, preco, preco_promocional, peso, altura, largura, comprimento FROM tb_produtos WHERE id = ?");
        $stmt->execute([$produto_id]);
        $prod = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($prod) {
            $preco_final = ($prod['preco_promocional'] > 0) ? $prod['preco_promocional'] : $prod['preco'];
            
            $altura = max((int)$prod['altura'], 2 );
            $largura = max((int)$prod['largura'], 11);
            $comprimento = max((int)$prod['comprimento'], 16);
            $peso = max((float)$prod['peso'], 0.1);

            $produtos_api[] = [
                'id' => (string)$produto_id,
                'width' => $largura,
                'height' => $altura,
                'length' => $comprimento,
                'weight' => $peso,
                'insurance_value' => (float)$preco_final,
                'quantity' => (int)$item['quantidade']
            ];
        }
    }
} catch (Exception $e) {
    echo json_encode(['erro' => true, 'msg' => 'Erro ao processar produtos']);
    exit;
}

$payload = [
    'from' => ['postal_code' => ME_CEP_ORIGEM],
    'to' => ['postal_code' => $cep_destino],
    'products' => $produtos_api,
    'options' => ['receipt' => false, 'own_hand' => false],
];

$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => ME_URL . '/api/v2/me/shipment/calculate',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Authorization: Bearer ' . ME_TOKEN,
        'Content-Type: application/json',
        'User-Agent: LionCompany/1.0 (seuemail@dominio.com)' 
    ],
    CURLOPT_SSL_VERIFYPEER => false 
]);

$response = curl_exec($curl);
$err = curl_error($curl);
$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

if ($err) {
    echo json_encode(['erro' => true, 'msg' => 'Erro CURL: ' . $err]);
    exit;
}

if ($http_code == 401) {
    echo json_encode(['erro' => true, 'msg' => 'Erro de autenticação no frete (Token inválido)']);
    exit;
}

$cotacoes = json_decode($response, true);

$opcoes_frete = [];
if (is_array($cotacoes)) {
    foreach ($cotacoes as $cotacao) {
        if (isset($cotacao['price']) && !isset($cotacao['error'])) {
            $opcoes_frete[] = [
                'id'    => $cotacao['id'], 
                'nome' => $cotacao['company']['name'] . ' - ' . $cotacao['name'], 
                'valor' => (float)$cotacao['price'],
                'prazo' => (int)$cotacao['delivery_time'],
                'preco_formatado' => number_format($cotacao['price'], 2, ',', '.'),
                'foto' => $cotacao['company']['picture'] ?? ''
            ];
        }
    }
}

usort($opcoes_frete, function($a, $b) { return $a['valor'] - $b['valor']; });

echo json_encode(['erro' => false, 'opcoes' => $opcoes_frete]);
?>