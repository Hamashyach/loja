<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../index.php");
    exit;
}

$os_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$os_id) {
    die("Código da Ordem de Serviço inválido.");
}

try {
    $sql_os = "
        SELECT
            os.*, 
            c.NOME AS CLIENTE_NOME,
            v.MODELO AS VEICULO_MODELO,
            v.PLACA_UF AS VEICULO_PLACA,
            v.RENAVAM AS VEICULO_RENAVAM,
            v.CHASSI AS VEICULO_CHASSI,
            desp.NOME AS DESPACHANTE_NOME,
            cond.CON_NOME AS CONDICAO_PAGAMENTO_NOME
        FROM
            ORDEM_SERVICO AS os
        LEFT JOIN
            CLIENTE AS c ON os.ORS_CPF_CNPJ = c.CPF_CNPJ
        LEFT JOIN
            VEICULO AS v ON os.ORS_VEI_CODIGO = v.CODIGO
        LEFT JOIN
            DESPACHANTE AS desp ON os.ORS_DEP_CODIGO = desp.COD_DESP
        LEFT JOIN
            CONDICAO_PAGAMENTO AS cond ON os.ORS_CON_CODIGO = cond.CON_CODIGO
        WHERE
            os.ORS_CODIGO = ?
    ";
    $stmt_os = $conn->prepare($sql_os);
    $stmt_os->execute([$os_id]);
    $ordem_servico = $stmt_os->fetch(PDO::FETCH_ASSOC);

    if (!$ordem_servico) {
        die("Ordem de Serviço não encontrada.");
    }

    $sql_itens = "
        SELECT 
            item.ORI_QUANTIDADE, item.ORI_VLUNITARIO, item.ORI_VLTOTAL,
            tipo.TSE_DESCRICAO
        FROM ORDEM_SERVICO_ITEM AS item
        JOIN TIPO_SERVICO AS tipo ON item.ORI_TSE_CODIGO = tipo.TSE_CODIGO
        WHERE item.ORI_ORS_CODIGO = ?
        ORDER BY item.ORI_ITEM
    ";
    $stmt_itens = $conn->prepare($sql_itens);
    $stmt_itens->execute([$os_id]);
    $itens_da_os = $stmt_itens->fetchAll(PDO::FETCH_ASSOC);
    $sql_parcelas = "SELECT * FROM ORDEM_SERVICO_PARCELA WHERE OSP_ORS_CODIGO = ? ORDER BY OSP_CODIGO ASC";
    $stmt_parcelas = $conn->prepare($sql_parcelas);
    $stmt_parcelas->execute([$os_id]);
    $parcelas_da_os = $stmt_parcelas->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro ao consultar o banco de dados: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Ordem de Serviço Nº <?= htmlspecialchars($ordem_servico['ORS_CODIGO']) ?></title>
    <link rel="stylesheet" href="../css/print_os.css">
</head>
<body>
    <div class="page">
        <header class="top-menu">
        <div class="top-menu-brand">
            <a href=".././pagina_principal.php" ><img src="../img/logo.png" alt="Lindomar Despachante" class="top-logo"></a>
            
        </div>
        <div class="header-meio">           
           <p>Lindonar Despachante - versão 1.1</p>
        </div>
        <div class="header-actions">
            <p>Olá, <?= $usuario_nome; ?></p>
            <a href="../logout.php" title="Fazer Logoff">
                Sair
            </a> 
        </div>
    </header>

        <section class="content">
            <fieldset class="info-section">
                <legend>Dados do Cliente</legend>
                <div class="info-grid">
                    <div class="info-item full"><strong>Nome:</strong> <span><?= htmlspecialchars($ordem_servico['CLIENTE_NOME'] ?? 'Não informado') ?></span></div>
                    <div class="info-item full"><strong>CPF/CNPJ:</strong> <span><?= htmlspecialchars($ordem_servico['ORS_CPF_CNPJ'] ?? 'Não informado') ?></span></div>
                </div>
            </fieldset>

            <fieldset class="info-section">
                <legend>Dados do Veículo</legend>
                <div class="info-grid">
                    <div class="info-item" style="flex-basis: 48%;"><strong>Modelo:</strong><span><?= htmlspecialchars($ordem_servico['VEICULO_MODELO'] ?? 'N/A') ?></span></div>
                    <div class="info-item" style="flex-basis: 24%;"><strong>Placa:</strong><span><?= htmlspecialchars($ordem_servico['VEICULO_PLACA'] ?? 'N/A') ?></span></div>
                    <div class="info-item" style="flex-basis: 24%;"><strong>Renavam:</strong><span><?= htmlspecialchars($ordem_servico['VEICULO_RENAVAM'] ?? 'N/A') ?></span></div>
                    <div class="info-item full"><strong>Chassi:</strong><span><?= htmlspecialchars($ordem_servico['VEICULO_CHASSI'] ?? 'N/A') ?></span></div>
                </div>
            </fieldset>
            
            <fieldset class="info-section">
                <legend>Serviços Prestados</legend>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Descrição do Serviço</th>
                            <th class="center">Qtde</th>
                            <th class="right">Vl. Unitário (R$)</th>
                            <th class="right">Vl. Total (R$)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($itens_da_os)): ?>
                            <?php foreach ($itens_da_os as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['TSE_DESCRICAO']) ?></td>
                                <td class="center"><?= htmlspecialchars((int)$item['ORI_QUANTIDADE']) ?></td>
                                <td class="right"><?= number_format($item['ORI_VLUNITARIO'], 2, ',', '.') ?></td>
                                <td class="right"><?= number_format($item['ORI_VLTOTAL'], 2, ',', '.') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4">Nenhum serviço adicionado.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </fieldset>

             <div class="totals-section">
                 <div class="payment-details">
                    <fieldset class="info-section">
                       <legend>Financeiro</legend>
                        <p><strong>Cond. Pagamento:</strong> <?= htmlspecialchars($ordem_servico['CONDICAO_PAGamento_NOME'] ?? 'Não especificada') ?></p>
                        <?php if (!empty($parcelas_da_os)): ?>
                            <table class="data-table installments">
                                <thead><tr><th>Parcela</th><th>Vencimento</th><th class="right">Valor (R$)</th></tr></thead>
                                <tbody>
                                    <?php 
                                    // Usamos um contador para exibir o número da parcela, já que não temos a coluna OSP_PARCELA
                                    $numero_parcela = 1; 
                                    foreach($parcelas_da_os as $parcela): 
                                    ?>
                                    <tr>
                                        <td class="center"><?= $numero_parcela++ ?></td>
                                        <td class="center"><?= date('d/m/Y', strtotime($parcela['OSP_VENCIMENTO'])) ?></td>
                                        <td class="right"><?= number_format($parcela['OSP_VALOR'], 2, ',', '.') ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </fieldset>
                </div>
                <div class="summary">
                    <p><span>Valor Entrada:</span> <strong>R$ <?= number_format($ordem_servico['ORS_VLENTRADA'] ?? 0, 2, ',', '.') ?></strong></p>
                    <p class="total-geral"><span>Valor Total da O.S.:</span> <strong>R$ <?= number_format($ordem_servico['ORS_VLTOTAL'] ?? 0, 2, ',', '.') ?></strong></p>
                </div>
            </div>

            <fieldset class="info-section observacoes">
                <legend>Observações</legend>
                <div class="obs-content">
                    <?= !empty($ordem_servico['ORS_OBS']) ? nl2br(htmlspecialchars($ordem_servico['ORS_OBS'])) : 'Nenhuma observação.' ?>
                </div>
            </fieldset>

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