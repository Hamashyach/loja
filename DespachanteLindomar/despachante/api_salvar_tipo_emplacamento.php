<?php
session_start();
require_once '../verificar_permissao.php';
protegerPagina('acessar_config'); 
require_once '../config.php';


function limparValor($valor) {
    if (!isset($valor) || $valor === '') return null;
    $valor = str_replace('.', '', $valor); 
    $valor = str_replace(',', '.', $valor); 
    return (float)preg_replace('/[^\d\.]/', '', $valor); 
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $EMT_CODIGO = $_POST['EMT_CODIGO'] ?: null;

    $dados = [
        'EMT_DESCRICAO' => $_POST['EMT_DESCRICAO'] ?? null,
        'EMT_ALIQUOTA' => limparValor($_POST['EMT_ALIQUOTA'] ?? 0),
        'EMT_DPVAT_VALOR_BASE' => limparValor($_POST['EMT_DPVAT_VALOR_BASE'] ?? 0),
        'EMT_ALIQ_JUROS_IPVA' => limparValor($_POST['EMT_ALIQ_JUROS_IPVA'] ?? 0),
        'EMT_ALIQ_MULTA_IPVA' => limparValor($_POST['EMT_ALIQ_MULTA_IPVA'] ?? 0),
        'EMT_DIAS_JUROS_MULTA' => (int)($_POST['EMT_DIAS_JUROS_MULTA'] ?? 0)
    ];

    try {
        if (empty($EMT_CODIGO)) {
            
            $colunas = implode(', ', array_keys($dados));
            $placeholders = implode(', ', array_fill(0, count($dados), '?'));
            $sql = "INSERT INTO EMPLACAMENTO_TIPO ($colunas) VALUES ($placeholders)";
            $stmt = $conn->prepare($sql);
            $stmt->execute(array_values($dados));
        } else {
            
            $set_sql = [];
            foreach (array_keys($dados) as $coluna) {
                $set_sql[] = "$coluna = ?";
            }
            $sql = "UPDATE EMPLACAMENTO_TIPO SET " . implode(', ', $set_sql) . " WHERE EMT_CODIGO = ?";
            $valores = array_values($dados);
            $valores[] = $EMT_CODIGO;
            $stmt = $conn->prepare($sql);
            $stmt->execute($valores);
        }
        header("Location: cadastro_primeiro_emplacamento.php?status=config_success");
        exit;

    } catch (PDOException $e) {
        header("Location: cadastro_primeiro_emplacamento.php?status=config_error&msg=" . urlencode($e->getMessage()));
        exit;
    }
}
?>