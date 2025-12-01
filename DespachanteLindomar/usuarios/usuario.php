<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../index.php");
    exit;
}
require_once '../verificar_permissao.php';
protegerPagina('gerenciar_usuarios');

$usuario_nome = htmlspecialchars($_SESSION['username'] ?? 'Usuário');
$usuario_login = htmlspecialchars($_SESSION['login'] ?? '');
$aba_ativa = $_GET['tab'] ?? 'gerenciar_usuarios';
$usuario_para_editar = [];
$cargos_do_sistema = [];
$permissoes_por_grupo = [];
$usuarios = []; 
$message = '';
$message_type = '';
if (!empty($_GET['status'])) {
    switch ($_GET['status']) {
        case 'success':
            $message = '<strong>Sucesso!</strong> Salvo com sucesso.';
            $message_type = 'success';
            break;
        case 'deleted':
            $message = 'Excluído com sucesso.';
            $message_type = 'success';
            break;
        case 'error':
            $errorMessage = $_GET['msg'] ?? 'Ocorreu um problema não especificado.';
            $message = '<strong>Erro!</strong> ' . htmlspecialchars(urldecode($errorMessage));
            $message_type = 'error';
            break;
    }
}
try {
    $stmt_cargos = $conn->query("SELECT ID, NOME_CARGO FROM CARGOS ORDER BY NOME_CARGO");
    $cargos_do_sistema = $stmt_cargos->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao carregar cargos: " . $e->getMessage());
}

try {
    $stmt_permissoes = $conn->query("SELECT * FROM PERMISSOES ORDER BY GRUPO, DESCRICAO");
    $todas_as_permissoes = $stmt_permissoes->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($todas_as_permissoes as $p) {
        $permissoes_por_grupo[$p['GRUPO']][] = $p;
    }
} catch (PDOException $e) {
    die("Erro ao carregar permissões: " . $e->getMessage());
}


    $sql_usuarios = "
        SELECT u.UCIDUSER, u.UCUSERNAME, u.UCLOGIN, u.UCEMAIL, u.UCINATIVE, u.CARGO_ID, c.NOME_CARGO 
        FROM USUARIO u
        LEFT JOIN CARGOS c ON u.CARGO_ID = c.ID 
        ORDER BY u.UCUSERNAME
    ";
    $stmt_usuarios = $conn->query($sql_usuarios);
    $usuarios = $stmt_usuarios->fetchAll(PDO::FETCH_ASSOC);

    $action = $_GET['action'] ?? null;
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if ($action === 'new') {
        $aba_ativa = 'cadastro_usuario';
    }

    if (($action === 'edit' || $action === 'view') && $id) {
        $aba_ativa = 'cadastro_usuario';
        $sql_edicao = "
            SELECT u.*, c.NOME_CARGO 
            FROM USUARIO u
            LEFT JOIN CARGOS c ON u.CARGO_ID = c.ID
            WHERE u.UCIDUSER = ?
        ";
        $stmt_edicao = $conn->prepare($sql_edicao);
        $stmt_edicao->execute([$id]);
        $usuario_para_editar = $stmt_edicao->fetch(PDO::FETCH_ASSOC);
    }
    


$cargo_id_para_editar = filter_input(INPUT_GET, 'cargo_id', FILTER_VALIDATE_INT);
$permissoes_do_cargo = [];
$cargo_para_editar = null;

