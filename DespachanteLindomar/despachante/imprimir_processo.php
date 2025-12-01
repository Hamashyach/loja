<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../index.php");
    exit;
}

$processo_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$processo_id) {
    die("Código do Processo inválido.");
}

try {
    $sql_processo = "
        SELECT 
            p.*,
            c.NOME AS CLIENTE_NOME,
            v.PLACA_UF AS VEICULO_PLACA,
            v.MODELO AS VEICULO_MODELO,
            v.RENAVAM AS VEICULO_RENAVAM,
            d.NOME AS DESPACHANTE_NOME,
            s.PSI_DESCRICAO AS SITUACAO_NOME,
            ts.TSE_DESCRICAO AS SERVICO_NOME
        FROM PROCESSO p
        LEFT JOIN CLIENTE c ON p.PRS_CPF_CNPJ = c.CPF_CNPJ
        LEFT JOIN VEICULO v ON p.PRS_VEI_CODIGO = v.CODIGO
        LEFT JOIN DESPACHANTE d ON p.PRS_DES_CODIGO = d.COD_DESP
        LEFT JOIN PROCESSO_SITUACAO s ON p.PRS_PSI_CODIGO = s.PSI_CODIGO
        LEFT JOIN TIPO_SERVICO ts ON p.PRS_TSE_CODIGO = ts.TSE_CODIGO
        WHERE p.PRS_CODIGO = ?
    ";
    $stmt = $conn->prepare($sql_processo);
    $stmt->execute([$processo_id]);
    $processo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$processo) {
        die("Processo não encontrado.");
    }

    // 2. Busca as pendências associadas
    $stmt_pend = $conn->prepare("SELECT * FROM processo_pendencia WHERE PRP_PRS_CODIGO = ? ORDER BY PRP_CODIGO");
    $stmt_pend->execute([$processo_id]);
    $pendencias = $stmt_pend->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro ao consultar o banco de dados: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Processo Nº <?= htmlspecialchars($processo['PRS_CODIGO']) ?></title>
    <link rel="stylesheet" href="../css/print_processo.css">
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
            <div class="os-info">
                <h2>DETALHES DO PROCESSO</h2>
                <p><strong>Nº:</strong> <?= htmlspecialchars($processo['PRS_CODIGO']) ?></p>
                <p><strong>Emissão:</strong> <?= date('d/m/Y', strtotime($processo['PRS_DATA_EMISSAO'])) ?></p>
            </div>
        </header>

        <section class="content">
            <fieldset class="info-section">
                <legend>Partes Envolvidas</legend>
                <div class="info-grid">
                    <div class="info-item" style="flex-basis: 60%;"><strong>Cliente:</strong> <span><?= htmlspecialchars($processo['CLIENTE_NOME'] ?? '') ?></span></div>
                    <div class="info-item" style="flex-basis: 38%;"><strong>Despachante:</strong> <span><?= htmlspecialchars($processo['DESPACHANTE_NOME'] ?? 'N/A') ?></span></div>
                </div>
            </fieldset>

            <fieldset class="info-section">
                <legend>Dados do Veículo</legend>
                <div class="info-grid">
                    <div class="info-item" style="flex-basis: 48%;"><strong>Modelo:</strong><span><?= htmlspecialchars($processo['VEICULO_MODELO'] ?? 'N/A') ?></span></div>
                    <div class="info-item" style="flex-basis: 24%;"><strong>Placa Atual:</strong><span><?= htmlspecialchars($processo['PRS_PLACA_ATUAL'] ?? 'N/A') ?></span></div>
                    <div class="info-item" style="flex-basis: 24%;"><strong>Renavam:</strong><span><?= htmlspecialchars($processo['VEICULO_RENAVAM'] ?? 'N/A') ?></span></div>
                </div>
            </fieldset>
            
            <fieldset class="info-section">
                <legend>Detalhes do Processo</legend>
                 <div class="info-grid">
                    <div class="info-item full"><strong>Serviço:</strong><span><?= htmlspecialchars($processo['SERVICO_NOME'] ?? 'N/A') ?></span></div>
                    <div class="info-item"><strong>Nº Protocolo:</strong><span><?= htmlspecialchars($processo['PRS_NUMERO_PROTOCOLO'] ?? 'N/A') ?></span></div>
                    <div class="info-item"><strong>Nº Selo CRV:</strong><span><?= htmlspecialchars($processo['PRS_NUMERO_SELO'] ?? 'N/A') ?></span></div>
                    <div class="info-item"><strong>Lote:</strong><span><?= htmlspecialchars($processo['PRS_LOTE'] ?? 'N/A') ?></span></div>
                    <div class="info-item"><strong>Situação:</strong><span><?= htmlspecialchars($processo['SITUACAO_NOME'] ?? 'N/A') ?></span></div>
                 </div>
            </fieldset>
            
            <?php if (!empty($pendencias)): ?>
            <fieldset class="info-section">
                <legend>Pendências</legend>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Motivo</th>
                            <th>Providência</th>
                            <th class="center">Vencimento</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendencias as $pend): ?>
                        <tr>
                            <td><?= htmlspecialchars($pend['PRP_MOTIVO']) ?></td>
                            <td><?= htmlspecialchars($pend['PRP_PROVIDENCIA']) ?></td>
                            <td class="center"><?= !empty($pend['PRP_DATA_VENCIMENTO']) ? date('d/m/Y', strtotime($pend['PRP_DATA_VENCIMENTO'])) : '' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </fieldset>
            <?php endif; ?>

            <div class="summary">
                 <p><span>Valor Taxas:</span> <strong>R$ <?= number_format($processo['PRS_VALOR_TAXAS'] ?? 0, 2, ',', '.') ?></strong></p>
                 <p class="total-geral"><span>Valor Total:</span> <strong>R$ <?= number_format($processo['PRS_VALOR_TOTAL'] ?? 0, 2, ',', '.') ?></strong></p>
            </div>
            
        </section>

        <footer class="page-footer">
            <div class="signature-box">
                <p>_________________________________________</p>
                <p>Assinatura do Cliente</p>
            </div>
            <p class="print-date">Documento gerado em: <?= date('d/m/Y H:i:s') ?></p>
        </footer>
    </div>
    <script>
        window.onload = function() {
            window.print();
        }
    </script>
</body>
</html>