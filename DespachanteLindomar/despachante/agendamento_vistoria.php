<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../index.php");
    exit;
}

$usuario_nome = htmlspecialchars($_SESSION['username'] ?? 'Usuário');
$usuario_login = htmlspecialchars($_SESSION['login'] ?? '');

require_once '../verificar_permissao.php';
protegerPagina('criar_editar_vistoria'); 
require_once '../config.php';

// --- INICIALIZAÇÃO DE VARIÁVEIS ---
$aba_ativa = 'consulta_agendamento';
$agendamento_para_editar = [];
$agendamentos = [];
$lista_postos = [];

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$message = '';
$message_type = '';
if (!empty($_GET['status'])) {
    switch ($_GET['status']) {
        case 'success':
            $message = '<strong>Sucesso!</strong> Agendamento salvo com sucesso.';
            $message_type = 'success'; break;
        case 'deleted':
            $message = 'Agendamento excluído com sucesso.';
            $message_type = 'success'; break;
        case 'error':
            $errorMessage = $_GET['msg'] ?? 'Ocorreu um problema.';
            $message = '<strong>Erro!</strong> ' . htmlspecialchars(urldecode($errorMessage));
            $message_type = 'error'; break;
    }
}
try {
    $stmt_posto = $conn->query("SELECT POV_CODIGO, POV_NOME FROM posto_vistoria ORDER BY POV_NOME");
    $lista_postos = $stmt_posto->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = "Erro ao carregar Postos de Vistoria: " . $e->getMessage();
    $message_type = 'error';
}

// --- LÓGICA DE AÇÕES ---
$action = $_GET['action'] ?? null;
$id = $_GET['id'] ?? null;

if ($action === 'new') {
    $aba_ativa = 'cadastro_agendamento';
}

// Lógica de Edição/Visualização
if (($action === 'edit' || $action === 'view') && $id) {
    $aba_ativa = 'cadastro_agendamento';
    
    $sql_edicao = "
        SELECT
            av.*, 
            c.NOME AS CLIENTE_NOME,
            c.CPF_CNPJ AS CLIENTE_CPF_CNPJ,
            v.MODELO AS VEICULO_MODELO,
            v.PLACA_UF AS VEICULO_PLACA,
            v.RENAVAM AS VEICULO_RENAVAM,
            v.CHASSI AS VEICULO_CHASSI,
            pv.POV_NOME AS POSTO_NOME
        FROM
            AGENDAMENTO_VISTORIA AS av
        LEFT JOIN
            CLIENTE AS c ON av.VIS_CLI_CODIGO = c.CODIGO
        LEFT JOIN
            VEICULO AS v ON av.VIS_VEI_CODIGO = v.CODIGO
        LEFT JOIN
            posto_vistoria AS pv ON av.VIS_PVI_CODIGO = pv.POV_CODIGO
        WHERE
            av.VIS_CODIGO = ?
    ";
    
    $stmt = $conn->prepare($sql_edicao);
    $stmt->execute([$id]);
    $agendamento_para_editar = $stmt->fetch(PDO::FETCH_ASSOC);
    $cliente_nome = $agendamento_para_editar['CLIENTE_NOME'] ?? '';
    $cliente_cpf_cnpj = $agendamento_para_editar['CLIENTE_CPF_CNPJ'] ?? '';
    $veiculo_modelo = $agendamento_para_editar['VEICULO_MODELO'] ?? '';
    $veiculo_placa = $agendamento_para_editar['VEICULO_PLACA'] ?? '';
    $veiculo_renavam = $agendamento_para_editar['VEICULO_RENAVAM'] ?? '';
    $veiculo_chassi = $agendamento_para_editar['VEICULO_CHASSI'] ?? '';
}

