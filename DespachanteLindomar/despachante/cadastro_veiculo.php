<?php 
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../index.php");
    exit;
}

$usuario_nome = htmlspecialchars($_SESSION['username'] ?? 'Usuário');
$usuario_login = htmlspecialchars($_SESSION['login'] ?? '');
require_once '../verificar_permissao.php';
protegerPagina('acessar_veiculos');

require_once '../config.php'; 

$aba_ativa= 'consulta_veiculo';
$veiculo_para_editar = [];
$veiculos=[];
$ipva_historico = [];
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10; 
$offset = ($page - 1) * $limit;
$message = '';
$message_type = '';
if (!empty($_GET['status'])) {
    switch ($_GET['status']) {
        case 'success':
            $message = '<strong>Sucesso!</strong> Veículo salvo com sucesso.';
            $message_type = 'success';
            break;
        case 'deleted':
            $message = 'Veículo excluído com sucesso.';
            $message_type = 'success';
            break;
        case 'error':
            $errorMessage = $_GET['msg'] ?? 'Ocorreu um problema não especificado.';
            $message = '<strong>Erro!</strong> ' . htmlspecialchars(urldecode($errorMessage));
            $message_type = 'error';
            break;
    }
}

$stmt_emplacamento = $conn->prepare("SELECT * FROM EMPLACAMENTO_TIPO");
$stmt_emplacamento->execute();
$tipos_com_dados = $stmt_emplacamento->fetchAll(PDO::FETCH_ASSOC);

$dados_emplacamento_json = [];
foreach ($tipos_com_dados as $tipo) {

    $dados_emplacamento_json[$tipo['EMT_DESCRICAO']] = $tipo;
}

$dados_emplacamento_json['Automoveis_luxo'] = ['EMT_ALIQUOTA' => 3.0];

$stmt_tipos = $conn->prepare("SELECT EMT_CODIGO, EMT_DESCRICAO FROM EMPLACAMENTO_TIPO ORDER BY EMT_DESCRICAO");
$stmt_tipos->execute();
$tipos_emplacamento = $stmt_tipos->fetchAll(PDO::FETCH_ASSOC);


// LÓGICA DE AÇÕES
$action = $_GET['action'] ?? null;
$id = $_GET['id'] ?? null;

if ($action === 'new') {
    $aba_ativa = 'cadastro_veiculo';
}

if (($action === 'edit' || $action === 'view') && $id) {
    $aba_ativa = 'cadastro_veiculo';
    $stmt = $conn->prepare("SELECT * FROM VEICULO WHERE CODIGO = ?");
    $stmt->execute([$id]);
    $veiculo_para_editar = $stmt->fetch(PDO::FETCH_ASSOC);  
    $marca_nome = '';
    $categoria_nome = '';
    $tipo_veic_nome = '';
    $cor_nome = '';
    $especie_nome = '';
    $combustivel_nome = '';
    $tipo_carroceria_nome = '';
    $restricoes_nome = '';
    $cliente_nome = '';

    if ($veiculo_para_editar) {
        function fetchDescription($conn, $tableName, $id, $idColumnName, $descColumnName = "DESCRICAO") {
            $sql = "SELECT DESCRICAO FROM $tableName WHERE $idColumnName = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $id . ' - ' . $result[$descColumnName] : '';
        }

        if (!empty($veiculo_para_editar['COD_CLI_PROPRIETARIO'])) {
            $cliente_nome = fetchDescription($conn, 'CLIENTE', $veiculo_para_editar['COD_CLI_PROPRIETARIO'], 'CODIGO', 'NOME');
        }
        if (!empty($veiculo_para_editar['COD_MARCA'])) {
            $marca_nome = fetchDescription($conn, 'MARCA', $veiculo_para_editar['COD_MARCA'], 'COD_MARCA');
        }
        if (!empty($veiculo_para_editar['COD_CATE'])) {
            $categoria_nome = fetchDescription($conn, 'CATEGORIA', $veiculo_para_editar['COD_CATE'], 'COD_CATE');
        }
        if (!empty($veiculo_para_editar['COD_VEIC'])) {
            $tipo_veic_nome = fetchDescription($conn, 'TIPO_VEICULO', $veiculo_para_editar['COD_VEIC'], 'COD_VEIC');
        }
        if (!empty($veiculo_para_editar['COD_COR'])) {
            $cor_nome = fetchDescription($conn, 'COR', $veiculo_para_editar['COD_COR'], 'COD_COR');
        }
        if (!empty($veiculo_para_editar['COD_ESPE'])) {
            $especie_nome = fetchDescription($conn, 'ESPECIE', $veiculo_para_editar['COD_ESPE'], 'COD_ESPE');
        }
        if (!empty($veiculo_para_editar['COD_COMBU'])) {
            $combustivel_nome = fetchDescription($conn, 'COMBUSTIVEL', $veiculo_para_editar['COD_COMBU'], 'COD_COMBU');
        }
        if (!empty($veiculo_para_editar['COD_CARR'])) {
            $tipo_carroceria_nome = fetchDescription($conn, 'CARROCERIA', $veiculo_para_editar['COD_CARR'], 'COD_CARR');
        }
        if (!empty($veiculo_para_editar['COD_REST'])) {
            $restricoes_nome = fetchDescription($conn, 'RESTRICOES', $veiculo_para_editar['COD_REST'], 'COD_REST');
        }

    }

    $proprietarios_anteriores = []; 
    if ($veiculo_para_editar) {
        $stmt_prop = $conn->prepare("SELECT * FROM proprietarios_anteriores WHERE pan_vei_codigo = ? ORDER BY pan_data_transferencia DESC");
        $stmt_prop->execute([$id]);
        $proprietarios_anteriores = $stmt_prop->fetchAll(PDO::FETCH_ASSOC);
    }
    
}

if ($action === 'delete' && $id) {
    try {
        $stmt = $conn->prepare("DELETE FROM VEICULO WHERE CODIGO = ?");
        $stmt->execute([$id]);
        header("Location: cadastro_veiculo.php?status=deleted");
        exit;
    } catch (PDOException $e) {
        header("Location: cadastro_veiculo.php?status=error&msg=" . urlencode($e->getMessage()));
        exit;
    }
}

