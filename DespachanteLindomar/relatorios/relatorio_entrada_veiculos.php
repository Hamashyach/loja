<?php
session_start();
require_once '../config.php';
require_once '../verificar_permissao.php';

protegerPagina('acessar_veiculos'); 
$usuario_nome = htmlspecialchars($_SESSION['username'] ?? 'Usuário');

// --- LÓGICA DE FILTROS ---
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-d', strtotime('-30 days'));
$data_fim = $_GET['data_fim'] ?? date('Y-m-d');
$campo_pesquisa = $_GET['campo_pesquisa'] ?? 'placa';
$valor_pesquisa = $_GET['valor_pesquisa'] ?? '';

// --- LÓGICA DE PAGINAÇÃO ---
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20; 
$offset = ($page - 1) * $limit;

$entradas_veiculos = [];
$total_records = 0;
$total_pages = 1;

try {
    $params = [];
    $where_clauses = [];
    $where_clauses[] = "DATE(pre.PRE_DATA_CADASTRO) BETWEEN ? AND ?";
    $params[] = $data_inicio;
    $params[] = $data_fim;

    if (!empty($valor_pesquisa)) {
        switch ($campo_pesquisa) {
            case 'protocolo_num':
                $where_clauses[] = "pre.PRE_NUMERO = ?";
                $params[] = $valor_pesquisa;
                break;
            case 'proprietario':
                $where_clauses[] = "pev.PEV_PROPRIETARIO LIKE ?";
                $params[] = '%' . $valor_pesquisa . '%';
                break;
            case 'placa':
            default:
                $where_clauses[] = "pev.PEV_VEI_PLACA LIKE ?";
                $params[] = '%' . $valor_pesquisa . '%';
                break;
        }
    }

    $where_sql = "";
    if (!empty($where_clauses)) {
        $where_sql = " WHERE " . implode(" AND ", $where_clauses);
    }

    $sql_count = "
        SELECT COUNT(*) 
        FROM PROTOCOLO_ENTRADA_VEICULO pev
        JOIN PROTOCOLO_ENTRADA pre ON pev.PEV_PRE_CODIGO = pre.PRE_CODIGO
        $where_sql
    ";
    $stmt_count = $conn->prepare($sql_count);
    $stmt_count->execute($params);
    $total_records = $stmt_count->fetchColumn();
    $total_pages = $total_records > 0 ? ceil($total_records / $limit) : 1;

    $sql_lista = "
        SELECT 
            pre.PRE_DATA_CADASTRO,
            pre.PRE_NUMERO,
            pre.PRE_RECEBIDO_POR,
            pev.PEV_VEI_PLACA,
            pev.PEV_VEI_MODELO,
            pev.PEV_PROPRIETARIO,
            pev.PEV_VALOR_TOTAL,
            pev.PEV_VEI_RENAVAM
        FROM PROTOCOLO_ENTRADA_VEICULO pev
        JOIN PROTOCOLO_ENTRADA pre ON pev.PEV_PRE_CODIGO = pre.PRE_CODIGO
        $where_sql
        ORDER BY 
            pre.PRE_DATA_CADASTRO DESC
        LIMIT $limit OFFSET $offset
    ";

    $stmt_lista = $conn->prepare($sql_lista);
    $stmt_lista->execute($params);
    $entradas_veiculos = $stmt_lista->fetchAll(PDO::FETCH_ASSOC);

    // --- LÓGICA DE NAVEGAÇÃO ---
    $nav_params = $_GET; unset($nav_params['page']);
    $query_string = http_build_query($nav_params);
    $link_base = "relatorio_entrada_veiculos.php?" . ($query_string ? $query_string . '&' : ''); // MUDANÇA
    $link_primeiro = $link_base . "page=1";
    $link_anterior = ($page > 1) ? $link_base . "page=" . ($page - 1) : "#";
    $link_proximo = ($page < $total_pages) ? $link_base . "page=" . ($page + 1) : "#";
    $link_ultimo = $link_base . "page=" . $total_pages;


} catch (PDOException $e) {
    $message = "Erro ao consultar Entradas de Veículos: " . $e->getMessage();
    $message_type = "error";
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Relatório de Entradas de Veículos - Lindomar Despachante</title>
    <link rel="stylesheet" href="../css/style_sistema.css">
    <style>
        .data-table .col-data { white-space: nowrap; }
        .data-table .col-prop { white-space: normal; min-width: 250px; }
        .data-table .col-valor { text-align: right; }
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
            <h1>Relatório de Entradas de Veículos (Protocolo)</h1>
            
            <div class="form-toolbar">
                <a href="#" class="btn btn-primary" onclick="window.print(); return false;">
                    Imprimir Relatório
                </a>
                <a href="<?= $link_primeiro ?>" class="btn">Primeiro</a>
                <a href="<?= $link_anterior ?>" class="btn <?= $page <= 1 ? 'disabled' : '' ?>">Anterior</a>
                <a href="<?= $link_proximo ?>" class="btn <?= $page >= $total_pages ? 'disabled' : '' ?>">Próximo</a>
                <a href="<?= $link_ultimo ?>" class="btn">Último</a>
            </div>

             <?php if (!empty($message)): ?>
                <div class="alert <?= $message_type ?>">
                    <?= $message ?>
                </div>
            <?php endif; ?>

            <form method="GET" action="relatorio_entrada_veiculos.php">
                <fieldset class="search-box">
                    <legend>Filtrar por Data de Entrada</legend>
                    <div class="form-group">
                        <label>Data Início</label>
                        <input type="date" name="data_inicio" value="<?= htmlspecialchars($data_inicio) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Data Fim</label>
                        <input type="date" name="data_fim" value="<?= htmlspecialchars($data_fim) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Pesquisar por</label>
                        <select name="campo_pesquisa">
                            <option value="placa" <?= $campo_pesquisa == 'placa' ? 'selected' : '' ?>>Placa</option>
                            <option value="protocolo_num" <?= $campo_pesquisa == 'protocolo_num' ? 'selected' : '' ?>>Nº Protocolo</option>
                            <option value="proprietario" <?= $campo_pesquisa == 'proprietario' ? 'selected' : '' ?>>Nome Proprietário</option>
                        </select>
                    </div>
                    <div class="form-group" style="flex-grow: 1.5;">
                        <label>Valor</label>
                        <input type="text" name="valor_pesquisa" value="<?= htmlspecialchars($valor_pesquisa) ?>">
                    </div>
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                </fieldset>
            </form>

            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Data Entrada</th>
                            <th>Nº Protocolo</th>
                            <th>Placa</th>
                            <th>Modelo</th>
                            <th>Proprietário</th>
                            <th>Renavam</th>
                            <th>Recebido Por</th>
                            <th class="col-valor">Valor (R$)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($entradas_veiculos)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center;">Nenhuma entrada de veículo encontrada para estes filtros.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($entradas_veiculos as $entrada): ?>
                                <tr>
                                    <td class="col-data"><?= $entrada['PRE_DATA_CADASTRO'] ? date('d/m/Y', strtotime($entrada['PRE_DATA_CADASTRO'])) : 'N/A' ?></td>
                                    <td><?= htmlspecialchars($entrada['PRE_NUMERO'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($entrada['PEV_VEI_PLACA'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($entrada['PEV_VEI_MODELO'] ?? 'N/A') ?></td>
                                    <td class="col-prop"><?= htmlspecialchars($entrada['PEV_PROPRIETARIO'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($entrada['PEV_VEI_RENAVAM'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($entrada['PRE_RECEBIDO_POR'] ?? 'N/A') ?></td>
                                    <td class="col-valor"><?= number_format($entrada['PEV_VALOR_TOTAL'], 2, ',', '.') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="pagination">
                <span class="paginacao">Página <?= $page ?> de <?= $total_pages ?> (Total de <?= $total_records ?> entradas de veículos)</span>
            </div>

        </main>
    </div>

    <script src="../js/script_menu.js"></script>
    <script src="../js/script_mascara.js"></script>
    <script src="../js/script_main.js"></script>
</body>
</html>