<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../index.php");
    exit;
}

$protocolo_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$protocolo_id) {
    die("Código do protocolo inválido.");
}


$stmt_protocolo = $conn->prepare("
    SELECT p.*, d.NOME as DESPACHANTE_NOME 
    FROM PROTOCOLO_ENTRADA p
    LEFT JOIN DESPACHANTE d ON p.PRE_DES_CODIGO = d.COD_DESP
    WHERE p.PRE_CODIGO = ?
");
$stmt_protocolo->execute([$protocolo_id]);
$protocolo = $stmt_protocolo->fetch(PDO::FETCH_ASSOC);

if (!$protocolo) {
    die("Protocolo não encontrado.");
}


$stmt_veiculos = $conn->prepare("SELECT * FROM PROTOCOLO_ENTRADA_VEICULO WHERE PEV_PRE_CODIGO = ? ORDER BY PEV_CODIGO");
$stmt_veiculos->execute([$protocolo_id]);
$veiculos_do_protocolo = $stmt_veiculos->fetchAll(PDO::FETCH_ASSOC);

foreach ($veiculos_do_protocolo as $key => $veiculo) {
    $stmt_itens = $conn->prepare("
        SELECT i.*, s.TSE_DESCRICAO 
        FROM PROTOCOLO_ENTRADA_ITEM i
        JOIN TIPO_SERVICO s ON i.PEI_TSE_CODIGO = s.TSE_CODIGO
        WHERE i.PEI_PEV_CODIGO = ? ORDER BY i.PEI_CODIGO
    ");
    $stmt_itens->execute([$veiculo['PEV_CODIGO']]);
    $veiculos_do_protocolo[$key]['servicos'] = $stmt_itens->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Protocolo de Entrada Nº <?= htmlspecialchars($protocolo['PRE_NUMERO']) ?></title>
    <link rel="stylesheet" href="../css/print.css">
</head>
<body>
    <div class="page">
        <header class="page-header">
            <img src="../img/logo-menu.png" alt="Logo Despachante" class="logo">
            <div class="company-info">
                <h1>LINDOMAR DESPACHANTE</h1>
                <p>Rua Major Jerônimo Belo, São Raimundo Nonato - PI</p>
                <p>CNPJ: 01.869.772/0001-27</p>
                <p>Telefone: (89) 9 8104-6839</p>
            </div>
        </header>

        <section class="content">
            <h2 class="section-title">Protocolo de Entrada de Documentos</h2>

            <div class="info-grid">
                <div class="info-item"><strong>Nº Protocolo:</strong><span><?= htmlspecialchars($protocolo['PRE_NUMERO']) ?></span></div>
                <div class="info-item"><strong>Data de Cadastro:</strong><span><?= date('d/m/Y', strtotime($protocolo['PRE_DATA_CADASTRO'])) ?></span></div>
                <div class="info-item"><strong>Despachante:</strong><span><?= htmlspecialchars($protocolo['DESPACHANTE_NOME'] ?? 'N/A') ?></span></div>
                <div class="info-item"><strong>Recebido Por:</strong><span><?= htmlspecialchars($protocolo['PRE_RECEBIDO_POR'] ?? 'N/A') ?></span></div>
            </div>

            <?php foreach($veiculos_do_protocolo as $veiculo): ?>
            <fieldset class="info-section">
                <legend>Veículo: <?= htmlspecialchars($veiculo['PEV_VEI_PLACA']) ?></legend>
                <div class="info-grid">
                    <div class="info-item"><strong>Placa:</strong><span><?= htmlspecialchars($veiculo['PEV_VEI_PLACA']) ?></span></div>
                    <div class="info-item"><strong>Modelo:</strong><span><?= htmlspecialchars($veiculo['PEV_VEI_MODELO']) ?></span></div>
                    <div class="info-item full"><strong>Proprietário:</strong><span><?= htmlspecialchars($veiculo['PEV_PROPRIETARIO']) ?></span></div>
                </div>
                
                <table class="pendencias-table" style="margin-top: 15px;">
                    <thead>
                        <tr>
                            <th>Serviços Solicitados</th>
                            <th style="text-align: right;">Valor (R$)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($veiculo['servicos'] as $servico): ?>
                        <tr>
                            <td><?= htmlspecialchars($servico['TSE_DESCRICAO']) ?></td>
                            <td style="text-align: right;"><?= number_format($servico['PEI_VALOR'], 2, ',', '.') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="font-weight: bold;">
                            <td>Total do Veículo:</td>
                            <td style="text-align: right;"><?= number_format($veiculo['PEV_VALOR_TOTAL'], 2, ',', '.') ?></td>
                        </tr>
                    </tfoot>
                </table>
            </fieldset>
            <?php endforeach; ?>

            <div style="text-align: right; margin-top: 20px; padding-top: 10px; border-top: 2px solid #333;">
                <p style="font-size: 1.3em; font-weight: bold; color: #2c3e50; margin: 0;">
                    Valor Total do Protocolo: 
                    <span style="display: inline-block; min-width: 150px;">
                        R$ <?= number_format($protocolo['PRE_VALOR_TOTAL'], 2, ',', '.') ?>
                    </span>
                </p>
            </div>
            
            <fieldset class="info-section observacoes">
                <legend>Observações</legend>
                <div class="obs-content">
                    <?= !empty($protocolo['PRE_OBSERVACOES']) ? nl2br(htmlspecialchars($protocolo['PRE_OBSERVACOES'])) : 'Nenhuma observação.' ?>
                </div>
            </fieldset>
        </section>

        <footer class="page-footer">
            <div class="signature-box">
                <p>_________________________________________</p>
                <p>Assinatura de Quem Entregou</p>
            </div>
            <p>Documento gerado em: <?= date('d/m/Y H:i:s') ?></p>
        </footer>
    </div>
    <script>
        
        window.onload = function() {
            window.print();
        }
    </script>
</body>
</html>