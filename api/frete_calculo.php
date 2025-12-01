<?php
// api/frete_calculo.php
header('Content-Type: application/json');

$cep_destino = $_POST['cep'] ?? '';
$peso_total = $_POST['peso'] ?? 1; // Peso em Kg
$valor_total = $_POST['valor'] ?? 0;

// CEP de Origem (Sua Loja - Configure aqui)
$cep_origem = '18550636'; // Ex: Boituva

if (strlen($cep_destino) < 8) {
    echo json_encode(['erro' => true, 'msg' => 'CEP inválido']);
    exit;
}

// Função para consultar API (Exemplo simplificado usando API pública ou fallback)
function consultarCorreios($servico, $cep_origem, $cep_destino, $peso) {
    // Códigos: 04014 = SEDEX, 04510 = PAC
    $cod_servico = ($servico == 'SEDEX') ? '04014' : '04510';
    
    // URL da API oficial (pode precisar de contrato no futuro)
    // Como fallback rápido, vamos simular uma lógica baseada em estado para não travar seu teste
    // Se quiser usar API real, precisaria de cURL aqui.
    
    // --- SIMULAÇÃO INTELIGENTE (PARA EVITAR TRAVAMENTO DA API DOS CORREIOS) ---
    // Em produção, substitua por uma biblioteca como "FlyingLuscas/Correios-PHP"
    
    $estado_origem = 'SP'; // Seu estado
    
    // Lógica fictícia de preço baseada apenas para teste funcional
    $preco = ($servico == 'SEDEX') ? 25.00 : 15.00;
    $prazo = ($servico == 'SEDEX') ? 3 : 8;
    
    // Se o CEP for do mesmo estado (simplificado), é mais barato
    // Isso é apenas um PLACEHOLDER para seu sistema funcionar agora.
    
    return [
        'preco' => number_format($preco, 2, ',', '.'),
        'valor' => $preco, // Valor float para soma
        'prazo' => $prazo,
        'erro' => false
    ];
}

$pac = consultarCorreios('PAC', $cep_origem, $cep_destino, $peso_total);
$sedex = consultarCorreios('SEDEX', $cep_origem, $cep_destino, $peso_total);

echo json_encode([
    'pac' => $pac,
    'sedex' => $sedex
]);
?>