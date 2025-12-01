<?php
require 'bd/config.php';
require 'templates/header.php';

$carrinho_itens = [];
$valor_total_carrinho = 0;

if (!empty($_SESSION['carrinho'])) {
    $ids_produtos = array_keys($_SESSION['carrinho']);
    
    if (!empty($ids_produtos)) {
        $placeholders = implode(',', array_fill(0, count($ids_produtos), '?'));
        
        try {
            $sql = "SELECT id, nome, preco, preco_promocional, imagem_principal, estoque 
                    FROM tb_produtos 
                    WHERE id IN ($placeholders)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($ids_produtos);
            $produtos_db = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($produtos_db as $prod) {
                $id = $prod['id'];
                $qtd = $_SESSION['carrinho'][$id]['quantidade'];
                $preco_final = ($prod['preco_promocional'] && $prod['preco_promocional'] > 0) ? $prod['preco_promocional'] : $prod['preco'];
                
                $subtotal = $preco_final * $qtd;
                $valor_total_carrinho += $subtotal;

                $carrinho_itens[] = [
                    'id' => $id,
                    'nome' => $prod['nome'],
                    'imagem' => $prod['imagem_principal'],
                    'preco_unitario' => $preco_final,
                    'quantidade' => $qtd,
                    'subtotal' => $subtotal,
                    'estoque_max' => $prod['estoque']
                ];
            }
        } catch (PDOException $e) {
            echo "Erro ao carregar carrinho.";
        }
    }
}
?>

<main>
    <div class="container" style="padding: 40px 20px;">
        <div class="page-header">
            <h1>Meu Carrinho</h1>
        </div>

        <?php if (empty($carrinho_itens)): ?>
            <div class="empty-cart" style="text-align: center; padding: 50px;">
                <img src="<?php echo BASE_URL; ?>/img/icones/carrinho.png" alt="Carrinho Vazio" style="width: 64px; opacity: 0.5; margin-bottom: 20px;">
                <p style="color: #888; font-size: 1.2rem;">Seu carrinho está vazio.</p>
                <a href="produtos.php" class="cta-button" style="display: inline-block; margin-top: 20px;">Continuar Comprando</a>
            </div>
        <?php else: ?>

            <div class="cart-layout" style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px;">
                
                <div class="cart-list">
                    <div class="cart-header-row" style="display: grid; grid-template-columns: 3fr 1fr 1fr 1fr auto; border-bottom: 1px solid #333; padding-bottom: 10px; margin-bottom: 20px; color: #888; font-size: 0.9rem;">
                        <span>Produto</span>
                        <span style="text-align: center;">Preço</span>
                        <span style="text-align: center;">Qtd</span>
                        <span style="text-align: right;">Subtotal</span>
                        <span></span>
                    </div>

                    <?php foreach ($carrinho_itens as $item): 
                        $img_src = $item['imagem'] ? (BASE_URL . '/uploads/produtos/' . htmlspecialchars($item['imagem'])) : (BASE_URL . '/img/placeholder.png');
                    ?>
                        <div class="cart-item-row" style="display: grid; grid-template-columns: 3fr 1fr 1fr 1fr auto; align-items: center; border-bottom: 1px solid #222; padding: 20px 0;">
                            
                            <div style="display: flex; align-items: center; gap: 15px;">
                                <img src="<?php echo $img_src; ?>" alt="<?php echo htmlspecialchars($item['nome']); ?>" style="width: 60px; height: 60px; object-fit: cover; border-radius: 4px;">
                                <div>
                                    <a href="produto-detalhe.php?id=<?php echo $item['id']; ?>" style="color: #fff; font-weight: 500; text-decoration: none;">
                                        <?php echo htmlspecialchars($item['nome']); ?>
                                    </a>
                                </div>
                            </div>

                            <div style="text-align: center; color: #ccc;">
                                R$ <?php echo number_format($item['preco_unitario'], 2, ',', '.'); ?>
                            </div>

                            <div style="text-align: center;">
                                <form action="carrinho_acoes.php" method="POST">
                                    <input type="hidden" name="acao" value="atualizar">
                                    <input type="hidden" name="produto_id" value="<?php echo $item['id']; ?>">
                                    <input type="number" name="quantidade" value="<?php echo $item['quantidade']; ?>" min="1" max="<?php echo $item['estoque_max']; ?>" 
                                           style="width: 50px; padding: 5px; background: #111; border: 1px solid #444; color: #fff; text-align: center; border-radius: 4px;"
                                           onchange="this.form.submit()">
                                </form>
                            </div>

                            <div style="text-align: right; color: #bb9a65; font-weight: bold;">
                                R$ <?php echo number_format($item['subtotal'], 2, ',', '.'); ?>
                            </div>
                            <form action="carrinho_acoes.php" method="POST" class="ajax-form" data-type="carrinho">
                                <div style="text-align: right; padding-left: 15px;">
                                    <a href="carrinho_acoes.php?acao=remover&id=<?php echo $item['id']; ?>" 
                                    style="color: #e64c4c; font-size: 1.2rem; text-decoration: none;">
                                        &times;
                                    </a>
                                </div>
                            </form>
                        </div>
                    <?php endforeach; ?>
                    <form action="carrinho_acoes.php" method="POST" class="ajax-form" data-type="carrinho">
                        <div style="margin-top: 20px; text-align: right;">
                            <a href="carrinho_acoes.php?acao=limpar" style="color: #888; text-decoration: underline; font-size: 0.9rem;">Esvaziar Carrinho</a>
                        </div>
                    </form>
                </div>

                <div class="cart-summary" style="background: #1a1a1a; padding: 25px; border-radius: 8px; height: fit-content; border: 1px solid #333;">
                    <h3 style="margin-bottom: 20px; border-bottom: 1px solid #333; padding-bottom: 10px;">Resumo</h3>
                    
                    <div style="display: flex; justify-content: space-between; margin-bottom: 15px; color: #ccc;">
                        <span>Subtotal</span>
                        <span>R$ <?php echo number_format($valor_total_carrinho, 2, ',', '.'); ?></span>
                    </div>

                    <div style="display: flex; justify-content: space-between; margin-bottom: 20px; font-size: 1.2rem; font-weight: bold; color: #fff;">
                        <span>Total</span>
                        <span style="color: #bb9a65;">R$ <?php echo number_format($valor_total_carrinho, 2, ',', '.'); ?></span>
                    </div>

                    <a href="checkout.php" class="cta-button" id="cta-button-pag" style="display: block; text-align: center; width: 100%;">Finalizar Compra</a>
                    <a href="produtos.php" style="display: block; text-align: center; margin-top: 15px; color: #888; text-decoration: none;">Continuar Comprando</a>
                </div>

            </div>
        <?php endif; ?>
    </div>
</main>



<?php require 'templates/footer.php'; ?>