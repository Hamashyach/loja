<?php
require_once 'auth_check.php';
require_once '../bd/config.php';

//  VERIFICAR O ID 
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: pedidos.php?erro=id_invalido');
    exit;
}
$pedido_id = (int)$_GET['id'];

function getNomeServicoFrete($id) {
    $servicos = [
        1 => 'Correios PAC',
        2 => 'Correios SEDEX',
        3 => 'Jadlog Package',
        4 => 'Jadlog .Com',
        17 => 'Correios Mini Envio',
        18 => 'Loggi',
    ];
    return $servicos[$id] ?? 'Transportadora (ID: ' . $id . ')';
}

// BUSCAR DADOS PRINCIPAIS DO PEDIDO E CLIENTE 
try {
    $sql = "SELECT 
                p.*, 
                c.cliente_nome, 
                c.cliente_sobrenome,
                c.cliente_email,
                c.cliente_contato,
                c.cliente_cpf
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

    // BUSCAR ITENS DO PEDIDO 
    $sql_itens = "SELECT 
                    i.quantidade, 
                    i.preco_unitario,
                    i.tamanho,
                    i.cor,
                    pr.nome AS produto_nome, 
                    pr.sku AS produto_sku,
                    pr.imagem_principal AS imagem_variacao
                FROM 
                    tb_itens_pedido i
                JOIN 
                    tb_produtos pr ON i.produto_id = pr.id
                LEFT JOIN
                    tb_produto_variacoes v
                    ON v.produto_id = i.produto_id 
                    AND v.cor_modelo = i.cor 
                    AND v.tamanho = i.tamanho
                WHERE 
                    i.pedido_id = ?";
    
    $stmt_itens = $pdo->prepare($sql_itens);
    $stmt_itens->execute([$pedido_id]);
    $itens = $stmt_itens->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die("Erro: " . $e->getMessage());
}

$admin_nome = $_SESSION['admin_nome'] ?? 'Admin';

$soma_produtos = 0;
foreach ($itens as $item) {
    $soma_produtos += ($item['preco_unitario'] * $item['quantidade']);
}
$valor_frete = $pedido['valor_total'] - $soma_produtos;
// Evitar valores negativos por arredondamento
if ($valor_frete < 0) $valor_frete = 0;

