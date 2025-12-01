<?php
session_start();
require_once '../config.php';
require_once '../verificar_permissao.php';


protegerPagina('acessar_financeiro');
$usuario_nome = htmlspecialchars($_SESSION['username'] ?? 'Usuário');



$data_inicio = $_GET['data_inicio'] ?? date('Y-m-d', strtotime('-30 days'));
$data_fim = $_GET['data_fim'] ?? date('Y-m-d');
$filtro_cec_codigo = $_GET['cec_codigo'] ?? ''; 
$filtro_tipo = $_GET['tipo'] ?? 'todos'; 


$centros_de_custo = [];
try {
    $stmt_cc = $conn->query("SELECT CEC_CODIGO, CEC_DESCRICAO FROM CENTRO_CUSTO ORDER BY CEC_DESCRICAO");
    $centros_de_custo = $stmt_cc->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    
}

$saldo_anterior = 0;
$total_entradas = 0;
$total_saidas = 0;
$saldo_final = 0;
$movimentacoes = [];

try {
       
    $params_resumo = [$data_inicio, $data_inicio, $data_fim, $data_inicio, $data_fim];
    
    $sql_resumo = "
        SELECT
            SUM(CASE 
                WHEN c.CAI_DATA < ? THEN
                    (CASE WHEN ci.CEC_CREDEB = 'C' THEN ci.CEC_VALOR ELSE -ci.CEC_VALOR END)
                ELSE 0 
            END) as saldo_anterior,
            
            SUM(CASE 
                WHEN c.CAI_DATA BETWEEN ? AND ? AND ci.CEC_CREDEB = 'C' THEN ci.CEC_VALOR 
                ELSE 0 
            END) as total_entradas,

            SUM(CASE 
                WHEN c.CAI_DATA BETWEEN ? AND ? AND ci.CEC_CREDEB = 'D' THEN ci.CEC_VALOR 
                ELSE 0 
            END) as total_saidas
            
        FROM CAIXA_ITEM ci
        JOIN CAIXA c ON ci.CAI_CODIGO = c.CAI_CODIGO
        WHERE ci.CEC_ESTORNADO = 0
    ";
    
    $stmt_resumo = $conn->prepare($sql_resumo);
    $stmt_resumo->execute($params_resumo);
    $resumo = $stmt_resumo->fetch(PDO::FETCH_ASSOC);

    $saldo_anterior = (float)($resumo['saldo_anterior'] ?? 0);
    $total_entradas = (float)($resumo['total_entradas'] ?? 0);
    $total_saidas = (float)($resumo['total_saidas'] ?? 0);
    $saldo_final = $saldo_anterior + $total_entradas - $total_saidas;
  
    $params_lista = [$data_inicio, $data_fim];
    $where_extra = "";
    
    if (!empty($filtro_cec_codigo)) {
        $where_extra .= " AND ci.CEC_CODIGO = ? ";
        $params_lista[] = $filtro_cec_codigo;
    }
    if ($filtro_tipo === 'C') {
        $where_extra .= " AND ci.CEC_CREDEB = 'C' ";
    }
    if ($filtro_tipo === 'D') {
        $where_extra .= " AND ci.CEC_CREDEB = 'D' ";
    }

    $sql_lista = "
        SELECT 
            c.CAI_DATA,
            ci.CIT_CODIGO,
            ci.CEC_CREDEB,
            ci.CEC_VALOR,
            ci.CEC_COMPLEMENTO,
            ci.CEC_HISTORICO,
            cc.CEC_DESCRICAO AS centro_custo_nome,
            f.FIN_NOME, 
            cl.NOME as cliente_nome 
        FROM CAIXA_ITEM ci
        JOIN CAIXA c ON ci.CAI_CODIGO = c.CAI_CODIGO
        LEFT JOIN CENTRO_CUSTO cc ON ci.CEC_CODIGO = cc.CEC_CODIGO
        LEFT JOIN FINANCEIRO_PAGAR_RECEBER f ON ci.CEC_FIN_CODIGO = f.FIN_CODIGO
        LEFT JOIN CLIENTE cl ON f.CPF_CNPJ = cl.CPF_CNPJ AND f.FIN_CREDEB = 'C'
        WHERE 
            c.CAI_DATA BETWEEN ? AND ?
            AND ci.CEC_ESTORNADO = 0
            {$where_extra}
        ORDER BY 
            c.CAI_DATA ASC, ci.CIT_CODIGO ASC
    ";

    $stmt_lista = $conn->prepare($sql_lista);
    $stmt_lista->execute($params_lista);
    $movimentacoes = $stmt_lista->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $message = "Erro ao consultar o fluxo de caixa: " . $e->getMessage();
    $message_type = "error";
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Fluxo de Caixa - Lindomar Despachante</title>
    <link rel="stylesheet" href="../css/style_sistema.css">
    <style>
       
        .summary-box { display:flex; gap:20px; margin-bottom:20px; }
        .summary-card { background:#fff; padding:12px 16px; border-radius:8px; border:1px solid #e6e6e6; flex:1; text-align:center; }
        .summary-card small { color: #555; font-size: 0.85em; text-transform: uppercase; font-weight: 600; }
        .summary-card p { margin: 5px 0 0 0; font-size: 1.6em; font-weight: bold; }
        
       
        .text-blue { color: #007bff; }
        .text-green { color: #28a745; }
        .text-red { color: #dc3545; }
        .text-dark { color: #343a40; }
        
       
        .data-table .col-entrada { color: #28a745; text-align: right; }
        .data-table .col-saida { color: #dc3545; text-align: right; }
        .data-table .col-data { white-space: nowrap; }
        .data-table .col-descricao { white-space: normal; min-width: 250px; }
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
            <h1>Fluxo de Caixa</h1>
             <?php if (!empty($message)): ?>
                <div class="alert <?= $message_type ?>">
                    <?= $message ?>
                </div>
            <?php endif; ?>

            <div class="form-toolbar">
                <a href="#" class="btn btn-primary" onclick="window.print(); return false;">
                    Imprimir Relatório 
                </a>
            </div>
            <?php if (!empty($message)): ?>
                <div class="alert <?= $message_type ?>">
                    <?= $message ?>
                </div>
            <?php endif; ?>

            <form method="GET" action="fluxo_caixa.php">
                <fieldset class="search-box">
                    <legend>Filtrar Período</legend>
                    <div class="form-group">
                        <label>Data Início</label>
                        <input type="date" name="data_inicio" value="<?= htmlspecialchars($data_inicio) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Data Fim</label>
                        <input type="date" name="data_fim" value="<?= htmlspecialchars($data_fim) ?>" required>
                    </div>
                    <div class="form-group" style="flex-grow: 1.5;">
                        <label>Centro de Custo</label>
                        <select name="cec_codigo">
                            <option value="">-- Todos --</option>
                            <?php foreach ($centros_de_custo as $cc): ?>
                                <option value="<?= $cc['CEC_CODIGO'] ?>" <?= $filtro_cec_codigo == $cc['CEC_CODIGO'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cc['CEC_DESCRICAO']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                     <div class="form-group">
                        <label>Tipo</label>
                        <select name="tipo">
                            <option value="todos" <?= $filtro_tipo == 'todos' ? 'selected' : '' ?>>Todos</option>
                            <option value="C" <?= $filtro_tipo == 'C' ? 'selected' : '' ?>>Entradas</option>
                            <option value="D" <?= $filtro_tipo == 'D' ? 'selected' : '' ?>>Saídas</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                </fieldset>
            </form>

            <div class="summary-box">
                <div class="summary-card">
                    <small>Saldo Anterior (em <?= date('d/m/Y', strtotime($data_inicio)) ?>)</small>
                    <p class="text-blue">R$ <?= number_format($saldo_anterior, 2, ',', '.') ?></p>
                </div>
                <div class="summary-card">
                    <small>Total Entradas (Período)</small>
                    <p class="text-green">+ R$ <?= number_format($total_entradas, 2, ',', '.') ?></p>
                </div>
                <div class="summary-card">
                    <small>Total Saídas (Período)</small>
                    <p class="text-red">- R$ <?= number_format($total_saidas, 2, ',', '.') ?></p>
                </div>
                <div class="summary-card">
                    <small>Saldo Final (em <?= date('d/m/Y', strtotime($data_fim)) ?>)</small>
                    <p class="text-dark">R$ <?= number_format($saldo_final, 2, ',', '.') ?></p>
                </div>
            </div>


            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Descrição / Histórico</th>
                            <th>Centro de Custo</th>
                            <th class="right">Entrada (R$)</th>
                            <th class="right">Saída (R$)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($movimentacoes)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center;">Nenhuma movimentação encontrada para este período.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($movimentacoes as $item): ?>
                                <?php
                                    
                                    $descricao = htmlspecialchars($item['CEC_COMPLEMENTO'] ?? '');
                                    if (empty($descricao)) {
                                        if ($item['CEC_CREDEB'] == 'C') {
                                            $descricao = "Recebimento: " . ($item['cliente_nome'] ?? $item['CEC_HISTORICO'] ?? 'N/D');
                                        } else {
                                            $descricao = "Pagamento: " . ($item['FIN_NOME'] ?? $item['CEC_HISTORICO'] ?? 'N/D');
                                        }
                                    }
                                ?>
                                <tr>
                                    <td class="col-data"><?= date('d/m/Y', strtotime($item['CAI_DATA'])) ?></td>
                                    <td class="col-descricao"><?= $descricao ?></td>
                                    <td><?= htmlspecialchars($item['centro_custo_nome'] ?? 'N/A') ?></td>
                                    
                                    <?php if($item['CEC_CREDEB'] == 'C'): ?>
                                        <td class="col-entrada">+ <?= number_format($item['CEC_VALOR'], 2, ',', '.') ?></td>
                                        <td class="col-saida"></td>
                                    <?php else: ?>
                                        <td class="col-entrada"></td>
                                        <td class="col-saida">- <?= number_format($item['CEC_VALOR'], 2, ',', '.') ?></td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </main>
    </div>

    <script src="../js/script_menu.js"></script>
    <script src="../js/script_mascara.js"></script>
    <script src="../js/script_main.js"></script>
</body>
</html>