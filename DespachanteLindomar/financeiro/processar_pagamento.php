<?php
session_start();
require_once '../config.php';
require_once '../verificar_permissao.php';
protegerPagina('acessar_financeiro');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: contas_pagar.php"); 
    exit;
}

$usuario_id = $_SESSION['UCIDUSER'] ?? $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;
$usuario_nome = htmlspecialchars($_SESSION['username'] ?? 'Usuário');

$cpf_cnpj = $_POST['cpf_cnpj'] ?? '';
$fin_raw = $_POST['fin_id'] ?? '';


$valor_pago = isset($_POST['valor_pago']) ? (float)str_replace(',', '.', $_POST['valor_pago']) : 0.0;
$desconto = isset($_POST['desconto']) ? (float)str_replace(',', '.', $_POST['desconto']) : 0.0;
$acrescimo = isset($_POST['acrescimo']) ? (float)str_replace(',', '.', $_POST['acrescimo']) : 0.0;
$forma_pagamento = $_POST['forma_pagamento'] ?? 'boleto'; 

if (empty($fin_raw) || $valor_pago <= 0) {
    
    header("Location: ficha_financeira_pagar.php?cpf_cnpj=" . urlencode($cpf_cnpj) . "&status=error&msg=" . urlencode("Parâmetros inválidos."));
    exit;
}


$fin_ids = json_decode($fin_raw, true);
if (!is_array($fin_ids)) $fin_ids = [$fin_raw];

try {
    $conn->beginTransaction();

    
    $hoje = date('Y-m-d');
    $stmtCx = $conn->prepare("SELECT * FROM CAIXA WHERE CAI_DATA = ?");
    $stmtCx->execute([$hoje]);
    $caixa = $stmtCx->fetch(PDO::FETCH_ASSOC);

    if (!$caixa) {
        $stmtIns = $conn->prepare("INSERT INTO CAIXA (CAI_DATA, CAI_VLRDEBITO, CAI_VLRCREDITO, CAI_SALDO) VALUES (?, 0, 0, '0.00')");
        $stmtIns->execute([$hoje]);
        $cai_codigo = $conn->lastInsertId();
    } else {
        $cai_codigo = $caixa['CAI_CODIGO'];
    }

    foreach ($fin_ids as $fin_id) {
        $fin_id = (int)$fin_id;
        if (!$fin_id) continue;

        
        $stmt = $conn->prepare("SELECT * FROM FINANCEIRO_PAGAR_RECEBER WHERE FIN_CODIGO = ?");
        $stmt->execute([$fin_id]);
        $fin = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fin) continue;

        $valor_alvo = (float)( ($fin['FIN_VALORTOTAL'] !== null && $fin['FIN_VALORTOTAL'] != 0) ? $fin['FIN_VALORTOTAL'] : $fin['FIN_VALOR'] );

        
        $stmtPago = $conn->prepare("SELECT COALESCE(SUM(CEC_VALOR),0) FROM CAIXA_ITEM WHERE CEC_FIN_CODIGO = ? AND CEC_ESTORNADO = 0 AND CEC_CREDEB = 'D'"); 
        $stmtPago->execute([$fin_id]);
        $ja_pago = (float)$stmtPago->fetchColumn();

        $saldo_restante = round($valor_alvo - $ja_pago, 2);
        if ($saldo_restante <= 0) continue;

        
        
        
        
        
        
        $valor_para_gravar = $saldo_restante;


        $complemento = "Pagamento FIN #{$fin_id}"; 
        $historico = "FIN_CODIGO {$fin_id}";

        
        $stmtInsItem = $conn->prepare("
            INSERT INTO CAIXA_ITEM 
            (CAI_CODIGO, CEC_CODIGO, CEC_CREDEB, CEC_VALOR, CEC_FIN_CODIGO, CEC_DEP_CODIGO, CEC_COMPLEMENTO, CEC_ORS_CODIGO, CEC_ESTORNADO, CEC_HISTORICO, CEC_USUARIO_ID)
            VALUES (?, ?, 'D', ?, ?, ?, ?, ?, 0, ?, ?)
        ");
        
        
        $stmtInsItem->execute([
            $cai_codigo, 
            $fin['CEC_CODIGO'],  
            $valor_para_gravar, 
            $fin_id, 
            $fin['FIN_DEP_CODIGO'], 
            $complemento, 
            $fin['FIN_ORS_CODIGO'], 
            $historico, 
            $usuario_id
        ]);

        
        $stmtUpdCx = $conn->prepare("UPDATE CAIXA SET CAI_VLRDEBITO = COALESCE(CAI_VLRDEBITO,0) + ? WHERE CAI_CODIGO = ?"); 
        $stmtUpdCx->execute([$valor_para_gravar, $cai_codigo]);

        
        $novo_pago = $ja_pago + $valor_para_gravar;
        
        if ($novo_pago + 0.01 >= $valor_alvo) {
            $valor_final = $valor_alvo - $desconto + $acrescimo;
            $stmtUpdFin = $conn->prepare("
                UPDATE FINANCEIRO_PAGAR_RECEBER 
                SET FIN_DATABAIXA = NOW(), 
                    FIN_DESCONTO = COALESCE(FIN_DESCONTO,0) + ?, 
                    FIN_ACRESCIMO = COALESCE(FIN_ACRESCIMO,0) + ?, 
                    FIN_VALORTOTAL = ?, 
                    FIN_FORMAPAGAMENTO = ?
                WHERE FIN_CODIGO = ?
            ");
            $stmtUpdFin->execute([$desconto, $acrescimo, $valor_final, $forma_pagamento, $fin_id]);
        }
    }

    
    $stmtSaldo = $conn->prepare("SELECT COALESCE(CAI_VLRCREDITO,0) AS cred, COALESCE(CAI_VLRDEBITO,0) AS deb FROM CAIXA WHERE CAI_CODIGO = ?");
    $stmtSaldo->execute([$cai_codigo]);
    $cx = $stmtSaldo->fetch(PDO::FETCH_ASSOC);
    $novo_saldo = (float)$cx['cred'] - (float)$cx['deb'];
    $stmtUpdSaldo = $conn->prepare("UPDATE CAIXA SET CAI_SALDO = ? WHERE CAI_CODIGO = ?");
    $stmtUpdSaldo->execute([number_format($novo_saldo,2,'.',''), $cai_codigo]);

    $conn->commit();

    
    header("Location: ficha_financeira_pagar.php?cpf_cnpj=" . urlencode($cpf_cnpj) . "&status=success&msg=" . urlencode("Baixa (pagamento) registrada com sucesso."));
    exit;

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    
    header("Location: ficha_financeira_pagar.php?cpf_cnpj=" . urlencode($cpf_cnpj) . "&status=error&msg=" . urlencode($e->getMessage()));
    exit;
}
?>