<?php 
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../index.php");
    exit;
}

$usuario_nome = htmlspecialchars($_SESSION['username'] ?? 'Usuário');
$usuario_login = htmlspecialchars($_SESSION['login'] ?? '');


require_once '../verificar_permissao.php';
protegerPagina('acessar_config');

require_once '../config.php'; 

// INICIALIZAÇÃO E PAGINAÇÃO
$aba_ativa = 'consulta_intencao_venda';
$intencao_venda__para_editar = [];
$intencao_vendas = [];
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$message = '';
$message_type = '';
if (!empty($_GET['status'])) {
    switch ($_GET['status']) {
        case 'success':
            $message = '<strong>Sucesso!</strong> Intenção de Venda salva com sucesso.';
            $message_type = 'success';
            break;
        case 'deleted':
            $message = 'Intenção de Venda excluído com sucesso.';
            $message_type = 'success';
            break;
        case 'error':
            $errorMessage = $_GET['msg'] ?? 'Ocorreu um problema não especificado.';
            $message = '<strong>Erro!</strong> ' . htmlspecialchars(urldecode($errorMessage));
            $message_type = 'error';
            break;
    }
}

// LÓGICA DE AÇÕES
$action = $_GET['action'] ?? null;
$id = $_GET['id'] ?? null;

if ($action === 'new') {
    $aba_ativa = 'cadastro_intencao_venda';
}

if (($action === 'edit' || $action === 'view') && $id) {
    $aba_ativa = 'cadastro_intencao_venda';
    $stmt = $conn->prepare("SELECT * FROM FRM_INTENCAO_VENDA WHERE FIV_CODIGO = ?");
    $stmt->execute([$id]);
    $intencao_venda_para_editar = $stmt->fetch(PDO::FETCH_ASSOC);
    $veiculo_nome="";

    if (!empty($intencao_venda_para_editar['FIV_VEI_CODIGO'])) { 
        $stmt_veiculo = $conn->prepare("SELECT CODIGO, MODELO, PLACA_UF FROM VEICULO WHERE CODIGO = ?");
        $stmt_veiculo->execute([$intencao_venda_para_editar['FIV_VEI_CODIGO']]); 
        $veiculo_encontrado = $stmt_veiculo->fetch(PDO::FETCH_ASSOC);
        if ($veiculo_encontrado) {
            $veiculo_nome = $veiculo_encontrado['MODELO'] . ' - ' . $veiculo_encontrado['PLACA_UF'];
        }
    }

}

if ($action === 'delete' && $id) {
    try {
        $stmt = $conn->prepare("DELETE FROM FRM_INTENCAO_VENDA WHERE FIV_CODIGO = ?");
        $stmt->execute([$id]);
        header("Location: cadastro_intencao_venda.php?status=deleted");
        exit;
    } catch (PDOException $e) {
        header("Location: cadastro_intencao_venda.php?status=error&msg=" . urlencode($e->getMessage()));
        exit;
    }
}

