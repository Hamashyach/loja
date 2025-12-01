<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../../config.php'; 
header('Content-Type: application/json');

try {
    $termoBusca = $_GET['q'] ?? '';

    $sql = "SELECT TSE_CODIGO as id, TSE_DESCRICAO as nome, TSE_VLUNITARIO as tse_vlunitario
            FROM TIPO_SERVICO
            WHERE TSE_DESCRICAO LIKE :termo OR TSE_CODIGO LIKE :termo OR TSE_VLUNITARIO LIKE :termo
            ORDER BY TSE_DESCRICAO ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute(['termo' => '%' . $termoBusca . '%']);
    
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($resultados);

} catch (PDOException $e) {
    http_response_code(500); 
    echo json_encode(['erro' => 'Falha na consulta: ' . $e->getMessage()]);
}
?>