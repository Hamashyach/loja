<?php
session_start();
require_once '../config.php';
require_once '../verificar_permissao.php';


protegerPagina('acessar_financeiro');
$usuario_nome = htmlspecialchars($_SESSION['username'] ?? 'Usuário');


$usuario_id = $_SESSION['UCIDUSER'] ?? $_SESSION['user_id'] ?? $_SESSION['id'] ?? $_SESSION['usuario_id'] ?? null;

$cpf_cnpj = $_GET['cpf_cnpj'] ?? null;
$cliente = null;
$erro_cliente = null;
$contas = [];
$historico = [];
$total_devido = 0;
$total_pago_geral = 0;
$saldo_devedor = 0;

if (!$cpf_cnpj) {
    $erro_cliente = "O CPF/CNPJ do cliente não foi especificado na URL.";
} else {
    
    $stmt_cliente = $conn->prepare("SELECT CODIGO, NOME, CPF_CNPJ, TELEFONE, EMAIL FROM CLIENTE WHERE CPF_CNPJ = ?");
    $stmt_cliente->execute([$cpf_cnpj]);
    $cliente = $stmt_cliente->fetch(PDO::FETCH_ASSOC);

    if (!$cliente) {
        $erro_cliente = "Cliente não encontrado ou foi excluído (CPF/CNPJ: " . htmlspecialchars($cpf_cnpj) . ").";
    } else {
        
        $sql_contas = "
            SELECT 
                f.FIN_CODIGO, f.FIN_DESCRICAO, f.FIN_CREDEB, f.FIN_VALOR, f.FIN_VALORTOTAL,
                f.FIN_DATAVENCIMENTO, f.FIN_DATABAIXA, f.FIN_ORS_CODIGO,
                COALESCE((
                   SELECT SUM(ci.CEC_VALOR) 
                   FROM CAIXA_ITEM ci 
                   WHERE ci.CEC_FIN_CODIGO = f.FIN_CODIGO AND ci.CEC_ESTORNADO = 0 AND ci.CEC_CREDEB = 'C'
                ),0) AS total_pago_parcela
            FROM FINANCEIRO_PAGAR_RECEBER f
            WHERE f.CPF_CNPJ = ? AND f.FIN_CREDEB = 'C' 
            ORDER BY f.FIN_DATAVENCIMENTO ASC, f.FIN_CODIGO ASC
        ";
        $stmt_contas = $conn->prepare($sql_contas);
        $stmt_contas->execute([$cpf_cnpj]);
        $contas = $stmt_contas->fetchAll(PDO::FETCH_ASSOC);

        
        $sql_historico = "
            SELECT 
                ci.CIT_CODIGO,
                ci.CEC_COMPLEMENTO,
                ci.CEC_VALOR,
                ci.CEC_ESTORNADO,
                c.CAI_DATA,
                ci.CEC_FIN_CODIGO,
                u.UCUSERNAME as nome_usuario_baixa,
                f.FIN_DESCONTO,
                f.FIN_ACRESCIMO,
                f.FIN_FORMAPAGAMENTO,
                f.FIN_DESCRICAO AS descricao_parcela,
                o.ORS_PARCELAS AS total_parcelas
            FROM CAIXA_ITEM ci
            JOIN CAIXA c ON ci.CAI_CODIGO = c.CAI_CODIGO
            LEFT JOIN USUARIO u ON ci.CEC_USUARIO_ID = u.UCIDUSER
            LEFT JOIN FINANCEIRO_PAGAR_RECEBER f ON f.FIN_CODIGO = ci.CEC_FIN_CODIGO
            LEFT JOIN ORDEM_SERVICO o ON o.ORS_CODIGO = f.FIN_ORS_CODIGO
            WHERE f.CPF_CNPJ = ?
            AND ci.CEC_CREDEB = 'C'
            ORDER BY c.CAI_DATA DESC, ci.CIT_CODIGO DESC
        ";
        $stmt_historico = $conn->prepare($sql_historico);
        $stmt_historico->execute([$cpf_cnpj]);
        $historico = $stmt_historico->fetchAll(PDO::FETCH_ASSOC);

        
        $total_devido = 0;
        $total_pago_geral = 0;

        foreach ($contas as $conta) {
            $valor_parcela = (float)(($conta['FIN_VALORTOTAL'] && $conta['FIN_VALORTOTAL'] != 0)
                ? $conta['FIN_VALORTOTAL']
                : $conta['FIN_VALOR']);
            $total_devido += $valor_parcela;
            $total_pago_geral += (float)($conta['total_pago_parcela'] ?? 0);
        }

        
        $saldo_devedor = round($total_devido - $total_pago_geral, 2);

        
        if (abs($saldo_devedor) <= 1.00) {
            $saldo_devedor = 0.00;
        }

        
        $corSaldo = ($saldo_devedor > 0) ? "color:#dc3545;" : (($saldo_devedor < 0) ? "color:#ffc107;" : "color:#28a745;");
            }
        }

        
        $pode_dar_baixa = temPermissao('dar_baixa_financeiro');
        $pode_estornar = temPermissao('estornar_financeiro');

        $titulo_pagina = $cliente ? "Ficha Financeira - " . htmlspecialchars($cliente['NOME']) : "Ficha Financeira - Erro";
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title><?= $titulo_pagina ?></title>
    <link rel="stylesheet" href="../css/style_sistema.css">
    <style>

        .summary-box { display:flex; gap:20px; margin-bottom:20px; }
        .summary-card { background:#fff; padding:12px 16px; border-radius:8px; border:1px solid #e6e6e6; flex:1; text-align:center; }
        .status-badge { padding:6px 8px; border-radius:6px; display:inline-block; font-weight:600; }
        .status-pago { color:#155724; background:#d4edda; }
        .status-atrasado { color:#721c24; background:#f8d7da; }
        .status-aberto { color:#0c5460; background:#d1ecf1; }
        .status-parcial { color:#856404; background:#fff3cd; }
        .actions-row { display:flex; gap:8px; align-items:center; margin-bottom:12px; }
    </style>
</head>
<body>
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
    <div class="main-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <button id="sidebarToggle" title="Recolher menu">☰</button>
            </div>

            <nav class="sidebar-nav">
                <ul>    
                     <li>
                        <a href="#" class="submenu-toggle">
                            <img src="../img/icon/despachante.png" alt="" class="nav-icon">
                            <span>Despachante</span>
                        </a>
                        <ul class="submenu">
                             <li><a href="../despachante/anotacao_recado.php"><span>Anotações e Recados</span></a></li>
                            <li><a href="../despachante/agendamento_vistoria.php"><span>Agendamento de Vistoria</span></a></li>   
                            <li><a href="../despachante/cadastro_clientes.php"><span>Cliente</span></a></li>
                            <li><a href="../despachante/cadastro_veiculo.php"><span>Veículo</span></a></li>
                            <li><a href="../despachante/cadastro_ordem_servico.php"><span>Ordem de Serviços</span></a></li>
                            <li><a href="../despachante/cadastro_processos.php"><span>Processos</span></a></li>
                            <li><a href="../despachante/cadastro_situacao_processo.php"><span>Situações de Processo</span></a></li>
                            <li><a href="../despachante/cadastro_primeiro_emplacamento.php"><span>Primeiro Emplacamento</span></a></li>     
                            <li><a href="../despachante/protocolo_entrada.php"><span>Protocolo Entrada Documentos</span></a></li>
                            <li><a href="../despachante/protocolo_saida.php"><span>Protocolo Saída Documentos</span></a></li> 
                            <li><a href="../despachante/cadastro_recibo.php"><span>Recibos</span></a></li> 
                            <li><a href="../despachante/cadastro_intencao_venda.php"><span>Intenção de Venda (ATPV-e)</span></a></li> 
                            
                        </ul>
                    </li>

                    <li>
                        <a href="#" class="submenu-toggle">
                            <img src="../img/icon/financeiro.png" alt="" class="nav-icon">
                            <span>Controle Financeiro</span>
                        </a>
                        <ul class="submenu">
                            <li><a href="lembretes.php"><span>Lembretes Financeiros</span></a></li>
                            <li><a href="contas_pagar.php"><span>Contas a Pagar</span></a></li>
                            <li><a href="contas_receber.php"><span>Contas a Receber</span></a></li>
                            <li><a href="fluxo_caixa.php"><span>Fluxo de Caixa</span></a></li>
                            <li><a href="cadastro_centro_custo.php"><span>Centro de Custo</span></a></li>
                        </ul>
                    </li>

                      <li>
                        <a href="#" class="submenu-toggle">
                            <img src="../img/icon/documento.png" alt="" class="nav-icon">
                            <span>Relatórios</span>
                        </a>
                        <ul class="submenu">
                            <li><a href="../relatorio_clientes.php"><span>Clientes</span></a></li>
                            <li><a href="../relatorio_veiculos.php"><span>Veículos</span></a></li>
                            <li><a href="../relatorio_entrada_veiculos.php"><span>Entrada de Veículos</span></a></li>
                            <li><a href="../relatorio_ordem_servico.php"><span>Ordem de Serviço</span></a></li>
                            <li><a href="../relatorio_processos.php"><span>Processos</span></a></li>
                            <li><a href="../relatorio_contas_pagar.php"><span>Contas a Pagar</span></a></li>
                            <li><a href="../relatorio_contas_receber.php"><span>Contas a Receber</span></a></li>                           
                            <li><a href="../relatorio_cnhs_vencendo.php"><span>CNH's Vencendo</span></a></li>            
                        </ul>
                    </li>
                    <li>
                        <a href="#" class="submenu-toggle">
                             <img src="../img/icon/usuario.png" alt="" class="nav-icon">
                            <span>Usuários</span>
                        </a>
                        <ul class="submenu">
                            <li><a href="../usuarios/usuario.php"><span>Gerenciar Usuários</span></a></li>
                        </ul>
                    </li>
                     <li>
                        <a href="#" class="submenu-toggle">
                            <img src="../img/icon/configuração.png" alt="" class="nav-icon">
                            <span>Configuração</span>
                        </a>
                        <ul class="submenu">
                             <li><a href="../veiculos/cadastro_condicao_pagamento.php"><span>Condições Pagamento</span></a></li>
                            <li><a href="../veiculos/cadastro_tipos_servico.php"><span>Tipos de Serviço</span></a></li>
                            <li><a href="../veiculos/cadastro_tipo_endereco.php"><span>Tipos de Endereço</span></a></li>
                            <li><a href="../veiculos/cadastro_posto_vistoria.php"><span>Postos de Vistoria</span></a></li>
                            <li><a href="../veiculos/cadastro_tempos_licenciamento.php"><span>Tempos de Licenciamento</span></a></li>
                            <li><a href="../veiculos/cadastro_despachante.php"><span>Cadastro de Despachante</span></a></li>
                            <li><a href="../veiculos/cadastro_municipios.php"><span>Cadastro de Municipio</span></a></li>
                            <li><a href="../veiculos/cadastro_carroceria.php"><span>Carroceria</span></a></li>
                            <li><a href="../veiculos/cadastro_categoria.php"><span>Categoria</span></a></li>
                            <li><a href="../veiculos/cadastro_combustivel.php"><span>Combustível</span></a></li>
                            <li><a href="../veiculos/cadastro_cor.php"><span>Cor</span></a></li>
                            <li><a href="../veiculos/cadastro_especie.php"><span>Espécie</span></a></li>
                            <li><a href="../veiculos/cadastro_modelo.php"><span>Marca/Modelo</span></a></li>
                            <li><a href="../veiculos/cadastro_restricao.php"><span>Restrição</span></a></li>
                            <li><a href="../veiculos/cadastro_tipo_veiculo.php"><span>Tipo de Veículo</span></a></li>
                        </ul>
                    </li>
                </ul>
            </nav>

             <div class="user-profile">
                <a href="#">
                    <img src="../img/icon/user.png" alt="" class="nav-icon">
                    <span class="user-name"><?= $usuario_nome; ?></span>
                </a>
            </div>
        </aside>
        <main class="main-content">
            <h1>Ficha Financeira</h1>

            <?php if ($erro_cliente): ?>
                <div class="alert error" style="margin-top: 20px;">
                    <strong>Atenção!</strong> <?= $erro_cliente ?>
                </div>
                <div class="form-footer" style="justify-content: flex-start;">
                    <a href="contas_receber.php" class="btn btn-secondary">Voltar</a>
                </div>
            <?php else: ?>
                <fieldset class="form-section">
                    <legend>Dados do Cliente</legend>
                    <div class="form-row">
                        <p><strong>Nome:</strong> <?= htmlspecialchars($cliente['NOME']) ?></p>
                        <p><strong>CPF/CNPJ:</strong> <?= htmlspecialchars($cliente['CPF_CNPJ']) ?></p>
                        <p><strong>Telefone:</strong> <?= htmlspecialchars($cliente['TELEFONE'] ?? 'N/A') ?></p>
                    </div>
                </fieldset>

                
                <div class="summary-box">
                    <div class="summary-card">
                        <small>VALOR TOTAL</small>
                        <p style="color:#007bff;">R$ <?= number_format($total_devido, 2, ',', '.') ?></p>
                    </div>
                    <div class="summary-card">
                        <small>TOTAL PAGO</small>
                        <p style="color:#28a745;">R$ <?= number_format($total_pago_geral, 2, ',', '.') ?></p>
                    </div>
                    <div class="summary-card">
                        <small>SALDO DEVEDOR</small>
                        <p style="<?= $corSaldo ?>">R$ <?= number_format($saldo_devedor, 2, ',', '.') ?></p>
                    </div>
                </div>

                <div class="tabs">
                    <div class="tab-buttons">
                        <button type="button" class="tab-button active" data-tab="tab-parcelas">Contas / Parcelas</button>
                        <button type="button" class="tab-button" data-tab="tab-historico">Histórico de Pagamentos</button>
                    </div>

                    <div class="tab-content">
                        <div id="tab-parcelas" class="tab-pane active">
                            <div class="actions-row">
                                <?php if ($pode_dar_baixa): ?>
                                    <button id="btnReceberSelecionado" class="btn btn-primary" disabled>Receber Parcela Selecionada</button>
                                <?php endif; ?>
                                <div style="margin-left:auto;">
                                    <small>Recibos:</small>
                                </div>
                            </div>

                            <div class="table-container">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th class="checkbox-cell"><input id="check_all" type="checkbox"></th>
                                            <th>OS Nº</th>
                                            <th>Parcela (Descrição)</th>
                                            <th>Vencimento</th>
                                            <th>Valor Original</th>
                                            <th>Valor Pago</th>
                                            <th>Saldo</th>
                                            <th>Status</th>
                                            <?php if ($pode_dar_baixa): ?><th>Ações</th><?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody id="tabela-financeira">
                                        <?php if(empty($contas)): ?>
                                            <tr><td colspan="<?= $pode_dar_baixa ? 9 : 8 ?>">Nenhuma conta a receber encontrada para este cliente.</td></tr>
                                        <?php endif; ?>
                                        <?php foreach($contas as $conta): 
                                            $valor_parcela = (float)( ($conta['FIN_VALORTOTAL'] !== null && $conta['FIN_VALORTOTAL'] != 0) ? $conta['FIN_VALORTOTAL'] : $conta['FIN_VALOR'] );
                                            $valor_pago_parcela = (float)$conta['total_pago_parcela'];
                                            $saldo_parcela = round($valor_parcela - $valor_pago_parcela, 2);
                                            $status = 'Aberto';
                                            if (!empty($conta['FIN_DATABAIXA'])) {
                                                $status = 'Pago';
                                            } elseif ($valor_pago_parcela > 0 && $saldo_parcela > 0.001) {
                                                $status = 'Parcial';
                                            } else {
                                                $venc = strtotime($conta['FIN_DATAVENCIMENTO']);
                                                if ($venc < strtotime(date('Y-m-d'))) $status = 'Atrasado';
                                            }
                                        ?>
                                            <tr data-fin="<?= $conta['FIN_CODIGO'] ?>" data-saldo="<?= $saldo_parcela ?>">
                                                <td class="checkbox-cell"><input class="chk-fin" type="checkbox" value="<?= $conta['FIN_CODIGO'] ?>" <?= $status=='Pago' ? 'disabled' : '' ?>></td>
                                                <td><a href="../despachante/cadastro_ordem_servico.php?action=view&id=<?= $conta['FIN_ORS_CODIGO'] ?>" target="_blank"><?= $conta['FIN_ORS_CODIGO'] ?></a></td>
                                                <td><?= htmlspecialchars($conta['FIN_DESCRICAO']) ?></td>
                                                <td><?= !empty($conta['FIN_DATAVENCIMENTO']) ? date('d/m/Y', strtotime($conta['FIN_DATAVENCIMENTO'])) : '-' ?></td>
                                                <td>R$ <?= number_format($valor_parcela, 2, ',', '.') ?></td>
                                                <td>R$ <?= number_format($valor_pago_parcela, 2, ',', '.') ?></td>
                                                <td class="<?= $saldo_parcela > 0.01 ? 'saldo-devedor-parcela' : '' ?>">R$ <?= number_format($saldo_parcela, 2, ',', '.') ?></td>
                                                <td>
                                                    <span class="status-badge <?= $status=='Pago' ? 'status-pago' : ($status=='Atrasado' ? 'status-atrasado' : ($status=='Parcial' ? 'status-parcial' : 'status-aberto')) ?>">
                                                        <?= $status ?>
                                                    </span>
                                                </td>
                                                <?php if ($pode_dar_baixa): ?>
                                                    <td>
                                                        <?php if ($status != 'Pago'): ?>
                                                            <button class="btn btn-primary btn-registrar-pagamento" data-id="<?= $conta['FIN_CODIGO'] ?>" data-saldo="<?= $saldo_parcela ?>">Receber</button>
                                                        <?php else: ?>
                                                            <a href="recibo_a4.php?fin=<?= $conta['FIN_CODIGO'] ?>" target="_blank" class="btn btn-secondary">Reimprimir A4</a>
                                                            <a href="recibo_termico.php?fin=<?= $conta['FIN_CODIGO'] ?>" target="_blank" class="btn btn-secondary">Reimprimir Térmica</a>
                                                        <?php endif; ?>
                                                    </td>
                                                <?php endif; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div id="tab-historico" class="tab-pane">
                            <div class="table-container">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Data Pagto.</th>
                                            <th>Histórico/Obs.</th>
                                            <th>Recebido por</th>
                                            <th>Valor Recebido</th>
                                            <?php if ($pode_estornar): ?><th>Ações</th><?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($historico)): ?>
                                            <tr><td colspan="<?= $pode_estornar ? 5 : 4 ?>">Nenhum pagamento registrado.</td></tr>
                                        <?php else: ?>
                                            <?php foreach($historico as $item_hist): ?>
                                                <?php
                                                    $forma = ucfirst($item_hist['FIN_FORMAPAGAMENTO'] ?? 'Não informado');
                                                    $valor = number_format($item_hist['CEC_VALOR'], 2, ',', '.');
                                                    $desconto = number_format($item_hist['FIN_DESCONTO'] ?? 0, 2, ',', '.');
                                                    $acrescimo = number_format($item_hist['FIN_ACRESCIMO'] ?? 0, 2, ',', '.');
                                                    $estornado = ((int)$item_hist['CEC_ESTORNADO'] === 1);
                                                    $classe = $estornado ? 'text-danger' : 'text-success';
                                                    $status = $estornado ? ' (Estornado)' : '';
                                                ?>
                                                <tr class="<?= $classe ?>">
                                                    <td><?= date('d/m/Y', strtotime($item_hist['CAI_DATA'])) ?></td>
                                                   <td>
                                                        <div style="white-space: normal;">
                                                            <strong>Recebimento:</strong><?= nl2br(htmlspecialchars($item_hist['CEC_COMPLEMENTO'] ?? ''))?><br>
                                                            <strong>Forma:</strong> <?= htmlspecialchars($item_hist['FIN_FORMAPAGAMENTO'] ?? '-') ?><br>
                                                            <strong>Desconto:</strong> R$ <?= number_format($item_hist['FIN_DESCONTO'] ?? 0, 2, ',', '.') ?><br>
                                                            <strong>Acréscimo:</strong> R$ <?= number_format($item_hist['FIN_ACRESCIMO'] ?? 0, 2, ',', '.') ?><br>
                                                            
                                                        </div>
                                                    </td>
                                                    <td><?= htmlspecialchars($item_hist['nome_usuario_baixa'] ?? 'Sistema') ?></td>
                                                    <td class="credito">R$ <?= $valor ?></td>
                                                    <?php if ($pode_estornar): ?>
                                                        <td>
                                                            <?php if ((int)$item_hist['CEC_ESTORNADO'] === 0): ?>
                                                                <button class="btn btn-danger btn-estornar" data-id="<?= $item_hist['CIT_CODIGO'] ?>">Estornar</button>
                                                            <?php else: ?>
                                                                <span style="color: #dc3545; font-weight:bold;">Estornado</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    <?php endif; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="form-footer" style="justify-content: flex-start;">
                     <a href="contas_receber.php" class="btn btn-secondary">Voltar</a>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Modal de Pagamento -->
    <div id="modalPagamento" class="modal" style="display:none;">
        <div class="modal-content large">
            <span class="close-button" onclick="fecharModal()">×</span>
            <h2>Registrar Recebimento (Baixa)</h2>
            
            <form id="formPagamento" method="POST" action="processar_baixa.php">
                <input type="hidden" name="cpf_cnpj" value="<?= htmlspecialchars($cpf_cnpj) ?>">
                <input type="hidden" name="fin_id" id="fin_id_input" value="">

                <fieldset class="form-section">
                    <legend>Valores da Baixa</legend>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Saldo restante da parcela</label>
                            <input type="text" id="saldo_display" readonly class="readonly">
                        </div>
                        <div class="form-group">
                            <label>Valor Recebido *</label>
                            <input type="number" step="0.01" name="valor_recebido" id="valor_recebido" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Desconto</label>
                            <input type="number" step="0.01" name="desconto" value="0.00">
                        </div>
                        <div class="form-group">
                            <label>Acréscimo</label>
                            <input type="number" step="0.01" name="acrescimo" value="0.00">
                        </div>
                    </div>
                </fieldset>

                <fieldset class="form-section">
                    <legend>Forma de Recebimento</legend>
                    <div class="form-group">
                        <label>Forma de Pagamento</label>
                        <select name="forma_pagamento">
                            <option value="dinheiro">Dinheiro</option>
                            <option value="pix">PIX</option>
                            <option value="cartao_credito">Cartão Crédito</option>
                            <option value="cartao_debito">Cartão Débito</option>
                        </select>
                    </div>
                    </fieldset>

                <div class="form-footer">
                    <button type="submit" class="btn btn-success">Confirmar Recebimento</button>
                    <button type="button" class="btn btn-secondary" onclick="fecharModal()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal de confirmação de estorno -->
    <div id="modalConfirmEstorno" class="modal" style="display:none;">
        <div class="modal-content" style="max-width:480px;">
            <span class="close-button" onclick="fecharModalEstorno()">X</span>
            <h2>Confirmar Estorno de Recebimento</h2>
            
            <form id="formEstorno" method="POST" action="processar_estorno.php">
                <input type="hidden" name="cpf_cnpj" value="<?= htmlspecialchars($cpf_cnpj) ?>">
                <input type="hidden" name="cit_id" id="cit_id_input" value="">
                
                <fieldset class="form-section" style="margin-top: 10px; background: transparent; border: none;">
                    <div class="form-group" style="min-width: 100%;">
                        <label for="motivo_estorno">Motivo (Obrigatório)</label>
                        <textarea name="motivo" id="motivo_estorno" rows="3" style="width:100%;" required></textarea>
                    </div>
                </fieldset>
                
                <div class="form-footer" style="border-top: none; padding-top: 0;">
                    <button type="submit" class="btn btn-danger">Confirmar Estorno</button>
                    <button type="button" class="btn btn-secondary" onclick="fecharModalEstorno()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>


<script src="../js/script_menu.js"></script>
<script src="../js/script_mascara.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        
        const tabButtons = document.querySelectorAll('.tab-buttons .tab-button');
        const tabPanes = document.querySelectorAll('.tab-content .tab-pane');
        tabButtons.forEach(btn=>{
            btn.addEventListener('click', ()=>{
                tabButtons.forEach(b=>b.classList.remove('active'));
                tabPanes.forEach(p=>p.classList.remove('active'));
                btn.classList.add('active');
                const target = document.getElementById(btn.dataset.tab);
                if (target) target.classList.add('active');
            });
        });

        
        const checkAll = document.getElementById('check_all');
        const chkList = document.querySelectorAll('.chk-fin');
        const btnReceber = document.getElementById('btnReceberSelecionado');
        function atualizarBotao() {
            const any = Array.from(document.querySelectorAll('.chk-fin:checked')).length > 0;
            if (btnReceber) btnReceber.disabled = !any;
        }
        if (checkAll) checkAll.addEventListener('change', function(){ chkList.forEach(c=>{ if(!c.disabled) c.checked = this.checked; }); atualizarBotao(); });
        chkList.forEach(c=> c.addEventListener('change', atualizarBotao));
        
        
        if (btnReceber) {
            btnReceber.addEventListener('click', function(){
                const selecionadas = Array.from(document.querySelectorAll('.chk-fin:checked'));
                if (selecionadas.length === 0) return alert('Selecione ao menos uma parcela.');

                const ids = selecionadas.map(i => i.value);
                const total = selecionadas.reduce((soma, i) => soma + parseFloat(i.closest('tr').dataset.saldo || 0), 0);

                abrirModalFinanceiro(ids, total);
            });
        }

        
        document.querySelectorAll('.btn-registrar-pagamento').forEach(btn=>{
            btn.addEventListener('click', function(){
                const fin = this.dataset.id;
                const saldo = parseFloat(this.dataset.saldo || 0);
                abrirModalFinanceiro([fin], saldo);
            });
        });

        
        function abrirModalFinanceiro(ids, total) {
            document.getElementById('fin_id_input').value = JSON.stringify(ids);
            document.getElementById('saldo_display').value = 'R$ ' + total.toFixed(2);
            document.getElementById('valor_recebido').value = total.toFixed(2);
            document.getElementById('modalPagamento').style.display = 'flex';
        }
        window.abrirModalFinanceiro = abrirModalFinanceiro;
        window.fecharModal = function(){ document.getElementById('modalPagamento').style.display = 'none'; };

    
        const selectForma = document.querySelector('#formPagamento select[name="forma_pagamento"]');
        let divCartao = document.getElementById('div_bandeiras'); 
        
        if (!divCartao) {
            divCartao = document.createElement('div');
            divCartao.id = 'div_bandeiras';
            divCartao.className = 'form-group'; 
            divCartao.style.display = 'none';
            divCartao.innerHTML = `
                <label>Bandeira do Cartão</label>
                <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:6px;">
                    <label style="font-weight: normal; margin-bottom: 0;"><input type="radio" name="bandeira" value="Mastercard"> Mastercard</label>
                    <label style="font-weight: normal; margin-bottom: 0;"><input type="radio" name="bandeira" value="Visa"> Visa</label>
                    <label style="font-weight: normal; margin-bottom: 0;"><input type="radio" name="bandeira" value="Elo"> Elo</label>
                    <label style="font-weight: normal; margin-bottom: 0;"><input type="radio" name="bandeira" value="American Express"> Amex</label>
                    <label style="font-weight: normal; margin-bottom: 0;"><input type="radio" name="bandeira" value="Hipercard"> Hipercard</label>
                    <label style="font-weight: normal; margin-bottom: 0;"><input type="radio" name="bandeira" value="Outro"> Outro</label>
                </div>
            `;

            selectForma.parentNode.insertAdjacentElement('afterend', divCartao);
        }

        selectForma.addEventListener('change', function(){

            if (this.value === 'cartao_credito' || this.value === 'cartao_debito') {
                divCartao.style.display = 'block';
            } else {
                divCartao.style.display = 'none';
            }
        });
    });
</script>

<script>
    
    document.querySelectorAll('.btn-estornar').forEach(btn => {
        btn.addEventListener('click', function() {
            const cit_id = this.dataset.id;
            if (!cit_id) {
                alert("Erro: identificador do movimento não encontrado.");
                return;
            }
            const modal = document.getElementById('modalConfirmEstorno');
            modal.querySelector('#cit_id_input').value = cit_id;
            modal.querySelector('#motivo_estorno').value = ''; 
            modal.style.display = 'flex';
        });
    });
    window.fecharModalEstorno = function(){ document.getElementById('modalConfirmEstorno').style.display = 'none'; };
</script>


</body>
</html>
