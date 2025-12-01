<?php
session_start();
require_once '../config.php';
require_once '../verificar_permissao.php';

// Proteção da página
protegerPagina('acessar_financeiro');
$usuario_nome = htmlspecialchars($_SESSION['username'] ?? 'Usuário');
// --- LÓGICA DE FILTROS ---
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-d');
$data_fim = $_GET['data_fim'] ?? date('Y-m-d', strtotime('+30 days'));
$filtro_cec_codigo = $_GET['cec_codigo'] ?? ''; 
$filtro_status = $_GET['status'] ?? 'apagar'; 

$centros_de_custo = [];
try {
    $stmt_cc = $conn->query("SELECT CEC_CODIGO, CEC_DESCRICAO FROM CENTRO_CUSTO ORDER BY CEC_DESCRICAO");
    $centros_de_custo = $stmt_cc->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    
}

$resumo_apagar = 0;
$resumo_pago = 0;
$resumo_vencido = 0;
$total_periodo = 0;
$lancamentos = []; 

try {
    $params_sql = [];
    $where_clauses = [];
    $where_clauses[] = "f.FIN_CREDEB = 'D'";

    $where_clauses[] = "f.FIN_DATAVENCIMENTO BETWEEN ? AND ?";
    $params_sql[] = $data_inicio;
    $params_sql[] = $data_fim;

  
    if (!empty($filtro_cec_codigo)) {
        $where_clauses[] = "f.CEC_CODIGO = ?";
        $params_sql[] = $filtro_cec_codigo;
    }

    if ($filtro_status === 'pago') {
        $where_clauses[] = "f.FIN_DATABAIXA IS NOT NULL";
    } elseif ($filtro_status === 'apagar') {
        $where_clauses[] = "f.FIN_DATABAIXA IS NULL AND f.FIN_DATAVENCIMENTO >= CURDATE()";
    } elseif ($filtro_status === 'vencido') {
        $where_clauses[] = "f.FIN_DATABAIXA IS NULL AND f.FIN_DATAVENCIMENTO < CURDATE()";
    }
    

    $where_sql = " WHERE " . implode(" AND ", $where_clauses);

    $where_resumo = " WHERE f.FIN_CREDEB = 'D' AND f.FIN_DATAVENCIMENTO BETWEEN ? AND ? " .
                    (!empty($filtro_cec_codigo) ? "AND f.CEC_CODIGO = ?" : "");
    
    $params_resumo = [$data_inicio, $data_fim];
    if (!empty($filtro_cec_codigo)) $params_resumo[] = $filtro_cec_codigo;

    $sql_resumo = "
        SELECT
            SUM(f.FIN_VALOR) as total_periodo,
            
            SUM(CASE 
                WHEN f.FIN_DATABAIXA IS NULL AND f.FIN_DATAVENCIMENTO < CURDATE() THEN f.FIN_VALOR 
                ELSE 0 
            END) as total_vencido,
            
            SUM(CASE 
                WHEN f.FIN_DATABAIXA IS NULL AND f.FIN_DATAVENCIMENTO >= CURDATE() THEN f.FIN_VALOR 
                ELSE 0 
            END) as total_apagar,
            
            SUM(CASE 
                WHEN f.FIN_DATABAIXA IS NOT NULL THEN f.FIN_VALOR 
                ELSE 0 
            END) as total_pago
            
        FROM FINANCEIRO_PAGAR_RECEBER f
        $where_resumo
    ";
    
    $stmt_resumo = $conn->prepare($sql_resumo);
    $stmt_resumo->execute($params_resumo);
    $resumo = $stmt_resumo->fetch(PDO::FETCH_ASSOC);

    $total_periodo = (float)($resumo['total_periodo'] ?? 0);
    $resumo_vencido = (float)($resumo['total_vencido'] ?? 0);
    $resumo_apagar = (float)($resumo['total_apagar'] ?? 0);
    $resumo_pago = (float)($resumo['total_pago'] ?? 0);

    $sql_lista = "
        SELECT 
            f.FIN_CODIGO,
            f.FIN_DATAVENCIMENTO,
            f.FIN_DATABAIXA,
            f.FIN_NOME,
            f.FIN_DESCRICAO,
            f.FIN_VALOR,
            f.FIN_VALORTOTAL,
            cc.CEC_DESCRICAO AS centro_custo_nome
        FROM FINANCEIRO_PAGAR_RECEBER f
        LEFT JOIN CENTRO_CUSTO cc ON f.CEC_CODIGO = cc.CEC_CODIGO
        $where_sql
        ORDER BY 
            f.FIN_DATAVENCIMENTO ASC, f.FIN_CODIGO ASC
    ";

    $stmt_lista = $conn->prepare($sql_lista);
    $stmt_lista->execute($params_sql);
    $lancamentos = $stmt_lista->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $message = "Erro ao consultar o relatório: " . $e->getMessage();
    $message_type = "error";
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Relatório de Contas a Pagar - Lindomar Despachante</title>
    <link rel="stylesheet" href="../css/style_sistema.css">
    <style>
        .summary-box { display:flex; gap:20px; margin-bottom:20px; }
        .summary-card { background:#fff; padding:12px 16px; border-radius:8px; border:1px solid #e6e6e6; flex:1; text-align:center; }
        .summary-card small { color: #555; font-size: 0.85em; text-transform: uppercase; font-weight: 600; }
        .summary-card p { margin: 5px 0 0 0; font-size: 1.6em; font-weight: bold; }
        
        .text-orange { color: #fd7e14; }
        .text-green { color: #28a745; }
        .text-red { color: #dc3545; }
        .text-dark { color: #343a40; }
        
        .data-table .col-valor { text-align: right; }
        .data-table .col-data { white-space: nowrap; }
        .data-table .col-descricao { white-space: normal; min-width: 250px; }
        
        /* Estilos para Status */
        .status-badge { padding:6px 8px; border-radius:6px; display:inline-block; font-weight:600; font-size: 0.9em; }
        .status-pago { color:#155724; background:#d4edda; }
        .status-atrasado { color:#721c24; background:#f8d7da; }
        .status-aberto { color:#856404; background:#fff3cd; } 
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
            <a href="../logout.php" title="Fazer Logoff">Sair</a> 
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
                            <li><a href="../financeiro/lembretes.php"><span>Lembretes Financeiros</span></a></li>
                            <li><a href="../financeiro/contas_pagar.php"><span>Contas a Pagar</span></a></li>
                            <li><a href="../financeiro/contas_receber.php"><span>Contas a Receber</span></a></li>
                            <li><a href="../financeiro/fluxo_caixa.php"><span>Fluxo de Caixa</span></a></li>
                            <li><a href="../financeiro/cadastro_centro_custo.php"><span>Centro de Custo</span></a></li>
                        </ul>
                    </li>

                      <li>
                        <a href="#" class="submenu-toggle">
                            <img src="../img/icon/documento.png" alt="" class="nav-icon">
                            <span>Relatórios</span>
                        </a>
                        <ul class="submenu">
                            <li><a href="relatorio_clientes.php"><span>Clientes</span></a></li>
                            <li><a href="relatorio_veiculos.php"><span>Veículos</span></a></li>
                            <li><a href="relatorio_entrada_veiculos.php"><span>Entrada de Veículos</span></a></li>
                            <li><a href="relatorio_ordem_servico.php"><span>Ordem de Serviço</span></a></li>
                            <li><a href="relatorio_processos.php"><span>Processos</span></a></li>
                            <li><a href="relatorio_contas_pagar.php"><span>Contas a Pagar</span></a></li>
                            <li><a href="relatorio_contas_receber.php"><span>Contas a Receber</span></a></li>                           
                            <li><a href="relatorio_cnhs_vencendo.php"><span>CNH's Vencendo</span></a></li>            
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
            <h1>Relatório de Contas a Pagar</h1>
            
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

            <form method="GET" action="relatorio_contas_pagar.php">
                <fieldset class="search-box">
                    <legend>Filtrar Período (por Vencimento)</legend>
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
                        <label>Status</label>
                        <select name="status">
                            <option value="todos" <?= $filtro_status == 'todos' ? 'selected' : '' ?>>Todos</option>
                            <option value="apagar" <?= $filtro_status == 'apagar' ? 'selected' : '' ?>>A Pagar</option>
                            <option value="vencido" <?= $filtro_status == 'vencido' ? 'selected' : '' ?>>Vencido</option>
                            <option value="pago" <?= $filtro_status == 'pago' ? 'selected' : '' ?>>Pago</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                </fieldset>
            </form>

            <div class="summary-box">
                <div class="summary-card">
                    <small>Total (Período)</small>
                    <p class="text-dark">R$ <?= number_format($total_periodo, 2, ',', '.') ?></p>
                </div>
                <div class="summary-card">
                    <small>A Pagar (Em dia)</small>
                    <p class="text-orange">R$ <?= number_format($resumo_apagar, 2, ',', '.') ?></p>
                </div>
                <div class="summary-card">
                    <small>Vencido (A Pagar)</small>
                    <p class="text-red">R$ <?= number_format($resumo_vencido, 2, ',', '.') ?></p>
                </div>
                <div class="summary-card">
                    <small>Total Pago (Período)</small>
                    <p class="text-green">R$ <?= number_format($resumo_pago, 2, ',', '.') ?></p>
                </div>
            </div>


            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Vencimento</th>
                            <th>Fornecedor</th>
                            <th>Descrição</th>
                            <th>Centro de Custo</th>
                            <th>Status</th>
                            <th class="col-valor">Valor (R$)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($lancamentos)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center;">Nenhum lançamento encontrado para estes filtros.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($lancamentos as $item): ?>
                                <?php
                                    // Define o Status
                                    $status_texto = '';
                                    $status_classe = '';
                                    if (!empty($item['FIN_DATABAIXA'])) {
                                        $status_texto = 'Pago';
                                        $status_classe = 'status-pago';
                                    } elseif (strtotime($item['FIN_DATAVENCIMENTO']) < strtotime(date('Y-m-d'))) {
                                        $status_texto = 'Vencido';
                                        $status_classe = 'status-atrasado';
                                    } else {
                                        $status_texto = 'A Pagar';
                                        $status_classe = 'status-aberto';
                                    }
                                ?>
                                <tr>
                                    <td class="col-data"><?= date('d/m/Y', strtotime($item['FIN_DATAVENCIMENTO'])) ?></td>
                                    <td><?= htmlspecialchars($item['FIN_NOME'] ?? 'N/A') ?></td>
                                    <td class="col-descricao"><?= htmlspecialchars($item['FIN_DESCRICAO']) ?></td>
                                    <td><?= htmlspecialchars($item['centro_custo_nome'] ?? 'N/A') ?></td>
                                    <td><span class="status-badge <?= $status_classe ?>"><?= $status_texto ?></span></td>
                                    <td class="col-valor"><?= number_format($item['FIN_VALOR'], 2, ',', '.') ?></td>
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