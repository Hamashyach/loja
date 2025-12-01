<?php
require_once 'auth_check.php';
require_once '../bd/config.php';

try {
    // Busca categorias ativas
    $categorias_stmt = $pdo->query("SELECT nome, slug FROM tb_categorias WHERE ativo = 1 ORDER BY nome");
    $link_categorias = $categorias_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Busca marcas ativas
    $marcas_stmt = $pdo->query("SELECT nome, slug FROM tb_marcas WHERE ativo = 1 ORDER BY nome");
    $link_marcas = $marcas_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $link_categorias = []; 
    $link_marcas = [];
}

// --- Links---

$link_options_html = '<option value="">-- Selecionar um link rápido --</option>';
$link_options_html .= '<option value="produtos.php">Ver Todos os Produtos</option>';

// Adiciona as categorias 
$link_options_html .= '<optgroup label="Categorias">';
foreach ($link_categorias as $cat) {
    $link_options_html .= '<option value="produtos.php?categoria=' . htmlspecialchars($cat['slug']) . '">Categoria: ' . htmlspecialchars($cat['nome']) . '</option>';
}
$link_options_html .= '</optgroup>';

// Adiciona as marcas 
$link_options_html .= '<optgroup label="Marcas">';
foreach ($link_marcas as $marca) {
    $link_options_html .= '<option value="produtos.php?marca=' . htmlspecialchars($marca['slug']) . '">Marca: ' . htmlspecialchars($marca['nome']) . '</option>';
}
$link_options_html .= '</optgroup>';
$config = get_all_configs($pdo);

// banner inicial
$hero_imagem = $config['hero_imagem'] ?? 'img/banner3.jpg';
$hero_titulo = $config['hero_titulo'] ?? 'Autenticidade além do estilo.';
$modal_ativo = $config['modal_ativo'] ?? '0';

// Modal 
$modal_ativo = $config['modal_ativo'] ?? '0';
$modal_titulo = $config['modal_titulo'] ?? 'Receba Nossas Novidades'; 
$modal_subtitulo = $config['modal_subtitulo'] ?? 'Seja o primeiro a saber...'; 

// Grid 1
$grid1_imagem = $config['grid1_imagem'] ?? 'img/breezer1 (2).png';
$grid1_label = $config['grid1_label'] ?? 'Lion Company';
$grid1_titulo = $config['grid1_titulo'] ?? 'Breezer Style';
$grid1_texto = $config['grid1_texto'] ?? 'Uma celebração à herança do streetwear...';
$grid1_link = $config['grid1_link'] ?? 'produtos.php';

// Grid 2
$grid2_imagem = $config['grid2_imagem'] ?? 'img/breezer1 (1).png';
$grid2_label = $config['grid2_label'] ?? 'Lion Company';
$grid2_titulo = $config['grid2_titulo'] ?? 'Breezer Style';
$grid2_texto = $config['grid2_texto'] ?? 'Uma celebração à herança do streetwear...';
$grid2_link = $config['grid2_link'] ?? 'produtos.php';

//  Banner (Calçados)
$feature_banner_imagem = $config['feature_banner_imagem'] ?? 'img/tenis-removebg-preview.png';
$feature_banner_label = $config['feature_banner_label'] ?? 'Lion Company Footwear';
$feature_banner_titulo = $config['feature_banner_titulo'] ?? 'Design que Deixa Marcas';
$feature_banner_texto = $config['feature_banner_texto'] ?? 'Construídos para durar e desenhados para impressionar...';
$feature_banner_link = $config['feature_banner_link'] ?? 'produtos.php';

//  banner Perfume
$perfum_imagem = $config['perfum_imagem'] ?? 'img/perfume-fundo.png';
$perfum_label = $config['perfum_label'] ?? 'Perfumes';
$perfum_titulo = $config['perfum_titulo'] ?? 'Brand Collection';
$perfum_texto = $config['perfum_texto'] ?? 'A Brand Collection revela sua essência...';
$perfum_link = $config['perfum_link'] ?? 'produtos.php';


