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


$aba_ativa = 'consulta_emplacamento';
$emplacamento_para_editar = [];
$emplacamentos = []; 
$config_tipos = []; 

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$message_type = '';
if (!empty($_GET['status'])) {
    switch ($_GET['status']) {
        case 'success':
            $message = '<strong>Sucesso!</strong> Emplacamento salvo com sucesso.';
            $message_type = 'success';
            break;
        case 'deleted':
            $message = 'Emplacamento excluído com sucesso.';
            $message_type = 'success';
            break;
        case 'error':
            $errorMessage = $_GET['msg'] ?? 'Ocorreu um problema não especificado.';
            $message = '<strong>Erro!</strong> ' . htmlspecialchars(urldecode($errorMessage));
            $message_type = 'error';
            break;
        
        case 'config_success':
            $message = '<strong>Sucesso!</strong> Configuração salva com sucesso.';
            $message_type = 'success';
            break;
        case 'config_deleted':
            $message = 'Configuração excluída com sucesso.';
            $message_type = 'success';
            break;
        case 'config_error':
            $errorMessage = $_GET['msg'] ?? 'Ocorreu um problema não especificado.';
            $message = '<strong>Erro na Configuração!</strong> ' . htmlspecialchars(urldecode($errorMessage));
            $message_type = 'error';
            break;
    }
}



try {
    $stmt_tipos = $conn->query("SELECT * FROM EMPLACAMENTO_TIPO ORDER BY EMT_DESCRICAO");
    $tipos_veiculo = $stmt_tipos->fetchAll(PDO::FETCH_ASSOC);
    $config_tipos =  $tipos_veiculo; 
} catch (PDOException $e) {
     $message = '<strong>Erro ao carregar tipos!</strong> ' . $e->getMessage();
     $message_type = 'error';
}


function converterData($data) {
    if (empty($data)) return null;
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) { return $data; }
    try {
        $dt = DateTime::createFromFormat('d/m/Y', $data);
        if ($dt && $dt->format('d/m/Y') === $data) {
            return $dt->format('Y-m-d');
        }
    } catch (Exception $e) { }
    return null;
}
function limparValor($valor) {
    if (!isset($valor) || $valor === '') return null;
    $valor = str_replace('.', '', $valor); 
    $valor = str_replace(',', '.', $valor); 
    return (float)preg_replace('/[^\d\.]/', '', $valor); 
}


// LÓGICA DE AÇÕES
$action = $_GET['action'] ?? null;
$id = $_GET['id'] ?? null;

if ($action === 'new') {
    $aba_ativa = 'cadastro_emplacamento';
}

if (($action === 'edit' || $action === 'view') && $id) {
    $aba_ativa = 'cadastro_emplacamento';
    $stmt = $conn->prepare("SELECT * FROM EMPLACAMENTO WHERE EMP_CODIGO = ?");
    $stmt->execute([$id]);
    $emplacamento_para_editar = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($action === 'delete' && $id) {
    try {
        $stmt = $conn->prepare("DELETE FROM EMPLACAMENTO WHERE EMP_CODIGO = ?");
        $stmt->execute([$id]);
        header("Location: cadastro_primeiro_emplacamento.php?status=deleted");
        exit;
    } catch (PDOException $e) {
        header("Location: cadastro_primeiro_emplacamento.php?status=error&msg=" . urlencode($e->getMessage()));
        exit;
    }
}
if ($action === 'delete' && $id) {
    try {
        $stmt = $conn->prepare("DELETE FROM EMPLACAMENTO WHERE EMP_CODIGO = ?");
        $stmt->execute([$id]);
        header("Location: cadastro_primeiro_emplacamento.php?status=deleted");
        exit;
    } catch (PDOException $e) {
        
        header("Location: cadastro_primeiro_emplacamento.php?status=error&msg=" . urlencode($e->getMessage()));
        exit;
    }
}


// LÓGICA DE SALVAR 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $CODIGO = $_POST['EMP_CODIGO'] ?: null;   
    $dados = [
        'EMP_EMT_CODIGO' => $_POST['EMP_EMT_CODIGO'] ?? null,
        'EMP_CPF_CNPJ' => $_POST['EMP_CPF_CNPJ'] ?? null,
        'EMP_VEI_CODIGO' => $_POST['EMP_VEI_CODIGO'] ?? null,
        'EMP_VALOR_VEICULO' => $_POST['EMP_VALOR_VEICULO'] ?? null,
        'EMP_DATA_NOTA_FISCAL' => $_POST['EMP_DATA_NOTA_FISCAL'] ?? null,
        'EMP_QTDE_MESES' => $_POST['EMP_QTDE_MESES'] ?? null,
        'EMP_VALOR_IPVA' => $_POST['EMP_VALOR_IPVA']?? null,
        'EMP_MES_NOTA_FISCAL' => $_POST['EMP_MES_NOTA_FISCAL'] ?? null,
        'EMP_VALOR_DPVAT' => $_POST['EMP_VALOR_DPVAT'] ?? null,
        'EMP_JUROS_IPVA' => $_POST['EMP_JUROS_IPVA'] ?? null,
        'EMP_MULTA_IPVA' => $_POST['EMP_MULTA_IPVA'] ?? null,
        'EMP_VALOR_EMPLACAMENTO' => $_POST['EMP_VALOR_EMPLACAMENTO'] ?? null,
        'EMP_VALOR_PLACA' => $_POST['EMP_VALOR_PLACA'] ?? null,
        'EMP_VALOR_VISTORIA' => $_POST['EMP_VALOR_VISTORIA'] ?? null,
        'EMP_VALOR_DESPACHANTE' => $_POST['EMP_VALOR_DESPACHANTE']?? null,
        'EMP_DATA_CADASTRO' => $_POST['EMP_DATA_CADASTRO'] ?? null,
        'EMP_VALOR_TOTAL' => $_POST['EMP_VALOR_TOTAL'] ?? null,
        'EMP_OBSERVACAO' => $_POST['EMP_OBSERVACAO'] ?? null,
        'EMP_CLI_CODIGO' => $_POST['EMP_CLI_CODIGO'] ?? null,
        'EMP_CLI_NOME' => $_POST['EMP_CLI_NOME'] ?? null,
        'EMP_CLI_TIPO' => $_POST['EMP_CLI_TIPO'] ?? null,
        'EMP_VEI_PLACA' => $_POST['EMP_VEI_PLACA'] ?? null,
        'EMP_VEI_RENAVAM' => $_POST['EMP_VEI_RENAVAM'] ?? null,
        'EMP_VEI_MODELO' => $_POST['EMP_VEI_MODELO'] ?? null,
        'EMP_ALIQUOTA' => $_POST['EMP_ALIQUOTA'] ?? null
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
            $sql = "INSERT INTO EMPLACAMENTO ($colunas) VALUES ($placeholders)";
            $stmt = $conn->prepare($sql);
            $stmt->execute(array_values($dados));
        } else {
            $set_sql = [];
            foreach (array_keys($dados) as $coluna) {
                $set_sql[] = "$coluna = ?";
            }
            $sql = "UPDATE EMPLACAMENTO SET " . implode(', ', $set_sql) . " WHERE EMP_CODIGO = ?";
            $valores = array_values($dados);
            $valores[] = $CODIGO;
            $stmt = $conn->prepare($sql);
            $stmt->execute($valores);
        }
        header("Location: cadastro_primeiro_emplacamento.php?status=success");
        exit;
    } catch (PDOException $e) {
        header("Location: cadastro_primeiro_emplacamento.php?status=error&msg=" . urlencode($e->getMessage()));
        exit;
    }
}


