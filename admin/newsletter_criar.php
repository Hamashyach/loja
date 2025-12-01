<?php
require_once 'auth_check.php';

// Nome do admin para o header
$admin_nome = $_SESSION['admin_nome'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nova Campanha - Painel Admin</title>
    <link rel="stylesheet" href="css/admin_style.css">
    <style>
        /* Ajustes específicos para esta página, mantendo o padrão */
        textarea.form-control {
            min-height: 300px; /* Altura maior para escrever o e-mail */
            resize: vertical;
            font-family: sans-serif;
            line-height: 1.5;
        }

        .preview-container {
            margin-top: 10px;
            display: none; /* Escondido por padrão */
        }
        
        .preview-img {
            max-width: 100%;
            max-height: 300px;
            border: 1px solid #333;
            border-radius: 6px;
            padding: 5px;
            background: #1a1a1a;
        }

        .btn-cancelar {
            background-color: #333;
            color: #ccc;
            text-decoration: none;
            padding: 0.85rem;
            border-radius: 6px;
            display: inline-block;
            text-align: center;
            font-weight: 600;
            width: 100%;
            border: 1px solid #444;
            transition: background-color 0.2s;
            margin-top: 10px;
        }
        .btn-cancelar:hover {
            background-color: #444;
            text-decoration: none;
            color: #fff;
        }
        
        .form-actions {
            margin-top: 2rem;
            display: grid;
            gap: 10px;
        }
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
                <a href="inscritos.php" class="active">Inscritos / Newsletter</a>
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
                <h1>Nova Campanha de E-mail</h1>
                <p style="color: #ccc; margin-bottom: 20px;">Escreva sua mensagem para enviar a todos os inscritos da lista.</p>

                <form action="newsletter_disparar.php" method="POST" enctype="multipart/form-data">
                    
                    <div class="form-group">
                        <label for="assunto">Assunto do E-mail</label>
                        <input type="text" id="assunto" name="assunto" class="form-control" placeholder="Ex: Chegou a Nova Coleção Lion Company!" required>
                    </div>

                    <div class="form-group">
                        <label for="imagem_campanha">Imagem de Banner (Opcional)</label>
                        <input type="file" id="imagem_campanha" name="imagem_campanha" class="form-control" accept="image/*" onchange="previewImage(this)">
                        <small style="color: #888; display: block; margin-top: 5px;">A imagem será enviada no corpo do e-mail.</small>
                        
                        <div class="preview-container" id="preview-box">
                            <p style="color: #bb9a65; margin-bottom: 5px;">Pré-visualização:</p>
                            <img id="img-preview" class="preview-img">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="mensagem">Mensagem</label>
                        <textarea id="mensagem" name="mensagem" class="form-control" required placeholder="Olá! Confira as novidades..."></textarea>
                        <small style="color: #888; display: block; margin-top: 5px;">Você pode usar HTML básico se desejar.</small>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="admin-btn" onclick="return confirm('Tem certeza que deseja enviar este e-mail para TODOS os inscritos agora?')">
                            🚀 Disparar Campanha
                        </button>
                        
                        <a href="inscritos.php" class="btn-cancelar">Cancelar</a>
                    </div>

                </form>
            </main>
        </div>
    </div>

    <script>
        function previewImage(input) {
            const previewBox = document.getElementById('preview-box');
            const previewImg = document.getElementById('img-preview');

            if (input.files && input.files[0]) {
                const reader = new FileReader();

                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    previewBox.style.display = 'block';
                }

                reader.readAsDataURL(input.files[0]);
            } else {
                previewImg.src = '';
                previewBox.style.display = 'none';
            }
        }
    </script>

</body>
</html>