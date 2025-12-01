<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../../config.php'; 
header('Content-Type: application/json');

try {
    $termoBusca = $_GET['q'] ?? '';

    if(!empty($termoBusca)){
    $sql = "SELECT COD_MUNI as id, MUNICIPIO as nome, ESTADO as uf FROM MUNICIPIO WHERE MUNICIPIO LIKE ? ORDER BY MUNICIPIO ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute([ '%' . $termoBusca . '%']);

    }else{
        $sql= "SELECT COD_MUNI as id, MUNICIPIO as nome, ESTADO as uf FROM MUNICIPIO ORDER BY MUNICIPIO ASC";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
    }
    
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($resultados);

} catch (PDOException $e) {
    http_response_code(500); 
    echo json_encode(['erro' => 'Falha na consulta: ' . $e->getMessage()]);
}
?>