<?php
require_once 'auth_check.php';
require_once '../bd/config.php';

// --- Inicialização ---
$marca_id = null;
$marca = ['nome' => '', 'slug' => '', 'ativo' => 1];
$titulo_pagina = "Nova Marca";
$acao_formulario = "marca_processa.php?acao=novo";

// --- Modo de Edição ---
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $marca_id = (int)$_GET['id'];
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM tb_marcas WHERE id = ?");
        $stmt->execute([$marca_id]);
        $marca_encontrada = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($marca_encontrada) {
            $marca = $marca_encontrada;
            $titulo_pagina = "Editar Marca: " . htmlspecialchars($marca['nome']);
            $acao_formulario = "marca_processa.php?acao=editar&id=" . $marca_id;
        } else {
            header("Location: marcas.php?erro=nao_encontrada");
            exit;
        }
    } catch (PDOException $e) {
        die("Erro ao buscar marca: " . $e->getMessage());
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
                <a href="marcas.php" class="active">Marcas</a> 
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
                
                <form action="<?php echo $acao_formulario; ?>" method="POST">
                    
                    <div class="form-group">
                        <label for="nome">Nome da Marca</label>
                        <input type="text" id="nome" name="nome" class="form-control" 
                               value="<?php echo htmlspecialchars($marca['nome']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="slug">URL amigável</label>
                        <input type="text" id="slug" name="slug" class="form-control" 
                               value="<?php echo htmlspecialchars($marca['slug']); ?>">
                        <small style="color: #000000ff; font-size: 0.85em;">Deixe em branco para gerar automaticamente a partir do nome.</small>
                    </div>

                    <div class="form-group">
                        <label for="ativo">Status</label>
                        <select id="ativo" name="ativo" class="form-control">
                            <option value="1" <?php echo ($marca['ativo'] == 1) ? 'selected' : ''; ?>>Ativo</option>
                            <option value="0" <?php echo ($marca['ativo'] == 0) ? 'selected' : ''; ?>>Inativo</option>
                        </select>
                    </div>

                    <hr style="border-color: #333; margin: 2rem 0;">

                    <button type="submit" class="btn-salvar">Salvar Marca</button>
                    <a href="#" class="btn-cancelar" id="link-cancelar-formulario">Cancelar</a>
                </form>
            </main>
        </div>
    </div>

    <div class="modal-overlay" id="modal-confirmacao">
        <div class="modal-container">
            <h3>Confirmação</h3>
            <p id="modal-mensagem">Tem certeza?</p>
            <div class="modal-buttons">
                <button class="modal-btn-cancel" id="modal-btn-cancelar">Cancelar</button>
                <a href="#" class="modal-btn-ok" id="modal-btn-ok">OK</a>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('modal-confirmacao');
        const modalMensagem = document.getElementById('modal-mensagem');
        const modalBtnOk = document.getElementById('modal-btn-ok');
        const modalBtnCancelar = document.getElementById('modal-btn-cancelar');

        function abrirModal(url, mensagem) {
            modalMensagem.textContent = mensagem;
            modalBtnOk.href = url;
            modal.classList.add('is-open');
        }
        function fecharModal() {
            modal.classList.remove('is-open');
        }
        
        modalBtnCancelar.addEventListener('click', fecharModal);
        modal.addEventListener('click', function(e) {
            if (e.target === modal) fecharModal();
        });

        // Evento para o link de "Cancelar" do formulário
        const linkCancelarForm = document.getElementById('link-cancelar-formulario');
        if (linkCancelarForm) {
            linkCancelarForm.addEventListener('click', function(e) {
                e.preventDefault();
                const url = 'marcas.php'; // Destino
                const mensagem = 'Tem certeza que deseja cancelar? Alterações não salvas serão perdidas.';
                abrirModal(url, mensagem);
            });
        }
    });
    </script>
</body>
</html>