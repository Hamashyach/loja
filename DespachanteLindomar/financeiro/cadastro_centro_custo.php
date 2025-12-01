<?php 
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../index.php");
    exit;
}

$usuario_nome = htmlspecialchars($_SESSION['username'] ?? 'Usuário');
$usuario_login = htmlspecialchars($_SESSION['login'] ?? '');
require_once '../verificar_permissao.php';
protegerPagina('acessar_financeiro'); 
require_once '../config.php';

$aba_ativa = 'consulta_centro_custo';
$centro_custo_para_editar = [];
$centros_de_custo = []; 
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$message = '';
$message_type = '';

if (!empty($_GET['status'])) {
    switch ($_GET['status']) {
        case 'success':
            $message = '<strong>Sucesso!</strong> Centro de Custo salvo com sucesso.';
            $message_type = 'success';
            break;
        case 'deleted':
            $message = 'Centro de Custo excluído com sucesso.';
            $message_type = 'success';
            break;
        case 'error':
            $errorMessage = $_GET['msg'] ?? 'Ocorreu um problema não especificado.';
            $message = '<strong>Erro!</strong> ' . htmlspecialchars(urldecode($errorMessage));
            $message_type = 'error';
            break;
    }
}
$action = $_GET['action'] ?? null;
$id = $_GET['id'] ?? null;

if ($action === 'new') {
    $aba_ativa = 'cadastro_centro_custo';
}

