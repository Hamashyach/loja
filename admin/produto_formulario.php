<?php
require_once 'auth_check.php';
require_once '../bd/config.php';

$produto_id = null;
$produto = [
    'nome' => '', 'descricao' => '', 'cuidados' => '', 'preco' => '', 'preco_promocional' => '',
    'sku' => '', 'estoque' => 0, 'categoria_id' => null, 'marca_id' => null,
    'imagem_principal' => null, 'ativo' => 1, 'em_destaque_kit' => 0
];
$titulo_pagina = "Adicionar Novo Produto";
$acao_formulario = "produto_processa.php?acao=novo";

// Variáveis para controle de visualização
$tem_variacoes = false; 
$estoque_unico = 0;
$variacoes_existentes = [];

// Drops
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

        // Busca Variações
        $stmt_var = $pdo->prepare("SELECT tamanho, estoque FROM tb_produto_variacoes WHERE produto_id = ?");
        $stmt_var->execute([$produto_id]);
        $variacoes_raw = $stmt_var->fetchAll(PDO::FETCH_ASSOC);

        // Analisa se é Tamanho Único ('U') ou Grade
        foreach ($variacoes_raw as $v) {
            if ($v['tamanho'] == 'U') {
                $estoque_unico = $v['estoque']; // É tamanho único
                $tem_variacoes = false;
            } else {
                $variacoes_existentes[$v['tamanho']] = ['estoque' => $v['estoque']];
                $tem_variacoes = true; // Tem grade (P, M, G...)
            }
        }
        // Se não tiver registros na tabela variações, assumimos único com estoque 0
        if (empty($variacoes_raw)) {
            $tem_variacoes = false;
        }

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $titulo_pagina; ?></title>
    <link rel="stylesheet" href="css/admin_style.css">
    <style>
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
        .form-group.full-width { grid-column: 1 / -1; }
        textarea.form-control { height: 150px; resize: vertical; }
        .stock-type-selector {
            display: flex; 
            gap: 30px; 
            margin-bottom: 20px; 
            padding: 15px;
            background: #222; 
            border-radius: 6px; 
            border: 1px solid #333;
        }
        .stock-type-label { cursor: pointer; display: flex; align-items: center; gap: 10px; color: #fff; }
        .stock-type-label input { accent-color: #bb9a65; width: 18px; height: 18px; margin-top: 10px; }
        
        /* Ocultar/Mostrar */
        .hidden { display: none; }

        /* Botões e Imagens (do seu CSS anterior) */
        .btn-salvar { padding: 0.8rem 1.5rem; background-color: #bb9a65; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; }
        .btn-cancelar { padding: 0.8rem 1.5rem; background-color: #555; color: #fff; border-radius: 6px; text-decoration: none; margin-left: 10px; }
        .imagem-preview { max-width: 100px; margin-top: 10px; border: 1px solid #444; }
        .galeria-preview-container { display: flex; gap: 10px; margin-top: 10px; flex-wrap: wrap; }
        .galeria-item { position: relative; width: 100px; height: 100px; }
        .galeria-item img { width: 100%; height: 100%; object-fit: cover; border-radius: 4px; }
        .btn-apagar-img { position: absolute; top: -5px; right: -5px; background: red; color: white; border-radius: 50%; width: 20px; height: 20px; text-align: center; line-height: 20px; cursor: pointer; text-decoration: none; font-size: 12px; }
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
                    
                    <div class="form-group">
                        <label>Nome do Produto</label>
                        <input type="text" name="nome" class="form-control" value="<?php echo htmlspecialchars($produto['nome']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Preço (R$)</label>
                        <input type="number" step="0.01" name="preco" class="form-control" value="<?php echo htmlspecialchars($produto['preco']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>SKU (Código Único)</label>
                        <input type="text" name="sku" class="form-control" value="<?php echo htmlspecialchars($produto['sku']); ?>" readonly style="background-color: #4b4b4bff; cursor: not-allowed;">
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

                    <div class="form-group full-width">
                        <label>Descrição</label>
                        <textarea name="descricao" class="form-control"><?php echo htmlspecialchars($produto['descricao']); ?></textarea>
                    </div>

                    <div class="form-group full-width">
                        <label>Detalhes e Cuidados</label>
                        <textarea name="cuidados" class="form-control"><?php echo htmlspecialchars($produto['cuidados']); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Preço Promocional</label>
                        <input type="number" step="0.01" name="preco_promocional" class="form-control" value="<?php echo htmlspecialchars($produto['preco_promocional'] ?? ''); ?>">
                    </div>

                    <div class="form-group full-width">
                        <hr style="border-color: var(--color-accent); margin: 2rem 0;">
                        <h3>Controle de Estoque</h3>
                        
                        <div class="stock-type-selector">
                            <label class="stock-type-label" style="">
                                <input type="radio" name="tipo_estoque" value="unico" id="radio-unico" 
                                    <?php echo (!$tem_variacoes) ? 'checked' : ''; ?> onclick="toggleEstoque('unico')">
                                Tamanho Único (Perfumes, Acessórios)
                            </label>
                            <label class="stock-type-label">
                                <input type="radio" name="tipo_estoque" value="grade" id="radio-grade" 
                                    <?php echo ($tem_variacoes) ? 'checked' : ''; ?> onclick="toggleEstoque('grade')">
                                Grade de Tamanhos (Roupas, Calçados)
                            </label>
                        </div>

                        <div id="box-estoque-unico" class="<?php echo ($tem_variacoes) ? 'hidden' : ''; ?>">
                            <div class="form-group">
                                <label>Quantidade em Estoque</label>
                                <input type="number" name="estoque_unico" class="form-control" value="<?php echo $estoque_unico; ?>" min="0" style="max-width: 200px;">
                            </div>
                        </div>

                        <div id="box-estoque-grade" class="<?php echo (!$tem_variacoes) ? 'hidden' : ''; ?>">
                            <small style="color: #ccc; margin-bottom: 10px; display: block;">Preencha apenas os tamanhos disponíveis.</small>
                            <div class="variation-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 10px;">
                                <?php 
                                $tamanhos_padrao = ['P', 'M', 'G', 'GG', 'XG', '36', '37', '38', '40', '42'];
                                foreach ($tamanhos_padrao as $tamanho): 
                                    $estoque_val = $variacoes_existentes[$tamanho]['estoque'] ?? 0;
                                ?>
                                    <div class="variation-item" style="background: #222; padding: 10px; border-radius: 4px; text-align: center;">
                                        <label style="color: var(--color-accent); font-weight: bold;"><?php echo $tamanho; ?></label>
                                        <input type="hidden" name="variacoes[<?php echo $tamanho; ?>][tamanho]" value="<?php echo $tamanho; ?>">
                                        <input type="number" name="variacoes[<?php echo $tamanho; ?>][estoque]" class="form-control" 
                                            value="<?php echo $estoque_val; ?>" min="0" style="margin-top: 5px; text-align: center;">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
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

                    <div class="form-group">
                        <label>Status</label>
                        <select name="ativo" class="form-control">
                            <option value="1" <?php echo ($produto['ativo'] == 1) ? 'selected' : ''; ?>>Ativo</option>
                            <option value="0" <?php echo ($produto['ativo'] == 0) ? 'selected' : ''; ?>>Inativo</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Destaque "Ideia de Kit"?</label>
                        <input type="checkbox" name="em_destaque_kit" value="1" <?php echo ($produto['em_destaque_kit'] == 1) ? 'checked' : ''; ?>>
                    </div>

                    <div class="form-group full-width">
                        <label>Imagem Principal</label>
                        <input type="file" name="imagem_principal" class="form-control" accept="image/*">
                        <?php if ($produto_id && !empty($produto['imagem_principal'])): ?>
                            <img src="../uploads/produtos/<?php echo htmlspecialchars($produto['imagem_principal']); ?>" class="imagem-preview">
                        <?php endif; ?>
                    </div>

                    <div class="form-group full-width">
                        <label>Galeria de Imagens</label>
                        <input type="file" name="galeria_imagens[]" class="form-control" accept="image/*" multiple>
                        <div class="galeria-preview-container">
                            <?php if (!empty($imagens_galeria)): ?>
                                <?php foreach ($imagens_galeria as $img): ?>
                                    <div class="galeria-item">
                                        <img src="../<?php echo htmlspecialchars($img['caminho_imagem']); ?>">
                                        <a href="produto_imagem_apagar.php?id=<?php echo $img['id']; ?>&produto_id=<?php echo $produto_id; ?>" class="btn-apagar-img" onclick="return confirm('Apagar imagem?');">&times;</a>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                </div> 
                <hr style="border-color: #333; margin: 2rem 0;">
                <button type="submit" class="btn-salvar">Salvar Produto</button>
                <a href="produtos.php" class="btn-cancelar" style="padding: 9px 15px;">Cancelar</a>
            </form>
        </main>
    </div>
</div>

<div class="modal-overlay" id="modal-feedback">
        <div class="modal-container">
            <h3 id="modal-titulo">Aviso</h3>
            <p id="modal-mensagem">Mensagem aqui.</p>
            <div class="modal-buttons" style="justify-content: center;">
                <button class="modal-btn-ok" id="modal-btn-ok">OK</button>
            </div>
        </div>
    </div>

<script>
    


</script>

<script>

    function toggleEstoque(tipo) {
    const boxUnico = document.getElementById('box-estoque-unico');
    const boxGrade = document.getElementById('box-estoque-grade');
    
    if (tipo === 'unico') {
        boxUnico.classList.remove('hidden');
        boxGrade.classList.add('hidden');
        // Limpa valores da grade para evitar envio acidental
        document.querySelectorAll('#box-estoque-grade input[type="number"]').forEach(input => input.value = 0);
    } else {
        boxUnico.classList.add('hidden');
        boxGrade.classList.remove('hidden');
        // Limpa valor único
        document.querySelector('input[name="estoque_unico"]').value = 0;
    }
}
    document.addEventListener('DOMContentLoaded', function() {

        // --- LÓGICA DO MODAL DE FEEDBACK ---
        const modalFeedback = document.getElementById('modal-feedback');
        const modalTitulo = document.getElementById('modal-titulo');
        const modalMensagem = document.getElementById('modal-mensagem');
        const modalBtnOk = document.getElementById('modal-btn-ok');

        function abrirModalFeedback(titulo, mensagem, tipo) {
            if (modalFeedback) {
                modalTitulo.textContent = titulo;
                modalMensagem.textContent = mensagem;
                
                if (tipo === 'sucesso') {
                    modalTitulo.style.color = '#28a745'; 
                } else {
                    modalTitulo.style.color = '#e64c4c'; 
                }

                modalFeedback.classList.add('is-open');
            }
        }

        if (modalBtnOk) {
            modalBtnOk.addEventListener('click', function() {
                modalFeedback.classList.remove('is-open');
                window.history.replaceState({}, document.title, window.location.pathname + window.location.search.split('?')[0] + "?id=" + "<?php echo $produto_id; ?>");
            });
        }
        const urlParams = new URLSearchParams(window.location.search);
        
        if (urlParams.has('sucesso')) {
            abrirModalFeedback('Sucesso!', 'Produto salvo com sucesso.', 'sucesso');
        }

        if (urlParams.has('erro')) {
            const erro = urlParams.get('erro');
            let msg = 'Ocorreu um erro ao salvar o produto.';
            
            if (erro === 'dados_invalidos') msg = 'Por favor, preencha todos os campos obrigatórios (Nome, Preço, SKU).';
            if (erro === 'erro_db') msg = 'Erro no banco de dados. Verifique se o SKU já existe.';
            if (erro === 'upload_imagem') msg = 'Erro ao fazer upload da imagem.';

            abrirModalFeedback('Erro', msg, 'erro');
        }
    });
    </script>

</body>
</html>