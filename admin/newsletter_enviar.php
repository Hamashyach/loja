<?php
require_once 'auth_check.php';
require_once '../bd/config.php';

// Contagem de inscritos para avisar o admin
try {
    $total_inscritos = $pdo->query("SELECT COUNT(id) FROM tb_inscritos WHERE ativo = 1")->fetchColumn();
} catch (PDOException $e) {
    $total_inscritos = 0;
}

$admin_nome = $_SESSION['admin_nome'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compor Newsletter - Admin</title>
    <link rel="stylesheet" href="css/admin_style.css">
    <style>
        .form-send { max-width: 800px; }
        .form-send textarea { height: 350px; resize: vertical; } /* Será substituído pelo TinyMCE */
        .btn-enviar {
            font-size: 1.1rem; font-weight: 600; padding: 1rem 2rem; color: #fff;
            background-color: #28a745; border: none;
            border-radius: 6px; cursor: pointer; transition: background-color 0.2s;
            margin-top: 20px;
        }
        .btn-enviar:hover { background-color: #1e7e34; }
        .alert-status { padding: 15px; background: #555; border-radius: 6px; color: #fff; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="admin-layout">
        <aside class="admin-sidebar">
           <div class="logo"><img src="../img/logo.png"></div>
            <nav class="admin-nav">
                <a href="inscritos.php">Clientes</a>
                <a href="inscritos.php" class="active">Inscritos</a>
                <a href="newsletter_enviar.php" class="active">Enviar Newsletter</a> <a href="config_site.php">Config. do Site</a>
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
                <h1>Enviar Nova Newsletter</h1>
                
                <div class="alert-status">
                    Você está prestes a enviar para **<?php echo $total_inscritos; ?>** assinantes ativos.
                </div>

                <form action="newsletter_enviar_processa.php" method="POST" class="form-send">
                    
                    <div class="form-group">
                        <label for="assunto">Assunto do E-mail</label>
                        <input type="text" id="assunto" name="assunto" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="corpo_email">Conteúdo da Novidade (Use o editor abaixo)</label>
                        <textarea id="corpo_email" name="corpo_email" class="form-control"></textarea> 
                    </div>

                    <button type="submit" class="btn-enviar" onclick="return confirm('Tem certeza que deseja ENVIAR esta Newsletter?');">Enviar Agora</button>
                    
                </form>
            </main>
        </div>
    </div>
</body>
</html>