// LÓGICA DE SALVAR 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $CODIGO = $_POST['FIV_CODIGO'] ?: null;   
    $dados = [
        'FIV_DATA_CADASTRO' => $_POST['FIV_DATA_CADASTRO'] ?? null,
        'FIV_VEND_CLI_CODIGO' => $_POST['FIV_VEND_CLI_CODIGO'] ?? null,
        'FIV_VEND_CPF_CNPJ' => $_POST['FIV_VEND_CPF_CNPJ'] ?? null,
        'FIV_VEND_NOME' => $_POST['FIV_VEND_NOME'] ?? null,
        'FIV_VEND_NOME_SOCIAL' => $_POST['FIV_VEND_NOME_SOCIAL'] ?? null,
        'FIV_VEND_IDENTIDADE' => $_POST['FIV_VEND_IDENTIDADE'] ?? null,
        'FIV_VEND_IDENTIDADE_EMISSOR' => $_POST['FIV_VEND_IDENTIDADE_EMISSOR']?? null,
        'FIV_VEND_IDENTIDADE_UF' => $_POST['FIV_VEND_IDENTIDADE_UF'] ?? null,
        'FIV_VEND_CNH' => $_POST['FIV_VEND_CNH'] ?? null,
        'FIV_VEND_TELEFONE' => $_POST['FIV_VEND_TELEFONE'] ?? null,
        'FIV_VEND_CELULAR' => $_POST['FIV_VEND_CELULAR'] ?? null,
        'FIV_VEND_EMAIL' => $_POST['FIV_VEND_EMAIL'] ?? null,
        'FIV_VEND_ESTADO_CIVIL' => $_POST['FIV_VEND_ESTADO_CIVIL'] ?? null,
        'FIV_VEND_ENDERECO' => $_POST['FIV_VEND_ENDERECO'] ?? null,
        'FIV_VEND_NUMERO' => $_POST['FIV_VEND_NUMERO']?? null,
        'FIV_VEND_CEP' => $_POST['FIV_VEND_CEP'] ?? null,
        'FIV_VEND_BAIRRO' => $_POST['FIV_VEND_BAIRRO'] ?? null,
        'FIV_VEND_CIDADE' => $_POST['FIV_VEND_CIDADE'] ?? null,
        'FIV_VEND_ESTADO' => $_POST['FIV_VEND_ESTADO'] ?? null,
        'FIV_COMP_CLI_CODIGO' => $_POST['FIV_COMP_CLI_CODIGO'] ?? null,
        'FIV_COMP_CPF_CNPJ' => $_POST['FIV_COMP_CPF_CNPJ'] ?? null,
        'FIV_COMP_NOME' => $_POST['FIV_COMP_NOME'] ?? null,
        'FIV_COMP_NOME_SOCIAL' => $_POST['FIV_COMP_NOME_SOCIAL'] ?? null,
        'FIV_COMP_IDENTIDADE' => $_POST['FIV_COMP_IDENTIDADE'] ?? null,
        'FIV_COMP_TELEFONE' => $_POST['FIV_COMP_TELEFONE'] ?? null,
        'FIV_COMP_CELULAR' => $_POST['FIV_COMP_CELULAR'] ?? null,
        'FIV_COMP_EMAIL' => $_POST['FIV_COMP_EMAIL'] ?? null,
        'FIV_COMP_ENDERECO' => $_POST['FIV_COMP_ENDERECO'] ?? null,
        'FIV_COMP_ENDERECO_NUMERO' => $_POST['FIV_COMP_ENDERECO_NUMERO'] ?? null,
        'FIV_COMP_ENDERECO_CEP' => $_POST['FIV_COMP_ENDERECO_CEP'] ?? null,
        'FIV_COMP_ESTADO' => $_POST['FIV_COMP_ESTADO'] ?? null,
        'FIV_COMP_CIDADE' => $_POST['FIV_COMP_CIDADE'] ?? null,
        'FIV_COMP_BAIRRO' => $_POST['FIV_COMP_BAIRRO'] ?? null,
        'FIV_VEI_CODIGO' => $_POST['FIV_VEI_CODIGO'] ?? null,
        'FIV_VEI_PLACA' => $_POST['FIV_VEI_PLACA'] ?? null,
        'FIV_VEI_RENAVAM' => $_POST['FIV_VEI_RENAVAM'] ?? null,
        'FIV_VEI_VALOR_VENDA' => $_POST['FIV_VEI_VALOR_VENDA'] ?? null,
        'FIV_VEI_MARCA_MODELO' => $_POST['FIV_VEI_MARCA_MODELO'] ?? null,
        'FIV_VEI_ANO' => $_POST['FIV_VEI_ANO'] ?? null,
        'FIV_VEI_CHASSI' => $_POST['FIV_VEI_CHASSI'] ?? null,
        'FIV_LOCAL' => $_POST['FIV_LOCAL'] ?? null,
        'FIV_DATA_EXTENSO' => $_POST['FIV_DATA_EXTENSO'] ?? null,
        'FIV_NUMERO_ATPVE' => $_POST['FIV_NUMERO_ATPVE'] ?? null,
        'FIV_CODIGO_SEGURANCA' => $_POST['FIV_CODIGO_SEGURANCA'] ?? null,
        'FIV_DESP_CODIGO' => $_POST['FIV_DESP_CODIGO'] ?? null,
        'FIV_DESP_NOME' => $_POST['FIV_DESP_NOME'] ?? null,
        'FIV_DESP_ENDERECO' => $_POST['FIV_DESP_ENDERECO'] ?? null,
        'FIV_DESP_NUMERO' => $_POST['FIV_DESP_NUMERO'] ?? null,
        'FIV_DESP_CEP' => $_POST['FIV_DESP_CEP'] ?? null,
        'FIV_DESP_BAIRRO' => $_POST['FIV_DESP_BAIRRO'] ?? null,
        'FIV_DESP_CIDADE' => $_POST['FIV_DESP_CIDADE'] ?? null,
        'FIV_DESP_ESTADO' => $_POST['FIV_DESP_ESTADO'] ?? null
    ];

     foreach ($dados as $chave => $valor) {
        if ($valor === '') {
            $dados[$chave] = null;
        }
    }

    try {
        if (empty($CODIGO)) {
            $colunas = implode(', ', array_keys($dados));
            $placeholders = implode(', ', array_fill(0, count($dados), '?'));
            $sql = "INSERT INTO FRM_INTENCAO_VENDA($colunas) VALUES ($placeholders)";
            $stmt = $conn->prepare($sql);
            $stmt->execute(array_values($dados));
        } else {
            $set_sql = [];
            foreach (array_keys($dados) as $coluna) {
                $set_sql[] = "$coluna = ?";
            }
            $sql = "UPDATE FRM_INTENCAO_VENDA SET " . implode(', ', $set_sql) . " WHERE FIV_CODIGO = ?";
            $valores = array_values($dados);
            $valores[] = $CODIGO;
            $stmt = $conn->prepare($sql);
            $stmt->execute($valores);
        }
        header("Location: cadastro_intencao_venda.php?status=success");
        exit;
    } catch (PDOException $e) {
        die("Erro ao salvar intenção de venda: " . $e->getMessage());
    }
}


