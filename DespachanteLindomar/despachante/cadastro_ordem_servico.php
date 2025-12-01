<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../index.php");
    exit;
}

$usuario_nome = htmlspecialchars($_SESSION['username'] ?? 'Usuário');
$usuario_login = htmlspecialchars($_SESSION['login'] ?? '');

require_once '../verificar_permissao.php';
protegerPagina('acessar_os');
require_once '../config.php';

// --- INICIALIZAÇÃO DE VARIÁVEIS ---
$aba_ativa = 'consulta_os';
$os_para_editar = [];
$ordens_servico = []; 
$itens_da_os = [];
$parcelas_da_os = [];
$todos_os_servicos = [];
$valor_primeira_parcela = '';
$vencimento_primeira_parcela = '';

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

try {
    $stmt_servicos = $conn->query("SELECT TSE_CODIGO, TSE_DESCRICAO, TSE_VLUNITARIO FROM TIPO_SERVICO ORDER BY TSE_DESCRICAO");
    $todos_os_servicos = $stmt_servicos->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao carregar tipos de serviço: " . $e->getMessage());
}

// --- LÓGICA DE AÇÕES ---
$action = $_GET['action'] ?? null;
$id = $_GET['id'] ?? null;

if ($action === 'new') {
    $aba_ativa = 'cadastro_os';
}

if (($action === 'edit' || $action === 'view') && $id) {
    $aba_ativa = 'cadastro_os';
    
    $sql_edicao = "
        SELECT
            os.*, 
            c.NOME AS CLIENTE_NOME,
            v.MODELO AS VEICULO_MODELO,
            v.PLACA_UF AS VEICULO_PLACA,
            v.RENAVAM AS VEICULO_RENAVAM,
            v.CHASSI AS VEICULO_CHASSI,
            desp.NOME AS DESPACHANTE_NOME,
            cond.CON_NOME AS CONDICAO_PAGAMENTO_NOME
        FROM
            ORDEM_SERVICO AS os
        LEFT JOIN
            CLIENTE AS c ON os.ORS_CPF_CNPJ = c.CPF_CNPJ
        LEFT JOIN
            VEICULO AS v ON os.ORS_VEI_CODIGO = v.CODIGO
        LEFT JOIN
            DESPACHANTE AS desp ON os.ORS_DEP_CODIGO = desp.COD_DESP -- Ajuste o nome da coluna de código do despachante se necessário
        LEFT JOIN
            CONDICAO_PAGAMENTO AS cond ON os.ORS_CON_CODIGO = cond.CON_CODIGO
        WHERE
            os.ORS_CODIGO = ?
    ";
    
    $stmt = $conn->prepare($sql_edicao);
    $stmt->execute([$id]);
    $os_para_editar = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($os_para_editar) {
        $sql_itens = "
            SELECT 
                item.ORI_ORS_CODIGO, item.ORI_ITEM, item.ORI_TSE_CODIGO,
                item.ORI_QUANTIDADE, item.ORI_VLUNITARIO, item.ORI_VLTOTAL,
                tipo.TSE_DESCRICAO
            FROM ORDEM_SERVICO_ITEM AS item
            JOIN TIPO_SERVICO AS tipo ON item.ORI_TSE_CODIGO = tipo.TSE_CODIGO
            WHERE item.ORI_ORS_CODIGO = ?
            ORDER BY item.ORI_ITEM
        ";
        $stmt_itens = $conn->prepare($sql_itens);
        $stmt_itens->execute([$id]);
        $itens_da_os = $stmt_itens->fetchAll(PDO::FETCH_ASSOC);

        $sql_parcelas = "SELECT * FROM ORDEM_SERVICO_PARCELA WHERE OSP_ORS_CODIGO = ? ORDER BY OSP_CODIGO ASC";
        $stmt_parcelas = $conn->prepare($sql_parcelas);
        $stmt_parcelas->execute([$id]);
        $parcelas_da_os = $stmt_parcelas->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($parcelas_da_os)) {
            $valor_primeira_parcela = $parcelas_da_os[0]['OSP_VALOR'];
            $vencimento_primeira_parcela = $parcelas_da_os[0]['OSP_VENCIMENTO'];
        }
    }

    $cliente_nome = $os_para_editar['CLIENTE_NOME'] ?? '';
    
    $despachante_nome = '';
    if (!empty($os_para_editar['ORS_DEP_CODIGO'])) {
        $despachante_nome = $os_para_editar['ORS_DEP_CODIGO'] . ' - ' . ($os_para_editar['DESPACHANTE_NOME'] ?? '');
    }

    $condicao_nome = '';
    if (!empty($os_para_editar['ORS_CON_CODIGO'])) {
        $condicao_nome = $os_para_editar['ORS_CON_CODIGO'] . ' - ' . ($os_para_editar['CONDICAO_PAGAMENTO_NOME'] ?? '');
    }
}

