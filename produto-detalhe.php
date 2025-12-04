<?php
require 'bd/config.php';
require 'templates/header.php';

$produto_id = (int)($_GET['id'] ?? 0);
$produto = null;

if ($produto_id > 0) {
    try {
        $sql = "SELECT p.*, c.nome AS categoria_nome, c.slug AS categoria_slug 
                FROM tb_produtos p
                LEFT JOIN tb_categorias c ON p.categoria_id = c.id
                WHERE p.id = ? AND p.ativo = 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$produto_id]);
        $produto = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { }
}

if (!$produto) {
    echo "<script>window.location='produtos.php';</script>";
    exit;
}

// BUSCAR VARIAÇÕES E AGRUPAR POR COR
$variacoes_db = [];
$cores_disponiveis = []; 
try {

    $stmt_var = $pdo->prepare("SELECT id, tamanho, estoque, cor_modelo, imagem FROM tb_produto_variacoes WHERE produto_id = ? AND estoque > 0 ORDER BY id ASC");
    $stmt_var->execute([$produto_id]);
    $variacoes_db = $stmt_var->fetchAll(PDO::FETCH_ASSOC);

    foreach ($variacoes_db as $v) {
        $cor = $v['cor_modelo'];
        if (!isset($cores_disponiveis[$cor])) {
            $cores_disponiveis[$cor] = [
                'imagem' => $v['imagem'], 
                'tamanhos' => []
            ];
        }
        $cores_disponiveis[$cor]['tamanhos'][] = $v;
    }
} catch (Exception $e) {}

// Imagem Principal
$img_principal = $produto['imagem_principal'] ? (BASE_URL . '/uploads/produtos/' . $produto['imagem_principal']) : (BASE_URL . '/img/placeholder.png');

// Galeria
$stmt_gal = $pdo->prepare("SELECT caminho_imagem FROM tb_produto_imagens WHERE produto_id = ? ORDER BY ordem");
$stmt_gal->execute([$produto_id]);
$imagens_galeria = $stmt_gal->fetchAll(PDO::FETCH_ASSOC);
?>

<main>
    <div class="container pdp-grid">
        <div class="product-gallery">
            <div class="main-image-container">
                <img src="<?php echo $img_principal; ?>" id="main-product-image" alt="Produto">
            </div>
            <div class="thumbnail-container">
                <img src="<?php echo $img_principal; ?>" class="thumbnail active" onclick="changeImage(this.src)">
                <?php foreach ($imagens_galeria as $img): ?>
                    <img src="<?php echo BASE_URL . '/' . $img['caminho_imagem']; ?>" class="thumbnail" onclick="changeImage(this.src)">
                <?php endforeach; ?>
            </div>
        </div>

        <div class="product-details">
            <h1><?php echo htmlspecialchars($produto['nome']); ?></h1>
            <p class="price">R$ <?php echo number_format($produto['preco'], 2, ',', '.'); ?></p>
            
            <form action="carrinho_acoes.php" method="POST" class="ajax-form" data-type="carrinho">
                <input type="hidden" name="acao" value="adicionar">
                <input type="hidden" name="produto_id" value="<?php echo $produto['id']; ?>">
                
                <?php if (count($cores_disponiveis) > 0): ?>
                    <div class="option-group">
                        <label>Cor / Modelo:</label>
                        <div class="color-selector" style="display:flex; gap:10px; flex-wrap:wrap;">
                            <?php 
                            $first = true; 
                            foreach ($cores_disponiveis as $nome_cor => $dados_cor): 
                                $img_troca = $dados_cor['imagem'] ? (BASE_URL . '/uploads/produtos/variacoes/' . $dados_cor['imagem']) : $img_principal;
                            ?>
                                <div class="color-option <?php echo $first ? 'selected' : ''; ?>" 
                                     data-color="<?php echo htmlspecialchars($nome_cor); ?>"
                                     data-image="<?php echo $img_troca; ?>"
                                     onclick="selectColor(this)"
                                     style="border:1px solid #555; padding:8px 15px; cursor:pointer; border-radius:4px;">
                                    <?php echo htmlspecialchars($nome_cor); ?>
                                </div>
                            <?php $first = false; endforeach; ?>
                        </div>
                        <input type="hidden" name="cor_selecionada" id="input-cor" required>
                    </div>

                    <div class="option-group">
                        <label>Tamanho:</label>
                        <div id="size-container" class="size-selector">
                            </div>
                        <input type="hidden" name="tamanho" id="input-tamanho" required>
                    </div>
                <?php else: ?>
                    <p style="color: #e64c4c;">Produto indisponível.</p>
                <?php endif; ?>

                <div class="option-group">
                    <label>Quantidade:</label>
                    <div class="quantity-selector">
                        <button type="button" class="quantity-btn" onclick="updateQty(-1)">-</button>
                        <input type="number" name="quantidade" id="qty_pdp" value="1" min="1" readonly>
                        <button type="button" class="quantity-btn" onclick="updateQty(1)">+</button>
                    </div>
                </div>

                <button type="submit" class="add-to-cart-btn" id="btn-compra">Adicionar ao Carrinho</button>
            </form>
            
            <div class="product-accordion" style="margin-top:40px;">
                <div class="accordion-item"><h4 class="accordion-header">Descrição</h4><div class="accordion-content"><p><?php echo nl2br($produto['descricao']); ?></p></div></div>
            </div>
        </div>
    </div>
