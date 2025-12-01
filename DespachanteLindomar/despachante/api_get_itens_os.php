<?php
session_start();
require_once '../config.php';


header('Content-Type: application/json');


if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['error' => 'Acesso não autorizado']);
    exit;
}

$os_id = filter_input(INPUT_GET, 'os_id', FILTER_VALIDATE_INT);

if (!$os_id) {
    echo json_encode([]); 
    exit;
}

try {
    
    $sql = "
        SELECT 
            i.ORI_ORS_CODIGO, 
            i.ORI_ITEM, 
            ts.TSE_DESCRICAO
        FROM ORDEM_SERVICO_ITEM i
        JOIN TIPO_SERVICO ts ON i.ORI_TSE_CODIGO = ts.TSE_CODIGO
        LEFT JOIN PROCESSO p ON i.ORI_ORS_CODIGO = p.PRS_ORS_CODIGO AND i.ORI_ITEM = p.PRS_ORI_ITEM
        WHERE i.ORI_ORS_CODIGO = ? AND p.PRS_CODIGO IS NULL
        ORDER BY i.ORI_ITEM ASC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$os_id]);
    $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($itens);

} catch (PDOException $e) {
    
    echo json_encode(['error' => 'Erro no banco de dados: ' . $e->getMessage()]);
}
?>