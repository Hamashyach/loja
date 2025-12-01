<?php
require 'bd/config.php';
require 'templates/header.php';

$produto_id = (int)($_GET['id'] ?? 0);
$produto = null;

if ($produto_id > 0) {
    try {
        // Busca o produto principal
        $sql = "SELECT p.*, c.nome AS categoria_nome, c.slug AS categoria_slug, m.nome AS marca_nome
                FROM tb_produtos p
                LEFT JOIN tb_categorias c ON p.categoria_id = c.id
                LEFT JOIN tb_marcas m ON p.marca_id = m.id
                WHERE p.id = ? AND p.ativo = 1";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$produto_id]);
        $produto = $stmt->fetch(PDO::FETCH_ASSOC);

    } catch (PDOException $e) { }
}

if (!$produto) {
    header("Location: produtos.php?erro=produto_nao_encontrado");
    exit; 
}

// estoque

$variacoes = [];
$tamanho_unico = false;
$estoque_atual_display = 0;

try {
    $stmt_var = $pdo->prepare("SELECT tamanho, estoque FROM tb_produto_variacoes WHERE produto_id = ? AND estoque > 0 ORDER BY tamanho");
    $stmt_var->execute([$produto_id]);
    $variacoes = $stmt_var->fetchAll(PDO::FETCH_ASSOC);

    // Verifica se é tamanho único 
    if (count($variacoes) == 1 && $variacoes[0]['tamanho'] == 'U') {
        $tamanho_unico = true;
        $estoque_atual_display = $variacoes[0]['estoque'];
    } elseif (empty($variacoes)) {
        $tamanho_unico = true;
        $estoque_atual_display = $produto['estoque'];
    } else {
        $tamanho_unico = false;
        $ordem = ['P', 'M', 'G', 'GG', 'XG'];
        usort($variacoes, function($a, $b) use ($ordem) {
            $posA = array_search($a['tamanho'], $ordem);
            $posB = array_search($b['tamanho'], $ordem);
            if ($posA === false && $posB === false) return strnatcmp($a['tamanho'], $b['tamanho']); 
            if ($posA === false) return 1;
            if ($posB === false) return -1;
            return $posA - $posB;
        });
    }
} catch (Exception $e) {}


