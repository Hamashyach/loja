<?php
session_start();
require_once '../config.php';
require_once '../verificar_permissao.php';


protegerPagina('acessar_financeiro');

$usuario_nome = htmlspecialchars($_SESSION['username'] ?? 'Usuário');

$centros_de_custo = [];
try {
    $stmt_cc = $conn->query("SELECT CEC_CODIGO, CEC_DESCRICAO FROM CENTRO_CUSTO ORDER BY CEC_DESCRICAO");
    $centros_de_custo = $stmt_cc->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    
    $message = "Erro ao carregar Centros de Custo: " . $e->getMessage();
    $message_type = "error";
}

$params = [];
$where_search = "";
$having_status = "";

$campo_pesquisa = $_GET['campo_pesquisa'] ?? 'nome';
$valor_pesquisa = $_GET['valor_pesquisa'] ?? '';
$status_filtro = $_GET['status_filtro'] ?? '';


if (!empty($valor_pesquisa)) {
    switch ($campo_pesquisa) {
        case 'cpf_cnpj':
            $where_search = " AND f.CPF_CNPJ LIKE ?";
            $params[] = '%' . $valor_pesquisa . '%';
            break;
        case 'nome':
        default:
            $where_search = " AND f.FIN_NOME LIKE ?";
            $params[] = '%' . $valor_pesquisa . '%';
            break;
    }
}

