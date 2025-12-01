<?php 

session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../index.php");
    exit;
}

$usuario_nome = htmlspecialchars($_SESSION['username'] ?? 'Usuário');
$usuario_login = htmlspecialchars($_SESSION['login'] ?? '');

require_once '../verificar_permissao.php';
protegerPagina('acessar_despachante');

require_once '../config.php'; 

// INICIALIZAÇÃO E PAGINAÇÃO
$aba_ativa = 'consulta_despachante';
$despachante_para_editar = [];
$despachantes = [];
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$message = '';
$message_type = '';
if (!empty($_GET['status'])) {
    switch ($_GET['status']) {
        case 'success':
            $message = '<strong>Sucesso!</strong> Despachante salvo com sucesso.';
            $message_type = 'success';
            break;
        case 'deleted':
            $message = 'Despachante excluído com sucesso.';
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
    $aba_ativa = 'cadastro_despachante';
}

if (($action === 'edit' || $action === 'view') && $id) {
    $aba_ativa = 'cadastro_despachante';
    $stmt = $conn->prepare("SELECT * FROM DESPACHANTE WHERE COD_DESP = ?");
    $stmt->execute([$id]);
    $despachante_para_editar = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($action === 'delete' && $id) {
    try {
        $stmt = $conn->prepare("DELETE FROM DESPACHANTE WHERE COD_DESP = ?");
        $stmt->execute([$id]);
        header("Location: cadastro_despachante.php?status=deleted");
        exit;
    } catch (PDOException $e) {
        header("Location: cadastro_despachante.php?status=error&msg=" . urlencode($e->getMessage()));
        exit;
    }
}


// LÓGICA DE SALVAR 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $CODIGO = $_POST['COD_DESP'] ?: null;   
    $dados = [
        'NOME' => $_POST['NOME'] ?? null,
        'ENDERECO' => $_POST['ENDERECO'] ?? null,
        'BAIRRO' => $_POST['BAIRRO'] ?? null,
        'TELEFONE' => $_POST['TELEFONE'] ?? null,
        'EMAIL' => $_POST['EMAIL'] ?? null,
        'CREDENCIAL' => $_POST['CREDENCIAL']?? null,
        'CPF_CNPJ' => $_POST['CPF_CNPJ'] ?? null,
        'CIDADE' => $_POST['CIDADE'] ?? null,
        'ESTADO' => $_POST['ESTADO'] ?? null,
        'NUMERO' => $_POST['NUMERO'] ?? null,
        'CEP' => $_POST['CEP'] ?? null,
        'TIPO' => $_POST['TIPO'] ?? null,
        'CELULAR' => $_POST['CELULAR'] ?? null,
        'FAX' => $_POST['FAX']?? null,
        'COMPLEMENTO' => $_POST['COMPLEMENTO'] ?? null,
        'LOGO' => $_POST['LOGO'] ?? null,
        'ESTADO_CIVIL' => $_POST['ESTADO_CIVIL'] ?? null,
        'RG' => $_POST['RG'] ?? null,
        'RG_ORGAO' => $_POST['RG_ORGAO'] ?? null,
        'RG_UF' => $_POST['RG_UF'] ?? null,
        'DETRAN' => $_POST['DETRAN'] ?? null,
        'PROPRIETARIO_NOME' => $_POST['PROPRIETARIO_NOME'] ?? null,
        'PROPRIETARIO_CPF' => $_POST['PROPRIETARIO_CPF'] ?? null,
        'PRINCIPAL' => $_POST['PRINCIPAL'] ?? null,
        'COM_PROCESSO' => $_POST['COM_PROCESSO'] ?? null,
        'COM_EMISSOR' => $_POST['COM_EMISSOR'] ?? null,
        'COM_ENDERECO' => $_POST['COM_ENDERECO'] ?? null,
        'COM_BAIRRO' => $_POST['COM_BAIRRO'] ?? null,
        'COM_COMPLEMENTO' => $_POST['COM_COMPLEMENTO'] ?? null,
        'COM_MUNICIPIO' => $_POST['COM_MUNICIPIO'] ?? null,
        'COM_ESTADO' => $_POST['COM_ESTADO'] ?? null,
        'COM_CEP' => $_POST['COM_CEP'] ?? null,
        'COM_FONE' => $_POST['COM_FONE'] ?? null,
        'COM_FAX' => $_POST['COM_FAX'] ?? null,
        'COM_REGIONAL' => $_POST['COM_REGIONAL'] ?? null,
        'COM_SECCIONAL' => $_POST['COM_SECCIONAL'] ?? null,
        'FICHA_INSCRICAO' => $_POST['FICHA_INSCRICAO'] ?? null,
        'FICHA_CODIGO_UF' => $_POST['FICHA_CODIGO_UF'] ?? null,
        'SEXO' => $_POST['SEXO'] ?? null,
        'NOME_PAI' => $_POST['NOME_PAI'] ?? null,
        'NOME_MAE' => $_POST['NOME_MAE'] ?? null,
        'NATURALIDADE' => $_POST['NATURALIDADE'] ?? null,
        'NATURALIDADE_UF' => $_POST['NATURALIDADE_UF'] ?? null,
        'NACIONALIDADE' => $_POST['NACIONALIDADE'] ?? null,
        'DATA_NASCIMENTO' => $_POST['DATA_NASCIMENTO'] ?? null,
        'GRAU_INSTRUCAO' => $_POST['GRAU_INSTRUCAO'] ?? null,
        'TITULO_ELEITORAL' => $_POST['TITULO_ELEITORAL'] ?? null,
        'TITULO_ELEITORAL_ZONA' => $_POST['TITULO_ELEITORAL_ZONA'] ?? null,
        'TITULO_ELEITORAL_SECAO' => $_POST['TITULO_ELEITORAL_SECAO'] ?? null,
        'FOTO_3X4' => $_POST['FOTO_3X4'] ?? null,
        'SITUACAO' => $_POST['SITUACAO'] ?? null,
        'DATA_INSCRICAO' => $_POST['DATA_INSCRICAO'] ?? null,
        'DATA_AFASTAMENTO' => $_POST['DATA_AFASTAMENTO'] ?? null,
        'ESFERA_ADM' => $_POST['ESFERA_ADM'] ?? null,
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
            $sql = "INSERT INTO DESPACHANTE ($colunas) VALUES ($placeholders)";
            $stmt = $conn->prepare($sql);
            $stmt->execute(array_values($dados));
        } else {
            $set_sql = [];
            foreach (array_keys($dados) as $coluna) {
                $set_sql[] = "$coluna = ?";
            }
            $sql = "UPDATE DESPACHANTE SET " . implode(', ', $set_sql) . " WHERE COD_DESP = ?";
            $valores = array_values($dados);
            $valores[] = $CODIGO;
            $stmt = $conn->prepare($sql);
            $stmt->execute($valores);
        }
        header("Location: cadastro_despachante.php?status=success");
        exit;
    } catch (PDOException $e) {
        die("Erro ao salvar despachante: " . $e->getMessage());
    }
}

