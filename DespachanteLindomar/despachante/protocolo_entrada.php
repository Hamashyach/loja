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
$veiculos_do_protocolo = [];
$message = '';
$message_type = '';
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

if ($action === 'new' || ($action === 'edit' || $action === 'view')) {
    $aba_ativa = 'cadastro';
}

if (($action === 'edit' || $action === 'view') && $id) {
    $stmt_protocolo = $conn->prepare("SELECT * FROM PROTOCOLO_ENTRADA WHERE PRE_CODIGO = ?");
    $stmt_protocolo->execute([$id]);
    $protocolo_para_editar = $stmt_protocolo->fetch(PDO::FETCH_ASSOC);

    if ($protocolo_para_editar) {
        if (!empty($protocolo_para_editar['PRE_DES_CODIGO'])) {
            $stmt_desp = $conn->prepare("SELECT NOME FROM DESPACHANTE WHERE COD_DESP = ?");
            $stmt_desp->execute([$protocolo_para_editar['PRE_DES_CODIGO']]);
            $despachante = $stmt_desp->fetch(PDO::FETCH_ASSOC);
            if ($despachante) {
                $despachante_nome = $protocolo_para_editar['PRE_DES_CODIGO'] . ' - ' . $despachante['NOME'];
            }
        }


            $stmt_veiculos = $conn->prepare("SELECT * FROM PROTOCOLO_ENTRADA_VEICULO WHERE PEV_PRE_CODIGO = ? ORDER BY PEV_CODIGO");
            $stmt_veiculos->execute([$id]);
            $veiculos_do_protocolo = $stmt_veiculos->fetchAll(PDO::FETCH_ASSOC);

            foreach ($veiculos_do_protocolo as $key => $veiculo) {
                $stmt_itens = $conn->prepare("SELECT * FROM PROTOCOLO_ENTRADA_ITEM WHERE PEI_PEV_CODIGO = ? ORDER BY PEI_CODIGO");
                $stmt_itens->execute([$veiculo['PEV_CODIGO']]);
                $veiculos_do_protocolo[$key]['servicos'] = $stmt_itens->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    }

// --- LÓGICA DE SALVAR ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn->beginTransaction();
    try {
        $protocolo_id = !empty($_POST['PRE_CODIGO']) ? $_POST['PRE_CODIGO'] : null;

        $dados_protocolo = [
            'PRE_DATA_CADASTRO' => $_POST['PRE_DATA_CADASTRO'] ?: date('Y-m-d'),
            'PRE_DES_CODIGO'    => $_POST['PRE_DES_CODIGO'] ?: null,
            'PRE_OBSERVACOES'   => $_POST['PRE_OBSERVACOES'] ?: null,
            'PRE_VALOR_TOTAL'   => 0,
            'PRE_ENTREGUE_POR'  => $_POST['PRE_ENTREGUE_POR'] ?: null,
            'PRE_DATA_ENTREGA'  => $_POST['PRE_DATA_ENTREGA'] ?: null
        ];

       if (empty($protocolo_id)) {
            // INSERT
            $lastNumStmt = $conn->query("SELECT MAX(PRE_NUMERO) as last_num FROM PROTOCOLO_ENTRADA");
            $lastNum = $lastNumStmt->fetchColumn();
            $dados_protocolo['PRE_NUMERO'] = ($lastNum ?: 0) + 1;

            $colunas = implode(', ', array_keys($dados_protocolo));
            $placeholders = ':' . implode(', :', array_keys($dados_protocolo));
            $sql = "INSERT INTO PROTOCOLO_ENTRADA ($colunas) VALUES ($placeholders)";
            $stmt = $conn->prepare($sql);
            $stmt->execute($dados_protocolo);
            $protocolo_id = $conn->lastInsertId();
        } else {
            // UPDATE
            $dados_protocolo['PRE_NUMERO'] = $_POST['PRE_NUMERO']; 
            $set_sql = [];
            foreach ($dados_protocolo as $key => $value) {
                $set_sql[] = "$key = :$key";
            }
            $sql = "UPDATE PROTOCOLO_ENTRADA SET " . implode(', ', $set_sql) . " WHERE PRE_CODIGO = :PRE_CODIGO";
            $stmt = $conn->prepare($sql);
            $dados_protocolo['PRE_CODIGO'] = $protocolo_id;
            $stmt->execute($dados_protocolo);

            $stmt_del_itens = $conn->prepare("DELETE FROM PROTOCOLO_ENTRADA_ITEM WHERE PEI_PEV_CODIGO IN (SELECT PEV_CODIGO FROM PROTOCOLO_ENTRADA_VEICULO WHERE PEV_PRE_CODIGO = ?)");
            $stmt_del_itens->execute([$protocolo_id]);
            $stmt_del_veiculos = $conn->prepare("DELETE FROM PROTOCOLO_ENTRADA_VEICULO WHERE PEV_PRE_CODIGO = ?");
            $stmt_del_veiculos->execute([$protocolo_id]);
        }

        $valor_total_protocolo = 0;

   
       if (isset($_POST['veiculos']) && is_array($_POST['veiculos'])) {
            $sql_veiculo = "INSERT INTO PROTOCOLO_ENTRADA_VEICULO (PEV_PRE_CODIGO, PEV_VEI_PLACA, PEV_VEI_MODELO, PEV_VEI_RENAVAM, PEV_PROPRIETARIO, PEV_VALOR_TOTAL) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt_veiculo = $conn->prepare($sql_veiculo);
            
            $sql_item = "INSERT INTO PROTOCOLO_ENTRADA_ITEM (PEI_PEV_CODIGO, PEI_TSE_CODIGO, PEI_VALOR) VALUES (?, ?, ?)";
            $stmt_item = $conn->prepare($sql_item);

            foreach ($_POST['veiculos'] as $veiculo_data) {
                $placa = $veiculo_data['placa'] ?? null;
                if (empty($placa)) continue;

                $valor_total_veiculo = 0;
                if (isset($veiculo_data['servicos']) && is_array($veiculo_data['servicos'])) {
                    foreach ($veiculo_data['servicos'] as $servico_data) {
                        $valor_servico = preg_replace('/[^\d,]/', '', $servico_data['valor'] ?? '0');
                        $valor_servico = (float) str_replace(',', '.', $valor_servico);
                        $valor_total_veiculo += $valor_servico;
                    }
                }
                $valor_total_protocolo += $valor_total_veiculo;

                $stmt_veiculo->execute([
                    $protocolo_id, $placa,
                    $veiculo_data['modelo'] ?? null,
                    $veiculo_data['renavam'] ?? null,
                    $veiculo_data['proprietario'] ?? null,
                    $valor_total_veiculo
                ]);
                $veiculo_id = $conn->lastInsertId();

               if (isset($veiculo_data['servicos']) && is_array($veiculo_data['servicos'])) {
                    foreach ($veiculo_data['servicos'] as $servico_data) {
                        $codigo_servico = $servico_data['codigo'] ?? null;
                        if (!empty($codigo_servico)) {
                            $valor_servico = preg_replace('/[^\d,]/', '', $servico_data['valor'] ?? '0');
                            $valor_servico = (float) str_replace(',', '.', $valor_servico);
                            $stmt_item->execute([$veiculo_id, $codigo_servico, $valor_servico]);
                        }
                    }
                }
            }
        }

        $stmt_update_total = $conn->prepare("UPDATE PROTOCOLO_ENTRADA SET PRE_VALOR_TOTAL = ? WHERE PRE_CODIGO = ?");
        $stmt_update_total->execute([$valor_total_protocolo, $protocolo_id]);

        $conn->commit();
        header("Location: protocolo_entrada.php?status=success&id=" . $protocolo_id . "&action=edit");
        exit;

    } catch (PDOException $e) {
        $conn->rollBack();
        die("Erro ao salvar o protocolo: " . $e->getMessage());
    }
}

// --- LÓGICA DE CONSULTA ---
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$sql_base = "
    SELECT p.*, d.NOME AS DESPACHANTE_NOME 
    FROM PROTOCOLO_ENTRADA p
    LEFT JOIN DESPACHANTE d ON p.PRE_DES_CODIGO = d.COD_DESP
";
$sql_count_base = "
    SELECT COUNT(*) 
    FROM PROTOCOLO_ENTRADA p
    LEFT JOIN DESPACHANTE d ON p.PRE_DES_CODIGO = d.COD_DESP
";

$params = [];
$where_clauses = [];

if (!empty($_GET['valor_pesquisa'])) {
    $campo = $_GET['campo_pesquisa'];
    $valor = '%' . $_GET['valor_pesquisa'] . '%';

    if ($campo == 'numero') $where_clauses[] = "p.PRE_NUMERO LIKE ?";
    elseif ($campo == 'despachante') $where_clauses[] = "d.NOME LIKE ?";
    elseif ($campo == 'recebido') $where_clauses[] = "p.PRE_RECEBIDO_POR LIKE ?";
    
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

$sql_final = $sql_base . " ORDER BY p.PRE_CODIGO DESC LIMIT $limit OFFSET $offset";
$stmt = $conn->prepare($sql_final);
$stmt->execute($params);
$protocolos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$query_string = http_build_query(array_merge($_GET, ['page' => '']));
$link_base = "protocolo_entrada.php?" . $query_string;
$link_primeiro = $link_base . "1";
$link_anterior = ($page > 1) ? $link_base . ($page - 1) : "#";
$link_proximo = ($page < $total_pages) ? $link_base . ($page + 1) : "#";
$link_ultimo = $link_base . $total_pages;

$todos_os_servicos = $conn->query("SELECT TSE_CODIGO, TSE_DESCRICAO, TSE_VLUNITARIO FROM TIPO_SERVICO ORDER BY TSE_DESCRICAO")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Protocolo de Entrada de Documentos</title>
    <link rel="stylesheet" href="../css/style_sistema.css">
    <link real="stylesheet" href="../css/print.css">
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
            <h1>Protocolo de Entrada de Documentos</h1>

            <?php if (!empty($message)): ?>
                <div class="alert <?= $message_type ?>">
                    <?= $message ?>
                </div>
            <?php endif; ?>

            <?php 
                if (($action === 'edit') && !empty($protocolo_para_editar)) :
            ?>
                <div class= "form-tollbar client-header" style="display: flex; justify-content: space-between;">

                    <div class="client-actions">
                        <a href="protocolo_entrada.php" class="btn btn-danger">Voltar para Consulta</a>
                    </div>
                </div>

            <?php else :?>
                <div class="form-toolbar">
                    <?php if (temPermissao('acessar_config')): ?>
                        <a href="protocolo_entrada.php?action=new" class="btn">Novo</a>
                    <?php endif; ?>        
                </div>
            <?php endif; ?>
            <div class="tabs">
                <div class="tab-buttons">
                    <button type="button" class="tab-button <?=$aba_ativa == 'consulta' ? 'active' : '' ?>" data-tab="consulta" <?php if ($aba_ativa == 'cadastro'): ?> style="display: none;" <?php endif;?>>Consulta</button>
                    <button type="button" class="tab-button <?= $aba_ativa == 'cadastro' ? 'active' : '' ?>" data-tab="cadastro" <?= $aba_ativa != 'cadastro' ? 'disabled' : '' ?>>Cadastro</button>
                </div>
                <div class="tab-content">
                    <!--consulta-->
                    <div id="consulta" class="tab-pane <?= $aba_ativa == 'consulta' ? 'active' : '' ?>">
                        <form method="GET" action="protocolo_entrada.php">
                            <fieldset class="search-box">
                                <legend>Opções de pesquisa</legend>
                                <div class="form-group">
                                    <label for="campo_pesquisa">Campo de Pesquisa:</label>
                                    <select id="campo_pesquisa" name="campo_pesquisa">
                                        <option value="numero" <?= ($_GET['campo_pesquisa'] ?? '') === 'numero' ? 'selected' : '' ?>>Nº PROTOCOLO</option>
                                        <option value="despachante" <?= ($_GET['campo_pesquisa'] ?? '') === 'despachante' ? 'selected' : '' ?>>DESPACHANTE</option>
                                        <option value="recebido" <?= ($_GET['campo_pesquisa'] ?? '') === 'recebido' ? 'selected' : '' ?>>RECEBIDO POR</option>
                                    </select>
                                </div>

                                <div class="form-group" style="flex-grow: 2;">
                                    <label for="valor_pesquisa">Parâmetro da Pesquisa:</label>
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
                                        <th>Nº PROTOCOLO</th>
                                        <th>DATA ENTREGA</th>
                                        <th>DESPACHANTE</th>
                                        <th>RECEBIDO POR</th>
                                        <th>R$ VALOR TOTAL</th>
                                        <th style="text-align: center;">AÇÃO</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($protocolos)): ?>
                                        <tr><td colspan="6" style="text-align: center;">Nenhum registro encontrado.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($protocolos as $protocolo): ?>
                                        <tr class="clickable-row" data-id="<?= $protocolo['PRE_CODIGO'] ?>">
                                            <td><?= htmlspecialchars($protocolo['PRE_NUMERO'] ?? '') ?></td>
                                            <td><?= date('d/m/Y', strtotime($protocolo['PRE_DATA_ENTREGA']) ?? '') ?></td>
                                            <td><?= htmlspecialchars($protocolo['PRE_DES_NOME'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($protocolo['PRE_RECEBIDO_POR'] ?? '') ?></td>
                                            <td><?= number_format($protocolo['PRE_VALOR_TOTAL'], 2, ',', '.') ?></td>
                                            <td style="text-align: center; white-space: nowrap; display: flex; justify-content: center; gap: 15px;">
                                                <?php if (temPermissao('gerenciar_config')) : ?>
                                                    <a href="protocolo_entrada.php?action=edit&id=<?= $protocolo['PRE_CODIGO'] ?>" class="btn btn-primary">Editar</a>
                                                <?php endif; ?>
                                                <?php if (temPermissao('gerenciar_config')) : ?>
                                                    <a href="protocolo_entrada.php?action=delete&id=<?= $protocolo['PRE_CODIGO'] ?>" class="btn btn-danger btn-excluir-linha">Excluir</a>
                                                <?php endif; ?>
                                            </td>     
                                        </tr>
                                        <?php endforeach; ?>
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
                    <!--cadastro-->
                    <div id="cadastro" class="tab-pane <?= $aba_ativa == 'cadastro' ? 'active' : '' ?>">
                        <form method="POST" action="protocolo_entrada.php">
                            <input type="hidden" name="PRE_CODIGO" value="<?= htmlspecialchars($protocolo_para_editar['PRE_CODIGO'] ?? '') ?>">

                            <fieldset class="form-section">
                                <legend>Dados do Protocolo</legend>
                                <div class="form-row">

                                    <div class="form-group">
                                        <label>Código</label>
                                        <input type="number" value="<?= htmlspecialchars($protocolo_para_editar['PRE_CODIGO'] ?? 'NOVO') ?>" readonly>
                                    </div>

                                    <div class="form-group">
                                        <label>Nº Protocolo</label>
                                        <input type="number" name="PRE_NUMERO" value="<?= htmlspecialchars($protocolo_para_editar['PRE_NUMERO'] ?? '') ?>" >
                                    </div>
                                    <div class="form-group">
                                        <label>Data</label>
                                        <input type="date" name="PRE_DATA_CADASTRO" value="<?= htmlspecialchars($protocolo_para_editar['PRE_DATA_CADASTRO'] ?? date('Y-m-d')) ?>">
                                    </div>                  
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Recebido Por</label>
                                        <input type="text" name="PRE_RECEBIDO_POR" value="<?= htmlspecialchars($protocolo_para_editar['PRE_RECEBIDO_POR'] ?? '') ?>">
                                    </div>    

                                    <div class="form-group">
                                        <label>Entregue Por</label>
                                        <input type="text" name="PRE_ENTREGUE_POR" value="<?= htmlspecialchars($protocolo_para_editar['PRE_ENTREGUE_POR'] ?? '') ?>">
                                    </div>    
                                </div>

                                <div class="form-row">
                                    <div class="form-group" style="flex-grow: 3;">
                                        <label for="COD_DESP">Despachante</label>
                                        <div class="input-with-button">
                                            <input type="text" id="cod_desp_input" placeholder="Clique no botão para buscar..."value="<?= htmlspecialchars($despachante_nome ?? '') ?>" readonly>
                                            <input type="hidden" id="cod_desp_hidden" name="PRE_DES_CODIGO" value="<?= htmlspecialchars($protocolo_para_editar['PRE_DES_CODIGO'] ?? '') ?>">
                                            <button type="button" id="btnAbrirModalDespachante" class="btn-lookup">...</button>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="">Data de Entrega</label>
                                        <input type="date" name="PRE_DATA_ENTREGA" value="<?= htmlspecialchars($protocolo_para_editar['PRE_DATA_ENTREGA'] ?? date('d/m/y') ?? '')?>">
                                    </div>
                                </div>
                                <div class="form-row">
                                     <div class="form-group" style="width:100%">
                                        <label>Observações</label>
                                        <textarea name="PRE_OBSERVACOES" rows="3"><?= htmlspecialchars($protocolo_para_editar['PRE_OBSERVACOES'] ?? '') ?></textarea>
                                    </div>
                                </div>
                            </fieldset>

                            <fieldset class="form-section">
                                <legend>Veículos e Serviços</legend>
                                <div id="vehicles-container">
                                    
                                    <?php foreach ($veiculos_do_protocolo as $v_index => $veiculo): ?>
                                    <div class="vehicle-block">
                                        <div class="vehicle-block-header">
                                            <h3>Dados do Veículo</h3>
                                            <button type="button" class="btn btn-danger btn-sm remove-vehicle-btn">Remover Veículo</button>
                                        </div>
                                        <div class="form-row">
                                            <div class="form-group" style="flex-grow: 2;">
                                                <label>Proprietário</label>
                                                <div class="input-with-button">
                                                    <input type="text" class="proprietario-input" name="veiculos[<?= $v_index ?>][proprietario]" value="<?= htmlspecialchars($veiculo['PEV_PROPRIETARIO'] ?? '') ?>" readonly placeholder="Clique no botão para buscar...">
                                                    <button type="button" class="btn-lookup search-proprietario-btn">...</button>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <label>Placa</label>
                                                <input type="text" class="placa-input" name="veiculos[<?= $v_index ?>][placa]" value="<?= htmlspecialchars($veiculo['PEV_VEI_PLACA'] ?? '') ?>">
                                            </div>
                                        </div>
                                        <div class="form-row">
                                            <div class="form-group">
                                                <label>Modelo</label>
                                                <input type="text" class="modelo-input" name="veiculos[<?= $v_index ?>][modelo]" value="<?= htmlspecialchars($veiculo['PEV_VEI_MODELO'] ?? '') ?>">
                                            </div>
                                            <div class="form-group">
                                                <label>Renavam</label>
                                                <input type="text" class="renavam-input" name="veiculos[<?= $v_index ?>][renavam]" value="<?= htmlspecialchars($veiculo['PEV_VEI_RENAVAM'] ?? '') ?>">
                                            </div>
                                        </div>
                                        <h4>Serviços do Veículo</h4>
                                        <div class="table-container">
                                            <table class="data-table services-table">
                                                <thead><tr><th>Serviço</th><th>Valor (R$)</th><th>Ação</th></tr></thead>
                                                <tbody>
                                                    <?php foreach ($veiculo['servicos'] as $s_index => $servico_item): ?>
                                                    <tr>
                                                        <td>
                                                            <select name="veiculos[<?= $v_index ?>][servicos][<?= $s_index ?>][codigo]" class="servico-select">
                                                                <option value="">Selecione...</option>
                                                                <?php foreach ($todos_os_servicos as $servico_option):
                                                                    $selected = ($servico_option['TSE_CODIGO'] == $servico_item['PEI_TSE_CODIGO']) ? 'selected' : '';
                                                                ?>
                                                                    <option value="<?= $servico_option['TSE_CODIGO'] ?>" data-valor="<?= $servico_option['TSE_VLUNITARIO'] ?>" <?= $selected ?>><?= $servico_option['TSE_DESCRICAO'] ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </td>
                                                        <td><input type="text" class="mascara-moeda valor-servico" name="veiculos[<?= $v_index ?>][servicos][<?= $s_index ?>][valor]" value="<?= number_format($servico_item['PEI_VALOR'], 2, ',', '.') ?>"></td>
                                                        <td><button type="button" class="btn btn-danger btn-sm remove-service-btn">Remover</button></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <button type="button" class="btn btn-sm add-service-btn">Adicionar Serviço</button>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="form-footer" style="justify-content: space-between;">
                                    <button type="button" id="add-vehicle-btn" class="btn">Adicionar Veículo</button>
                                    <div class="resultado-calculo" style="text-align: right;">
                                    <p class="valor-final" style="font-size: 1.2em; color: #2c3e50;">
                                        Valor Total do Protocolo:
                                        <strong id="valorTotalProtocolo">R$ 0,00</strong>
                                    </p>
                                </div>
                                </div>
                            </fieldset>

                            <div class="form-footer">
                                <?php if ($action !== 'view'): ?>
                                    <button type="submit" class="btn btn-primary">Salvar</button> 
                                <?php endif; ?>

                                <a href="imprimir_protocolo_entrada.php?id=<?= $protocolo_para_editar['PRE_CODIGO'] ?>" target="_blank" class="btn">Imprimir</a>
                                <a href="protocolo_entrada.php" class="btn btn-danger">Cancelar</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>

        <template id="vehicle-template">
            <div class="vehicle-block">
                <div class="vehicle-block-header">
                    <h3>Dados do Veículo</h3>
                    <button type="button" class="btn btn-danger btn-sm remove-vehicle-btn">Remover Veículo</button>
                </div>
                <div class="form-row">
                    <div class="form-group" style="flex-grow: 2;">
                        <label>Proprietário</label>
                        <div class="input-with-button">
                            <input type="text" class="proprietario-input" name="veiculos[__INDEX__][proprietario]" readonly placeholder="Clique no botão para buscar...">
                            <button type="button" class="btn-lookup search-proprietario-btn">...</button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Placa</label>
                        <input type="text" class="placa-input" name="veiculos[__INDEX__][placa]">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Modelo</label>
                        <input type="text" class="modelo-input" name="veiculos[__INDEX__][modelo]">
                    </div>
                    <div class="form-group">
                        <label>Renavam</label>
                        <input type="text" class="renavam-input" name="veiculos[__INDEX__][renavam]">
                    </div>
                </div>
                <h4>Serviços do Veículo</h4>
                <div class="table-container">
                    <table class="data-table services-table">
                        <thead><tr><th>Serviço</th><th>Valor (R$)</th><th>Ação</th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
                <button type="button" class="btn btn-sm add-service-btn">Adicionar Serviço</button>
            </div>
        </template>
    
        <template id="service-template">
            <tr>
                <td>
                    <select name="veiculos[__V_INDEX__][servicos][__S_INDEX__][codigo]" class="servico-select">
                        <option value="" data-valor="0">Selecione...</option>
                        <?php 
                            foreach ($todos_os_servicos as $servico) {
                                echo "<option value='{$servico['TSE_CODIGO']}' data-valor='{$servico['TSE_VLUNITARIO']}'>{$servico['TSE_DESCRICAO']}</option>";
                            }
                        ?>
                    </select>
                </td>
                <td><input style="max-width: 90%;" type="text" class="mascara-moeda valor-servico" name="veiculos[__V_INDEX__][servicos][__S_INDEX__][valor]"></td>
                <td><button type="button" class="btn btn-danger btn-sm remove-service-btn">Remover</button></td>
            </tr>
        </template>

     <!--modal buscar despachante -->
    <div id="modalDespachante" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2>Buscar Despachante</h2>
            <input class="modal_busca" type="text" id="buscaDespachanteInput" placeholder="Busque pelo nome ou código...">
            <div id="listaResultadosDespachante" class="results-list"></div>
        </div>
    </div>
     <!--modal buscar veiculo -->
    <div id="modalBuscaClienteVeiculo" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <div id="busca-cliente-etapa">
                <h2>1. Buscar Proprietário / Cliente</h2>
                <input type="text" id="buscaClienteInput" placeholder="Digite o nome ou CPF/CNPJ do cliente...">
                <div id="listaResultadosCliente" class="results-list"></div>
            </div>
            <div id="seleciona-veiculo-etapa" style="display:none;">
                <h2>2. Selecionar Veículo de <strong id="nomeClienteSelecionado"></strong></h2>
                <div id="listaResultadosVeiculos" class="results-list"></div>
                <button type="button" id="btnVoltarBuscaCliente" class="btn btn-secondary" style="margin-top:15px;">Voltar</button>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        let vehicleIndex = <?= count($veiculos_do_protocolo) ?>;
        const vehiclesContainer = document.getElementById('vehicles-container');
        const addVehicleBtn = document.getElementById('add-vehicle-btn');
        const vehicleTemplate = document.getElementById('vehicle-template');
        const serviceTemplate = document.getElementById('service-template');

        <?php if ($action === 'new' && empty($protocolo_para_editar)): ?>
            if (vehiclesContainer.children.length === 0) addVehicleBlock();
        <?php endif; ?>

        addVehicleBtn.addEventListener('click', addVehicleBlock);

        function addVehicleBlock() {
            const templateContent = vehicleTemplate.innerHTML.replace(/__INDEX__/g, vehicleIndex);
            const newBlock = document.createElement('div');
            newBlock.innerHTML = templateContent;
            vehiclesContainer.appendChild(newBlock);
            
            const servicesTbody = newBlock.querySelector('.services-table tbody');
            addServiceRow(servicesTbody, vehicleIndex);

            vehicleIndex++;
        }

        function updateTotalProtocolo() {
            let total = 0;
            const todosValores = vehiclesContainer.querySelectorAll('.valor-servico');
            todosValores.forEach(input => {
                let valor = parseFloat(input.value.replace(/\./g, '').replace(',', '.')) || 0;
                total += valor;
            });
            const totalFormatado = total.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
            document.getElementById('valorTotalProtocolo').textContent = totalFormatado;
        }

        function addServiceRow(tbody, vIndex) {
            let serviceIndex = tbody.children.length;
            const templateContent = serviceTemplate.innerHTML
                .replace(/__V_INDEX__/g, vIndex)
                .replace(/__S_INDEX__/g, serviceIndex);
            const newRow = tbody.insertRow();
            newRow.innerHTML = templateContent;
        }

            vehiclesContainer.addEventListener('click', function(e) {            
            if (e.target.classList.contains('remove-vehicle-btn') || e.target.classList.contains('remove-service-btn')) {
                e.target.closest(e.target.classList.contains('remove-vehicle-btn') ? '.vehicle-block' : 'tr').remove();
                updateTotalProtocolo();
            }         
            if (e.target.classList.contains('add-service-btn')) {
                const vehicleBlock = e.target.closest('.vehicle-block');
                const vIndex = Array.from(vehiclesContainer.children).indexOf(vehicleBlock);
                const servicesTbody = vehicleBlock.querySelector('.services-table tbody');
                addServiceRow(servicesTbody, vIndex);
            }
        });

       vehiclesContainer.addEventListener('change', function(e) {

            if (e.target.matches('.servico-select')) {
                const select = e.target;
                const selectedOption = select.options[select.selectedIndex];
                const valor = selectedOption.getAttribute('data-valor');
                const valorInput = select.closest('tr').querySelector('.valor-servico');
                
                if (valorInput && valor) {
                    const valorFormatado = parseFloat(valor).toLocaleString('pt-BR', { minimumFractionDigits: 2 });
                    valorInput.value = valorFormatado;
                    updateTotalProtocolo();
                }
            }
        });

        vehiclesContainer.addEventListener('keyup', function(e) {
            if (e.target.matches('.valor-servico')) {
                updateTotalProtocolo();
            }
        });

        updateTotalProtocolo();

        const modalBusca = document.getElementById('modalBuscaClienteVeiculo');
        const closeModalBtn = modalBusca.querySelector('.close-button');
        let activeVehicleBlock = null; 

        const clienteEtapa = document.getElementById('busca-cliente-etapa');
        const veiculoEtapa = document.getElementById('seleciona-veiculo-etapa');
        const buscaClienteInput = document.getElementById('buscaClienteInput');
        const resultadosClienteDiv = document.getElementById('listaResultadosCliente');
        const nomeClienteSpan = document.getElementById('nomeClienteSelecionado');
        const resultadosVeiculoDiv = document.getElementById('listaResultadosVeiculos');
        const btnVoltar = document.getElementById('btnVoltarBuscaCliente');

       
        vehiclesContainer.addEventListener('click', function(e) {
            if (e.target.classList.contains('search-proprietario-btn')) {
                activeVehicleBlock = e.target.closest('.vehicle-block');
                resetBuscaModal();
                modalBusca.style.display = 'block';
                buscaClienteInput.focus();
            }
        });
       
            closeModalBtn.addEventListener('click', () => modalBusca.style.display = 'none');
            btnVoltar.addEventListener('click', resetBuscaModal);
            
            function resetBuscaModal() {
                clienteEtapa.style.display = 'block';
                veiculoEtapa.style.display = 'none';
                buscaClienteInput.value = '';
                resultadosClienteDiv.innerHTML = '';
                resultadosVeiculoDiv.innerHTML = '';
            }

            buscaClienteInput.addEventListener('keyup', async function() {
                const query = this.value;
                if (query.length < 2) {
                    resultadosClienteDiv.innerHTML = '';
                    return;
                }
                const response = await fetch(`api_busca_cliente.php?query=${query}`);
                const clientes = await response.json();
                
                resultadosClienteDiv.innerHTML = '';
                clientes.forEach(cliente => {
                    const div = document.createElement('div');
                    div.className = 'result-item';
                    div.innerHTML = `<strong>${cliente.NOME}</strong> - ${cliente.CPF_CNPJ}`;
                    div.dataset.nome = cliente.NOME;
                    div.dataset.cpfCnpj = cliente.CPF_CNPJ;
                    resultadosClienteDiv.appendChild(div);
                });
            });

            resultadosClienteDiv.addEventListener('click', async function(e) {
                const item = e.target.closest('.result-item');
                if (!item) return;

                const nome = item.dataset.nome;
                const cpfCnpj = item.dataset.cpfCnpj;
                nomeClienteSpan.textContent = nome;

                if (activeVehicleBlock) {
                    activeVehicleBlock.querySelector('.proprietario-input').value = nome;
                }

                const response = await fetch(`api_busca_veiculos_cliente.php?cpf_cnpj=${cpfCnpj}`);
                const veiculos = await response.json();

                resultadosVeiculoDiv.innerHTML = '';
                if (veiculos.length > 0) {
                    veiculos.forEach(veiculo => {
                        const div = document.createElement('div');
                        div.className = 'result-item';
                        div.innerHTML = `<strong>${veiculo.MODELO}</strong> - Placa: ${veiculo.PLACA_UF}`;
                        div.dataset.placa = veiculo.PLACA_UF;
                        div.dataset.modelo = veiculo.MODELO;
                        div.dataset.renavam = veiculo.RENAVAM;
                        resultadosVeiculoDiv.appendChild(div);
                    });
                } else {
                    resultadosVeiculoDiv.innerHTML = '<div class="result-item" style="justify-content:center;">Nenhum veículo encontrado para este cliente.</div>';
                }

                clienteEtapa.style.display = 'none';
                veiculoEtapa.style.display = 'block';
            });

            resultadosVeiculoDiv.addEventListener('click', function(e) {
                const item = e.target.closest('.result-item');
                if (!item || !activeVehicleBlock) return;

                activeVehicleBlock.querySelector('.placa-input').value = item.dataset.placa;
                activeVehicleBlock.querySelector('.modelo-input').value = item.dataset.modelo;
                activeVehicleBlock.querySelector('.renavam-input').value = item.dataset.renavam;
                
                modalBusca.style.display = 'none';
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

    <script src="../js/script.js"></script>
    <script src="../js/script_menu.js"></script>
    <script src="../js/script_modal.js"></script>
    <script src="../js/script_mascara.js"></script>
    <script src="../js/script_main.js"></script>

</body>
</html>