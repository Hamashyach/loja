<?php
require_once '../config.php';

header('Content-Type: application/json');

$query = $_GET['query'] ?? '';
$searchTerm = '%' . $query . '%';
$params = [':searchTerm' => $searchTerm];

try {
    // Se a busca estiver vazia, apenas busca as 10 mais recentes.
    // Se não, busca por código, nome do cliente ou placa do veículo.
    $sql = "
        SELECT 
            os.ORS_CODIGO,
            os.ORS_DTEMISSAO,
            c.NOME AS CLIENTE_NOME,
            v.PLACA_UF
        FROM ORDEM_SERVICO AS os
        LEFT JOIN CLIENTE AS c ON os.ORS_CPF_CNPJ = c.CPF_CNPJ
        LEFT JOIN VEICULO AS v ON os.ORS_VEI_CODIGO = v.CODIGO
    ";

    if (!empty($query)) {
    $sql .= "
        WHERE 
            c.NOME LIKE :searchTerm OR 
            v.PLACA_UF LIKE :searchTerm
    ";
    
    if (is_numeric($query)) {
        $sql .= " OR os.ORS_CODIGO = :codigo_num ";
        $params[':codigo_num'] = (int)$query;
    }
}

    $sql .= "
        ORDER BY os.ORS_CODIGO DESC
        LIMIT 10
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    // Adiciona o parâmetro apenas se a busca não estiver vazia
    if (!empty($query)) {
        $stmt->bindValue(':searchTerm', $searchTerm, PDO::PARAM_STR);
    }
    
    $stmt->execute();
    $ordens_servico = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($ordens_servico);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}