if (($action === 'edit' || $action === 'view') && $id) {
    $aba_ativa = 'cadastro_centro_custo';
    
    $stmt = $conn->prepare("SELECT * FROM CENTRO_CUSTO WHERE CEC_CODIGO = ?"); 
    $stmt->execute([$id]);
    $centro_custo_para_editar = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($action === 'delete' && $id) {
    try {
        
        $stmt = $conn->prepare("DELETE FROM CENTRO_CUSTO WHERE CEC_CODIGO = ?"); 
        $stmt->execute([$id]);
        header("Location: cadastro_centro_custo.php?status=deleted");
        exit;
    } catch (PDOException $e) {
        
        if ($e->getCode() == '23000') {
             header("Location: cadastro_centro_custo.php?status=error&msg=" . urlencode("Não é possível excluir. Este Centro de Custo já está sendo usado em lançamentos financeiros."));
        } else {
             header("Location: cadastro_centro_custo.php?status=error&msg=" . urlencode($e->getMessage()));
        }
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $CODIGO = $_POST['CEC_CODIGO'] ?: null;   
    $dados = [
        'CEC_DESCRICAO' => $_POST['CEC_DESCRICAO'] ?? null 
    ];

    foreach ($dados as $chave => $valor) {
        if ($valor === '') $dados[$chave] = null;
    }

    try {
        if (empty($CODIGO)) {
            
            $colunas = implode(', ', array_keys($dados));
            $placeholders = implode(', ', array_fill(0, count($dados), '?'));
            
            $sql = "INSERT INTO centro_custo ($colunas) VALUES ($placeholders)"; 
            $stmt = $conn->prepare($sql);
            $stmt->execute(array_values($dados));
        } else {
            
            $set_sql = [];
            foreach (array_keys($dados) as $coluna) {
                $set_sql[] = "$coluna = ?";
            }
            
            $sql = "UPDATE centro_custo SET " . implode(', ', $set_sql) . " WHERE CEC_CODIGO = ?"; 
            $valores = array_values($dados);
            $valores[] = $CODIGO;
            $stmt = $conn->prepare($sql);
            $stmt->execute($valores);
        }
        header("Location: cadastro_centro_custo.php?status=success");
        exit;
    } catch (PDOException $e) {
        die("Erro ao salvar Centro de Custo: " . $e->getMessage());
    }
}

$sql_base = "SELECT * FROM CENTRO_CUSTO"; 
$sql_count_base = "SELECT COUNT(*) FROM CENTRO_CUSTO"; 
$params = [];
$where_clauses = [];

if (!empty($_GET['valor_pesquisa'])) {
    $campo = $_GET['campo_pesquisa'];
    $valor = '%' . $_GET['valor_pesquisa'] . '%'; 

    
    if ($campo == 'codigo') $where_clauses[] = "CEC_CODIGO LIKE ?"; 
    elseif ($campo == 'descricao') $where_clauses[] = "CEC_DESCRICAO LIKE ?";

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


$sql_final = $sql_base ." ORDER BY CEC_CODIGO LIMIT $limit OFFSET $offset";
$stmt = $conn->prepare($sql_final);
$stmt->execute($params);
$centros_de_custo = $stmt->fetchAll(PDO::FETCH_ASSOC); 

$query_string = http_build_query(array_merge($_GET, ['page' => '']));

$link_primeiro = "cadastro_centro_custo.php?{$query_string}1";
$link_anterior = ($page > 1) ? "cadastro_centro_custo.php?{$query_string}" . ($page - 1) : "#";
$link_proximo = ($page < $total_pages) ? "cadastro_centro_custo.php?{$query_string}" . ($page + 1) : "#";
$link_ultimo = "cadastro_centro_custo.php?{$query_string}" . $total_pages;

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro de Centro de Custo - Lindomar Despachante</title> <link rel="stylesheet" href="../css/style_sistema.css">
    <style>
       
       .alert { padding: 15px; margin-bottom: 20px; border-radius: 5px; border: 1px solid transparent; }
       .alert.success { color: #155724; background-color: #d4edda; border-color: #c3e6cb; }
       .alert.error { color: #721c24; background-color: #f8d7da; border-color: #f5c6cb; }
       .modal-confirm { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0, 0, 0, 0.6); }
       .modal-confirm-content { background-color: #fefefe; margin: 15% auto; padding: 25px 30px; border: 1px solid #888; width: 90%; max-width: 400px; border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); text-align: center; }
       .modal-confirm-content p { font-size: 1.1em; margin: 0 0 20px 0; color: #333; }
       .modal-confirm-buttons { display: flex; justify-content: center; gap: 15px; }
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
            <h1>Cadastro de Centro de Custo</h1> <?php if (!empty($message)): ?>
                <div class="alert <?= $message_type ?>">
                    <?= $message ?>
                </div>
            <?php endif; ?>

            <div class="form-toolbar">
                <div class="form-toolbar">
                    <a href="cadastro_centro_custo.php?action=new" class="btn">Novo</a> <a href="" class="btn btn-primary disabled" id="btnEditar">Editar</a>
                    <a href="" class="btn btn-primary disabled" id="btnVisualizar">Visualizar</a>
                    <a href="" class="btn btn-primary disabled" id="btnExcluir">Excluir</a>
                    <a href="<?= $link_primeiro ?>" class="btn">Primeiro</a>
                    <a href="<?= $link_anterior ?>" class="btn <?= $page <= 1 ? 'disabled' : '' ?>">Anterior</a>
                    <a href="<?= $link_proximo ?>" class="btn <?= $page >= $total_pages ? 'disabled' : '' ?>">Próximo</a>
                    <a href="<?= $link_ultimo ?>" class="btn">Último</a>
                </div>
            </div>

             <div class="tabs">
                <div class="tab-buttons">
                    <button type="button" class="tab-button <?=$aba_ativa == 'consulta_centro_custo' ? 'active' : '' ?>" data-tab="consulta_centro_custo">Consulta</button>
                    <button type="button" class="tab-button <?= $aba_ativa == 'cadastro_centro_custo' ? 'active' : '' ?>" data-tab="cadastro_centro_custo" <?= $aba_ativa != 'cadastro_centro_custo' ? 'disabled' : '' ?>>Cadastro</button>
                </div>

                <div class="tab-content"> 
                <div id="consulta_centro_custo" class="tab-pane <?= $aba_ativa == 'consulta_centro_custo' ? 'active' : '' ?>" style="padding: 20px;">
                    <form method="GET" action="cadastro_centro_custo.php"> <fieldset class="search-box">
                            <legend>Opções de Pesquisa</legend>
                            <div class="form-group">
                                <label for="campo_pesquisa">Campo de Pesquisa:</label>
                                <select id="campo_pesquisa" name="campo_pesquisa">
                                    <option value="descricao" <?= ($_GET['campo_pesquisa'] ?? '') === 'descricao' ? 'selected' : '' ?>>DESCRIÇÃO</option>
                                    <option value="codigo" <?= ($_GET['campo_pesquisa'] ?? '') === 'codigo' ? 'selected' : '' ?>>CÓDIGO</option>
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
                                    <th>DESCRIÇÃO</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($centros_de_custo)): ?> <?php foreach ($centros_de_custo as $item): ?> <tr class="clickable-row" data-id="<?= $item['CEC_CODIGO'] ?>">
                                            <td><?= htmlspecialchars($item['CEC_CODIGO']??'') ?></td>
                                            <td><?= htmlspecialchars($item['CEC_DESCRICAO']??'') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="2">Nenhum registro encontrado.</td> </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="pagination">
                        <span class="span_paginacao">Página <?= $page ?> de <?= $total_pages ?></span>
                    </div>
                </div>           
                
                <div id="cadastro_centro_custo" class="tab-pane <?= $aba_ativa == 'cadastro_centro_custo' ? 'active' : '' ?>"> <form id="formCentroCusto" method="POST" action="cadastro_centro_custo.php"> <input type="hidden" name="CEC_CODIGO" value="<?= htmlspecialchars($centro_custo_para_editar['CEC_CODIGO'] ?? '') ?>">
                        
                        <fieldset <?= ($action === 'view') ? 'disabled' : '' ?> style="border: none;">
                            <fieldset class="form-section">
                                <legend>Dados Gerais</legend>
                                <div class="form-row">
                                    <div class="form-group" style="max-width: 100px;">
                                        <label for="CEC_CODIGO">Código</label>
                                        <input type="text" id="CEC_CODIGO" value="<?= htmlspecialchars($centro_custo_para_editar['CEC_CODIGO'] ?? 'NOVO') ?>" readonly>
                                    </div>

                                    <div class="form-group">
                                        <label for="CEC_DESCRICAO">Descrição</label>
                                        <input type="text" id="CEC_DESCRICAO" name="CEC_DESCRICAO" value="<?= htmlspecialchars($centro_custo_para_editar['CEC_DESCRICAO'] ?? '') ?>" required>
                                    </div>
                                </div>                                   
                            </fieldset>
                        </fieldset>
                        <div class="form-footer">
                            <?php if ($action !== 'view'): ?>
                                <button type="submit" class="btn btn-primary">Salvar</button> 
                            <?php endif; ?>
                            <a href="cadastro_centro_custo.php" class="btn btn-danger">Cancelar</a> </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script src="../js/script.js"></script>
    <script src="../js/script_menu.js"></script>
    <script src="../js/script_modal.js"></script>
    <script src="../js/script_mascara.js"></script>
    <script src="../js/script_main.js"></script> <div id="customConfirmModal" class="modal-confirm" style="display: none;">
        <div class="modal-confirm-content">
            <p id="confirmMessage">Tem certeza que deseja excluir este registro? Esta ação não pode ser desfeita.</p>
            <div class="modal-confirm-buttons">
                <button id="cancelBtn" class="btn btn-secondary">Cancelar</button>
                <button id="confirmBtn" class="btn btn-danger">Excluir</button>
            </div>
        </div>
    </div>
</body>
</html>