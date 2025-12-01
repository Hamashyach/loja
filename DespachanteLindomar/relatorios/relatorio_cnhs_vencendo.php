<?php
session_start();
require_once '../config.php';
require_once '../verificar_permissao.php';

protegerPagina('acessar_veiculos'); 
$usuario_nome = htmlspecialchars($_SESSION['username'] ?? 'Usuário');

// --- LÓGICA DE FILTROS ---
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-d');
$data_fim = $_GET['data_fim'] ?? date('Y-m-d', strtotime('+30 days'));
$campo_pesquisa = $_GET['campo_pesquisa'] ?? 'nome';
$valor_pesquisa = $_GET['valor_pesquisa'] ?? '';

// --- LÓGICA DE PAGINAÇÃO ---
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20; 
$offset = ($page - 1) * $limit;

$clientes_cnh = [];
$total_records = 0;
$total_pages = 1;

try {
    $params = [];
    $where_clauses = [];
    $where_clauses[] = "c.CLI_CNH_VENCIMENTO IS NOT NULL";
    $where_clauses[] = "DATE(c.CLI_CNH_VENCIMENTO) BETWEEN ? AND ?";
    $params[] = $data_inicio;
    $params[] = $data_fim;

    if (!empty($valor_pesquisa)) {
        switch ($campo_pesquisa) {
            case 'cnh':
                $where_clauses[] = "c.CNH LIKE ?"; 
                $params[] = '%' . $valor_pesquisa . '%';
                break;
            case 'cpf_cnpj':
                $where_clauses[] = "c.CPF_CNPJ LIKE ?";
                $params[] = '%' . $valor_pesquisa . '%';
                break;
            case 'nome':
            default:
                $where_clauses[] = "c.NOME LIKE ?";
                $params[] = '%' . $valor_pesquisa . '%';
                break;
        }
    }

    $where_sql = "";
    if (!empty($where_clauses)) {
        $where_sql = " WHERE " . implode(" AND ", $where_clauses);
    }

    $sql_count = "SELECT COUNT(*) FROM CLIENTE c $where_sql";
    $stmt_count = $conn->prepare($sql_count);
    $stmt_count->execute($params);
    $total_records = $stmt_count->fetchColumn();
    $total_pages = $total_records > 0 ? ceil($total_records / $limit) : 1;

    $sql_lista = "
        SELECT 
            c.CODIGO,
            c.NOME,
            c.CNH, -- Coluna do número da CNH
            c.CLI_CNH_VENCIMENTO, -- Coluna da data de vencimento
            c.TELEFONE,
            c.TELEFONE_CELULAR
        FROM CLIENTE c
        $where_sql
        ORDER BY 
            c.CLI_CNH_VENCIMENTO ASC -- Mais próximos de vencer primeiro
        LIMIT $limit OFFSET $offset
    ";

    $stmt_lista = $conn->prepare($sql_lista);
    $stmt_lista->execute($params);
    $clientes_cnh = $stmt_lista->fetchAll(PDO::FETCH_ASSOC);

    // --- LÓGICA DE NAVEGAÇÃO ---
    $nav_params = $_GET; unset($nav_params['page']);
    $query_string = http_build_query($nav_params);
    $link_base = "relatorio_cnhs_vencendo.php?" . ($query_string ? $query_string . '&' : ''); // MUDANÇA
    $link_primeiro = $link_base . "page=1";
    $link_anterior = ($page > 1) ? $link_base . "page=" . ($page - 1) : "#";
    $link_proximo = ($page < $total_pages) ? $link_base . "page=" . ($page + 1) : "#";
    $link_ultimo = $link_base . "page=" . $total_pages;


} catch (PDOException $e) {
    $message = "Erro ao consultar CNHs: " . $e->getMessage();
    $message_type = "error";
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Relatório de CNHs Vencendo - Lindomar Despachante</title>
    <link rel="stylesheet" href="../css/style_sistema.css">
    <style>
        .data-table .col-data { white-space: nowrap; }
        .data-table .col-cliente { white-space: normal; min-width: 250px; }
        /* Estilo para destacar CNHs já vencidas (caso o filtro inclua datas passadas) */
        .data-table tr.vencido td {
            background-color: #f8d7da !important;
            color: #721c24;
            font-weight: bold;
        }
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
            <h1>Relatório de CNHs Vencendo</h1>
            
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

            <form method="GET" action="relatorio_cnhs_vencendo.php">
                <fieldset class="search-box">
                    <legend>Filtrar por Data de Vencimento</legend>
                    <div class="form-group">
                        <label>Vencimento De</label>
                        <input type="date" name="data_inicio" value="<?= htmlspecialchars($data_inicio) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Vencimento Até</label>
                        <input type="date" name="data_fim" value="<?= htmlspecialchars($data_fim) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Pesquisar por</label>
                        <select name="campo_pesquisa">
                            <option value="nome" <?= $campo_pesquisa == 'nome' ? 'selected' : '' ?>>Nome</option>
                            <option value="cpf_cnpj" <?= $campo_pesquisa == 'cpf_cnpj' ? 'selected' : '' ?>>CPF/CNPJ</option>
                            <option value="cnh" <?= $campo_pesquisa == 'cnh' ? 'selected' : '' ?>>Nº CNH</option>
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
                            <th>Cliente</th>
                            <th>Nº CNH</th>
                            <th>Celular</th>
                            <th>Telefone</th>
                            <th>Vencimento</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($clientes_cnh)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center;">Nenhuma CNH vencendo no período selecionado.</td>
                            </tr>
                        <?php else: ?>
                            <?php 
                            $hoje = date('Y-m-d');
                            foreach($clientes_cnh as $cliente): 
                                $data_venc = $cliente['CLI_CNH_VENCIMENTO'];
                                $classe_vencido = (strtotime($data_venc) < strtotime($hoje)) ? 'vencido' : '';
                            ?>
                                <tr class="<?= $classe_vencido ?>">
                                    <td class="col-cliente"><?= htmlspecialchars($cliente['NOME']) ?></td>
                                    <td><?= htmlspecialchars($cliente['CNH'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($cliente['TELEFONE_CELULAR'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($cliente['TELEFONE'] ?? 'N/A') ?></td>
                                    <td class="col-data"><?= $data_venc ? date('d/m/Y', strtotime($data_venc)) : 'N/A' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="pagination">
                <span class="paginacao">Página <?= $page ?> de <?= $total_pages ?> (Total de <?= $total_records ?> clientes)</span>
            </div>

        </main>
    </div>

    <script src="../js/script_menu.js"></script>
    <script src="../js/script_mascara.js"></script>
    <script src="../js/script_main.js"></script>
</body>
</html>