if ($action === 'delete' && $id) {
    try {
        $conn->beginTransaction();

        // Deleta as parcelas associadas
        $stmt_del_parcelas = $conn->prepare("DELETE FROM ORDEM_SERVICO_PARCELA WHERE OSP_ORS_CODIGO = ?");
        $stmt_del_parcelas->execute([$id]);

        // Deleta os itens associados
        $stmt_del_itens = $conn->prepare("DELETE FROM ORDEM_SERVICO_ITEM WHERE ORI_ORS_CODIGO = ?");
        $stmt_del_itens->execute([$id]);

        // Deleta a Ordem de Serviço principal
        $stmt_del_os = $conn->prepare("DELETE FROM ORDEM_SERVICO WHERE ORS_CODIGO = ?");
        $stmt_del_os->execute([$id]);
        
        $conn->commit();
        header("Location: cadastro_ordem_servico.php?status=deleted");
        exit;
    } catch (PDOException $e) {
        $conn->rollBack();
        header("Location: cadastro_ordem_servico.php?status=error&msg=" . urlencode($e->getMessage()));
        exit;
    }
}

// LÓGICA DE SALVAR 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    function converterDataParaBanco($data) {
    if (empty($data)) return null;

    $data = trim($data);

    $dY = DateTime::createFromFormat('Y-m-d', $data);
    if ($dY && $dY->format('Y-m-d') === $data) {
        return $dY->format('Y-m-d');
    }
    $d = DateTime::createFromFormat('d/m/Y', $data);
    if ($d && $d->format('d/m/Y') === $data) {
        return $d->format('Y-m-d');
    }
    $data_alt = str_replace(['-', '.'], '/', $data);
    $d2 = DateTime::createFromFormat('d/m/Y', $data_alt);
    if ($d2 && $d2->format('d/m/Y') === $data_alt) {
        return $d2->format('Y-m-d');
    }
    return null;
}

   function limparValorMonetario($valor) {
        if (!isset($valor) || $valor === '') return null;
        
        $valor = preg_replace('/[^\d\.,-]/', '', $valor);
        if (strpos($valor, '.') !== false && strpos($valor, ',') !== false) {
            $valor = str_replace('.', '', $valor); 
            $valor = str_replace(',', '.', $valor); 
        } else {
            $valor = str_replace(',', '.', $valor);
        }
        return (float)$valor;
    }

    function limparNumero($numero) {
    if (!isset($numero) || $numero === '') return null;
    $numero_limpo = preg_replace('/\D/', '', (string)$numero);
    return $numero_limpo === '' ? null : (int)$numero_limpo;
}


    foreach($_POST as $chave => &$valor){
        if($valor === '' || $valor === null) {
            $valor = null;
        }
    }

    if (!empty($_POST['item_qtde']) && is_array($_POST['item_qtde'])) {
        foreach ($_POST['item_qtde'] as $i => $qtd) {
            $_POST['item_qtde'][$i] = str_replace(',', '.', $qtd); 
        }
    }

    // --- INÍCIO DO PROCESSAMENTO ---
    $CODIGO = isset($_POST['ORS_CODIGO']) && $_POST['ORS_CODIGO'] !== '' ? $_POST['ORS_CODIGO'] : null;

    $dados = [
        'ORS_DTEMISSAO' => converterDataParaBanco($_POST['ORS_DTEMISSAO'] ?? ''),
        'ORS_HREMISSAO' => !empty($_POST['ORS_HREMISSAO']) ? $_POST['ORS_HREMISSAO'] : date('H:i:s'), 
        'ORS_SITUACAO' => $_POST['ORS_SITUACAO'] ?? null,
        'ORS_VLTOTAL' => limparValorMonetario($_POST['ORS_VLTOTAL'] ?? null),
        'ORS_OBS' => $_POST['ORS_OBS'] ?? null,
        'ORS_CPF_CNPJ' => $_POST['ORS_CPF_CNPJ'] ?? null,
        'ORS_VEI_CODIGO' => $_POST['ORS_VEI_CODIGO'] ?? null,
        'ORS_DEP_CODIGO' => $_POST['ORS_DEP_CODIGO'] ?? null,
        'ORS_CON_CODIGO'=> $_POST['ORS_CON_CODIGO'] ?? null,
        'ORS_VLENTRADA' => limparValorMonetario($_POST['ORS_VLENTRADA'] ?? null),
        'ORS_PARCELAS' => !empty($_POST['ORS_PARCELAS']) ? (int)$_POST['ORS_PARCELAS'] : null,
       
    ];
  
    foreach ($dados as $k => $v) {
        if ($v === '') $dados[$k] = null;
    }

    try {
        $conn->beginTransaction();

        // SALVAR A ORDEM DE SERVIÇO 
        if (empty($CODIGO)) {
            // INSERT
            $colunas = implode(', ', array_keys($dados));
            $placeholders = implode(', ', array_fill(0, count($dados), '?'));
            $sql = "INSERT INTO ORDEM_SERVICO ($colunas) VALUES ($placeholders)";
            $stmt = $conn->prepare($sql);
            $stmt->execute(array_values($dados));
            $CODIGO = $conn->lastInsertId(); 
        } else {
            // UPDATE
            $set = [];
            foreach (array_keys($dados) as $col) {
                $set[] = "$col = ?";
            }
            $sql = "UPDATE ORDEM_SERVICO SET " . implode(', ', $set) . " WHERE ORS_CODIGO = ?";
            $valores = array_values($dados);
            $valores[] = $CODIGO;
            $stmt = $conn->prepare($sql);
            $stmt->execute($valores);
        }

        // SALVAR OS ITENS DA ORDEM DE SERVIÇO
        $stmt_delete_items = $conn->prepare("DELETE FROM ORDEM_SERVICO_ITEM WHERE ORI_ORS_CODIGO = ?");
        $stmt_delete_items->execute([$CODIGO]);
        
        $itens_codigo = $_POST['item_codigo'] ?? [];
        if (!empty($itens_codigo) && is_array($itens_codigo)) {
            $sql_insert_item = "INSERT INTO ORDEM_SERVICO_ITEM (ORI_ORS_CODIGO, ORI_ITEM, ORI_TSE_CODIGO, ORI_QUANTIDADE, ORI_VLUNITARIO, ORI_VLTOTAL) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt_insert_item = $conn->prepare($sql_insert_item);

            foreach ($itens_codigo as $index => $codigo_servico) {
                if (empty($codigo_servico)) continue; 

                $item_numero = $index + 1;
                $quantidade = limparNumero($_POST['item_qtde'][$index] ?? '1');
                $vl_unitario = limparValorMonetario($_POST['item_vlunitario'][$index] ?? '0');
                $vl_total = limparValorMonetario($_POST['item_vltotal'][$index] ?? '0');

                $stmt_insert_item->execute([$CODIGO, $item_numero, $codigo_servico, $quantidade, $vl_unitario, $vl_total]);
            }
        }

        // SALVAR AS PARCELAS DA ORDEM DE SERVIÇO
        $stmt_delete_financeiro = $conn->prepare("DELETE FROM financeiro_pagar_receber WHERE FIN_ORS_CODIGO = ? AND FIN_CREDEB = 'C'");
        $stmt_delete_financeiro->execute([$CODIGO]);
        
        
        $stmt_delete_parcelas = $conn->prepare("DELETE FROM ORDEM_SERVICO_PARCELA WHERE OSP_ORS_CODIGO = ?");
        $stmt_delete_parcelas->execute([$CODIGO]);

        $qtde_parcelas = isset($_POST['ORS_PARCELAS']) ? (int)$_POST['ORS_PARCELAS'] : 0;
        $valor_total = limparValorMonetario($_POST['ORS_VLTOTAL'] ?? 0);
        $valor_entrada = limparValorMonetario($_POST['ORS_VLENTRADA'] ?? 0);
        $dia_vencimento = isset($_POST['OSP_DIA']) && $_POST['OSP_DIA'] !== '' ? (int)$_POST['OSP_DIA'] : (int)date('d');
        $valor_restante = max($valor_total - $valor_entrada, 0);
        $valor_parcela = $qtde_parcelas > 0 ? round($valor_restante / $qtde_parcelas, 2) : 0;
        $data_base = new DateTime();
        $data_base->setDate($data_base->format('Y'), $data_base->format('m'), $dia_vencimento);
        $sql_insert_parcela = "INSERT INTO ORDEM_SERVICO_PARCELA (OSP_ORS_CODIGO, OSP_VENCIMENTO, OSP_VALOR) VALUES (?, ?, ?)";
        $stmt_insert_parcela = $conn->prepare($sql_insert_parcela);

        $sql_insert_financeiro = "
            INSERT INTO financeiro_pagar_receber 
            (FIN_ORS_CODIGO, CPF_CNPJ, FIN_CREDEB, FIN_DESCRICAO, FIN_DATAEMISSAO, FIN_DATAVENCIMENTO, FIN_VALOR, FIN_VALORTOTAL) 
            VALUES (?, ?, 'C', ?, NOW(), ?, ?, ?)
        ";
        $stmt_insert_financeiro = $conn->prepare($sql_insert_financeiro);


        if ($valor_entrada > 0) {
            $descricao_entrada = "Entrada OS {$CODIGO}";
            $data_entrada = date('Y-m-d'); 

            $stmt_insert_parcela->execute([$CODIGO, $data_entrada, $valor_entrada]);
            $stmt_insert_financeiro->execute([
                $CODIGO,
                $dados['ORS_CPF_CNPJ'],
                $descricao_entrada,
                $data_entrada,
                $valor_entrada,
                $valor_entrada
            ]);
        }

        for ($i = 1; $i <= $qtde_parcelas; $i++) {
            $vencimento = clone $data_base;
            if ($i > 1) {
                $vencimento->modify("+".($i - 1)." month");
            }
       
            $ultimo_dia_mes = (int)$vencimento->format('t');
            if ($dia_vencimento > $ultimo_dia_mes) {
                $vencimento->setDate($vencimento->format('Y'), $vencimento->format('m'), $ultimo_dia_mes);
            }

            $data_vencimento_sql = $vencimento->format('Y-m-d');
            $descricao = "Recebimento OS {$CODIGO} - Parcela {$i}/{$qtde_parcelas}";

            $stmt_insert_parcela->execute([$CODIGO, $data_vencimento_sql, $valor_parcela]);
            $stmt_insert_financeiro->execute([
                $CODIGO,
                $dados['ORS_CPF_CNPJ'],
                $descricao,
                $data_vencimento_sql,
                $valor_parcela,
                $valor_parcela
            ]);
        }

        $conn->commit();
        header("Location: cadastro_ordem_servico.php?action=edit&id=" . $CODIGO . "&status=success");
        exit;

    } catch (PDOException $e) {
 
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
    
        die("Erro ao salvar ordem de serviço: " . $e->getMessage());
    }
}

