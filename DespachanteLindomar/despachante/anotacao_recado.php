<?php 
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../index.php");
    exit;
}

$usuario_nome = htmlspecialchars($_SESSION['username'] ?? 'Usuário');
$usuario_login = htmlspecialchars($_SESSION['login'] ?? '');

require_once '../verificar_permissao.php';
protegerPagina('gerenciar_anotacoes');

require_once '../config.php'; 


// INICIALIZAÇÃO E PAGINAÇÃO
$aba_ativa = 'consulta_anotacao';
$anotacoes_para_editar = [];
$anotacoes = [];
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
if (!empty($_GET['status'])) {
    switch ($_GET['status']) {
        case 'success':
            $message = '<strong>Sucesso!</strong> Anotação salvo com sucesso.';
            $message_type = 'success';
            break;
        case 'deleted':
            $message = 'Anotação excluído com sucesso.';
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
    $aba_ativa = 'cadastro_anotacao';
}

if (($action === 'edit' || $action === 'view') && $id) {
    $aba_ativa = 'cadastro_anotacao';
    $stmt = $conn->prepare("SELECT * FROM ANOTACAO WHERE ANO_CODIGO = ?");
    $stmt->execute([$id]);
    $anotacoes_para_editar = $stmt->fetch(PDO::FETCH_ASSOC);
    
}

if ($action === 'delete' && $id) {
    try {
        $stmt = $conn->prepare("DELETE FROM ANOTACAO WHERE ANO_CODIGO = ?");
        $stmt->execute([$id]);
        header("Location: anotacao_recado.php?status=deleted");
        exit;
    } catch (PDOException $e) {
        header("Location: anotacao_recado.php?status=error&msg=" . urlencode($e->getMessage()));
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $CODIGO = $_POST['ANO_CODIGO'] ?: null;   
    $dados = [
        'ANO_DATA' => $_POST['ANO_DATA'] ?? null,
        'ANO_TITULO' => $_POST['ANO_TITULO'] ?? null,
        'ANO_DESCRICAO' => $_POST['ANO_DESCRICAO'] ?? null
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
            $sql = "INSERT INTO ANOTACAO ($colunas) VALUES ($placeholders)";
            $stmt = $conn->prepare($sql);
            $stmt->execute(array_values($dados));
        } else {
            $set_sql = [];
            foreach (array_keys($dados) as $coluna) {
                $set_sql[] = "$coluna = ?";
            }
            $sql = "UPDATE ANOTACAO SET " . implode(', ', $set_sql) . " WHERE ANO_CODIGO = ?";
            $valores = array_values($dados);
            $valores[] = $CODIGO;
            $stmt = $conn->prepare($sql);
            $stmt->execute($valores);
        }
        header("Location: anotacao_recado.php?status=success");
        exit;
    } catch (PDOException $e) {
        die("Erro ao salvar anotação: " . $e->getMessage());
    }
}

// --- LÓGICA DE BUSCA E CONSULTA ---
$sql_base = "SELECT * FROM ANOTACAO";
$sql_count_base = "SELECT COUNT(*) FROM ANOTACAO";
$params = [];
$where_clauses = [];

if (!empty($_GET['valor_pesquisa'])) {
    $campo = $_GET['campo_pesquisa'];
    $valor = '%' . $_GET['valor_pesquisa'] . '%';

    if ($campo == 'codigo') $where_clauses[] = "ANO_CODIGO LIKE ?";
    elseif ($campo == 'titulo') $where_clauses[] = "ANO_TITULO LIKE ?";
    elseif ($campo == 'data') $where_clauses[] = "ANO_DATA LIKE ?";
    
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

$sql_final = $sql_base ." ORDER BY ANO_CODIGO LIMIT $limit OFFSET $offset";
$stmt = $conn->prepare($sql_final);
$stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);

for ($i = 0; $i < count($params); $i++) {
    $stmt->bindValue($i + 1, $params[$i]);
}
$stmt->execute($params);
$anotacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Lógica  de navegação
$query_string = http_build_query(array_merge($_GET, ['page' => '']));
$link_primeiro = "anotacao_recado.php?{$query_string}1";
$link_anterior = ($page > 1) ? "anotacao_recado.php?{$query_string}" . ($page - 1) : "#";
$link_proximo = ($page < $total_pages) ? "anotacao_recado.php?{$query_string}" . ($page + 1) : "#";
$link_ultimo = "anotacao_recado.php?{$query_string}" . $total_pages;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Anotações e Recados</title>
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
            <h1>Anotações e Recados</h1>

            <?php if (!empty($message)): ?>
                <div class="alert <?= $message_type ?>">
                    <?= $message ?>
                </div>
            <?php endif; ?>

            <?php  if (($action === 'edit') && !empty($anotacoes_para_editar)) :?>
                
            <div class= "form-tollbar client-header" style="display: flex; justify-content: space-between;">
                <div class="client-info">
                    <strong><?= htmlspecialchars($anotacoes_para_editar['ANO_TITULO'])?></strong><br><br>
                    Data: <?= htmlspecialchars(date('d/m/Y H:i', strtotime($anotacoes_para_editar['ANO_DATA']))) ?>
                </div>

                <div class="client-actions">
                    <a href="anotacao_recado.php" class="btn btn-danger">Voltar para Consulta</a>
                </div>
            </div>

             <?php else :?>
                <div class="form-toolbar">
                    <?php if (temPermissao('gerenciar_anotacao')): ?>
                        <a href="anotacao_recado.php?action=new" class="btn">Novo</a>
                    <?php endif; ?>        
                </div>
            <?php endif; ?>

            <div class="tabs">
                <div class="tab-buttons">
                    <button type="button" class="tab-button <?=$aba_ativa == 'consulta_anotacao' ? 'active' : '' ?>" data-tab="consulta_anotacao" <?php if ($aba_ativa == 'cadastro_anotacao'): ?> style="display: none;" <?php endif;?>>Consulta</button>
                    <button type="button" class="tab-button <?= $aba_ativa == 'cadastro_anotacao' ? 'active' : '' ?>" data-tab="cadastro_anotacao"<?= $aba_ativa != 'cadastro_anotacao' ? 'disabled' : '' ?>>Cadastro</button>
                </div>

                <div class="tab-content">
                    <!--Consulta-->
                    <div id="consulta_anotacao" class="tab-pane <?= $aba_ativa == 'consulta_anotacao' ? 'active' : '' ?>">
                        <form method="GET" action="anotacao_recado.php">
                            <fieldset class="search-box">
                                <legend>Opções de Pesquisa</legend>
                                <div class="form-group">
                                    <label for="campo_pesquisa">Pesquisar por:</label>
                                    <select id="campo_pesquisa" name="campo_pesquisa">
                                        <option value="codigo"<?=($_GET['campo_pesquisa'] ?? '') === 'codigo' ? 'selected' : '' ?>>Código</option>
                                        <option value="titulo"<?=($_GET['campo_pesquisa'] ?? '') === 'titulo' ? 'selected' : '' ?>>Título</option>
                                        <option value="data"<?=($_GET['campo_pesquisa'] ?? '') === 'data' ? 'selected' : '' ?>>Data</option>
                                    </select>
                                </div>
                                <div class="form-group" style="flex-grow: 2;">
                                    <label for="valor_pesquisa">Parâmetro</label>
                                    <input type="text" id="valor_pesquisa" name="valor_pesquisa" value="<?= htmlspecialchars($_GET['valor_pesquisa'] ?? '') ?>">
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
                                        <th>TÍTULO</th>
                                        <th>DATA</th>
                                        <th>DESCRIÇÃO</th>
                                         <th style="text-align: center;">AÇÃO</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($anotacoes)): ?>
                                        <?php foreach ($anotacoes as $anotacao): ?>
                                        <tr class="clickable-row" data-id="<?= $anotacao['ANO_CODIGO'] ?>">
                                            <td><?=htmlspecialchars($anotacao['ANO_CODIGO'] ?? '')?></td>
                                            <td><?=htmlspecialchars($anotacao['ANO_TITULO'] ?? '')?></td>
                                            <td><?php               
                                                if (!empty($anotacao['ANO_DATA'])) {
                                                    echo htmlspecialchars(date('d/m/Y', strtotime($anotacao['ANO_DATA'])));
                                                } 
                                                ?>
                                            </td>
                                            <td><?=htmlspecialchars($anotacao['ANO_DESCRICAO'] ?? '')?></td>
                                            <td style="text-align: center; white-space: nowrap; display: flex; justify-content: center; gap: 15px;">
                                                <?php if (temPermissao('gerenciar_anotacao')) : ?>
                                                    <a href="anotacao_recado.php?action=edit&id=<?= $anotacao['ANO_CODIGO'] ?>" class="btn btn-primary">Editar</a>
                                                <?php endif; ?>
                                                <?php if (temPermissao('gerenciar_anotacoes')) : ?>
                                                    <a href="anotacao_recado.php?action=delete&id=<?= $anotacao['ANO_CODIGO'] ?>" class="btn btn-danger btn-excluir-linha">Excluir</a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5">Nenhum registro encontrado.</td>
                                        </tr>
                                    <?php endif; ?>
                                    <?php ?>
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
            </div>

                    <!--Cadastro-->
                    <div id="cadastro_anotacao" class="tab-pane <?=$aba_ativa == 'cadastro_anotacao' ? 'active' : '' ?>">
                        <form id="formAnotacao" method="POST" action="anotacao_recado.php">
                            <input type="hidden" name="ANO_CODIGO" value="<?= htmlspecialchars($anotacoes_para_editar['ANO_CODIGO'] ?? '') ?>">
                            <fieldset <?= ($action === 'view') ? 'disabled' : '' ?> style="border: none;">
                                <fieldset class="form-section">
                                    <legend>Dados da Anotação</legend>
                                    <div class="form-row">
                                        <div class="form-group" style="max-width: 120px;">
                                            <label>Código</label>
                                            <input type="text" value="<?= htmlspecialchars($anotacoes_para_editar['ANO_CODIGO'] ?? 'NOVO') ?>" readonly>
                                        </div>
                                        <div class="form-group" style="flex-grow: 2;">
                                            <label for="ANO_TITULO">Título</label>
                                            <input type="text" id="ANO_TITULO" name="ANO_TITULO" value="<?= htmlspecialchars($anotacoes_para_editar['ANO_TITULO'] ?? '') ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="ANO_DATA">Data</label>
                                            <input type="date" id="ANO_DATA" name="ANO_DATA" value="<?= htmlspecialchars($anotacoes_para_editar['ANO_DATA'] ?? date('d/m/Y')) ?>"required>
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group" style="flex-basis: 100%;">
                                        <label for="ANO_DESCRICAO">Descrição / Conteúdo</label>
                                        
                                        <textarea id="ANO_DESCRICAO" name="ANO_DESCRICAO" rows="10"><?= htmlspecialchars($anotacoes_para_editar['ANO_DESCRICAO'] ?? '') ?></textarea>
                                    
                                    </div>
                                    </div>
                                </fieldset>
                            </fieldset>
                            <div class="form-footer">
                                <?php if ($action !== 'view'): ?>
                                    <button type="submit" class="btn btn-primary">Salvar</button> 
                                <?php endif; ?>
                                <a href="anotacao_recado.php" class="btn btn-danger">Cancelar</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
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
</body>
</html>