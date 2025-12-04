<?php
require_once 'auth_check.php';
require_once '../bd/config.php';

$produto_id = null;
// Valores padrão
$produto = [
    'nome' => '', 'descricao' => '', 'cuidados' => '', 'preco' => '', 'preco_promocional' => '',
    'sku' => '', 'estoque' => 0, 'categoria_id' => null, 'marca_id' => null,
    'imagem_principal' => null, 'ativo' => 1, 'em_destaque_kit' => 0,
    'peso_kg' => '0.300', 'altura_cm' => '5', 'largura_cm' => '20', 'comprimento_cm' => '20'
];

$titulo_pagina = "Adicionar Novo Produto";
$acao_formulario = "produto_processa.php?acao=novo";
$variacoes = [];

try {
    $categorias = $pdo->query("SELECT id, nome FROM tb_categorias ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
    $marcas = $pdo->query("SELECT id, nome FROM tb_marcas ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { die("Erro DB"); }

// Edição
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $produto_id = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM tb_produtos WHERE id = ?");
    $stmt->execute([$produto_id]);
    $produto_encontrado = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($produto_encontrado) {
        $produto = $produto_encontrado;
        $titulo_pagina = "Editar Produto: " . htmlspecialchars($produto['nome']);
        $acao_formulario = "produto_processa.php?acao=editar&id=" . $produto_id;

        // Busca Variações com Imagem
        $stmt_var = $pdo->prepare("SELECT * FROM tb_produto_variacoes WHERE produto_id = ? ORDER BY id ASC");
        $stmt_var->execute([$produto_id]);
        $variacoes = $stmt_var->fetchAll(PDO::FETCH_ASSOC);

        // Galeria
        $stmt_gal = $pdo->prepare("SELECT id, caminho_imagem FROM tb_produto_imagens WHERE produto_id = ?");
        $stmt_gal->execute([$produto_id]);
        $imagens_galeria = $stmt_gal->fetchAll(PDO::FETCH_ASSOC);
    }
}
$admin_nome = $_SESSION['admin_nome'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title><?php echo $titulo_pagina; ?></title>
    <link rel="stylesheet" href="css/admin_style.css">
    <style>
        .form-grid { 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 20px; 
        }

        .full-width { 
            grid-column: 1 / -1; 
        }

        textarea.form-control { 
            height: 120px; 
            resize: vertical; 
        }
        
        /* Variações */
        .variations-box { 
            background: #ffffffff; 
            padding: 20px; 
            border-radius: 8px; 
            border: 1px solid #cecbcbff; 
            margin-top: 10px; 
        }

        .variation-row { 
            display: grid; 
            grid-template-columns: 2fr 1fr 1fr 2fr auto; 
            gap: 10px; 
            margin-bottom: 15px; 
            align-items: center; 
            background: #ffffffff;
            padding: 10px;
            border-radius: 6px;
        }
        .btn-remove-var { background: #e74c3c; color: white; border: none; width: 30px; height: 30px; border-radius: 4px; cursor: pointer; display: flex; align-items: center; justify-content: center; }
        .var-img-preview { width: 40px; height: 40px; object-fit: cover; border-radius: 4px; border: 1px solid #555; margin-left: 10px; vertical-align: middle; }
        
        /* Galeria */
        .gallery-grid { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 10px; }
        .gallery-item { position: relative; width: 80px; height: 80px; }
        .gallery-item img { width: 100%; height: 100%; object-fit: cover; border-radius: 4px; border: 1px solid #444; }
        .btn-delete-img { position: absolute; top: -5px; right: -5px; background: red; color: white; border-radius: 50%; width: 20px; height: 20px; text-align: center; line-height: 20px; font-size: 12px; cursor: pointer; }
    </style>
</head>
<body>
<div class="admin-layout">
    <aside class="admin-sidebar">
        <div class="logo"><img src="../img/logo.png"></div>
        <nav class="admin-nav">
            <a href="dashboard.php">Dashboard</a>
            <a href="pedidos.php">Pedidos</a>
            <a href="produtos.php" class="active">Produtos</a> 
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
            <h1><?php echo $titulo_pagina; ?></h1>
            
            <form action="<?php echo $acao_formulario; ?>" method="POST" enctype="multipart/form-data">
                
                <div class="form-grid">
                    <div>
                        <div class="form-group">
                            <label>Nome do Produto</label>
                            <input type="text" name="nome" class="form-control" value="<?php echo htmlspecialchars($produto['nome']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Preço (R$)</label>
                            <input type="number" step="0.01" name="preco" class="form-control" value="<?php echo htmlspecialchars($produto['preco']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Preço Promo (Opcional)</label>
                            <input type="number" step="0.01" name="preco_promocional" class="form-control" value="<?php echo htmlspecialchars($produto['preco_promocional'] ?? ''); ?>">
                        </div>
                    </div>

                    <div>
                        <div class="form-group">
                            <label>SKU</label>
                            <input type="text" name="sku" class="form-control" value="<?php echo htmlspecialchars($produto['sku']); ?>" placeholder="Gerado auto se vazio">
                        </div>
                        <div class="form-group">
                            <label>Categoria</label>
                            <select name="categoria_id" class="form-control">
                                <option value="">Selecione...</option>
                                <?php foreach ($categorias as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo ($produto['categoria_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['nome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Marca</label>
                            <select name="marca_id" class="form-control">
                                <option value="">Selecione...</option>
                                <?php foreach ($marcas as $marca): ?>
                                    <option value="<?php echo $marca['id']; ?>" <?php echo ($produto['marca_id'] == $marca['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($marca['nome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="full-width">
                        <div class="form-group">
                            <label>Descrição</label>
                            <textarea name="descricao" class="form-control"><?php echo htmlspecialchars($produto['descricao']); ?></textarea>
                        </div>
                    </div>
                </div>

                <h3 style="margin-top: 30px; color: var(--text-main); border-bottom: 1px solid #333; padding-bottom: 10px;">Informações para o cálculo de frete</h3>
                <div class="form-grid" style="grid-template-columns: repeat(4, 1fr);">
                    <div class="form-group"><label>Peso (kg)</label><input type="number" step="0.001" name="peso_kg" class="form-control" value="<?php echo htmlspecialchars($produto['peso_kg']); ?>"></div>
                    <div class="form-group"><label>Altura (cm)</label><input type="number" name="altura_cm" class="form-control" value="<?php echo htmlspecialchars($produto['altura_cm']); ?>"></div>
                    <div class="form-group"><label>Largura (cm)</label><input type="number" name="largura_cm" class="form-control" value="<?php echo htmlspecialchars($produto['largura_cm']); ?>"></div>
                    <div class="form-group"><label>Comp. (cm)</label><input type="number" name="comprimento_cm" class="form-control" value="<?php echo htmlspecialchars($produto['comprimento_cm']); ?>"></div>
                </div>

                <h3 style="margin-top: 30px; color: var(----text-main); border-bottom: 1px solid #333; padding-bottom: 10px;">Variações (Cores e Tamanhos)</h3>
                <div class="variations-box">
                    <div id="variations-list">
                        <div style="display: grid; grid-template-columns: 2fr 1fr 1fr 2fr 40px; gap: 10px; color: var(--text-main); font-size: 0.95rem; margin-bottom: 10px; font-weight: 600;">
                            <span>Cor / Modelo</span>
                            <span>Tamanho</span>
                            <span>Estoque</span>
                            <span>Imagem da Variação</span>
                            <span></span>
                        </div>

                        <?php if (!empty($variacoes)): ?>
                            <?php foreach ($variacoes as $idx => $v): ?>
                                <div class="variation-row">
                                    <input type="text" name="variacoes[<?php echo $idx; ?>][cor]" class="form-control" value="<?php echo htmlspecialchars($v['cor_modelo']); ?>" required>
                                    <input type="text" name="variacoes[<?php echo $idx; ?>][tamanho]" class="form-control" value="<?php echo htmlspecialchars($v['tamanho']); ?>" required>
                                    <input type="number" name="variacoes[<?php echo $idx; ?>][estoque]" class="form-control" value="<?php echo $v['estoque']; ?>" min="0" required>
                                    
                                    <div style="display:flex; align-items:center;">
                                        <input type="file" name="variacoes[<?php echo $idx; ?>][imagem]" class="form-control" accept="image/*" style="font-size: 0.8rem;">
                                        <input type="hidden" name="variacoes[<?php echo $idx; ?>][imagem_antiga]" value="<?php echo htmlspecialchars($v['imagem'] ?? ''); ?>">
                                        <?php if (!empty($v['imagem'])): ?>
                                            <img src="../uploads/produtos/variacoes/<?php echo htmlspecialchars($v['imagem']); ?>" class="var-img-preview" title="Imagem Atual">
                                        <?php endif; ?>
                                    </div>

                                    <button type="button" class="btn-remove-var" onclick="this.parentElement.remove()">&times;</button>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="variation-row">
                                <input type="text" name="variacoes[0][cor]" class="form-control" placeholder="Ex: Azul" value="Padrão" required>
                                <input type="text" name="variacoes[0][tamanho]" class="form-control" placeholder="Ex: M" value="U" required>
                                <input type="number" name="variacoes[0][estoque]" class="form-control" placeholder="0" min="0" required>
                                <div style="display:flex; align-items:center;">
                                    <input type="file" name="variacoes[0][imagem]" class="form-control" accept="image/*" style="font-size: 0.8rem;">
                                </div>
                                <button type="button" class="btn-remove-var" onclick="this.parentElement.remove()">&times;</button>
                            </div>
                        <?php endif; ?>
                    </div>
                    <button type="button" class="btn-novo" style="margin-top: 10px;" onclick="addVariation()">+ Adicionar Variação</button>
                </div>

                <div class="form-grid" style="margin-top: 30px;">
                    <div class="full-width">
                        <label>Imagem Principal (Padrão)</label>
                        <input type="file" name="imagem_principal" class="form-control" accept="image/*">
                        <?php if ($produto_id && !empty($produto['imagem_principal'])): ?>
                            <img src="../uploads/produtos/<?php echo htmlspecialchars($produto['imagem_principal']); ?>" style="max-width: 80px; margin-top: 10px; border: 1px solid #444; border-radius: 4px;">
                        <?php endif; ?>
                    </div>
                    <div class="full-width">
                        <label>Galeria Geral</label>
                        <input type="file" name="galeria_imagens[]" class="form-control" accept="image/*" multiple>
                        <div class="gallery-grid">
                            <?php if (!empty($imagens_galeria)): ?>
                                <?php foreach ($imagens_galeria as $img): ?>
                                    <div class="gallery-item">
                                        <img src="../<?php echo htmlspecialchars($img['caminho_imagem']); ?>">
                                        <a href="produto_imagem_apagar.php?id=<?php echo $img['id']; ?>&produto_id=<?php echo $produto_id; ?>" class="btn-delete-img" onclick="return confirm('Apagar?');">&times;</a>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 20px;">
                    <label>Status</label>
                    <select name="ativo" class="form-control" style="width: auto;">
                        <option value="1" <?php echo ($produto['ativo'] == 1) ? 'selected' : ''; ?>>Ativo</option>
                        <option value="0" <?php echo ($produto['ativo'] == 0) ? 'selected' : ''; ?>>Inativo</option>
                    </select>
                </div>

                <hr style="border-color: #333; margin: 30px 0;">
                <div style="display: flex; gap: 15px;">
                    <button type="submit" class="btn-salvar">Salvar Produto</button>
                    <a href="produtos.php" class="btn-cancelar">Cancelar</a>
                </div>
            </form>
        </main>
    </div>
</div>

<script>
    let vCount = <?php echo empty($variacoes) ? 1 : count($variacoes); ?>;

    function addVariation() {
        const container = document.getElementById('variations-list');
        const div = document.createElement('div');
        div.className = 'variation-row';
        div.innerHTML = `
            <input type="text" name="variacoes[${vCount}][cor]" class="form-control" placeholder="Cor" required>
            <input type="text" name="variacoes[${vCount}][tamanho]" class="form-control" placeholder="Tam" required>
            <input type="number" name="variacoes[${vCount}][estoque]" class="form-control" placeholder="0" min="0" required>
            <input type="file" name="variacoes[${vCount}][imagem]" class="form-control" accept="image/*" style="font-size: 0.8rem;">
            <button type="button" class="btn-remove-var" onclick="this.parentElement.remove()">&times;</button>
        `;
        container.appendChild(div);
        vCount++;
    }
</script>
</body>
</html>