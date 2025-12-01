<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../../config.php'; 
header('Content-Type: application/json');

try {
    $termoBusca = $_GET['q'] ?? '';

    $sql = "SELECT COD_COMBU as id, DESCRICAO as nome 
            FROM COMBUSTIVEL 
            WHERE DESCRICAO LIKE :termo OR COD_COMBU LIKE :termo
            ORDER BY DESCRICAO ASC ";

    $stmt = $conn->prepare($sql);
    $stmt->execute(['termo' => '%' . $termoBusca . '%']);
    
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($resultados);

} catch (PDOException $e) {
    http_response_code(500); 
    echo json_encode(['erro' => 'Falha na consulta: ' . $e->getMessage()]);
}
?>