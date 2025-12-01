<?php
require_once '../config.php';
header('Content-Type: application/json');

$query = $_GET['query'] ?? '';
$searchTerm = '%' . $query . '%';

try {
    $sql = "SELECT NOME, CPF_CNPJ FROM CLIENTE 
            WHERE NOME LIKE :searchTerm 
            OR CPF_CNPJ LIKE :searchTerm 
            ORDER BY NOME LIMIT 10";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':searchTerm', $searchTerm, PDO::PARAM_STR);
    $stmt->execute();
    
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>