// --- LÓGICA DE BUSCA E CONSULTA ---
$sql_base = "SELECT * FROM FRM_INTENCAO_VENDA";
$sql_count_base = "SELECT COUNT(*) FROM FRM_INTENCAO_VENDA";
$params = [];
$where_clauses = [];

if (!empty($_GET['valor_pesquisa'])) {
    $campo = $_GET['campo_pesquisa'];
    $valor = '%' . $_GET['valor_pesquisa'] . '%';

    if ($campo == 'codigo') $where_clauses[] = "FIV_CODIGO LIKE ?";
    elseif ($campo == 'comprador') $where_clauses[] = "FIV_COMP_NOME LIKE ?";
    elseif ($campo == 'vendedor') $where_clauses[] = "FIV_VEND_NOME LIKE ?";
    elseif ($campo == 'placa') $where_clauses[] = "FIV_VEI_PLACA LIKE ?";
    
    if (!empty($where_clauses)) {
        $params[] = $valor;
    }
}

if (!empty($where_clauses)) {
    $sql_base .= " WHERE " . implode(' AND ', $where_clauses);
    $sql_count_base .= " WHERE " . implode(' AND ', $where_clauses);
}

$total_stmt = $conn->prepare($sql_count_base);
$total_stmt->execute($params);
$total_records = $total_stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

$sql_final = $sql_base ." ORDER BY FIV_CODIGO LIMIT $limit OFFSET $offset";
$stmt = $conn->prepare($sql_final);
$stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);

