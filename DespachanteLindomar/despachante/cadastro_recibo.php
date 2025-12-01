<?php
session_start();
require_once '../config.php';
require_once '../verificar_permissao.php';

protegerPagina('acessar_config'); 
$usuario_nome = htmlspecialchars($_SESSION['username'] ?? 'Usuário');

// --- INICIALIZAÇÃO ---
$aba_ativa = 'consulta_recibo';
$recibo_para_editar = [];
$itens_do_recibo = [];
$recibos = []; 
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$message = ''; $message_type = '';

if (!empty($_GET['status'])) {
    switch ($_GET['status']) {
        case 'success':
            $message = '<strong>Sucesso!</strong> Recibo salvo com sucesso.'; $message_type = 'success'; break;
        case 'deleted':
            $message = 'Recibo excluído com sucesso.'; $message_type = 'success'; break;
        case 'error':
            $errorMessage = $_GET['msg'] ?? 'Ocorreu um problema.';
            $message = '<strong>Erro!</strong> ' . htmlspecialchars(urldecode($errorMessage)); $message_type = 'error'; break;
    }
}
$lista_despachantes = [];
$lista_servicos = [];
try {
    $stmt_desp = $conn->query("SELECT COD_DESP, NOME, CPF_CNPJ FROM DESPACHANTE ORDER BY NOME");
    $lista_despachantes = $stmt_desp->fetchAll(PDO::FETCH_ASSOC);

    $stmt_serv = $conn->query("SELECT TSE_CODIGO, TSE_DESCRICAO, TSE_VLUNITARIO FROM TIPO_SERVICO ORDER BY TSE_DESCRICAO");
    $lista_servicos = $stmt_serv->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
     $message = '<strong>Erro ao carregar dados!</strong> ' . $e->getMessage();
     $message_type = 'error';
}

// --- LÓGICA DE AÇÕES ---
$action = $_GET['action'] ?? null;
$id = $_GET['id'] ?? null;

if ($action === 'new') {
    $aba_ativa = 'cadastro_recibo';
}