if ($status_filtro === "Atrasado") {
    $having_status = " AND status_financeiro = 'Atrasado'";
} elseif ($status_filtro === "Em Aberto") {
    $having_status = " AND status_financeiro = 'Em Aberto'";
} elseif ($status_filtro === "Pago") {
    $having_status = " AND status_financeiro = 'Pago'";
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;
$fornecedores_devedores = []; 

try {
       
    $sql_base = "
        SELECT 
            f.FIN_NOME AS NOME,
            f.CPF_CNPJ,
            NULL AS TELEFONE, 
            SUM(f.FIN_VALOR) AS total_devido,
            SUM(IFNULL(pagamentos.total_pago_por_conta, 0)) AS total_pago_geral,
            (SUM(f.FIN_VALOR) - SUM(IFNULL(pagamentos.total_pago_por_conta, 0))) AS saldo_devedor,
            MIN(CASE WHEN f.FIN_DATABAIXA IS NULL THEN f.FIN_DATAVENCIMENTO ELSE NULL END) AS proximo_vencimento,
            CASE
                
                WHEN (SUM(f.FIN_VALOR) - SUM(IFNULL(pagamentos.total_pago_por_conta, 0))) <= 0.01 THEN 'Pago'
                WHEN MIN(f.FIN_DATAVENCIMENTO) < CURDATE() AND (SUM(f.FIN_VALOR) - SUM(IFNULL(pagamentos.total_pago_por_conta, 0))) > 0.01 THEN 'Atrasado'
                ELSE 'Em Aberto'
            END AS status_financeiro
        FROM 
            FINANCEIRO_PAGAR_RECEBER f
        LEFT JOIN 
            (
                SELECT 
                    CEC_FIN_CODIGO, 
                    SUM(CEC_VALOR) AS total_pago_por_conta
                FROM 
                    CAIXA_ITEM
                WHERE 
                    CEC_CREDEB = 'D' 
                    AND CEC_ESTORNADO = 0
                GROUP BY 
                    CEC_FIN_CODIGO
            ) pagamentos ON f.FIN_CODIGO = pagamentos.CEC_FIN_CODIGO
        WHERE 
            f.FIN_CREDEB = 'D'
            {$where_search}
        GROUP BY 
            f.CPF_CNPJ, f.FIN_NOME 
        HAVING 
            (saldo_devedor > 0.01 OR total_pago_geral > 0)
            {$having_status} 
    ";

    
    $stmt_count = $conn->prepare("SELECT COUNT(*) FROM ({$sql_base}) as subquery");
    $stmt_count->execute($params); 
    $total_records = $stmt_count->fetchColumn();
    $total_pages = $total_records > 0 ? ceil($total_records / $limit) : 1;

    
    $sql_data = $sql_base . " ORDER BY saldo_devedor DESC LIMIT $limit OFFSET $offset";
    
    $stmt_data = $conn->prepare($sql_data);
    $stmt_data->execute($params); 
    $fornecedores_devedores = $stmt_data->fetchAll(PDO::FETCH_ASSOC);

    
    $nav_params = $_GET; unset($nav_params['page']);
    $query_string = http_build_query($nav_params);
    $link_base = "contas_pagar.php?" . ($query_string ? $query_string . '&' : ''); 
    $link_primeiro = $link_base . "page=1";
    $link_anterior = ($page > 1) ? $link_base . "page=" . ($page - 1) : "#";
    $link_proximo = ($page < $total_pages) ? $link_base . "page=" . ($page + 1) : "#";
    $link_ultimo = $link_base . "page=" . $total_pages;

} catch (PDOException $e) {
    die("Erro ao consultar o banco de dados: " . $e->getMessage());
}
$pode_excluir = temPermissao('excluir_lanc_financeiro');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Contas a Pagar - Lindomar despachante</title> <link rel="stylesheet" href="../css/style_sistema.css">
    <style>
        .saldo-devedor { color: #dc3545; font-weight: bold; }
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
            <h1>Contas a Pagar</h1> 
            
            <?php if (isset($_GET['status'])): ?>
                <div class="alert <?= $_GET['status'] == 'success' ? 'success' : 'error' ?>">
                    <strong><?= $_GET['status'] == 'success' ? 'Sucesso!' : 'Erro!' ?></strong> 
                    <?= htmlspecialchars(urldecode($_GET['msg'] ?? 'Operação realizada.')) ?>
                </div>
            <?php endif; ?>


            <div class="form-toolbar">
                <a href="#" class="btn btn-success" id="btnNovoLancamento">Novo Lançamento</a>
                <a href="#" class="btn btn-primary disabled" id="btnVisualizarFicha">Visualizar Ficha</a>
                <a href="<?= $link_primeiro ?>" class="btn">Primeiro</a>
                <a href="<?= $link_anterior ?>" class="btn <?= $page <= 1 ? 'disabled' : '' ?>">Anterior</a>
                <a href="<?= $link_proximo ?>" class="btn <?= $page >= $total_pages ? 'disabled' : '' ?>">Próximo</a>
                <a href="<?= $link_ultimo ?>" class="btn">Último</a>
            </div>

            <form method="GET" action="contas_pagar.php"> <fieldset class="search-box">
                    <legend>Filtrar Fornecedores</legend> <div class="form-group">
                        <label>Pesquisar por</label>
                        <select name="campo_pesquisa">
                            <option value="nome" <?= $campo_pesquisa == 'nome' ? 'selected' : '' ?>>Nome</option>
                            <option value="cpf_cnpj" <?= $campo_pesquisa == 'cpf_cnpj' ? 'selected' : '' ?>>CPF/CNPJ</option>
                            </select>
                    </div>
                    <div class="form-group" style="flex-grow:2;">
                        <label>Valor</label>
                        <input type="text" name="valor_pesquisa" value="<?= htmlspecialchars($valor_pesquisa) ?>">
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status_filtro">
                            <option value="">Todos</option>
                            <option value="Atrasado" <?= $status_filtro == 'Atrasado' ? 'selected' : '' ?>>Atrasado</option>
                            <option value="Em Aberto" <?= $status_filtro == 'Em Aberto' ? 'selected' : '' ?>>Em Aberto</option>
                            <option value="Pago" <?= $status_filtro == 'Pago' ? 'selected' : '' ?>>Pago</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                </fieldset>
            </form>

            <div class="table-container">
                <p class="table-note">Selecione um fornecedor para visualizar a ficha financeira detalhada.</p> <table class="data-table">
                    <thead>
                        <tr>
                            <th>Fornecedor</th> <th>CPF/CNPJ</th>
                            <th>Telefone</th>
                            <th>Total Devido (R$)</th>
                            <th>Total Pago (R$)</th>
                            <th>Saldo Devedor (R$)</th>
                            <th>PRÓXIMO VENCIMENTO</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($fornecedores_devedores)): ?> <tr><td colspan="8">Nenhum fornecedor com saldo devedor encontrado.</td></tr> <?php endif; ?>
                        
                        <?php foreach($fornecedores_devedores as $fornecedor): ?> 
                            <tr class="clickable-row" data-id="<?= htmlspecialchars($fornecedor['CPF_CNPJ']) ?>">
                                <td><?= htmlspecialchars($fornecedor['NOME']) ?></td>
                                <td><?= htmlspecialchars($fornecedor['CPF_CNPJ']) ?></td>
                                <td><?= htmlspecialchars($fornecedor['TELEFONE'] ?? 'N/A') ?></td>
                                <td><?= number_format($fornecedor['total_devido'], 2, ',', '.') ?></td>
                                <td><?= number_format($fornecedor['total_pago_geral'], 2, ',', '.') ?></td>
                                <td class="saldo-devedor"><?= number_format($fornecedor['saldo_devedor'], 2, ',', '.') ?></td>
                                <td><?= $fornecedor['proximo_vencimento'] ? date('d/m/Y', strtotime($fornecedor['proximo_vencimento'])) : '-' ?></td>
                                <td>
                                    <?php 
                                        $status = $fornecedor['status_financeiro'];
                                        $cor = ($status === 'Atrasado') ? 'red' : (($status === 'Pago') ? 'green' : 'orange');
                                    ?>
                                    <span style="color: <?= $cor ?>; font-weight: bold;">
                                        <?= htmlspecialchars($status) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="pagination"><span class="paginacao">Página <?= $page ?> de <?= $total_pages ?> (Total de <?= $total_records ?> fornecedores)</span></div>
        </main>
    </div>
    
    <div id="modalNovoLancamento" class="modal" style="display:none;">
        <div class="modal-content large" style="height: 650px;"> <span class="close-button" id="closeModalNovoLancamento">×</span>
            <h2>Novo Lançamento (Conta a Pagar)</h2>
            
            <form id="formNovoLancamento" method="POST" action="processar_novo_lancamento_pagar.php">
                <input type="hidden" name="fin_credeb" value="D">

                <fieldset class="form-section">
                    <legend>Dados do Fornecedor (Opção 1)</legend>
                    <div class="form-row">
                        <div class="form-group" style="flex-grow: 2;">
                            <label for="fin_nome">Nome do Fornecedor *</label>
                            <input type="text" id="fin_nome" name="fin_nome" required>
                        </div>
                        <div class="form-group">
                            <label for="cpf_cnpj">CPF/CNPJ</label>
                            <input type="text" id="cpf_cnpj" name="cpf_cnpj">
                        </div>
                    </div>
                </fieldset>

                <fieldset class="form-section">
                    <legend>Dados da Despesa</legend>
                    <div class="form-row">
                        <div class="form-group" style="flex-grow: 2;">
                            <label for="fin_descricao">Descrição da Despesa *</label>
                            <input type="text" id="fin_descricao" name="fin_descricao" required>
                        </div>
                        <div class="form-group">
                            <label for="cec_codigo">Centro de Custo *</label>
                            <select id="cec_codigo" name="cec_codigo" required>
                                <option value="">Selecione...</option>
                                <?php foreach ($centros_de_custo as $cc): ?>
                                    <option value="<?= $cc['CEC_CODIGO'] ?>">
                                        <?= htmlspecialchars($cc['CEC_DESCRICAO']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="fin_valor">Valor (R$) *</label>
                            <input type="text" id="fin_valor" name="fin_valor" class="money" required>
                        </div>
                        <div class="form-group">
                            <label for="fin_dataemissao">Data Emissão *</label>
                            <input type="date" id="fin_dataemissao" name="fin_dataemissao" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="fin_datavencimento">Data Vencimento *</label>
                            <input type="date" id="fin_datavencimento" name="fin_datavencimento" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group full-width">
                            <label for="fin_obs">Observações (Opcional)</label>
                            <textarea id="fin_obs" name="fin_obs" rows="2"></textarea>
                        </div>
                    </div>
                </fieldset>

                <div class="form-footer">
                    <button type="submit" class="btn btn-primary">Salvar Lançamento</button>
                    <button type="button" class="btn btn-secondary" id="btnCancelarNovoLancamento">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
<div id="customConfirmModal" class="modal-confirm" style="display: none;">...</div>
    

    
    <script src="../js/script_menu.js"></script>
    <script src="../js/script_mascara.js"></script>
    <script src="../js/script_main.js"></script>
    <script src="../js/script.js"></script>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const btnVisualizarFicha = document.getElementById('btnVisualizarFicha');
        
        if (btnVisualizarFicha) {
            btnVisualizarFicha.addEventListener('click', function(e) {
                e.preventDefault(); 
                if (this.classList.contains('disabled')) return;

                const selectedRow = document.querySelector('.data-table .selected');
                if (selectedRow) {
                    const cpfCnpj = selectedRow.dataset.id; 
                    if (cpfCnpj) {
                        window.location.href = `ficha_financeira_pagar.php?cpf_cnpj=${encodeURIComponent(cpfCnpj)}`;
                    } else {
                        alert('Erro: Não foi possível obter o CPF/CNPJ do fornecedor selecionado.');
                    }
                }
            });
        }
    });
    </script>
</body>
</html>