// LÓGICA DE SALVAR 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $CODIGO = $_POST['CODIGO'] ?: null;   
    $dados = [
        'COD_COMBU' => $_POST['COD_COMBU'] ?? null,
        'COD_CARR' => $_POST['COD_CARR'] ?? null,
        'COD_VEIC' => $_POST['COD_VEIC'] ?? null,
        'COD_REST' => $_POST['COD_REST'] ?? null,
        'COD_CATE' => $_POST['COD_CATE'] ?? null,
        'COD_MARCA' => $_POST['COD_MARCA'] ?? null,
        'COD_COR' => $_POST['COD_COR']?? null,
        'COD_ESPE' => $_POST['COD_ESPE'] ?? null,
        'CPF_CNPJ' => $_POST['CPF_CNPJ'] ?? null,
        'PLACA_UF' => $_POST['PLACA_UF'] ?? null,
        'MODELO' => $_POST['MODELO'] ?? null,
        'CHASSI' => $_POST['CHASSI'] ?? null,
        'ALONGADO' => $_POST['ALONGADO'] ?? null,
        'REMARCADO' => $_POST['REMARCADO'] ?? null,
        'RENAVAM' => $_POST['RENAVAM']?? null,
        'ANO_FABRI' => $_POST['ANO_FABRI'] ?? null,
        'DATA_AQUISICAO' => $_POST['DATA_AQUISICAO'] ?? null,
        'LUGARES' => $_POST['LUGARES'] ?? null,
        'POTENCIA' => $_POST['POTENCIA'] ?? null,
        'CMT' => $_POST['CMT'] ?? null,
        'CILINDRADA' => $_POST['CILINDRADA'] ?? null,
        'CARGA' => $_POST['CARGA'] ?? null,
        'RTB' => $_POST['RTB'] ?? null,
        'QTD_EIXOS' => $_POST['QTD_EIXOS'] ?? null,
        'PBT' => $_POST['PBT'] ?? null,
        'NUMERO_MOTOR' => $_POST['NUMERO_MOTOR'] ?? null,
        'NUM_CARROCERIA' => $_POST['NUM_CARROCERIA'] ?? null,
        'ANO' => $_POST['ANO'] ?? null,
        'ANT_PROPRIETARIO' => $_POST['ANT_PROPRIETARIO'] ?? null,
        'ANT_CPF_CNPJ' => $_POST['ANT_CPF_CNPJ'] ?? null,
        'ANT_PLACA_UF' => $_POST['ANT_PLACA_UF'] ?? null,
        'ANT_MUNICIPIOCOD' => $_POST['ANT_MUNICIPIOCOD'] ?? null,
        'FISCAL_CNPJ' => $_POST['FISCAL_CNPJ'] ?? null,
        'FISCAL_ESTADO' => $_POST['FISCAL_ESTADO'] ?? null,
        'FISCAL_SERIE' => $_POST['FISCAL_SERIE'] ?? null,
        'FISCAL_URT' => $_POST['FISCAL_URT'] ?? null,
        'FISCAL_IE' => $_POST['FISCAL_IE'] ?? null,
        'FISCAL_DATA' => $_POST['FISCAL_DATA'] ?? null,
        'FISCAL_VALOR' => $_POST['FISCAL_VALOR'] ?? null,
        'ANT_TIPO' => $_POST['ANT_TIPO'] ?? null,
        'ARRENDADO' => $_POST['ARRENDADO'] ?? null,
        'FINANCEIRA' => $_POST['FINANCEIRA'] ?? null,
        'FISCAL_NUM' => $_POST['FISCAL_NUM'] ?? null,
        'PROCEDENCIA' => $_POST['PROCEDENCIA'] ?? null,
        'QUILOMETRAGEM' => $_POST['QUILOMETRAGEM'] ?? null,
        'FISCAL_ENVELOPE' => $_POST['FISCAL_ENVELOPE'] ?? null,
        'PLACA_FINAL' => $_POST['PLACA_FINAL'] ?? null,
        'USER_NOME' => $_POST['USER_NOME'] ?? null,
        'USER_EMAIL' => $_POST['USER_EMAIL'] ?? null,
        'USER_DDD' => $_POST['USER_DDD'] ?? null,
        'USER_FONE' => $_POST['USER_FONE'] ?? null,
        'CONDU_NOME' => $_POST['CONDU_NOME'] ?? null,
        'CONDU_FONE_1' => $_POST['CONDU_FONE_1'] ?? null,
        'CONDU_FONE_2' => $_POST['CONDU_FONE_2'] ?? null,
        'CONDU_EMAIL' => $_POST['CONDU_EMAIL'] ?? null,
        'OBS_LIVRE' => $_POST['OBS_LIVRE'] ?? null,
        'VEI_DT_CADASTRO' => $_POST['VEI_DT_CADASTRO'] ?? null,
        'VEI_ALTERACAO_USERNAME' => $_POST['VEI_ALTERACAO_USERNAME'] ?? null,
        'VEI_ALTERACAO_DATAHORA' => $_POST['VEI_ALTERACAO_DATAHORA'] ?? null,
        'VEI_ANO_LICENCIAMENTO' => $_POST['VEI_ANO_LICENCIAMENTO'] ?? null,
        'VEI_SITUACAO' => $_POST['VEI_SITUACAO'] ?? null,
        'VEI_PSI_CODIGO' => $_POST['VEI_PSI_CODIGO'] ?? null,
        'ANT_MUNICIPIONOME' => $_POST['ANT_MUNICIPIONOME'] ?? null,
        'ANT_MUNICIPIOUF' => $_POST['ANT_MUNICIPIOUF'] ?? null,
        'VEI_LOJ_CODIGO' => $_POST['VEI_LOJ_CODIGO'] ?? null,
        'VEI_DOCUMENTO_PRONTO_EM' => $_POST['VEI_DOCUMENTO_PRONTO_EM	'] ?? null,
        'VEI_TSE_CODIGO' => $_POST['VEI_TSE_CODIGO'] ?? null,
        'ANT_CRV' => $_POST['ANT_CRV'] ?? null,
        'FISCAL_CRV' => $_POST['FISCAL_CRV'] ?? null,
        'VEI_CPF_CNPJ_LOJA' => $_POST['VEI_CPF_CNPJ_LOJA'] ?? null,
        'VEI_BAN_CODIGO_GERAL' => $_POST['VEI_BAN_CODIGO_GERAL'] ?? null,
        'VEI_BAN_CODIGO_REGISTRO' => $_POST['VEI_BAN_CODIGO_REGISTRO'] ?? null,
        'VEI_COD_REST_REGISTRO' => $_POST['VEI_COD_REST_REGISTRO'] ?? null,
        'VEI_RESTRICAO_BANCO' => $_POST['VEI_RESTRICAO_BANCO'] ?? null,
        'VEI_ANT_BAIXA_GRAVAME' => $_POST['VEI_ANT_BAIXA_GRAVAME'] ?? null,
        'VEI_ANT_BANCO' => $_POST['VEI_ANT_BANCO'] ?? null,
        'OBS' => $_POST['OBS'] ?? null,
        'EXIGENCIA' => $_POST['EXIGENCIA'] ?? null,
        'ACESSORIOS_OPCIONAIS' => $_POST['ACESSORIOS_OPCIONAIS'] ?? null,
        'VEI_ESTOQUE_CODIGO' => $_POST['VEI_ESTOQUE_CODIGO'] ?? null,
        'VALOR_AQUISICAO' => $_POST['VALOR_AQUISICAO'] ?? null,
        'BLOCO_VIRGEM' => $_POST['BLOCO_VIRGEM'] ?? null,
        'VEI_VEN_CODIGO' => $_POST['VEI_VEN_CODIGO'] ?? null,
        'VEI_LOJ_COMISSAO' => $_POST['VEI_LOJ_COMISSAO'] ?? null,
        'VEI_VEN_COMISSAO' => $_POST['VEI_VEN_COMISSAOL'] ?? null,
        'CONDU_CLI_CODIGO' => $_POST['CONDU_CLI_CODIGO'] ?? null,
        'CONDU_CPF' => $_POST['CONDU_CPF'] ?? null,
        'CONDU_RG' => $_POST['CONDU_RG'] ?? null,
        'CONDU_ENDERECO' => $_POST['CONDU_ENDERECO'] ?? null,
        'CONDU_NUMERO' => $_POST['CONDU_NUMERO'] ?? null,
        'CONDU_COMPLEMENTO' => $_POST['CONDU_COMPLEMENTO'] ?? null,
        'CONDU_BAIRRO' => $_POST['CONDU_BAIRRO'] ?? null,
        'CONDU_CEP' => $_POST['CONDU_CEP'] ?? null,
        'CONDU_MUNICIPIO' => $_POST['CONDU_MUNICIPIO'] ?? null,
        'CONDU_ESTADO' => $_POST['CONDU_ESTADO'] ?? null,
        'CONDU_TELEFONE' => $_POST['CONDU_TELEFONE'] ?? null,
        'CONDU_CNH' => $_POST['CONDU_CNH'] ?? null,
        'CONDU_CNH_VALIDADE' => $_POST['CONDU_CNH_VALIDADE'] ?? null,
        'CONDU_CNH_UF' => $_POST['CONDU_CNH_UF'] ?? null,
        'NUMERO_ATPVE' => $_POST['NUMERO_ATPVE'] ?? null,
        'CODIGO_SEGURANCA' => $_POST['CODIGO_SEGURANCA'] ?? null,
        'VEI_CONDICAO' => $_POST['VEI_CONDICAO'] ?? null,
        'VEI_TROCA_PLACA' => $_POST['VEI_TROCA_PLACA'] ?? null,
        'VEI_CODIGO_PV' => $_POST['VEI_CODIGO_PV'] ?? null,
        'VEI_GUC_CODIGO' => $_POST['VEI_GUC_CODIGO'] ?? null,
        'VEI_MON_CODIGO' => $_POST['VEI_MON_CODIGO'] ?? null,
        'COD_CLI_PROPRIETARIO' => $_POST['COD_CLI_PROPRIETARIO'] ?? null

    ];

     foreach ($dados as $chave => $valor) {
        if ($valor === '') {
            $dados[$chave] = null;
        }
    }

    try { 
        $veiculo_id = null;

        if (empty($CODIGO)) {
            // --- INSERE NOVO VEÍCULO ---
            $colunas = implode(', ', array_keys($dados));
            $placeholders = implode(', ', array_fill(0, count($dados), '?'));
            $sql = "INSERT INTO VEICULO ($colunas) VALUES ($placeholders)";
            $stmt = $conn->prepare($sql);
            $stmt->execute(array_values($dados));
            $veiculo_id = $conn->lastInsertId();
        } else {
            // --- ATUALIZA VEÍCULO EXISTENTE ---
            $set_sql = [];
            foreach (array_keys($dados) as $coluna) {
                $set_sql[] = "$coluna = ?";
            }
            $sql = "UPDATE VEICULO SET " . implode(', ', $set_sql) . " WHERE CODIGO = ?";
            $valores = array_values($dados);
            $valores[] = $CODIGO;
            $stmt = $conn->prepare($sql);
            $stmt->execute($valores);
            $veiculo_id = $CODIGO;
        }

        // --- SALVA PROPRIETÁRIOS ANTERIORES (com lógica já existente) ---
        if ($veiculo_id) {
            $stmt_delete_prop = $conn->prepare("DELETE FROM proprietarios_anteriores WHERE pan_vei_codigo = ?");
            $stmt_delete_prop->execute([$veiculo_id]);

            if (isset($_POST['pan_nome']) && is_array($_POST['pan_nome'])) {
                $stmt_prop = $conn->prepare(
                    "INSERT INTO proprietarios_anteriores (pan_vei_codigo, pan_nome, pan_cpf_cnpj, pan_cidade, pan_uf, pan_data_transferencia) 
                     VALUES (?, ?, ?, ?, ?, ?)"
                );

                for ($i = 0; $i < count($_POST['pan_nome']); $i++) {
                    $nome = $_POST['pan_nome'][$i];
                    if (!empty($nome)) {
                        $cpf_cnpj = $_POST['pan_cpf_cnpj'][$i];
                        $cidade = $_POST['pan_cidade'][$i];
                        $uf = $_POST['pan_uf'][$i];
                        $data_transferencia = !empty($_POST['pan_data_transferencia'][$i]) ? date('Y-m-d', strtotime($_POST['pan_data_transferencia'][$i])) : null;

                        $stmt_prop->execute([
                            $veiculo_id,
                            $nome,
                            $cpf_cnpj,
                            $cidade,
                            $uf,
                            $data_transferencia
                        ]);
                    }
                }
            }
        }

        header("Location: cadastro_veiculo.php?status=success");
        exit;

    } catch (PDOException $e) { // <-- Fecha o bloco TRY principal
        die("Erro ao salvar o veículo: " . $e->getMessage());
    }
}

