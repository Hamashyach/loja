<?php
session_start();
require_once '../config.php';
require_once '../verificar_permissao.php';


protegerPagina('acessar_financeiro'); 
$usuario_id = $_SESSION['UCIDUSER'] ?? $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: contas_pagar.php");
    exit;
}


$fin_credeb = 'D'; 
$fin_nome = trim($_POST['fin_nome'] ?? '');
$cpf_cnpj = trim($_POST['cpf_cnpj'] ?? '');
$cec_codigo = (int)($_POST['cec_codigo'] ?? 0);
$fin_descricao = trim($_POST['fin_descricao'] ?? 'Nova Despesa');
$fin_obs = trim($_POST['fin_obs'] ?? '');


$fin_valor_str = $_POST['fin_valor'] ?? '0';
$fin_valor = (float)str_replace(',', '.', str_replace('.', '', $fin_valor_str));

$fin_dataemissao = $_POST['fin_dataemissao'] ?? date('Y-m-d');
$fin_datavencimento = $_POST['fin_datavencimento'] ?? date('Y-m-d');



if (empty($fin_nome) || empty($fin_datavencimento) || $cec_codigo === 0 || $fin_valor <= 0) {
    header("Location: contas_pagar.php?status=error&msg=" . urlencode("Dados obrigatórios (Fornecedor, Centro de Custo, Valor ou Vencimento) não preenchidos."));
    exit;
}

try {
    $conn->beginTransaction();

    $sql = "
        INSERT INTO FINANCEIRO_PAGAR_RECEBER 
        (
            FIN_NOME, CPF_CNPJ, CEC_CODIGO, FIN_DESCRICAO, FIN_OBS,
            FIN_VALOR, FIN_VALORTOTAL, 
            FIN_DATAEMISSAO, FIN_DATAVENCIMENTO, 
            FIN_CREDEB, FIN_PARCELA
        ) 
        VALUES 
        (
            ?, ?, ?, ?, ?, 
            ?, ?, 
            ?, ?, 
            'D', 1
        )
    ";

    $stmt = $conn->prepare($sql);
    
    
    $params = [
        $fin_nome,
        $cpf_cnpj,
        $cec_codigo,
        $fin_descricao,
        $fin_obs,
        $fin_valor,
        $fin_valor, 
        $fin_dataemissao . ' 00:00:00', 
        $fin_datavencimento . ' 00:00:00' 
    ];

    $stmt->execute($params);
    
    $conn->commit();

    header("Location: contas_pagar.php?status=success&msg=" . urlencode("Lançamento de despesa salvo com sucesso."));
    exit;

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    
    $msg = "Erro ao salvar lançamento: " . $e->getMessage();
    header("Location: contas_pagar.php?status=error&msg=" . urlencode($msg));
    exit;
}
?>