<?php
require_once '../../config.php';
header('Content-Type: application/json');

$termo = $_GET['q'] ?? '';

try {
    $sql = "SELECT 
                os.ORS_CODIGO, 
                os.ORS_VLTOTAL, 
                os.ORS_CON_CODIGO,
                cp.CON_NOME,
                cli.NOME as CLIENTE_NOME, 
                cli.CPF_CNPJ as CLIENTE_CPF_CNPJ,
                vei.PLACA_UF, 
                vei.MODELO, 
                vei.CODIGO as VEICULO_CODIGO, 
                vei.RENAVAM
            FROM ORDEM_SERVICO os
            LEFT JOIN VEICULO vei ON os.ORS_VEI_CODIGO = vei.CODIGO
            LEFT JOIN CLIENTE cli ON vei.COD_CLI_PROPRIETARIO = cli.CODIGO
            LEFT JOIN CONDICAO_PAGAMENTO cp ON os.ORS_CON_CODIGO = cp.CON_CODIGO
            WHERE (os.ORS_CODIGO LIKE :termo OR cli.NOME LIKE :termo OR vei.PLACA_UF LIKE :termo)
            ORDER BY os.ORS_CODIGO DESC 
            LIMIT 20";
            
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':termo', '%' . $termo . '%');
    $stmt->execute();
    $ordens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $resultado = [];
    foreach ($ordens as $os) {
        // Busca o primeiro serviço da OS separadamente
        $stmt_item = $conn->prepare("SELECT ts.TSE_DESCRICAO, osi.ORI_TSE_CODIGO FROM ORDEM_SERVICO_ITEM osi JOIN TIPO_SERVICO ts ON osi.ORI_TSE_CODIGO = ts.TSE_CODIGO WHERE osi.ORI_ORS_CODIGO = ? LIMIT 1");
        $stmt_item->execute([$os['ORS_CODIGO']]);
        $item = $stmt_item->fetch(PDO::FETCH_ASSOC);

        // --- CORREÇÃO APLICADA AQUI: Nomes de chave padronizados ---
        $resultado[] = [
            'id' => $os['ORS_CODIGO'],
            'nome' => 'OS ' . $os['ORS_CODIGO'] . ' - ' . ($os['CLIENTE_NOME'] ?? 'N/A') . ' (' . ($os['PLACA_UF'] ?? 'N/A') . ')',
            'cliente_nome' => $os['CLIENTE_NOME'],
            'cliente_cpf' => $os['CLIENTE_CPF_CNPJ'],
            'veiculo_codigo' => $os['VEICULO_CODIGO'],
            'placa' => $os['PLACA_UF'],
            'modelo' => $os['MODELO'],
            'renavam' => $os['RENAVAM'],
            'servico_id' => $item['ORI_TSE_CODIGO'] ?? null,
            'servico_nome' => $item['TSE_DESCRICAO'] ?? 'Não especificado',
            'cond_pagamento' => $os['CON_DESCRICAO'] ?? '',
            'valor_total' => $os['ORS_VLTOTAL']
        ];
    }
    
    echo json_encode($resultado);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['erro' => 'Erro ao buscar OS: ' . $e->getMessage()]);
}
?>