<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../index.php");
    exit;
}

$usuario_nome = htmlspecialchars($_SESSION['username'] ?? 'Usuário');
$usuario_login = htmlspecialchars($_SESSION['login'] ?? '');

require_once '../verificar_permissao.php';
protegerPagina('acessar_clientes');


require_once '../config.php'; 

// INICIALIZAÇÃO E PAGINAÇÃO
$aba_ativa = 'consulta_cliente';
$cliente_para_editar = [];
$clientes = [];
$veiculos_do_cliente = [];
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$message = '';
$message_type = '';
if (!empty($_GET['status'])) {
    switch ($_GET['status']) {
        case 'success':
            $message = '<strong>Sucesso!</strong> Cliente salvo com sucesso.';
            $message_type = 'success';
            break;
        case 'deleted':
            $message = 'Cliente excluído com sucesso.';
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
    $aba_ativa = 'cadastro_cliente';
}

if (($action === 'edit' || $action === 'view') && $id) {
    $aba_ativa = 'cadastro_cliente';
    $stmt = $conn->prepare("SELECT * FROM CLIENTE WHERE CODIGO = ?");
    $stmt->execute([$id]);
    $cliente_para_editar = $stmt->fetch(PDO::FETCH_ASSOC);
    $despachante_nome='';

     if ($cliente_para_editar) {

        $cpfClienteSemPontuacao = preg_replace('/[^0-9]/', '', $cliente_para_editar['CPF_CNPJ'] ?? '');
        $sqlVeic = "SELECT 
                VEICULO.*, 
                COR.DESCRICAO AS NOME_COR, 
                COMBUSTIVEL.DESCRICAO AS NOME_COMBUSTIVEL
            FROM VEICULO 
            INNER JOIN COR ON VEICULO.COD_COR = COR.COD_COR
            INNER JOIN COMBUSTIVEL ON VEICULO.COD_COMBU = COMBUSTIVEL.COD_COMBU
            WHERE REPLACE(REPLACE(REPLACE(CPF_CNPJ, '.', ''), '-', ''), '/', '') = ?
            ORDER BY MODELO";

        $stmtVeiculos = $conn->prepare($sqlVeic);
        $stmtVeiculos->execute([$cpfClienteSemPontuacao]);
        $veiculos_do_cliente = $stmtVeiculos->fetchAll(PDO::FETCH_ASSOC);
     }

     if (!empty($cliente_para_editar['COD_DESP'])) {
            $stmt_desp = $conn->prepare("SELECT NOME FROM DESPACHANTE WHERE COD_DESP = ?");
            $stmt_desp->execute([$cliente_para_editar['COD_DESP']]);
            $despachante = $stmt_desp->fetch(PDO::FETCH_ASSOC);
            if ($despachante) {
                $despachante_nome = $cliente_para_editar['COD_DESP'] . ' - ' . $despachante['NOME'];
            }
        }

    }


if ($action === 'delete' && $id) {
    try {
        $stmt = $conn->prepare("DELETE FROM CLIENTE WHERE CODIGO = ?");
        $stmt->execute([$id]);
        header("Location: cadastro_clientes.php?status=deleted");
        exit;
    } catch (PDOException $e) {
        header("Location: cadastro_clientes.php?status=error&msg=" . urlencode($e->getMessage()));
        exit;
    }
}


// LÓGICA DE SALVAR 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $CODIGO = $_POST['CODIGO'] ?: null;   
    $dados = [
        'TIPO' => $_POST['TIPO'] ?? null,
        'CPF_CNPJ' => $_POST['CPF_CNPJ'] ?? null,
        'NOME' => $_POST['NOME'] ?? null,
        'COD_DESP' => $_POST['COD_DESP'] ?? null,
        'COD_MUNI' => $_POST['COD_MUNI'] ?? null,
        'DATA_NASC' => $_POST['DATA_NASC'] ?? null,
        'NOME_MAE' => $_POST['NOME_MAE']?? null,
        'NATURALIDADE_COD' => $_POST['NATURALIDADE_COD'] ?? null,
        'SEXO' => $_POST['SEXO'] ?? null,
        'ENDERECO' => $_POST['ENDERECO'] ?? null,
        'NUMERO' => $_POST['NUMERO'] ?? null,
        'TELEFONE' => $_POST['TELEFONE'] ?? null,
        'BAIRRO' => $_POST['BAIRRO'] ?? null,
        'EMAIL' => $_POST['EMAIL'] ?? null,
        'COMPLEMENTO' => $_POST['COMPLEMENTO']?? null,
        'CNH' => $_POST['CNH'] ?? null,
        'CEP_LOC' => $_POST['CEP_LOC'] ?? null,
        'TELEFONE_RESIDENCIAL' => $_POST['TELEFONE_RESIDENCIAL'] ?? null,
        'TELEFONE_CELULAR' => $_POST['TELEFONE_CELULAR'] ?? null,
        'NOME_PAI' => $_POST['NOME_PAI'] ?? null,
        'ESTADO_CIVIL' => $_POST['ESTADO_CIVIL'] ?? null,
        'NACIONALIDADE' => $_POST['NACIONALIDADE'] ?? null,
        'TLOG_CODIGO' => $_POST['TLOG_CODIGO'] ?? null,
        'PRO_CODIGO' => $_POST['PRO_CODIGO'] ?? null,
        'ENDCOR_ENDERECO' => $_POST['ENDCOR_ENDERECO'] ?? null,
        'ENDCOR_BAIRRO' => $_POST['ENDCOR_BAIRRO'] ?? null,
        'ENDCOR_COMPLEMENTO' => $_POST['ENDCOR_COMPLEMENTO'] ?? null,
        'ENDCOR_NUMERO' => $_POST['ENDCOR_NUMERO'] ?? null,
        'ENDCOR_CEP' => $_POST['ENDCOR_CEP'] ?? null,
        'ENDCOR_COD_MUNI' => $_POST['ENDCOR_COD_MUNI'] ?? null,
        'ENDCOR_TLOG_CODIGO' => $_POST['ENDCOR_TLOG_CODIGO'] ?? null,
        'OBSERVACAO' => $_POST['OBSERVACAO'] ?? null,
        'OBS_LIVRE' => $_POST['OBS_LIVRE'] ?? null,
        'CLI_ALTERACAO_USERNAME' => $_POST['CLI_ALTERACAO_USERNAME'] ?? null,
        'CLI_ALTERACAO_DATAHORA' => $_POST['CLI_ALTERACAO_DATAHORA'] ?? null,
        'CLI_PROPRIETARIO_NOME' => $_POST['CLI_PROPRIETARIO_NOME'] ?? null,
        'CLI_PROPRIETARIO_CPF' => $_POST['CLI_PROPRIETARIO_CPF'] ?? null,
        'CLI_RATR' => $_POST['CLI_RATR'] ?? null,
        'CLI_CARGO' => $_POST['CLI_CARGO'] ?? null,
        'CLI_CD_LOGIN' => $_POST['CLI_CD_LOGIN'] ?? null,
        'CLI_CD_SENHA' => $_POST['CLI_CD_SENHA'] ?? null,
        'CLI_PESSOA_CONTATO' => $_POST['CLI_PESSOA_CONTATO'] ?? null,
        'CLI_PESSOA_CONTATO_FONE' => $_POST['CLI_PESSOA_CONTATO_FONE'] ?? null,
        'CLI_PESSOA_CONTATO_EMAIL' => $_POST['CLI_PESSOA_CONTATO_EMAIL'] ?? null,
        'CLI_LOJA' => $_POST['CLI_LOJA'] ?? null,
        'CLI_CNH_VENCIMENTO' => $_POST['CLI_CNH_VENCIMENTO'] ?? null,
        'CLI_DT_CADASTRO' => $_POST['CLI_DT_CADASTRO'] ?? null,
        'CLI_CNH_CATEGORIA' => $_POST['CLI_CNH_CATEGORIA'] ?? null,
        'CLI_CNH_UF' => $_POST['CLI_CNH_UF'] ?? null,
        'CLI_CNH_PGU' => $_POST['CLI_CNH_PGU'] ?? null,
        'UF_ENDERECO' => $_POST['UF_ENDERECO'] ?? null,
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
            $sql = "INSERT INTO CLIENTE ($colunas) VALUES ($placeholders)";
            $stmt = $conn->prepare($sql);
            $stmt->execute(array_values($dados));
        } else {
            $set_sql = [];
            foreach (array_keys($dados) as $coluna) {
                $set_sql[] = "$coluna = ?";
            }
            $sql = "UPDATE CLIENTE SET " . implode(', ', $set_sql) . " WHERE CODIGO = ?";
            $valores = array_values($dados);
            $valores[] = $CODIGO;
            $stmt = $conn->prepare($sql);
            $stmt->execute($valores);
        }
        header("Location: cadastro_clientes.php?status=success");
        exit;
    } catch (PDOException $e) {
        die("Erro ao salvar cliente: " . $e->getMessage());
    }
}