// --- LÓGICA DE BUSCA E CONSULTA ---
$sql_base = "SELECT * FROM VEICULO";
$sql_count_base = "SELECT COUNT(*) FROM VEICULO";
$params = [];
$where_clauses = [];

if (!empty($_GET['valor_pesquisa'])) {
    $campo = $_GET['campo_pesquisa'];
    $valor = '%' . $_GET['valor_pesquisa'] . '%';

    if ($campo == 'codigo') $where_clauses[] = "CODIGO LIKE ?";
    elseif ($campo == 'placa_atual') $where_clauses[] = "PLACA_UF LIKE ?";
    elseif ($campo == 'placa_antiga') $where_clauses[] = "PLACA_ANT_UF LIKE ?";
    elseif ($campo == 'proprietario') $where_clauses[] = "ANT_PROPRIETARIO LIKE ?";
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

$sql_final = $sql_base ." ORDER BY CODIGO LIMIT $limit OFFSET $offset";
$stmt = $conn->prepare($sql_final);
$stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);

for ($i = 0; $i < count($params); $i++) {
    $stmt->bindValue($i + 1, $params[$i]);
}
$stmt->execute($params);
$veiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Lógica  de navegação
$query_string = http_build_query(array_merge($_GET, ['page' => '']));
$link_primeiro = "cadastro_veiculo.php?{$query_string}1";
$link_anterior = ($page > 1) ? "cadastro_veiculo.php?{$query_string}" . ($page - 1) : "#";
$link_proximo = ($page < $total_pages) ? "cadastro_veiculo.php?{$query_string}" . ($page + 1) : "#";
$link_ultimo = "cadastro_veiculo.php?{$query_string}" . $total_pages;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Veículos - Lindomar Despachante</title>
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
            <h1>Veículos</h1>
            <?php if (!empty($message)): ?>
                <div class="alert <?= $message_type ?>">
                    <?= $message ?>
                </div>
            <?php endif; ?>

            <?php 
                if (($action === 'edit') && !empty($veiculo_para_editar)) :
            ?>
                <div class= "form-tollbar client-header" style="display: flex; justify-content: space-between;">
                    <div class="client-info">
                        <strong>PLACA: <?= htmlspecialchars($veiculo_para_editar['PLACA_UF'])?></strong> <br><br>
                        CPF/CNPJ: <?= htmlspecialchars($veiculo_para_editar['CPF_CNPJ']) ?>
                    </div>

                    <div class="client-actions">
                        <a href="cadastro_veiculo.php" class="btn btn-danger">Voltar para Consulta</a>
                    </div>
                </div>
                <?php else :?>
                    <div class="form-toolbar">
                        <?php if (temPermissao('criar_editar_veiculos')): ?>
                            <a href="cadastro_veiculo.php?action=new" class="btn">Novo</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            <div class="tabs">

                <div class="tab-buttons">
                    <button type="button" class="tab-button <?= $aba_ativa == 'consulta_veiculo' ? 'active' : '' ?>" data-tab="consulta_veiculo"<?php if ($aba_ativa == 'cadastro_veiculo'): ?> style="display: none;" <?php endif;?>>Consulta</button>               
                    <button type="button" class="tab-button <?= $aba_ativa == 'cadastro_veiculo' ? 'active' : '' ?>" data-tab="cadastro_veiculo" <?= $aba_ativa != 'cadastro_veiculo' ? 'disabled' : '' ?>>Cadastro</button>                   
                    <button type="button" class="tab-button" data-tab="ipva">Cálculo IPVA</button>
                </div>

                <div class="tab-content">
                    <!-- Consulta -->
                    <div id="consulta_veiculo" class="tab-pane <?= $aba_ativa == 'consulta_veiculo' ? 'active' : '' ?>">
                        <form method="GET" action="cadastro_veiculo.php">
                            <fieldset class="search-box">
                                <legend>Opções de pesquisa</legend>
                                <div class="form-group">
                                    <label for="campo_pesquisa">Campo de Pesquisa:</label>
                                    <select id="campo_pesquisa" name="campo_pesquisa">
                                        <option value="codigo"<?= ($_GET['campo_pesquisa'] ?? '') === 'codigo' ? 'selected' : '' ?>>CÓDIGO</option>
                                        <option value="placa_atual"<?= ($_GET['campo_pesquisa'] ?? '') === 'placa_atual' ? 'selected' : '' ?>>PLACA ATUAL</option>
                                        <option value="placa_antiga"<?= ($_GET['campo_pesquisa'] ?? '') === 'placa_antiga' ? 'selected' : '' ?>>PLACA ANTIGA</option>
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
                                        <th>PLACA ATUAL</th>
                                        <th>PLACA ANTERIOR</th>
                                        <th>CPF/CNPJ ATUAL</th>
                                        <th style="text-align: center;">AÇÃO</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($veiculos)): ?>
                                        <?php foreach ($veiculos as $veiculo): ?>
                                        <tr class="clickable-row" data-id="<?= $veiculo['CODIGO'] ?>">
                                            <td><?=htmlspecialchars($veiculo['CODIGO'] ?? '')?></td>
                                            <td><?=htmlspecialchars($veiculo['PLACA_UF'] ?? '')?></td>
                                            <td><?=htmlspecialchars($veiculo['ANT_PLACA_UF'] ?? '')?></td>
                                            <td><?=htmlspecialchars($veiculo['CPF_CNPJ'] ?? '')?></td>
                                             <td style="text-align: center; white-space: nowrap; display: flex; justify-content: center; gap: 15px;">
                                                <?php if (temPermissao('criar_editar_veiculos')) : ?>
                                                    <a href="cadastro_veiculo.php?action=edit&id=<?= $veiculo['CODIGO'] ?>" class="btn btn-primary">Editar</a>
                                                <?php endif; ?>
                                                <?php if (temPermissao('excluir_veiculos')) : ?>
                                                    <a href="cadastro_veiculo.php?action=delete&id=<?= $veiculo['CODIGO'] ?>" class="btn btn-danger btn-excluir-linha">Excluir</a>
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

                    <!--Cadastro-->
                    <div id="cadastro_veiculo" class="tab-pane <?=$aba_ativa == 'cadastro_veiculo' ? 'active' : '' ?>">
                        <form id="formVeiculo" method="POST" action="cadastro_veiculo.php">
                            <input type="hidden" name="CODIGO" value="<?= htmlspecialchars($veiculo_para_editar['CODIGO'] ?? '') ?>">
                            <fieldset <?= ($action === 'view') ? 'disabled' : '' ?> style="border: none;">
                                <fieldset class="form-section">
                                    <legend>Dados Gerais</legend>
                                    <div class="form-row">
                                        <div class="form-group" style="max-width: 100px">
                                            <label>Código</label>
                                            <input type="text" value="<?= htmlspecialchars($veiculo_para_editar['CODIGO'] ?? 'NOVO') ?>" readonly>                                        
                                        </div>                                 

                                        <div class="form-group" >
                                            <label for="VEI_CONDICAO">Condição</label>
                                            <select id="VEI_CONDICAO" name="VEI_CONDICAO">
                                                <option value="">-- Selecione --</option>
                                                <option value="novo" <?=($veiculo_para_editar['VEI_CONDICAO']?? 'novo') == 'novo' ? 'selected' : '' ?>>Novo</option>
                                                <option value="usado" <?=($veiculo_para_editar['VEI_CONDICAO']?? 'usado') == 'usado' ? 'selected' : '' ?>>Usado</option>
                                            </select>
                                        </div>

                                        <div class="form-group" >
                                            <label for="ANT_PLACA_UF">Placa Anterior</label>
                                            <input type="text" id="ANT_PLACA_UF" name="ANT_PLACA_UF" value="<?= htmlspecialchars($veiculo_para_editar ['ANT_PLACA_UF'] ?? '') ?>" required>
                                        </div>

                                        <div class="form-group-checkbox" style="gap: 5px;">
                                            <input type="checkbox" id="mercosul">
                                            <label for="mercosul">Mercosul</label>
                                        </div>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="PLACA_UF">Placa Atual</label>
                                            <input type="text" id="PLACA_UF" name="PLACA_UF" value="<?= htmlspecialchars($veiculo_para_editar ['PLACA_UF'] ?? '')?>">
                                        </div>

                                        <div class="form-group">
                                            <label for="PLACA_FINAL">Final</label>
                                            <input type="text" id="PLACA_FINAL" name="PLACA_FINAL" value="<?= htmlspecialchars($veiculo_para_editar ['PLACA_FINAL'] ?? '')?>">
                                        </div>

                                        <div class="form-group max-width: 80px">
                                            <label for="VEI_TROCA_PLACA">Troca de Placa</label>
                                            <select id="VEI_TROCA_PLACA" name="VEI_TROCA_PLACA">
                                                <option value="">-- Selecione --</option>
                                                <option value="1"<?=($veiculo_para_editar['VEI_TROCA_PLACA']?? '1') == '1' ? 'selected' : '' ?>>Sim</option>
                                                <option value="0" <?=($veiculo_para_editar['VEI_TROCA_PLACA']?? '0') == '0' ? 'selected' : '' ?>>Não</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="marca_modelo">Marca/Modelo</label>
                                            <div class="input-with-button">
                                                <input type="text" id="marca_display"placeholder="Clique no botão para buscar..."value="<?= htmlspecialchars($marca_nome ?? '') ?>"readonly>
                                                <input type="hidden" id="cod_marca_hidden" name="COD_MARCA">    
                                                <button type="button" id="btnAbrirModalMarca" class="btn-lookup">...</button>
                                            </div>
                                        </div>

                                        <div class="form-group" style="flex-grow: 1;">
                                            <label for="RENAVAM">Renavam</label>
                                            <input type="text" id="RENAVAM" name="RENAVAM" value="<?= htmlspecialchars($veiculo_para_editar ['RENAVAM'] ?? '')?>">
                                        </div>

                                        <div class="form-group">
                                            <label for="VEI_DT_CADASTRO">Data de Cadastro</label>
                                            <input type="text" id="VEI_DT_CADASTRO" name="VEI_DT_CADASTRO" value="<?= htmlspecialchars($veiculo_para_editar ['VEI_DT_CADASTRO'] ?? date('d/m/y'))?>" readonly>
                                        </div>         
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="proprietario">Proprietário (Cliente)</label>
                                            <div class="input-with-button">
                                                <input type="text" id="cliente_display" placeholder="Busque pelo cliente..." value="<?= htmlspecialchars($cliente_nome ?? '') ?>" readonly>
                                                <input type="hidden" id="cod_cliente_hidden" name="COD_CLI_PROPRIETARIO" value="<?= htmlspecialchars($veiculo_para_editar['COD_CLI_PROPRIETARIO'] ?? '') ?>">
                                                <button type="button" id="btnAbrirModalCliente" class="btn-lookup">...</button>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label for="cpf_cnpj">CPF</label>
                                            <input type="text" id="cpf_cnpj" name="CPF_CNPJ" value="<?= htmlspecialchars($veiculo_para_editar['CPF_CNPJ'] ?? '') ?>" readonly>
                                        </div>
                

                                        <div class="form-group">
                                            <label for="VEI_SITUACAO">Situação</label>
                                            <select id="VEI_SITUACAO" name="VEI_SITUACAO">
                                                <option value="">-- Selecione --</option>
                                                <option value="Ativo"<?=($veiculo_para_editar['VEI_SITUACAO'] ?? 'Ativo') == 'Ativo' ? 'selected' : '' ?>>Ativo</option>
                                                <option value="Inativo"<?=($veiculo_para_editar['VEI_SITUACAO'] ?? 'Inativo') == 'Inativo' ? 'selected' : '' ?>>Inativo</option>
                                            </select>
                                        </div>
                                    </div>
                                </fieldset>
                            </fieldset>
                        
                            <div class="tabs">

                                <div class="tab-buttons">
                                    <button type="button" class="tab-button active" data-tab="subtab-geral">Geral</button>
                                    <button type="button" class="tab-button" data-tab="subtab-registro">Registro</button>
                                    <button type="button" class="tab-button" data-tab="subtab-adicionais">Adicionais</button>
                                    <!-- <button type="button" class="tab-button" data-tab="subtab-vistorias">Vistorias</button> -->        
                                    <button type="button" class="tab-button" data-tab="subtab-observacoes">Observação Livre</button>
                                    <button type="button" class="tab-button" data-tab="sub_tabprop_ant">Proprietários Anteriores</button>
                                </div>     

                                <!--dados gerais-->                               
                                <div id="subtab-geral" class="tab-pane active">
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="REMARCADO">Remarcado</label>
                                            <select id="REMARCADO" name="REMARCADO">
                                                <option value="">-- Selecione --</option>
                                                <option value="1"<?=($veiculo_para_editar['REMARCADO'] ?? '1') == '1' ? 'selected' : '' ?>>Sim</option>
                                                <option value="0"<?=($veiculo_para_editar['REMARCADO'] ?? '0') == '0' ? 'selected' : '' ?>>Não</option>
                                            </select>                                          
                                        </div>

                                        <div class="form-group">
                                            <label for="alongado">Alongado</label>
                                            <select id="alongado" name="alongado">
                                                <option value="">-- Selecione --</option>
                                                <option value="1"<?=($veiculo_para_editar['ALONGADO'] ?? '1') == '1' ? 'selected' : '' ?>>Sim</option>
                                                <option value="0"<?=($veiculo_para_editar['ALONGADO'] ?? '0') == '0' ? 'selected' : '' ?>>Não</option>
                                            </select>
                                        </div>

                                        <div class="form-group" style="max-width: 100px;">
                                            <label for="NUMERO_MOTOR">Número Motor</label>
                                            <input type="text" id="NUMERO_MOTOR" name="NUMERO_MOTOR" value="<?=($veiculo_para_editar['NUMERO_MOTOR'] ?? '')?>">
                                        </div>

                                        <div class="form-group" style="max-width: 80px;">
                                            <label for="ANO_FABRI">Fabricação (Ano)</label>
                                            <input type="text" id="ANO_FABRI" name="ANO_FABRI" value="<?=($veiculo_para_editar['ANO_FABRI'] ?? 'AAAA')?>">
                                        </div>

                                        <div class="form-group" style="max-width: 80px;">
                                            <label for="DATA_AQUISICAO">Data da Aquisição</label>
                                            <input type="date" id="DATA_AQUISICAO" name="DATA_AQUISICAO" value="<?=($veiculo_para_editar['DATA_AQUISICAO'] ?? '')?>">
                                        </div>  

                                    </div>           

                                    <div class="form-row">

                                        <div class="form-group">
                                            <label for="categoria_id">Categoria</label>
                                            <div class="input-with-button">
                                                <input type="text" id="categoria_display" placeholder="Clique para buscar..." value="<?= htmlspecialchars($categoria_nome ?? '') ?>" readonly>
                                                <input type="hidden" id="cod_categoria_hidden" name="COD_CATE"> 
                                                <button type="button" id="btnAbrirModalCategoria" class="btn-lookup">...</button>
                                            </div>
                                        </div>


                                        <div class="form-group">
                                            <label for="tipo_veiculo_id">Tipo</label>
                                            <div class="input-with-button">
                                                <input type="text" id="tipo_display" placeholder="Clique para buscar..." value="<?= htmlspecialchars($tipo_veic_nome ?? '') ?>"readonly>
                                                <input type="hidden" id="cod_tipo_hidden" name="COD_VEIC" >
                                                <button type="button" id="btnAbrirModalTipo" class="btn-lookup">...</button>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label for="cor_id">Cor</label>
                                            <div class="input-with-button">
                                                <input type="text" id="cor_display" placeholder="Clique para buscar..." value="<?= htmlspecialchars($cor_nome ?? '') ?>" readonly>
                                                <input type="hidden" id="cod_cor_hidden" name="COD_COR" >
                                                <button type="button" id="btnAbrirModalCor" class="btn-lookup">...</button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-row">

                                        <div class="form-group">
                                            <label for="especie_id">Espécie</label>
                                            <div class="input-with-button">
                                                <input type="text" id="especie_display" placeholder="Clique para buscar..." value="<?= htmlspecialchars($especie_nome ?? '') ?>" readonly>
                                                <input type="hidden" id="cod_especie_hidden" name="COD_ESPE" >
                                                <button type="button" id="btnAbrirModalEspecie" class="btn-lookup">...</button>
                                            </div>
                                        </div> 

                                        <div class="form-group" style="max-width: 100px;">
                                            <label for="ANO">Ano Modelo</label>
                                            <input type="text" id="ANO" name="ANO" value="<?=($veiculo_para_editar['ANO'] ?? '')?>">
                                        </div>

                                        <div class="form-group" style="max-width: 100px;">
                                            <label for="VEI_ANO_LICENCIAMENTO">Ano Licen.</label>
                                            <input type="text" id="VEI_ANO_LICENCIAMENTO" name="VEI_ANO_LICENCIAMENTO" value="<?=($veiculo_para_editar['VEI_ANO_LICENCIAMENTO'] ?? '')?>">
                                        </div>

                                        <div class="form-group">
                                            <label for="lugares">Lugares</label>
                                            <input type="number" id="LUGARES" name="LUGARES" value="<?=($veiculo_para_editar['LUGARES'] ?? '')?>">
                                        </div>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="combustivel_id">Combustível</label>
                                            <div class="input-with-button">
                                                <input type="text"id="combustivel_display" placeholder="Clique para buscar..." value="<?= htmlspecialchars($combustivel_nome ?? '') ?>"readonly>
                                                <input type="hidden" id="cod_combustivel_hidden" name="COD_COMBU" >
                                                <button type="button" id="btnAbrirModalCombustivel" class="btn-lookup">...</button>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label for="tipo_carroceria_id">Tipo Carroceria</label>
                                            <div class="input-with-button">
                                                <input type="text" id="carroceria_display" placeholder="Clique para buscar..."value="<?= htmlspecialchars($tipo_carroceria_nome ?? '') ?>"readonly>
                                                <input type="hidden" id="cod_carroceria_hidden" name="COD_CARR" >
                                                <button type="button" id="btnAbrirModalCarroceria" class="btn-lookup">...</button>
                                            </div>
                                        </div>
                                        
                                    </div>
                                    
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="PROCEDENCIA">Procedência</label>
                                            <select name="PROCEDENCIA">
                                                <option value="N" <?=($veiculo_para_editar['PROCEDENCIA'] ?? 'N') == 'N' ? 'selected' : '' ?>>NACIONAL</option>
                                                <option value="I" <?=($veiculo_para_editar['PROCEDENCIA'] ?? 'I') == 'I' ? 'selected' : '' ?>>IMPORTADO</option>
                                            </select>
                                        </div>

                                        <div class="form-group"><label for="ARRENDADO">Arrendado</label>
                                            <select name="ARRENDADO">
                                                <option value="0" <?=($veiculo_para_editar['ARRENDADO'] ?? '0') == '0' ? 'selected' : '' ?>>NÃO</option>
                                                <option value="1"<?=($veiculo_para_editar['ARRENDADO'] ?? '1') == '1' ? 'selected' : '' ?>>Sim</option>
                                            </select>
                                        </div>

                                        <div class="form-group" style="flex-grow: 2;">
                                            <label for="restricoes_id">Restrições</label>
                                            <div class="input-with-button">
                                                <input type="text" id="restricoes_display" placeholder="Clique para buscar..."value="<?= htmlspecialchars($restricoes_nome ?? '') ?>" readonly>
                                                <input type="hidden" id="cod_restricoes_hidden" name="COD_REST" value="<?= htmlspecialchars($veiculo_para_editar['COD_REST'] ?? '') ?>">
                                                <button type="button" id="btnAbrirModalRestricoes"class="btn-lookup">...</button>
                                            </div>
                                        </div>
                                    </div>   
                                </div>  
                                                                                                                  

                                <!--dados registros-->
                                <div id="subtab-registro" class="tab-pane">
                                    <fieldset class="form-section">
                                        <legend>Documentação (CRV / ATPV-e)</legend>
                                        <div class="form-row">
                                            <div class="form-group">
                                                <label for="FISCAL_CRV">Número CRV (ATPV-e)</label>
                                                <input type="text" id="FISCAL_CRV" name="FISCAL_CRV" value="<?=($veiculo_para_editar['FISCAL_CRV'] ?? '')?>">
                                            </div>
                                        </div>
                                    </fieldset>                 
                                </div>
                            

                                <!--Adicionais-->
                                <div id="subtab-adicionais" class="tab-pane">
                                    <fieldset class="form-section">
                                        <legend>Valores e Códigos Internos</legend>
                                        <div class="form-row">
                                            <div class="form-group">
                                                <label for="VALOR_AQUISICAO">Valor de Aquisição (R$)</label>
                                                <input type="number" step="0.01" id="VALOR_AQUISICAO" name="VALOR_AQUISICAO" placeholder="Ex: 25000.00" value="<?=($veiculo_para_editar['VALOR_AQUISICAO'] ?? '')?>">
                                            </div>
                                        </div>
                                    </fieldset>

                                    <fieldset class="form-section">
                                        <legend>Observações e Acessórios</legend>
                                        <div class="form-row">
                                            <div class="form-group" style="flex-basis: 100%;">
                                                <label for="ACESSORIOS_OPCIONAIS">Acessórios Opcionais</label>
                                                <textarea id="ACESSORIOS_OPCIONAIS" name="ACESSORIOS_OPCIONAIS" rows="4"><?=htmlspecialchars($veiculo_para_editar['ACESSORIOS_OPCIONAIS'] ?? '')?></textarea>
                                            </div>
                                        </div>
                                        <div class="form-row">
                                            <div class="form-group" style="flex-basis: 100%;">
                                                <label for="OBS">Observações Gerais (OBS)</label>
                                                <textarea id="OBS" name="OBS" rows="4"><?=htmlspecialchars($veiculo_para_editar['OBS'] ?? '')?></textarea>
                                            </div>
                                        </div>
                                        <div class="form-row">
                                            <div class="form-group" style="flex-basis: 100%;">
                                                <label for="EXIGENCIA">Exigências</label>
                                                <textarea id="EXIGENCIA" name="EXIGENCIA" rows="4"><?=htmlspecialchars($veiculo_para_editar['EXIGENCIA'] ?? '')?></textarea>
                                            </div>
                                        </div>
                                    </fieldset>
                                </div>
                             

                                <!--Vistoria-->
                                <!-- <div id="subtab-vistorias" class="tab-pane">
                                    <fieldset class="form-section">
                                        <legend>Dados da Vistoria</legend>
                                        <div class="form-row">
                                            <div class="form-group" style="flex-grow: 2;">
                                                <label for="posto_vistoria_nome">Posto de Vistoria</label>
                                                <div class="input-with-button">
                                                    <input type="text" id="posto_vistoria_nome" name="posto_vistoria_nome" placeholder="Clique para buscar..."disabled>
                                                    <input type="hidden" id="posto_vistoria_id" name="VEI_CODIGO_PV">
                                                    <button type="button" id="btnAbrirModalPosto" class="btn-lookup">...</button>
                                                </div>
                                            </div>

                        
                                        </div>

                                        <div class="form-row">
                                            <div class="form-group">
                                                <label for="VEI_DATA_VISTORIA">Data da Vistoria</label>
                                                <input type="date" id="VEI_DATA_VISTORIA" name="VEI_DATA_VISTORIA">
                                            </div>

                                            <div class="form-group">
                                                <label for="VEI_STATUS_VISTORIA">Resultado</label>
                                                <select id="VEI_STATUS_VISTORIA" name="VEI_STATUS_VISTORIA">
                                                    <option value="">-- Selecione --</option>
                                                    <option value="Aprovado">Aprovado</option>
                                                    <option value="Reprovado">Reprovado</option>
                                                    <option value="Pendente">Pendente</option>
                                                </select>
                                            </div>
                                        </div>
                                    </fieldset>

                                    <div id="modalPostoVistoria" class="modal">
                                        <div class="modal-content">
                                            <span class="close-button">&times;</span>
                                            <h2>Buscar Posto de Vistoria</h2>
                                            <input type="text" id="buscaPostoInput" placeholder="Digite para buscar o posto...">
                                            <div id="listaResultadosPosto" class="results-list"></div>
                                        </div>
                                    </div>
                                </div> -->
                                

                                <!--Observaçao-->
                                <div id="subtab-observacoes" class="tab-pane">
                                    <fieldset class="form-section">
                                        <legend>Observação Livre do Veículo</legend>
                                        <div class="form-row">
                                            <div class="form-group" style="flex-basis: 100%;">
                                                <label for="OBS">Digite abaixo todas as informações relevantes:</label>
                                                <textarea 
                                                    id="OBS" 
                                                    name="OBS" 
                                                    rows="15" 
                                                    placeholder="Ex: Histórico de manutenções, detalhes da aquisição, informações sobre documentação pendente, características especiais do veículo, etc.">
                                                </textarea>
                                            </div>
                                        </div>
                                    </fieldset>
                                </div>

                                <!--Proprietarios Anteriores-->
                                <div id="sub_tabprop_ant" class="tab-pane">
                                    <div class="form-toolbar" style="justify-content: flex-start;">
                                        <?php if ($action !== 'view'): ?>
                                            <button type="button" id="btnAdicionarPropAnterior" class="btn btn-primary">Adicionar</button>
                                            <button type="button" id="btnEditarPropAnterior" class="btn edit" disabled>Editar</button>
                                            <button type="button" id="btnExcluirPropAnterior" class="btn delete" disabled>Excluir</button>
                                        <?php endif; ?>
                                        <a href="cadastro_veiculo.php" class="btn btn-danger">Voltar para Consulta</a>
                                    </div>

                                    <div class="table-container">
                                        <p class="table-note">Selecione um proprietário para Editar ou Excluir</p>
                                        <table class="data-table">
                                            <thead>
                                                <tr>
                                                    <th>NOME</th>
                                                    <th>CPF/CNPJ</th>
                                                    <th>CIDADE / UF</th>
                                                    <th>DATA DE TRANSFERÊNCIA</th>
                                                </tr>
                                            </thead>
                                            <tbody id="tabelaPropAnteriorBody">
                                                <?php if (!empty($proprietarios_anteriores)): ?>
                                                    <?php foreach ($proprietarios_anteriores as $prop): ?>
                                                        <tr class="clickable-row" data-id="<?= htmlspecialchars($prop['pan_codigo']) ?>">
                                                            <td><?= htmlspecialchars($prop['pan_nome']) ?></td>
                                                            <td><?= htmlspecialchars($prop['pan_cpf_cnpj']) ?></td>
                                                            <td><?= htmlspecialchars($prop['pan_cidade'] . ' / ' . $prop['pan_uf']) ?></td>
                                                            <td><?= !empty($prop['pan_data_transferencia']) ? htmlspecialchars(date('d/m/Y', strtotime($prop['pan_data_transferencia']))) : '' ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <tr id="nenhumPropAnterior">
                                                        <td colspan="4" style="text-align: center;">Nenhum proprietário anterior cadastrado.</td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>

                                    <div id="modalPropAnterior" class="modal">
                                        <div class="modal-content">
                                            <span class="close-button">&times;</span>
                                            <h2 id="modalPropAnteriorTitulo">Adicionar Proprietário Anterior</h2>
                                            <form id="formPropAnterior">
                                                <input type="hidden" name="pan_codigo" id="pan_codigo">
                                                <input type="text" value="<?= htmlspecialchars($veiculo_para_editar['pan_codigo'] ?? 'NOVO') ?>" readonly>

                                                <div class="form-row">
                                                    <div class="form-group" style="flex-grow: 2;">
                                                        <label for="pan_nome">Nome Completo</label>
                                                        <input type="text" id="pan_nome" name="pan_nome" value="<?= htmlspecialchars($veiculo_para_editar['pan_nome'] ?? '') ?>">
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="pan_cpf_cnpj">CPF/CNPJ</label>
                                                        <input type="text" id="pan_cpf_cnpj" name="pan_cpf_cnpj" value="<?= htmlspecialchars($veiculo_para_editar['pan_cpf_cnpj'] ?? '') ?>">
                                                    </div>
                                                </div>
                                                <div class="form-row">
                                                    <div class="form-group">
                                                        <label for="pan_cidade">Cidade</label>
                                                        <input type="text" id="pan_cidade" name="pan_cidade" value="<?= htmlspecialchars($veiculo_para_editar['pan_cidade'] ?? '') ?>" >
                                                    </div>
                                                    <div class="form-group" style="max-width: 100px;">
                                                        <label for="pan_uf">UF</label>
                                                        <input type="text" id="pan_uf" name="pan_uf" maxlength="2" value="<?= htmlspecialchars($veiculo_para_editar['pan_uf'] ?? '') ?>">
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="pan_data_transferencia">Data da Transferência</label>
                                                        <input type="date" id="pan_data_transferencia" name="pan_data_transferencia" value="<?= htmlspecialchars($veiculo_para_editar['pan_data_transferencia'] ?? '') ?>" >
                                                    </div>
                                                </div>
                                                <div class="form-footer">
                                                    <button type="submit" class="btn btn-primary">Salvar</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>

                             <div class="form-footer">     
                                <button type="submit" class="btn btn-primary">Salvar</button> 
                                <a href="cadastro_veiculo.php" class="btn btn-danger">Cancelar</a>
                            </div>
                        </form>
                    </div>

                    <!--IPVA-->
                    <div id="ipva" class="tab-pane">
                        <fieldset class="form-section">
                            <legend>Calculadora de IPVA (Base Piauí)</legend>
                            <p class="table-note" style="text-align: center; margin-bottom: 20px;">
                                Selecione o tipo de veículo para carregar as alíquotas correspondentes.
                            </p>

                            <div class="form-row" style="justify-content: center; align-items: flex-end; gap: 15px;">
                                <div class="form-group" style="max-width: 500px;">
                                    <label for="ipva_tipo_veiculo">Tipo de Veículo</label>
                                    <select id="ipva_tipo_veiculo">
                                        <option value="">-- Selecione um Tipo --</option>
                                        <?php foreach ($tipos_emplacamento as $tipo): ?>
                                            <option value="<?= htmlspecialchars($tipo['EMT_DESCRICAO']) ?>">
                                                <?= htmlspecialchars($tipo['EMT_DESCRICAO']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="ipva_valor_venal">Valor Venal (R$)</label>
                                    <input type="text" id="ipva_valor_venal" placeholder="R$ 0,00" class="money-mask">
                                </div>
                            </div>

                            <div class="form-row" style="justify-content: center; align-items: flex-end; gap: 15px; margin-top: 15px;">
                                <div class="form-group">
                                    <label for="ipva_aliquota">Alíquota (%)</label>
                                    <input type="text" id="ipva_aliquota">
                                </div>
                                <div class="form-group">
                                    <label for="ipva_juros">Juros Atraso (% a.m.)</label>
                                    <input type="text" id="ipva_juros">
                                </div>
                                <div class="form-group">
                                    <label for="ipva_multa">Multa Atraso (%)</label>
                                    <input type="text" id="ipva_multa" >
                                </div>
                            </div>
                            
                            <div class="form-footer" style="justify-content: center; margin-top: 20px;">
                                <button type="button" id="btnCalcularIpva" class="btn btn-primary">Calcular IPVA</button>
                            </div>
                        </fieldset>

                        <!-- Resultado do cálculo -->
                        <div id="resultadoIpva" class="resultado-calculo" style="display: none; margin-top: 25px; border-top: 1px solid #ddd; padding-top: 20px; text-align: center;">
                            <h3 style="text-align: center;">Resultado da Estimativa (PI)</h3>
                            <div style="max-width: 400px; margin: 0 auto; font-size: 15px; line-height: 1.6;">
                                <p><strong>Valor Venal:</strong> <span id="res_valor_venal">-/span></p>
                                <p><strong>Tipo de Veículo:</strong> <span id="res_tipo_veiculo">-</span></p>
                                <p><strong>Alíquota:</strong> <span id="res_aliquota">-</span></p>
                                <p><strong>Valor do IPVA:</strong> <span id="res_valor_ipva">-</span></p>
                            </div>
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
    
    <!--modal marca-->
    <div id="modalMarca" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2>Buscar Marca</h2>
            <input type="text" id="buscaMarcaInput" placeholder="Digite para buscar pelo nome ou código...">
            <div id="listaResultadosMarca" class="results-list"></div>
        </div>
    </div>

    <!--modal cliente-->
    <div id="modalCliente" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2>Buscar Cliente</h2>
            <input type="text" id="buscaClienteInput" placeholder="Digite para buscar pelo nome ou código...">
            <div id="listaResultadosCliente" class="results-list"></div>
        </div>
    </div>

    <!--modal categoria-->
    <div id="modalCategoria" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2>Buscar Categoria</h2>
            <input type="text" id="buscaCategoriaInput" placeholder="Digite para buscar pelo nome ou código...">
            <div id="listaResultadosCategoria" class="results-list"></div>
        </div>
    </div>

     <!--modal tipo de veículo-->
    <div id="modalTipo" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2>Buscar Tipo de Veículo</h2>
            <input type="text" id="buscaTipoInput" placeholder="Digite para buscar pelo nome ou código...">
            <div id="listaResultadosTipo" class="results-list"></div>
        </div>
    </div>

     <!--modal cor-->
     <div id="modalCor" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2>Buscar Cor</h2>
            <input type="text" id="buscaCorInput" placeholder="Digite para buscar pelo nome ou código...">
            <div id="listaResultadosCor" class="results-list"></div>
        </div>
    </div>

     <!--modal especie-->
    <div id="modalEspecie" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2>Buscar Espécie</h2>
            <input type="text" id="buscaEspecieInput" placeholder="Digite para buscar pelo nome ou código...">
            <div id="listaResultadosEspecie" class="results-list"></div>
        </div>
    </div>

     <!--modal combustivel-->
    <div id="modalCombustivel" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2>Buscar Combustível</h2>
            <input type="text" id="buscaCombustivelInput" placeholder="Digite para buscar pelo nome ou código...">
            <div id="listaResultadosCombustivel" class="results-list"></div>
        </div>
    </div>

     <!--modal restricoes-->
    <div id="modalRestricoes" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2>Buscar Restrições</h2>
            <input type="text" id="buscaRestricoesInput" placeholder="Digite para buscar pelo nome ou código...">
            <div id="listaResultadosRestricoes" class="results-list"></div>
        </div>
    </div>

     <!--modal Tipo Carroceria-->
    <div id="modalCarroceria" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2>Buscar Tipo de Carroceria</h2>
            <input type="text" id="buscaCarroceriaInput" placeholder="Digite para buscar pelo nome ou código...">
            <div id="listaResultadosCarroceria" class="results-list"></div>
        </div>
    </div>

 
    <script src="../js/script_menu.js"></script>
    <script src="../js/script_main.js"></script>
    <script src="../js/script_modal.js"></script>
    <script src="../js/script_mascara.js"></script>
    <script src="../js/script_prop_anterior.js"></script>
    <script>
        const dadosEmplacamento = <?= json_encode($dados_emplacamento_json); ?>;
    </script>
    <script src="../js/script_ipva.js"></script>

    
</body>
</html>