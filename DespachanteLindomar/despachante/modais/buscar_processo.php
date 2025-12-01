<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../../config.php'; 
header('Content-Type: application/json');

try {
    $termoBusca = $_GET['q'] ?? '';

    $sql = "SELECT PSI_CODIGO as id, PSI_DESCRICAO as nome 
            FROM PROCESSO_SITUACAO
            WHERE PSI_DESCRICAO LIKE :termo OR PSI_CODIGO LIKE :termo
            ORDER BY PSI_DESCRICAO ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute(['termo' => '%' . $termoBusca . '%']);
    
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($resultados);

} catch (PDOException $e) {
    http_response_code(500); 
    echo json_encode(['erro' => 'Falha na consulta: ' . $e->getMessage()]);
}
?>