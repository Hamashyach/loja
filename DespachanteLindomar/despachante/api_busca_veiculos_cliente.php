<?php
require_once '../config.php';
header('Content-Type: application/json');

$cpf_cnpj = $_GET['cpf_cnpj'] ?? '';

if (empty($cpf_cnpj)) {
    echo json_encode([]);
    exit;
}

try {

    $sql = "SELECT CODIGO, PLACA_UF, MODELO, RENAVAM FROM VEICULO 
            WHERE CPF_CNPJ = :cpf_cnpj 
            ORDER BY MODELO";
            
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':cpf_cnpj', $cpf_cnpj, PDO::PARAM_STR);
    $stmt->execute();
    
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>