for ($i = 0; $i < count($params); $i++) {
    $stmt->bindValue($i + 1, $params[$i]);
}
$stmt->execute($params);
$intencao_vendas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Lógica  de navegação
$query_string = http_build_query(array_merge($_GET, ['page' => '']));
$link_primeiro = "cadastro_intencao_venda.php?{$query_string}1";
$link_anterior = ($page > 1) ? "cadastro_intencao_venda.php?{$query_string}" . ($page - 1) : "#";
$link_proximo = ($page < $total_pages) ? "cadastro_intencao_venda.php?{$query_string}" . ($page + 1) : "#";
$link_ultimo = "cadastro_intencao_venda.php?{$query_string}" . $total_pages;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Intenção de Venda (ATPV-e) - Lindomar Despachante</title>
    <link rel="stylesheet" href="../css/style_sistema.css">
    <link rel="stylesheet" href="../css/print_form.css" media="print">
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
            <h1>Intenção de Venda (ATPV-e)</h1>

             <?php if (!empty($message)): ?>
                <div class="alert <?= $message_type ?>">
                    <?= $message ?>
                </div>
            <?php endif; ?>

             <?php 
                if (($action === 'edit') && !empty($intencao_venda_para_editar)) :
            ?>
                <div class= "form-tollbar client-header" style="display: flex; justify-content: space-between;">
                    <div class="client-info">
                        Comprador: <strong><?= htmlspecialchars($intencao_venda_para_editar['FIV_COMP_NOME'])?></strong> <br><br>
                        Veículo: <?= htmlspecialchars($intencao_venda_para_editar['FIV_VEI_PLACA']) ?><br><br>
                        Valor: <?= htmlspecialchars($intencao_venda_para_editar['FIV_VEI_VALOR_VENDA']) ?>
                    </div>

                    <div class="client-actions">
                        <a href="cadastro_intencao_venda.php" class="btn btn-danger">Voltar para Consulta</a>
                    </div>
                </div>

            <?php else :?>
                <div class="form-toolbar">
                    <?php if (temPermissao('acessar_config')): ?>
                        <a href="cadastro_intencao_venda.php?action=new" class="btn">Novo</a>
                    <?php endif; ?>        
                </div>
            <?php endif; ?>

            <div class="tabs">

                <div class="tab-buttons">
                    <button type="button" class="tab-button <?=$aba_ativa == 'consulta_intencao_venda"' ? 'active' : '' ?>" data-tab="consulta_intencao_venda"" <?php if ($aba_ativa == 'cadastro_intencao_venda"'): ?> style="display: none;" <?php endif;?>>Consulta</button>
                    <button type="button" class="tab-button <?=$aba_ativa == 'cadastro_intencao_venda' ? 'active' : '' ?>" data-tab="cadastro_intencao_venda" <?=$aba_ativa != 'cadastro_intencao_venda' ? 'disabled' : ''?>>Cadastro</button>
                </div>

                <div class="tab-content">
                    <!-- Consulta -->
                    <div id="consulta_intencao" class="tab-pane <?=$aba_ativa == 'consulta_intencao_venda' ? 'active' : '' ?>">
                        <form method="GET" action="cadastro_intencao_venda.php">
                            <fieldset class="search-box">
                                <legend>Opções de pesquisa</legend>
                                <div class="form-group">
                                    <label for="campo_pesquisa">Campo de Pesquisa:</label>
                                    <select id="campo_pesquisa" name="campo_pesquisa">
                                        <option value="codigo" <?= ($_GET['campo_pesquisa'] ?? '') === 'codigo' ? 'selected' : '' ?>>CÓDIGO</option>
                                        <option value="comprador" <?= ($_GET['campo_pesquisa'] ?? '') === 'comprador' ? 'selected' : '' ?>>COMPRADOR</option>
                                        <option value="vendedor" <?= ($_GET['campo_pesquisa'] ?? '') === 'vendedor' ? 'selected' : '' ?>>VENDEDOR</option>
                                        <option value="cpf_cnpj" <?= ($_GET['campo_pesquisa'] ?? '') === 'cpf_cnpj' ? 'selected' : '' ?>>CPF_CNPJ</option>
                                        <option value="placa" <?= ($_GET['campo_pesquisa'] ?? '') === 'placa' ? 'selected' : '' ?>>PLACA</option>
                                    </select>
                                </div>

                                <div class="form-group" style="flex-grow: 2;">
                                    <label for="parametro_pesquisa">Parâmetro da Pesquisa:</label>
                                    <input type="text" id="parametro_pesquisa" name="valor_pesquisa" value="<?= htmlspecialchars($_GET['valor_pesquisa'] ?? '') ?>">
                                </div>

                                <button type="submit" class="btn btn-primary">Pesquisar</button>

                            </fieldset>
                        </form>

                        <div class="table-container">
                            <p class="table-note">Selecione para Consultar/Editar</p>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>CÓDIGO</th>
                                        <th>DATA CADASTRO</th>
                                        <th>PLACA</th>
                                        <th>VENDEDOR</th>
                                        <th>COMPRADOR</th>
                                        <th>VALOR(R$)</th>
                                        <th style="text-align: center;">AÇÃO</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($intencao_vendas)) : ?>
                                        <?php foreach ($intencao_vendas as $intencao_venda): ?>
                                        <tr class="clickable-row" data-id="<?= $intencao_venda['FIV_CODIGO'] ?>">
                                            <td><?=htmlspecialchars($intencao_venda['FIV_CODIGO'] ?? '')?></td>
                                            <td>
                                                <?php
                                                    if(!empty($intencao_venda['FIV_DATA_CADASTRO'])){
                                                        echo htmlspecialchars(date('d/m/y', strtotime($intencao_venda['FIV_DATA_CADASTRO'])));
                                                    }
                                                ?>
                                            </td>
                                            <td><?=htmlspecialchars($intencao_venda['FIV_VEI_PLACA'] ?? '')?></td>
                                            <td><?=htmlspecialchars($intencao_venda['FIV_VEND_NOME'] ?? '')?></td>
                                            <td><?=htmlspecialchars($intencao_venda['FIV_COMP_NOME'] ?? '')?></td>
                                            <td><?=htmlspecialchars($intencao_venda['FIV_VEI_VALOR_VENDA'] ?? '')?></td>
                                            <td style="text-align: center; white-space: nowrap; display: flex; justify-content: center; gap: 15px;">
                                                <?php if (temPermissao('gerenciar_config')) : ?>
                                                    <a href="cadastro_intencao_venda.php?action=edit&id=<?= $intencao_venda['FIV_CODIGO'] ?>" class="btn btn-primary">Editar</a>
                                                <?php endif; ?>
                                                <?php if (temPermissao('gerenciar_config')) : ?>
                                                    <a href="cadastro_intencao_venda.php?action=delete&id=<?= $intencao_venda['FIV_CODIGO'] ?>" class="btn btn-danger btn-excluir-linha">Excluir</a>
                                                <?php endif; ?>
                                            </td>      
                                        </tr>
                                        <?php endforeach ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6">Nenhum registro encontrado.</td>
                                        </tr>
                                    <?php endif; ?>
                                    <?php ?>
                                </tbody>
                            </table>
                            <div class="pagination">
                                <span class="paginacao">Página <?= $page ?> de <?= $total_pages ?></span>
                            </div>
                        </div>
                    </div>

                    <!--Cadastro-->
                    <div id="cadastro_intencao" class="tab-pane <?=$aba_ativa == 'cadastro_intencao_venda' ? 'active' : '' ?>">
                        <form id="formIntencaoVenda" method="POST" action="cadastro_intencao_venda.php">
                        <input type="hidden" name="FIV_CODIGO" value="<?= htmlspecialchars($intencao_venda_para_editar['FIV_CODIGO'] ?? '') ?>">
                            <fieldset <?= ($action === 'view') ? 'disabled' : '' ?> style="border: none;">
                                <fieldset class="form-section">
                                    <legend>Dados Gerais da Venda</legend>
                                    <div class="form-row">

                                        <div class="form-group" style="max-width: 70px;">
                                            <label>Código</label>
                                            <input type="text" value="<?= htmlspecialchars($intencao_venda_para_editar['FIV_CODIGO'] ?? 'NOVO') ?>" readonly> 
                                        </div>

                                        <div class="form-group">
                                            <label for="FIV_DATA_CADASTRO">Data</label>
                                            <input type="text" id="FIV_DATA_CADASTRO" name="FIV_DATA_CADASTRO" value="<?= htmlspecialchars($intencao_venda_para_editar['FIV_DATA_CADASTRO'] ?? date('d/m/y')) ?>">
                                        </div>

                                        <div class="form-group" style="flex-grow: 2;">
                                            <label for="FIV_LOCAL">Local</label>
                                            <input type="text" id="FIV_LOCAL" name="FIV_LOCAL" value="<?= htmlspecialchars($intencao_venda_para_editar ['FIV_LOCAL'] ?? '') ?>" required>
                                        </div>

                                    </div>

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="FIV_VEI_VALOR_VENDA">Valor da Venda (R$)</label>
                                            <input type="number" step="0.01" id="FIV_VEI_VALOR_VENDA" name="FIV_VEI_VALOR_VENDA" value="<?= htmlspecialchars($intencao_venda_para_editar ['FIV_VEI_VALOR_VENDA'] ?? '') ?>" required>
                                        </div>

                                        <div class="form-group">
                                            <label for="FIV_RECONHECIMENTO_FIRMA">Reconhecer Firma?</label>
                                            <select id="FIV_RECONHECIMENTO_FIRMA" name="FIV_RECONHECIMENTO_FIRMA">
                                                <option value="SIM"<?=($intencao_venda_para_editar['FIV_RECONHECIMENTO_FIRMA']?? 'SIM') == 'SIM' ? 'selected' : '' ?>>Sim</option>
                                                <option value="NÃO"<?=($intencao_venda_para_editar['FIV_RECONHECIMENTO_FIRMA']?? 'NÃO') == 'NÃO' ? 'selected' : '' ?>>Não</option>
                                            </select>
                                        </div>
                                    </div>
                                </fieldset>
                            
                                <fieldset class="form-section">
                                    <legend>Dados do Veículo</legend>
                                    <div class="form-row">

                                        <div class="form-group" style="flex-grow: 2;">
                                            <label>Buscar Veículo</label>
                                            <div class="input-with-button">
                                                <input type="text" id="veiculo_display" value="<?= htmlspecialchars($veiculo_nome ?? '') ?>" readonly>
                                                <input type="hidden" id="veiculo_hidden" name="FIV_VEI_CODIGO">
                                                <button type="button" id="btnAbrirModalVeiculo" class="btn-lookup">...</button>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label for="FIV_VEI_PLACA">Placa</label>
                                            <input type="text" id="placa" name="FIV_VEI_PLACA" value="<?= htmlspecialchars($intencao_venda_para_editar['FIV_VEI_PLACA'] ?? '') ?>" readonly>
                                        </div>

                                        <div class="form-group">
                                            <label for="FIV_VEI_RENAVAM">Renavam</label>
                                            <input type="text" id="renavam" name="FIV_VEI_RENAVAM" value="<?= htmlspecialchars($intencao_venda_para_editar ['FIV_VEI_RENAVAM'] ?? '') ?>" required>
                                        </div>

                                        <div class="form-group" style="flex-grow: 1.5;">
                                            <label for="FIV_VEI_CHASSI">Chassi</label>
                                            <input type="text" id="chassi" name="FIV_VEI_CHASSI"value="<?= htmlspecialchars($intencao_venda_para_editar ['FIV_VEI_CHASSI'] ?? '') ?>" required>
                                        </div>
                                    </div>
                                </fieldset>
                            
                                <fieldset class="form-section">
                                    <legend>Dados do Vendedor</legend>
                                    <div class="form-row">

                                        <div class="form-group" style="flex-grow: 2;">
                                            <label for="FIV_VEND_NOME">Nome</label>
                                            <input type="text" id="FIV_VEND_NOME" name="FIV_VEND_NOME" value="<?= htmlspecialchars($intencao_venda_para_editar ['FIV_VEND_NOME'] ?? '') ?>" required>
                                        </div>

                                        <div class="form-group">
                                            <label for="FIV_VEND_CPF_CNPJ">CPF/CNPJ</label>
                                            <input type="text" id="FIV_VEND_CPF_CNPJ" name="FIV_VEND_CPF_CNPJ" value="<?= htmlspecialchars($intencao_venda_para_editar ['FIV_VEND_CPF_CNPJ'] ?? '') ?>" required>
                                        </div>

                                        <div class="form-group">
                                            <label for="FIV_VEND_CPF_CNPJ">CNH</label>
                                            <input type="text" id="FIV_VEND_CNH" name="FIV_VEND_CNH" value="<?= htmlspecialchars($intencao_venda_para_editar ['FIV_VEND_CNH'] ?? '') ?>" required>
                                        </div>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="FIV_VEND_CELULAR">Celular</label>
                                            <input type="text" id="FIV_VEND_CELULAR" name="FIV_VEND_CELULAR" placeholder="(00) 00000-0000" value="<?= htmlspecialchars($intencao_venda_para_editar ['FIV_VEND_CELULAR'] ?? '') ?>">
                                        </div>

                                        <div class="form-group">
                                            <label for="FIV_VEND_TELEFONE">Telefone Residencial</label>
                                            <input type="text" id="FIV_VEND_TELEFONE" name="FIV_VEND_TELEFONE" placeholder="(00) 0000-0000" value="<?= htmlspecialchars($intencao_venda_para_editar ['FIV_VEND_TELEFONE'] ?? '') ?>">
                                        </div>
                                    </div>
    
                                    <div class="form-row">
                                        <div class="form-group" style="flex-grow: 1;">
                                            <label for="FIV_VEND_CEP">CEP</label>
                                            <input type="text" id="FIV_VEND_CEP" name="FIV_VEND_CEP" value="<?= htmlspecialchars($intencao_venda_para_editar ['FIV_VEND_CEP'] ?? '') ?> ">
                                        </div>

                                        <div class="form-group" style="flex-grow: 3;">
                                            <label for="FIV_VEND_ENDERECO">Endereço</label>
                                            <input type="text" id="FIV_VEND_ENDERECO" name="FIV_VEND_ENDERECO" value="<?= htmlspecialchars($intencao_venda_para_editar ['FIV_VEND_ENDERECO'] ?? '') ?>">
                                        </div>

                                         <div class="form-group" style="flex-grow: 3;">
                                            <label for="FIV_VEND_BAIRRO">Bairro</label>
                                            <input type="text" id="FIV_VEND_BAIRRO" name="FIV_VEND_BAIRRO" value="<?= htmlspecialchars($intencao_venda_para_editar ['FIV_VEND_NUMERO'] ?? '') ?>">
                                        </div>

                                        <div class="form-group" style="max-width: 50px;">
                                            <label for="FIV_VEND_NUMERO">Número</label>
                                            <input type="text" id="FIV_VEND_NUMERO" name="FIV_VEND_NUMERO" value="<?= htmlspecialchars($intencao_venda_para_editar ['FIV_VEND_NUMERO'] ?? '') ?>">
                                        </div>

                                       
                                    </div>

                                    <div class="form-row">

                                        <div class="form-group" style="flex-grow: 1;">
                                            <label for="FIV_VEN_CIDADE">Cidade</label>
                                            <input type="text" id="FIV_VEND_CIDADE" name="FIV_VEND_CIDADE" value="<?= htmlspecialchars($intencao_venda_para_editar ['FIV_VEND_CIDADE'] ?? '') ?>">
                                        </div>

                                        <div class="form-group" style="max-width: 50px;">
                                            <label for="FIV_VEND_ESTADO">UF</label>
                                            <input type="text" id="FIV_VEND_ESTADO" name="FIV_VEND_ESTADO"  value="<?= htmlspecialchars($intencao_venda_para_editar ['FIV_VEND_ESTADO'] ?? '') ?>"> 
                                        </div>
                                    </div>
                                </fieldset>
                            
                                <fieldset class="form-section">
                                    <legend>Dados do Comprador</legend>
                                    <div class="form-row">
                                        <div class="form-group" style="flex-grow: 2;">
                                            <label>Buscar Comprador (Cliente)</label>
                                            <div class="input-with-button">
                                                <input type="text" id="cliente_display" name="FIV_COMP_NOME" placeholder="Busque pelo cliente..." value="<?= htmlspecialchars($intencao_venda_para_editar['FIV_COMP_NOME'] ?? '') ?>" readonly>
                                                <input type="hidden" id="cod_cliente_hidden" name="FIV_COMP_CLI_CODIGO" value="<?= htmlspecialchars($intencao_venda_para_editar['FIV_COMP_CLI_CODIGO'] ?? '') ?>">
                                                <button type="button" id="btnAbrirModalCliente" class="btn-lookup">...</button>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label for="FIV_COMP_CPF_CNPJ">CPF/CNPJ</label>
                                            <input type="text" id="cpf_cnpj" name="FIV_COMP_CPF_CNPJ" value="<?= htmlspecialchars($intencao_venda_para_editar ['FIV_COMP_CPF_CNPJ'] ?? '') ?>">
                                        </div>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="FIV_COMP_CELULAR">Celular</label>
                                            <input type="text" id="FIV_COMP_CELULAR" name="FIV_COMP_CELULAR" placeholder="(00) 00000-0000" value="<?= htmlspecialchars($intencao_venda_para_editar ['FIV_COMP_CELULAR'] ?? '') ?>">
                                        </div>

                                        <div class="form-group">
                                            <label for="FIV_COMP_TELEFONE">Telefone Residencial</label>
                                            <input type="text" id="FIV_COMP_TELEFONE" name="FIV_COMP_TELEFONE" placeholder="(00) 0000-0000" value="<?= htmlspecialchars($intencao_venda_para_editar ['FIV_COMP_TELEFONE'] ?? '') ?>">
                                        </div>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group" style="flex-grow: 3;">
                                            <label for="FIV_COMPRADOR_ENDERECO">Endereço</label>
                                            <input type="text" id="endereco" name="FIV_COMP_ENDERECO" value="<?= htmlspecialchars($intencao_venda_para_editar ['FIV_COMP_ENDERECO'] ?? '') ?>">
                                        </div>

                                         <div class="form-group" style="flex-grow: 3;">
                                            <label for="FIV_COMP_BAIRRO">Bairro</label>
                                            <input type="text" id="bairro" name="FIV_COMP_BAIRRO" value="<?= htmlspecialchars($intencao_venda_para_editar ['FIV_COMP_BAIRRO'] ?? '') ?>">
                                        </div>

                                        <div class="form-group" style="max-width: 50px;">
                                            <label for="FIV_COMP_ENDERECO_NUMERO">Número</label>
                                            <input type="text" id="numero" name="FIV_COMP_ENDERECO_NUMERO" value="<?= htmlspecialchars($intencao_venda_para_editar ['FIV_COMP_ENDERECO_NUMERO'] ?? '') ?>">
                                        </div>
                                    </div>

                                    <div class="form-row">

                                        <div class="form-group" style="flex-grow: 1;">
                                            <label for="FIV_COMP_CIDADE">Cidade</label>
                                            <input type="text" id="FIV_COMP_CIDADE" name="FIV_COMP_CIDADE" value="<?= htmlspecialchars($intencao_venda_para_editar ['FIV_COMP_CIDADE'] ?? '') ?>" > 
                                        </div>

                                        <div class="form-group">
                                            <label for="FIV_COMP_ESTADO">UF</label>
                                            <input type="text" id="estado" name="FIV_COMP_ESTADO" value="<?= htmlspecialchars($intencao_venda_para_editar ['FIV_COMP_ESTADO'] ?? '') ?>" > 
                                        </div>

                                        <div class="form-group" style="flex-grow: 1;">
                                            <label for="FIV_COMP_ENDERECO_CEP">CEP</label>
                                            <input type="text" id="cep" name="FIV_COMP_ENDERECO_CEP" value="<?= htmlspecialchars($intencao_venda_para_editar ['FIV_COMP_ENDERECO_CEP'] ?? '') ?>">
                                        </div>
                                    </div>
                                </fieldset>

                                  <div class="form-footer">
                                    <?php if ($action !== 'view'): ?>
                                        <button type="submit" class="btn btn-primary">Salvar</button> 
                                    <?php endif; ?>
                                    <!-- <button type="button" class="btn" onclick="imprimirIntencaoVenda()">Imprimir</button> -->
                                    <a href="cadastro_intencao_venda.php" class="btn btn-danger">Cancelar</a>
                                </div>
                                </fieldset>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!--modal veiculo-->
    <div id="modalVeiculo" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2>Buscar Veiculo</h2>
            <input type="text" id="buscaVeiculoInput" placeholder="Digite para buscar pelo nome ou código...">
            <div id="listaResultadosVeiculo" class="results-list"></div>
        </div>
    </div>

    <!--modal buscar Cliente-->
    <div id="modalCliente" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2>Buscar Cliente</h2>
            <input type="text" id="buscaClienteInput" placeholder="Digite para buscar pelo nome ou código...">
            <div id="listaResultadosCliente" class="results-list"></div>
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

    <script src="../js/script.js"></script>
    <script src="../js/script_menu.js"></script>
    <script src="../js/script_modal.js"></script>
    <script src="../js/script_mascara.js"></script>
    <script src="../js/script_main.js"></script>

    <!-- <script>
        function imprimirIntencaoVenda() {
            document.body.classList.add('print-mode');
            window.print();
        }
        window.onafterprint = function() {
            document.body.classList.remove('print-mode');
        };
    </script> -->
</body>
</html>