$nome_frete = getNomeServicoFrete($pedido['frete_servico_id']);

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalhe do Pedido #<?php echo $pedido_id; ?> - Admin</title>
    <link rel="stylesheet" href="css/admin_style.css">
    <style>
        .order-details-layout { display: grid; grid-template-columns: 2fr 1fr; gap: 30px; }
        .box { background-color: #ffffffff; padding: 1.5rem; border-radius: 8px; border: 1px solid #c5c5c5ff; margin-bottom: 20px; }
        .box h2 { font-size: 1.3rem; color: #000000ff; border-bottom: 1px solid #d1d1d1ff; padding-bottom: 10px; margin-bottom: 20px; }
        .items-table { width: 100%; border-collapse: collapse; }
        .items-table th, .items-table td { padding: 12px; border-bottom: 1px solid #e7e7e7ff; text-align: left; vertical-align: middle; }
        .items-table th { font-size: 0.9rem; color: #000000ff; }
        .items-table td { color: #000000ff; }
        .items-table .item-total { font-weight: bold; }
        .item-meta { font-size: 0.85rem; color: #000000ff; margin-top: 4px; }
        .item-meta span { background: #ffffffff; padding: 2px 6px; border-radius: 4px; margin-right: 5px; }
        .summary-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px dashed #444; font-size: 1rem; color: #000000ff; }
        .summary-row.total { border-bottom: none; border-top: 1px solid #eeeeeeff; margin-top: 15px; padding-top: 15px; font-size: 1.4rem; color: #000000ff; font-weight: bold; }
        .details-list p { font-size: 0.95rem; color: #000000ff; line-height: 1.7; margin-bottom: 10px; }
        .details-list p strong { color: #000000ff; }
        .btn-salvar { font-size: 1rem; font-weight: 600; padding: 0.8rem 1.5rem; color: #000; background-color: #a98a54; border: none; border-radius: 6px; cursor: pointer; transition: background-color 0.2s; width: 100%; text-align: center; display: block; text-decoration: none; }
        .btn-salvar:hover { background-color: #a98a54; }
        .admin-alert { padding: 1rem; margin-bottom: 1.5rem; border-radius: 6px; font-weight: 500; border: 1px solid; }
        .admin-alert.success { background-color: #2a4a34; color: #d1f0db; border-color: #28a745; }
        .admin-alert.error { background-color: #4a2020; color: #f5c5c5; border-color: #e64c4c; }
    </style>
</head>
<body>

    <div class="admin-layout">
        <aside class="admin-sidebar">
           <div class="logo"><img src="../img/logo.png" alt="Logo"></div>
            <nav class="admin-nav">
                <a href="dashboard.php">Dashboard</a>
                <a href="pedidos.php" class="active">Pedidos</a> 
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
                <div class="title-product-detalhe" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h1>Detalhes do Pedido #<?php echo $pedido['id']; ?></h1>
                    <a href="pedidos.php" class="btn-cancelar" style="background:#444; color:#fff; padding: 10px 20px; text-decoration:none; border-radius:5px;">Voltar</a>
                </div>

                <div class="order-details-layout">
                    
                    <div class="left-col">
                        <div class="box">
                            <h2>Itens do Pedido</h2>
                            <table class="items-table">
                                <thead>
                                    <tr>
                                        <th width="60">Img</th>
                                        <th>Produto / Detalhes</th>
                                        <th>Preço Unit.</th>
                                        <th>Qtd.</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($itens as $item): 
                                        $subtotal_item = $item['preco_unitario'] * $item['quantidade'];
                                        
                                        // LÓGICA DA IMAGEM: Se tiver imagem na variação, usa ela. Senão, usa a principal.
                                        if (!empty($item['imagem_variacao'])) {
                                            $imagem_url = '../uploads/produtos/variacoes/' || '../uploads/produtos/galeria/' || '../uploads/produtos/'. $item['imagem_variacao'];
                                        } elseif (!empty($item['imagem_principal'])) {
                                            $imagem_url = '../' . $item['imagem_principal'];
                                        } else {
                                            $imagem_url = '../img/sem-foto.jpg';
                                        }
                                    ?>
                                    <tr>
                                        <td>
                                            <img src="<?php echo $imagem_url; ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($item['produto_nome']); ?></strong><br>
                                            <span style="font-size: 0.8rem; color: #888;">SKU: <?php echo htmlspecialchars($item['produto_sku']); ?></span>
                                            
                                            <div class="item-meta">
                                                <?php if (!empty($item['tamanho']) && $item['tamanho'] != 'Padrão'): ?>
                                                    <span>Tam: <?php echo htmlspecialchars($item['tamanho']); ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($item['cor']) && $item['cor'] != 'Padrão'): ?>
                                                    <span>Cor: <?php echo htmlspecialchars($item['cor']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>R$ <?php echo number_format($item['preco_unitario'], 2, ',', '.'); ?></td>
                                        <td><?php echo $item['quantidade']; ?></td>
                                        <td class="item-total">R$ <?php echo number_format($subtotal_item, 2, ',', '.'); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="box">
                            <h2>Resumo Financeiro</h2>
                            <div class="summary-row">
                                <span>Subtotal Produtos:</span>
                                <span>R$ <?php echo number_format($soma_produtos, 2, ',', '.'); ?></span>
                            </div>
                            <div class="summary-row">
                                <span>Frete (<?php echo htmlspecialchars($nome_frete); ?>):</span>
                                <span>R$ <?php echo number_format($valor_frete, 2, ',', '.'); ?></span>
                            </div>
                            <div class="summary-row total">
                                <span>Total do Pedido:</span>
                                <span style="color: var(--color-accent, #bb9a65);">R$ <?php echo number_format($pedido['valor_total'], 2, ',', '.'); ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="right-col">
                        <div class="box">
                            <h2>Dados do Cliente</h2>
                            <div class="details-list">
                                <p><strong>Nome:</strong> <?php echo htmlspecialchars($pedido['cliente_nome'] . ' ' . $pedido['cliente_sobrenome']); ?></p>
                                <p><strong>CPF:</strong> <?php echo htmlspecialchars($pedido['cliente_cpf']); ?></p>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($pedido['cliente_email']); ?></p>
                                <p><strong>Tel:</strong> <?php echo htmlspecialchars($pedido['cliente_contato']); ?></p>
                            </div>
                        </div>

                        <div class="box">
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
                        
                        <div class="box" style="border: 1px solid #bb9a65;">
                            <h2 style="color: #bb9a65;">Logística</h2>
                            
                            <?php if (!empty($pedido['etiqueta_url'])): ?>
                                <a href="<?php echo $pedido['etiqueta_url']; ?>" target="_blank" class="btn-salvar" style="background: #28a745; color: white;">
                                     Imprimir Etiqueta
                                </a>
                            <?php elseif ($pedido['status_pagamento'] == 'Pago'): ?>
                                <a href="etiqueta_gerar.php?id=<?php echo $pedido['id']; ?>" class="btn-salvar">
                                     Gerar Etiqueta
                                </a>
                            <?php else: ?>
                                <p style="color: #ccc; font-size: 0.9rem; text-align: center;">Aprovar pagamento para gerar etiqueta.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>