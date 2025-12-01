<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../../config.php'; 
header('Content-Type: application/json');

try {
    $termoBusca = $_GET['q'] ?? '';

    $sql = "SELECT CODIGO as id, MODELO as nome, PLACA_UF as placa, RENAVAM as renavam, CHASSI as chassi
            FROM VEICULO 
            WHERE MODELO LIKE :termo OR CODIGO LIKE :termo OR PLACA_UF LIKE :termo OR RENAVAM LIKE :termo OR CHASSI LIKE :termo
            ORDER BY CODIGO ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute(['termo' => '%' . $termoBusca . '%']);
    
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($resultados);

} catch (PDOException $e) {
    http_response_code(500); 
    echo json_encode(['erro' => 'Falha na consulta: ' . $e->getMessage()]);
}
?>