if (($action === 'edit' || $action === 'view') && $id) {
    $aba_ativa = 'cadastro_recibo';
    $stmt = $conn->prepare("SELECT * FROM RECIBO_SIMPLES WHERE RES_CODIGO = ?");
    $stmt->execute([$id]);
    $recibo_para_editar = $stmt->fetch(PDO::FETCH_ASSOC);
    $despachante_cpf = '';
    $cliente_nome = ''; $cliente_cpf_cnpj = ''; $cliente_endereco = ''; $cliente_cidade = '';
    $veiculo_modelo = ''; $veiculo_placa = ''; $veiculo_renavam = '';

    if ($recibo_para_editar) {
        if (!empty($recibo_para_editar['RES_DESPACHANTE'])) {
            $stmt_d = $conn->prepare("SELECT NOME, CPF_CNPJ FROM DESPACHANTE WHERE COD_DESP = ?");
            $stmt_d->execute([$recibo_para_editar['RES_DESPACHANTE']]);
            $desp_data = $stmt_d->fetch(PDO::FETCH_ASSOC);
            $despachante_cpf = $recibo_para_editar['RES_CPFCNPJ'] ?? ($desp_data['CPF_CNPJ'] ?? '');
        }
        
        if (!empty($recibo_para_editar['RES_CLIENTE_CPF_CNPJ'])) {
             $stmt_c = $conn->prepare("
                SELECT c.NOME, c.ENDERECO, m.MUNICIPIO 
                FROM CLIENTE c 
                LEFT JOIN MUNICIPIO m ON c.COD_MUNI = m.COD_MUNI
                WHERE c.CPF_CNPJ = ?");
             $stmt_c->execute([$recibo_para_editar['RES_CLIENTE_CPF_CNPJ']]);
             $cli_data = $stmt_c->fetch(PDO::FETCH_ASSOC);
             
             $cliente_nome = $cli_data['NOME'] ?? $recibo_para_editar['RES_CLIENTE'];
             $cliente_cpf_cnpj = $recibo_para_editar['RES_CLIENTE_CPF_CNPJ'];
             $cliente_endereco = $cli_data['ENDERECO'] ?? $recibo_para_editar['RES_CLIENTE_ENDERECO'];
             $cliente_cidade = $cli_data['MUNICIPIO'] ?? $recibo_para_editar['RES_CLIENTE_CIDADE']; // Nome da cidade
        }
        
        if (!empty($recibo_para_editar['RES_VEICULO_CODIGO'])) {
            $stmt_v = $conn->prepare("SELECT MODELO, PLACA_UF, RENAVAM FROM VEICULO WHERE CODIGO = ?");
            $stmt_v->execute([$recibo_para_editar['RES_VEICULO_CODIGO']]);
            $vei_data = $stmt_v->fetch(PDO::FETCH_ASSOC);
            $veiculo_modelo = $vei_data['MODELO'] ?? '';
            $veiculo_placa = $vei_data['PLACA_UF'] ?? $recibo_para_editar['RES_VEICULO_PLACA'];
            $veiculo_renavam = $vei_data['RENAVAM'] ?? $recibo_para_editar['RES_VEICULO_RENAVAM'];
        }

        for ($i = 1; $i <= 5; $i++) {
            if (!empty($recibo_para_editar["RES_SERVICO{$i}"])) {
                $valor_item = ($i == 1) ? $recibo_para_editar['RES_TSE_VLUNITARIO'] : 0;
                if ($valor_item == 0) {
                     foreach($lista_servicos as $servico) {
                         if ($servico['TSE_CODIGO'] == $recibo_para_editar["RES_SERVICO{$i}"]) {
                             $valor_item = $servico['TSE_VLUNITARIO'];
                             break;
                         }
                     }
                }
                $itens_do_recibo[] = [
                    'id' => $recibo_para_editar["RES_SERVICO{$i}"],
                    'nome' => $recibo_para_editar["RES_LINHA{$i}"] ?? 'Serviço ' . $i,
                    'valor' => $valor_item
                ];
            }
        }
    }
}

// Lógica de Exclusão
if ($action === 'delete' && $id) {
    try {
        $stmt_del = $conn->prepare("DELETE FROM RECIBO_SIMPLES WHERE RES_CODIGO = ?");
        $stmt_del->execute([$id]);
        header("Location: cadastro_recibo.php?status=deleted");
        exit;
    } catch (PDOException $e) {
        header("Location: cadastro_recibo.php?status=error&msg=" . urlencode($e->getMessage()));
        exit;
    }
}

// LÓGICA DE SALVAR 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $CODIGO = $_POST['RES_CODIGO'] ?: null;  
    
    $emitente_nome = ''; $emitente_cpf = '';
    if (!empty($_POST['RES_DESPACHANTE_ID'])) { 
        foreach ($lista_despachantes as $desp) {
            if ($desp['COD_DESP'] == $_POST['RES_DESPACHANTE_ID']) {
                $emitente_nome = $desp['NOME'];
                $emitente_cpf = $desp['CPF_CNPJ'];
                break;
            }
        }
    }
    
    $cliente_nome = $_POST['recibo_cliente_display'] ?? '';
    $cliente_cpf = $_POST['recibo_cliente_hidden_cpf'] ?? null;
    $cliente_end = $_POST['recibo_cliente_endereco'] ?? null;
    $cliente_cid = $_POST['recibo_cliente_cidade'] ?? null;
    $veiculo_placa = $_POST['recibo_veiculo_placa'] ?? null;
    $veiculo_renavam = $_POST['recibo_veiculo_renavam'] ?? null;

    function limparValor($valor) {
        return (float)str_replace(',', '.', str_replace('.', '', preg_replace('/[^\d,]/', '', $valor)));
    }

    $dados = [
        'RES_DATA' => ($_POST['RES_DATA']) ? date('Y-m-d', strtotime(str_replace('/', '-', $_POST['RES_DATA']))) : date('Y-m-d'),
        'RES_VIAS' => $_POST['RES_VIAS'] ?? 'uma',
        'RES_SITUACAO' => 'Impresso',
        'RES_DATA_ATUALIZACAO' => date('Y-m-d H:i:s'),
        'RES_DESPACHANTE' => $_POST['RES_DESPACHANTE_ID'] ?? null,
        'RES_EMITENTE' => $emitente_nome,
        'RES_CPFCNPJ' => $emitente_cpf,
        'RES_CLIENTE' => $cliente_nome, 
        'RES_CLIENTE_CPF_CNPJ' => $cliente_cpf,
        'RES_CLIENTE_ENDERECO' => $cliente_end,
        'RES_CLIENTE_CIDADE' => $cliente_cid,
        'RES_VEICULO_CODIGO' => $_POST['recibo_veiculo_hidden_id'] ?? null,
        'RES_VEICULO_PLACA' => $veiculo_placa,
        'RES_VEICULO_RENAVAM' => $veiculo_renavam,     
        'RES_VALOR' => limparValor($_POST['valor_total_hidden'] ?? '0'),
        'RES_EXTENSO' => $_POST['valor_extenso_hidden'] ?? null,
    ];
    
    $servicos_id = $_POST['servicos_id'] ?? [];
    $servicos_desc = $_POST['servicos_desc'] ?? [];
    $servicos_valor = $_POST['servicos_valor'] ?? [];

    for ($i = 0; $i < 5; $i++) {
        $dados["RES_SERVICO" . ($i + 1)] = $servicos_id[$i] ?? null;
        $dados["RES_LINHA" . ($i + 1)] = $servicos_desc[$i] ?? null;
        if ($i == 0) {
            $dados["RES_TSE_VLUNITARIO"] = isset($servicos_valor[$i]) ? limparValor($servicos_valor[$i]) : null;
        }
    }
    
    if (empty($CODIGO)) {
        $dados['RES_DATA_CADASTRO'] = date('Y-m-d H:i:s');
    }

    foreach ($dados as $chave => $valor) { if ($valor === '') $dados[$chave] = null; }

    try {
        if (empty($CODIGO)) {
            $colunas = implode(', ', array_keys($dados));
            $placeholders = implode(', ', array_fill(0, count($dados), '?'));
            $sql = "INSERT INTO RECIBO_SIMPLES ($colunas) VALUES ($placeholders)";
            $stmt = $conn->prepare($sql);
            $stmt->execute(array_values($dados));
        } else {
            unset($dados['RES_DATA_CADASTRO']);
            $set_sql = [];
            foreach (array_keys($dados) as $coluna) { $set_sql[] = "$coluna = ?"; }
            $sql = "UPDATE RECIBO_SIMPLES SET " . implode(', ', $set_sql) . " WHERE RES_CODIGO = ?";
            $valores = array_values($dados); $valores[] = $CODIGO;
            $stmt = $conn->prepare($sql);
            $stmt->execute($valores);
        }
        header("Location: cadastro_recibo.php?status=success");
        exit;
    } catch (PDOException $e) {
        header("Location: cadastro_recibo.php?status=error&msg=" . urlencode($e->getMessage()));
        exit;
    }
}

