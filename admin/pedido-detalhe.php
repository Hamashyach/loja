<?php
require_once 'auth_check.php';
require_once '../bd/config.php';

// --- 1. VERIFICAR O ID ---
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: pedidos.php?erro=id_invalido');
    exit;
}
$pedido_id = (int)$_GET['id'];

// --- 2. BUSCAR DADOS PRINCIPAIS DO PEDIDO E CLIENTE ---
try {
    $sql = "SELECT 
                p.*, 
                c.cliente_nome, 
                c.cliente_sobrenome,
                c.cliente_email
            FROM 
                tb_pedidos p
            JOIN 
                tb_client_users c ON p.cliente_id = c.id
            WHERE 
                p.id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$pedido_id]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pedido) {
        throw new Exception("Pedido não encontrado.");
    }

    // --- 3. BUSCAR ITENS DO PEDIDO ---
    $sql_itens = "SELECT 
                    i.quantidade, 
                    i.preco_unitario, 
                    pr.nome AS produto_nome, 
                    pr.sku AS produto_sku
                FROM 
                    tb_itens_pedido i
                JOIN 
                    tb_produtos pr ON i.produto_id = pr.id
                WHERE 
                    i.pedido_id = ?";
    
    $stmt_itens = $pdo->prepare($sql_itens);
    $stmt_itens->execute([$pedido_id]);
    $itens = $stmt_itens->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Detalhe do Pedido #<?php echo $pedido_id; ?> - Admin</title>
    <link rel="stylesheet" href="css/admin_style.css">
    <style>
        .order-details-layout {
            display: grid;
            grid-template-columns: 2fr 1fr; /* Coluna da esquerda maior */
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
        
        /* Tabela de Itens */
        .items-table { width: 100%; border-collapse: collapse; }
        .items-table th, .items-table td { padding: 12px; border-bottom: 1px solid #333; text-align: left; }
        .items-table th { font-size: 0.9rem; }
        .items-table .item-total { font-weight: bold; }
        
        /* Detalhes do Cliente/Endereço */
        .details-list p {
            font-size: 0.95rem; color: #ccc;
            line-height: 1.7; margin-bottom: 10px;
        }
        .details-list p strong { color: #fff; }
        
        /* Formulário de Status */
        .status-form .form-group { margin-bottom: 20px; }
        .status-form label { display: block; margin-bottom: 8px; font-weight: 500; color: #ccc; }
        .btn-salvar {
            font-size: 1rem; font-weight: 600; padding: 0.8rem 1.5rem; color: #000;
            background-color: var(--color-accent, #bb9a65); border: none;
            border-radius: 6px; cursor: pointer; transition: background-color 0.2s; width: 100%;
        }
        .btn-salvar:hover { background-color: #a98a54; }

        .admin-alert {
            padding: 1rem; margin-bottom: 1.5rem; border-radius: 6px;
            font-weight: 500; border: 1px solid;
        }
        .admin-alert.success {
            background-color: #2a4a34; color: #d1f0db; border-color: #28a745;
        }
        .admin-alert.error {
            background-color: #4a2020; color: #f5c5c5; border-color: #e64c4c;
        }
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
                <h1>Detalhes do Pedido #<?php echo $pedido['id']; ?></h1>

                <?php
                // --- (INÍCIO) BLOCO DE MENSAGEM ---
                if (isset($_GET['sucesso']) && $_GET['sucesso'] == '1') {
                    echo '<div class="admin-alert success">Status do pedido atualizado com sucesso!</div>';
                }
                if (isset($_GET['erro']) && $_GET['erro'] == 'atualizar') {
                    echo '<div class="admin-alert error">Erro ao atualizar o status do pedido.</div>';
                }
                // --- (FIM) BLOCO DE MENSAGEM ---
                ?>

                <p style="color: #ccc; margin-top: -15px; margin-bottom: 20px;">
                    Feito em: <?php echo date('d/m/Y H:i', strtotime($pedido['data_pedido'])); ?>
                </p>
                <p style="color: #ccc; margin-top: -15px; margin-bottom: 20px;">
                    Feito em: <?php echo date('d/m/Y H:i', strtotime($pedido['data_pedido'])); ?>
                </p>

                <div class="order-details-layout">
                    
                    <div class="box">
                        <h2>Itens Comprados</h2>
                        <table class="items-table">
                            <thead>
                                <tr>
                                    <th>Produto</th>
                                    <th>SKU</th>
                                    <th>Preço Unit.</th>
                                    <th>Qtd.</th>
                                    <th>Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($itens as $item): 
                                    $subtotal_item = $item['preco_unitario'] * $item['quantidade'];
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['produto_nome']); ?></td>
                                    <td><?php echo htmlspecialchars($item['produto_sku']); ?></td>
                                    <td>R$ <?php echo number_format($item['preco_unitario'], 2, ',', '.'); ?></td>
                                    <td><?php echo $item['quantidade']; ?></td>
                                    <td class="item-total">R$ <?php echo number_format($subtotal_item, 2, ',', '.'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <h3 style="text-align: right; margin-top: 20px;">
                            Valor Total: 
                            <span style="color: var(--color-accent); font-size: 1.5rem;">
                                R$ <?php echo number_format($pedido['valor_total'], 2, ',', '.'); ?>
                            </span>
                        </h3>
                    </div>

                    <div>
                        <div class="box">
                            <h2>Cliente</h2>
                            <div class="details-list">
                                <p><strong>Nome:</strong> <?php echo htmlspecialchars($pedido['cliente_nome'] . ' ' . $pedido['cliente_sobrenome']); ?></p>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($pedido['cliente_email']); ?></p>
                            </div>
                        </div>

                        <div class="box" style="margin-top: 20px;">
                            <h2>Endereço de Entrega</h2>
                            <div class="details-list">
                                <p><?php echo htmlspecialchars($pedido['entrega_endereco'] . ', ' . $pedido['entrega_numero']); ?></p>
                                <p><?php echo htmlspecialchars($pedido['entrega_bairro']); ?>
                                   <?php if ($pedido['entrega_complemento']) echo ' - ' . htmlspecialchars($pedido['entrega_complemento']); ?>
                                </p>
                                <p><?php echo htmlspecialchars($pedido['entrega_cidade'] . ' - ' . $pedido['entrega_estado']); ?></p>
                                <p><strong>CEP:</strong> <?php echo htmlspecialchars($pedido['entrega_cep']); ?></p>
                            </div>
                        </div>
                        
                        <div class="box" style="margin-top: 20px;">
                            <h2>Atualizar Status</h2>
                            <form action="pedido_atualizar.php" method="POST" class="status-form">
                                <input type="hidden" name="pedido_id" value="<?php echo $pedido['id']; ?>">
                                
                                <div class="form-group">
                                    <label for="status_pagamento">Status do Pagamento</label>
                                    <select name="status_pagamento" id="status_pagamento" class="form-control">
                                        <option value="Aguardando Pagamento" <?php echo ($pedido['status_pagamento'] == 'Aguardando Pagamento') ? 'selected' : ''; ?>>Aguardando Pagamento</option>
                                        <option value="Pago" <?php echo ($pedido['status_pagamento'] == 'Pago') ? 'selected' : ''; ?>>Pago</option>
                                        <option value="Cancelado" <?php echo ($pedido['status_pagamento'] == 'Cancelado') ? 'selected' : ''; ?>>Cancelado</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="status_entrega">Status da Entrega</label>
                                    <select name="status_entrega" id="status_entrega" class="form-control">
                                        <option value="Nao Enviado" <?php echo ($pedido['status_entrega'] == 'Nao Enviado') ? 'selected' : ''; ?>>Não Enviado</option>
                                        <option value="Em Separacao" <?php echo ($pedido['status_entrega'] == 'Em Separacao') ? 'selected' : ''; ?>>Em Separação</option>
                                        <option value="Enviado" <?php echo ($pedido['status_entrega'] == 'Enviado') ? 'selected' : ''; ?>>Enviado</option>
                                        <option value="Entregue" <?php echo ($pedido['status_entrega'] == 'Entregue') ? 'selected' : ''; ?>>Entregue</option>
                                    </select>
                                </div>
                                
                                <button type="submit" class="btn-salvar">Atualizar Pedido</button>
                            </form>
                        </div>
                    </div>

                </div>
            </main>
        </div>
    </div>

</body>
</html>