$admin_nome = $_SESSION['admin_nome'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações do Site - Painel Admin</title>
    <link rel="stylesheet" href="css/admin_style.css">
    <style>

        .btn-salvar {
            font-size: 1rem; font-weight: 600; padding: 0.8rem 1.5rem; color: #000;
            background-color: var(--color-accent, #bb9a65); border: none;
            border-radius: 6px; cursor: pointer; transition: background-color 0.2s;
        }
        .btn-salvar:hover { background-color: #a98a54; }
        
        /* Botão de Preview  */
        .btn-preview {
            font-size: 1rem; font-weight: 600; padding: 0.8rem 1.5rem; color: #000000ff;
            background-color: #c0c0c0ff; 
            border: none; border-radius: 6px; cursor: pointer;
            margin-left: 10px; text-decoration: none;
        }
        .btn-preview:hover { background-color: #535353ff; }

        .btn-cancelar {
            display: inline-block; padding: 0.6rem 1.5rem; background-color: #555;
            color: #fff; border: none; border-radius: 6px; text-decoration: none;
            margin-left: 10px; font-size: 1rem; font-weight: 600; line-height: 1.5;
        }
        .btn-cancelar:hover { background-color: #777; text-decoration: none; }

        .form-section {
            background-color: #1a1a1a; padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem; border: 1px solid #333;
        }
        .form-section h3 {
            font-size: 1.3rem; color: var(--color-accent, #bb9a65); border-bottom: 1px solid #444;
            padding-bottom: 10px; margin-bottom: 20px;
        }
        .imagem-preview { max-width: 300px; height: auto; margin-top: 10px; border: 1px solid #444; padding: 5px; background-color: #222; }
        
        /* Switch Toggle */
        .switch { position: relative; display: inline-block; width: 60px; height: 34px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #555; transition: .4s; border-radius: 34px; }
        .slider:before { position: absolute; content: ""; height: 26px; width: 26px; left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: #bb9a65; }
        input:checked + .slider:before { transform: translateX(26px); }

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
                <a href="categorias.php">Categorias</a>
                <a href="marcas.php">Marcas</a>
                 <a href="clientes.php">Clientes</a>
                 <a href="inscritos.php">Inscritos</a>
                <a href="config_site.php" class="active">Config. do Site</a> </nav>
        </aside>

        <div class="admin-main">
            <header class="admin-header">
                <div class="admin-user-info">
                    <span>Olá, <strong><?php echo htmlspecialchars($admin_nome); ?></strong></span>
                    <a href="logout.php">Sair</a>
                </div>
            </header>

            <main class="admin-content">
                <main class="admin-content">
                    <?php
                    if (isset($_GET['sucesso']) && $_GET['sucesso'] == '1') {
                        echo '<div class="admin-alert success" style="background-color: #2a4a34; color: #d1f0db; border: 1px solid #28a745; padding: 1rem; margin-bottom: 1.5rem; border-radius: 6px; font-weight: 500;">
                                Configurações salvas com sucesso!
                            </div>';
                    }
                    ?>

                <h1>Configurações do Site</h1>

                
                <form action="config_processa.php" method="POST" enctype="multipart/form-data" id="config-form">
                    
                    <div class="form-section">
                        <h3>Banner Principal (Hero Section)</h3>
                        <div class="form-group">
                            <label for="hero_imagem">Imagem de Fundo do Banner</label>
                            <input type="file" id="hero_imagem" name="hero_imagem" class="form-control" accept="image/*">
                            <p style="margin-top: 10px;">Imagem atual:</p>
                            <img src="../<?php echo htmlspecialchars($hero_imagem); ?>" alt="Banner atual" class="imagem-preview">
                        </div>
                        <div class="form-group">
                            <label for="hero_titulo">Título do Banner</label>
                            <input type="text" id="hero_titulo" name="hero_titulo" class="form-control" 
                                   value="<?php echo htmlspecialchars($hero_titulo); ?>">
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>Modal Promocional</h3>
                        <div class="form-group">
                            <label style="display: block; margin-bottom: 10px;">Ativar Modal Promocional?</label>
                            <label class="switch">
                                <input type="checkbox" name="modal_ativo" value="1" <?php echo ($modal_ativo == '1') ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                            <small style="color: #ccc; margin-left: 10px;"><?php echo ($modal_ativo == '1') ? 'Ativo' : 'Inativo'; ?></small>
                        </div>
                        
                        <div class="form-group">
                            <label for="modal_titulo">Título do Modal</label>
                            <input type="text" id="modal_titulo" name="modal_titulo" class="form-control" 
                                   value="<?php echo htmlspecialchars($modal_titulo); ?>">
                        </div>
                        <div class="form-group">
                            <label for="modal_subtitulo">Subtítulo/Texto do Modal</label>
                            <textarea id="modal_subtitulo" name="modal_subtitulo" class="form-control"><?php echo htmlspecialchars($modal_subtitulo); ?></textarea>
                        </div>
                    </div>

                    <div class="form-section">

                        <h3>Grid de Conteúdo (Item 1)</h3>
                        <div class="form-group">
                            <label for="grid1_imagem">Imagem de Fundo</label>
                            <input type="file" id="grid1_imagem" name="grid1_imagem" class="form-control" accept="image/*">
                            <img src="../<?php echo htmlspecialchars($grid1_imagem); ?>" alt="Preview" class="imagem-preview">
                        </div>
                        <div class="form-group">
                            <label for="grid1_label">Label</label>
                            <input type="text" id="grid1_label" name="grid1_label" class="form-control" value="<?php echo htmlspecialchars($grid1_label); ?>">
                        </div>
                        <div class="form-group">
                            <label for="grid1_titulo">Título</label>
                            <input type="text" id="grid1_titulo" name="grid1_titulo" class="form-control" value="<?php echo htmlspecialchars($grid1_titulo); ?>">
                        </div>
                        <div class="form-group">
                            <label for="grid1_texto">Texto</label>
                            <textarea id="grid1_texto" name="grid1_texto" class="form-control"><?php echo htmlspecialchars($grid1_texto); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="grid1_link_helper">Link Rápido (Assistente)</label>
                            <select id="grid1_link_helper" class="form-control link-helper" data-target="grid1_link">
                                <?php echo $link_options_html; // Nossa variável mágica ?>
                            </select>

                            <label for="grid1_link" style="margin-top: 10px;">Link do Botão (Será salvo)</label>
                            <input type="text" id="grid1_link" name="grid1_link" class="form-control" value="<?php echo htmlspecialchars($grid1_link); ?>">
                            <small style="color: #ccc;">Use o seletor acima para preencher este campo, ou digite um link manualmente.</small>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>Grid de Conteúdo (Item 2)</h3>
                        <div class="form-group">
                            <label for="grid2_imagem">Imagem de Fundo</label>
                            <input type="file" id="grid2_imagem" name="grid2_imagem" class="form-control" accept="image/*">
                            <img src="../<?php echo htmlspecialchars($grid2_imagem); ?>" alt="Preview" class="imagem-preview">
                        </div>
                        <div class="form-group">
                            <label for="grid2_label">Label</label>
                            <input type="text" id="grid2_label" name="grid2_label" class="form-control" value="<?php echo htmlspecialchars($grid2_label); ?>">
                        </div>
                        <div class="form-group">
                            <label for="grid2_titulo">Título</label>
                            <input type="text" id="grid2_titulo" name="grid2_titulo" class="form-control" value="<?php echo htmlspecialchars($grid2_titulo); ?>">
                        </div>
                        <div class="form-group">
                            <label for="grid2_texto">Texto</label>
                            <textarea id="grid2_texto" name="grid2_texto" class="form-control"><?php echo htmlspecialchars($grid2_texto); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="grid2_link_helper">Link Rápido (Assistente)</label>
                            <select id="grid2_link_helper" class="form-control link-helper" data-target="grid2_link">
                                <?php echo $link_options_html; // Nossa variável mágica ?>
                            </select>

                            <label for="grid2_link" style="margin-top: 10px;">Link do Botão (Será salvo)</label>
                            <input type="text" id="grid2_link" name="grid2_link" class="form-control" value="<?php echo htmlspecialchars($grid2_link); ?>">
                            <small style="color: #ccc;">Use o seletor acima para preencher este campo, ou digite um link manualmente.</small>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>Banner "Feature" (Calçados)</h3>
                        <div class="form-group">
                            <label for="feature_banner_imagem">Imagem do Produto (Tênis)</label>
                            <input type="file" id="feature_banner_imagem" name="feature_banner_imagem" class="form-control" accept="image/*">
                            <img src="../<?php echo htmlspecialchars($feature_banner_imagem); ?>" alt="Preview" class="imagem-preview">
                        </div>
                        <div class="form-group">
                            <label for="feature_banner_label">Label</label>
                            <input type="text" id="feature_banner_label" name="feature_banner_label" class="form-control" value="<?php echo htmlspecialchars($feature_banner_label); ?>">
                        </div>
                        <div class="form-group">
                            <label for="feature_banner_titulo">Título</label>
                            <input type="text" id="feature_banner_titulo" name="feature_banner_titulo" class="form-control" value="<?php echo htmlspecialchars($feature_banner_titulo); ?>">
                        </div>
                        <div class="form-group">
                            <label for="feature_banner_texto">Texto</label>
                            <textarea id="feature_banner_texto" name="feature_banner_texto" class="form-control"><?php echo htmlspecialchars($feature_banner_texto); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="feature_banner_link">Link Rápido (Assistente)</label>
                            <select id="feature_banner_link_helper" class="form-control link-helper" data-target="feature_banner_link">
                                <?php echo $link_options_html; // Nossa variável mágica ?>
                            </select>

                            <label for="feature_banner_link" style="margin-top: 10px;">Link do Botão (Será salvo)</label>
                            <input type="text" id="feature_banner_link" name="feature_banner_link" class="form-control" value="<?php echo htmlspecialchars($feature_banner_link); ?>">
                            <small style="color: #ccc;">Use o seletor acima para preencher este campo, ou digite um link manualmente.</small>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>Banner "Perfum" (Fundo Fixo)</h3>
                        <div class="form-group">
                            <label for="perfum_imagem">Imagem de Fundo</label>
                            <input type="file" id="perfum_imagem" name="perfum_imagem" class="form-control" accept="image/*">
                            <img src="../<?php echo htmlspecialchars($perfum_imagem); ?>" alt="Preview" class="imagem-preview">
                        </div>
                        <div class="form-group">
                            <label for="perfum_label">Label</label>
                            <input type="text" id="perfum_label" name="perfum_label" class="form-control" value="<?php echo htmlspecialchars($perfum_label); ?>">
                        </div>
                        <div class="form-group">
                            <label for="perfum_titulo">Título</label>
                            <input type="text" id="perfum_titulo" name="perfum_titulo" class="form-control" value="<?php echo htmlspecialchars($perfum_titulo); ?>">
                        </div>
                        <div class="form-group">
                            <label for="perfum_texto">Texto</label>
                            <textarea id="perfum_texto" name="perfum_texto" class="form-control"><?php echo htmlspecialchars($perfum_texto); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="perfum_link">Link Rápido (Assistente)</label>
                            <select id="perfum_link_helper" class="form-control link-helper" data-target="perfum_link">
                                <?php echo $link_options_html; // Nossa variável mágica ?>
                            </select>

                            <label for="perfum_link" style="margin-top: 10px;">Link do Botão (Será salvo)</label>
                            <input type="text" id="perfum_link" name="perfum_link" class="form-control" value="<?php echo htmlspecialchars($perfum_link); ?>">
                            <small style="color: #ccc;">Use o seletor acima para preencher este campo, ou digite um link manualmente.</small>
                        </div>
                    </div>

                    <hr style="border-color: #333; margin: 2rem 0;">

                    <button type="submit" class="btn-salvar">Salvar Configurações</button>
                    <button type="button" class="btn-preview" id="btn-preview">Visualizar Alterações</button>
                    <a href="#" class="btn-cancelar" id="link-cancelar-formulario">
                    Cancelar
                    </a>
                </form>
            </main>
        </div>
    </div>

    
    <div class="modal-overlay" id="modal-confirmacao">
        <div class="modal-container">
            <h3>Confirmação</h3>
            <p id="modal-mensagem">Tem certeza que deseja executar esta ação?</p>
            <div class="modal-buttons">
                <button class="modal-btn-cancel" id="modal-btn-cancelar">Cancelar</button>
                <a href="#" class="modal-btn-ok" id="modal-btn-ok">OK</a>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="modal-confirmacao"></div>
    <div class="modal-overlay" id="modal-informacao">
        <div class="modal-container">
            <h3 id="modal-info-titulo">Aviso</h3>
            <p id="modal-info-mensagem"></p>
            <div class="modal-buttons" style="justify-content: flex-end;">
                <button class="modal-btn-ok" id="modal-info-btn-ok">OK</button>
            </div>
        </div>
    </div>

    
    <script>
    document.getElementById('btn-preview').addEventListener('click', function() {
        const form = document.getElementById('config-form');
        
        // 1. Criamos um FormData para pegar todos os dados, INCLUINDO arquivos
        const formData = new FormData(form);
        
        // 2. Enviamos os dados para um script de "Preview" via AJAX (Fetch)
        fetch('config_preview_salvar.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .catch(error => {
            console.error('Erro no Fetch:', error);
            alert('Erro de script ao tentar visualizar.');
        });
    });

    // --- (INÍCIO) NOVO CÓDIGO DO "ASSISTENTE DE LINK" ---
    
        // Procura por todos os dropdowns com a classe 'link-helper'
        document.querySelectorAll('.link-helper').forEach(function(selectElement) {
            
            // Adiciona um evento 'change' (quando o usuário escolhe algo)
            selectElement.addEventListener('change', function() {
                // 1. Pega o ID do campo de texto que queremos preencher (ex: 'grid1_link')
                const targetInputId = this.getAttribute('data-target');
                const targetInput = document.getElementById(targetInputId);
                
                // 2. Se o campo de texto existir e o valor não for vazio...
                if (targetInput && this.value !== "") {
                    // 3. Copia o valor do dropdown para o campo de texto
                    targetInput.value = this.value;
                }
            });
        });
    </script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        
        // --- Elementos do Modal de CONFIRMAÇÃO ---
        const modalConfirm = document.getElementById('modal-confirmacao');
        const modalConfirmMensagem = document.getElementById('modal-mensagem');
        const modalConfirmBtnOk = document.getElementById('modal-btn-ok');
        const modalConfirmBtnCancelar = document.getElementById('modal-btn-cancelar');

        // --- Elementos do Modal de INFORMAÇÃO (NOVO) ---
        const modalInfo = document.getElementById('modal-informacao');
        const modalInfoTitulo = document.getElementById('modal-info-titulo');
        const modalInfoMensagem = document.getElementById('modal-info-mensagem');
        const modalInfoBtnOk = document.getElementById('modal-info-btn-ok');

        // --- Funções de Controle do Modal de CONFIRMAÇÃO ---
        function abrirModalConfirm(url, mensagem) {
            if (modalConfirm && modalConfirmMensagem && modalConfirmBtnOk) {
                modalConfirmMensagem.textContent = mensagem;
                modalConfirmBtnOk.href = url;
                modalConfirm.classList.add('is-open');
            }
        }
        function fecharModalConfirm() {
            if (modalConfirm) {
                modalConfirm.classList.remove('is-open');
            }
        }

        // --- Funções de Controle do Modal de INFORMAÇÃO (NOVO) ---
        function abrirModalInfo(mensagem, titulo = 'Aviso') {
            if (modalInfo && modalInfoTitulo && modalInfoMensagem) {
                modalInfoTitulo.textContent = titulo;
                modalInfoMensagem.textContent = mensagem;
                modalInfo.classList.add('is-open');
            }
        }
        function fecharModalInfo() {
            if (modalInfo) {
                modalInfo.classList.remove('is-open');
            }
        }

        // --- Eventos para Fechar os Modais ---
        if (modalConfirmBtnCancelar) {
            modalConfirmBtnCancelar.addEventListener('click', fecharModalConfirm);
        }
        if (modalConfirm) {
            modalConfirm.addEventListener('click', function(e) {
                if (e.target === modalConfirm) fecharModalConfirm();
            });
        }
        // (NOVO) Evento para fechar o modal de info
        if (modalInfoBtnOk) {
            modalInfoBtnOk.addEventListener('click', fecharModalInfo);
        }
        if (modalInfo) {
            modalInfo.addEventListener('click', function(e) {
                if (e.target === modalInfo) fecharModalInfo();
            });
        }

        // --- LÓGICA DO BOTÃO "VISUALIZAR" (ATUALIZADO) ---
        const btnPreview = document.getElementById('btn-preview');
        if (btnPreview) {
            btnPreview.addEventListener('click', function() {
                const form = document.getElementById('config-form');
                const formData = new FormData(form);
                
                fetch('config_preview_salvar.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const previewUrl = '../index.php?preview=true&t=' + new Date().getTime();
                        window.open(previewUrl, '_blank');
                        
                        // --- SUBSTITUIÇÃO DO ALERT ---
                        const msg = 'IMPORTANTE: As alterações só serão públicas depois que você clicar em "Salvar Configurações".';
                        abrirModalInfo(msg, 'Visualização Pronta');
                        // alert(msg); // <-- REMOVIDO
                        
                    } else {
                        // --- SUBSTITUIÇÃO DO ALERT ---
                        abrirModalInfo('Erro ao tentar salvar a visualização.');
                        // alert('Erro ao tentar salvar a visualização.'); // <-- REMOVIDO
                    }
                })
                .catch(error => {
                    console.error('Erro no Fetch:', error);
                    // --- SUBSTITUIÇÃO DO ALERT ---
                    abrirModalInfo('Erro de script ao tentar visualizar. Verifique o console.');
                    // alert('Erro de script ao tentar visualizar.'); // <-- REMOVIDO
                });
            });
        }

        // --- LÓGICA DO "ASSISTENTE DE LINK" ---
        document.querySelectorAll('.link-helper').forEach(function(selectElement) {
            selectElement.addEventListener('change', function() {
                const targetInputId = this.getAttribute('data-target');
                const targetInput = document.getElementById(targetInputId);
                
                if (targetInput && this.value !== "") {
                    targetInput.value = this.value;
                }
            });
        });

        // --- LÓGICA DO MODAL "CANCELAR FORMULÁRIO" ---
        const linkCancelarForm = document.getElementById('link-cancelar-formulario');
        if (linkCancelarForm) {
            linkCancelarForm.addEventListener('click', function(e) {
                e.preventDefault();
                const url = 'config_site.php';
                const mensagem = 'Tem certeza que deseja cancelar? Alterações não salvas serão perdidas.';
                abrirModalConfirm(url, mensagem); // Usa o modal de CONFIRMAÇÃO
            });
        }

    });
    </script>
</body>
</html>