// --- LÓGICA DE BUSCA E CONSULTA ---
$sql_base="SELECT
        os.ORS_CODIGO,
        os.ORS_DTEMISSAO,
        c.NOME AS CLIENTE_NOME,  
        v.PLACA_UF,             
        os.ORS_SITUACAO,
        os.ORS_VLTOTAL,
        os.ORS_IMPRESSA
    FROM
        ORDEM_SERVICO AS os
    INNER JOIN
        CLIENTE AS c ON os.ORS_CPF_CNPJ = c.CPF_CNPJ
    LEFT JOIN
        VEICULO AS v ON os.ORS_VEI_CODIGO = v.CODIGO
";
$params = [];
$where_clauses = [];

if (!empty($_GET['valor_pesquisa']) && ($_GET['campo_pesquisa'] == 'nome' || $_GET['campo_pesquisa'] == 'placa')) {
    $sql_count_base = "
        SELECT COUNT(*)
        FROM ORDEM_SERVICO AS os
        INNER JOIN CLIENTE AS c ON os.ORS_CPF_CNPJ = c.CPF_CNPJ
        LEFT JOIN VEICULO AS v ON os.ORS_VEI_CODIGO = v.CODIGO
    ";

       if ($campo == 'situacao') {
        $where_clauses[] = "os.ORS_SITUACAO = ?"; 
        $mapa_situacao = [
            'aberta' => 0,
            'fechada' => 1,
            'cancelada' => 2
        ];
        
        $texto_busca = strtolower(trim($valor));
        if (array_key_exists($texto_busca, $mapa_situacao)) {
            $params[] = $mapa_situacao[$texto_busca];
        } else {
            $params[] = -1;
        }

    } else { 
        $valor_param = '%' . $valor . '%';
        
        if ($campo == 'codigo') {
            $where_clauses[] = "os.ORS_CODIGO LIKE ?";
        } elseif ($campo == 'nome') {
            $where_clauses[] = "c.NOME LIKE ?";
        } elseif ($campo == 'placa') {
            $where_clauses[] = "v.PLACA_UF LIKE ?";
        }
        
        if (!empty($where_clauses)) {
            $params[] = $valor_param;
        }
}

} else{
    $sql_count_base = "SELECT COUNT(*) FROM ORDEM_SERVICO AS os";
}

   
 

