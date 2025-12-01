<?php
// recibo_termico.php?fin=123
require_once '../config.php';
$fin = intval($_GET['fin'] ?? 0);
if (!$fin) die("FIN não informado.");
$stmt = $conn->prepare("SELECT f.*, c.NOME AS CLIENTE_NOME FROM FINANCEIRO_PAGAR_RECEBER f LEFT JOIN CLIENTE c ON f.CPF_CNPJ = c.CPF_CNPJ WHERE f.FIN_CODIGO = ?");
$stmt->execute([$fin]);
$r = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$r) die("Lançamento não encontrado.");

// calcula valor pago (soma caixa_item)
$stmt2 = $conn->prepare("SELECT COALESCE(SUM(CEC_VALOR),0) as pago FROM CAIXA_ITEM WHERE CEC_FIN_CODIGO = ? AND CEC_ESTORNADO = 0 AND CEC_CREDEB = 'C'");
$stmt2->execute([$fin]);
$pago = (float)$stmt2->fetchColumn();
$valor = (float)($r['FIN_VALORTOTAL'] && $r['FIN_VALORTOTAL'] != 0 ? $r['FIN_VALORTOTAL'] : $r['FIN_VALOR']);
$saldo = round($valor - $pago, 2);
?>
<!doctype html>
<html>
    <head>
        <meta charset="utf-8">
        <title>Recibo Térmico #<?= $fin ?></title>
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
                    <div><strong>Recibo Nº:</strong> <?= $fin ?></div>
                    <div><strong>Cliente:</strong> <?= htmlspecialchars($r['CLIENTE_NOME'] ?? $r['CPF_CNPJ']) ?></div>
                    <div><strong>CPF/CNPJ:</strong> <?= htmlspecialchars($r['CPF_CNPJ']) ?></div>
                    <div><strong>Descrição:</strong> <?= htmlspecialchars($r['FIN_DESCRICAO']) ?></div>
                    <div><strong>Valor:</strong> R$ <?= number_format($valor,2,',','.') ?></div>
                    <div><strong>Pago:</strong> R$ <?= number_format($pago,2,',','.') ?></div>
                    <div><strong>Saldo:</strong> R$ <?= number_format($saldo,2,',','.') ?></div>
                    <div><strong>Data Pagamento:</strong> <?= $r['FIN_DATABAIXA'] ? date('d/m/Y H:i', strtotime($r['FIN_DATABAIXA'])) : date('d/m/Y H:i') ?></div>
                </div>

                <div class="line"></div>
                <div class="small center">Obrigado pela preferência!</div>
                <div style="height:40px;"></div>
            </div>
    </body>
</html>
