<?php
// Arquivo: despachante/api_busca_processos_concluidos.php
require_once '../config.php';
header('Content-Type: application/json');

$query = $_GET['query'] ?? '';
$searchTerm = '%' . $query . '%';

// ASSUMA que o código da situação "Finalizado" é 3.
// Se for diferente, ajuste o valor da variável abaixo.
$statusFinalizado = 3;

$sql = "
    SELECT 
        p.PRS_CODIGO, 
        ts.TSE_DESCRICAO AS SERVICO_NOME, 
        v.PLACA_UF AS VEICULO_PLACA,
        c.NOME AS CLIENTE_NOME
    FROM PROCESSO p
    LEFT JOIN TIPO_SERVICO ts ON p.PRS_TSE_CODIGO = ts.TSE_CODIGO
    LEFT JOIN VEICULO v ON p.PRS_VEI_CODIGO = v.CODIGO
    LEFT JOIN CLIENTE c ON p.PRS_CPF_CNPJ = c.CPF_CNPJ
    WHERE 
        p.PRS_PSI_CODIGO = :status 
        AND (
            CAST(p.PRS_CODIGO AS CHAR) LIKE :searchTerm OR 
            v.PLACA_UF LIKE :searchTerm OR 
            c.NOME LIKE :searchTerm
        )
    ORDER BY p.PRS_CODIGO DESC
    LIMIT 10
";

try {
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':status', $statusFinalizado, PDO::PARAM_INT);
    $stmt->bindValue(':searchTerm', $searchTerm, PDO::PARAM_STR);
    $stmt->execute();
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>