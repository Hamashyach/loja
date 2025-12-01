<?php
require_once 'auth_check.php';
require_once '../bd/config.php';

// --- Inicialização ---
$categoria_id = null;
// Define menu_principal como nulo por padrão
$categoria = ['nome' => '', 'slug' => '', 'ativo' => 1, 'menu_principal' => null, 'em_destaque' => 0, 'label_destaque' => '', 'imagem_destaque' => ''];
$titulo_pagina = "Nova Categoria";
$acao_formulario = "categoria_processa.php?acao=novo";

// --- Modo de Edição ---
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $categoria_id = (int)$_GET['id'];
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM tb_categorias WHERE id = ?");
        $stmt->execute([$categoria_id]);
        $categoria_encontrada = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($categoria_encontrada) {
            $categoria = $categoria_encontrada;
            $titulo_pagina = "Editar Categoria: " . htmlspecialchars($categoria['nome']);
            $acao_formulario = "categoria_processa.php?acao=editar&id=" . $categoria_id;
        } else {
            header("Location: categorias.php?erro=nao_encontrada");
            exit;
        }
    } catch (PDOException $e) {
        die("Erro ao buscar categoria: " . $e->getMessage());
    }
}

$admin_nome = $_SESSION['admin_nome'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $titulo_pagina; ?> - Painel Admin</title>
    <link rel="stylesheet" href="css/admin_style.css">
    <style>
        .form-group label small { display: block; color: #888; font-weight: normal; margin-top: 3px; }
        .btn-salvar { font-size: 1rem; font-weight: 600; padding: 0.8rem 1.5rem; color: #000; background-color: var(--color-accent, #bb9a65); border: none; border-radius: 6px; cursor: pointer; transition: background-color 0.2s; }
        .btn-salvar:hover { background-color: #a98a54; }
        .btn-cancelar { display: inline-block; padding: 0.8rem 1.5rem; background-color: #555; color: #fff; border: none; border-radius: 6px; text-decoration: none; margin-left: 10px; font-size: 1rem; font-weight: 600; }
        .btn-cancelar:hover { background-color: #777; text-decoration: none; }
        .imagem-preview { max-width: 200px; height: auto; margin-top: 10px; border: 1px solid #444; padding: 5px; background-color: #222; }
        /* Switch CSS */
        .switch { position: relative; display: inline-block; width: 50px; height: 26px; vertical-align: middle; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #555; transition: .4s; border-radius: 34px; }
        .slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: #bb9a65; }
        input:checked + .slider:before { transform: translateX(24px); }
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
                <a href="categorias.php" class="active">Categorias</a>
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
                    
                    <div class="form-group">
                        <label for="menu_principal">Onde esta categoria aparece no Menu?</label>
                        <select id="menu_principal" name="menu_principal" class="form-control">
                            <option value="">-- Selecione o Departamento --</option>
                            <option value="ROUPAS" <?php echo (strtoupper($categoria['menu_principal'] ?? '') == 'ROUPAS') ? 'selected' : ''; ?>>ROUPAS</option>
                            <option value="PERFUMES" <?php echo (strtoupper($categoria['menu_principal'] ?? '') == 'PERFUMES') ? 'selected' : ''; ?>>PERFUMES</option>
                            <option value="CALCADOS" <?php echo (strtoupper($categoria['menu_principal'] ?? '') == 'CALCADOS') ? 'selected' : ''; ?>>CALÇADOS</option>
                            <option value="ACESSORIOS" <?php echo (strtoupper($categoria['menu_principal'] ?? '') == 'ACESSORIOS') ? 'selected' : ''; ?>>ACESSÓRIOS</option>
                            </select>
                        <small>Ex: Se você criar "Camisetas", selecione "ROUPAS". Se criar "Tênis Nike", selecione "CALÇADOS".</small>
                    </div>

                    <div class="form-group">
                        <label for="nome">Nome da Categoria</label>
                        <input type="text" id="nome" name="nome" class="form-control" 
                               placeholder="Ex: Camisetas, Jaquetas, Brand Collection"
                               value="<?php echo htmlspecialchars($categoria['nome']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="slug">URL Amigável (Slug)</label>
                        <input type="text" id="slug" name="slug" class="form-control" 
                               value="<?php echo htmlspecialchars($categoria['slug']); ?>">
                        <small>Deixe em branco para gerar automaticamente (ex: nome "Camisetas" vira "camisetas").</small>
                    </div>

                    <div style="background: #222; padding: 15px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #333;">
                        <h3 style="margin-top: 0; font-size: 1.1rem; color: #bb9a65;">Configuração de Destaque (Home)</h3>
                        
                        <div class="form-group" style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
                            <label class="switch">
                                <input type="checkbox" name="em_destaque" value="1" <?php echo ($categoria['em_destaque'] ?? 0) == '1' ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                            <label style="margin:0; cursor:pointer;">Exibir esta categoria no carrossel da Página Inicial?</label>
                        </div>

                        <div class="form-group">
                            <label for="label_destaque">Título do Slide (Opcional)</label>
                            <input type="text" id="label_destaque" name="label_destaque" class="form-control" 
                                   placeholder="Ex: Lion Company Essencial"
                                   value="<?php echo htmlspecialchars($categoria['label_destaque'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="imagem_destaque">Imagem do Slide (Obrigatório se for destaque)</label>
                            <input type="file" id="imagem_destaque" name="imagem_destaque" class="form-control" accept="image/*">
                            <?php if ($categoria_id && !empty($categoria['imagem_destaque'])): ?>
                                <div style="margin-top: 5px;">
                                    <small>Imagem atual:</small><br>
                                    <img src="../<?php echo htmlspecialchars($categoria['imagem_destaque']); ?>" class="imagem-preview">
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="ativo">Status</label>
                        <select id="ativo" name="ativo" class="form-control">
                            <option value="1" <?php echo ($categoria['ativo'] == 1) ? 'selected' : ''; ?>>Ativo (Visível no site)</option>
                            <option value="0" <?php echo ($categoria['ativo'] == 0) ? 'selected' : ''; ?>>Inativo (Oculto)</option>
                        </select>
                    </div>

                    <hr style="border-color: #333; margin: 2rem 0;">

                    <button type="submit" class="btn-salvar">Salvar Categoria</button>
                    <a href="categorias.php" class="btn-cancelar">Cancelar</a>
                </form>
            </main>
        </div>
    </div>
    
    </body>
</html>