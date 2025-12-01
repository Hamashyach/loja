<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../index.php");
    exit;
}
$usuario_nome = htmlspecialchars($_SESSION['username'] ?? 'Usuário');
$usuario_login = htmlspecialchars($_SESSION['login'] ?? '');
require_once '../verificar_permissao.php';
protegerPagina('acessar_processos');

require_once '../config.php'; 

// --- INICIALIZAÇÃO DE VARIÁVEIS ---
$aba_ativa = $_GET['tab'] ?? 'consulta_processos';
$processo_para_editar = [];
$pendencias_do_processo = []; 
$processos = [];
$total_records = 0;
$total_pages = 1;
$lista_pendencias = [];
$total_pendencias = 0;
$total_pend_pages = 1;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$message = '';
$message_type = '';
if (!empty($_GET['status'])) {
    switch ($_GET['status']) {
        case 'success':
            $message = '<strong>Sucesso!</strong> Processo salvo com sucesso.';
            $message_type = 'success';
            break;
        case 'deleted':
            $message = 'Processo excluído com sucesso.';
            $message_type = 'success';
            break;
        case 'generated':
            $message = 'Dados carregados da OS! Por favor, complete e salve o processo.';
            $message_type = 'success';
            break;
        case 'error':
            $errorMessage = $_GET['msg'] ?? 'Ocorreu um problema não especificado.';
            $message = '<strong>Erro!</strong> ' . htmlspecialchars(urldecode($errorMessage));
            $message_type = 'error';
            break;
    }
}

function limparValorMonetario($valor) {
    if (empty($valor)) return null;
    $valor = str_replace('.', '', $valor);
    $valor = str_replace(',', '.', $valor);
    return (float) $valor;
}

// --- LÓGICA DE AÇÕES ---
$action = $_GET['action'] ?? null;
$id = $_GET['id'] ?? null;

if (
    $action === 'new' || 
    $action === 'generate_from_os' || 
    (($action === 'edit' || $action === 'view') && $id)
) {
    $aba_ativa = 'cadastro_processos';
}

if ($action === 'generate_from_os') {
    $os_id = filter_input(INPUT_GET, 'os_id', FILTER_VALIDATE_INT);
    $item_id = filter_input(INPUT_GET, 'item_id', FILTER_VALIDATE_INT);

    if ($os_id && $item_id) {
        $aba_ativa = 'cadastro_processos';
  
        $sql_from_os = "
            SELECT 
                os.ORS_CODIGO, os.ORS_VEI_CODIGO, os.ORS_CPF_CNPJ, os.ORS_DEP_CODIGO,
                item.ORI_ITEM, item.ORI_TSE_CODIGO, item.ORI_VLTOTAL,
                c.NOME AS CLIENTE_NOME,
                v.PLACA_UF AS VEICULO_PLACA, v.MODELO AS VEICULO_MODELO, v.RENAVAM AS VEICULO_RENAVAM,
                d.NOME AS DESPACHANTE_NOME,
                ts.TSE_DESCRICAO AS SERVICO_NOME
            FROM ORDEM_SERVICO os
            JOIN ORDEM_SERVICO_ITEM item ON os.ORS_CODIGO = item.ORI_ORS_CODIGO
            LEFT JOIN CLIENTE c ON os.ORS_CPF_CNPJ = c.CPF_CNPJ
            LEFT JOIN VEICULO v ON os.ORS_VEI_CODIGO = v.CODIGO
            LEFT JOIN DESPACHANTE d ON os.ORS_DEP_CODIGO = d.COD_DESP
            LEFT JOIN TIPO_SERVICO ts ON item.ORI_TSE_CODIGO = ts.TSE_CODIGO
            WHERE os.ORS_CODIGO = ? AND item.ORI_ITEM = ?
        ";
        $stmt = $conn->prepare($sql_from_os);
        $stmt->execute([$os_id, $item_id]);
        $dados_os = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($dados_os) {
            $processo_para_editar = [
                'PRS_ORS_CODIGO' => $dados_os['ORS_CODIGO'],
                'PRS_ORI_ITEM'   => $dados_os['ORI_ITEM'],
                'PRS_VEI_CODIGO' => $dados_os['ORS_VEI_CODIGO'],
                'PRS_CPF_CNPJ'   => $dados_os['ORS_CPF_CNPJ'],
                'PRS_DES_CODIGO' => $dados_os['ORS_DEP_CODIGO'],
                'PRS_TSE_CODIGO' => $dados_os['ORI_TSE_CODIGO'],
                'PRS_DATA_EMISSAO' => date('Y-m-d'),
                'PRS_PSI_CODIGO' => 1,
                'PRS_VALOR_TOTAL' => $dados_os['ORI_VLTOTAL'],
                'CLIENTE_NOME' => $dados_os['CLIENTE_NOME'],
                'VEICULO_PLACA' => $dados_os['VEICULO_PLACA'],
                'VEICULO_MODELO' => $dados_os['VEICULO_MODELO'],
                'VEICULO_RENAVAM' => $dados_os['VEICULO_RENAVAM'],
                'DESPACHANTE_NOME' => $dados_os['DESPACHANTE_NOME'],
                'SERVICO_NOME' => $dados_os['SERVICO_NOME'],
            ];
        }
    }
}

if (($action === 'edit' || $action === 'view') && $id) {
    $aba_ativa = 'cadastro_processos';
    $sql_edit = "
        SELECT 
            p.*,
            c.NOME AS CLIENTE_NOME,
            v.PLACA_UF AS VEICULO_PLACA,
            v.MODELO AS VEICULO_MODELO,
            v.RENAVAM AS VEICULO_RENAVAM,
            d.NOME AS DESPACHANTE_NOME,
            s.PSI_DESCRICAO AS SITUACAO_NOME,
            ts.TSE_DESCRICAO AS SERVICO_NOME  -- <== 1. ADICIONADO NOME DO SERVIÇO
        FROM PROCESSO p
        LEFT JOIN CLIENTE c ON p.PRS_CPF_CNPJ = c.CPF_CNPJ
        LEFT JOIN VEICULO v ON p.PRS_VEI_CODIGO = v.CODIGO
        LEFT JOIN DESPACHANTE d ON p.PRS_DES_CODIGO = d.COD_DESP -- <== 2. CORRIGIDO O JOIN DO DESPACHANTE
        LEFT JOIN PROCESSO_SITUACAO s ON p.PRS_PSI_CODIGO = s.PSI_CODIGO
        LEFT JOIN TIPO_SERVICO ts ON p.PRS_TSE_CODIGO = ts.TSE_CODIGO -- <== 3. ADICIONADO JOIN PARA SERVIÇO
        WHERE p.PRS_CODIGO = ?
    ";
    $stmt = $conn->prepare($sql_edit);
    $stmt->execute([$id]);
    $processo_para_editar = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($processo_para_editar) {
        $stmt_pend = $conn->prepare("SELECT * FROM PROCESSO_PENDENCIA WHERE PRP_PRS_CODIGO = ? ORDER BY PRP_CODIGO");
        $stmt_pend->execute([$id]);
        $pendencias_do_processo = $stmt_pend->fetchAll(PDO::FETCH_ASSOC);
    }
}