// --- LÓGICA DE BUSCA E CONSULTA ---
$sql_base = "SELECT * FROM CLIENTE";
$sql_count_base = "SELECT COUNT(*) FROM CLIENTE";
$params = [];
$where_clauses = [];

if (!empty($_GET['valor_pesquisa'])) {
    $campo = $_GET['campo_pesquisa'];
    $valor = '%' . $_GET['valor_pesquisa'] . '%';

    if ($campo == 'codigo') $where_clauses[] = "CODIGO LIKE ?";
    elseif ($campo == 'nome') $where_clauses[] = "NOME LIKE ?";
    elseif ($campo == 'cpf_cnpj') $where_clauses[] = "CPF_CNPJ LIKE ?";
    
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

$sql_final = $sql_base ." ORDER BY NOME LIMIT $limit OFFSET $offset";
$stmt = $conn->prepare($sql_final);
$stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);

for ($i = 0; $i < count($params); $i++) {
    $stmt->bindValue($i + 1, $params[$i]);
}
$stmt->execute($params);
$clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Lógica  de navegação
$query_string = http_build_query(array_merge($_GET, ['page' => '']));
$link_primeiro = "cadastro_clientes.php?{$query_string}1";
$link_anterior = ($page > 1) ? "cadastro_clientes.php?{$query_string}" . ($page - 1) : "#";
$link_proximo = ($page < $total_pages) ? "cadastro_clientes.php?{$query_string}" . ($page + 1) : "#";
$link_ultimo = "cadastro_clientes.php?{$query_string}" . $total_pages;


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clientes - Lindomar Despachante</title>
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
            <h1>Clientes</h1>

             <?php if (!empty($message)): ?>
                <div class="alert <?= $message_type ?>">
                    <?= $message ?>
                </div>
            <?php endif; ?>

            <?php 
                if (($action === 'edit') && !empty($cliente_para_editar)) :
            ?>
                <div class= "form-tollbar client-header" style="display: flex; justify-content: space-between;">
                    <div class="client-info">
                        <strong><?= htmlspecialchars($cliente_para_editar['NOME'] ?? '')?></strong>
                        CPF/CNPJ<?= htmlspecialchars($cliente_para_editar['CPF_CNPJ']?? '') ?>
                    </div>

                    <div class="client-actions">
                        <a href="cadastro_clientes.php" class="btn btn-danger">Voltar para Consulta</a>
                    </div>
                </div>

            <?php else :?>
                <div class="form-toolbar">
                    <?php if (temPermissao('acessar_clientes')): ?>
                        <a href="cadastro_clientes.php?action=new" class="btn">Novo</a>
                    <?php endif; ?>        
                </div>
            <?php endif; ?>

            <div class="tabs">

                <div class="tab-buttons">
                    <button type="button" class="tab-button <?=$aba_ativa == 'consulta_cliente' ? 'active' : '' ?>" data-tab="consulta_cliente" <?php if ($aba_ativa == 'cadastro_cliente'): ?> style="display: none;" <?php endif;?>>Consulta</button>
                    <button type="button" class="tab-button <?= $aba_ativa == 'cadastro_cliente' ? 'active' : '' ?>" data-tab="cadastro_cliente" <?= $aba_ativa != 'cadastro_cliente' ? 'disabled' : '' ?>>Cadastro</button>
                </div>

                <div class="tab-content">

                    <!-- Consulta -->
                    <div id="consulta_cliente" class="tab-pane <?= $aba_ativa == 'consulta_cliente' ? 'active' : '' ?>">
                        <form method="GET" action="cadastro_clientes.php">
                            <fieldset class="search-box">
                                <legend>Opções de pesquisa</legend>
                                <div class="form-group">
                                    <label for="campo_pesquisa">Campo de Pesquisa:</label>
                                    <select id="campo_pesquisa" name="campo_pesquisa">
                                        <option value="codigo"<?= ($_GET['campo_pesquisa'] ?? '') === 'codigo' ? 'selected' : '' ?>>CÓDIGO</option>
                                        <option value="nome"<?= ($_GET['campo_pesquisa'] ?? '') === 'nome' ? 'selected' : '' ?>>NOME</option>
                                        <option value="cpf_cnpj" <?= ($_GET['campo_pesquisa'] ?? '') === 'cpf_cnpj' ? 'selected' : '' ?>>CPF/CNPJ</option>
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
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>CÓDIGO</th>
                                        <th>TIPO</th>
                                        <th>NOME</th>
                                        <th>CPF/CNPJ</th>
                                        <th style="text-align: center;">AÇÃO</th>
                                      
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($clientes)): ?>
                                        <?php foreach ($clientes as $cliente): ?>
                                        <tr class="clickable-row" data-id="<?= $cliente['CODIGO'] ?>">
                                            <td><?=htmlspecialchars($cliente['CODIGO'] ?? '')?></td>
                                            <td><?=htmlspecialchars($cliente['TIPO'] ?? '')?></td>
                                            <td><?=htmlspecialchars($cliente['NOME'] ?? '')?></td>
                                            <td><?=htmlspecialchars($cliente['CPF_CNPJ'] ?? '')?></td>
                                            <td style="text-align: center; white-space: nowrap; display: flex; justify-content: center; gap: 15px;">
                                                <?php if (temPermissao('acessar_clientes')) : ?>
                                                    <a href="cadastro_clientes.php?action=edit&id=<?= $cliente['CODIGO'] ?>" class="btn btn-primary">Editar</a>
                                                <?php endif; ?>
                                                <?php if (temPermissao('excluir_clientes')) : ?>
                                                    <a href="cadastro_clientes.php?action=delete&id=<?= $cliente['CODIGO'] ?>" class="btn btn-danger btn-excluir-linha">Excluir</a>
                                                <?php endif; ?>
                                            </td>                                       
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                    <tr>
                                        <td colspan="5">Nenhum registro encontrado.</td>
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

                    <!--Cadastro-->
                    <div id="cadastro_cliente" class="tab-pane <?=$aba_ativa == 'cadastro_cliente' ? 'active' : '' ?>">
                        <form id="formCliente" method="POST" action="cadastro_clientes.php">
                            <input type="hidden" name="CODIGO" value="<?= htmlspecialchars($cliente_para_editar['CODIGO'] ?? '') ?>">
                            <fieldset <?= ($action === 'view') ? 'disabled' : '' ?> style="border: none;">
                                <fieldset class="form-section">
                                    <legend>Dados Gerais</legend>
                                    <div class="form-row">
                                        <div class="form-group" style="max-width: 100px;">
                                            <label for="codigo">Código</label>
                                            <input type="text" value="<?= htmlspecialchars($cliente_para_editar['CODIGO'] ?? 'NOVO') ?>" readonly>
                                        </div>

                                        <div class="form-group">
                                            <label for="TIPO">Tipo</label>
                                            <select id="TIPO" name="TIPO">
                                                <option>--Selecione--</option>
                                                <option value="Pessoa Física" <?= ($cliente_para_editar['TIPO'] ?? 'Pessoa Física') == 'Pessoa Física' ? 'selected' : '' ?>>Pessoa Física</option>
                                                <option value="Pessoa Jurídica" <?= ($cliente_para_editar['TIPO'] ?? 'Pessoa Jurídica') == 'Pessoa Jurídica' ? 'selected' : '' ?>>Pessoa Jurídica</option>
                                            </select>
                                        </div>

                                        <div class="form-group" style="flex-grow: 2;">
                                            <label for="CPF_CNPJ">CPF/CNPJ</label>
                                            <input type="text" id="CPF_CNPJ" name="CPF_CNPJ" value="<?= htmlspecialchars($cliente_para_editar['CPF_CNPJ'] ?? '') ?>">
                                        </div>                       
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group" style="flex-grow: 2.5;">
                                            <label for="NOME">Nome</label>
                                            <input type="text" id="NOME" name="NOME" value="<?= htmlspecialchars($cliente_para_editar['NOME'] ?? '') ?>" required>
                                        </div>

                                        <div class="form-group">
                                            <label for="CNH">CNH</label>
                                            <input type="text" id="CNH" name="CNH" value="<?=htmlspecialchars($cliente_para_editar['CNH'] ?? '')?>"required>
                                        </div>

                                        <div class="form-group">
                                            <label for="CLI_CNH_VENCIMENTO">CNH Vencimento</label>
                                            <input type="date" id="CLI_CNH_VENCIMENTO" name="CLI_CNH_VENCIMENTO" value="<?=htmlspecialchars($cliente_para_editar['CLI_CNH_VENCIMENTO'] ?? '')?>"required>
                                        </div>

                                        <div class="form-group" style="max-width: 120px;">
                                            <label for="CLI_CNH_CATEGORIA">CNH Categoria</label>
                                            <select id="CLI_CNH_CATEGORIA" name="CLI_CNH_CATEGORIA">
                                                <option>--Selecione--</option>
                                                <option value="A" <?= ($cliente_para_editar['CLI_CNH_CATEGORIA'] ?? '') == 'A' ? 'selected' : '' ?>>A</option>
                                                <option value="B"<?= ($cliente_para_editar['CLI_CNH_CATEGORIA'] ?? '') == 'B' ? 'selected' : '' ?>>B</option>
                                                <option value="AB"<?= ($cliente_para_editar['CLI_CNH_CATEGORIA'] ?? '') == 'AB' ? 'selected' : '' ?>>AB</option>
                                                <option value="C"<?= ($cliente_para_editar['CLI_CNH_CATEGORIA'] ?? '') == 'C' ? 'selected' : '' ?>>C</option>
                                                <option value="D"<?= ($cliente_para_editar['CLI_CNH_CATEGORIA'] ?? '') == 'D' ? 'selected' : '' ?>>D</option>
                                                <option value="E"<?= ($cliente_para_editar['CLI_CNH_CATEGORIA'] ?? '') == 'E' ? 'selected' : '' ?>>E</option>
                                            </select>
                                        </div>

                                        <div class="form-group" style="max-width: 80px;">
                                            <label for="CLI_CNH_UF">CNH UF</label>
                                            <select id="CLI_CNH_UF" name="CLI_CNH_UF">
                                                <option>UF</option>
                                                <option value="AC"<?=($cliente_para_editar['CLI_CNH_UF'] ?? '')== 'AC'? 'selected' : ''?>>AC</option>
                                                <option value="AL"<?=($cliente_para_editar['CLI_CNH_UF'] ?? '')== 'AL'? 'selected' : ''?>>AL</option>
                                                <option value="AP"<?=($cliente_para_editar['CLI_CNH_UF'] ?? '')== 'AP'? 'selected' : ''?>>AP</option>
                                                <option value="AM"<?=($cliente_para_editar['CLI_CNH_UF'] ?? '')== 'AM'? 'selected' : ''?>>AM</option>
                                                <option value="BA"<?=($cliente_para_editar['CLI_CNH_UF'] ?? '')== 'BA'? 'selected' : ''?>>BA</option>
                                                <option value="CE"<?=($cliente_para_editar['CLI_CNH_UF'] ?? '')== 'CE'? 'selected' : ''?>>CE</option>
                                                <option value="DF"<?=($cliente_para_editar['CLI_CNH_UF'] ?? '')== 'DF'? 'selected' : ''?>>DF</option>
                                                <option value="ES"<?=($cliente_para_editar['CLI_CNH_UF'] ?? '')== 'ES'? 'selected' : ''?>>ES</option>
                                                <option value="GO"<?=($cliente_para_editar['CLI_CNH_UF'] ?? '')== 'GO'? 'selected' : ''?>>GO</option>
                                                <option value="MA"<?=($cliente_para_editar['CLI_CNH_UF'] ?? '')== 'MA'? 'selected' : ''?>>MA</option>
                                                <option value="MT"<?=($cliente_para_editar['CLI_CNH_UF'] ?? '')== 'MT'? 'selected' : ''?>>MT</option>
                                                <option value="MS"<?=($cliente_para_editar['CLI_CNH_UF'] ?? '')== 'MS'? 'selected' : ''?>>MS</option>
                                                <option value="MG"<?=($cliente_para_editar['CLI_CNH_UF'] ?? '')== 'MG'? 'selected' : ''?>>MG</option>
                                                <option value="PA"<?=($cliente_para_editar['CLI_CNH_UF'] ?? '')== 'PA'? 'selected' : ''?>>PA</option>
                                                <option value="PB"<?=($cliente_para_editar['CLI_CNH_UF'] ?? '')== 'PB'? 'selected' : ''?>>PB</option>
                                                <option value="PR"<?=($cliente_para_editar['CLI_CNH_UF'] ?? '')== 'PR'? 'selected' : ''?>>PR</option>
                                                <option value="PE"<?=($cliente_para_editar['CLI_CNH_UF'] ?? '')== 'PE'? 'selected' : ''?>>PE</option>
                                                <option value="PI"<?=($cliente_para_editar['CLI_CNH_UF'] ?? '')== 'PI'? 'selected' : ''?>>PI</option>
                                                <option value="RJ"<?=($cliente_para_editar['CLI_CNH_UF'] ?? '')== 'RJ'? 'selected' : ''?>>RJ</option>
                                                <option value="RN"<?=($cliente_para_editar['CLI_CNH_UF'] ?? '')== 'RN'? 'selected' : ''?>>RN</option>
                                                <option value="RS"<?=($cliente_para_editar['CLI_CNH_UF'] ?? '')== 'RS'? 'selected' : ''?>>RS</option>
                                                <option value="RO"<?=($cliente_para_editar['CLI_CNH_UF'] ?? '')== 'RO'? 'selected' : ''?>>RO</option>
                                                <option value="RR"<?=($cliente_para_editar['CLI_CNH_UF'] ?? '')== 'RR'? 'selected' : ''?>>RR</option>
                                                <option value="SC"<?=($cliente_para_editar['CLI_CNH_UF'] ?? '')== 'SC'? 'selected' : ''?>>SC</option>
                                                <option value="SP"<?=($cliente_para_editar['CLI_CNH_UF'] ?? '')== 'SP'? 'selected' : ''?>>SP</option>
                                                <option value="SE"<?=($cliente_para_editar['CLI_CNH_UF'] ?? '')== 'SE'? 'selected' : ''?>>SE</option>
                                                <option value="TO"<?=($cliente_para_editar['CLI_CNH_UF'] ?? '')== 'TO'? 'selected' : ''?>>TO</option>
                                            </select>
                                        </div>

                                    </div>

                                    <div class="form-row">
                                        <div class="form-group" style="flex-grow: 3;">
                                            <label for="COD_DESP">Cód. Desp. / Despachante</label>
                                            <div class="input-with-button">
                                                <input type="text" id="cod_desp_input" placeholder="Clique no botão para buscar..."value="<?= htmlspecialchars($despachante_nome ?? '') ?>" readonly>
                                                <input type="hidden" id="cod_desp_hidden" name="COD_DESP" >
                                                <button type="button" id="btnAbrirModalDespachante" class="btn-lookup">...</button>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label for="CLI_DT_CADASTRO">Data de Cadastro</label>
                                            <input type="text" id="CLI_DT_CADASTRO" name="CLI_DT_CADASTRO" value="<?= htmlspecialchars($cliente_para_editar['CLI_DT_CADASTRO'] ?? date('d/m/Y')) ?>" readonly>
                                        </div>
                                    </div>
                                </fieldset>
                            </fieldset>

                            <div class="tabs">
                    
                                <div class="tab-buttons">
                                    <button type="button" class="tab-button" data-tab="contato">Contato</button>
                                    <button type="button" class="tab-button" data-tab="observacao">Observação</button>
                                    <button type="button" class="tab-button" data-tab="veiculos_cliente">Veiculos deste Cliente</button>
                                </div>
                                

                                <!--dados cadastrais-->
                                <fieldset <?= ($action === 'view') ? 'disabled' : '' ?> style="border: none;">
                                    <div id="contato" class="tab-pane">
                                        <div class="form-row">
                                                <div class="form-group">
                                                    <label for="municipio">Município</label>
                                                    <input type="text" id="COD_MUNI" name="COD_MUNI" value="<?= htmlspecialchars($cliente_para_editar['COD_MUNI'] ?? '') ?>"readonly>
                                                    
                                                </div>

                                                <div class="form-group" style="max-width: 80px;">
                                                    <label for="uf">UF</label>
                                                    <input type="text" id="UF_ENDERECO" name="UF_ENDERECO" value="<?= htmlspecialchars($cliente_para_editar['UF_ENDERECO'] ?? '') ?>" readonly>                          
                                                </div>
                                        </div>

                                        <div class="form-row">

                                            <div class="form-group" style="max-width: 80px;">
                                                <label for="CEP_LOC">CEP</label>
                                                <input type="text" id="CEP_LOC" name="CEP_LOC" placeholder="Digite o CEP..." value="<?= htmlspecialchars($cliente_para_editar['CEP_LOC'] ?? '') ?>" >
                                            </div>

                                            <div class="form-group" style="flex-grow: 3;">
                                                <label for="ENDERECO">Endereço</label>
                                                <input type="text" id="ENDERECO" name="ENDERECO"value="<?= htmlspecialchars($cliente_para_editar['ENDERECO'] ?? '') ?>"readonly>
                                            </div>

                                            <div class="form-group" style="max-width: 80px;">
                                                <label for="NUMERO">Número</label>
                                                <input type="text" id="NUMERO" name="NUMERO" value="<?= htmlspecialchars($cliente_para_editar['NUMERO'] ?? '') ?>">
                                            </div>
                                        </div>

                                        <div class="form-row">
                                            <div class="form-group">
                                                <label for="COMPLEMENTO">Complemento</label>
                                                <input type="text" id="COMPLEMENTO" name="COMPLEMENTO" value="<?= htmlspecialchars($cliente_para_editar['COMPLEMENTO'] ?? '') ?>">
                                            </div>

                                            <div class="form-group">
                                                <label for="BAIRRO">Bairro</label>
                                                <input type="text" id="BAIRRO" name="BAIRRO" value="<?= htmlspecialchars($cliente_para_editar['BAIRRO'] ?? '') ?>"readonly>
                                            </div>                                 
                                        </div>

                                        <div class="form-row">
                                            <div class="form-group">
                                                <label for="CLI_PESSOA_CONTATO_FONE">Celular</label>
                                                <input type="tel" id="CLI_PESSOA_CONTATO_FONE" name="CLI_PESSOA_CONTATO_FONE" value="<?=htmlspecialchars($cliente_para_editar['CLI_PESSOA_CONTATO_FONE'] ?? '')?>" required>
                                            </div>

                                            <div class="form-group">
                                                <label for="TELEFONE_RESIDENCIAL">Telefone Residencial</label>
                                                <input type="tel" id="TELEFONE_RESIDENCIAL" name="TELEFONE_RESIDENCIAL" value="<?=htmlspecialchars($cliente_para_editar['TELEFONE_RESIDENCIAL'] ?? '')?>">
                                            </div>

                                            <div class="form-group">
                                                <label for="EMAIL">E-mail</label>
                                                <input type="email" id="EMAIL" name="EMAIL" value="<?=htmlspecialchars($cliente_para_editar['EMAIL'] ?? '')?>" >
                                            </div>
                                        </div>

                                        <div class="form-row" style="flex-grow: 3;">
                                            <div class="form-group" style="max-width: 100px;">
                                                <label for="DATA_NASC">Data Nascimento</label>
                                                <input type="date" id="DATA_NASC" name="DATA_NASC" value="<?=htmlspecialchars($cliente_para_editar['DATA_NASC'] ?? '')?>" >
                                            </div>
                                            <div class="form-group">
                                                <label for="NACIONALIDADE">Nacionalidade</label>
                                                <input type="text" id="NACIONALIDADE" name="NACIONALIDADE" value="<?=htmlspecialchars($cliente_para_editar['NACIONALIDADE'] ?? '')?>">
                                            </div>
                                        </div>
                                    </div>
                                </fieldset>

                                <!--dados observaçoes-->
                                <fieldset <?= ($action === 'view') ? 'disabled' : '' ?> style="border: none;">
                                    <div id="observacao" class="tab-pane">
                                        <fieldset class="form-section">
                                            <legend>Observações do Cliente</legend>
                                            <div class="form-row">
                                                <div class="form-group" style="flex-grow: 1;">
                                                    <label for="observacao_livre">Observação Livre</label>
                                                    <textarea id="observacao_livre" name="OBS_LIVRE" rows="4"<?=htmlspecialchars($cliente_para_editar['OBS_LIVRE'] ?? '')?>></textarea>
                                                </div>
                                            </div>

                                            <div class="form-row">
                                                <div class="form-group" style="flex-grow: 1;">
                                                    <label for="observacao">Observação</label>
                                                    <textarea id="observacao" name="OBSERVACAO" rows="4"<?=htmlspecialchars($cliente_para_editar['OBSERVACAO'] ?? '')?>></textarea>
                                                </div>
                                            </div>
                                        </fieldset>
                                    </div>
                                </fieldset>
                                    <!--dados veiculos do cliente-->
                                <fieldset <?= ($action === 'view') ? 'disabled' : '' ?> style="border: none;">                                    
                                        <div id="veiculos_cliente" class="tab-pane">
                                            <fieldset class="form-section">
                                                <legend>Veículos Associados ao Cliente</legend>
                                                <div class="table-container">
                                                    <table class="data-table">
                                                        <thead>
                                                            <tr>
                                                                <th>Código</th>
                                                                <th>Placa</th>
                                                                <th>Marca/Modelo</th>
                                                                <th>Ano Fab./Mod.</th>
                                                                <th>Combústivel</th>
                                                                <th>Cor</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php if(empty($veiculos_do_cliente)): ?>
                                                                <tr>
                                                                    <td colspan="7" style="text-align: center;">Nenhum veículo associado a este cliente.</td>
                                                                </tr>
                                                            <?php else: ?>
                                                                <?php foreach($veiculos_do_cliente as $veiculo): ?>
                                                                    <tr>
                                                                        <td><?= htmlspecialchars($veiculo['CODIGO'] ?? '') ?></td>
                                                                        <td><?= htmlspecialchars($veiculo['PLACA_UF'] ?? '') ?></td>
                                                                        <td><?= htmlspecialchars($veiculo['MODELO'] ?? '') ?></td>
                                                                        <td><?= htmlspecialchars(($veiculo['ANO_FABRI'] ?? '') . '/' . ($veiculo['ANO'] ?? '')) ?></td>
                                                                        <td><?= htmlspecialchars($veiculo['NOME_COR'] ?? '') ?></td>
                                                                        <td><?= htmlspecialchars($veiculo['NOME_COMBUSTIVEL'] ?? '') ?></td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            <?php endif; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </fieldset>
                                        </div> 
                                </fieldset>
                            </div>
                                                            
                            <div class="form-footer">
                                <?php if ($action !== 'view'): ?>
                                    <button type="submit" class="btn btn-primary">Salvar</button> 
                                <?php endif; ?>
                                <a href="cadastro_clientes.php" class="btn btn-danger">Cancelar</a>
                            </div> 
                        </form>  
                    </div>
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

    <!--modal buscar municipio-->
    <div id="modalMunicipio" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2>Buscar Município</h2>
            <input type="text" id="buscaMunicipioInput" placeholder="Digite para buscar pelo nome ou código...">
            <div id="listaResultadosMunicipio" class="results-list"></div>
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