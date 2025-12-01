<?php
// 1. O Guarda de Segurança e Conexão
require_once 'auth_check.php';
require_once '../bd/config.php';

// --- 2. BUSCAR AS ESTATÍSTICAS ---
try {
    // Total Faturado (Pedidos com status 'Pago')
    $stmt_faturado = $pdo->query("SELECT SUM(valor_total) AS total_faturado FROM tb_pedidos WHERE status_pagamento = 'Pago'");
    $total_faturado = $stmt_faturado->fetchColumn();

    // Pedidos para Enviar (Pagos, mas não enviados)
    $stmt_enviar = $pdo->query("SELECT COUNT(id) AS total_enviar FROM tb_pedidos WHERE status_pagamento = 'Pago' AND status_entrega = 'Nao Enviado'");
    $total_para_enviar = $stmt_enviar->fetchColumn();
    
    // Pedidos Pendentes (Aguardando Pagamento)
    $stmt_pendentes = $pdo->query("SELECT COUNT(id) AS total_pendentes FROM tb_pedidos WHERE status_pagamento = 'Aguardando Pagamento'");
    $total_pendentes = $stmt_pendentes->fetchColumn();

    // Total de Clientes Cadastrados
    $stmt_clientes = $pdo->query("SELECT COUNT(id) AS total_clientes FROM tb_client_users");
    $total_clientes = $stmt_clientes->fetchColumn();

    // Buscar os 5 últimos pedidos para a tabela
    $stmt_ultimos = $pdo->query("
        SELECT p.id, p.valor_total, p.data_pedido, c.cliente_nome, c.cliente_sobrenome
        FROM tb_pedidos p
        JOIN tb_client_users c ON p.cliente_id = c.id
        ORDER BY p.data_pedido DESC
        LIMIT 5
    ");
    $ultimos_pedidos = $stmt_ultimos->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro ao buscar estatísticas: " . $e->getMessage());
}

$admin_nome = $_SESSION['admin_nome'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Painel Admin</title>
    <link rel="stylesheet" href="css/admin_style.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background-color: #1a1a1a;
            padding: 25px;
            border-radius: 8px;
            border: 1px solid #333;
        }
        .stat-card h3 {
            font-size: 1rem;
            color: #ccc;
            text-transform: uppercase;
            margin-bottom: 10px;
        }
        .stat-card .stat-value {
            font-size: 2.5rem;
            font-weight: bold;
            color: #fff;
        }
        .stat-card .stat-value.currency {
            color: var(--color-accent, #bb9a65);
        }
        
        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #fff;
            margin-bottom: 20px;
            border-bottom: 1px solid #333;
            padding-bottom: 10px;
        }
        
        /* Reutiliza o CSS da tabela de pedidos */
        .data-table { width: 100%; border-collapse: collapse; background-color: #1a1a1a; }
        .data-table th, .data-table td { border: 1px solid #333; padding: 0.75rem 1rem; text-align: left; }
        .data-table th { background-color: #252525; font-size: 0.9rem; }
        .data-table td { font-size: 0.95rem; }
        .data-table .acoes a { color: #bb9a65; }
    </style>
</head>
<body>

    <div class="admin-layout">
        <aside class="admin-sidebar">
            <div class="logo"><img src="../img/logo.png"></div>
            <nav class="admin-nav">
                <a href="dashboard.php" class="active">Dashboard</a> <a href="pedidos.php">Pedidos</a>
                <a href="produtos.php">Produtos</a>
                <a href="categorias.php">Categorias</a>
                <a href="marcas.php">Marcas</a>
                 <a href="clientes.php">Clientes</a>
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
                <h1>Painel de Vendas</h1>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3>Total Faturado (Pago)</h3>
                        <p class="stat-value currency">R$ <?php echo number_format($total_faturado ?? 0, 2, ',', '.'); ?></p>
                    </div>
                    <div class="stat-card">
                        <h3>Pedidos Para Enviar</h3>
                        <p class="stat-value"><?php echo $total_para_enviar ?? 0; ?></p>
                    </div>
                    <div class="stat-card">
                        <h3>Pag. Pendentes</h3>
                        <p class="stat-value"><?php echo $total_pendentes ?? 0; ?></p>
                    </div>
                    <div class="stat-card">
                        <h3>Total de Clientes</h3>
                        <p class="stat-value"><?php echo $total_clientes ?? 0; ?></p>
                    </div>
                </div>
                <h2 class="section-title">Últimos Pedidos Recebidos</h2>

                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Pedido ID</th>
                            <th>Data</th>
                            <th>Cliente</th>
                            <th>Valor Total</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($ultimos_pedidos)): ?>
                            <tr>
                                <td colspan="5" style="text-align:center;">Nenhum pedido recebido ainda.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($ultimos_pedidos as $pedido): ?>
                                <tr>
                                    <td>#<?php echo $pedido['id']; ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($pedido['data_pedido'])); ?></td>
                                    <td><?php echo htmlspecialchars($pedido['cliente_nome'] . ' ' . $pedido['cliente_sobrenome']); ?></td>
                                    <td>R$ <?php echo number_format($pedido['valor_total'], 2, ',', '.'); ?></td>
                                    <td class="acoes">
                                        <a href="pedido_detalhe.php?id=<?php echo $pedido['id']; ?>">Ver Detalhes</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                </main>
        </div>
    </div>

</body>
</html>