if ($action === 'delete' && $id) {
    try {
        $stmt = $conn->prepare("DELETE FROM PROCESSO WHERE PRS_CODIGO = ?");
        $stmt->execute([$id]);
        header("Location: cadastro_processos.php?status=deleted");
        exit;
        } catch (PDOException $e) {
            header("Location: cadastro_processos.php?status=error&msg=" . urlencode($e->getMessage())); // <-- CORRIGIDO
            exit;
        }
}

// --- LÓGICA DE SALVAR (INSERT/UPDATE) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn->beginTransaction();
    try {
        $CODIGO = !empty($_POST['PRS_CODIGO']) ? $_POST['PRS_CODIGO'] : null;

        $dados_processo = [
            'PRS_ORS_CODIGO' => $_POST['PRS_ORS_CODIGO'] ?: null,
            'PRS_ORI_ITEM' => $_POST['PRS_ORI_ITEM'] ?: null,
            'PRS_VEI_CODIGO' => $_POST['PRS_VEI_CODIGO'] ?: null,
            'PRS_CPF_CNPJ' => $_POST['PRS_CPF_CNPJ'] ?: null,
            'PRS_DES_CODIGO' => $_POST['PRS_DEP_CODIGO'] ?? null,
            'PRS_TSE_CODIGO' => $_POST['PRS_TSE_CODIGO'] ?: null,
            'PRS_PSI_CODIGO' => $_POST['PRS_PSI_CODIGO'] ?: null,
            'PRS_DATA_EMISSAO' => $_POST['PRS_DATA_EMISSAO'] ?: null,
            'PRS_OBSERVACOES' => $_POST['PRS_OBSERVACOES'] ?: null,
            'PRS_VALOR_TAXAS' => limparValorMonetario($_POST['PRS_VALOR_TAXAS'] ?? null),
            'PRS_VALOR_TOTAL' => limparValorMonetario($_POST['PRS_VALOR_TOTAL'] ?? null),
            'PRS_NUMERO_PROTOCOLO' => $_POST['PRS_NUMERO_PROTOCOLO'] ?: null,
            'PRS_NUMERO_PROCESSO' => $_POST['PRS_NUMERO_PROCESSO'] ?: null,
            'PRS_NUMERO_SELO' => $_POST['PRS_NUMERO_SELO'] ?: null,
            'PRS_LOTE' => $_POST['PRS_LOTE'] ?: null,
            'PRS_DATA_IMPRESSAO' => $_POST['PRS_DATA_IMPRESSAO'] ?: null,
            'PRS_DATA_RECEBIMENTO' => $_POST['PRS_DATA_RECEBIMENTO'] ?: null,
            'PRS_DATA_ENTREGA' => $_POST['PRS_DATA_ENTREGA'] ?: null,
            'PRS_PLACA_ANTERIOR' => $_POST['PRS_PLACA_ANTERIOR'] ?: null,
            'PRS_PLACA_ATUAL' => $_POST['PRS_PLACA_ATUAL'] ?: null,
            'PRS_VALOR_TAXAS' => limparValorMonetario($_POST['PRS_VALOR_TAXAS'] ?? null),
            'PRS_USUARIO_ALT' => $usuario_login,
            'PRS_DATA_ALT' => date('Y-m-d H:i:s')
        ];

        if (empty($CODIGO)) { // INSERT
            $dados_processo['PRS_USUARIO_CAD'] = $usuario_login; 
            
            $colunas = implode(', ', array_keys($dados_processo));
            $placeholders = implode(', ', array_fill(0, count($dados_processo), '?'));
            $sql = "INSERT INTO PROCESSO ($colunas) VALUES ($placeholders)";
            $stmt = $conn->prepare($sql);
            $stmt->execute(array_values($dados_processo));
            $CODIGO = $conn->lastInsertId();
        } else { // UPDATE
          
            unset($dados_processo['PRS_USUARIO_CAD']);

            $set_sql = [];
            foreach (array_keys($dados_processo) as $coluna) {
                $set_sql[] = "$coluna = ?";
            }
            $sql = "UPDATE PROCESSO SET " . implode(', ', $set_sql) . " WHERE PRS_CODIGO = ?";
            $valores = array_values($dados_processo);
            $valores[] = $CODIGO;
            $stmt = $conn->prepare($sql);
            $stmt->execute($valores);
        }

        // Lógica para salvar Pendências
        $stmt_del_pend = $conn->prepare("DELETE FROM PROCESSO_PENDENCIA WHERE PRP_PRS_CODIGO = ?");
        $stmt_del_pend->execute([$CODIGO]);

        if (isset($_POST['prp_motivo']) && is_array($_POST['prp_motivo'])) {
            $sql_pend = "INSERT INTO PROCESSO_PENDENCIA (PRP_PRS_CODIGO, PRP_MOTIVO, PRP_PROVIDENCIA, PRP_DATA_ENTRADA, PRP_DATA_VENCIMENTO, PRP_DATA_SAIDA) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt_pend = $conn->prepare($sql_pend);
            
            foreach ($_POST['prp_motivo'] as $index => $motivo) {
                if (!empty($motivo)) {
                    $stmt_pend->execute([
                        $CODIGO, $motivo,
                        $_POST['prp_providencia'][$index] ?: null,
                        $_POST['prp_data_entrada'][$index] ?: null,
                        $_POST['prp_data_vencimento'][$index] ?: null,
                        $_POST['prp_data_saida'][$index] ?: null
                    ]);
                }
            }
        }

        $conn->commit();
        header("Location: cadastro_processos.php?status=success&id=" . $CODIGO . "&action=edit");
        exit;

    } catch (PDOException $e) {
        $conn->rollBack();
        $errorMessage = urlencode($e->getMessage());
        header("Location: cadastro_processos.php?status=error&msg=" . $errorMessage);
        exit;
    }
}

