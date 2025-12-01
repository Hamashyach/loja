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
$aba_ativa = 'consulta_tipos_servico';
$tipos_servico_para_editar = [];
$tipos_servico = [];
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$message = '';
$message_type = '';
if (!empty($_GET['status'])) {
    switch ($_GET['status']) {
        case 'success':
            $message = '<strong>Sucesso!</strong> Serviço salvo com sucesso.';
            $message_type = 'success';
            break;
        case 'deleted':
            $message = 'Serviço excluído com sucesso.';
            $message_type = 'success';
            break;
        case 'error':
            $errorMessage = $_GET['msg'] ?? 'Ocorreu um problema não especificado.';
            $message = '<strong>Erro!</strong> ' . htmlspecialchars(urldecode($errorMessage));
            $message_type = 'error';
            break;
    }
}

//LÓGICA DE AÇÕES

$action = $_GET['action'] ?? null;
$id = $_GET['id'] ?? null;

if ($action === 'new') {
    $aba_ativa = 'cadastro_tipos_servico';
}

if (($action === 'edit' || $action === 'view') && $id) {
    $aba_ativa = 'cadastro_tipos_servico';
    $stmt = $conn->prepare("SELECT * FROM TIPO_SERVICO WHERE TSE_CODIGO = ?");
    $stmt->execute([$id]);
    $tipos_servico_para_editar = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($action === 'delete' && $id) {
    try {
        $stmt = $conn->prepare("DELETE FROM TIPO_SERVICO WHERE TSE_CODIGO = ?");
        $stmt->execute([$id]);
        header("Location: cadastro_tipos_servico.php?status=deleted");
        exit;
    } catch (PDOException $e) {
        header("Location: cadastro_tipos_servico.php?status=error&msg=" . urlencode($e->getMessage()));
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $CODIGO = $_POST['TSE_CODIGO'] ?: null;   
    $dados = [
        'TSE_DESCRICAO' => $_POST['TSE_DESCRICAO'] ?? null,
        'TSE_VLUNITARIO' => $_POST['TSE_VLUNITARIO'] ?? null,
        'TSE_RELACAO' => $_POST['TSE_RELACAO'] ?? null,
        'TSE_HONORARIO' => $_POST['TSE_HONORARIO'] ?? null,
        'TSE_PLACAS' => $_POST['TSE_PLACAS'] ?? null,
        'TSE_ATPVE' => $_POST['TSE_ATPVE'] ?? null,
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
            $sql = "INSERT INTO TIPO_SERVICO ($colunas) VALUES ($placeholders)";
            $stmt = $conn->prepare($sql);
            $stmt->execute(array_values($dados));
        } else {
            $set_sql = [];
            foreach (array_keys($dados) as $coluna) {
                $set_sql[] = "$coluna = ?";
            }
            $sql = "UPDATE TIPO_SERVICO SET " . implode(', ', $set_sql) . " WHERE TSE_CODIGO = ?";
            $valores = array_values($dados);
            $valores[] = $CODIGO;
            $stmt = $conn->prepare($sql);
            $stmt->execute($valores);
        }
        header("Location: cadastro_tipos_servico.php?status=success");
        exit;
    } catch (PDOException $e) {
        die("Erro ao salvar tipo de serviço: " . $e->getMessage());
    }
}

// --- LÓGICA DE BUSCA E CONSULTA ---
$sql_base = "SELECT * FROM TIPO_SERVICO";
$sql_count_base = "SELECT COUNT(*) FROM TIPO_SERVICO";
$params = [];
$where_clauses = [];

if (!empty($_GET['valor_pesquisa'])) {
    $campo = $_GET['campo_pesquisa'];
    $valor = '%' . $_GET['valor_pesquisa'] . '%';

    if ($campo == 'codigo') $where_clauses[] = "TSE_CODIGO LIKE ?";
    elseif ($campo == 'descricao') $where_clauses[] = "TSE_DESCRICAO LIKE ?";
    elseif ($campo == 'valor') $where_clauses[] = "TSE_VLUNITARIO LIKE ?";
    
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

$sql_final = $sql_base ." ORDER BY TSE_DESCRICAO LIMIT $limit OFFSET $offset";
$stmt = $conn->prepare($sql_final);
$stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);

for ($i = 0; $i < count($params); $i++) {
    $stmt->bindValue($i + 1, $params[$i]);
}
$stmt->execute($params);
$tipos_servico = $stmt->fetchAll(PDO::FETCH_ASSOC);


// Lógica  de navegação
$query_string = http_build_query(array_merge($_GET, ['page' => '']));
$link_primeiro = "cadastro_tipos_servico.php?{$query_string}1";
$link_anterior = ($page > 1) ? "cadastro_tipos_servico.php?{$query_string}" . ($page - 1) : "#";
$link_proximo = ($page < $total_pages) ? "cadastro_tipos_servico.php?{$query_string}" . ($page + 1) : "#";
$link_ultimo = "cadastro_tipos_servico.php?{$query_string}" . $total_pages;

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tipos de Serviço</title>
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
                             <li><a href="cadastro_condicao_pagamento.php"><span>Condições Pagamento</span></a></li>
                            <li><a href="cadastro_tipos_servico.php"><span>Tipos de Serviço</span></a></li>
                            <li><a href="cadastro_tipo_endereco.php"><span>Tipos de Endereço</span></a></li>
                            <li><a href="cadastro_posto_vistoria.php"><span>Postos de Vistoria</span></a></li>
                            <li><a href="cadastro_tempos_licenciamento.php"><span>Tempos de Licenciamento</span></a></li>
                            <li><a href="cadastro_despachante.php"><span>Cadastro de Despachante</span></a></li>
                            <li><a href="cadastro_municipios.php"><span>Cadastro de Municipio</span></a></li>
                            <li><a href="cadastro_carroceria.php"><span>Carroceria</span></a></li>
                            <li><a href="cadastro_categoria.php"><span>Categoria</span></a></li>
                            <li><a href="cadastro_combustivel.php"><span>Combustível</span></a></li>
                            <li><a href="cadastro_cor.php"><span>Cor</span></a></li>
                            <li><a href="cadastro_especie.php"><span>Espécie</span></a></li>
                            <li><a href="cadastro_modelo.php"><span>Marca/Modelo</span></a></li>
                            <li><a href="cadastro_restricao.php"><span>Restrição</span></a></li>
                            <li><a href="cadastro_tipo_veiculo.php"><span>Tipo de Veículo</span></a></li>
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
            <h1>Tipos de Serviço</h1>

                <?php if (!empty($message)): ?>
                    <div class="alert <?= $message_type ?>">
                        <?= $message ?>
                    </div>
                <?php endif; ?>

               <?php 
                if (($action === 'edit') && !empty($tipos_servico_para_editar)) :
            ?>
                <div class= "form-tollbar client-header" style="display: flex; justify-content: space-between;">
                    <div class="client-info">
                       <strong>CÓDIGO:</strong> <?= htmlspecialchars($tipos_servico_para_editar['TSE_CODIGO'] ?? '')?><br><br>
                        DESCRIÇÃO: <?= htmlspecialchars($tipos_servico_para_editar['TSE_DESCRICAO']?? '') ?>
                    </div>

                    <div class="client-actions">
                        <a href="cadastro_tipo_servico.php" class="btn btn-danger">Voltar para Consulta</a>
                    </div>
                </div>


            <?php else :?>
                <div class="form-toolbar">
                    <?php if (temPermissao('gerenciar_config')): ?>
                        <a href="cadastro_tipo_servico.php?action=new" class="btn">Novo</a>
                    <?php endif; ?>        
                </div>
            <?php endif; ?>

            <div class="tabs">
                <div class="tab-buttons">
                    <button type="button" class="tab-button <?=$aba_ativa == 'consulta_tipos_servico' ? 'active' : '' ?>" data-tab="consulta_tipos_servico" <?php if ($aba_ativa == 'cadastro_tipos_servico'): ?> style="display: none;" <?php endif;?>>Consulta</button>
                    <button type="button" class="tab-button <?= $aba_ativa == 'cadastro_tipos_servico' ? 'active' : '' ?>" data-tab="cadastro_tipos_servico" <?= $aba_ativa != 'cadastro_tipos_servico' ? 'disabled' : '' ?>>Cadastro</button>
                </div>

                <!-- Consulta -->
                <div id="consulta_tipos_servico" class="tab-pane <?=$aba_ativa == 'consulta_tipos_servico' ? 'active' : '' ?>" style="padding: 20px;">
                    <form method="GET" action="cadastro_tipos_servico.php">
                        <fieldset class="search-box" >
                            <legend>Opções de pesquisa</legend>
                            <div class="form-group">
                                <label for="campo_pesquisa">Campo de Pesquisa:</label>
                                <select id="campo_pesquisa" name="campo_pesquisa">
                                    <option value="codigo" <?= ($_GET['campo_pesquisa'] ?? '') === 'codigo' ? 'selected' : '' ?>>CÓDIGO</option>
                                    <option value="descricao" <?=($_GET['campo_pesquisa'] ?? '') === 'descricao' ? 'selected' : '' ?>>DESCRIÇÃO</option>
                                    <option value="valor" <?=($_GET['campo_pesquisa'] ?? '') === 'valor' ? 'selected' : '' ?>>VALOR</option>
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
                                    <th>VALOR UNITÁRIO (R$)</th>
                                    <th style="text-align: center;">AÇÃO</th> 
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($tipos_servico)): ?>
                                    <?php foreach ($tipos_servico as $servico): ?>
                                        <tr class="clickable-row" data-id="<?= htmlspecialchars($servico['TSE_CODIGO'] ?? '') ?>">
                                            <td><?= htmlspecialchars($servico['TSE_CODIGO'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($servico['TSE_DESCRICAO'] ?? '') ?></td>
                                            <td>R$<?= htmlspecialchars($servico['TSE_VLUNITARIO'] ?? '') ?></td>
                                            <td style="text-align: center; white-space: nowrap; gap: 15px; display: flex; justify-content: center;">
                                                <?php if (temPermissao('gerenciar_config')) : ?>
                                                    <a href="cadastro_tipos_servico.php?action=edit&id=<?=$servico['TSE_CODIGO'] ?>" class="btn btn-primary">Editar</a>
                                                <?php endif; ?>
                                                <?php if (temPermissao('gerenciar_config')) : ?>
                                                    <a href="cadastro_tipos_servico.php?action=delete&id=<?=$servico['TSE_CODIGO'] ?>" class= "btn btn-danger btn-excluir-linha">Excluir</a>
                                                <?php endif; ?>
                                            </td>      
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3">Nenhum registro encontrado.</td>
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

                <!--cadastro-->
                <div id="cadastro_tipos_servico" class="tab-pane <?=$aba_ativa == 'cadastro_tipos_servico' ? 'active' : '' ?>">
                    <form id="formTiposServico" method="POST" action="cadastro_tipos_servico.php">
                        <input type="hidden" name="TSE_CODIGO" value="<?= htmlspecialchars($tipos_servico_para_editar['TSE_CODIGO'] ?? '') ?>">
                        <fieldset <?= ($action === 'view') ? 'disabled' : '' ?> style="border: none;">
                            <fieldset class="form-section">
                                <legend>Dados do Serviço</legend>
                                <div class="form-row">
                                    <div class="form-group" style="max-width: 120px;">
                                        <label for="TSE_CODIGO">Código</label>
                                        <input type="text" id="TSE_CODIGO" value="<?= htmlspecialchars($tipos_servico_para_editar['TSE_CODIGO'] ?? 'NOVO') ?>" readonly>

                                    </div>

                                    <div class="form-group" style="flex-grow: 2;">
                                        <label for="TSE_DESCRICAO">Descrição</label>
                                        <input type="text" id="TSE_DESCRICAO" name="TSE_DESCRICAO" value="<?= htmlspecialchars($tipos_servico_para_editar['TSE_DESCRICAO'] ?? '') ?>" required>
                                    </div>

                                    <div class="form-group">
                                        <label for="TSE_VLUNITARIO">Valor Unitário (R$)</label>
                                        <input type="number" step="0.01" id="TSE_VLUNITARIO" name="TSE_VLUNITARIO" value="<?= htmlspecialchars($tipos_servico_para_editar['TSE_VLUNITARIO'] ?? '') ?>" required>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="TSE_RELACAO">Relação</label>
                                        <select id="TSE_RELACAO" name="TSE_RELACAO">
                                            <option value="">--Selecione--</option>
                                            <option value="2VIA_CRV" <?=($tipos_servico_para_editar['TSE_RELACAO']?? '2VIA_CRV') == '2VIA_CRV' ? 'selected' : '' ?>>2° Via CRV</option> 
                                            <option value="PLACAS_VEICULO" <?=($tipos_servico_para_editar['TSE_RELACAO']?? 'PLACAS_VEICULO') == 'PLACAS_VEICULO' ? 'selected' : '' ?>>Placas De Veículo</option>
                                            <option value="TRANSF_JURISD_CRV" <?=($tipos_servico_para_editar['TSE_RELACAO']?? 'TRANSF_JURISD_CRV') == 'TRANSF_JURISD_CRV' ? 'selected' : '' ?>>Jurisdição</option>
                                            <option value="TRANSF_PROP_CRV" <?=($tipos_servico_para_editar['TSE_RELACAO']?? 'TRANSF_PROP_CRV') == 'TRANSF_PROP_CRV' ? 'selected' : '' ?>>Propriedade</option>
                                            <option value="PRIMEIRO_EMPLACAMENTO" <?=($tipos_servico_para_editar['TSE_RELACAO']?? 'PRIMEIRO_EMPLACAMENTO') == 'PRIMEIRO_EMPLACAMENTO' ? 'selected' : '' ?>>1° Emplacamento</option>
                                            <option value="DUAL_ALTERACAO" <?=($tipos_servico_para_editar['TSE_RELACAO']?? 'DUAL_ALTERACAO') == 'DUAL_ALTERACAO' ? 'selected' : '' ?>>Dual Alteração</option>
                                            <option value="NAO_ESPECIFICADO" <?=($tipos_servico_para_editar['TSE_RELACAO']?? 'NAO_ESPECIFICADO') == 'NAO_ESPECIFICADO' ? 'selected' : '' ?>>Não Especificado</option>
                                        </select>
                                    </div>

                                    <div class="form-group">
                                        <label for="TSE_HONORARIO">É Honorário?</label>
                                        <select id="TSE_HONORARIO" name="TSE_HONORARIO">
                                            <option value="0" <?=($tipos_servico_para_editar['TSE_HONORARIO'] ?? '0') == '0' ? 'selected' : '' ?>>Não</option>
                                            <option value="1" <?=($tipos_servico_para_editar['TSE_HONORARIO'] ?? '1') == '1' ? 'selected' : '' ?>>Sim</option>
                                        </select>
                                    </div>

                                    <div class="form-group">
                                        <label for="TSE_PLACAS">Envolve Placas?</label>
                                        <select id="TSE_PLACAS" name="TSE_PLACAS">
                                            <option value="0" <?=($tipos_servico_para_editar['TSE_PLACAS'] ?? '0') == '0' ? 'selected' : '' ?> >Não</option>
                                            <option value="1" <?=($tipos_servico_para_editar['TSE_PLACAS'] ?? '1') == '1' ? 'selected' : '' ?>>Sim</option>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="TSE_ATPVE">Envolve ATPV-e?</label>
                                        <select id="TSE_ATPVE" name="TSE_ATPVE">
                                            <option value="0" <?=($tipos_servico_para_editar['TSE_ATPVE'] ?? '0') == '0' ? 'selected' : '' ?>>Não</option>
                                            <option value="1" <?=($tipos_servico_para_editar['TSE_ATPVE'] ?? '1') == '1' ? 'selected' : '' ?>>Sim</option>
                                        </select>
                                    </div>
                                </div>
                            </fieldset>
                        </fieldset>

                        <div class="form-footer">
                            <?php if ($action !== 'view'): ?>
                                <button type="submit" class="btn btn-primary">Salvar</button> 
                            <?php endif; ?>
                            <a href="cadastro_tipos_servico.php" class="btn btn-danger">Cancelar</a>
                        </div>

                    </form>
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

            // ATIVAÇÃO DAS SUB-ABAS (Contato, Observação, Veículos)
            const nestedTabButtons = document.querySelectorAll('#cadastro_cliente .tabs .tab-button');
            const nestedTabPanes = document.querySelectorAll('#cadastro_cliente .tabs .tab-pane');

            nestedTabButtons.forEach(button => {
                button.addEventListener('click', () => {
                    nestedTabButtons.forEach(btn => btn.classList.remove('active'));
                    nestedTabPanes.forEach(pane => pane.classList.remove('active'));
                    button.classList.add('active');
                    
                    const targetPaneId = button.dataset.tab;
                    const targetPane = document.getElementById(targetPaneId);
                    if (targetPane) {
                        targetPane.classList.add('active');
                    }
                });
            });

            if (document.getElementById('cadastro_cliente').classList.contains('active')) {
                if (!document.querySelector('#cadastro_cliente .tabs .tab-button.active')) {
                     document.querySelector('#cadastro_cliente .tabs .tab-button[data-tab="contato"]').classList.add('active');
                     document.querySelector('#cadastro_cliente .tabs .tab-pane#contato').classList.add('active');
                }
            }
        });
    </script>

</body>
</html>