if (!empty($where_clauses)) {
    $sql_base .= " WHERE " . implode(' AND ', $where_clauses);
    $sql_count_base .= " WHERE " . implode(' AND ', $where_clauses);
}

$total_stmt = $conn->prepare($sql_count_base);
$total_stmt->execute($params);
$total_records = $total_stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

$limit = (int)$limit;
$offset = (int)$offset;
$sql_final = $sql_base ." ORDER BY os.ORS_CODIGO LIMIT $limit OFFSET $offset";
$stmt = $conn->prepare($sql_final);


for ($i = 0; $i < count($params); $i++) {
    $stmt->bindValue($i + 1, $params[$i]);
}
$stmt->execute($params);
$ordens_servico = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Lógica  de navegação
$query_string = http_build_query(array_merge($_GET, ['page' => '']));
$link_base = "cadastro_ordem_servico.php?" . $query_string;
$link_primeiro = $link_base . "1";
$link_anterior = ($page > 1) ? $link_base . ($page - 1) : "#";
$link_proximo = ($page < $total_pages) ? $link_base . ($page + 1) : "#";
$link_ultimo = $link_base . $total_pages;

?>



<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ordem de Serviços</title>
    <link rel="stylesheet" href="../css/style_sistema.css">
    <link rel="stylesheet" href="../css/print_os.css" media="print">
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
            <h1>Ordem de Serviços</h1>

              <?php if (isset($_GET['status'])): ?>
                <?php if ($_GET['status'] == 'success'): ?>
                    <div class="alert success">Ordem de Serviço salva com sucesso!</div>
                <?php elseif ($_GET['status'] == 'error'): ?>
                    <div class="alert error"><strong>Erro ao salvar!</strong> <?= htmlspecialchars(urldecode($_GET['msg'] ?? 'Ocorreu um problema.')) ?></div>
                <?php endif; ?>
            <?php endif; ?>

            <?php 
                if (($action === 'edit') && !empty($os_para_editar)) :
            ?>

            <div class= "form-tollbar client-header" style="display: flex; justify-content: space-between;">
                <div class="client-info">
                    <strong><?= htmlspecialchars($os_para_editar['CLIENTE_NOME'] ?? '')?></strong><br><br>
                    CPF/CNPJ<?= htmlspecialchars($os_para_editar['ORS_CPF_CNPJ'] ?? '') ?>
                </div>

                <div class="client-actions">
                    <a href="cadastro_ordem_servuco.php" class="btn btn-danger">Voltar para Consulta</a>
                </div>
            </div>

            <?php else :?>
                <div class="form-toolbar">
                    <?php if (temPermissao('criar_editar_os')): ?>
                            <a href="cadastro_ordem_servico.php?action=new" class="btn">Novo</a>
                    <?php endif; ?>   
                </div>
             <?php endif; ?>

            <div class="tabs">
                <div class="tab-buttons">
                    <button type="button" class="tab-button <?= $aba_ativa == 'consulta_os' ? 'active' : '' ?>" data-tab="consulta_os" <?php if ($aba_ativa == 'cadastro_os'): ?> style="display: none;" <?php endif; ?>>Consulta</button>
                    <button type="button" class="tab-button <?= $aba_ativa == 'cadastro_os' ? 'active' : '' ?>" data-tab="cadastro_os" <?= $aba_ativa != 'cadastro_os' ? 'disabled' : '' ?>>Cadastro</button>
                </div>

                <div class="tab-content">
                    <!-- Consulta -->
                    <div id="consulta_os" class="tab-pane <?= $aba_ativa == 'consulta_os' ? 'active' : '' ?>">
                        <form method="GET" action="cadastro_ordem_servico.php">
                            <fieldset class="search-box">
                                <legend>Opções de pesquisa</legend>
                                <div class="form-group">
                                    <label for="campo_pesquisa">Campo de Pesquisa:</label>
                                    <select id="campo_pesquisa" name="campo_pesquisa">
                                        <option value="codigo" <?= ($_GET['campo_pesquisa'] ?? '') === 'codigo' ? 'selected' : '' ?>>CÓDIGO</option>
                                        <option value="nome" <?= ($_GET['campo_pesquisa'] ?? '') === 'nome' ? 'selected' : '' ?>>CLIENTE</option>
                                        <option value="placa" <?= ($_GET['campo_pesquisa'] ?? '') === 'placa' ? 'selected' : '' ?>>PLACA ATUAL</option>
                                        <option value="situacao" <?= ($_GET['campo_pesquisa'] ?? '') === 'situacao' ? 'selected' : '' ?>>SITUAÇÃO</option>
                                    </select>
                                </div>

                                <div class="form-group" style="flex-grow: 2;">
                                    <label for="parametro_pesquisa">Parâmetro da Pesquisa:</label>
                                    <input type="text" id="parametro_pesquisa" name="valor_pesquisa"></input>
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
                                        <th>DATA</th>
                                        <th>CLIENTE</th>
                                        <th>PLACA ATUAL</th>
                                        <th>SITUAÇÃO</th>
                                        <th>VALOR(R$)</th>
                                        <th style="text-align: center;">AÇÃO</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($ordens_servico)): ?>
                                        <?php foreach ($ordens_servico as $os): ?>
                                        <tr class="clickable-row" data-id="<?= $os['ORS_CODIGO'] ?>">
                                            <td><?= htmlspecialchars($os['ORS_CODIGO'] ?? '') ?></td>
                                             <td><?php 
                                                if (!empty($os['ORS_DTEMISSAO'])) {
                                                    echo htmlspecialchars(date('d/m/Y', strtotime($os['ORS_DTEMISSAO'])));
                                                } else {
                                                    echo ''; 
                                                }
                                            ?></td>
                                            <td><?= htmlspecialchars($os['CLIENTE_NOME'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($os['PLACA_UF'] ?? '') ?></td>
                                            <td>
                                                <?php 
                                                    $situacao_texto = [0 => 'Aberta', 1 => 'Fechada', 2 => 'Cancelada'];
                                                    echo htmlspecialchars($situacao_texto[$os['ORS_SITUACAO']] ?? 'Desconhecida');
                                                ?>
                                            </td>
                                            <td>R$ <?= number_format($os['ORS_VLTOTAL'] ?? 0, 2, ',', '.') ?></td>
                                             <td style="text-align: center; white-space: nowrap; display: flex; justify-content: center; gap: 15px;">
                                                <?php if (temPermissao('criar_editar_os')) : ?>
                                                    <a href="cadastro_ordem_servico.php?action=edit&id=<?= $os['ORS_CODIGO'] ?>" class="btn btn-primary">Editar</a>
                                                <?php endif; ?>
                                                <?php if (temPermissao('excluir_os')) : ?>
                                                    <a href="cadastro_orsem_servico.php?action=delete&id=<?= $os['ORS_CODIGO'] ?>" class="btn btn-danger btn-excluir-linha">Excluir</a>
                                                <?php endif; ?>
                                            </td>                 
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7">Nenhum registro encontrado.</td> </tr>
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
                    <div id="cadastro_os" class="tab-pane <?=$aba_ativa == 'cadastro_os' ? 'active' : '' ?>">
                        <form id="formOrdemServico" method="POST" action="cadastro_ordem_servico.php">
                            <input type="hidden" name="ORS_CODIGO" value="<?= htmlspecialchars($os_para_editar['ORS_CODIGO'] ?? '') ?>">
                            <fieldset <?= ($action === 'view') ? 'disabled' : '' ?> style="border: none;">
                                <fieldset class="form-section">
                                    <legend>Dados da Ordem de Serviço</legend>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Código</label>
                                            <input type="text" value="<?= htmlspecialchars($os_para_editar['ORS_CODIGO'] ?? 'NOVO') ?>" readonly>
                                        </div>

                                        <div class="form-group">
                                            <label>Data Emissão</label>
                                            <input type="text" name="ORS_DTEMISSAO" value="<?= htmlspecialchars($os_para_editar['ORS_DTEMISSAO'] ?? date('d/m/Y')) ?? '' ?>" readonly>
                                        </div>

                                        <div class="form-group">
                                            <label>Situação</label>
                                            <?php $situacao_atual = $os_para_editar['ORS_SITUACAO'] ?? '0'; ?>
                                            <select name="ORS_SITUACAO">
                                                <option value="0" <?= $situacao_atual == '0' ? 'selected' : '' ?>>Aberta</option>
                                                <option value="1" <?= $situacao_atual == '1' ? 'selected' : '' ?>>Fechada</option>
                                                <option value="2" <?= $situacao_atual == '2' ? 'selected' : '' ?>>Cancelada</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Cliente</label>
                                            <div class="input-with-button">
                                                <input type="text" id="servicos_cliente_display" name="cliente_nome_display" placeholder="Busque pelo cliente..." value="<?= htmlspecialchars($cliente_nome ?? '') ?>" readonly>
                                                <input type="hidden" id="servicos_cod_cliente_hidden" name="ORS_CPF_CNPJ" value="<?= htmlspecialchars($os_para_editar['ORS_CPF_CNPJ'] ?? '') ?>">
                                                <button type="button" id="servicos_btnAbrirModalCliente" class="btn-lookup">...</button>
                                            </div>
                                        </div>

                                            <div class="form-group">
                                            <label>CPF/CNPJ</label>
                                            <input type="text" id="servicos_cpf_cnpj" value="<?= htmlspecialchars ($os_para_editar['ORS_CPF_CNPJ'] ?? '' )?>"readonly>
                                        </div>
                                    </div>

                                    <div class="form-row">

                                        <div class="form-group">
                                            <label>Veículo</label>
                                            <div class="input-with-button">
                                                <input type="text" id="veiculo_display" readonly value="<?= htmlspecialchars($os_para_editar['VEICULO_MODELO'] ?? '') ?>">
                                                <input type="hidden" id="veiculo_id_hidden" name="ORS_VEI_CODIGO" value="<?= htmlspecialchars($os_para_editar['ORS_VEI_CODIGO'] ?? '') ?>">
                                                <button type="button" id="btnAbrirModalVeiculoCliente" class="btn-lookup">...</button>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label>Placa</label>
                                            <input type="text" id="vei_placa" value="<?= htmlspecialchars($os_para_editar['VEICULO_PLACA'] ?? '') ?>">
                                        </div>

                                        <div class="form-group">
                                            <label>Renavam</label>
                                            <input type="text" id="vei_renavam" value="<?= htmlspecialchars($os_para_editar['VEICULO_RENAVAM'] ?? '') ?>">
                                        </div>

                                        <div class="form-group">
                                            <label>Chassi</label>
                                            <input type="text" id="vei_chassi" value="<?= htmlspecialchars($os_para_editar['VEICULO_CHASSI'] ?? '') ?>">
                                        </div>
                                    </div>

                                    <div class="form-row">

                                        <div class="form-group">
                                            <label>Despachante</label>
                                            <div class="input-with-button">
                                                <input type="text" id="cod_desp_input" placeholder="Clique no botão para buscar..." value="<?= htmlspecialchars($despachante_nome ?? '') ?>" readonly>
                                                <input type="hidden" id="cod_desp_hidden" name="ORS_DEP_CODIGO" value="<?= htmlspecialchars($os_para_editar['ORS_DEP_CODIGO'] ?? '') ?>">
                                                <button type="button" id="btnAbrirModalDespachante" class="btn-lookup">...</button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Condição de Pagamento</label>
                                            <div class="input-with-button">
                                                <input type="text" id="pagamento_display" placeholder="Clique no botão para buscar..."value="<?= htmlspecialchars($condicao_nome ?? '') ?>" readonly>
                                                <input type="hidden" id="cod_pagamento_hidden" name="ORS_CON_CODIGO" value="<?= htmlspecialchars($os_para_editar['ORS_CON_CODIGO'] ?? '') ?>">
                                                <button type="button" id="btnAbrirModalPagamento" class="btn-lookup">...</button>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label>Qtde. Parcelas</label>
                                            <input type="number" name="ORS_PARCELAS" value="<?= htmlspecialchars($os_para_editar['ORS_PARCELAS'] ?? '1') ?>">
                                        </div>

                                        <div class="form-group">
                                            <label>R$ Valor da Parcela</label>
                                            <input type="text" name="OSP_VALOR" class="mascara-moeda" value="<?= !empty($valor_primeira_parcela) ? number_format($valor_primeira_parcela, 2, ',', '.') : '' ?>">
                                        </div>

                                        <div class="form-group">
                                            <label>Dia de Vencimento</label>
                                            <input type="text" name="OSP_DIA" placeholder="dd" value="<?= htmlspecialchars($os_para_editar['OSP_DIA'] ?? '') ?>">
                                        </div>

                                        <div class="form-group">
                                            <label>R$ Entrada</label>
                                            <input type="text" name="ORS_VLENTRADA" class="mascara-moeda" value="<?= !empty($os_para_editar['ORS_VLENTRADA']) ? number_format($os_para_editar['ORS_VLENTRADA'], 2, ',', '.') : '' ?>">
                                        </div>
                                    </div>
                                </fieldset>
                            </fieldset>

                             <fieldset <?= ($action === 'view') ? 'disabled' : '' ?> style="border: none;">
                                <fieldset class="form-section">
                                        <legend>Itens da Ordem de Serviços</legend>

                                        <div id="itens-container">
                                            
                                            <div class="form-row item-header" style="font-weight: bold; margin-bottom: 5px;">
                                                <div class="form-group" style="flex: 4; color: #555; font-size: 0.9em;">Serviço</div>
                                                <div class="form-group" style="flex: 1; color: #555; font-size: 0.9em;">Qtde</div>
                                                <div class="form-group" style="flex: 2; color: #555; font-size: 0.9em;">Vl. Unitário (R$)</div>
                                                <div class="form-group" style="flex: 2; color: #555; font-size: 0.9em;">Vl. Total (R$)</div>
                                                <div class="form-group" style="width: 20px; color: #555; font-size: 0.9em;">Ação</div>
                                            </div>

                                            <?php if (!empty($itens_da_os)): ?>
                                                <?php foreach ($itens_da_os as $item_existente): ?>
                                                    <div class="form-row item-row">
                                                        <div class="form-group" style="flex: 4;">
                                                            <select name="item_codigo[]" class="item-servico-select">
                                                                <option value="">Selecione um serviço...</option>
                                                                <?php foreach ($todos_os_servicos as $servico): ?>
                                                                    <option value="<?= $servico['TSE_CODIGO'] ?>" 
                                                                            data-valor="<?= $servico['TSE_VLUNITARIO'] ?>"
                                                                            <?= ($servico['TSE_CODIGO'] == $item_existente['ORI_TSE_CODIGO']) ? 'selected' : '' ?>>
                                                                        <?= htmlspecialchars($servico['TSE_DESCRICAO']) ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <div class="form-group" style="flex: 1;">
                                                            <input type="number" name="item_qtde[]" class="item-qtde" value="<?= htmlspecialchars((int)$item_existente['ORI_QUANTIDADE']) ?>">
                                                        </div>
                                                        <div class="form-group" style="flex: 2;">
                                                            <input type="text" name="item_vlunitario[]" class="item-vlunitario mascara-moeda" value="<?= number_format($item_existente['ORI_VLUNITARIO'], 2, ',', '.') ?>">
                                                        </div>
                                                        <div class="form-group" style="flex: 2;">
                                                            <input type="text" name="item_vltotal[]" class="item-vltotal mascara-moeda" value="<?= number_format($item_existente['ORI_VLTOTAL'], 2, ',', '.') ?>" readonly>
                                                        </div>
                                                        <div class="form-group" style="width: 50px;">
                                                            <button type="button" class="btn btn-danger btn-remover-item">Remover</button>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>

                                        <div class="form-footer" style="justify-content: flex-start; padding-top: 10px;">
                                            <button type="button" id="btnAdicionarItem" class="btn">Adicionar Item</button>
                                        </div>

                                        <div class="resultado-calculo" style="text-align: right;">
                                            <div class="form-group">
                                                <p class="valor-final">
                                                    Valor Total:
                                                    <strong id="valorTotalServicos">
                                                        R$ <?= number_format($os_para_editar['ORS_VLTOTAL'] ?? 0, 2, ',', '.') ?>
                                                    </strong>
                                                </p>
                                                <input type="hidden" name="ORS_VLTOTAL" id="valor_total_hidden" value="<?= $os_para_editar['ORS_VLTOTAL'] ?? 0 ?>">
                                            </div>
                                        </div>
                                    
                                </fieldset>

                                <template id="template-item-row">
                                    <div class="form-row item-row">
                                        <div class="form-group" style="flex: 4;">
                                            <select name="item_codigo[]" class="item-servico-select">
                                                <option value="">Selecione um serviço...</option>
                                                <?php foreach ($todos_os_servicos as $servico): ?>
                                                    <option value="<?= $servico['TSE_CODIGO'] ?>" data-valor="<?= $servico['TSE_VLUNITARIO'] ?>">
                                                        <?= htmlspecialchars($servico['TSE_DESCRICAO']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="form-group" style="flex: 1; ">
                                            <input type="number" class="item-qtde form-control text-end" min="1" value="1">
                                        </div>
                                        <div class="form-group" style="flex: 2;">
                                            <input type="text" name="item_vlunitario[]" class="item-vlunitario mascara-moeda" value="0,00">
                                        </div>
                                        <div class="form-group" style="flex: 2;">
                                            <input type="text" name="item_vltotal[]" class="item-vltotal mascara-moeda" value="0,00" readonly>
                                        </div>
                                        <div class="form-group" style="width: 50px;">
                                            <button type="button" class="btn btn-danger btn-remover-item">Remover</button>
                                        </div>
                                    </div>
                                </template>
                            </fieldset>

                            <div class="form-footer">
                                <?php if ($action !== 'view'): ?>
                                    <button type="button" id="btnGerarProcesso" class="btn btn-primary">Gerar Processo</button>
                                    <button type="submit" class="btn btn-primary">Salvar</button> 
                                <?php endif; ?>
                                <?php 
                                    if (!empty($os_para_editar['ORS_CODIGO'])): ?>
                                        <a href="imprimir_ordem_servico.php?id=<?= htmlspecialchars($os_para_editar['ORS_CODIGO']) ?>" target="_blank" class="btn">Imprimir</a>
                                    <?php endif; ?>
                                <a href="cadastro_ordem_servico.php" class="btn btn-danger">Cancelar</a>
                            </div>

                        <div id="configuracao_os" class="tab-pane">
                        
                    </div>
                </div>
            </div>
        </main>
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

    <!--modal buscar veiculo do cliente -->
    <div id="modalVeiculoCliente" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2>Buscar Veículo do Cliente</h2>
            <input type="text" id="buscaVeiculoClienteInput" placeholder="Busque pela placa ou modelo...">
            <div id="listaResultadosVeiculoCliente" class="results-list"></div>
        </div>
    </div>

    <!--modal buscar despachante -->
    <div id="modalDespachante" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2>Buscar Despachante</h2>
            <input class="text" type="text" id="buscaDespachanteInput" placeholder="Busque pelo nome ou código...">
            <div id="listaResultadosDespachante" class="results-list"></div>
        </div>
    </div>

    <!--modal buscar condição pagamento -->
    <div id="modalPagamento" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2>Buscar Condição Pagamento</h2>
            <input class="text" type="text" id="buscaPagamentoInput" placeholder="Busque pelo nome ou código...">
            <div id="listaResultadosPagamento" class="results-list"></div>
        </div>
    </div>

     <!--modal buscar Tipo Servico -->
    <div id="modalTipoServico" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2>Buscar Serviço</h2>
            <input type="text" id="buscaTipoServicoInput" placeholder="Busque pelo nome ou código...">
            <div id="listaResultadosTipoServico" class="results-list"></div>
        </div>
    </div>

    <div id="modalGerarProcesso" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2>Selecione o Serviço para Gerar o Processo</h2>
            <p>Selecione um dos serviços da OS <strong id="modalOsCodigo"></strong> que ainda não possuem um processo:</p>
            <div id="listaItensParaProcesso" class="results-list">
                </div>
        </div>
    </div>

    <script src="../js/script_menu.js"></script>
    <script src="../js/script_modal.js"></script>
    <script src="../js/script_mascara.js"></script>
    <script src="../js/script_main.js"></script>
    <script src="../js/script_ordem_servico.js"></script>

</body>
</html>