if (($aba_ativa == 'cadastro_cargo') && $cargo_id_para_editar) {
    $stmt_cargo = $conn->prepare("SELECT ID, NOME_CARGO FROM CARGOS WHERE ID = ?");
    $stmt_cargo->execute([$cargo_id_para_editar]);
    $cargo_para_editar = $stmt_cargo->fetch(PDO::FETCH_ASSOC);
    $stmt_permissoes_cargo = $conn->prepare("SELECT PERMISSAO_ID FROM CARGO_PERMISSOES WHERE CARGO_ID = ?");
    $stmt_permissoes_cargo->execute([$cargo_id_para_editar]);
    $permissoes_do_cargo = $stmt_permissoes_cargo->fetchAll(PDO::FETCH_COLUMN);

    if (!$cargo_para_editar) {
        $aba_ativa = 'gerenciar_cargos'; 
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action_type'] ?? '') === 'salvar_cargo') {
    $conn->beginTransaction();
    try {
        $cargo_id = filter_input(INPUT_POST, 'cargo_id', FILTER_VALIDATE_INT);
        $cargo_nome = trim($_POST['cargo_nome']);
        $permissoes_selecionadas = $_POST['permissoes'] ?? [];

        
        if (empty($cargo_nome)) {
            throw new Exception("O nome do Cargo é obrigatório.");
        }

        
        if (empty($cargo_id)) { 
            $stmt = $conn->prepare("INSERT INTO CARGOS (NOME_CARGO) VALUES (?)");
            $stmt->execute([$cargo_nome]);
            $cargo_id = $conn->lastInsertId();
        } else { 
            $stmt = $conn->prepare("UPDATE CARGOS SET NOME_CARGO = ? WHERE ID = ?");
            $stmt->execute([$cargo_nome, $cargo_id]);
        }

        
        $stmt_del = $conn->prepare("DELETE FROM CARGO_PERMISSOES WHERE CARGO_ID = ?");
        $stmt_del->execute([$cargo_id]);

        
        if (!empty($permissoes_selecionadas)) {
            $sql_insert = "INSERT INTO CARGO_PERMISSOES (CARGO_ID, PERMISSAO_ID) VALUES (?, ?)";
            $stmt_insert = $conn->prepare($sql_insert);
            foreach ($permissoes_selecionadas as $permissao_id) {
                $stmt_insert->execute([$cargo_id, (int)$permissao_id]);
            }
        }
        
        $conn->commit();
        header("Location: usuario.php?tab=gerenciar_cargos&status=success&msg=" . urlencode("Cargo e Permissões salvos com sucesso!"));
        exit;

    } catch (Exception $e) {
        $conn->rollBack();
        $msg = urlencode("Erro ao salvar cargo: " . $e->getMessage());
        header("Location: usuario.php?tab=cadastro_cargo&action=edit&cargo_id={$cargo_id}&status=error&msg={$msg}");
        exit;
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_nome'])) {
    $conn->beginTransaction();
    try {
        $id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
        
        $user_nome = trim($_POST['user_nome']);
        $user_login = trim($_POST['user_login']);
        $user_cargo_id = filter_input(INPUT_POST, 'user_cargo_id', FILTER_VALIDATE_INT);
        $user_email = trim($_POST['user_email']);
        $user_inativo = filter_input(INPUT_POST, 'user_inativo', FILTER_VALIDATE_INT);
        
        $senha = $_POST['user_senha'] ?? '';
        $confirma_senha = $_POST['user_confirma_senha'] ?? '';
        $hash_senha = null;

        
        if (empty($id) || !empty($senha)) { 
            if (empty($senha)) {
                throw new Exception("A senha é obrigatória para novos usuários.");
            }
            if ($senha !== $confirma_senha) {
                throw new Exception("A senha e a confirmação de senha não coincidem.");
            }
            
            $hash_senha = password_hash($senha, PASSWORD_DEFAULT);
        }

        if (empty($id)) { 
            
            $stmt_check = $conn->prepare("SELECT COUNT(*) FROM USUARIO WHERE UCLOGIN = ?");
            $stmt_check->execute([$user_login]);
            if ($stmt_check->fetchColumn() > 0) {
                throw new Exception("O login '{$user_login}' já está em uso.");
            }
            
            
            $sql = "INSERT INTO USUARIO (UCUSERNAME, UCLOGIN, UCPASSWORD, UCEMAIL, CARGO_ID, UCINATIVE, UCTYPEREC, UCPASSEXPIRED, UCUSEREXPIRED, UCUSERDAYSSUN) 
                    VALUES (?, ?, ?, ?, ?, ?, 'C', 0, 0, 0)"; 
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([$user_nome, $user_login, $hash_senha, $user_email, $user_cargo_id, $user_inativo]);
            $id = $conn->lastInsertId();

        } else { 
            
            $updates = [
                'UCUSERNAME = ?', 'UCEMAIL = ?', 'CARGO_ID = ?', 'UCINATIVE = ?'
            ];
            $valores = [$user_nome, $user_email, $user_cargo_id, $user_inativo];

            if (!empty($hash_senha)) {
                $updates[] = 'UCPASSWORD = ?';
                $valores[] = $hash_senha;
            }
            
            $sql = "UPDATE USUARIO SET " . implode(', ', $updates) . " WHERE UCIDUSER = ?";
            $valores[] = $id;

            $stmt = $conn->prepare($sql);
            $stmt->execute($valores);
        }

        $conn->commit();
        header("Location: usuario.php?tab=gerenciar_usuarios&status=success&msg=" . urlencode("Usuário salvo com sucesso!"));
        exit;

    } catch (Exception $e) {
        $conn->rollBack();
        $msg = urlencode("Erro ao salvar: " . $e->getMessage());
        header("Location: usuario.php?tab=cadastro_usuario&action=edit&id={$id}&status=error&msg={$msg}");
        exit;
    }
}



?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciamento de Usuários e Acesso</title>
    <link rel="stylesheet" href="../css/style_sistema.css">
    <style>
        .permissoes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 15px;
        }
        .permissoes-grupo {
            border: 1px solid #ccc;
            border-radius: 5px;
            padding: 10px;
        }
        .permissoes-grupo h4 {
            margin-top: 0;
            border-bottom: 1px solid #eee;
            padding-bottom: 5px;
            font-size: 1em;
            color: #2c3e50;
        }
        .permissoes-grupo label {
            display: block;
            margin-bottom: 10px;
            font-weight: normal;
            font-size: 0.9em;
            display: flex;
            align-items: center;
            gap: 10px;

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
                            <li><a href="usuario.php"><span>Gerenciar Usuários</span></a></li>
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
            <h1>Gerenciamento de Usuários</h1>

             <?php if (!empty($message)): ?>
                <div class="alert <?= $message_type ?>">
                    <?= $message ?>
                </div>
            <?php endif; ?>

           <?php 
                if (($action === 'edit') && !empty($usuario_para_editar)) :
            ?>
                <div class= "form-tollbar client-header" style="display: flex; justify-content: space-between;">
                    <div class="client-info">
                       <strong>NOME:</strong> <?= htmlspecialchars($usuario_para_editar['UCUSERNAME'] ?? '')?><br><br>
                        CARGO: <?= htmlspecialchars($usuario_para_editar['NOME_CARGO']?? '') ?>
                    </div>

                    <div class="client-actions">
                        <a href="usuario.php" class="btn btn-danger">Voltar para Consulta</a>
                    </div>
                </div>


            <?php else :?>
                <div class="form-toolbar">
                    <?php if (temPermissao('gerenciar_usuarios')): ?>
                        <a href="usuario.php?action=new" class="btn">Novo</a>
                    <?php endif; ?>        
                </div>
            <?php endif; ?>

            <div class="tabs">
                <div class="tab-buttons">
                    <button type="button" class="tab-button <?= $aba_ativa == 'gerenciar_usuarios' ? 'active' : '' ?>" <?=$aba_ativa == 'gerenciar_usuarios' ? 'active' : '' ?>" data-tab="gerenciar_usuarios" <?php if ($aba_ativa == 'cadastro_usuario'): ?> style="display: none;" <?php endif;?>>Usuários</button>
                    <button type="button" class="tab-button <?= $aba_ativa == 'gerenciar_cargos' ? 'active' : '' ?>" <?=$aba_ativa == 'gerenciar_cargos' ? 'active' : '' ?>" data-tab="gerenciar_cargos" <?php if ($aba_ativa == 'cadastro_cargo'): ?> style="display: none;" <?php endif;?>>Cargos e Permissões</button>
                    <button type="button" class="tab-button <?= $aba_ativa == 'cadastro_usuario' ? 'active' : '' ?>" data-tab="cadastro_usuario" style="display: none;"></button>
                    <button type="button" class="tab-button <?= $aba_ativa == 'cadastro_cargo' ? 'active' : '' ?>" data-tab="cadastro_cargo" style="display: none;"></button>
                </div>

                <div class="tab-content">
                    
                    <div id="gerenciar_usuarios" class="tab-pane <?= $aba_ativa == 'gerenciar_usuarios' ? 'active' : '' ?>">
                        <div class="table-container">
                            <p class="table-note">Selecione para Editar/Excluir</p>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>CÓDIGO</th>
                                        <th>NOME</th>
                                        <th>LOGIN</th>
                                        <th>CARGO</th>
                                        <th>ATIVO</th>
                                        <th style="text-align: center;">AÇÃO</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($usuarios as $user): ?>
                                        <tr class="clickable-row" data-id="<?= $user['UCIDUSER'] ?>">
                                            <td><?= htmlspecialchars($user['UCIDUSER']) ?></td>
                                            <td><?= htmlspecialchars($user['UCUSERNAME']) ?></td>
                                            <td><?= htmlspecialchars($user['UCLOGIN']) ?></td>
                                            <td><?= htmlspecialchars($user['NOME_CARGO'] ?? 'Não Atribuído') ?></td>
                                            <td><?= $user['UCINATIVE'] == 0 ? 'SIM' : 'NÃO' ?></td>
                                             <td style="text-align: center; white-space: nowrap; gap: 15px; display: flex; justify-content: center;">
                                                <?php if (temPermissao('gerenciar_usuarios')) : ?>
                                                    <a href="usuario.php?action=edit&id=<?=$user['UCIDUSER'] ?>" class="btn btn-primary">Editar</a>
                                                <?php endif; ?>
                                                <?php if (temPermissao('gerenciar_usuarios')) : ?>
                                                    <a href="usuario..php?action=delete&id=<?=$user['UCIDUSER'] ?>" class= "btn btn-danger btn-excluir-linha">Excluir</a>
                                                <?php endif; ?>
                                            </td>    
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    </div>
                    
                    <div id="cadastro_usuario" class="tab-pane <?= $aba_ativa == 'cadastro_usuario' ? 'active' : '' ?>">
                        <form method="POST" action="usuario.php?tab=cadastro_usuario">
                            <input type="hidden" name="user_id" value="<?= htmlspecialchars($usuario_para_editar['UCIDUSER'] ?? '') ?>">
                            <fieldset class="form-section">
                                <legend>Dados do Usuário</legend>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Nome Completo:</label>
                                        <input type="text" name="user_nome" value="<?= htmlspecialchars($usuario_para_editar['UCUSERNAME'] ?? '') ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Login:</label>
                                        <input type="text" name="user_login" value="<?= htmlspecialchars($usuario_para_editar['UCLOGIN'] ?? '') ?>" required <?= !empty($usuario_para_editar['UCLOGIN']) ? 'readonly' : '' ?>>
                                    </div>
                                    <div class="form-group">
                                        <label>Cargo:</label>
                                        <select name="user_cargo_id" required>
                                            <option value="">Selecione um Cargo</option>
                                            <?php foreach ($cargos_do_sistema as $cargo): ?>
                                                <option value="<?= $cargo['ID'] ?>" 
                                                        <?= ($usuario_para_editar['CARGO_ID'] ?? '') == $cargo['ID'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($cargo['NOME_CARGO']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Senha:</label>
                                        <input type="password" name="user_senha" id="user_senha" <?= empty($usuario_para_editar['UCIDUSER']) ? 'required' : '' ?> placeholder="<?= empty($usuario_para_editar['UCIDUSER']) ? 'Obrigatório' : 'Deixe vazio para não alterar' ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>Confirme a Senha:</label>
                                        <input type="password" name="user_confirma_senha" id="user_confirma_senha" placeholder="Confirmação da nova senha">
                                    </div>
                                    <div class="form-group">
                                        <label>Email:</label>
                                        <input type="email" name="user_email" value="<?= htmlspecialchars($usuario_para_editar['UCEMAIL'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Ativo:</label>
                                        <?php $ativo = $usuario_para_editar['UCINATIVE'] ?? 0; ?>
                                        <select name="user_inativo">
                                            <option value="0" <?= $ativo == 0 ? 'selected' : '' ?>>Sim</option>
                                            <option value="1" <?= $ativo == 1 ? 'selected' : '' ?>>Não</option>
                                        </select>
                                    </div>
                                </div>
                            </fieldset>
                            <div class="form-footer">
                                <button type="submit" class="btn btn-primary">Salvar Usuário</button>
                                <a href="usuario.php" class="btn btn-danger">Cancelar</a>
                            </div>
                        </form>
                    </div>

                    <div id="gerenciar_cargos" class="tab-pane <?= $aba_ativa == 'gerenciar_cargos' ? 'active' : '' ?>">
                        <div class="table-container">
                            <p class="table-note">Clique em um cargo para ver/editar permissões.</p>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>CÓDIGO</th>
                                        <th>CARGO</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cargos_do_sistema as $cargo): ?>
                                        <tr class="clickable-row" data-id="<?= $cargo['ID'] ?>" data-action="edit_cargo">
                                            <td><?= htmlspecialchars($cargo['ID']) ?></td>
                                            <td><?= htmlspecialchars($cargo['NOME_CARGO']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="form-footer" style="justify-content: flex-start;">
                            <a href="usuario.php?action=new_cargo" class="btn">Novo Cargo</a>
                        </div>
                    </div>
                    
                    <div id="cadastro_cargo" class="tab-pane <?= $aba_ativa == 'cadastro_cargo' ? 'active' : '' ?>">
                        <form method="POST" action="usuario.php">
                            <input type="hidden" name="cargo_id" id="cargo_id" value="<?= htmlspecialchars($cargo_para_editar['ID'] ?? '') ?>">
                            <input type="hidden" name="action_type" value="salvar_cargo">
                            <fieldset class="form-section">
                                <legend>Cadastro de Cargo</legend>
                                <div class="form-group" style="max-width: 400px;">
                                    <label>Nome do Cargo:</label>
                                    <input type="text" name="cargo_nome" id="cargo_nome" value="<?= htmlspecialchars($cargo_para_editar['NOME_CARGO'] ?? '') ?>" required>
                                </div>
                            </fieldset>
                            
                            <fieldset class="form-section">
                                <legend>Permissões</legend>
                                <div class="permissoes-grid">
                                    <?php foreach ($permissoes_por_grupo as $grupo => $permissoes): ?>
                                        <div class="permissoes-grupo">
                                            <h4><?= htmlspecialchars($grupo) ?></h4>
                                            <?php foreach ($permissoes as $permissao): 
                                                $checked = in_array($permissao['ID'], $permissoes_do_cargo) ? 'checked' : '';
                                            ?>
                                                <label>
                                                    <input type="checkbox" name="permissoes[]" value="<?= $permissao['ID'] ?>" <?= $checked ?>>
                                                    <?= htmlspecialchars($permissao['DESCRICAO']) ?>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </fieldset>
                            <div class="form-footer">
                                <button type="submit" class="btn btn-primary">Salvar Cargo</button>
                                <a href="usuario.php?tab=gerenciar_cargos" class="btn btn-danger">Cancelar</a>
                            </div>
                            </form>
                    </div>

                </div>
            </div>
        </main>
    </div>
    
    <script src="../js/script.js"></script>
    <script src="../js/script_menu.js"></script>
    <script src="../js/script_modal.js"></script>
    <script src="../js/script_mascara.js"></script>
    <script src="../js/script_main.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('tr.clickable-row[data-action="edit_cargo"]').forEach(row => {
                row.addEventListener('click', function() {
                    const cargoId = this.dataset.id;
                    window.location.href = `usuario.php?tab=cadastro_cargo&action=edit&cargo_id=${cargoId}`;
                });
            });
            
            const btnNovoCargo = document.querySelector('a[href="usuario.php?action=new_cargo"]');
            if (btnNovoCargo) {
                btnNovoCargo.addEventListener('click', function(e) {
                    e.preventDefault();
                    window.location.href = 'usuario.php?tab=cadastro_cargo&action=new';
                });
            }
        });
    </script>
</body>
</html>