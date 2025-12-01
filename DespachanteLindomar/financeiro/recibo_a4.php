<?php
require_once '../config.php';
$fin = intval($_GET['fin'] ?? 0);
if (!$fin) die("Código (FIN) não informado.");


$empresa_nome = "Lindomar Despachante"; 
$empresa_doc = "01.869.772/0001-27"; 
$empresa_end = "Rua Major Jerônimo belo- Centro, São Raimundo Nonato - PI"; 


$stmt = $conn->prepare("
    SELECT f.*, 
           c.NOME AS CLIENTE_NOME, 
           c.CPF_CNPJ AS CLIENTE_DOC 
    FROM FINANCEIRO_PAGAR_RECEBER f 
    LEFT JOIN CLIENTE c ON f.CPF_CNPJ = c.CPF_CNPJ 
    WHERE f.FIN_CODIGO = ? AND f.FIN_CREDEB = 'C'
");
$stmt->execute([$fin]);
$r = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$r) die("Lançamento de recebimento não encontrado.");


$stmt2 = $conn->prepare("
    SELECT 
        COALESCE(SUM(ci.CEC_VALOR), 0) as pago,
        MAX(c.CAI_DATA) as data_pagamento
    FROM CAIXA_ITEM ci
    JOIN CAIXA c ON ci.CAI_CODIGO = c.CAI_CODIGO
    WHERE ci.CEC_FIN_CODIGO = ? 
      AND ci.CEC_ESTORNADO = 0 
      AND ci.CEC_CREDEB = 'C'
");
$stmt2->execute([$fin]);
$pagamento_info = $stmt2->fetch(PDO::FETCH_ASSOC);

$valor_pago = (float)$pagamento_info['pago'];
$data_pagamento = $pagamento_info['data_pagamento'] ? date('d/m/Y', strtotime($pagamento_info['data_pagamento'])) : date('d/m/Y');


$valor_original = (float)$r['FIN_VALOR'];
$desconto = (float)$r['FIN_DESCONTO'];
$acrescimo = (float)$r['FIN_ACRESCIMO'];
$valor_final_fatura = ($valor_original - $desconto + $acrescimo);


$saldo = round($valor_final_fatura - $valor_pago, 2);


$cliente_nome = $r['CLIENTE_NOME'] ?? ($r['FIN_NOME'] ?? 'Cliente não identificado');
$cliente_doc = $r['CLIENTE_DOC'] ?? ($r['CPF_CNPJ'] ?? 'N/A');

?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Recibo #<?= $fin ?></title>
<link rel="stylesheet" href="../css/style_sistema.css"> <style>
    body {
        background-color: #f0f0f0;
        font-family: Arial, sans-serif;
    }
    .paper {
        max-width: 800px;
        margin: 20px auto;
        background: #fff;
        padding: 40px;
        border: 1px solid #eaeaea;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    }
    .print-button {
        display: block;
        width: 100%;
        padding: 12px;
        margin-bottom: 20px;
        background: #2c3e50;
        color: #fff;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-size: 16px;
        font-weight: bold;
    }
    .header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 30px;
        border-bottom: 2px solid #f0f0f0;
        padding-bottom: 20px;
    }
    .header .company-details {
        font-size: 0.9em;
        color: #555;
        text-align: left;
    }
    .header .doc-title {
        text-align: right;
    }
    .header .doc-title h1 {
        margin: 0;
        color: #2c3e50;
        font-size: 1.8em;
    }
    .header .doc-title p {
        margin: 5px 0 0 0;
        font-size: 1em;
    }
    .parties {
        display: flex;
        justify-content: space-between;
        margin-bottom: 30px;
        gap: 20px;
    }
    .party-box {
        flex: 1;
        border: 1px solid #eee;
        padding: 15px;
        border-radius: 8px;
    }
    .party-box h3 {
        margin-top: 0;
        margin-bottom: 10px;
        font-size: 0.9em;
        text-transform: uppercase;
        color: #777;
        border-bottom: 1px solid #f0f0f0;
        padding-bottom: 5px;
    }
    .party-box p {
        margin: 4px 0;
        font-size: 0.95em;
    }
    
    .table-details {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }
    .table-details th, .table-details td {
        padding: 12px 15px;
        border-bottom: 1px solid #eee;
        text-align: left;
    }
    .table-details thead th {
        background-color: #f9f9f9;
        font-size: 0.9em;
        color: #555;
        text-transform: uppercase;
    }
    .table-details .right {
        text-align: right;
    }
   
    .table-details .balance-row td {
        font-weight: bold;
        color: #555;
        font-size: 1.1em;
        border-top: 1px solid #ccc;
    }
   
    .table-details tfoot .total-row td {
        border-top: 2px solid #333;
        font-size: 1.2em;
        font-weight: bold;
        color: #000;
        background-color: #f9f9f9;
    }

    .footer {
        margin-top: 50px;
        text-align: center;
        font-size: 0.9em;
        color: #777;
    }
    .signature-line {
        margin-top: 60px;
        text-align: center;
    }
    .signature-line p {
        margin: 0;
    }
    .signature-line .line {
        width: 300px;
        border-bottom: 1px solid #333;
        margin: 0 auto 5px auto;
    }

   
    @media print {
        body {
            background-color: #fff;
            margin: 0;
        }
        .paper {
            margin: 0;
            padding: 0;
            border: none;
            box-shadow: none;
            max-width: 100%;
        }
        .print-button {
            display: none;
        }
    }
</style>
</head>
<body onload="window.print()"> <div class="paper">
    
    <button class="print-button" onclick="window.print()">Imprimir</button>

    <div class="header">
        <div class="company-details">
            <strong><?= htmlspecialchars($empresa_nome) ?></strong><br>
            <?= htmlspecialchars($empresa_end) ?><br>
            CNPJ: <?= htmlspecialchars($empresa_doc) ?>
        </div>
        <div class="doc-title">
            <h1>RECIBO</h1> <p><strong>Nº do Lançamento:</strong> <?= $fin ?></p>
            <p><strong>Data do Recebimento:</strong> <?= $data_pagamento ?></p> </div>
    </div>

    <div class="parties">
        <div class="party-box">
            <h3>PAGADOR (CLIENTE)</h3>
            <p><strong>Nome:</strong> <?= htmlspecialchars($cliente_nome) ?></p>
            <p><strong>CPF/CNPJ:</strong> <?= htmlspecialchars($cliente_doc) ?></p>
        </div>
        <div class="party-box">
            <h3>RECEBEDOR (EMPRESA)</h3>
            <p><strong>Empresa:</strong> <?= htmlspecialchars($empresa_nome) ?></p>
            <p><strong>CNPJ:</strong> <?= htmlspecialchars($empresa_doc) ?></p>
        </div>
    </div>

    <h2 style="font-size: 1.2em; color: #333; margin-top: 30px; margin-bottom: 10px;">Detalhes do Recebimento</h2>

    <table class="table-details">
        <thead>
            <tr>
                <th>Descrição do Serviço</th>
                <th>Vencimento</th>
                <th class="right">Valor Original</th>
                <th class="right">Ajustes (Desc/Acrés)</th>
                <th class="right">Valor Recebido</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><?= htmlspecialchars($r['FIN_DESCRICAO']) ?></td>
                <td><?= date('d/m/Y', strtotime($r['FIN_DATAVENCIMENTO'])) ?></td>
                <td class="right">R$ <?= number_format($valor_original, 2, ',', '.') ?></td>
                <td class="right">R$ <?= number_format(($acrescimo - $desconto), 2, ',', '.') ?></td>
                <td class="right">R$ <?= number_format($valor_pago, 2, ',', '.') ?></td>
            </tr>
        </tbody>
        <tfoot>
            <tr class="balance-row">
                <td colspan="4" class="right">SALDO DEVEDOR (Restante)</td>
                <td class="right" style="color: #dc3545;">R$ <?= number_format($saldo, 2, ',', '.') ?></td>
            </tr>
            <tr class="total-row">
                <td colspan="4" class="right">TOTAL RECEBIDO</td>
                <td class="right">R$ <?= number_format($valor_pago, 2, ',', '.') ?></td>
            </tr>
        </tfoot>
    </table>

    <div class="signature-line">
        <div class="line"></div>
        <p><strong>Assinatura do Recebedor (Empresa)</strong></p> <p><?= htmlspecialchars($empresa_nome) ?></p>
    </div>

    <div class="footer">
        Gerado em <?= date('d/m/Y \à\s H:i') ?> pelo Sistema Lindomar Despachante.
    </div>
</div>

</body>
</html>