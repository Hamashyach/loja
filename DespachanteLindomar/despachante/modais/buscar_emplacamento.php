<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../../config.php'; 
header('Content-Type: application/json');

try {
    $termoBusca = $_GET['q'] ?? '';

    $sql = "SELECT EMT_CODIGO as id, EMT_DESCRICAO as nome, EMT_ALIQUOTA as emt_aliquota
            FROM EMPLACAMENTO_TIPO
            WHERE EMT_DESCRICAO LIKE :termo OR EMT_CODIGO LIKE :termo OR EMT_ALIQUOTA LIKE :termo
            ORDER BY EMT_DESCRICAO ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute(['termo' => '%' . $termoBusca . '%']);
    
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($resultados);

} catch (PDOException $e) {
    http_response_code(500); 
    echo json_encode(['erro' => 'Falha na consulta: ' . $e->getMessage()]);
}
?>