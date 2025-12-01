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

// --- INICIALIZAÇÃO ---
$aba_ativa = 'consulta'; 
$protocolo_para_editar = [];
$itens_do_protocolo = [];
if (!empty($_GET['status'])) {
    switch ($_GET['status']) {
        case 'success':
            $message = '<strong>Sucesso!</strong> Protocolo salvo com sucesso.';
            $message_type = 'success';
            break;
        case 'deleted':
            $message = 'Protocolo excluído com sucesso.';
            $message_type = 'success';
            break;
        case 'error':
            $errorMessage = $_GET['msg'] ?? 'Ocorreu um problema não especificado.';
            $message = '<strong>Erro!</strong> ' . htmlspecialchars(urldecode($errorMessage));
            $message_type = 'error';
            break;
    }
}

// --- AÇÕES ---
$action = $_GET['action'] ?? null;
$id = $_GET['id'] ?? null;

if ($action === 'new' || $action === 'edit' || $action === 'view') {
    $aba_ativa = 'cadastro';
}

if (($action === 'edit' || $action === 'view') && $id) {
    $stmt = $conn->prepare("SELECT * FROM PROTOCOLO_SAIDA WHERE PSA_CODIGO = ?");
    $stmt->execute([$id]);
    $protocolo_para_editar = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($protocolo_para_editar) {
        if (!empty($protocolo_para_editar['PSA_DES_CODIGO'])) {
            $stmt_desp = $conn->prepare("SELECT NOME FROM DESPACHANTE WHERE COD_DESP = ?");
            $stmt_desp->execute([$protocolo_para_editar['PSA_DES_CODIGO']]);
            $despachante = $stmt_desp->fetch(PDO::FETCH_ASSOC);
            if ($despachante) {
                $despachante_nome = $protocolo_para_editar['PSA_DES_CODIGO'] . ' - ' . $despachante['NOME'];
            }
        }

        // Carrega os itens do protocolo
        $stmt_itens = $conn->prepare("SELECT * FROM PROTOCOLO_SAIDA_ITEM WHERE PSI_PSA_CODIGO = ? ORDER BY PSI_CODIGO");
        $stmt_itens->execute([$id]);
        $itens_do_protocolo = $stmt_itens->fetchAll(PDO::FETCH_ASSOC);
    }
}



// --- LÓGICA DE SALVAR ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn->beginTransaction();
    try {
        $protocolo_id = !empty($_POST['PSA_CODIGO']) ? $_POST['PSA_CODIGO'] : null;

        $dados_protocolo = [
            'PSA_DATA_EMISSAO' => $_POST['PSA_DATA_EMISSAO'] ?: date('d-m-y'),
            'PSA_DESTINATARIO_NOME' => $_POST['PSA_DESTINATARIO_NOME'] ?: null,
            'PSA_ENTREGUE_POR' => $_POST['PSA_ENTREGUE_POR'] ?: null,
            'PSA_OBSERVACOES' => $_POST['PSA_OBSERVACOES'] ?: null,
            'PSA_DES_CODIGO' => $_POST['PSA_DES_CODIGO'] ?: null,
            'PSA_DATA_ENTREGUE' => $_POST['PSA_DATA_ENTREGUE'] ?: null

        ];

        if (empty($protocolo_id)) {
            // INSERT
            $lastNumStmt = $conn->query("SELECT MAX(PSA_NUMERO) as last_num FROM PROTOCOLO_SAIDA");
            $lastNum = $lastNumStmt->fetchColumn();
            $dados_protocolo['PSA_NUMERO'] = ($lastNum ?: 0) + 1;

            $colunas = implode(', ', array_keys($dados_protocolo));
            $placeholders = ':' . implode(', :', array_keys($dados_protocolo));
            $sql = "INSERT INTO PROTOCOLO_SAIDA ($colunas) VALUES ($placeholders)";
            $stmt = $conn->prepare($sql);
            $stmt->execute($dados_protocolo);
            $protocolo_id = $conn->lastInsertId();
        } else {
            // UPDATE
            $dados_protocolo['PSA_NUMERO'] = $_POST['PSA_NUMERO'];
            $set_sql = [];
            foreach ($dados_protocolo as $key => $value) {
                $set_sql[] = "$key = :$key";
            }
            $sql = "UPDATE PROTOCOLO_SAIDA SET " . implode(', ', $set_sql) . " WHERE PSA_CODIGO = :PSA_CODIGO";
            $stmt = $conn->prepare($sql);
            $dados_protocolo['PSA_CODIGO'] = $protocolo_id;
            $stmt->execute($dados_protocolo);
            $stmt_del_itens = $conn->prepare("DELETE FROM PROTOCOLO_SAIDA_ITEM WHERE PSI_PSA_CODIGO = ?");
            $stmt_del_itens->execute([$protocolo_id]);
        }

        if (isset($_POST['processos']) && is_array($_POST['processos'])) {
            $sql_item = "INSERT INTO PROTOCOLO_SAIDA_ITEM (PSI_PSA_CODIGO, PSI_PRS_CODIGO, PSI_DESCRICAO_ITEM) VALUES (?, ?, ?)";
            $stmt_item = $conn->prepare($sql_item);

            foreach ($_POST['processos'] as $processo) {
                if (!empty($processo['codigo'])) {
                    $stmt_item->execute([$protocolo_id, $processo['codigo'], $processo['descricao']]);
                }
            }
        }

        $conn->commit();
        header("Location: protocolo_saida.php?action=edit&id=" . $protocolo_id . "&status=success");
        exit;

    } catch (PDOException $e) {
        $conn->rollBack();
        $errorMessage = urlencode($e->getMessage());
        $redirectUrl = "protocolo_saida.php?status=error&msg=$errorMessage";
        if ($protocolo_id) {
            $redirectUrl .= "&action=edit&id=$protocolo_id";
        }
        
        header("Location: " . $redirectUrl);
        exit;
    }
}