// --- LÓGICA DE BUSCA E CONSULTA ---
$sql_base = "SELECT * FROM DESPACHANTE";
$sql_count_base = "SELECT COUNT(*) FROM DESPACHANTE";
$params = [];
$where_clauses = [];

if (!empty($_GET['valor_pesquisa'])) {
    $campo = $_GET['campo_pesquisa'];
    $valor = '%' . $_GET['valor_pesquisa'] . '%';

    if ($campo == 'codigo') $where_clauses[] = "COD_DESP LIKE ?";
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
$despachantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Lógica  de navegação
$query_string = http_build_query(array_merge($_GET, ['page' => '']));
$link_primeiro = "cadastro_despachante.php?{$query_string}1";
$link_anterior = ($page > 1) ? "cadastro_despachante.php?{$query_string}" . ($page - 1) : "#";
$link_proximo = ($page < $total_pages) ? "cadastro_despachante.php?{$query_string}" . ($page + 1) : "#";
$link_ultimo = "cadastro_despachante.php?{$query_string}" . $total_pages;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Despachantes - Lindomar Despachante</title>
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
            <h1>Despachantes</h1>

             <?php if (!empty($message)): ?>
                <div class="alert <?= $message_type ?>">
                    <?= $message ?>
                </div>
            <?php endif; ?>

            <?php 
                if (($action === 'edit') && !empty($despachante_para_editar)) :
            ?>
                <div class= "form-tollbar client-header" style="display: flex; justify-content: space-between;">
                    <div class="client-info">
                       <strong>DESPACHANTE:</strong> <?= htmlspecialchars($despachante_para_editar['NOME'] ?? '')?><br><br>
                        CPF/CNPJ: <?= htmlspecialchars($despachante_para_editar['CPF_CNPJ']?? '') ?>
                    </div>

                    <div class="client-actions">
                        <a href="cadastro_despachante.php" class="btn btn-danger">Voltar para Consulta</a>
                    </div>
                </div>


            <?php else :?>
                <div class="form-toolbar">
                    <?php if (temPermissao('gerenciar_config')): ?>
                        <a href="cadastro_despachante.php?action=new" class="btn">Novo</a>
                    <?php endif; ?>        
                </div>
            <?php endif; ?>

            <div class="tabs">

                <div class="tab-buttons">
                    <button type="button" class="tab-button <?=$aba_ativa == 'consulta_despachante' ? 'active' : '' ?>" data-tab="consulta_despachante" <?php if ($aba_ativa == 'cadastro_despachante'): ?> style="display: none;" <?php endif;?>>Consulta</button>
                    <button type="button" class="tab-button <?= $aba_ativa == 'cadastro_despachante' ? 'active' : '' ?>" data-tab="cadastro_despachante" <?= $aba_ativa != 'cadastro_despachante' ? 'disabled' : '' ?>>Cadastro</button>
                </div>

                <div class="tab-content">

                    <!-- Consulta -->
                    <div id="consulta_despachante" class="tab-pane <?= $aba_ativa == 'consulta_despachante' ? 'active' : '' ?>">
                        <form method="GET" action="cadastro_despachante.php">
                            <fieldset class="search-box">
                                <legend>Opções de pesquisa</legend>
                                <div class="form-group">
                                    <label for="campo_pesquisa">Campo de Pesquisa:</label>
                                    <select id="campo_pesquisa" name="campo_pesquisa">
                                        <option value="codigo" <?= ($_GET['campo_pesquisa'] ?? '') === 'codigo' ? 'selected' : '' ?>>CÓDIGO</option>
                                        <option value="nome" <?= ($_GET['campo_pesquisa'] ?? '') === 'nome' ? 'selected' : '' ?>>NOME</option>
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
                            <p class="table-note">Selecione para Consultar/Editar</p>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>CÓDIGO</th>
                                        <th>NOME</th>
                                        <th>TIPO</th>
                                        <th>CPF/CNPJ</th>
                                        <th>CONTATO</th>
                                        <th style="text-align: center;">AÇÃO</th>        
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($despachantes)): ?>
                                        <?php foreach ($despachantes as $despachante): ?>
                                        <tr class="clickable-row" data-id="<?= $despachante['COD_DESP'] ?>">
                                            <td><?=htmlspecialchars($despachante['COD_DESP'] ?? '')?></td>
                                            <td><?=htmlspecialchars($despachante['NOME'] ?? '')?></td>
                                            <td><?=htmlspecialchars($despachante['TIPO'] ?? '')?></td>
                                            <td><?=htmlspecialchars($despachante['CPF_CNPJ'] ?? '')?></td>
                                            <td><?=htmlspecialchars($despachante['CELULAR'] ?? '')?></td>
                                            <td style="text-align: center; white-space: nowrap; gap: 15px; display: flex; justify-content: center;">
                                                <?php if (temPermissao('gerenciar_config')) : ?>
                                                    <a href="cadastro_despachante.php?action=edit&id=<?=$despachante['COD_DESP'] ?>" class="btn btn-primary">Editar</a>
                                                <?php endif; ?>
                                                <?php if (temPermissao('gerenciar_config')) : ?>
                                                    <a href="cadastro_despachante.php?action=delete&id=<?=$despachante['COD_DESP'] ?>" class= "btn btn-danger btn-excluir-linha">Excluir</a>
                                                <?php endif; ?>
                                            </td>                                              
                                        </tr>
                                        <?php endforeach ?>
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
                    </div>

                    <!--Cadastro-->
                    <div id="cadastro_despachante" class="tab-pane <?=$aba_ativa == 'cadastro_despachante' ? 'active' : '' ?>">
                        <form id="formDespachante" method="POST" action="cadastro_despachante.php">
                            <input type="hidden" name="COD_DESP"  value="<?= htmlspecialchars($despachante_para_editar['COD_DESP'] ?? '') ?>">
                            <fieldset <?= ($action === 'view') ? 'disabled' : '' ?> style="border: none;">
                                <fieldset class="form-section">
                                    <legend>Dados principais</legend>
                                    <div class="form-row">
                                        <div class="form-group" style="max-width: 100px;">
                                            <label>Código</label>
                                            <input type="text" value="<?= htmlspecialchars($despachante_para_editar['COD_DESP'] ?? 'NOVO') ?>" readonly>    
                                        </div>
                                        <div class="form-group" style="flex-grow: 3;">
                                            <label for="NOME">Nome</label>
                                            <input type="text" id="NOME" name="NOME" value="<?= htmlspecialchars($despachante_para_editar['NOME'] ?? '') ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="TIPO">Tipo</label>
                                            <select id="TIPO" name="TIPO">
                                                <option value="Pessoa Física" <?=($despachante_para_editar['TIPO']?? 'Pessoa Física') == 'Pessoa Física' ? 'selected' : '' ?>>Pessoa Física</option>
                                                <option value="Pessoa Jurídica" <?=($despachante_para_editar['TIPO']?? 'Pessoa Jurídica') == 'Pessoa Jurídica' ? 'selected' : '' ?>>Pessoa Jurídica</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="CPF_CNPJ">CPF/CNPJ</label>
                                            <input type="text" id="CPF_CNPJ" name="CPF_CNPJ" value="<?= htmlspecialchars($despachante_para_editar['CPF_CNPJ'] ?? '') ?>">
                                        </div>

                                        <div class="form-group">
                                            <label for="CREDENCIAL">Credencial</label>
                                            <input type="text" id="CREDENCIAL" name="CREDENCIAL" value="<?= htmlspecialchars($despachante_para_editar['CREDENCIAL'] ?? '') ?>">
                                        </div>

                                        <div class="form-group">
                                            <label for="SITUACAO">Situação</label>
                                            <select id="SITUACAO" name="SITUACAO">
                                                <option value="Ativo"<?=($despachante_para_editar['SITUACAO']?? 'Ativo') == 'Ativo' ? 'selected' : '' ?>>Ativo</option>
                                                <option value="Inativo" <?=($despachante_para_editar['SITUACAO']?? 'Inativo') == 'Inativo' ? 'selected' : '' ?>>Inativo</option>
                                            </select>
                                        </div>

                                        <div class="form-group"><label for="PRINCIPAL">Despachante Principal</label>
                                            <select id="PRINCIPAL" name="PRINCIPAL">
                                                <option value="1"<?=($despachante_para_editar['PRINCIPAL']?? '') == '1' ? 'selected' : '' ?>>Sim</option>
                                                <option value="0" <?=($despachante_para_editar['PRINCIPAL']?? '') == '0' ? 'selected' : '' ?>>Não</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="DATA_NASCIMENTO">Data de Nascimento</label>
                                            <input type="date" id="DATA_NASCIMENTO" name="DATA_NASCIMENTO" value="<?= htmlspecialchars($despachante_para_editar['PROPRIETARIO_CPF'] ?? '') ?>">
                                        </div>

                                        <div class="form-group">
                                            <label for="SEXO">Sexo</label>
                                            <select id="SEXO" name="SEXO">
                                                <option value="">--Selecione--</option>
                                                <option value="M"<?=($despachante_para_editar['SEXO'] ?? '')== 'M'? 'selected' : ''?> >Masculino</option>
                                                <option value="F"<?=($despachante_para_editar['SEXO'] ?? '')== 'F'? 'selected' : ''?>>Feminino</option>
                                            </select>
                                        </div>

                                        <div class="form-group">
                                            <label for="ESTADO_CIVIL">Estado Civil</label>
                                            <select id="ESTADO_CIVIL" name="ESTADO_CIVIL">
                                                <option value="">-- Selecione --</option>
                                                <option value="Solteiro" <?=($despachante_para_editar['ESTADO_CIVIL'] ?? '')== 'Solteiro'? 'selected' : ''?> >Solteiro</option>
                                                <option value="Casado"<?=($despachante_para_editar['ESTADO_CIVIL'] ?? '')== 'Casado'? 'selected' : ''?> >Casado</option>
                                                <option value="Divorciado"<?=($despachante_para_editar['ESTADO_CIVIL'] ?? '')== 'Divorciado'? 'selected' : ''?> >Divorciado</option>
                                                <option value="Viúvo"<?=($despachante_para_editar['ESTADO_CIVIL'] ?? '')== 'Viúvo'? 'selected' : ''?> >Viúvo</option>
                                            </select>
                                        </div>
                                    </div>
                                </fieldset>
                            </fieldset>
                        
                            <div class="tabs">

                                <div class="tab-buttons">
                                    <button type="button" class="tab-button active" data-tab="dados-cadastrais">Dados cadastrais</button>
                                </div>

                                <!--dados cadastrais-->
                                <fieldset <?= ($action === 'view') ? 'disabled' : '' ?> style="border: none;">
                                    <div id="dados-cadastrais" class="tab-pane active">
                                        <fieldset class="form-section">
                                            <legend>Endereço e Contato</legend>
                                            <div class="form-row">
                                                <div class="form-group" style="max-width: 150px;">
                                                    <label for="CEP">CEP</label>
                                                    <input type="text" id="CEP" name="CEP" placeholder="Digite o CEP..." value="<?= htmlspecialchars($despachante_para_editar['CEP'] ?? '') ?>">
                                                </div>
                                                <div class="form-group" style="flex-grow: 3;">
                                                    <label for="ENDERECO">Endereço</label>
                                                    <input type="text" id="ENDERECO" name="ENDERECO" value="<?= htmlspecialchars($despachante_para_editar['ENDERECO'] ?? '') ?>" readonly>
                                                </div>
                                                <div class="form-group" style="max-width: 100px;">
                                                    <label for="NUMERO">Número</label>
                                                    <input type="text" id="NUMERO" name="NUMERO" value="<?= htmlspecialchars($despachante_para_editar['NUMERO'] ?? '') ?>">
                                                </div>
                                            </div>

                                            <div class="form-row">
                                                <div class="form-group">
                                                    <label for="BAIRRO">Bairro</label>
                                                    <input type="text" id="BAIRRO" name="BAIRRO" value="<?= htmlspecialchars($despachante_para_editar['BAIRRO'] ?? '') ?>" readonly>
                                                </div>

                                                <div class="form-group">
                                                    <label for="CIDADE">Cidade</label>
                                                    <input type="text" id="CIDADE" name="CIDADE" value="<?= htmlspecialchars($despachante_para_editar['CIDADE'] ?? '') ?>" readonly>
                                                </div>

                                                <div class="form-group" style="max-width: 80px;"><label for="ESTADO">UF</label>
                                                    <input type="text" id="ESTADO" name="ESTADO" value="<?= htmlspecialchars($despachante_para_editar['ESTADO'] ?? '') ?>" readonly>
                                                </div>
                                            </div>

                                            <div class="form-row">
                                                <div class="form-group">
                                                    <label for="TELEFONE">Telefone</label>
                                                    <input type="tel" id="TELEFONE" name="TELEFONE" placeholder="(99)9999-9999" value="<?= htmlspecialchars($despachante_para_editar['TELEFONE'] ?? '') ?>">
                                                </div>

                                                <div class="form-group">
                                                    <label for="CELULAR">Celular</label>
                                                    <input type="tel" id="CELULAR" name="CELULAR"placeholder="(99)99999-9999" value="<?= htmlspecialchars($despachante_para_editar['CELULAR'] ?? '') ?>">
                                                </div>

                                                <div class="form-group">
                                                    <label for="EMAIL">E-mail</label>
                                                    <input type="email" id="EMAIL" name="EMAIL" value="<?= htmlspecialchars($despachante_para_editar['EMAIL'] ?? '') ?>">
                                                </div>
                                            </div>

                                            <div class="form-row" >
                                                <div class="form-group" >
                                                    <label for="PROPRIETARIO_NOME">Proprietário Nome</label>
                                                    <input type="text" id="PROPRIETARIO_NOME" name="PROPRIETARIO_NOME" value="<?= htmlspecialchars($despachante_para_editar['PROPRIETARIO_NOME'] ?? '') ?>">
                                                </div>

                                                <div class="form-group" style="max-width: 300px;">
                                                    <label for="PROPRIETARIO_CPF">Proprietário CPF</label>
                                                    <input type="text" id="PROPRIETARIO_CPF" name="PROPRIETARIO_CPF" placeholder="000.000.000-0" value="<?= htmlspecialchars($despachante_para_editar['PROPRIETARIO_CPF'] ?? '') ?>">
                                                </div>
                                            </div>
                                        </fieldset>
                                    </div>
                                </fieldset>
                            </div>

                            <div class="form-footer">
                                <?php if ($action !== 'view'): ?>
                                    <button type="submit" class="btn btn-primary">Salvar</button> 
                                <?php endif; ?>
                                <a href="cadastro_despachante.php" class="btn btn-danger">Cancelar</a>
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