// --- BUSCAR IMAGENS DA GALERIA ---
$imagens_galeria = [];
try {
    $stmt_galeria = $pdo->prepare("SELECT caminho_imagem FROM tb_produto_imagens WHERE produto_id = ? ORDER BY ordem");
    $stmt_galeria->execute([$produto_id]);
    $imagens_galeria = $stmt_galeria->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { /* ignora */ }

// --- 4. BUSCAR PRODUTOS RELACIONADOS ---
$produtos_relacionados = [];
if ($produto['categoria_id']) {
    try {
        $sql_rel = "SELECT id, nome, preco, imagem_principal 
                    FROM tb_produtos 
                    WHERE categoria_id = ? AND id != ? AND ativo = 1 
                    ORDER BY RAND() 
                    LIMIT 4";
        $stmt_rel = $pdo->prepare($sql_rel);
        $stmt_rel->execute([$produto['categoria_id'], $produto_id]);
        $produtos_relacionados = $stmt_rel->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { /* ignora */ }
}
$is_favorito = false;
if (isset($_SESSION['cliente_logado']) && $_SESSION['cliente_logado'] === true) {
    try {
        $stmt_fav = $pdo->prepare("SELECT id FROM tb_client_favorites WHERE cliente_id = ? AND produto_id = ?");
        $stmt_fav->execute([(int)$_SESSION['cliente_id'], $produto_id]);
        if ($stmt_fav->fetch()) {
            $is_favorito = true;
        }
    } catch (PDOException $e) { /* ignora */ }
}

// Define a imagem principal
$img_principal = $produto['imagem_principal'] ? (BASE_URL . '/uploads/produtos/' . htmlspecialchars($produto['imagem_principal'])) : (BASE_URL . '/img/placeholder.png');
?>
    <main>
        <div class="container pdp-grid">
            
            <div class="product-gallery">
                <div class="main-image-container">
                    <img src="<?php echo $img_principal; ?>" alt="<?php echo htmlspecialchars($produto['nome']); ?>" id="main-product-image">
                </div>
                <div class="thumbnail-container">
                    
                    <img src="<?php echo $img_principal; ?>" alt="Thumbnail 1" class="thumbnail active">
                    
                    <?php foreach ($imagens_galeria as $img): 
                        $img_galeria_path = BASE_URL . '/' . htmlspecialchars($img['caminho_imagem']);
                    ?>
                        <img src="<?php echo $img_galeria_path; ?>" alt="Thumbnail da galeria" class="thumbnail">
                    <?php endforeach; ?>
                    
                </div>
            </div>
            <div class="product-details">
                <nav class="nav-product">
                    <div class="nav">
                        <a href="<?php echo BASE_URL; ?>/index.php">Home</a> ♦ 
                        <a href="<?php echo BASE_URL; ?>/produtos.php">Produtos</a> ♦ 
                        <?php if (!empty($produto['categoria_nome'])): ?>
                            <a href="<?php echo BASE_URL; ?>/produtos.php?categoria=<?php echo htmlspecialchars($produto['categoria_slug']); ?>"><?php echo htmlspecialchars($produto['categoria_nome']); ?></a> ♦ 
                        <?php endif; ?>
                        <span><?php echo htmlspecialchars($produto['nome']); ?></span>
                    </div>
                    <div class="icon-fav">
                        <form action="favorito_processa.php" method="POST" class="ajax-form" data-type="favorito" style="display: inline; margin: 0;">
                            <input type="hidden" name="produto_id" value="<?php echo $produto['id']; ?>">
                            <button type="submit" style="background: none; border: none; cursor: pointer;">
                                <img src="<?php echo BASE_URL; ?>/img/icones/favorito.png" 
                                     style="height: 30px; <?php echo $is_favorito ? 'filter: brightness(0.8) sepia(1) hue-rotate(-50deg) saturate(5);' : ''; ?>">
                            </button>
                        </form>
                    </div>
                </nav>

                <h1><?php echo htmlspecialchars($produto['nome']); ?></h1>
                
                <?php 
                if (!empty($produto['preco_promocional']) && $produto['preco_promocional'] < $produto['preco']): ?>
                    <p class="price" style="text-decoration: line-through; color: #888; font-size: 1rem;">
                        R$ <?php echo number_format($produto['preco'], 2, ',', '.'); ?>
                    </p>
                    <p class="price" style="color: var(--color-accent); font-size: 1.4rem; margin-top: -15px;">
                        R$ <?php echo number_format($produto['preco_promocional'], 2, ',', '.'); ?>
                </p>

                <?php else: ?>
                    <p class="price">R$ <?php echo number_format($produto['preco'], 2, ',', '.'); ?></p>
                <?php endif; ?>

                <p class="description"><?php echo nl2br(htmlspecialchars($produto['descricao'] ?? '')); ?></p>

                <form action="carrinho_acoes.php" method="POST" class="ajax-form" data-type="carrinho" id="form-compra">
                    <input type="hidden" name="acao" value="adicionar">
                    <input type="hidden" name="produto_id" value="<?php echo $produto['id']; ?>">
                    
                    <?php if (!$tamanho_unico): ?>
                        <div class="option-group">
                            <label>Tamanho:</label>
                            <div class="size-selector">
                                <?php foreach ($variacoes as $v): ?>
                                    <div class="size-option" 
                                         data-value="<?php echo htmlspecialchars($v['tamanho']); ?>"
                                         data-stock="<?php echo $v['estoque']; ?>"
                                         onclick="selectSize(this)">
                                        <?php echo htmlspecialchars($v['tamanho']); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" name="tamanho" id="selected-size" required>
                            <small id="size-error" style="color: #e64c4c; display: none;">Selecione um tamanho.</small>
                        </div>
                    <?php else: ?>
                        <input type="hidden" name="tamanho" value="U">
                    <?php endif; ?>
                    
                    <div class="option-group">
                        <label>Quantidade:</label>
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <div class="quantity-selector">
                                <button type="button" class="quantity-btn" onclick="updateQty(-1)">-</button>
                                <input type="number" id="quantity-input" name="quantidade" value="1" min="1" max="<?php echo $tamanho_unico ? $estoque_atual_display : 1; ?>" readonly>
                                <button type="button" class="quantity-btn" onclick="updateQty(1)">+</button>
                            </div>
                            
                            <small style="color: #888;" id="stock-display">
                                <?php 
                                if ($tamanho_unico) {
                                    echo $estoque_atual_display > 0 ? "($estoque_atual_display disponíveis)" : "(Esgotado)";
                                } else {
                                    echo "Selecione um tamanho";
                                }
                                ?>
                            </small>
                        </div>
                    </div>
                    <?php 
                        $btn_disabled = ($tamanho_unico && $estoque_atual_display <= 0) ? 'disabled style="opacity: 0.5; cursor: not-allowed;"' : '';
                        $btn_text = ($tamanho_unico && $estoque_atual_display <= 0) ? 'Esgotado' : 'Adicionar ao Carrinho';               
                    ?>
                    
                    <button type="submit" class="add-to-cart-btn" id="btn-compra" <?php echo $btn_disabled; ?>>
                        <?php echo $btn_text; ?>
                    </button>
                </form>
                <div class="product-accordion">
                    <div class="accordion-item">
                        <h4 class="accordion-header" onclick="toggleAccordion(this)">
                            Descrição <span>+</span>
                        </h4>
                        <div class="accordion-content">
                            <p><?php echo nl2br(htmlspecialchars($produto['descricao'] ?? '')); ?></p>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h4 class="accordion-header" onclick="toggleAccordion(this)">
                            Detalhes e Cuidados <span>+</span>
                        </h4>
                        <div class="accordion-content">
                            <p><?php echo nl2br(htmlspecialchars($produto['cuidados'] ?? '')); ?></p>
                        </div>
                    </div>
                </div>
                
            </div>

           
        </div>

        <section class="highlights-section container" style="margin-top: 60px; margin-bottom: 40px;">
            <?php if (!empty($produtos_relacionados)): ?>
                <h2 style="font-size: 1.5rem; margin-bottom: 20px; border-left: 4px solid #bb9a65; padding-left: 15px; color: #fff;">
                    Você também pode gostar
                </h2>

                <div class="product-grid">
                    <?php foreach ($produtos_relacionados as $rel): 
                        $link_rel = BASE_URL . '/produto-detalhe.php?id=' . $rel['id'];
                        $img_rel = $rel['imagem_principal'] ? (BASE_URL . '/uploads/produtos/' . htmlspecialchars($rel['imagem_principal'])) : (BASE_URL . '/img/placeholder.png');
                    ?>
                        <div class="product-card">
                            <a href="<?php echo $link_rel; ?>">
                                <div class="product-image-wrapper">
                                    <img src="<?php echo $img_rel; ?>" alt="<?php echo htmlspecialchars($rel['nome']); ?>">
                                    <span class="quick-shop-button">Ver Produto</span>
                                </div>
                            </a>
                            <div class="product-info">
                                <a href="<?php echo $link_rel; ?>">
                                    <h3><?php echo htmlspecialchars($rel['nome']); ?></h3>
                                </a>
                                <p class="price">R$ <?php echo number_format($rel['preco'], 2, ',', '.'); ?></p>
                                
                                <div class="product-actions">
                                    <form action="<?php echo BASE_URL; ?>/carrinho_acoes.php" method="POST" class="ajax-form" data-type="carrinho" style="display:inline;">
                                        <input type="hidden" name="acao" value="adicionar">
                                        <input type="hidden" name="produto_id" value="<?php echo $rel['id']; ?>">
                                        <input type="hidden" name="quantidade" value="1">
                                        <button type="submit" class="btn-action-icon" aria-label="Adicionar ao Carrinho">
                                            <img src="<?php echo BASE_URL; ?>/img/icones/carrinho.png" alt="Carrinho">
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <script>

    function selectSize(element) {
        document.querySelectorAll('.size-option').forEach(el => el.classList.remove('selected'));
        element.classList.add('selected');
        const tamanho = element.getAttribute('data-value');
        document.getElementById('selected-size').value = tamanho;
        const estoque = parseInt(element.getAttribute('data-stock'));
        const stockDisplay = document.getElementById('stock-display');
        const qtyInput = document.getElementById('quantity-input');
        const btnCompra = document.getElementById('btn-compra');

        if (estoque > 0) {
            stockDisplay.textContent = `(${estoque} disponíveis)`;
            stockDisplay.style.color = '#888';
            qtyInput.setAttribute('max', estoque);
            qtyInput.value = 1;
            btnCompra.disabled = false;
            btnCompra.textContent = "Adicionar ao Carrinho";
            btnCompra.style.opacity = "1";
            btnCompra.style.cursor = "pointer";
        } else {
            stockDisplay.textContent = "(Esgotado neste tamanho)";
            stockDisplay.style.color = "#e64c4c";
            qtyInput.setAttribute('max', 0);
            qtyInput.value = 0;
            btnCompra.disabled = true;
            btnCompra.textContent = "Esgotado";
            btnCompra.style.opacity = "0.5";
            btnCompra.style.cursor = "not-allowed";
        }
        document.getElementById('size-error').style.display = 'none';
    }
    function updateQty(change) {
        const input = document.getElementById('quantity-input');
        let val = parseInt(input.value);
        const max = parseInt(input.getAttribute('max')) || 1;
        
        let newVal = val + change;
        if (newVal >= 1 && newVal <= max) {
            input.value = newVal;
        }
    }
    </script>
<?php
require 'templates/footer.php';
?>