// --- LÓGICA DE CONSULTA  ---
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$sql_base = "SELECT * FROM PROTOCOLO_SAIDA";
$sql_count_base = "SELECT COUNT(*) FROM PROTOCOLO_SAIDA";
$params = [];
$where_clauses = [];

if (!empty($_GET['valor_pesquisa'])) {
    $campo = $_GET['campo_pesquisa'];
    $valor = '%' . $_GET['valor_pesquisa'] . '%';

    if ($campo == 'numero') $where_clauses[] = "PSA_NUMERO LIKE ?";
    elseif ($campo == 'destinatario') $where_clauses[] = "PSA_DESTINATARIO_NOME LIKE ?";
    elseif ($campo == 'entregue') $where_clauses[] = "PSA_ENTREGUE_POR LIKE ?";
    
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

$sql_final = $sql_base . " ORDER BY PSA_CODIGO DESC LIMIT $limit OFFSET $offset";
$stmt = $conn->prepare($sql_final);
$stmt->execute($params);
$protocolos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$query_string = http_build_query(array_merge($_GET, ['page' => '']));
$link_base = "protocolo_saida.php?" . $query_string;
$link_primeiro = $link_base . "1";
$link_anterior = ($page > 1) ? $link_base . ($page - 1) : "#";
$link_proximo = ($page < $total_pages) ? $link_base . ($page + 1) : "#";
$link_ultimo = $link_base . $total_pages;

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Protocolo de Saída - Lindomar Despachante</title>
    <link rel="stylesheet" href="../css/style_sistema.css">
    <link rel="stylesheet"href="../css/print.css">
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
        <h1>Protocolo de Saída</h1>

       <?php if (!empty($message)): ?>
                <div class="alert <?= $message_type ?>">
                    <?= $message ?>
                </div>
            <?php endif; ?>

        <?php 
                if (($action === 'edit') && !empty($protocolo_para_editar)) :
            ?>
                <div class= "form-tollbar client-header" style="display: flex; justify-content: space-between;">
                    <div class="client-info">
                        <strong>Destinatário:</strong> <?= htmlspecialchars($protocolo_para_editar['PSA_DESTINATARIO_NOME'] ?? '')?>
                    </div>

                    <div class="client-actions">
                        <a href="protocolo_saida.php" class="btn btn-danger">Voltar para Consulta</a>
                    </div>
                </div>

            <?php else :?>
                <div class="form-toolbar">
                    <?php if (temPermissao('acessar_config')): ?>
                        <a href="protocolo_saida.php?action=new" class="btn">Novo</a>
                    <?php endif; ?>        
                </div>
            <?php endif; ?>
        
        <div class="tabs">
            <div class="tab-buttons">
                <button type="button" class="tab-button <?=$aba_ativa == 'consulta' ? 'active' : '' ?>" data-tab="consulta" <?php if ($aba_ativa == 'cadastro'): ?> style="display: none;" <?php endif;?>>Consulta</button>
                <button type="button" class="tab-button <?= $aba_ativa == 'cadastro' ? 'active' : '' ?>" data-tab="cadastro" <?= !($aba_ativa == 'cadastro') ? 'disabled' : '' ?>>Cadastro</button>                 
            </div>

            <div class="tab-content">

                <div id="consulta" class="tab-pane <?= $aba_ativa == 'consulta' ? 'active' : '' ?>">
                    <form method="GET" action="protocolo_saida.php"><fieldset class="search-box">
                        <legend>Opções de pesquisa</legend>
                        <div class="form-group">
                            <label for="campo_pesquisa">Campo:</label>
                            <select id="campo_pesquisa" name="campo_pesquisa">
                                <option value="numero" <?= ($_GET['campo_pesquisa'] ?? '') === 'numero' ? 'selected' : '' ?>>Nº PROTOCOLO</option>
                                <option value="destinatario" <?= ($_GET['campo_pesquisa'] ?? '') === 'destinatario' ? 'selected' : '' ?>>RECEBIDO POR</option>
                                <option value="entregue" <?= ($_GET['campo_pesquisa'] ?? '') === 'entregue' ? 'selected' : '' ?>>ENTREGUE POR</option>
                            </select>
                        </div>
                        <div class="form-group" style="flex-grow: 2;">
                            <label for="valor_pesquisa">Parâmetro:</label>
                            <input type="text" id="valor_pesquisa" name="valor_pesquisa"value="<?= htmlspecialchars($_GET['valor_pesquisa'] ?? '') ?>">
                        </div>

                        <button type="submit" class="btn btn-primary">Pesquisar</button>
                    </fieldset></form>

                    <div class="table-container">
                        <p class="table-note">Selecione para Consultar/Editar</p>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Nº PROTOCOLO</th>
                                    <th>DATA CADASTRO</th>
                                    <th>DESTINATÁRIO</th>
                                    <th>ENTREGUE POR</th>
                                    <th style="text-align: center;">AÇÃO</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($protocolos as $protocolo): ?>
                                <tr class="clickable-row" data-id="<?= $protocolo['PSA_CODIGO'] ?>">
                                    <td><?= htmlspecialchars($protocolo['PSA_NUMERO'] ?? '') ?></td>
                                    <td><?= date('d/m/Y', strtotime($protocolo['PSA_DATA_EMISSAO']) ?? '') ?></td>
                                    <td><?= htmlspecialchars($protocolo['PSA_DESTINATARIO_NOME'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($protocolo['PSA_ENTREGUE_POR'] ?? '') ?></td>
                                    <td style="text-align: center; white-space: nowrap; display: flex; justify-content: center; gap: 15px;">
                                        <?php if (temPermissao('gerenciar_config')) : ?>
                                            <a href="protocolo_saida.php?action=edit&id=<?= $protocolo['PSA_CODIGO'] ?>" class="btn btn-primary">Editar</a>
                                        <?php endif; ?>
                                        <?php if (temPermissao('gerenciar_config')) : ?>
                                            <a href="protocolo_saida.php?action=delete&id=<?= $protocolo['PSA_CODIGO'] ?>" class="btn btn-danger btn-excluir-linha">Excluir</a>
                                        <?php endif; ?>
                                    </td>     
                                </tr>
                                <?php endforeach; ?>
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

                <div id="cadastro" class="tab-pane <?= $aba_ativa == 'cadastro' ? 'active' : '' ?>">
                    <form method="POST" action="protocolo_saida.php">
                        <input type="hidden" name="PSA_CODIGO" value="<?= htmlspecialchars($protocolo_para_editar['PSA_CODIGO'] ?? '') ?>">
                        <fieldset <?= ($action === 'view') ? 'disabled' : '' ?> style="border: none;">
                            <fieldset class="form-section">
                                <legend>Dados do Protocolo</legend>
                                <div class="form-row">
                                    <div class="form-group">
                                            <label>Código</label>
                                            <input type="number" value="<?= htmlspecialchars($protocolo_para_editar['PSA_CODIGO'] ?? 'NOVO') ?>" readonly>
                                        </div>

                                        <div class="form-group">
                                            <label>Nº Protocolo</label>
                                            <input type="number" name="PSA_NUMERO" value="<?= htmlspecialchars($protocolo_para_editar['PSA_NUMERO'] ?? '') ?>" >
                                        </div>
                                        <div class="form-group">
                                            <label>Data de Cadastro</label>
                                            <input type="date" name="PSA_DATA_EMISSAO" value="<?= htmlspecialchars($protocolo_para_editar['PSA_DATA_EMISSAO'] ?? date('d/m/y')) ?>">
                                        </div>                  
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Recebido Por</label>
                                            <input type="text" name="PSA_DESTINATARIO_NOME" value="<?= htmlspecialchars($protocolo_para_editar['PSA_DESTINATARIO_NOME'] ?? '') ?>">
                                        </div>    

                                        <div class="form-group">
                                            <label>Entregue Por</label>
                                            <input type="text" name="PSA_ENTREGUE_POR" value="<?= htmlspecialchars($protocolo_para_editar['PSA_ENTREGUE_POR'] ?? '') ?>">
                                        </div>    
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group" style="flex-grow: 3;">
                                            <label for="COD_DESP">Despachante</label>
                                            <div class="input-with-button">
                                                <input type="text" id="cod_desp_input" placeholder="Clique no botão para buscar..."value="<?= htmlspecialchars($despachante_nome ?? '') ?>" readonly>
                                                <input type="hidden" id="cod_desp_hidden" name="PSA_DES_CODIGO" value="<?= htmlspecialchars($protocolo_para_editar['PSA_DES_CODIGO'] ?? '') ?>">
                                                <button type="button" id="btnAbrirModalDespachante" class="btn-lookup">...</button>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label for="">Data de Entrega</label>
                                            <input type="date" name="PSA_DATA_ENTREGUE" value="<?= htmlspecialchars($protocolo_para_editar['PSA_DATA_ENTREGUE'] ?? date('d/m/y') ?? '')?>">
                                        </div>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group" style="width:100%">
                                            <label>Observações</label>
                                            <textarea name="PSA_OBSERVACOES" rows="3"><?= htmlspecialchars($protocolo_para_editar['PSA_OBSERVACOES'] ?? '') ?></textarea>
                                        </div>
                                    </div>
                            </fieldset>
                        </fieldset>

                        <fieldset <?= ($action === 'view') ? 'disabled' : '' ?> style="border: none;">
                            <fieldset class="form-section">
                                <legend>Itens/Processos para Entrega</legend>
                                <div class="table-container">
                                    <table class="data-table" id="itens-protocolo-table">
                                        <thead>
                                            <tr>
                                                <th>CÓDIGO DE PROCESSO</th>
                                                <th>DESCRIÇÃO DO ITEM</th>
                                                <th>AÇÃO</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($itens_do_protocolo as $i_index => $item): ?>
                                            <tr>
                                                <td>
                                                    <?= htmlspecialchars($item['PSI_PRS_CODIGO']) ?>
                                                    <input type="hidden" name="processos[<?= $i_index ?>][codigo]" value="<?= htmlspecialchars($item['PSI_PRS_CODIGO']) ?>">
                                                    <input type="hidden" name="processos[<?= $i_index ?>][descricao]" value="<?= htmlspecialchars($item['PSI_DESCRICAO_ITEM']) ?>">
                                                </td>
                                                <td><?= htmlspecialchars($item['PSI_DESCRICAO_ITEM']) ?></td>
                                                <td><button type="button" class="btn btn-danger btn-sm remove-item-btn">Remover</button></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    </table>
                                </div>
                                <div class="form-footer" style="justify-content: flex-start;">
                                    <button type="button" id="btn-add-processo" class="btn">Adicionar Processo Concluído</button>
                                </div>
                            </fieldset>
                        </fieldset>
                        <div class="form-footer">
                            <?php if ($action !== 'view'): ?>
                                <button type="submit" class="btn btn-primary">Salvar</button> 
                            <?php endif; ?>

                            <?php if ($action === 'edit' || 'view' && !empty($protocolo_para_editar)): ?>
                                <a href="imprimir_protocolo_saida.php?id=<?= $protocolo_para_editar['PSA_CODIGO'] ?>" target="_blank" class="btn">Imprimir</a>
                            <?php endif; ?>

                            <a href="protocolo_saida.php" class="btn btn-danger">Cancelar</a>
                        </div>
                    </form>
                </div>                                    
            </div>
        </main>
    </div>


         <!--modal buscar despachante -->
    <div id="modalDespachante" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2>Buscar Despachante</h2>
            <input class="modal_busca" type="text" id="buscaDespachanteInput" placeholder="Busque pelo nome ou código...">
            <div id="listaResultadosDespachante" class="results-list"></div>
        </div>
    </div>

    <div id="modalBuscaProcesso" class="modal">
        <div class="modal-content" style="width: 60%; max-width: 700px;">
            <span class="close-button">&times;</span>
            <h2>Buscar Processo Concluído</h2>
            <input type="text" id="buscaProcessoInput" placeholder="Busque por nº, placa, nome do cliente...">
            <div id="listaResultadosProcessos" class="results-list"></div>
        </div>
    </div>

    <script src="../js/script.js"></script>
    <script src="../js/script_menu.js"></script>
    <script src="../js/script_modal.js"></script>
    <script src="../js/script_mascara.js"></script>
    <script src="../js/script_main.js"></script>
 
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const addProcessoBtn = document.getElementById('btn-add-processo');
        const modal = document.getElementById('modalBuscaProcesso');
        const closeModalBtn = modal.querySelector('.close-button');
        const buscaInput = document.getElementById('buscaProcessoInput');
        const resultadosDiv = document.getElementById('listaResultadosProcessos');
        const itensTbody = document.getElementById('itens-protocolo-table').querySelector('tbody');
        let itemIndex = 0;

        addProcessoBtn.addEventListener('click', () => {
            modal.style.display = 'block';
            buscaInput.focus();
        });
        closeModalBtn.addEventListener('click', () => modal.style.display = 'none');

        buscaInput.addEventListener('keyup', async function() {
            const response = await fetch(`api_busca_processos_concluidos.php?query=${this.value}`);
            const processos = await response.json();
            resultadosDiv.innerHTML = '';
            processos.forEach(p => {
                const div = document.createElement('div');
                div.className = 'result-item';
                div.innerHTML = `<strong>Proc. ${p.PRS_CODIGO}</strong> - ${p.SERVICO_NOME} (Placa: ${p.VEICULO_PLACA})`;
                div.dataset.codigo = p.PRS_CODIGO;
                div.dataset.descricao = `${p.SERVICO_NOME} - Placa ${p.VEICULO_PLACA}`;
                resultadosDiv.appendChild(div);
            });
        });

        resultadosDiv.addEventListener('click', function(e) {
            const item = e.target.closest('.result-item');
            if (!item) return;

            const newRow = itensTbody.insertRow();
            newRow.innerHTML = `
                <td>
                    ${item.dataset.codigo}
                    <input type="hidden" name="processos[${itemIndex}][codigo]" value="${item.dataset.codigo}">
                    <input type="hidden" name="processos[${itemIndex}][descricao]" value="${item.dataset.descricao}">
                </td>
                <td>${item.dataset.descricao}</td>
                <td><button type="button" class="btn btn-danger btn-sm remove-item-btn">Remover</button></td>
            `;
            itemIndex++;
            modal.style.display = 'none';
        });

        itensTbody.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-item-btn')) {
                e.target.closest('tr').remove();
            }
        });
    });
    </script>

    <div id="customConfirmModal" class="modal-confirm" style="display: none;">
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