// Lógica de Exclusão
if ($action === 'delete' && $id) {
    try {
        $stmt_del = $conn->prepare("DELETE FROM AGENDAMENTO_VISTORIA WHERE VIS_CODIGO = ?");
        $stmt_del->execute([$id]);
        header("Location: agendamento_vistoria.php?status=deleted");
        exit;
    } catch (PDOException $e) {
        header("Location: agendamento_vistoria.php?status=error&msg=" . urlencode($e->getMessage()));
        exit;
    }
}

// LÓGICA DE SALVAR 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $CODIGO = isset($_POST['VIS_CODIGO']) && $_POST['VIS_CODIGO'] !== '' ? $_POST['VIS_CODIGO'] : null;
    $cpf_cnpj_cliente = $_POST['ORS_CPF_CNPJ'] ?? null; 
    $cli_codigo = null;
    
    if ($cpf_cnpj_cliente) {
        $stmt_find = $conn->prepare("SELECT CODIGO FROM CLIENTE WHERE CPF_CNPJ = ?");
        $stmt_find->execute([$cpf_cnpj_cliente]);
        $cli_codigo = $stmt_find->fetchColumn();
    }
    $dados = [
        'VIS_CLI_CODIGO' => $cli_codigo, 
        'VIS_VEI_CODIGO' => $_POST['ORS_VEI_CODIGO'] ?? null, 
        'VIS_PVI_CODIGO' => $_POST['VIS_PVI_CODIGO'] ?? null,
        'VIS_DATA_AGENDAMENTO' => $_POST['VIS_DATA_AGENDAMENTO'] ?? null,
        'VIS_SITUACAO' => $_POST['VIS_SITUACAO'] ?? 'Agendado',
        'VIS_OBSERVACOES' => $_POST['VIS_OBSERVACOES'] ?? null
    ];
    
    foreach ($dados as $k => $v) { if ($v === '') $dados[$k] = null; }

    try {
        $conn->beginTransaction();
        if (empty($CODIGO)) {
            $colunas = implode(', ', array_keys($dados));
            $placeholders = implode(', ', array_fill(0, count($dados), '?'));
            $sql = "INSERT INTO AGENDAMENTO_VISTORIA ($colunas) VALUES ($placeholders)";
            $stmt = $conn->prepare($sql);
            $stmt->execute(array_values($dados));
            $CODIGO = $conn->lastInsertId();
        } else {
            $set = [];
            foreach (array_keys($dados) as $col) { $set[] = "$col = ?"; }
            $sql = "UPDATE AGENDAMENTO_VISTORIA SET " . implode(', ', $set) . " WHERE VIS_CODIGO = ?";
            $valores = array_values($dados);
            $valores[] = $CODIGO;
            $stmt = $conn->prepare($sql);
            $stmt->execute($valores);
        }
        $conn->commit();
        header("Location: agendamento_vistoria.php?action=edit&id=" . $CODIGO . "&status=success");
        exit;
    } catch (PDOException $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        die("Erro ao salvar agendamento: " . $e->getMessage());
    }
}

// --- LÓGICA DE BUSCA E CONSULTA ---
$sql_base="
    SELECT
        av.VIS_CODIGO, av.VIS_DATA_AGENDAMENTO, av.VIS_SITUACAO,
        c.NOME AS CLIENTE_NOME, v.PLACA_UF AS VEICULO_PLACA, pv.POV_NOME AS POSTO_NOME
    FROM AGENDAMENTO_VISTORIA AS av
    LEFT JOIN CLIENTE AS c ON av.VIS_CLI_CODIGO = c.CODIGO
    LEFT JOIN VEICULO AS v ON av.VIS_VEI_CODIGO = v.CODIGO
    LEFT JOIN posto_vistoria AS pv ON av.VIS_PVI_CODIGO = pv.POV_CODIGO
";
$sql_count_base = "
    SELECT COUNT(*) FROM AGENDAMENTO_VISTORIA AS av
    LEFT JOIN CLIENTE AS c ON av.VIS_CLI_CODIGO = c.CODIGO
    LEFT JOIN VEICULO AS v ON av.VIS_VEI_CODIGO = v.CODIGO