$sql_base = "SELECT RES_CODIGO, RES_DATA, RES_CLIENTE, RES_CLIENTE_CPF_CNPJ, RES_VALOR, RES_EMITENTE FROM RECIBO_SIMPLES";
$sql_count_base = "SELECT COUNT(*) FROM RECIBO_SIMPLES";
$params = [];
$where_clauses = [];
if (!empty($_GET['valor_pesquisa'])) {
    $campo = $_GET['campo_pesquisa']; $valor = '%' . $_GET['valor_pesquisa'] . '%';
    if ($campo == 'codigo') { $where_clauses[] = "RES_CODIGO LIKE ?"; $params[] = $valor; }
    elseif ($campo == 'nome') { $where_clauses[] = "RES_CLIENTE LIKE ?"; $params[] = $valor; }
    elseif ($campo == 'cpf_cnpj') { $where_clauses[] = "RES_CLIENTE_CPF_CNPJ LIKE ?"; $params[] = $valor; }
}
if (!empty($where_clauses)) {
    $sql_base .= " WHERE " . implode(' AND ', $where_clauses);
    $sql_count_base .= " WHERE " . implode(' AND ', $where_clauses);
}
$total_stmt = $conn->prepare($sql_count_base); $total_stmt->execute($params);
$total_records = $total_stmt->fetchColumn();
$total_pages = $total_records > 0 ? ceil($total_records / $limit) : 1;
$sql_final = $sql_base ." ORDER BY RES_CODIGO DESC LIMIT $limit OFFSET $offset";
$stmt = $conn->prepare($sql_final); $stmt->execute($params);
$recibos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$query_string = http_build_query(array_merge($_GET, ['page' => '']));
$link_primeiro = "cadastro_recibo.php?{$query_string}1";
$link_anterior = ($page > 1) ? "cadastro_recibo.php?{$query_string}" . ($page - 1) : "#";
$link_proximo = ($page < $total_pages) ? "cadastro_recibo.php?{$query_string}" . ($page + 1) : "#";
$link_ultimo = "cadastro_recibo.php?{$query_string}" . $total_pages;

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emissão de Recibos - Lindomar Despachante</title>
    <link rel="stylesheet" href="../css/style_sistema.css">
    <link rel="stylesheet" href="../css/print_recibo.css" >
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
           #recibo-para-impressao { display: none; }
        
        .modal {
            display: none; 
            position: fixed; 
            z-index: 1000;
            left: 0; top: 0;
            width: 100%; height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.6);
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background-color: #f8f9fa;
            margin: auto;
            padding: 25px;
            border: 1px solid #bdc3c7;
            width: 70%; 
            max-width: 800px;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            position: relative;
        }
        .results-list { max-height: 300px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px; background: #fff; }
        .result-item { padding: 10px 15px; border-bottom: 1px solid #eee; cursor: pointer; display: flex; justify-content: space-between; }
        .result-item:hover { background-color: #f0f0f0; }
        .result-item:last-child { border-bottom: none; }
        .result-item strong { color: #2c3e50; }
        .result-item span { color: #777; }
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
            <h1>Recibos</h1>

             <?php if (!empty($message)): ?>
                <div class="alert <?= $message_type ?>"><?= $message ?></div>
            <?php endif; ?>

            <?php 
                if (($action === 'edit') && !empty($recibo_para_editar)) :
            ?>
            <div class= "form-tollbar client-header" style="display: flex; justify-content: space-between;">
                <div class="client-info">
                        <strong><?= htmlspecialchars($recibo_para_editar['RES_CLIENTE'])?></strong><br><br>
                        CPF/CNPJ: <?= htmlspecialchars($recibo_para_editar['RES_CLIENTE_CPF_CNPJ']) ?><br><br>
                        Valor Total:<?= htmlspecialchars($recibo_para_editar['RES_VALOR']) ?>
                    </div>


                <div class="client-actions">
                    <a href="cadastro_recibo.php" class="btn btn-danger">Voltar para Consulta</a>
                </div>
            </div>

            <?php else :?>
                <div class="form-toolbar">
                    <?php if (temPermissao('acessar_config')): ?>
                        <a href="cadastro_recibo.php?action=new" class="btn">Novo</a>
                    <?php endif; ?>        
                </div>
            <?php endif; ?>

            <div class="tabs">
                <div class="tab-buttons">
                    <button type="button" class="tab-button <?=$aba_ativa == 'consulta_recibo' ? 'active' : '' ?>" data-tab="consulta_recibo" <?php if ($aba_ativa == 'cadastro_recibo'): ?> style="display: none;" <?php endif;?>>Consulta</button>
                    <button type="button" class="tab-button <?= $aba_ativa == 'cadastro_recibo' ? 'active' : '' ?>" data-tab="cadastro_recibo" <?= $aba_ativa != 'cadastro_recibo' ? 'disabled' : '' ?>>Cadastro</button>
                </div>

                <div class="tab-content">
                    <div id="consulta_recibo" class="tab-pane <?= $aba_ativa == 'consulta_recibo' ? 'active' : '' ?>">
                        <form method="GET" action="cadastro_recibo.php">
                            <fieldset class="search-box">
                                <legend>Opções de pesquisa</legend>
                                <div class="form-group">
                                    <label for="campo_pesquisa">Campo de Pesquisa:</label>
                                    <select id="campo_pesquisa" name="campo_pesquisa">
                                        <option value="codigo"<?= ($_GET['campo_pesquisa'] ?? '') === 'codigo' ? 'selected' : '' ?>>CÓDIGO</option>
                                        <option value="nome"<?= ($_GET['campo_pesquisa'] ?? '') === 'nome' ? 'selected' : '' ?>>CLIENTE</option>
                                        <option value="cpf_cnpj"<?= ($_GET['campo_pesquisa'] ?? '') === 'cpf_cnpj' ? 'selected' : '' ?>>CPF/CNPJ</option>
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
                                        <th>DATA</th>
                                        <th>CLIENTE</th>
                                        <th>CPF/CNPJ</th>
                                        <th>VALOR(R$)</th>
                                        <th>EMITENTE</th>
                                        <th style="text-align: center;">AÇÃO</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($recibos)): ?>
                                        <?php foreach ($recibos as $recibo): ?>
                                        <tr class="clickable-row" data-id="<?= $recibo['RES_CODIGO'] ?>">
                                            <td><?=htmlspecialchars($recibo['RES_CODIGO'])?></td>
                                            <td><?= $recibo['RES_DATA'] ? date('d/m/Y', strtotime($recibo['RES_DATA']) ?? '') : '' ?></td>
                                            <td><?=htmlspecialchars($recibo['RES_CLIENTE']?? '')?></td>
                                            <td><?=htmlspecialchars($recibo['RES_CLIENTE_CPF_CNPJ']?? '')?></td>       
                                            <td>R$ <?= number_format($recibo['RES_VALOR'] ?? 0, 2, ',', '.') ?></td>
                                            <td><?=htmlspecialchars($recibo['RES_EMITENTE']?? '')?></td>
                                            <td style="text-align: center; white-space: nowrap; display: flex; justify-content: center; gap: 15px;">
                                                <?php if (temPermissao('gerenciar_config')) : ?>
                                                    <a href="cadastro_recibo.php?action=edit&id=<?= $recibo['RES_CODIGO'] ?>" class="btn btn-primary">Editar</a>
                                                <?php endif; ?>
                                                <?php if (temPermissao('gerenciar_config')) : ?>
                                                    <a href="cadastro_recibo.php.php?action=delete&id=<?= $recibo['RES_CODIGO'] ?>" class="btn btn-danger btn-excluir-linha">Excluir</a>
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
                    <div id="cadastro_recibo" class="tab-pane <?=$aba_ativa == 'cadastro_recibo' ? 'active' : '' ?>">
                        
                        <form id="formReciboServicos" method="POST" action="cadastro_recibo.php">
                            <input type="hidden" name="RES_CODIGO" value="<?= htmlspecialchars($recibo_para_editar['RES_CODIGO'] ?? '') ?>">
                            <input type="hidden" id="valor_total_hidden" name="valor_total_hidden" value="<?= htmlspecialchars($recibo_para_editar['RES_VALOR'] ?? '0') ?>">
                            <input type="hidden" id="valor_extenso_hidden" name="valor_extenso_hidden" value="<?= htmlspecialchars($recibo_para_editar['RES_EXTENSO'] ?? '') ?>">
                            
                            <fieldset <?= ($action === 'view') ? 'disabled' : '' ?> style="border: none;">
                                <fieldset class="form-section">
                                    <legend>Dados Gerais</legend>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Número</label>
                                            <input type="text" value="<?= htmlspecialchars($recibo_para_editar['RES_CODIGO'] ?? 'NOVO') ?>" readonly>
                                        </div>
                                        <div class="form-group">
                                            <label>Data Emissão</label>
                                            <input type="text" name="RES_DATA" value="<?= !empty($recibo_para_editar['RES_DATA']) ? date('d/m/Y', strtotime($recibo_para_editar['RES_DATA'])) : date('d/m/Y') ?>" readonly>
                                        </div>
                                        <div class="form-group">
                                            <label>Vias</label>
                                            <select id="RES_VIAS" name="RES_VIAS">
                                                <option value="uma" <?=($recibo_para_editar['RES_VIAS'] ?? 'uma') == 'uma' ? 'selected' : '' ?>>Uma</option>
                                                <option value="duas" <?=($recibo_para_editar['RES_VIAS'] ?? '') == 'duas' ? 'selected' : '' ?>>Duas</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="RES_DESPACHANTE_ID">Emitente (Despachante)</label>
                                            <select id="recibo_despachante_select" name="RES_DESPACHANTE_ID">
                                                <option value="">-- Selecione o Emitente --</option>
                                                <?php foreach ($lista_despachantes as $desp): ?>
                                                    <option value="<?= $desp['COD_DESP'] ?>" data-cpf="<?= htmlspecialchars($desp['CPF_CNPJ'] ?? '') ?>"
                                                        <?= ($recibo_para_editar['RES_DESPACHANTE'] ?? '') == $desp['COD_DESP'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($desp['NOME']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>CPF/CNPJ (Emitente)</label>
                                            <input type="text" id="recibo_despachante_cpf" name="RES_CPFCNPJ" value="<?= htmlspecialchars($despachante_cpf ?? '') ?>" readonly>
                                        </div> 
                                    </div>
                                </fieldset>
                                
                                <fieldset class="form-section">
                                    <legend>Dados do Cliente e Veículo</legend>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Cliente</label>
                                            <div class="input-with-button">
                                                <input type="text" id="recibo_cliente_display" name="recibo_cliente_display" placeholder="Busque pelo cliente..." value="<?= htmlspecialchars($cliente_nome ?? '') ?>" readonly>
                                                <input type="hidden" id="recibo_cliente_hidden_cpf" name="recibo_cliente_hidden_cpf" value="<?= htmlspecialchars($cliente_cpf_cnpj ?? '') ?>"> 
                                                <button type="button" id="btnBuscarClienteRecibo" class="btn-lookup">...</button>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label>CPF/CNPJ</label>
                                            <input type="text" id="recibo_cliente_cpf" name="RES_CLIENTE_CPF_CNPJ_display" value="<?= htmlspecialchars($cliente_cpf_cnpj ?? '') ?>" readonly>
                                        </div>
                                    </div>
                                     <div class="form-row">
                                        <div class="form-group">
                                            <label>Endereço</label>
                                            <input type="text" id="recibo_cliente_endereco" name="recibo_cliente_endereco" value="<?= htmlspecialchars($cliente_endereco ?? '') ?>" readonly>
                                        </div>
                                        <div class="form-group">
                                            <label>Cidade</label>
                                            <input type="text" id="recibo_cliente_cidade" name="recibo_cliente_cidade" value="<?= htmlspecialchars($cliente_cidade ?? '') ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Veículo</label>
                                            <div class="input-with-button">
                                                <input type="text" id="recibo_veiculo_display" value="<?= htmlspecialchars($veiculo_modelo ?? '') ?>" readonly>
                                                <input type="hidden" id="recibo_veiculo_hidden_id" name="RES_VEICULO_CODIGO" value="<?= htmlspecialchars($recibo_para_editar['RES_VEICULO_CODIGO'] ?? '') ?>">
                                                <button type="button" id="btnBuscarVeiculoRecibo" class="btn-lookup" <?= empty($cliente_cpf_cnpj) ? 'disabled' : '' ?>>...</button>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label>Placa</label>
                                            <input type="text" id="recibo_veiculo_placa" name="recibo_veiculo_placa" value="<?= htmlspecialchars($veiculo_placa ?? '') ?>" readonly>
                                        </div>
                                        <div class="form-group">
                                            <label>Renavam</label>
                                            <input type="text" id="recibo_veiculo_renavam" name="recibo_veiculo_renavam" value="<?= htmlspecialchars($veiculo_renavam ?? '') ?>" readonly>
                                        </div>
                                    </div>
                                </fieldset>

                                <fieldset class="form-section">
                                    <legend>Lista de Serviços (Máx 5)</legend>
                                    <div class="table-container">
                                        <table class="data-table">
                                            <thead>
                                                <tr>
                                                    <th>Serviço</th>
                                                    <th style="width: 150px;">Valor (R$)</th>
                                                    <th style="width: 50px;">Ação</th>
                                                </tr>
                                            </thead>
                                            <tbody id="tabela-servicos">
                                                <?php if (!empty($itens_do_recibo)): ?>
                                                    <?php foreach ($itens_do_recibo as $item): ?>
                                                    <tr class="item-row">
                                                        <td>
                                                            <select name="servicos_id[]" class="item-servico-select">
                                                                <option value="">Selecione...</option>
                                                                <?php foreach ($lista_servicos as $servico): ?>
                                                                    <option value="<?= $servico['TSE_CODIGO'] ?>" data-valor="<?= $servico['TSE_VLUNITARIO'] ?>" data-desc="<?= htmlspecialchars($servico['TSE_DESCRICAO']) ?>"
                                                                        <?= ($item['id'] == $servico['TSE_CODIGO']) ? 'selected' : '' ?>>
                                                                        <?= htmlspecialchars($servico['TSE_DESCRICAO']) ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                            <input type="hidden" name="servicos_desc[]" value="<?= htmlspecialchars($item['nome']) ?>">
                                                        </td>
                                                        <td>
                                                            <input type="text" name="servicos_valor[]" class="mascara-moeda item-valor" value="<?= number_format($item['valor'] ?? 0, 2, ',', '.') ?>">
                                                        </td>
                                                        <td>
                                                            <button type="button" class="btn-remover-servico btn-danger">X</button>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="form-footer" style="justify-content: flex-start;">
                                        <button type="button" id="btn-add-servico" class="btn">Adicionar Serviço</button>
                                    </div>
                                </fieldset>
                            </fieldset>
                            
                            <div class="resultado-calculo" style="text-align: right;">
                                <p class="valor-final">Valor Total: <strong id="valor-total-display">R$ <?= number_format($recibo_para_editar['RES_VALOR'] ?? 0, 2, ',', '.') ?></strong></p>
                            </div>
                            
                            <div class="form-footer">
                                <button type="button" id="btn-imprimir" class="btn">Imprimir Recibo</button>
                                <button type="submit" class="btn btn-primary">Salvar</button>
                                <a href="cadastro_recibo.php" class="btn">Cancelar</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <div id="modalBuscaClienteRecibo" class="modal">
        <div class="modal-content">
            <span class="close-button" id="closeClienteModal">&times;</span>
            <h2>Buscar Cliente</h2>
            <input type="text" id="inputBuscaClienteRecibo" placeholder="Digite para buscar pelo nome ou código...">
            <div id="listaResultadosClienteRecibo" class="results-list"></div>
        </div>
    </div>
    <div id="modalBuscaVeiculoRecibo" class="modal">
        <div class="modal-content">
            <span class="close-button" id="closeVeiculoModal">&times;</span>
            <h2>Buscar Veículo do Cliente</h2>
            <input type="text" id="inputBuscaVeiculoRecibo" placeholder="Busque pela placa ou modelo...">
            <div id="listaResultadosVeiculoRecibo" class="results-list"></div>
        </div>
    </div>
    <div id="modalBuscaDespachanteRecibo" class="modal">
        <div class="modal-content">
            <span class="close-button" id="closeDespachanteModal">&times;</span>
            <h2>Buscar Despachante</h2>
            <input class="text" type="text" id="inputBuscaDespachanteRecibo" placeholder="Busque pelo nome ou código...">
            <div id="listaResultadosDespachanteRecibo" class="results-list"></div>
        </div>
    </div>

    <div id="recibo-para-impressao" class="recibo-print-container">
        <div class="recibo">
            <div class="recibo-header">
                <div class="recibo-header-top">
                    <div class="recibo-logo">
                        <img src="../img/logo-sem-fundo.png" alt="Logo">
                    </div>
                    <div class="recibo-empresa">
                        <p><strong>LINDOMAR C. CAVALCANTE - ME</strong></p>
                        <p>Rua Raimundo A. Pinheiro, 398, Centro</p>
                        <p>São Raimundo Nonato - PI, 64.770-000</p>
                        <p>CNPJ: 01.869.772/0001-27</p>
                    </div>
                </div>

                <div class="recibo-numero-valor">
                    <div class="numero">
                    <p>RECIBO Nº</p>
                    <span id="print_numero"></span>
                </div>
                <div class="valor">
                    <p>VALOR</p>
                    <span id="print_valor"></span>
                </div>
            </div>
            <div class="recibo-body">
                <p>Recebi(emos) de <strong id="print_cliente_nome"></strong>, CPF/CNPJ nº <strong id="print_cliente_cpf"></strong>, a importância de <strong id="print_valor_extenso"></strong>.</p>
                <p>Referente a:</p>
                <div id="print_servicos_lista" class="servicos-lista"></div>
            </div>

            <div class="recibo-footer">
                <p class="data-local">
                    <span id="print_cidade_data"></span>
                </p>
                <div class="assinaturas" style="display: flex; justify-content: space-between;">
                <div class="assinatura-block">
                        <p class="assinatura-linha">________________________________________</p>
                        <p class="assinatura-nome" id="print_emitente_nome"></p>
                        <p class="assinatura-doc" id="print_emitente_cpf"></p>
                    </div>
                    <div class="assinatura-block">
                        <p class="assinatura-linha">________________________________________</p>
                        <p class="assinatura-nome" id="print_pagante_nome">(Pagante)</p>
                        <p class="assinatura-doc" id="print_pagante_cpf">(CPF/CNPJ)</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div id="modalBuscaClienteRecibo" class="modal">
    <div id="customConfirmModal" class="modal-confirm"> </div>

    <script src="../js/script_menu.js"></script>
    <script src="../js/script_mascara.js"></script>
    <script src="../js/script_main.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        
        const tabelaServicos = document.getElementById('tabela-servicos');
        const btnAddServico = document.getElementById('btn-add-servico');
        const totalDisplay = document.getElementById('valor-total-display');
        const totalHidden = document.getElementById('valor_total_hidden');
        const btnImprimir = document.getElementById('btn-imprimir');
        const form = document.getElementById('formReciboServicos');
        const selectDesp = document.getElementById('recibo_despachante_select');

        const linhaTemplate = `
            <td>
                <select name="servicos_id[]" class="item-servico-select">
                    <option value="">Selecione...</option>
                    <?php foreach ($lista_servicos as $servico): ?>
                        <option value="<?= $servico['TSE_CODIGO'] ?>" data-valor="<?= $servico['TSE_VLUNITARIO'] ?>" data-desc="<?= htmlspecialchars($servico['TSE_DESCRICAO']) ?>">
                            <?= htmlspecialchars($servico['TSE_DESCRICAO']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="servicos_desc[]" value="">
            </td>
            <td>
                <input type="text" name="servicos_valor[]" class="mascara-moeda item-valor" value="R$ 0,00">
            </td>
            <td>
                <button type="button" class="btn-remover-servico btn-danger">X</button>
            </td>
        `;


        function calcularTotal() {
            let total = 0;
            tabelaServicos.querySelectorAll('.item-valor').forEach(input => {
                const valor = parseFloat(input.value.replace(/[^\d,]/g, '').replace(',', '.')) || 0;
                total += valor;
            });
            const valorFormatado = total.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
            totalDisplay.textContent = valorFormatado;
            totalHidden.value = total.toFixed(2);
        }

        function adicionarLinha() {
            if (tabelaServicos.rows.length >= 5) {
                alert('Limite de 5 serviços por recibo atingido.'); return;
            }
            const newRow = tabelaServicos.insertRow();
            newRow.className = 'item-row';
            newRow.innerHTML = linhaTemplate;
            aplicarMascaraDinheiro(newRow.querySelector('.mascara-moeda'));
        }

        tabelaServicos.addEventListener('click', function(e) {
            if (e.target.classList.contains('btn-remover-servico')) {
                e.target.closest('tr').remove();
                calcularTotal();
            }
        });
        if (btnAddServico) btnAddServico.addEventListener('click', adicionarLinha);
        
        tabelaServicos.addEventListener('change', function(e) {
            if (e.target.classList.contains('item-servico-select')) {
                const select = e.target;
                const selectedOption = select.options[select.selectedIndex];
                const valor = selectedOption.dataset.valor || 0;
                const desc = selectedOption.dataset.desc || '';
                const row = select.closest('tr');
                const valorInput = row.querySelector('.item-valor');
                const descInput = row.querySelector('input[name="servicos_desc[]"]');
                valorInput.value = parseFloat(valor).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
                descInput.value = desc;
                calcularTotal();
            }
        });
        
        tabelaServicos.addEventListener('input', function(e) {
            if (e.target.classList.contains('item-valor')) {
                calcularTotal();
            }
        });

        const codigoRecibo = document.querySelector('input[name="RES_CODIGO"]').value;
        if (tabelaServicos.rows.length === 0 && !codigoRecibo) {
            adicionarLinha();
        }
        if (selectDesp) {
            selectDesp.addEventListener('change', function() {
                const opt = this.options[this.selectedIndex];
                document.getElementById('recibo_despachante_cpf').value = opt.dataset.cpf || '';
            });
        }
        
        function setupModal(btnId, modalId, closeSelector, inputSelector, resultsSelector, urlBuilder, renderFunc, selectFunc) {
            const modal = document.getElementById(modalId);
            const btn = document.getElementById(btnId);
            const closeBtn = modal.querySelector(closeSelector);
            const input = document.getElementById(inputSelector);
            const results = document.getElementById(resultsSelector);
            
            if (!modal || !btn) return;
            
            const openModal = () => {
                modal.style.display = 'flex'; 
                input.focus();
                buscarDados(''); 
            };
            const closeModal = () => modal.style.display = 'none';
            
            btn.addEventListener('click', openModal);
            closeBtn.addEventListener('click', closeModal);
            window.addEventListener('click', (e) => { if (e.target == modal) closeModal(); });

            async function buscarDados(termo) {
                const url = urlBuilder(termo);
                if (url === null) {
                    results.innerHTML = '<div>Selecione um cliente primeiro.</div>';
                    return;
                }
                results.innerHTML = '<div>Buscando...</div>';
                try {
                    const response = await fetch(url);
                    const data = await response.json();
                    results.innerHTML = '';
                    if (data.length === 0) {
                        results.innerHTML = '<div>Nenhum resultado encontrado.</div>'; return;
                    }
                    data.forEach(item => {
                        const div = renderFunc(item);
                        div.addEventListener('click', () => {
                            selectFunc(item);
                            closeModal();
                        });
                        results.appendChild(div);
                    });
                } catch (err) {
                    results.innerHTML = '<div>Erro ao buscar dados.</div>';
                }
            }
            input.addEventListener('keyup', () => buscarDados(input.value));
        }

        setupModal(
            'btnBuscarClienteRecibo', 'modalBuscaClienteRecibo', '.close-button',
            'inputBuscaClienteRecibo', 'listaResultadosClienteRecibo',
            (termo) => `modais/buscar_cliente.php?q=${encodeURIComponent(termo)}`,
            (item) => {
                const div = document.createElement('div');
                div.className = 'result-item';
                div.innerHTML = `<strong>${item.nome}</strong> <span>${item.cpf_cnpj}</span>`;
                return div;
            },
            (item) => {
                document.getElementById('recibo_cliente_display').value = item.nome;
                document.getElementById('recibo_cliente_hidden_cpf').value = item.cpf_cnpj;
                document.getElementById('recibo_cliente_cpf').value = item.cpf_cnpj;
                document.getElementById('recibo_cliente_endereco').value = item.endereco || '';
                document.getElementById('recibo_cliente_cidade').value = item.cidade || '';
                document.getElementById('btnBuscarVeiculoRecibo').disabled = false;
                document.getElementById('recibo_veiculo_display').value = '';
                document.getElementById('recibo_veiculo_hidden_id').value = '';
                document.getElementById('recibo_veiculo_placa').value = '';
                document.getElementById('recibo_veiculo_renavam').value = '';
            }
        );

        setupModal(
            'btnBuscarVeiculoRecibo', 'modalBuscaVeiculoRecibo', '.close-button',
            'inputBuscaVeiculoRecibo', 'listaResultadosVeiculoRecibo',
            (termo) => {
                const clienteCpf = document.getElementById('recibo_cliente_hidden_cpf').value;
                if (!clienteCpf) return null;
                return `modais/buscar_veiculos_por_cliente.php?q=${encodeURIComponent(termo)}&cliente_cpf=${encodeURIComponent(clienteCpf)}`;
            },
            (item) => {
                const div = document.createElement('div');
                div.className = 'result-item';
                div.innerHTML = `<strong>${item.placa}</strong> <span>${item.modelo}</span>`;
                return div;
            },
            (item) => {
                document.getElementById('recibo_veiculo_display').value = item.modelo;
                document.getElementById('recibo_veiculo_hidden_id').value = item.id;
                document.getElementById('recibo_veiculo_placa').value = item.placa;
                document.getElementById('recibo_veiculo_renavam').value = item.renavam;
            }
        );

        // --- IMPRESSÃO ---
        if (btnImprimir) {
            btnImprimir.addEventListener('click', function() {
                document.getElementById('print_numero').textContent = form.querySelector('input[name="RES_CODIGO"]').value || 'NOVO';
                document.getElementById('print_valor').textContent = totalDisplay.textContent;
                
                document.getElementById('print_cliente_nome').textContent = document.getElementById('recibo_cliente_display').value || 'N/A';
                document.getElementById('print_cliente_cpf').textContent = document.getElementById('recibo_cliente_cpf').value || 'N/A';
                document.getElementById('print_valor_extenso').textContent = document.getElementById('valor_extenso_hidden').value || '(Valor por extenso)';
                
                const listaPrint = document.getElementById('print_servicos_lista');
                listaPrint.innerHTML = '';
                tabelaServicos.querySelectorAll('.item-row').forEach(row => {
                    const descSelect = row.querySelector('.item-servico-select');
                    const desc = descSelect.options[descSelect.selectedIndex].text;
                    const val = row.querySelector('.item-valor').value;
                    if (desc && desc !== 'Selecione...') {
                        const p = document.createElement('p');
                        p.innerHTML = `- ${desc} <strong>(${val})</strong>`;
                        listaPrint.appendChild(p);
                    }
                });
                
                const selDesp = document.getElementById('recibo_despachante_select');
                const despNome = selDesp.options[selDesp.selectedIndex].text;
                document.getElementById('print_cidade_data').textContent = (document.getElementById('recibo_cliente_cidade').value || 'Sua Cidade') + `, ${form.querySelector('input[name="RES_DATA"]').value}.`;
                document.getElementById('print_emitente_nome').textContent = (despNome.includes('--')) ? 'N/A' : despNome;
                document.getElementById('print_emitente_cpf').textContent = document.getElementById('recibo_despachante_cpf').value;
                document.getElementById('print_pagante_nome').textContent = document.getElementById('recibo_cliente_display').value || 'N/A';
                document.getElementById('print_pagante_cpf').textContent = document.getElementById('recibo_cliente_cpf').value;

                window.print();
            });
        }
        
        // --- MÁSCARA ---
        function aplicarMascaraDinheiro(input) {
            if (!input) return;
            input.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                value = (value / 100).toLocaleString('pt-BR', { minimumFractionDigits: 2 });
                e.target.value = (value === '0,00') ? '' : 'R$ ' + value;
            });
        }
        document.querySelectorAll('.mascara-moeda').forEach(aplicarMascaraDinheiro);
        
        calcularTotal();
    });
    </script>
</body>
</html>