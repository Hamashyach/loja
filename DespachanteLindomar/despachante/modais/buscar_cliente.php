<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../../config.php'; 
header('Content-Type: application/json');

try {
    $termoBusca = $_GET['q'] ?? '';

    $sql = "SELECT CODIGO as id, NOME as nome, CPF_CNPJ as cpf_cnpj, ENDERECO as endereco, NUMERO as numero, BAIRRO as bairro
            FROM CLIENTE 
            WHERE NOME LIKE :termo OR CODIGO LIKE :termo OR CPF_CNPJ LIKE :termo OR ENDERECO LIKE :termo OR NUMERO LIKE :termo OR BAIRRO LIKE :termo
            ORDER BY NOME ASC 
            LIMIT 50";

    $stmt = $conn->prepare($sql);
    $stmt->execute(['termo' => '%' . $termoBusca . '%']);
    
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($resultados);

} catch (PDOException $e) {
    http_response_code(500); 
    echo json_encode(['erro' => 'Falha na consulta: ' . $e->getMessage()]);
}
?>