<?php 

session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../index.php");
    exit;
}
require_once '../verificar_permissao.php';
protegerPagina('acessar_config');
$usuario_nome = htmlspecialchars($_SESSION['username'] ?? 'Usuário');
$usuario_login = htmlspecialchars($_SESSION['login'] ?? '');

require_once '../config.php';

// INICIALIZAÇÃO E PAGINAÇÃO
$aba_ativa = 'consulta_TIPO_VEICULO';
$tipo_veiculo_para_editar = [];
$TIPO_VEICULO = [];
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$message = '';
$message_type = '';
if (!empty($_GET['status'])) {
    switch ($_GET['status']) {
        case 'success':
            $message = '<strong>Sucesso!</strong> Tipo de Veículo salvo com sucesso.';
            $message_type = 'success';
            break;
        case 'deleted':
            $message = 'Tipo de Veículo  excluído com sucesso.';
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
    $aba_ativa = 'cadastro_TIPO_VEICULO';
}

if (($action === 'edit' || $action === 'view') && $id) {
    $aba_ativa = 'cadastro_TIPO_VEICULO';
    $stmt = $conn->prepare("SELECT * FROM TIPO_VEICULO WHERE COD_VEIC = ?");
    $stmt->execute([$id]);
    $tipo_veiculo_para_editar = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($action === 'delete' && $id) {
    try {
        $stmt = $conn->prepare("DELETE FROM TIPO_VEICULO WHERE COD_VEIC = ?");
        $stmt->execute([$id]);
        header("Location: cadastro_tipo_veiculo?status=deleted");
        exit;
    } catch (PDOException $e) {
        header("Location: cadastro_tipo_veiculo?status=error&msg=" . urlencode($e->getMessage()));
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $CODIGO = $_POST['COD_VEIC'] ?: null;   
    $dados = [
        'DESCRICAO' => $_POST['DESCRICAO'] ?? null,
        'ABREVIATURA' => $_POST['ABREVIATURA'] ?? null
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
            $sql = "INSERT INTO TIPO_VEICULO ($colunas) VALUES ($placeholders)";
            $stmt = $conn->prepare($sql);
            $stmt->execute(array_values($dados));
        } else {
            $set_sql = [];
            foreach (array_keys($dados) as $coluna) {
                $set_sql[] = "$coluna = ?";
            }
            $sql = "UPDATE TIPO_VEICULO SET " . implode(', ', $set_sql) . " WHERE COD_VEIC = ?";
            $valores = array_values($dados);
            $valores[] = $CODIGO;
            $stmt = $conn->prepare($sql);
            $stmt->execute($valores);
        }
        header("Location: cadastro_tipo_veiculo?status=success");
        exit;
    } catch (PDOException $e) {
        die("Erro ao salvar tipo de TIPO_VEICULO: " . $e->getMessage());
    }
}

// --- LÓGICA DE BUSCA E CONSULTA ---
$sql_base = "SELECT * FROM TIPO_VEICULO";
$sql_count_base = "SELECT COUNT(*) FROM TIPO_VEICULO";
$params = [];
$where_clauses = [];

if (!empty($_GET['valor_pesquisa'])) {
    $campo = $_GET['campo_pesquisa'];
    $valor = '%' . $_GET['valor_pesquisa'] . '%';

    if ($campo == 'codigo') $where_clauses[] = "COD_VEIC LIKE ?";
    elseif ($campo == 'descricao') $where_clauses[] = "DESCRICAO LIKE ?";
    elseif ($campo == 'abreviatura') $where_clauses[] = "ABREVIATURA LIKE ?";

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

$sql_final = $sql_base ." ORDER BY COD_VEIC LIMIT $limit OFFSET $offset";
$stmt = $conn->prepare($sql_final);
$stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);

for ($i = 0; $i < count($params); $i++) {
    $stmt->bindValue($i + 1, $params[$i]);
}
$stmt->execute($params);
$TIPO_VEICULO = $stmt->fetchAll(PDO::FETCH_ASSOC);


// Lógica  de navegação
$query_string = http_build_query(array_merge($_GET, ['page' => '']));
$link_primeiro = "cadastro_tipo_veiculo?{$query_string}1";
$link_anterior = ($page > 1) ? "cadastro_tipo_veiculo?{$query_string}" . ($page - 1) : "#";
$link_proximo = ($page < $total_pages) ? "cadastro_tipo_veiculo?{$query_string}" . ($page + 1) : "#";
$link_ultimo = "cadastro_tipo_veiculo?{$query_string}" . $total_pages;

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro Tipo de Veículo - Lindomar Despachante</title>
    <link rel="stylesheet" href="../css/style_sistema.css">
    <style>
        .vehicle-block { border: 1px solid #ccc; border-radius: 5px; padding: 15px; margin-bottom: 20px; background: #f9f9f9; }
        .vehicle-block-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .vehicle-block-header h3 { margin: 0; color: #2c3e50; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 5px; border: 1px solid transparent; }
        .alert.success { color: #155724; background-color: #d4edda; border-color: #c3e6cb; }
        .alert.error { color: #721c24; background-color: #f8d7da; border-color: #f5c6cb; }


        .modal-confirm {
            display: none; /* Inicia oculto */
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.6);
        }

        .modal-confirm-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 25px 30px;
            border: 1px solid #888;
            width: 90%;
            max-width: 400px;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            text-align: center;
        }

        .modal-confirm-content p {
            font-size: 1.1em;
            margin: 0 0 20px 0;
            color: #333;
        }

        .modal-confirm-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
        }

        .modal-confirm-buttons button {
            padding: 10px 25px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
        }
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
            <h1>Tipo de Veículo</h1>

            <?php if (!empty($message)): ?>
                <div class="alert <?= $message_type ?>">
                    <?= $message ?>
                </div>
            <?php endif; ?>

             <?php 
                if (($action === 'edit') && !empty($tipo_veiculo_para_editar)) :
            ?>
                <div class= "form-tollbar client-header" style="display: flex; justify-content: space-between;">
                    <div class="client-info">
                       <strong>CÓDIGO:</strong> <?= htmlspecialchars($tipo_veiculo_para_editar['COD_VEIC'] ?? '')?><br><br>
                        DESCRIÇÃO: <?= htmlspecialchars($tipo_veiculo_para_editar['DESCRICAO']?? '') ?>
                    </div>

                    <div class="client-actions">
                        <a href="cadastro_tipo_veiculo.php" class="btn btn-danger">Voltar para Consulta</a>
                    </div>
                </div>


            <?php else :?>
                <div class="form-toolbar">
                    <?php if (temPermissao('gerenciar_config')): ?>
                        <a href="cadastro_tipo_veiculo.php?action=new" class="btn">Novo</a>
                    <?php endif; ?>        
                </div>
            <?php endif; ?>


             <div class="tabs">
                <div class="tab-buttons">
                    <button type="button" class="tab-button <?= $aba_ativa == 'consulta_TIPO_VEICULO' ? 'active' : '' ?>" data-tab="consulta_TIPO_VEICULO" <?php if ($aba_ativa == 'cadastro_TIPO_VEICULO'): ?> style="display: none;" <?php endif;?>>Consulta</button>
                    <button type="button" class="tab-button <?= $aba_ativa == 'cadastro_TIPO_VEICULO' ? 'active' : '' ?>" data-tab="cadastro_TIPO_VEICULO"<?= $aba_ativa != 'cadastro_TIPO_VEICULO' ?'disabled': '' ?>>Cadastro</button>
                </div>

                <div class="tab-content">                
                <div id="consulta_TIPO_VEICULO" class="tab-pane <?= $aba_ativa == 'consulta_TIPO_VEICULO' ? 'active' : '' ?>">
                    <form method="GET" action="cadastro_tipo_veiculo">
                        <fieldset class="search-box">
                            <legend>Opções de Pesquisa</legend>
                            <div class="form-group">
                                <label for="campo_pesquisa">Campo de Pesquisa:</label>
                                <select id="campo_pesquisa" name="campo_pesquisa">
                                    <option value="descricao" <?= ($_GET['campo_pesquisa'] ?? '') === 'descricao' ? 'selected' : '' ?>>DESCRIÇÃO</option>
                                    <option value="codigo" <?= ($_GET['campo_pesquisa'] ?? '') === 'codigo' ? 'selected' : '' ?>>CÓDIGO</option>
                                    <option value="abreviatura" <?= ($_GET['campo_pesquisa'] ?? '') === 'abreviatura' ? 'selected' : '' ?>>ABREVIATURA</option>
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
                        <p class="table-note">Selecione para consultar/editar</p>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>CÓDIGO</th>
                                    <th>DESCRIÇÃO</th>
                                    <th>ABREVIATURA</th>
                                    <th style="text-align: center;">AÇÃO</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($TIPO_VEICULO)): ?>
                                    <?php foreach ($TIPO_VEICULO as $TIPO_VEICULOs): ?>
                                    <tr class="clickable-row" data-id="<?= $TIPO_VEICULOs['COD_VEIC'] ?>">
                                        <td><?= htmlspecialchars($TIPO_VEICULOs['COD_VEIC']??'') ?></td>
                                        <td><?= htmlspecialchars($TIPO_VEICULOs['DESCRICAO']??'') ?></td>
                                        <td><?= htmlspecialchars($TIPO_VEICULOs['ABREVIATURA']??'') ?></td>
                                        <td style="text-align: center; white-space: nowrap; gap: 15px; display: flex; justify-content: center;">
                                                <?php if (temPermissao('gerenciar_config')) : ?>
                                                    <a href="cadastro_tipo_veiculo.php?action=edit&id=<?=$TIPO_VEICULOs['COD_VEIC'] ?>" class="btn btn-primary">Editar</a>
                                                <?php endif; ?>
                                                <?php if (temPermissao('gerenciar_config')) : ?>
                                                    <a href="cadastro_tipo_veiculo.php?action=delete&id=<?=$TIPO_VEICULOs['COD_VEIC'] ?>" class= "btn btn-danger btn-excluir-linha">Excluir</a>
                                                <?php endif; ?>
                                            </td>     
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4">Nenhum registro encontrado.</td>
                                    </tr>
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
                                    
               <div id="cadastro_TIPO_VEICULO" class="tab-pane <?= $aba_ativa == 'cadastro_TIPO_VEICULO' ? 'active' : '' ?>">
                    <form id="formTIPO_VEICULO" method="POST" action="cadastro_tipo_veiculo">
                        <input type="hidden" name="COD_VEIC" value="<?= htmlspecialchars($tipo_veiculo_para_editar['COD_VEIC'] ?? '') ?>">

                        <fieldset <?= ($action === 'view') ? 'disabled' : '' ?> style="border: none;">
                            <fieldset class="form-section">
                                <legend>Dados Gerais</legend>
                                <div class="form-row">

                                    <div class="form-group" style="max-width: 100px;">
                                        <label for="COD_VEIC">Código</label>
                                        <input type="text" id="COD_VEIC" value="<?= htmlspecialchars($tipo_veiculo_para_editar['COD_VEIC'] ?? 'NOVO') ?>" readonly>
                                    </div>

                                    <div class="form-group">
                                        <label for="DESCRICAO">Descrição</label>
                                        <input type="text" id="DESCRICAO" name="DESCRICAO" value="<?= htmlspecialchars($tipo_veiculo_para_editar['DESCRICAO'] ?? '') ?>" required>
                                    </div>

                                    <div class="form-group">
                                        <label for="ABREVIATURA">Abreviatura</label>
                                        <input type="text" id="ABREVIATURA" name="ABREVIATURA" value="<?= htmlspecialchars($tipo_veiculo_para_editar['ABREVIATURA'] ?? '') ?>" required>
                                    </div>
                                </div>
                            </fieldset>
                        </fieldset>
                        <div class="form-footer">
                            <?php if ($action !== 'view'): ?>
                                <button type="submit" class="btn btn-primary">Salvar</button> 
                            <?php endif; ?>
                            <a href="cadastro_tipo_veiculo" class="btn btn-danger">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

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