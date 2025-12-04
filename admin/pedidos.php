<?php
require_once 'auth_check.php';
require_once '../bd/config.php';

try {
    $sql = "SELECT 
                p.id, 
                p.valor_total, 
                p.status_pagamento, 
                p.status_entrega, 
                p.data_pedido,
                c.cliente_nome, 
                c.cliente_sobrenome
            FROM 
                tb_pedidos p
            JOIN 
                tb_client_users c ON p.cliente_id = c.id
            ORDER BY 
                p.data_pedido DESC"; // Mais recentes primeiro

    $stmt = $pdo->query($sql);
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro ao buscar pedidos: " . $e->getMessage());
}

$admin_nome = $_SESSION['admin_nome'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Pedidos - Painel Admin</title>
    <link rel="stylesheet" href="css/admin_style.css">
    <style>
        
        /* Estilos de Status */
        .status {
            padding: 5px 10px; border-radius: 4px; font-size: 0.8rem;
            font-weight: bold; text-align: center;
        }
        .status-aguardando { background-color: #a98a54; color: #fff; }
        .status-pago { background-color: #28a745; color: #fff; }
        .status-nao-enviado { background-color: #555; color: #ccc; }
        .status-enviado { background-color: #4a90e2; color: #fff; }
    </style>
</head>
<body>

    <div class="admin-layout">
        <aside class="admin-sidebar">
           <div class="logo"><img src="../img/logo.png"></div>
            <nav class="admin-nav">
                <a href="dashboard.php">Dashboard</a>
                <a href="pedidos.php" class="active">Pedidos</a> <a href="produtos.php">Produtos</a>
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
                <h1>Gerenciar Pedidos</h1>
                
                
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Pedido ID</th>
                            <th>Data</th>
                            <th>Cliente</th>
                            <th>Valor Total</th>
                            <th>Status Pag.</th>
                            <th>Status Entrega</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pedidos)): ?>
                            <tr><td colspan="6">Nenhum pedido encontrado.</td></tr>
                        <?php else: ?>
                            <?php foreach ($pedidos as $pedido): ?>
                                <tr>
                                    <td>#<?php echo $pedido['id']; ?></td>
                                    <td><?php echo htmlspecialchars($pedido['cliente_nome'] . ' ' . $pedido['cliente_sobrenome']); ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($pedido['data_pedido'])); ?></td>
                                    <td>R$ <?php echo number_format($pedido['valor_total'], 2, ',', '.'); ?></td>
                                    
                                    <td>
                                        <?php 
                                            $st = strtolower($pedido['status_pagamento']); // Converte para minúsculo para comparar
                                            if ($st == 'pago' || $st == 'approved' || $st == 'accredited'): 
                                        ?>
                                            <span class="status status-pago" style="background:#d4edda; color:#155724; padding:5px 10px; border-radius:4px;">Pago</span>
                                        <?php else: ?>
                                            <span class="status status-aguardando" style="background:#fff3cd; color:#856404; padding:5px 10px; border-radius:4px;">
                                                <?php echo $pedido['status_pagamento']; // Mostra o status real (Pendente, Aguardando, etc) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <?php if ($pedido['status_entrega'] == 'Enviado' || $pedido['status_entrega'] == 'Etiqueta Gerada'): ?>
                                            <span class="status status-enviado" style="background:#cce5ff; color:#004085; padding:5px 10px; border-radius:4px;"><?php echo $pedido['status_entrega']; ?></span>
                                        <?php else: ?>
                                            <span class="status status-nao-enviado" style="background:#f8d7da; color:#721c24; padding:5px 10px; border-radius:4px;">Não Enviado</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td class="acoes">
                                        <a href="pedido-detalhe.php?id=<?php echo $pedido['id']; ?>" style="color:#007bff; text-decoration:none;">Ver Detalhes</a>
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