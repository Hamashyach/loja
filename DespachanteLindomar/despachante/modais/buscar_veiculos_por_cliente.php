<?php
// despachante/modais/buscar_veiculos_por_cliente.php

require_once '../../config.php';
header('Content-Type: application/json');

$termo = $_GET['q'] ?? '';
$cliente_cpf_cnpj = $_GET['cliente_cpf'] ?? '';

if (empty($cliente_cpf_cnpj)) {
    echo json_encode([]);
    exit;
}

try {
    // --- CORREÇÃO IMPORTANTE ---
    // A função REPLACE remove '.', '-' e '/' do campo do banco de dados antes de comparar.
    // Isso torna a busca imune a diferenças de formatação.
    $sql = "SELECT 
                CODIGO as id, 
                PLACA_UF, 
                MODELO, 
                RENAVAM,
                CHASSI
            FROM VEICULO 
            WHERE REPLACE(REPLACE(REPLACE(CPF_CNPJ, '.', ''), '-', ''), '/', '') = :cliente_cpf_cnpj_limpo
            AND (PLACA_UF LIKE :termo OR MODELO LIKE :termo)
            LIMIT 50";
            
    $stmt = $conn->prepare($sql);

    // Limpa também o CPF/CNPJ recebido do formulário antes de enviar para a query
    $cpf_cnpj_limpo = preg_replace('/[^0-9]/', '', $cliente_cpf_cnpj);

    $stmt->bindValue(':cliente_cpf_cnpj_limpo', $cpf_cnpj_limpo);
    $stmt->bindValue(':termo', '%' . $termo . '%');
    $stmt->execute();
    
    $veiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $resultado = [];
    foreach ($veiculos as $veiculo) {
        $resultado[] = [
            'id' => $veiculo['id'],
            'nome' => $veiculo['PLACA_UF'] . ' - ' . $veiculo['MODELO'],
            'placa' => $veiculo['PLACA_UF'],
            'modelo' => $veiculo['MODELO'],
            'renavam' => $veiculo['RENAVAM'],
            'chassi' => $veiculo['CHASSI']
        ];
    }

    echo json_encode($resultado);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['erro' => 'Falha na consulta: ' . $e->getMessage()]);
}
?>