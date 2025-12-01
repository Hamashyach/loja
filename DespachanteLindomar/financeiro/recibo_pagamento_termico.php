<?php

require_once '../config.php';
$fin = intval($_GET['fin'] ?? 0);
if (!$fin) die("FIN não informado.");


$stmt = $conn->prepare("SELECT * FROM FINANCEIRO_PAGAR_RECEBER WHERE FIN_CODIGO = ? AND FIN_CREDEB = 'D'");
$stmt->execute([$fin]);
$r = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$r) die("Lançamento de pagamento não encontrado.");


$stmt2 = $conn->prepare("
    SELECT 
        COALESCE(SUM(ci.CEC_VALOR), 0) as pago,
        MAX(c.CAI_DATA) as data_pagamento
    FROM CAIXA_ITEM ci
    JOIN CAIXA c ON ci.CAI_CODIGO = c.CAI_CODIGO
    WHERE ci.CEC_FIN_CODIGO = ? 
      AND ci.CEC_ESTORNADO = 0 
      AND ci.CEC_CREDEB = 'D'
");
$stmt2->execute([$fin]);
$pagamento_info = $stmt2->fetch(PDO::FETCH_ASSOC);

$pago = (float)$pagamento_info['pago'];
$data_pagamento_formatada = $pagamento_info['data_pagamento'] ? date('d/m/Y', strtotime($pagamento_info['data_pagamento'])) : date('d/m/Y');


$valor = (float)($r['FIN_VALORTOTAL'] && $r['FIN_VALORTOTAL'] != 0 ? $r['FIN_VALORTOTAL'] : $r['FIN_VALOR']);

?>
<!doctype html>
<html>
    <head>
        <meta charset="utf-8">
        <title>Comprovante Pag. #<?= $fin ?></title> 
        <style>
            body { font-family: monospace; width: 280px; margin:0; padding:8px; }
            .center { text-align:center; }
            .h1 { font-size:16px; font-weight:bold; margin-bottom:6px; }
            .small { font-size:12px; color:#333; }
            .line { border-top:1px dashed #000; margin:8px 0; }
        </style>
    </head>
    <body onload="window.print()">
        <div class="center">
            <div class="h1">COMPROVANTE DE PAGAMENTO</div>
            <div class="small">Lindomar Despachante</div>
            <div class="line"></div>

            <div style="text-align:left;">
                <div><strong>Comprov. Nº:</strong> <?= $fin ?></div>
                <div><strong>Data Pagto:</strong> <?= $data_pagamento_formatada ?></div>
                
                <div class="line"></div>
                
                <div><strong>Fornecedor:</strong><br><?= htmlspecialchars($r['FIN_NOME']) ?></div>
                <div><strong>CPF/CNPJ:</strong> <?= htmlspecialchars($r['CPF_CNPJ']) ?></div>
                
                <div class="line"></div>
                
                <div><strong>Descricao:</strong><br><?= htmlspecialchars($r['FIN_DESCRICAO']) ?></div>
                <div><strong>Vencimento:</strong> <?= date('d/m/Y', strtotime($r['FIN_DATAVENCIMENTO'])) ?></div>
                
                <div class="line"></div>
                
                <div><strong>Valor Total:</strong> R$ <?= number_format($valor,2,',','.') ?></div>
                <div><strong>Valor Pago:</strong> R$ <?= number_format($pago,2,',','.') ?></div>
            </div>

            <div class="line"></div>
            <div class="small center">Obrigado pela preferência!</div>
            <div style="height:40px;"></div>
        </div>
    </body>
</html>