<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../index.php");
    exit;
}

$protocolo_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$protocolo_id) {
    die("Código do protocolo de saída inválido.");
}

$stmt_protocolo = $conn->prepare("SELECT * FROM PROTOCOLO_SAIDA WHERE PSA_CODIGO = ?");
$stmt_protocolo->execute([$protocolo_id]);
$protocolo = $stmt_protocolo->fetch(PDO::FETCH_ASSOC);

if (!$protocolo) {
    die("Protocolo de saída não encontrado.");
}
$stmt_itens = $conn->prepare("
    SELECT i.*, p.PRS_CPF_CNPJ, c.NOME as CLIENTE_NOME
    FROM PROTOCOLO_SAIDA_ITEM i
    JOIN PROCESSO p ON i.PSI_PRS_CODIGO = p.PRS_CODIGO
    LEFT JOIN CLIENTE c ON p.PRS_CPF_CNPJ = c.CPF_CNPJ
    WHERE i.PSI_PSA_CODIGO = ? 
    ORDER BY i.PSI_CODIGO
");
$stmt_itens->execute([$protocolo_id]);
$itens_do_protocolo = $stmt_itens->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Protocolo de Saída Nº <?= htmlspecialchars($protocolo['PSA_NUMERO']) ?></title>
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
            <h2 class="section-title">Protocolo de Saída / Recibo de Entrega</h2>

            <fieldset class="info-section">
                <legend>Informações da Entrega</legend>
                <div class="info-grid">
                    <div class="info-item"><strong>Nº Protocolo:</strong><span><?= htmlspecialchars($protocolo['PSA_NUMERO']) ?></span></div>
                    <div class="info-item"><strong>Data de Emissão:</strong><span><?= date('d/m/Y', strtotime($protocolo['PSA_DATA_EMISSAO'])?? '') ?></span></div>
                    <div class="info-item full"><strong>Recebido Por (Destinatário):</strong><span><?= htmlspecialchars($protocolo['PSA_DESTINATARIO_NOME']?? '') ?></span></div>
                    <div class="info-item"><strong>Entregue Por:</strong><span><?= htmlspecialchars($protocolo['PSA_ENTREGUE_POR'] ?? '') ?></span></div>
                </div>
            </fieldset>
            
            <fieldset class="info-section">
                <legend>Documentos / Processos Entregues</legend>
                <table class="pendencias-table" style="margin-top: 20px;">
                    <thead>
                        <tr>
                            <th style="width: 120px;">Cód. Processo</th>
                            <th>Descrição do Item</th>
                            <th>Cliente</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($itens_do_protocolo)): ?>
                            <tr><td colspan="3" style="text-align: center;">Nenhum item neste protocolo.</td></tr>
                        <?php else: ?>
                            <?php foreach($itens_do_protocolo as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['PSI_PRS_CODIGO']) ?></td>
                                <td><?= htmlspecialchars($item['PSI_DESCRICAO_ITEM']) ?></td>
                                <td><?= htmlspecialchars($item['CLIENTE_NOME'] ?? 'N/A') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </fieldset>

            <fieldset class="info-section observacoes">
                <legend>Observações</legend>
                <div class="obs-content">
                    <?= !empty($protocolo['PSA_OBSERVACOES']) ? nl2br(htmlspecialchars($protocolo['PSA_OBSERVACOES'])) : 'Nenhuma observação.' ?>
                </div>
            </fieldset>
        </section>

        <footer class="page-footer">
            <div class="signature-box">
                <p>Assinatura do Recebedor:_________________________________________</p>  
                <p>CPF/CNPJ: __________________________</p>
            </div>
            <p style="font-size: 0.8em;">Documento gerado em: <?= date('d/m/Y H:i:s') ?></p>
        </footer>
    </div>
    <script>
        window.onload = function() {
            window.print();
        }
    </script>
</body>
</html>