</main>

<script>
    // Recupera o JSON gerado pelo PHP (certifique-se que o PHP está imprimindo isso antes)
    const variationsData = <?php echo json_encode($cores_disponiveis); ?>;

    /* --- LÓGICA DE COR (Gera os tamanhos dinamicamente) --- */
    function selectColor(element) {
        // 1. Visual da seleção de cor
        document.querySelectorAll('.color-option').forEach(el => el.classList.remove('selected'));
        element.classList.add('selected');

        // 2. Troca imagem principal se houver
        const newImage = element.getAttribute('data-image');
        if (newImage) {
            changeImage(newImage);
        }

        // 3. Atualiza Input Oculto de Cor
        const colorName = element.getAttribute('data-color');
        document.getElementById('input-cor').value = colorName;

        // 4. Renderiza os Tamanhos desta cor
        const sizesContainer = document.getElementById('size-container');
        sizesContainer.innerHTML = ''; // Limpa tamanhos anteriores

        // Reseta o estado do botão de compra e displays
        resetPurchaseState();

        // Pega os tamanhos da cor selecionada no JSON
        if (variationsData[colorName] && variationsData[colorName]['tamanhos']) {
            const sizes = variationsData[colorName]['tamanhos'];

            sizes.forEach(sizeObj => {
                const sizeDiv = document.createElement('div');
                sizeDiv.className = 'size-option';
                sizeDiv.textContent = sizeObj.tamanho;
                
                // --- AQUI ESTÁ O SEGREDO DA UNIÃO ---
                // Adicionamos os dados que a função selectSize precisa
                sizeDiv.setAttribute('data-value', sizeObj.tamanho);
                sizeDiv.setAttribute('data-stock', sizeObj.estoque);
                
                // Adicionamos o evento de clique
                sizeDiv.onclick = function() { selectSize(this); };

                sizesContainer.appendChild(sizeDiv);
            });
        }
    }

    /* --- LÓGICA DE TAMANHO E ESTOQUE --- */
    function selectSize(element) {
        // Visual da seleção
        document.querySelectorAll('.size-option').forEach(el => el.classList.remove('selected'));
        element.classList.add('selected');

        // Atualiza input oculto
        const tamanho = element.getAttribute('data-value');
        document.getElementById('input-tamanho').value = tamanho;

        // Lógica de Estoque
        const estoque = parseInt(element.getAttribute('data-stock'));
        const stockDisplay = document.getElementById('stock-display');
        const qtyInput = document.getElementById('qty_pdp'); // ID padronizado
        const btnCompra = document.getElementById('btn-compra');

        if (estoque > 0) {
            if(stockDisplay) {
                stockDisplay.textContent = `(${estoque} disponíveis)`;
                stockDisplay.style.color = '#888';
                stockDisplay.style.display = 'inline-block';
            }
            
            qtyInput.setAttribute('max', estoque);
            qtyInput.value = 1; // Reseta para 1 ao trocar tamanho
            
            btnCompra.disabled = false;
            btnCompra.textContent = "Adicionar ao Carrinho";
            btnCompra.style.opacity = "1";
            btnCompra.style.cursor = "pointer";
        } else {
            if(stockDisplay) {
                stockDisplay.textContent = "(Esgotado neste tamanho)";
                stockDisplay.style.color = "#e64c4c";
                stockDisplay.style.display = 'inline-block';
            }

            qtyInput.setAttribute('max', 0);
            qtyInput.value = 0;
            
            btnCompra.disabled = true;
            btnCompra.textContent = "Esgotado";
            btnCompra.style.opacity = "0.6";
            btnCompra.style.cursor = "not-allowed";
        }

        // Esconde erro se houver
        const sizeError = document.getElementById('size-error');
        if(sizeError) sizeError.style.display = 'none';
    }

    /* --- FUNÇÃO AUXILIAR PARA LIMPAR ESTADO --- */
    function resetPurchaseState() {
        document.getElementById('input-tamanho').value = '';
        const btnCompra = document.getElementById('btn-compra');
        const stockDisplay = document.getElementById('stock-display');
        
        btnCompra.disabled = true;
        btnCompra.textContent = "Selecione um tamanho";
        btnCompra.style.opacity = "0.6";
        
        if(stockDisplay) stockDisplay.style.display = 'none';
    }

    /* --- LÓGICA DE QUANTIDADE --- */
    function updateQty(change) {
        const input = document.getElementById('qty_pdp'); // ID padronizado
        let val = parseInt(input.value);
        // Se não tiver max definido, assume 1, senão usa o max do estoque
        const max = parseInt(input.getAttribute('max')) || 999; 
        
        if (max === 0) return; // Se estoque for 0, não faz nada

        let newVal = val + change;

        if (newVal >= 1 && newVal <= max) {
            input.value = newVal;
        }
    }

    /* --- UTILITÁRIOS (Imagens e Accordion) --- */
    function changeImage(src) {
        const mainImage = document.getElementById('main-product-image');
        if(mainImage) mainImage.src = src;
    }

    function toggleAccordion(header) {
        const content = header.nextElementSibling;
        const icon = header.querySelector('span');

        if (content.style.maxHeight) {
            content.style.maxHeight = null;
            if(icon) icon.textContent = "+";
            header.classList.remove('active');
        } else {
            content.style.maxHeight = content.scrollHeight + "px";
            if(icon) icon.textContent = "-";
            header.classList.add('active');
        }
    }

    /* --- INICIALIZAÇÃO --- */
    document.addEventListener('DOMContentLoaded', function(){
        // 1. Inicializa a primeira cor automaticamente
        const firstColor = document.querySelector('.color-option');
        if(firstColor) firstColor.click();

        // 2. Setup da Galeria de Imagens
        const thumbnails = document.querySelectorAll('.thumbnail');
        thumbnails.forEach(thumb => {
            thumb.addEventListener('click', function() {
                thumbnails.forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                changeImage(this.src);
            });
        });

        // 3. Validação do Formulário no Submit
        const formCompra = document.querySelector('form.ajax-form'); // Ou getElementById se tiver ID
        if (formCompra) {
            formCompra.addEventListener('submit', function(e) {
                const sizeInput = document.getElementById('input-tamanho');
                const sizeError = document.getElementById('size-error');

                if (!sizeInput || sizeInput.value === "") {
                    e.preventDefault();
                    if(sizeError) {
                        sizeError.style.display = 'block';
                        sizeError.textContent = "Por favor, selecione um tamanho.";
                    }
                    alert("Por favor, selecione um tamanho."); // Fallback
                }
            });
        }
    });
</script>

<style>
    .color-option.selected { border-color: #bb9a65 !important; background: rgba(187, 154, 101, 0.2); color: #bb9a65; }
    .size-option { padding: 10px 15px; border: 1px solid #555; cursor: pointer; margin-right: 5px; display: inline-block; }
    .size-option.selected { background: #bb9a65; color: #000; border-color: #bb9a65; }
</style>

<?php require 'templates/footer.php'; ?>