// --- LÓGICA DE BUSCA E CONSULTA ---
if ($aba_ativa == 'consulta_processos') {
    $sql_base = "SELECT 
            prs.PRS_CODIGO,
            prs.PRS_ORS_CODIGO,
            prs.PRS_VEI_CODIGO,
            prs.PRS_CPF_CNPJ,
            prs.PRS_DES_CODIGO,
            prs.PRS_TSE_CODIGO,
            prs.PRS_OBSERVACOES,
            prs.PRS_NUMERO_PROCESSO,
            prs.PRS_NUMERO_PROTOCOLO,
            prs.PRS_DATA_EMISSAO,
            prs.PRS_PSI_CODIGO,
            prs.PRS_DATA_ENTREGA,
            prs.PRS_LOTE,
            prs.PRS_DATA_IMPRESSAO,
            prs.PRS_DATA_RECEBIMENTO,
            prs.PRS_PLACAS,
            prs.PRS_NUMERO_SELO,
            c.NOME AS CLIENTE_NOME,
            s.PSI_DESCRICAO AS SITUACAO_NOME,
            v.PLACA_UF AS PLACAS
             FROM PROCESSO AS prs 
             INNER JOIN 
             CLIENTE AS c ON prs.PRS_CPF_CNPJ = c.CPF_CNPJ
             INNER JOIN 
             PROCESSO_SITUACAO AS s ON prs.PRS_PSI_CODIGO = s.PSI_CODIGO
             INNER JOIN 
             VEICULO AS v ON prs.PRS_VEI_CODIGO = v.CODIGO";
        $sql_count_base = "SELECT COUNT(*) FROM PROCESSO AS prs INNER JOIN CLIENTE AS c ON prs.PRS_CPF_CNPJ = c.CPF_CNPJ INNER JOIN PROCESSO_SITUACAO AS s ON prs.PRS_PSI_CODIGO = s.PSI_CODIGO INNER JOIN 
                    VEICULO AS v ON prs.PRS_VEI_CODIGO = v.CODIGO";

                    
        $params = [];
        $where_clauses = [];

        if (!empty($_GET['valor_pesquisa'])) {
            $campo = $_GET['campo_pesquisa'];
            $valor = $campo === 'codigo' ? $_GET['valor_pesquisa'] : '%' . $_GET['valor_pesquisa'] . '%';

            if ($campo == 'codigo') $where_clauses[] = "prs.PRS_CODIGO = ?";
            elseif ($campo == 'cpf_cnpj') $where_clauses[] = " prs.PRS_CPF_CNPJ LIKE ?";
            elseif ($campo == 'veiculo') $where_clauses[] = "prs.PRS_PLACAS LIKE ?";
            elseif ($campo == 'situacao') $where_clauses[] = "s.PSI_DESCRICAO LIKE ?";
            
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

        $sql_final = $sql_base ." ORDER BY prs.PRS_CODIGO LIMIT $limit OFFSET $offset";
        $stmt = $conn->prepare($sql_final);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);

        for ($i = 0; $i < count($params); $i++) {
            $stmt->bindValue($i + 1, $params[$i]);
        }
        $stmt->execute($params);
        $processos = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

//PENDENCIAS
if ($aba_ativa == 'consulta_pendentes') {
$pend_page = isset($_GET['pend_page']) ? (int)$_GET['pend_page'] : 1;
$pend_limit = 10;
$pend_offset = ($pend_page - 1) * $pend_limit;

$params_pend = [];
$where_clauses_pend = ["(pp.PRP_DATA_SAIDA IS NULL OR pp.PRP_DATA_SAIDA = '0000-00-00')"];

if (!empty($_GET['pend_valor_pesquisa'])) {
    $campo = $_GET['pend_campo_pesquisa'];
    $valor = '%' . $_GET['pend_valor_pesquisa'] . '%';

   
    if ($campo == 'cliente') {
        $where_clauses_pend[] = "c.NOME LIKE :valor_busca";
    } elseif ($campo == 'placa') {
        $where_clauses_pend[] = "v.PLACA_UF LIKE :valor_busca";
    } elseif ($campo == 'motivo') {
        $where_clauses_pend[] = "pp.PRP_MOTIVO LIKE :valor_busca";
    }
    $params_pend[':valor_busca'] = $valor;
}

$where_sql_pend = " WHERE " . implode(' AND ', $where_clauses_pend);

$sql_count_pend = "
    SELECT COUNT(*) FROM PROCESSO_PENDENCIA pp
    LEFT JOIN PROCESSO p ON pp.PRP_PRS_CODIGO = p.PRS_CODIGO
    LEFT JOIN CLIENTE c ON p.PRS_CPF_CNPJ = c.CPF_CNPJ
    LEFT JOIN VEICULO v ON p.PRS_VEI_CODIGO = v.CODIGO
    $where_sql_pend
";
$total_pend_stmt = $conn->prepare($sql_count_pend);
$total_pend_stmt->execute($params_pend);
$total_pendencias = $total_pend_stmt->fetchColumn();
$total_pend_pages = $pend_limit > 0 ? ceil($total_pendencias / $pend_limit) : 0;

$sql_pendencias = "
    SELECT
        pp.PRP_MOTIVO, pp.PRP_PROVIDENCIA, pp.PRP_DATA_ENTRADA, pp.PRP_DATA_VENCIMENTO,
        p.PRS_CODIGO, c.NOME AS CLIENTE_NOME, v.PLACA_UF
    FROM PROCESSO_PENDENCIA AS pp
    JOIN PROCESSO AS p ON pp.PRP_PRS_CODIGO = p.PRS_CODIGO
    LEFT JOIN CLIENTE AS c ON p.PRS_CPF_CNPJ = c.CPF_CNPJ
    LEFT JOIN VEICULO AS v ON p.PRS_VEI_CODIGO = v.CODIGO
    $where_sql_pend
    ORDER BY pp.PRP_DATA_VENCIMENTO ASC
    LIMIT :limit OFFSET :offset
";

$stmt_pendencias = $conn->prepare($sql_pendencias);


foreach ($params_pend as $key => &$val) {
    $stmt_pendencias->bindParam($key, $val);
}

$stmt_pendencias->bindValue(':limit', $pend_limit, PDO::PARAM_INT);
$stmt_pendencias->bindValue(':offset', $pend_offset, PDO::PARAM_INT);

$stmt_pendencias->execute();
$lista_pendencias = $stmt_pendencias->fetchAll(PDO::FETCH_ASSOC);

// NAVEGAÇÃO DAS PENDÊNCIAS
$params_nav_pend = $_GET;
$params_nav_pend['tab'] = 'consulta_pendentes'; 
unset($params_nav_pend['pend_page']); 
$query_string_pend = http_build_query($params_nav_pend);

$link_primeiro_pend = "cadastro_processos.php?" . $query_string_pend . "&pend_page=1";
$link_anterior_pend = ($pend_page > 1) ? "cadastro_processos.php?" . $query_string_pend . "&pend_page=" . ($pend_page - 1) : "#";
$link_proximo_pend = ($pend_page < $total_pend_pages) ? "cadastro_processos.php?" . $query_string_pend . "&pend_page=" . ($pend_page + 1) : "#";
$link_ultimo_pend = "cadastro_processos.php?" . $query_string_pend . "&pend_page=" . $total_pend_pages;

}

// NAVEGAÇÃO DA CONSULTA PRINCIPAL
$params_nav_main = $_GET;
$params_nav_main['tab'] = 'consulta_processos'; 
unset($params_nav_main['page']); 
$query_string_main = http_build_query($params_nav_main);

$link_primeiro = "cadastro_processos.php?" . $query_string_main . "&page=1";
$link_anterior = ($page > 1) ? "cadastro_processos.php?" . $query_string_main . "&page=" . ($page - 1) : "#";
$link_proximo = ($page < $total_pages) ? "cadastro_processos.php?" . $query_string_main . "&page=" . ($page + 1) : "#";
$link_ultimo = "cadastro_processos.php?" . $query_string_main . "&page=" . $total_pages;


?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Controle de Processos - Lindomar Despachante</title>
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
            <h1>Controle de Processos</h1>
            <?php if (!empty($message)): ?>
                <div class="alert <?= $message_type ?>">
                    <?= $message ?>
                </div>
            <?php endif; ?>

            <?php 
                if (($action === 'edit') && !empty($processo_para_editar)) :
            ?>

            <div class= "form-tollbar client-header" style="display: flex; justify-content: space-between;">
                    <div class="client-info">
                        Ordem de Serviço: <?= htmlspecialchars($processo_para_editar['PRS_ORS_CODIGO']) ?><br><br>
                        <strong>Nome:</strong> <?= htmlspecialchars($processo_para_editar['CLIENTE_NOME'])?><br><br> <strong>CPF/CNPJ:</strong> <?= htmlspecialchars($processo_para_editar['PRS_CPF_CNPJ']) ?>
                    </div>

                    <div class="client-actions">
                        <a href="cadastro_processos.php" class="btn btn-danger">Voltar para Consulta</a>
                    </div>
                </div>

            <?php else :?>
                <div class="form-toolbar">
                    <?php if (temPermissao('criar_editar_processo')): ?>
                        <a href="cadastro_processos.php?action=new" class="btn">Novo</a>
                    <?php endif; ?>        
                </div>
            <?php endif; ?>


            <div class="tabs">
                <div class="tab-buttons">
                   <button type="button" class="tab-button <?= $aba_ativa == 'consulta_processos' ? 'active' : '' ?>" data-tab="consulta_processos" <?php if ($aba_ativa == 'cadastro_processos'): ?> style="display: none;" <?php endif; ?>>Consulta</button>
                    <button type="button" class="tab-button <?= $aba_ativa == 'cadastro_processos' ? 'active' : '' ?>" data-tab="cadastro_processos" <?= $aba_ativa != 'cadastro_processos' ? 'disabled' : '' ?>>Cadastro</button>
                    <button type="button" class="tab-button <?= $aba_ativa == 'consulta_pendentes' ? 'active' : '' ?>" data-tab="consulta_pendentes" >Processos Pendentes</button>
                </div>
              
                <div class="tab-content">
                    <!-- Consulta -->
                    <div id="consulta_processos" class="tab-pane <?=$aba_ativa == 'consulta_processos' ? 'active' : '' ?>">
                        <form method="GET" action="cadastro_processos.php">
                            <fieldset class="search-box">
                                <input type="hidden" name="tab" value="consulta_processos"> 
                                <legend>Opções de pesquisa</legend>
                                <div class="form-group">
                                    <label for="campo_pesquisa">Campo:</label>
                                    <select id="campo_pesquisa" name="campo_pesquisa">
                                        <option value="codigo"<?= ($_GET['campo_pesquisa'] ?? '') === 'codigo' ? 'selected' : '' ?>>CÓDIGO</option>
                                        <option value="cpf_cnpj"<?= ($_GET['campo_pesquisa'] ?? '') === 'cpf_cnpj' ? 'selected' : '' ?>>CPF/CNPJ</option>
                                        <option value="situacao"<?= ($_GET['campo_pesquisa'] ?? '') === 'situacao' ? 'selected' : '' ?>>SITUAÇÃO</option>
                                    </select>
                                </div>
                                <div class="form-group" style="flex-grow: 2;">
                                    <label for="valor_pesquisa">Parâmetro:</label>
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
                                        <th>DATA ENTREGUE</th>
                                        <th>CLIENTE</th>
                                        <th>CPF/CNPJ</th>
                                        <th>PLACA</th>
                                        <th>SITUAÇÃO</th>
                                        <th style="text-align: center;">AÇÃO</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($processos)): ?>
                                        <?php foreach ($processos as $processo): ?>
                                            <tr class="clickable-row" data-id="<?= $processo['PRS_CODIGO'] ?>">
                                                <td><?= htmlspecialchars($processo['PRS_CODIGO'] ?? '') ?></td>
                                                <td><?= !empty($processo['PRS_DATA_ENTREGA']) ? htmlspecialchars(date('d/m/Y', strtotime($processo['PRS_DATA_ENTREGA']))) : '' ?></td>
                                                <td><?= htmlspecialchars($processo['CLIENTE_NOME'] ?? 'Cliente não encontrado') ?></td>
                                                <td><?= htmlspecialchars($processo['PRS_CPF_CNPJ'] ?? '') ?></td>
                                                <td><?= htmlspecialchars($processo['PLACAS'] ?? 'Placa não encontrada') ?></td>
                                                <td><?= htmlspecialchars($processo['SITUACAO_NOME'] ?? 'Situação não definida') ?></td>
                                                <td style="text-align: center; white-space: nowrap; display: flex; justify-content: center; gap: 15px;">
                                                <?php if (temPermissao('criar_editar_processos')) : ?>
                                                    <a href="cadastro_processos.php?action=edit&id=<?= $processo['PRS_CODIGO'] ?>" class="btn btn-primary">Editar</a>
                                                <?php endif; ?>
                                                <?php if (temPermissao('excluir_processos')) : ?>
                                                    <a href="cadastro_processos.php?action=delete&id=<?= $processo['PRS_CODIGO'] ?>" class="btn btn-danger btn-excluir-linha">Excluir</a>
                                                <?php endif; ?>
                                            </td>                                       
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7">Nenhum registro encontrado.</td>
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
                    </div>

                    <div id="cadastro_processos" class="tab-pane <?= $aba_ativa == 'cadastro_processos' ? 'active' : '' ?>">
                        <form id="formProcesso" method="POST" action="cadastro_processos.php">
                            <input type="hidden" name="PRS_CODIGO" value="<?= htmlspecialchars($processo_para_editar['PRS_CODIGO'] ?? '') ?>">
                                <fieldset <?= ($action === 'view') ? 'disabled' : '' ?> style="border: none;">
                                    <fieldset class="form-section">
                                        <legend>Dados Gerais do Processo</legend>
                                        <div class="form-row">
                                            <div class="form-group">
                                                <label>Código</label>
                                                <input type="text" value="<?= htmlspecialchars($processo_para_editar['PRS_CODIGO'] ?? 'NOVO') ?>" readonly>
                                            </div>
                                            <div class="form-group">
                                                <label>Ordem de Serviço (Origem)</label>
                                                <div class="input-with-button">
                                                    <input type="text" id="os_origem_display" name="PRS_ORS_CODIGO" value="<?= htmlspecialchars($processo_para_editar['PRS_ORS_CODIGO'] ?? '') ?>" readonly>
                                                    <input type="hidden" name="PRS_ORI_ITEM" value="<?= htmlspecialchars($processo_para_editar['PRS_ORI_ITEM'] ?? '') ?>">
                                                    <button type="button" id="btnAbrirModalBuscaOS" class="btn-lookup" <?= !empty($processo_para_editar['PRS_CODIGO']) ? 'disabled' : '' ?>>...</button>
                                                </div>
                                            </div>
                                            <div class="form-group" style="flex-grow: 2;">
                                                <label>Serviço Vinculado</label>
                                                <input type="text" value="<?= htmlspecialchars($processo_para_editar['SERVICO_NOME'] ?? '') ?>" readonly>
                                                <input type="hidden" name="PRS_TSE_CODIGO" value="<?= htmlspecialchars($processo_para_editar['PRS_TSE_CODIGO'] ?? '') ?>">
                                            </div>
                                        <div class="form-group">
                                            <label>Valor Total (R$)</label>
                                            <input type="text" name="PRS_VALOR_TOTAL" class="mascara-moeda" value="<?= number_format($processo_para_editar['PRS_VALOR_TOTAL'] ?? 0, 2, ',', '.') ?>" readonly>
                                        </div>
                                        </div>
                                    </fieldset>

                                    <fieldset class="form-section">
                                        <legend>Partes Envolvidas</legend>
                                        <div class="form-row">
                                            <div class="form-group" style="flex-grow: 2;">
                                                <label>Cliente</label>
                                                <input type="text" value="<?= htmlspecialchars($processo_para_editar['CLIENTE_NOME'] ?? '') ?>" readonly>
                                                <input type="hidden" name="PRS_CPF_CNPJ" value="<?= htmlspecialchars($processo_para_editar['PRS_CPF_CNPJ'] ?? '') ?>">
                                            </div>
                                            <div class="form-group" style="flex-grow: 1;">
                                                <label>Despachante</label>
                                                <input type="text" value="<?= htmlspecialchars($processo_para_editar['DESPACHANTE_NOME'] ?? '') ?>" readonly>
                                                <input type="hidden" name="PRS_DES_CODIGO" value="<?= htmlspecialchars($processo_para_editar['PRS_DES_CODIGO'] ?? '') ?>">
                                            </div>
                                        </div>
                                        <div class="form-row">
                                            <div class="form-group">
                                                <label>Placa Atual</label>
                                                <input type="text" name="PRS_PLACA_ATUAL" value="<?= htmlspecialchars($processo_para_editar['VEICULO_PLACA'] ?? $processo_para_editar['PRS_PLACA_ATUAL'] ?? '') ?>">
                                            </div>
                                            <div class="form-group">
                                                <label>Placa Anterior</label>
                                                <input type="text" name="PRS_PLACA_ANTERIOR" value="<?= htmlspecialchars($processo_para_editar['PRS_PLACA_ANTERIOR'] ?? '') ?>">
                                            </div>
                                            <div class="form-group" style="flex-grow: 1.5;">
                                                <label>Modelo</label>
                                                <input type="text" value="<?= htmlspecialchars($processo_para_editar['VEICULO_MODELO'] ?? '') ?>" readonly>
                                                <input type="hidden" name="PRS_VEI_CODIGO" value="<?= htmlspecialchars($processo_para_editar['PRS_VEI_CODIGO'] ?? '') ?>">
                                            </div>
                                            <div class="form-group">
                                                <label>Renavam</label>
                                                <input type="text" value="<?= htmlspecialchars($processo_para_editar['VEICULO_RENAVAM'] ?? '') ?>" readonly>
                                            </div>
                                        </div>
                                    </fieldset>

                                    <fieldset class="form-section">
                                        <legend>Controle e Protocolos</legend>
                                        <div class="form-row">
                                            <div class="form-group">
                                                <label>Nº Protocolo</label>
                                                <input type="text" name="PRS_NUMERO_PROTOCOLO" value="<?= htmlspecialchars($processo_para_editar['PRS_NUMERO_PROTOCOLO'] ?? '') ?>">
                                            </div>
                                            <div class="form-group">
                                                <label>Nº Selo CRV</label>
                                                <input type="text" name="PRS_NUMERO_SELO" value="<?= htmlspecialchars($processo_para_editar['PRS_NUMERO_SELO'] ?? '') ?>">
                                            </div>
                                            <div class="form-group">
                                                <label>Lote</label>
                                                <input type="text" name="PRS_LOTE" value="<?= htmlspecialchars($processo_para_editar['PRS_LOTE'] ?? '') ?>">
                                            </div>
                                        </div>
                                    </fieldset>
                                    
                                    <fieldset class="form-section">
                                        <legend>Linha do Tempo e Situação</legend>
                                        <div class="form-row">
                                            <div class="form-group">
                                                <label>Data de Emissão</label>
                                                <input type="date" name="PRS_DATA_EMISSAO" value="<?= htmlspecialchars($processo_para_editar['PRS_DATA_EMISSAO'] ?? date('Y-m-d')) ?>">
                                            </div>
                                            <div class="form-group">
                                                <label>Data de Impressão (Doc)</label>
                                                <input type="date" name="PRS_DATA_IMPRESSAO" value="<?= htmlspecialchars($processo_para_editar['PRS_DATA_IMPRESSAO'] ?? '') ?>">
                                            </div>
                                            <div class="form-group">
                                                <label>Data de Recebimento</label>
                                                <input type="date" name="PRS_DATA_RECEBIMENTO" value="<?= htmlspecialchars($processo_para_editar['PRS_DATA_RECEBIMENTO'] ?? '') ?>">
                                            </div>
                                            <div class="form-group">
                                                <label>Data de Entrega (Cliente)</label>
                                                <input type="date" name="PRS_DATA_ENTREGA" value="<?= htmlspecialchars($processo_para_editar['PRS_DATA_ENTREGA'] ?? '') ?>">
                                            </div>
                                            <div class="form-group">
                                                <label>Situação do Processo</label>
                                                <select name="PRS_PSI_CODIGO">
                                                    <option value="1" <?= ($processo_para_editar['PRS_PSI_CODIGO'] ?? '') == '1' ? 'selected' : '' ?>>A Iniciar</option>
                                                    <option value="2" <?= ($processo_para_editar['PRS_PSI_CODIGO'] ?? '') == '2' ? 'selected' : '' ?>>Em Andamento</option>
                                                    <option value="3" <?= ($processo_para_editar['PRS_PSI_CODIGO'] ?? '') == '3' ? 'selected' : '' ?>>Finalizado</option>
                                                </select>
                                            </div>
                                        </div>
                                    </fieldset>
                                    
                                    <fieldset class="form-section">
                                        <legend>Valores</legend>
                                        <div class="form-row">
                                            <div class="form-group">
                                                <label>Valor Taxas (R$)</label>
                                                <input type="text" class="mascara-moeda" name="PRS_VALOR_TAXAS" value="<?= number_format($processo_para_editar['PRS_VALOR_TAXAS'] ?? 0, 2, ',', '.') ?>">
                                            </div>
                                        </div>
                                    </fieldset>

                                    <div class="tabs">
                                        <div class="tab-buttons">
                                            <button type="button" class="tab-button active" data-tab="form_pendencias">Pendências</button>
                                            <button type="button" class="tab-button" data-tab="form_observacao">Observações</button>
                                        </div>

                                        <div id="form_pendencias" class="tab-pane active">
                                            <fieldset class="form-section" style="border:none;">
                                                <div id="pendencias-container">
                                                    <div class="form-row" style="font-weight: bold; margin-bottom: 5px;">
                                                        <div class="form-group" style="flex:2;">Motivo</div>
                                                        <div class="form-group" style="flex:2;">Providência</div>
                                                        <div class="form-group" style="flex:1;">Data Entrada</div>
                                                        <div class="form-group" style="flex:1;">Data Vencimento</div>
                                                        <div class="form-group" style="flex:1;">Data Saída</div>
                                                        <div class="form-group" style="width: 50px;"></div>
                                                    </div>

                                                    <?php if (!empty($pendencias_do_processo)): ?>
                                                        <?php foreach ($pendencias_do_processo as $pend): ?>
                                                        <div class="form-row pendencia-row">
                                                            <div class="form-group" style="flex:2;"><input type="text" name="prp_motivo[]" value="<?= htmlspecialchars($pend['PRP_MOTIVO'] ?? '') ?>"></div>
                                                            <div class="form-group" style="flex:2;"><input type="text" name="prp_providencia[]" value="<?= htmlspecialchars($pend['PRP_PROVIDENCIA'] ?? '') ?>"></div>
                                                            <div class="form-group" style="flex:1;"><input type="date" name="prp_data_entrada[]" value="<?= htmlspecialchars($pend['PRP_DATA_ENTRADA'] ?? '') ?>"></div>
                                                            <div class="form-group" style="flex:1;"><input type="date" name="prp_data_vencimento[]" value="<?= htmlspecialchars($pend['PRP_DATA_VENCIMENTO'] ?? '') ?>"></div>
                                                            <div class="form-group" style="flex:1;"><input type="date" name="prp_data_saida[]" value="<?= htmlspecialchars($pend['PRP_DATA_SAIDA'] ?? '') ?>"></div>
                                                            <div class="form-group" style="width: 80px;"><button type="button" class="btn btn-danger btn-remover-pendencia">Remover</button></div>
                                                        </div>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </div>

                                                <div class="form-footer" style="justify-content: flex-start; padding-top: 10px;">
                                                    <button type="button" id="btnAdicionarPendencia" class="btn">Adicionar Pendência</button>
                                                </div>
                                            

                                                <template id="template-pendencia-row">
                                                    <div class="form-row pendencia-row">
                                                        <div class="form-group" style="flex:2;"><input type="text" name="prp_motivo[]" placeholder="Ex: Falta reconhecimento de firma"></div>
                                                        <div class="form-group" style="flex:2;"><input type="text" name="prp_providencia[]" placeholder="Ex: Contatar cliente"></div>
                                                        <div class="form-group" style="flex:1;"><input type="date" name="prp_data_entrada[]"></div>
                                                        <div class="form-group" style="flex:1;"><input type="date" name="prp_data_vencimento[]"></div>
                                                        <div class="form-group" style="flex:1;"><input type="date" name="prp_data_saida[]"></div>
                                                        <div class="form-group" style="width: 80px;"><button type="button" class="btn btn-danger btn-remover-pendencia">Remover</button></div>
                                                    </div>
                                                </template>
                                            </fieldset>
                                        </div>

                                        <div id="form_observacao" class="tab-pane">
                                            <fieldset class="form-section">
                                                <div class="form-row">
                                                    <div class="form-group" style="width: 100%;">
                                                        <label>Observações do Processo</label>
                                                        <textarea name="PRS_OBSERVACOES" rows="5"><?= htmlspecialchars($processo_para_editar['PRS_OBSERVACOES'] ?? '') ?></textarea>
                                                    </div>
                                                </div>
                                            </fieldset>
                                        </div>
                                    </div>
                                </fieldset>

                                <div class="form-footer">
                                    <?php if ($action !== 'view'): ?>
                                        <button type="submit" class="btn btn-primary">Salvar Processo</button>
                                    <?php endif; ?>
                                    
                                    <?php 
                                    if (!empty($processo_para_editar['PRS_CODIGO'])): ?>
                                        <a href="imprimir_processo.php?id=<?= htmlspecialchars($processo_para_editar['PRS_CODIGO']) ?>" target="_blank" class="btn">Imprimir</a>
                                    <?php endif; ?>

                                    <a href="cadastro_processos.php" class="btn btn-danger">Cancelar</a>
                                </div>
                            </form>
                    </div>                                                       

                    <div id="consulta_pendentes"  class="tab-pane <?= $aba_ativa == 'consulta_pendentes' ? 'active' : '' ?>">
                       <form method="GET" action="cadastro_processos.php">
                          <fieldset class="search-box">
                            <input type="hidden" name="tab" value="consulta_pendentes"> 
                                <legend>Opções de pesquisa de pendências</legend>
                                <div class="form-group">
                                    <label for="pend_campo_pesquisa">Campo:</label>
                                    <select id="pend_campo_pesquisa" name="pend_campo_pesquisa">
                                        <option value="cliente" <?= ($_GET['pend_campo_pesquisa'] ?? '') === 'cliente' ? 'selected' : '' ?>>CLIENTE</option>
                                        <option value="placa" <?= ($_GET['pend_campo_pesquisa'] ?? '') === 'placa' ? 'selected' : '' ?>>PLACA</option>
                                        <option value="motivo" <?= ($_GET['pend_campo_pesquisa'] ?? '') === 'motivo' ? 'selected' : '' ?>>MOTIVO</option>
                                    </select>
                                </div>
                                <div class="form-group" style="flex-grow: 2;">
                                    <label for="pend_valor_pesquisa">Parâmetro:</label>
                                    <input type="text" id="pend_valor_pesquisa" name="pend_valor_pesquisa" value="<?= htmlspecialchars($_GET['pend_valor_pesquisa'] ?? '') ?>">
                                </div>
                                <button type="submit" class="btn btn-primary">Pesquisar</button>
                            </fieldset>
                        </form>
                    
                        <div class="table-container">
                                <p class="table-note">Clique em uma pendência para ir diretamente para o cadastro do processo.</p>
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Cód. Processo</th>
                                            <th>Cliente</th>
                                            <th>Placa</th>
                                            <th>Motivo da Pendência</th>
                                            <th>Providência</th>
                                            <th>Data de Entrada</th>
                                            <th>Data de Vencimento</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($lista_pendencias)): ?>
                                            <?php
                                                $hoje = new DateTime();
                                            ?>
                                            <?php foreach ($lista_pendencias as $pendencia): ?>
                                                <?php
                                                    // Lógica para destacar pendências vencidas
                                                    $estilo_vencido = '';
                                                    if (!empty($pendencia['PRP_DATA_VENCIMENTO'])) {
                                                        $data_vencimento = new DateTime($pendencia['PRP_DATA_VENCIMENTO']);
                                                        if ($data_vencimento < $hoje) {
                                                            $estilo_vencido = 'style="color: red; font-weight: bold;"';
                                                        }
                                                    }
                                                ?>
                                                <tr class="clickable-row" data-href="cadastro_processos.php?action=edit&id=<?= $pendencia['PRS_CODIGO'] ?>">
                                                    <td><?= htmlspecialchars($pendencia['PRS_CODIGO'] ?? '') ?></td>
                                                    <td><?= htmlspecialchars($pendencia['CLIENTE_NOME'] ?? 'N/A') ?></td>
                                                    <td><?= htmlspecialchars($pendencia['PLACA_UF'] ?? 'N/A') ?></td>
                                                    <td><?= htmlspecialchars($pendencia['PRP_MOTIVO'] ?? '') ?></td>
                                                    <td><?= htmlspecialchars($pendencia['PRP_PROVIDENCIA'] ?? '') ?></td>
                                                    <td><?= !empty($pendencia['PRP_DATA_ENTRADA']) ? htmlspecialchars(date('d/m/Y', strtotime($pendencia['PRP_DATA_ENTRADA']))) : '' ?></td>
                                                    <td <?= $estilo_vencido ?>>
                                                        <?= !empty($pendencia['PRP_DATA_VENCIMENTO']) ? htmlspecialchars(date('d/m/Y', strtotime($pendencia['PRP_DATA_VENCIMENTO']))) : '' ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="7">Nenhuma pendência ativa encontrada.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                        </div>
                        <div class="pagination">
                            <span class="paginacao">Página <?= $pend_page ?> de <?= $total_pend_pages ?> (Total de <?= $total_pendencias ?> registros)</span>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!--modal buscar sitacao processo-->
    <div id="modalProcesso" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2>Buscar Processo</h2>
            <input type="text" id="buscaProcessoInput" placeholder="Digite para buscar pelo nome ou código...">
            <div id="listaResultadosProcesso" class="results-list"></div>
        </div>
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

       <!--modal buscar ordem de servico -->
    <div id="modalOrdemServico" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2>Buscar Ordem de Serviço</h2>
            <input class="modal_busca" type="text" id="buscaOrdemServicoInput" placeholder="Busque pelo nome ou código...">
            <div id="listaResultadosOrdemServico" class="results-list"></div>
        </div>
    </div>

    <div id="modalBuscaOS" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <div id="modal-etapa-1">
                <h2>Buscar Ordem de Serviço</h2>
                <input type="text" id="buscaOSInput" placeholder="Busque pelo Nº da OS, nome do cliente ou placa...">
                <div id="listaResultadosOS" class="results-list"></div>
            </div>
            <div id="modal-etapa-2" style="display:none;">
                <h2>Selecione o Serviço para Gerar o Processo</h2>
                <p>Selecione um dos serviços da OS <strong id="osSelecionadaCodigo"></strong>:</p>
                <div id="listaItensOSParaProcesso" class="results-list"></div>
                <button type="button" id="btnVoltarBuscaOS" class="btn btn-secondary">Voltar</button>
            </div>
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


    <script src="../js/script.js"></script>
    <script src="../js/script_menu.js"></script>
    <script src="../js/script_modal.js"></script>
    <script src="../js/script_mascara.js"></script>
    <script src="../js/script_main.js"></script>
    <script src="../js/script.js"></script>

     <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('#consulta_pendentes .clickable-row').forEach(row => {
                row.addEventListener('click', () => {
                    if (row.dataset.href) {
                        window.location.href = row.dataset.href;
                    }
                });
            });
        });
    </script>

    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.getElementById('pendencias-container');
            const btnAdicionar = document.getElementById('btnAdicionarPendencia');
            const template = document.getElementById('template-pendencia-row');

            btnAdicionar.addEventListener('click', () => {
                const clone = template.content.cloneNode(true);
                container.appendChild(clone);
            });

            container.addEventListener('click', (e) => {
                if (e.target && e.target.classList.contains('btn-remover-pendencia')) {
                    e.target.closest('.pendencia-row').remove();
                }
            });
        });
    </script>

    <script>
            document.addEventListener('DOMContentLoaded', function() {
                const modal = document.getElementById('modalBuscaOS');
                if (!modal) return; 

                const btnAbrirModal = document.getElementById('btnAbrirModalBuscaOS');
                const closeModalBtn = modal.querySelector('.close-button');
                const buscaInput = document.getElementById('buscaOSInput');
                const resultadosDiv = document.getElementById('listaResultadosOS');              
                const etapa1 = document.getElementById('modal-etapa-1');
                const etapa2 = document.getElementById('modal-etapa-2');
                const osSelecionadaCodigo = document.getElementById('osSelecionadaCodigo');
                const listaItensDiv = document.getElementById('listaItensOSParaProcesso');
                const btnVoltar = document.getElementById('btnVoltarBuscaOS');

                const buscarOrdensServico = async (query) => {
                    try {
                        const response = await fetch(`api_busca_os.php?query=${encodeURIComponent(query)}`);
                        const data = await response.json();
                        
                        resultadosDiv.innerHTML = '';
                        if (data.length > 0) {
                            data.forEach(os => {
                                const div = document.createElement('div');
                                div.className = 'result-item';
                                div.innerHTML = `<strong>OS Nº ${os.ORS_CODIGO}</strong> - ${os.CLIENTE_NOME || 'Cliente não informado'} (${os.PLACA_UF || 'N/A'})`;
                                div.dataset.osId = os.ORS_CODIGO;
                                resultadosDiv.appendChild(div);
                            });
                        } else {
                            resultadosDiv.innerHTML = '<p>Nenhuma Ordem de Serviço encontrada.</p>';
                        }
                    } catch (error) {
                        console.error('Erro ao buscar OS:', error);
                        resultadosDiv.innerHTML = '<p>Erro ao realizar a busca.</p>';
                    }
                };

                if (btnAbrirModal) {
                    btnAbrirModal.addEventListener('click', () => {
                        resetModal();
                        modal.style.display = 'block';
                        buscaInput.focus();
                        
                        buscarOrdensServico(''); 
                    });
                }

                closeModalBtn.addEventListener('click', () => modal.style.display = 'none');
                window.addEventListener('click', (event) => {
                    if (event.target == modal) modal.style.display = 'none';
                });
                btnVoltar.addEventListener('click', resetModal);

                function resetModal() {
                    etapa1.style.display = 'block';
                    etapa2.style.display = 'none';
                    buscaInput.value = '';
                    resultadosDiv.innerHTML = '';
                    listaItensDiv.innerHTML = '';
                }

                buscaInput.addEventListener('keyup', function() {
                    buscarOrdensServico(this.value);
                });

                resultadosDiv.addEventListener('click', async function(e) {
                    const item = e.target.closest('.result-item');
                    if (!item) return;

                    const osId = item.dataset.osId;
                    osSelecionadaCodigo.textContent = osId;
                    
                    etapa1.style.display = 'none';
                    etapa2.style.display = 'block';
                    listaItensDiv.innerHTML = '<p>Carregando serviços...</p>';

                    try {
                       
                        const response = await fetch(`api_get_itens_os.php?os_id=${osId}`);
                        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                        const itens = await response.json();

                        listaItensDiv.innerHTML = '';
                        if (itens.length > 0) {
                            itens.forEach(item => {
                                const a = document.createElement('a');
                                a.className = 'result-item link-item-processo';
                                a.href = `cadastro_processos.php?action=generate_from_os&os_id=${item.ORI_ORS_CODIGO}&item_id=${item.ORI_ITEM}`;
                                a.innerHTML = `<strong>Item ${item.ORI_ITEM}:</strong> ${item.TSE_DESCRICAO}`;
                                listaItensDiv.appendChild(a);
                            });
                        } else {
                            listaItensDiv.innerHTML = '<p>Não há serviços disponíveis para gerar processo nesta OS (todos os serviços podem já ter sido processados).</p>';
                        }
                    } catch (error) {
                        console.error('Erro ao buscar itens da OS:', error);
                        listaItensDiv.innerHTML = '<p>Erro ao carregar os serviços. Verifique o console para mais detalhes.</p>';
                    }
                });
            });              
    </script>
   

    </body>
</html>