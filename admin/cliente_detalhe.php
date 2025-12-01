<?php
require_once 'auth_check.php';
require_once '../bd/config.php';

// --- 1. VERIFICAR O ID ---
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: clientes.php?erro=id_invalido');
    exit;
}
$cliente_id = (int)$_GET['id'];

// --- 2. BUSCAR DADOS PRINCIPAIS DO CLIENTE ---
try {
    $sql_cliente = "SELECT * FROM tb_client_users WHERE id = ?";
    $stmt_cliente = $pdo->prepare($sql_cliente);
    $stmt_cliente->execute([$cliente_id]);
    $cliente = $stmt_cliente->fetch(PDO::FETCH_ASSOC);

    if (!$cliente) {
        throw new Exception("Cliente não encontrado.");
    }

    // --- 3. BUSCAR ENDEREÇOS DO CLIENTE ---
    $sql_enderecos = "SELECT * FROM tb_client_addresses WHERE cliente_id = ?";
    $stmt_enderecos = $pdo->prepare($sql_enderecos);
    $stmt_enderecos->execute([$cliente_id]);
    $enderecos = $stmt_enderecos->fetchAll(PDO::FETCH_ASSOC);

    // --- 4. BUSCAR HISTÓRICO DE PEDIDOS DO CLIENTE ---
    $sql_pedidos = "SELECT id, data_pedido, valor_total, status_pagamento 
                    FROM tb_pedidos 
                    WHERE cliente_id = ? 
                    ORDER BY data_pedido DESC";
    $stmt_pedidos = $pdo->prepare($sql_pedidos);
    $stmt_pedidos->execute([$cliente_id]);
    $pedidos = $stmt_pedidos->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die("Erro: " . $e->getMessage());
}

$admin_nome = $_SESSION['admin_nome'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalhe do Cliente - Admin</title>
    <link rel="stylesheet" href="css/admin_style.css">
    <style>
        .details-layout {
            display: grid;
            grid-template-columns: 1fr 2fr; 
            gap: 30px;
        }
        .box {
            background-color: #1a1a1a; padding: 1.5rem;
            border-radius: 8px; border: 1px solid #333;
        }
        .box h2 {
            font-size: 1.3rem; color: var(--color-accent, #bb9a65);
            border-bottom: 1px solid #444;
            padding-bottom: 10px; margin-bottom: 20px;
        }
        
        /* Lista de Detalhes */
        .details-list p {
            font-size: 0.95rem; color: #ccc;
            line-height: 1.7; margin-bottom: 10px;
        }
        .details-list p strong { color: #fff; }
        .details-list .status-ativo { color: #28a745; font-weight: bold; }
        .details-list .status-inativo { color: #e64c4c; font-weight: bold; }
        
        /* Tabela de Pedidos */
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { padding: 12px; border-bottom: 1px solid #333; text-align: left; }
        .data-table th { font-size: 0.9rem; }
        .data-table .acoes a { color: #bb9a65; }
        .status-aguardando { color: #a98a54; }
        .status-pago { color: #28a745; }
    </style>
</head>
<body>

    <div class="admin-layout">
        <aside class="admin-sidebar">
           <div class="logo"><img src="../img/logo.png"></div>
            <nav class="admin-nav">
                <a href="dashboard.php">Dashboard</a>
                <a href="pedidos.php">Pedidos</a>
                <a href="produtos.php">Produtos</a>
                <a href="categorias.php">Categorias</a>
                <a href="marcas.php">Marcas</a>
                <a href="clientes.php" class="active">Clientes</a> 
                <a href="inscritos.php">Inscritos</a>
                <a href="config_site.php">Config. do Site</a>
            </nav>
        </aside>

        <div class="admin-main">
            <header class="admin-header">
                <div class="admin-user-info">
                    <span>Olá, <strong><?php echo htmlspecialchars($admin_nome); ?></strong></span>
                    <a href="logout.php">Sair</a>
                </div>
            </header>

            <main class="admin-content">
                <h1><?php echo htmlspecialchars($cliente['cliente_nome'] . ' ' . $cliente['cliente_sobrenome']); ?></h1>
                
                <div class="details-layout">
                    
                    <div>
                        <div class="box">
                            <h2>Detalhes do Cliente</h2>
                            <div class="details-list">
                                <p><strong>ID:</strong> <?php echo $cliente['id']; ?></p>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($cliente['cliente_email']); ?></p>
                                <p><strong>Data Cadastro:</strong> <?php echo date('d/m/Y', strtotime($cliente['data_cadastro'])); ?></p>
                                <p><strong>Status:</strong> 
                                    <?php if ($cliente['ativo']): ?>
                                        <span class="status-ativo">Ativo</span>
                                    <?php else: ?>
                                        <span class="status-inativo">Inativo</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>

                        <div class="box" style="margin-top: 20px;">
                            <h2>Endereços</h2>
                            <?php if (empty($enderecos)): ?>
                                <p style="color: #888;">Nenhum endereço cadastrado.</p>
                            <?php else: ?>
                                <?php foreach ($enderecos as $endereco): ?>
                                    <div class="details-list" style="border-bottom: 1px solid #333; padding-bottom: 15px; margin-bottom: 15px;">
                                        <p><strong><?php echo htmlspecialchars($endereco['tipo']); ?>:</strong></p>
                                        <p><?php echo htmlspecialchars($endereco['endereco'] . ', ' . $endereco['numero']); ?></p>
                                        <p><?php echo htmlspecialchars($endereco['bairro']); ?></p>
                                        <p><?php echo htmlspecialchars($endereco['cidade'] . ' - ' . $endereco['estado']); ?></p>
                                        <p><strong>CEP:</strong> <?php echo htmlspecialchars($endereco['cep']); ?></p>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="box">
                        <h2>Histórico de Pedidos</h2>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Pedido ID</th>
                                    <th>Data</th>
                                    <th>Status Pag.</th>
                                    <th>Total</th>
                                    <th>Ação</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($pedidos)): ?>
                                    <tr><td colspan="5" style="text-align:center; color: #888;">Nenhum pedido realizado por este cliente.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($pedidos as $pedido): ?>
                                        <tr>
                                            <td>#<?php echo $pedido['id']; ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($pedido['data_pedido'])); ?></td>
                                            <td>
                                                <?php if ($pedido['status_pagamento'] == 'Pago'): ?>
                                                    <span class="status-pago">Pago</span>
                                                <?php else: ?>
                                                    <span class="status-aguardando">Aguardando</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>R$ <?php echo number_format($pedido['valor_total'], 2, ',', '.'); ?></td>
                                            <td class="acoes">
                                                <a href="pedido_detalhe.php?id=<?php echo $pedido['id']; ?>">Ver Pedido</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                </div>
            </main>
        </div>
    </div>

</body>
</html>