";
$params = [];
$where_clauses = [];
if (!empty($_GET['valor_pesquisa'])) {
    $campo = $_GET['campo_pesquisa']; $valor_param = '%' . $_GET['valor_pesquisa'] . '%';
    if ($campo == 'codigo') { $where_clauses[] = "av.VIS_CODIGO LIKE ?"; } 
    elseif ($campo == 'nome') { $where_clauses[] = "c.NOME LIKE ?"; } 
    elseif ($campo == 'placa') { $where_clauses[] = "v.PLACA_UF LIKE ?"; }
    if (!empty($where_clauses)) { $params[] = $valor_param; }
}
if (!empty($where_clauses)) {
    $sql_base .= " WHERE " . implode(' AND ', $where_clauses);
    $sql_count_base .= " WHERE " . implode(' AND ', $where_clauses);
}
// Lógica de paginação
$total_stmt = $conn->prepare($sql_count_base); $total_stmt->execute($params);
$total_records = $total_stmt->fetchColumn();
$total_pages = $total_records > 0 ? ceil($total_records / $limit) : 1;
$limit = (int)$limit; $offset = (int)$offset;
$sql_final = $sql_base ." ORDER BY av.VIS_DATA_AGENDAMENTO DESC LIMIT $limit OFFSET $offset";
$stmt = $conn->prepare($sql_final); $stmt->execute($params);
$agendamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$query_string = http_build_query(array_merge($_GET, ['page' => '']));
$link_base = "agendamento_vistoria.php?" . $query_string;
$link_primeiro = $link_base . "1";
$link_anterior = ($page > 1) ? $link_base . ($page - 1) : "#";
$link_proximo = ($page < $total_pages) ? $link_base . ($page + 1) : "#";
$link_ultimo = $link_base . $total_pages;

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agendamento de Vistoria</title>
    <link rel="stylesheet" href="../css/style_sistema.css">
    <style>
        .vehicle-block { border: 1px solid #ccc; border-radius: 5px; padding: 15px; margin-bottom: 20px; background: #f9f9f9; }
        .vehicle-block-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .vehicle-block-header h3 { margin: 0; color: #2c3e50; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 5px; border: 1px solid transparent; }
        .alert.success { color: #155724; background-color: #d4edda; border-color: #c3e6cb; }
        .alert.error { color: #721c24; background-color: #f8d7da; border-color: #f5c6cb; }
        .modal-confirm { display: none;  position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%;overflow: auto; background-color: rgba(0, 0, 0, 0.6);}
        .modal-confirm-content { background-color: #fefefe; margin: 15% auto;  padding: 25px 30px; border: 1px solid #888; width: 90%; max-width: 400px; border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); text-align: center; }
        .modal-confirm-content p { font-size: 1.1em; margin: 0 0 20px 0; color: #333; }
        .modal-confirm-buttons { display: flex; justify-content: center;  gap: 15px; }
        .modal-confirm-buttons button { padding: 10px 25px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; }
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
                             <li><a href="anotacao_recado.php"><span>Anotações e Recados</span></a></li>
                            <li><a href="agendamento_vistoria.php"><span>Agendamento de Vistoria</span></a></li>   
                            <li><a href="cadastro_clientes.php"><span>Cliente</span></a></li>
                            <li><a href="cadastro_veiculo.php"><span>Veículo</span></a></li>
                            <li><a href="cadastro_ordem_servico.php"><span>Ordem de Serviços</span></a></li>
                            <li><a href="cadastro_processos.php"><span>Processos</span></a></li>
                            <li><a href="cadastro_situacao_processo.php"><span>Situações de Processo</span></a></li>
                            <li><a href="cadastro_primeiro_emplacamento.php"><span>Primeiro Emplacamento</span></a></li>     
                            <li><a href="protocolo_entrada.php"><span>Protocolo Entrada Documentos</span></a></li>
                            <li><a href="protocolo_saida.php"><span>Protocolo Saída Documentos</span></a></li> 
                            <li><a href="cadastro_recibo.php"><span>Recibos</span></a></li> 
                            <li><a href="cadastro_intencao_venda.php"><span>Intenção de Venda (ATPV-e)</span></a></li> 
                            
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
                            <li><a href="../relatorios/relatorio_clientes.php"><span>Clientes</span></a></li>
                            <li><a href="../relatorios/relatorio_veiculos.php"><span>Veículos</span></a></li>
                            <li><a href="../relatorios/relatorio_entrada_veiculos.php"><span>Entrada de Veículos</span></a></li>
                            <li><a href="../relatorios/relatorio_ordem_servico.php"><span>Ordem de Serviço</span></a></li>
                            <li><a href="../relatorios/relatorio_processos.php"><span>Processos</span></a></li>
                            <li><a href="../relatorios/relatorio_contas_pagar.php"><span>Contas a Pagar</span></a></li>
                            <li><a href="../relatorios/relatorio_contas_receber.php"><span>Contas a Receber</span></a></li>                           
                            <li><a href="../relatorios/relatorio_cnhs_vencendo.php"><span>CNH's Vencendo</span></a></li>            
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
            <h1>Agendamento de Vistoria</h1>

             <?php if (!empty($message)): ?>
                <div class="alert <?= $message_type ?>"><?= $message ?></div>
            <?php endif; ?>

            <?php 
                if (($action === 'edit') && !empty($agendamento_para_editar)) :
            ?>

            <div class= "form-tollbar client-header" style="display: flex; justify-content: space-between;">
                <div class="client-info">
                    <strong><?= htmlspecialchars($agendamento_para_editar['CLIENTE_NOME'] ?? '')?></strong>
                    Data do Agendamento:<?= htmlspecialchars(date('d/m/Y H:i', strtotime($agendamento_para_editar['VIS_DATA_AGENDAMENTO']))) ?><br><br>
                    Posto de Vistoria: <?= htmlspecialchars($agendamento_para_editar['POSTO_NOME'] ?? '') ?> |
                    Situação: <?= htmlspecialchars($agendamento_para_editar['VIS_SITUACAO']) ?>
                </div>

                <div class="client-actions">
                    <a href="agendamento_vistoria.php" class="btn btn-danger">Voltar para Consulta</a>
                </div>
            </div>

            <?php else : ?>
                <div class="form-toolbar">
                    <?php if (temPermissao('criar_editar_vistoria')): ?>
                    <a href="agendamento_vistoria.php?action=new" class="btn">Novo</a>
                    <?php endif; ?>
                </div>
             <?php endif; ?>

            <div class="tabs">
                <div class="tab-buttons">
                    <button type="button" class="tab-button <?=$aba_ativa == 'consulta_agendamento' ? 'active' : '' ?>" data-tab="consulta_agendamento" <?php if ($aba_ativa == 'cadastro_agendamento'): ?> style="display: none;" <?php endif;?>>Consulta</button>
                    <button type="button" class="tab-button <?= $aba_ativa == 'cadastro_agendamento' ? 'active' : '' ?>" data-tab="cadastro_agendamento" <?= $aba_ativa != 'cadastro_agendamento' ? 'disabled' : '' ?>>Cadastro</button>
                </div>

                <div class="tab-content">
                    <div id="consulta_agendamento" class="tab-pane <?= $aba_ativa == 'consulta_agendamento' ? 'active' : '' ?>">
                        <div class="table-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>CÓDIGO</th> 
                                        <th>DATA/HORA</th> 
                                        <th>CLIENTE</th> 
                                        <th>PLACA</th> 
                                        <th>POSTO VISTORIA</th> 
                                        <th>SITUAÇÃO</th>
                                        <th style="text-align: center;">AÇÃO</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($agendamentos)): ?>
                                        <?php foreach ($agendamentos as $ag): ?>
                                        <tr class="clickable-row" data-id="<?= $ag['VIS_CODIGO'] ?>">
                                            <td><?= htmlspecialchars($ag['VIS_CODIGO'] ?? '') ?></td>
                                            <td><?= $ag['VIS_DATA_AGENDAMENTO'] ? date('d/m/Y H:i', strtotime($ag['VIS_DATA_AGENDAMENTO'])) : 'N/A' ?></td>
                                            <td><?= htmlspecialchars($ag['CLIENTE_NOME'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($ag['VEICULO_PLACA'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($ag['POSTO_NOME'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($ag['VIS_SITUACAO'] ?? '') ?></td>
                                            <td style="text-align: center; white-space: nowrap; display: flex; justify-content: center; gap: 15px;">
                                                <?php if (temPermissao('criar_editar_vistoria')) : ?>
                                                    <a href="agendamento_vistoria.php?action=edit&id=<?= $ag['VIS_CODIGO'] ?>" class="btn btn-primary">Editar</a>
                                                <?php endif; ?>
                                                <?php if (temPermissao('excluir_vistoria')) : ?>
                                                    <a href="agendamento_vistoria.php?action=delete&id=<?= $ag['VIS_CODIGO'] ?>" class="btn btn-danger btn-excluir-linha">Excluir</a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="7" style="text-align: center;">Nenhum registro encontrado.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                          <div style="display: flex; justify-content: space-between; margin-top: 30px;;" class="pagination">
                            <span class="paginacao">Página <?= $page ?> de <?= $total_pages ?></span>         
                            <div class="paginacao">
                                <a href="<?= $link_primeiro ?>" class="btn">Primeiro</a>
                                <a href="<?= $link_anterior ?>" class="btn <?= $page <= 1 ? 'disabled' : '' ?>">Anterior</a>
                                <a href="<?= $link_proximo ?>" class="btn <?= $page >= $total_pages ? 'disabled' : '' ?>">Próximo</a>
                                <a href="<?= $link_ultimo ?>" class="btn">Último</a>
                            </div>
                        </div>
                    </div>

                    <div id="cadastro_agendamento" class="tab-pane <?=$aba_ativa == 'cadastro_agendamento' ? 'active' : '' ?>">
                        <form id="formAgendamentoVistoria" method="POST" action="agendamento_vistoria.php">
                            <input type="hidden" name="VIS_CODIGO" value="<?= htmlspecialchars($agendamento_para_editar['VIS_CODIGO'] ?? '') ?>">
                            
                            <input type="hidden" id="servicos_cod_cliente_hidden" name="ORS_CPF_CNPJ" value="<?= htmlspecialchars($cliente_cpf_cnpj ?? '') ?>">
                            <input type="hidden" id="veiculo_id_hidden" name="ORS_VEI_CODIGO" value="<?= htmlspecialchars($agendamento_para_editar['VIS_VEI_CODIGO'] ?? '') ?>">

                            <fieldset <?= ($action === 'view') ? 'disabled' : '' ?> style="border: none;">
                                <fieldset class="form-section">
                                    <legend>Dados Gerais</legend>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Código</label>
                                            <input type="text" value="<?= htmlspecialchars($agendamento_para_editar['VIS_CODIGO'] ?? 'NOVO') ?>" readonly>
                                        </div>
                                    </div>
                                    
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Cliente</label>
                                            <div class="input-with-button">
                                                <input type="text" id="servicos_cliente_display" name="cliente_nome_display" placeholder="Busque pelo cliente..." value="<?= htmlspecialchars($cliente_nome ?? '') ?>" readonly>
                                                <button type="button" id="servicos_btnAbrirModalCliente" class="btn-lookup">...</button>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label>CPF/CNPJ</label>
                                            <input type="text" id="servicos_cpf_cnpj" value="<?= htmlspecialchars($agendamento_cpf_cnpj ?? '' )?>" readonly>
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Veículo</label>
                                            <div class="input-with-button">
                                                <input type="text" id="veiculo_display" readonly value="<?= htmlspecialchars($veiculo_modelo ?? '') ?>">
                                                <button type="button" id="btnAbrirModalVeiculoCliente" class="btn-lookup" <?= empty($cliente_cpf_cnpj) ? 'disabled' : '' ?>>...</button>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label>Placa</label>
                                            <input type="text" id="vei_placa" value="<?= htmlspecialchars($veiculo_placa ?? '') ?>" readonly>
                                        </div>
                                        <div class="form-group">
                                            <label>Renavam</label>
                                            <input type="text" id="vei_renavam" value="<?= htmlspecialchars($veiculo_renavam ?? '') ?>" readonly>
                                        </div>
                                        <div class="form-group">
                                            <label>Chassi</label>
                                            <input type="text" id="vei_chassi" value="<?= htmlspecialchars($veiculo_chassi ?? '') ?>" readonly>
                                        </div>
                                    </div>
                                    </fieldset>
                                
                                <fieldset class="form-section">
                                    <legend>Detalhes da Vistoria</legend>
                                    <div class="form-row">
                                        <div class="form-group" style="flex-grow: 1.5;">
                                            <label for="VIS_PVI_CODIGO">Posto de Vistoria *</label>
                                            <select id="VIS_PVI_CODIGO" name="VIS_PVI_CODIGO" required>
                                                <option value="">-- Selecione um Posto --</option>
                                                <?php foreach ($lista_postos as $posto): ?>
                                                    <option value="<?= $posto['POV_CODIGO'] ?>" <?= ($agendamento_para_editar['VIS_PVI_CODIGO'] ?? '') == $posto['POV_CODIGO'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($posto['POV_NOME']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="VIS_DATA_AGENDAMENTO">Data e Hora *</label>
                                            <?php 
                                            $data_agendamento = $agendamento_para_editar['VIS_DATA_AGENDAMENTO'] ?? '';
                                            if ($data_agendamento) {
                                                $data_agendamento = date('Y-m-d\TH:i', strtotime($data_agendamento));
                                            }
                                            ?>
                                            <input type="datetime-local" id="VIS_DATA_AGENDAMENTO" name="VIS_DATA_AGENDAMENTO" value="<?= $data_agendamento ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="VIS_SITUACAO">Situação *</label>
                                            <select id="VIS_SITUACAO" name="VIS_SITUACAO" required>
                                                <option value="Agendado" <?= ($agendamento_para_editar['VIS_SITUACAO'] ?? 'Agendado') == 'Agendado' ? 'selected' : '' ?>>Agendado</option>
                                                <option value="Realizado" <?= ($agendamento_para_editar['VIS_SITUACAO'] ?? '') == 'Realizado' ? 'selected' : '' ?>>Realizado</option>
                                                <option value="Cancelado" <?= ($agendamento_para_editar['VIS_SITUACAO'] ?? '') == 'Cancelado' ? 'selected' : '' ?>>Cancelado</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group" style="flex-basis: 100%;">
                                            <label for="VIS_OBSERVACOES">Observações</label>
                                            <textarea id="VIS_OBSERVACOES" name="VIS_OBSERVACOES" rows="3"><?= htmlspecialchars($agendamento_para_editar['VIS_OBSERVACOES'] ?? '') ?></textarea>
                                        </div>
                                    </div>
                                </fieldset>
                            </fieldset>

                            <div class="form-footer">
                                <?php if ($action !== 'view'): ?>
                                    <button type="submit" class="btn btn-primary">Salvar Agendamento</button> 
                                <?php endif; ?>
                                <a href="agendamento_vistoria.php" class="btn btn-danger">Cancelar</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <div id="modalCliente" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2>Buscar Cliente</h2>
            <input type="text" id="buscaClienteInput" placeholder="Digite para buscar pelo nome ou código...">
            <div id="listaResultadosCliente" class="results-list"></div>
        </div>
    </div>
    <div id="modalVeiculoCliente" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2>Buscar Veículo do Cliente</h2>
            <input type="text" id="buscaVeiculoClienteInput" placeholder="Busque pela placa ou modelo...">
            <div id="listaResultadosVeiculoCliente" class="results-list"></div>
        </div>
    </div>

    <div id="customConfirmModal" class="modal-confirm" style="display: none;">
        <div class="modal-confirm-content">
            <p id="confirmMessage">Tem certeza que deseja excluir este registro? Esta ação não pode ser desfeita.</p>
            <div class="modal-confirm-buttons">
                <button id="cancelBtn" class="btn btn-secondary">Cancelar</button>
                <button id="confirmBtn" class="btn btn-danger">Excluir</button>
            </div>
        </div>
    </div>

    <script src="../js/script_menu.js"></script>
    <script src="../js/script_modal.js"></script>
    <script src="../js/script_mascara.js"></script>
    <script src="../js/script_main.js"></script>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        
        // Configura o modal de Clientes
        setupModalSearch({
            modalId: 'modalCliente',
            btnId: 'servicos_btnAbrirModalCliente', 
            inputId: 'buscaClienteInput',
            resultsId: 'listaResultadosCliente',
            url: 'modais/buscar_cliente.php', 
            onSelect: function(item) {
                // Preenche os campos de cliente
                document.getElementById('servicos_cliente_display').value = item.nome;
                document.getElementById('servicos_cod_cliente_hidden').value = item.cpf_cnpj; 
                document.getElementById('agendamento_cpf_cnpj').value = item.cpf_cnpj;

                // Limpa os campos de veículo
                document.getElementById('veiculo_display').value = '';
                document.getElementById('veiculo_id_hidden').value = '';
                document.getElementById('vei_placa').value = '';
                document.getElementById('vei_renavam').value = '';
                document.getElementById('vei_chassi').value = '';

                // Habilita o botão de buscar veículo
                document.getElementById('btnAbrirModalVeiculoCliente').disabled = false;
            }
        });

        // Configura o modal de Veículos 
        setupModalSearch({
            modalId: 'modalVeiculoCliente',
            btnId: 'btnAbrirModalVeiculoCliente',
            inputId: 'buscaVeiculoClienteInput',
            resultsId: 'listaResultadosVeiculoCliente',
            buildUrl: function(termo) {
                const clienteCpf = document.getElementById('servicos_cod_cliente_hidden').value;
                if (!clienteCpf) {
                    alert('Primeiro, selecione um cliente.');
                    return null; 
                }
                return `modais/buscar_veiculos_por_cliente.php?q=${encodeURIComponent(termo)}&cliente_cpf=${encodeURIComponent(clienteCpf)}`;
            },
            onSelect: function(item) {
                // Preenche todos os campos de veículo
                document.getElementById('veiculo_display').value = item.MODELO || item.modelo;
                document.getElementById('veiculo_id_hidden').value = item.CODIGO || item.codigo || item.id;
                document.getElementById('vei_placa').value = item.PLACA_UF || item.placa || '';
                document.getElementById('vei_renavam').value = item.RENAVAM || item.renavam || '';
                document.getElementById('vei_chassi').value = item.CHASSI || item.chassi || '';
            }
        });

    });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('customConfirmModal');
            const cancelBtn = document.getElementById('cancelBtn');
            const confirmBtn = document.getElementById('confirmBtn');
            let deleteUrl = ''; 

            document.querySelectorAll('.btn-excluir-linha').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault(); 
                    deleteUrl = this.href; 
                    modal.style.display = 'block'; 
                });
            });

            if (cancelBtn) {
                cancelBtn.addEventListener('click', function() {
                    modal.style.display = 'none'; 
                    deleteUrl = ''; 
                });
            }

            if (confirmBtn) {
                confirmBtn.addEventListener('click', function() {
                    if (deleteUrl) {
                        window.location.href = deleteUrl; 
                    }
                });
            }

            window.addEventListener('click', function(e) {
                if (e.target == modal) {
                    modal.style.display = 'none';
                    deleteUrl = '';
                }
            });
        });
    </script>
</body>
</html>