// --- LÓGICA DE BUSCA E CONSULTA ---
$sql_base = "SELECT * FROM EMPLACAMENTO";
$sql_count_base = "SELECT COUNT(*) FROM EMPLACAMENTO";
$params = [];
$where_clauses = [];

if (!empty($_GET['valor_pesquisa'])) {
    $campo = $_GET['campo_pesquisa'];
    $valor = '%' . $_GET['valor_pesquisa'] . '%';

    if ($campo == 'codigo') $where_clauses[] = "EMP_CODIGO LIKE ?";
    elseif ($campo == 'nome') $where_clauses[] = "EMP_CLI_NOME LIKE ?";
    elseif ($campo == 'vei_modelo') $where_clauses[] = "EMP_VEI_MODELO LIKE ?";
    // --- [CORREÇÃO] Corrigido o campo de 'data_cadastro' e o nome da coluna ---
    elseif ($campo == 'data_cadastro') $where_clauses[] = "EMP_DATA_CADASTRO LIKE ?"; 
    
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
$total_pages = $total_records > 0 ? ceil($total_records / $limit) : 1; 

$sql_final = $sql_base ." ORDER BY EMP_CODIGO LIMIT $limit OFFSET $offset";
$stmt = $conn->prepare($sql_final);
$stmt->execute($params);
$emplacamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Lógica  de navegação
$query_string = http_build_query(array_merge($_GET, ['page' => '']));
$link_primeiro = "cadastro_primeiro_emplacamento.php?{$query_string}1";
$link_anterior = ($page > 1) ? "cadastro_primeiro_emplacamento.php?{$query_string}" . ($page - 1) : "#";
$link_proximo = ($page < $total_pages) ? "cadastro_primeiro_emplacamento.php?{$query_string}" . ($page + 1) : "#";
$link_ultimo = "cadastro_primeiro_emplacamento.php?{$query_string}" . $total_pages;
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>1° Emplacamento - Lindomar Despachante</title>
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
       
        .modal {display: none; position: fixed; z-index: 1000; left: 0; top: 0;width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.6);align-items: center; justify-content: center;}
        .modal-content {background-color: #fefefe; margin: auto; padding: 25px 30px; border: 1px solid #888; width: 90%; max-width: 800px;border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.3);}
        .results-list { max-height: 300px; overflow-y: auto; border: 1px solid #ddd; }
        .result-item { padding: 10px; cursor: pointer; border-bottom: 1px solid #eee; }
        .result-item:hover { background-color: #f0f0f0; }
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
            <h1>1° Emplacamento</h1>

             <?php if (!empty($message)): ?>
                <div class="alert <?= $message_type ?>">
                    <?= $message ?></div>
            <?php endif; ?>

            <?php 
                if (($action === 'edit') && !empty($emplacamento_para_editar)) :
            ?>

            <div class= "form-tollbar client-header" style="display: flex; justify-content: space-between;">
                <div class="client-info">
                    <strong>Nome:</strong> <?= htmlspecialchars($emplacamento_para_editar['EMP_CLI_NOME'] ?? '')?><br><br>
                    <strong>Veículo:</strong> <?= htmlspecialchars($emplacamento_para_editar['EMP_VEI_MODELO'] ?? '')?><br><br>
                    <strong>Valor:</strong> <?= htmlspecialchars($emplacamento_para_editar['EMP_VALOR_TOTAL'] ?? '')?>
                </div>

                <div class="client-actions">
                    <a href="cadastro_primeiro_emplacamento.php" class="btn btn-danger">Voltar para Consulta</a>
                </div>
            </div>
          
            
             <?php else :?>
                <div class="form-toolbar">
                    <?php if (temPermissao('acessar_config')): ?>
                        <a href="cadastro_primeiro_emplacamento.php?action=new" class="btn">Novo</a>
                    <?php endif; ?>        
                </div>
            <?php endif; ?>

            <div class="tabs">

                <div class="tab-buttons">
                    <button type="button" class="tab-button <?= $aba_ativa == 'consulta_emplacamento' ? 'active' : '' ?>" data-tab="consulta_emplacamento" <?php if ($aba_ativa == 'cadastro_rmplacamento'): ?> style="display: none;" <?php endif; ?>>Consulta</button>
                    <button type="button" class="tab-button <?= $aba_ativa == 'cadastro_emplacamento' ? 'active' : '' ?>" data-tab="cadastro_emplacamento" <?= $aba_ativa != 'cadastro_emplacamento' ? 'disabled' : '' ?>>Cadastro</button>
                    <button type="button" class="tab-button <?= $aba_ativa == 'configuracao_emplacamento' ? 'active' : '' ?>" data-tab="configuracao_emplacamento">Configuração do Cálculo</button>
                </div>

                <div class="tab-content">
                    <div id="consulta_emplacamento" class="tab-pane <?= $aba_ativa == 'consulta_emplacamento' ? 'active' : '' ?>">
                        <form method="GET" action="cadastro_primeiro_emplacamento.php">
                            <fieldset class="search-box">
                               <legend>Opções de Pesquisa</legend>
                               <div class="form-group">
                                    <label for="campo_pesquisa">Campo de Pesquisa:</label>
                                    <select id="campo_pesquisa" name="campo_pesquisa">
                                        <option value="codigo"<?= ($_GET['campo_pesquisa'] ?? '') === 'codigo' ? 'selected' : '' ?>>CÓDIGO</option>
                                        <option value="nome"<?= ($_GET['campo_pesquisa'] ?? '') === 'nome' ? 'selected' : '' ?>>CLIENTE</option>
                                        <option value="vei_modelo"<?= ($_GET['campo_pesquisa'] ?? '') === 'vei_modelo' ? 'selected' : '' ?>>MODELO VEÍCULO</option>
                                        <option value="data_cadastro"<?= ($_GET['campo_pesquisa'] ?? '') === 'data_cadastro' ? 'selected' : '' ?>>DATA CADASTRO</option>
                                    </select>
                                </div>
                                <div class="form-group" style="flex-grow: 2;">
                                    <label for="parametro_pesquisa">Parâmetro da Pesquisa:</label>
                                    <input type="text" id="parametro_pesquisa" name="valor_pesquisa" value="<?= htmlspecialchars($_GET['valor_pesquisa'] ?? '') ?>" placeholder="Use AAAA-MM-DD para data">
                                </div>
                                <button type="submit" class="btn btn-primary">Pesquisar</button>
                            </fieldset>
                        </form>

                        <div class="table-container">
                            <p class="table-note">Selecione para Consultar/Editar</p>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Código</th> 
                                        <th>Data Cadastro</th> 
                                        <th>Cliente</th> 
                                        <th>Modelo Veículo</th> 
                                        <th>Valor Total</th>
                                        <th style="text-align: center;">AÇÃO</th>
                                    </tr>
                                </thead>
                                <tbody>
                                     <?php if (!empty($emplacamentos)): ?>
                                        <?php foreach ($emplacamentos as $emplacamento): ?>
                                        
                                        <tr class="clickable-row" data-id="<?= $emplacamento['EMP_CODIGO'] ?>">
                                            <td><?=htmlspecialchars($emplacamento['EMP_CODIGO'] ?? '')?></td>
                                            <td><?= $emplacamento['EMP_DATA_CADASTRO'] ? date('d/m/Y', strtotime($emplacamento['EMP_DATA_CADASTRO'])) : '' ?></td>
                                            <td><?=htmlspecialchars($emplacamento['EMP_CLI_NOME'] ?? '')?></td>
                                            <td><?=htmlspecialchars($emplacamento['EMP_VEI_MODELO'] ?? '')?></td>
                                            <td>R$ <?= number_format($emplacamento['EMP_VALOR_TOTAL'] ?? 0, 2, ',', '.') ?></td>
                                            <td style="text-align: center; white-space: nowrap; display: flex; justify-content: center; gap: 15px;">
                                                <?php if (temPermissao('acessar_config')) : ?>
                                                    <a href="cadastro_primeiro_emplacamento.php?action=edit&id=<?= $emplacamento['EMP_CODIGO'] ?>" class="btn btn-primary">Editar</a>
                                                <?php endif; ?>
                                                <?php if (temPermissao('acessar_config')) : ?>
                                                    <a href="cadastro_primeiro_emplacamento.php?action=delete&id=<?= $emplacamento['EMP_CODIGO'] ?>" class="btn btn-danger btn-excluir-linha">Excluir</a>
                                                <?php endif; ?>
                                            </td>         
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="5" style="text-align: center;">Nenhum registro encontrado.</td></tr>
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

                    <div id="cadastro_emplacamento" class="tab-pane<?= $aba_ativa == 'cadastro_emplacamento' ? 'active' : '' ?>">
                         <form id="formEmplacamento" method="POST">
                             <input type="hidden" name="EMP_CODIGO" value="<?= htmlspecialchars($emplacamento_para_editar['EMP_CODIGO'] ?? '') ?>">
                             
                             <fieldset <?= ($action === 'view') ? 'disabled' : '' ?> style="border: none;">
                                 <fieldset class="form-section">
                                     <legend>Dados Gerais Para Cálculo do Primeiro Emplacamento</legend>

                                     <div class="form-row">

                                         <div class="form-group" style="max-width: 100px;">
                                             <label>Código</label>
                                             <input type="text" value="<?= htmlspecialchars($emplacamento_para_editar['EMP_CODIGO'] ?? 'NOVO') ?>" disabled>
                                         </div>

                                         <div class="form-group">
                                             <label>Data Cadastro</label>
                                             <input type="date" name="EMP_DATA_CADASTRO" value="<?= htmlspecialchars($emplacamento_para_editar['EMP_DATA_CADASTRO'] ?? date('Y-m-d')) ?>">
                                         </div>

                                         <div class="form-group">
                                             <label for="EMP_EMT_CODIGO">Tipo de Veículo</label>
                                             <select id="EMP_EMT_CODIGO" name="EMP_EMT_CODIGO" required>
                                                 <option value="">-- Selecione --</option>
                                                 <?php foreach ($tipos_veiculo as $tipo): ?>
                                                    <?php
                                                      $selected = (!empty($emplacamento_para_editar) && $emplacamento_para_editar['EMP_EMT_CODIGO'] == $tipo['EMT_CODIGO']) ? 'selected' : '';
                                                    ?>
                                                    <option value="<?= $tipo['EMT_CODIGO'] ?>"
                                                            data-aliquota="<?= $tipo['EMT_ALIQUOTA'] ?>"
                                                            data-dpvat="<?= $tipo['EMT_DPVAT_VALOR_BASE'] ?>"
                                                            data-juros-aliq="<?= $tipo['EMT_ALIQ_JUROS_IPVA'] ?>"
                                                            data-multa-aliq="<?= $tipo['EMT_ALIQ_MULTA_IPVA'] ?>"
                                                            data-dias-juros="<?= $tipo['EMT_DIAS_JUROS_MULTA'] ?>"
                                                            <?= $selected ?>>
                                                        <?= htmlspecialchars($tipo['EMT_DESCRICAO']) ?>
                                                    </option>
                                                 <?php endforeach; ?>
                                             </select>
                                             <input type="hidden" id="EMP_ALIQUOTA_hidden" name="EMP_ALIQUOTA" value="<?= htmlspecialchars($emplacamento_para_editar['EMP_ALIQUOTA'] ?? '') ?>">
                                         </div>

                                         <div class="form-group">
                                             <label>Alíquota IPVA (%)</label>
                                             <input type="text" id="aliquota_display" value="<?= htmlspecialchars($emplacamento_para_editar['EMP_ALIQUOTA'] ?? '') ?>" disabled>
                                         </div
                                         >
                                     </div>

                                     <div class="form-row">
                                         <div class="form-group">
                                             <label for="EMP_CLI_NOME">Cliente</label>
                                             <div class="input-with-button">
                                                 <input type="text" id="servicos_cliente_display" name="EMP_CLI_NOME" placeholder="Clique no botão para buscar..." value="<?= htmlspecialchars($emplacamento_para_editar['EMP_CLI_NOME'] ?? '') ?>" disabled>
                                                 <input type="hidden" id="servicos_cod_cliente_hidden" name="EMP_CPF_CNPJ" value="<?= htmlspecialchars($emplacamento_para_editar['EMP_CPF_CNPJ'] ?? '') ?>"> 
                                                 <button type="button" id="servicos_btnAbrirModalCliente" class="btn-lookup">...</button> 
                                             </div>
                                         </div>

                                         <div class="form-group">
                                             <label for="EMP_VEI_MODELO">Veiculo</label>
                                             <div class="input-with-button">
                                                 <input type="text" id="veiculo_display" name="EMP_VEI_MODELO" placeholder="Clique no botão para buscar..." value="<?= htmlspecialchars($emplacamento_para_editar['EMP_VEI_MODELO'] ?? '') ?>" disabled>
                                                 <input type="hidden" id="veiculo_id_hidden" name="EMP_VEI_CODIGO" value="<?= htmlspecialchars($emplacamento_para_editar['EMP_VEI_CODIGO'] ?? '') ?>">      
                                                 <button type="button" id="btnAbrirModalVeiculoCliente" class="btn-lookup">...</button>
                                             </div>
                                         </div> 

                                        <div class="form-group">
                                             <label>Placa</label>
                                             <input type="text" id="vei_placa" value="<?= htmlspecialchars($emplacamento_para_editar['EMP_VEI_PLACA'] ?? '') ?>" disabled>
                                        </div>

                                        <div class="form-group">
                                            <label>Renavan</label>
                                            <input type="text" id="vei_renavam" value="<?= htmlspecialchars($emplacamento_para_editar['EMP_VEI_RENAVAM'] ?? '') ?>" disabled>
                                        </div>
                                     </div>

                                 </fieldset>
                             </fieldset>
                             <fieldset <?= ($action === 'view') ? 'disabled' : '' ?> style="border: none;">
                                 <fieldset class="form-section">
                                     <legend>Valores</legend>

                                     <div class="form-row">
                                         <div class="form-group">
                                             <label for="EMP_VALOR_VEICULO">Valor do Veículo (NF)</label>
                                             <input type="number" class="mascara-moeda" id="EMP_VALOR_VEICULO" name="EMP_VALOR_VEICULO" value="<?= htmlspecialchars($emplacamento_para_editar['EMP_VALOR_VEICULO'] ?? '') ?>" required>
                                         </div>

                                         <div class="form-group">
                                             <label for="EMP_DATA_NOTA_FISCAL">Data da Nota Fiscal</label>
                                             <input type="date" id="EMP_DATA_NOTA_FISCAL" name="EMP_DATA_NOTA_FISCAL" value="<?= htmlspecialchars($emplacamento_para_editar['EMP_DATA_NOTA_FISCAL'] ?? '') ?>" required>
                                         </div>

                                         <div class="form-group">
                                             <label>Qtde. Meses (IPVA)</label>
                                             <input type="text" id="EMP_QTDE_MESES" name="EMP_QTDE_MESES" value="<?= htmlspecialchars($emplacamento_para_editar['EMP_QTDE_MESES'] ?? '') ?>">
                                         </div>                                         
                                     </div>

                                     <div class="form-row">
                                         <div class="form-group">
                                             <label>Valor IPVA</label>
                                             <input type="text" id="EMP_VALOR_IPVA" name="EMP_VALOR_IPVA" value="<?= htmlspecialchars($emplacamento_para_editar['EMP_VALOR_IPVA'] ?? '') ?>">
                                         </div>
                                         <div class="form-group">
                                             <label>Valor DPVAT</label>
                                             <input type="text" id="EMP_VALOR_DPVAT" name="EMP_VALOR_DPVAT" value="<?= htmlspecialchars($emplacamento_para_editar['EMP_VALOR_DPVAT'] ?? '') ?>">
                                         </div>
                                         <div class="form-group">
                                             <label>Valor Juros IPVA</label>
                                             <input type="text" id="EMP_JUROS_IPVA" name="EMP_JUROS_IPVA" value="<?= htmlspecialchars($emplacamento_para_editar['EMP_JUROS_IPVA'] ?? '') ?>">
                                         </div>
                                         <div class="form-group">
                                             <label>Valor Multa IPVA</label>
                                             <input type="text" id="EMP_MULTA_IPVA" name="EMP_MULTA_IPVA" value="<?= htmlspecialchars($emplacamento_para_editar['EMP_MULTA_IPVA'] ?? '') ?>">
                                         </div>
                                     </div>
                         
                                     <div class="form-row">

                                         <div class="form-group">
                                             <label for="EMP_VALOR_EMPLACAMENTO">Valor 1º Emplacamento</label>
                                             <input type="number" class="mascara-moeda" id="EMP_VALOR_EMPLACAMENTO" name="EMP_VALOR_EMPLACAMENTO" value="<?= htmlspecialchars($emplacamento_para_editar['EMP_VALOR_EMPLACAMENTO'] ?? '') ?>">
                                         </div>

                                         <div class="form-group">
                                             <label for="EMP_VALOR_PLACA">Valor Placa</label>
                                             <input type="number" class="mascara-moeda" id="EMP_VALOR_PLACA" name="EMP_VALOR_PLACA" value="<?= htmlspecialchars($emplacamento_para_editar['EMP_VALOR_PLACA'] ?? '') ?>">
                                         </div>

                                         <div class="form-group">
                                             <label for="EMP_VALOR_VISTORIA">Valor Vistoria</label>
                                             <input type="number" class="mascara-moeda" id="EMP_VALOR_VISTORIA" name="EMP_VALOR_VISTORIA" value="<?= htmlspecialchars($emplacamento_para_editar['EMP_VALOR_VISTORIA'] ?? '') ?>">
                                         </div>

                                         <div class="form-group">
                                             <label for="EMP_VALOR_DESPACHANTE">Valor Despachante</label>
                                             <input type="number" class="mascara-moeda" id="EMP_VALOR_DESPACHANTE" name="EMP_VALOR_DESPACHANTE" value="<?= htmlspecialchars($emplacamento_para_editar['EMP_VALOR_DESPACHANTE'] ?? '') ?>">
                                         </div>

                                     </div>

                                     <div class="resultado-calculo" style="text-align: right;">
                                         <p class="valor-final">Valor Total: <strong id="EMP_VALOR_TOTAL_display">R$ 0,00</strong></p>
                                         <input type="hidden" name="EMP_VALOR_TOTAL" id="EMP_VALOR_TOTAL_hidden" value="<?= htmlspecialchars($emplacamento_para_editar['EMP_VALOR_TOTAL'] ?? '') ?>">
                                     </div>

                                     <div class="form-row">
                                        <div class="form-group">
                                            <label for="observacao_livre">Observação</label>
                                            <textarea id="observacao_livre" name="EMP_OBSERVACAO" rows="4"<?=htmlspecialchars($emplacamento_para_editar['EMP_OBSERVACAO'] ?? '')?>></textarea>
                                        </div>
                                     </div>
                                     
                                 </fieldset>
                             </fieldset>

                            <div class="form-footer">
                                <?php if ($action !== 'view'): ?>
                                    <button type="submit" class="btn btn-primary">Salvar</button> 
                                <?php endif; ?>
                                <a href="cadastro_primeiro_emplacamento.php" class="btn btn-danger">Cancelar</a>
                            </div>
                        </form>                                                       
                    </div>

                    <div id="configuracao_emplacamento" class="tab-pane <?= $aba_ativa == 'configuracao_emplacamento' ? 'active' : '' ?>">
                        <p>Clique em uma linha para selecionar e depois utilize os botões no rodapé.</p>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Código</th> <th>Descrição</th> <th>Alíquota IPVA (%)</th> <th>DPVAT Base (R$)</th> <th>Juros IPVA (%)</th> <th>Multa IPVA (%)</th> <th>Prazo Juros (dias)</th>
                                    </tr>
                                </thead>
                                <tbody id="tabela-config-tipos">
                                    <?php foreach ($config_tipos as $tipo): ?>
                                        <tr class="clickable-row" data-id="<?= $tipo['EMT_CODIGO'] ?>"
                                            data-json="<?= htmlspecialchars(json_encode($tipo), ENT_QUOTES, 'UTF-8') ?>">
                                            <td><?= $tipo['EMT_CODIGO'] ?></td>
                                            <td><?= htmlspecialchars($tipo['EMT_DESCRICAO']) ?></td>
                                            <td><?= number_format($tipo['EMT_ALIQUOTA'], 2, ',', '.') ?>%</td>
                                            <td>R$ <?= number_format($tipo['EMT_DPVAT_VALOR_BASE'], 2, ',', '.') ?></td>
                                            <td><?= number_format($tipo['EMT_ALIQ_JUROS_IPVA'], 2, ',', '.') ?>%</td>
                                            <td><?= number_format($tipo['EMT_ALIQ_MULTA_IPVA'], 2, ',', '.') ?>%</td>
                                            <td><?= $tipo['EMT_DIAS_JUROS_MULTA'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                         <div class="form-footer">
                            <button type="button" id="btnConfigNovo" class="btn btn-primary">Novo</button>
                            <button type="button" id="btnConfigEditar" class="btn disabled">Editar</button>
                            <button type="button" id="btnConfigExcluir" class="btn btn-danger disabled">Excluir</button>
                        </div>
                    </div>
                </div>  
            </div>     
        </main>
    </div>

    <div id="modalTipoEmplacamento" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2 id="modalTipoEmplacamentoTitulo">Adicionar Novo Tipo</h2>
            <form id="formTipoEmplacamento" method="POST" action="api_salvar_tipo_emplacamento.php">
                <input type="hidden" name="EMT_CODIGO" id="EMT_CODIGO">
                <div class="form-row">
                    <div class="form-group">
                        <label for="EMT_DESCRICAO">Descrição</label>
                        <input type="text" id="EMT_DESCRICAO" name="EMT_DESCRICAO" placeholder="Ex: Motos, Automóveis" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="EMT_ALIQUOTA">Alíquota IPVA (%)</label>
                        <input type="text" id="EMT_ALIQUOTA" name="EMT_ALIQUOTA" class="mascara-moeda" placeholder="Ex: 2,50" required>
                    </div>
                    <div class="form-group">
                        <label for="EMT_DPVAT_VALOR_BASE">DPVAT Valor Base (R$)</label>
                        <input type="text" id="EMT_DPVAT_VALOR_BASE" name="EMT_DPVAT_VALOR_BASE" class="mascara-moeda" placeholder="Ex: 12,94" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="EMT_ALIQ_JUROS_IPVA">Alíquota Juros IPVA (%)</label>
                        <input type="text" id="EMT_ALIQ_JUROS_IPVA" name="EMT_ALIQ_JUROS_IPVA" class="mascara-moeda" placeholder="Ex: 3,50" required>
                    </div>
                    <div class="form-group">
                        <label for="EMT_ALIQ_MULTA_IPVA">Alíquota Multa IPVA (%)</label>
                        <input type="text" id="EMT_ALIQ_MULTA_IPVA" name="EMT_ALIQ_MULTA_IPVA" class="mascara-moeda" placeholder="Ex: 15,00" required>
                    </div>
                    <div class="form-group">
                        <label for="EMT_DIAS_JUROS_MULTA">Prazo Juros/Multa (dias)</label>
                        <input type="number" id="EMT_DIAS_JUROS_MULTA" name="EMT_DIAS_JUROS_MULTA" placeholder="Ex: 15" required>
                    </div>
                </div>
                <div class="form-footer">
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </div>
            </form>
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
                <h2>Buscar Veículo</h2>
                <input type="text" id="buscaVeiculoClienteInput" placeholder="Digite para buscar pela marca ou modelo...">
                <div id="listaResultadosVeiculoCliente" class="results-list"></div>
            </div>
        </div>

     <script src="../js/script_menu.js"></script>
     <script src="../js/script_modal.js"></script>
     <script src="../js/script_mascara.js"></script> 
     <script>
         document.addEventListener('DOMContentLoaded', function() {
            
            if (document.getElementById('formEmplacamento')) {
                
    
                const form = document.getElementById('formEmplacamento');
                const tipoVeiculoSelect = document.getElementById('EMP_EMT_CODIGO');
                const aliquotaDisplay = document.getElementById('aliquota_display');
                const aliquotaHidden = document.getElementById('EMP_ALIQUOTA_hidden');
                
                const valorVeiculoInput = document.getElementById('EMP_VALOR_VEICULO');
                const dataNfInput = document.getElementById('EMP_DATA_NOTA_FISCAL');
                
                const qtdeMesesInput = document.getElementById('EMP_QTDE_MESES');
                const valorIpvaInput = document.getElementById('EMP_VALOR_IPVA');
                const valorDpvatInput = document.getElementById('EMP_VALOR_DPVAT');
                const jurosIpvaInput = document.getElementById('EMP_JUROS_IPVA');
                const multaIpvaInput = document.getElementById('EMP_MULTA_IPVA');
                
                const valorEmplacamentoInput = document.getElementById('EMP_VALOR_EMPLACAMENTO');
                const valorPlacaInput = document.getElementById('EMP_VALOR_PLACA');
                const valorVistoriaInput = document.getElementById('EMP_VALOR_VISTORIA');
                const valorDespachanteInput = document.getElementById('EMP_VALOR_DESPACHANTE');
                
                const valorTotalDisplay = document.getElementById('EMP_VALOR_TOTAL_display');
                const valorTotalHiddenInput = document.getElementById('EMP_VALOR_TOTAL_hidden');
                const calculoState = {
                    aliquotaIpva: 0,
                    valorDpvatBase: 0,
                    aliquotaJuros: 0,
                    aliquotaMulta: 0,
                    diasJurosMulta: 0
                };

                function atualizarCamposTipoVeiculo() {
                    const selectedOption = tipoVeiculoSelect.options[tipoVeiculoSelect.selectedIndex];
                    
                    if (!selectedOption || selectedOption.value === "") {
                        aliquotaDisplay.value = "";
                        aliquotaHidden.value = "";
                        valorDpvatInput.value = "";
                        calculoState.aliquotaIpva = 0;
                        calculoState.valorDpvatBase = 0;
                        calculoState.aliquotaJuros = 0;
                        calculoState.aliquotaMulta = 0;
                        calculoState.diasJurosMulta = 0;
                    } else {
                        const aliquota = parseFloat(selectedOption.dataset.aliquota || 0);
                        const dpvat = parseFloat(selectedOption.dataset.dpvat || 0);

                        calculoState.aliquotaIpva = aliquota;
                        calculoState.valorDpvatBase = dpvat;
                        calculoState.aliquotaJuros = parseFloat(selectedOption.dataset.jurosAliq || 0);
                        calculoState.aliquotaMulta = parseFloat(selectedOption.dataset.multaAliq || 0);
                        calculoState.diasJurosMulta = parseInt(selectedOption.dataset.diasJuros || 0);

                        aliquotaDisplay.value = aliquota.toFixed(2);
                        aliquotaHidden.value = aliquota.toFixed(2);
                        valorDpvatInput.value = dpvat.toFixed(2);
                    }
                    
                  
                    calcularTudo();
                }

                
                function calcularTudo() {
                    const valorVeiculo = parseFloat(valorVeiculoInput.value) || 0;
                    const dataNfString = dataNfInput.value;

                    let qtdeMeses = 0;
                    let valorIpvaProporcional = 0;
                    let valorJuros = 0;
                    let valorMulta = 0;

                    if (valorVeiculo > 0 && dataNfString && calculoState.aliquotaIpva > 0) {
                        try {
                            // --- Cálculo IPVA Proporcional ---
                            const dataNf = new Date(dataNfString + 'T00:00:00'); 
                            const mesNf = dataNf.getMonth() + 1; 
                            
                            qtdeMeses = 12 - (mesNf - 1);
                            const valorIpvaAnual = (valorVeiculo * calculoState.aliquotaIpva) / 100;
                            valorIpvaProporcional = (valorIpvaAnual / 12) * qtdeMeses;

                            // --- Cálculo Juros/Multa  ---
                            const hoje = new Date();
                            hoje.setHours(0, 0, 0, 0); 
                            dataNf.setHours(0, 0, 0, 0);

                            const diffTime = hoje - dataNf;
                            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                            
                            if (calculoState.diasJurosMulta > 0 && diffDays > calculoState.diasJurosMulta) {
                                valorJuros = (valorIpvaProporcional * calculoState.aliquotaJuros) / 100;
                                valorMulta = (valorIpvaProporcional * calculoState.aliquotaMulta) / 100;
                            }
                        } catch(e) {
                            console.error("Erro ao calcular datas:", e);
                        }
                    }
                    
                    // Atualiza os campos de IPVA
                    qtdeMesesInput.value = qtdeMeses;
                    valorIpvaInput.value = valorIpvaProporcional.toFixed(2);
                    jurosIpvaInput.value = valorJuros.toFixed(2);
                    multaIpvaInput.value = valorMulta.toFixed(2);
                    
                    // --- Cálculo do Valor Total ---
                    const valorDpvat = parseFloat(valorDpvatInput.value) || 0; 
                    const valorEmplacamento = parseFloat(valorEmplacamentoInput.value) || 0;
                    const valorPlaca = parseFloat(valorPlacaInput.value) || 0;
                    const valorVistoria = parseFloat(valorVistoriaInput.value) || 0;
                    const valorDespachante = parseFloat(valorDespachanteInput.value) || 0;

                    const total = valorIpvaProporcional + valorDpvat + valorJuros + valorMulta + 
                                  valorEmplacamento + valorPlaca + valorVistoria + valorDespachante;

                    valorTotalDisplay.textContent = total.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
                    valorTotalHiddenInput.value = total.toFixed(2);
                }

                tipoVeiculoSelect.addEventListener('change', atualizarCamposTipoVeiculo);
                
                const camposDeValor = [
                    valorVeiculoInput, dataNfInput, valorEmplacamentoInput, 
                    valorPlacaInput, valorVistoriaInput, valorDespachanteInput
                ];
                
                camposDeValor.forEach(input => {
                    input.addEventListener('input', calcularTudo);
                });

                atualizarCamposTipoVeiculo();
            }
         });
     </script>
     <script>
    document.addEventListener('DOMContentLoaded', function() {
        
        // --- GERENCIAMENTO DA ABA DE CONFIGURAÇÃO ---
        const tabelaConfig = document.getElementById('tabela-config-tipos');
        const btnConfigNovo = document.getElementById('btnConfigNovo');
        const btnConfigEditar = document.getElementById('btnConfigEditar');
        const btnConfigExcluir = document.getElementById('btnConfigExcluir');
        
        const modalConfig = document.getElementById('modalTipoEmplacamento');
        const modalConfigTitulo = document.getElementById('modalTipoEmplacamentoTitulo');
        const modalConfigForm = document.getElementById('formTipoEmplacamento');
        
        const confirmModal = document.getElementById('customConfirmModal');
        const confirmBtn = document.getElementById('confirmBtn');
        const cancelBtn = document.getElementById('cancelBtn');

        let linhaConfigSelecionada = null;
        let idConfigSelecionado = null;

        if (tabelaConfig) {
            tabelaConfig.addEventListener('click', function(e) {
                const linha = e.target.closest('tr.clickable-row');
                if (!linha) return;

                if (linhaConfigSelecionada) {
                    linhaConfigSelecionada.classList.remove('selected');
                }

                if (linhaConfigSelecionada === linha) {
                    // Desmarcar
                    linhaConfigSelecionada = null;
                    idConfigSelecionado = null;
                    btnConfigEditar.classList.add('disabled');
                    btnConfigExcluir.classList.add('disabled');
                } else {
                    // Marcar
                    linhaConfigSelecionada = linha;
                    linhaConfigSelecionada.classList.add('selected');
                    idConfigSelecionado = linha.dataset.id;
                    btnConfigEditar.classList.remove('disabled');
                    btnConfigExcluir.classList.remove('disabled');
                }
            });

            btnConfigNovo.addEventListener('click', function() {
                modalConfigForm.reset(); 
                document.getElementById('EMT_CODIGO').value = ''; 
                modalConfigTitulo.textContent = 'Adicionar Novo Tipo';
                modalConfig.style.display = 'flex';
            });

 
            btnConfigEditar.addEventListener('click', function() {
                if (!linhaConfigSelecionada) return;

                const dados = JSON.parse(linhaConfigSelecionada.dataset.json);
                
                
                document.getElementById('EMT_CODIGO').value = dados.EMT_CODIGO;
                document.getElementById('EMT_DESCRICAO').value = dados.EMT_DESCRICAO;
                document.getElementById('EMT_ALIQUOTA').value = (dados.EMT_ALIQUOTA || '0').replace('.', ',');
                document.getElementById('EMT_DPVAT_VALOR_BASE').value = (dados.EMT_DPVAT_VALOR_BASE || '0').replace('.', ',');
                document.getElementById('EMT_ALIQ_JUROS_IPVA').value = (dados.EMT_ALIQ_JUROS_IPVA || '0').replace('.', ',');
                document.getElementById('EMT_ALIQ_MULTA_IPVA').value = (dados.EMT_ALIQ_MULTA_IPVA || '0').replace('.', ',');
                document.getElementById('EMT_DIAS_JUROS_MULTA').value = dados.EMT_DIAS_JUROS_MULTA;
                
                modalConfigTitulo.textContent = 'Editar Tipo';
                modalConfig.style.display = 'flex';
            });

    
            btnConfigExcluir.addEventListener('click', function() {
                if (!idConfigSelecionado) return;
                
          
                confirmBtn.onclick = function() {
                    window.location.href = `api_deletar_tipo_emplacamento.php?id=${idConfigSelecionado}`;
                };
                
                confirmModal.style.display = 'block';
            });

            modalConfig.querySelector('.close-button').addEventListener('click', function() {
                modalConfig.style.display = 'none';
            });
            
            cancelBtn.addEventListener('click', function() {
                confirmModal.style.display = 'none';
            });
        }

    });
    </script>
    <script>
        
document.addEventListener('DOMContentLoaded', function() {

    // ---  SISTEMA DE ABAS (TABS) ---
    const allTabContainers = document.querySelectorAll('.tabs');
    allTabContainers.forEach(container => {
        const tabButtons = container.querySelectorAll(':scope > .tab-buttons .tab-button');
        const tabPanes = container.querySelectorAll(':scope > .tab-content > .tab-pane, :scope > .tab-pane');
        tabButtons.forEach(button => {
            button.addEventListener('click', () => {
                if (button.disabled) return;
                const targetId = button.dataset.tab;
                tabButtons.forEach(btn => btn.classList.remove('active'));
                tabPanes.forEach(pane => pane.classList.remove('active'));
                button.classList.add('active');
                const targetPane = document.getElementById(targetId);
                if (targetPane) {
                    targetPane.classList.add('active');
                }
            });
        });
    });

    // ---  CONTROLE DOS BOTÕES DA BARRA DE FERRAMENTAS ---
    const btnEditar = document.getElementById('btnEditar');
    const btnVisualizar = document.getElementById('btnVisualizar');
    const btnExcluir = document.getElementById('btnExcluir');
    const btnVisualizarFicha = document.getElementById('btnVisualizarFicha');
  
    
    let selectedId = null;
    const currentPage = window.location.pathname.split('/').pop();

    // Função para habilitar/desabilitar todos os botões de uma vez
    function setButtonsState(enabled) {
        if (enabled) {
            if (btnEditar) btnEditar.classList.remove('disabled');
            if (btnVisualizar) btnVisualizar.classList.remove('disabled');
            if (btnExcluir) btnExcluir.classList.remove('disabled');
            if (btnVisualizarFicha) btnVisualizarFicha.classList.remove('disabled');
          
        } else {
            if (btnEditar) btnEditar.classList.add('disabled');
            if (btnVisualizar) btnVisualizar.classList.add('disabled');
            if (btnExcluir) btnExcluir.classList.add('disabled');
            if (btnVisualizarFicha) btnVisualizarFicha.classList.add('disabled');
           
        }
    }

    // Evento de clique na tabela
    document.querySelectorAll('#consulta_emplacamento .data-table tbody').forEach(tbody => {
        tbody.addEventListener('click', function(event) {
            const row = event.target.closest('tr.clickable-row');
            if (!row) return;

            const isSelected = row.classList.contains('selected');
            document.querySelectorAll('tr.clickable-row').forEach(r => r.classList.remove('selected'));

            if (isSelected) {
                selectedId = null;
                setButtonsState(false); 
            } else {
                row.classList.add('selected');
                selectedId = row.dataset.id;
                setButtonsState(true); 
            }
        });
    });

    // --- AÇÕES DOS BOTÕES ---
    if (btnEditar) {
        btnEditar.addEventListener('click', function(e) {
            if (selectedId) {
                this.href = `${currentPage}?action=edit&id=${selectedId}`;
            } else {
                e.preventDefault();
            }
        });
    }

    if (btnVisualizar) {
        btnVisualizar.addEventListener('click', function(e) {
            if (selectedId) {
                this.href = `${currentPage}?action=view&id=${selectedId}`;
            } else {
                e.preventDefault();
            }
        });
    }

   if (btnExcluir) {
        const customConfirmModal = document.getElementById('customConfirmModal');

        btnExcluir.addEventListener('click', function(event) {
            if (this.classList.contains('disabled') || !selectedId) {
                event.preventDefault();
                return;
            }

            this.href = `${currentPage}?action=delete&id=${selectedId}`;
            
            if (customConfirmModal) {
                event.preventDefault();
                
                const confirmBtn = document.getElementById('confirmBtn');
                const cancelBtn = document.getElementById('cancelBtn');
                const deleteUrl = this.href;

                customConfirmModal.style.display = 'block';

                confirmBtn.onclick = () => {
                    if (deleteUrl) {
                        window.location.href = deleteUrl;
                    }
                };

                cancelBtn.onclick = () => {
                    customConfirmModal.style.display = 'none';
                };

                window.onclick = (e) => {
                    if (e.target == customConfirmModal) {
                        customConfirmModal.style.display = 'none';
                    }
                };
            } 
            else { 
                if (!confirm('Tem certeza que deseja excluir este registro? Esta ação não pode ser desfeita.')) {
                    event.preventDefault();
                }
            }
        });
    }

    

  if (btnVisualizarFicha) {
        btnVisualizarFicha.addEventListener('click', function(e) {
            e.preventDefault();
     
            if (!this.classList.contains('disabled') && selectedId) {
                window.location.href = `ficha_financeira.php?cpf_cnpj=${encodeURIComponent(selectedId)}`;
            }
        });
    }

    const btnNovoLancamento = document.getElementById('btnNovoLancamento');
    const modalNovoLancamento = document.getElementById('modalNovoLancamento');
    const closeModalBtn = document.getElementById('closeModalNovoLancamento');
    const cancelModalBtn = document.getElementById('btnCancelarNovoLancamento');

    if (btnNovoLancamento) {
        btnNovoLancamento.addEventListener('click', (e) => {
            e.preventDefault();
            modalNovoLancamento.style.display = 'flex';
        });
    }

    if (closeModalBtn) {
        closeModalBtn.addEventListener('click', () => {
            modalNovoLancamento.style.display = 'none';
        });
    }

    if (cancelModalBtn) {
        cancelModalBtn.addEventListener('click', () => {
            modalNovoLancamento.style.display = 'none';
        });
    }
    window.addEventListener('click', (e) => {
        if (e.target == modalNovoLancamento) {
            modalNovoLancamento.style.display = 'none';
        }
    });
});
    </script>
     
</body>
</html>