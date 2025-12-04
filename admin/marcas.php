<?php
require_once 'auth_check.php';
require_once '../bd/config.php';

try {
    $stmt = $pdo->query("SELECT * FROM tb_marcas ORDER BY nome");
    $marcas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao buscar marcas: " . $e->getMessage());
}

$admin_nome = $_SESSION['admin_nome'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Marcas - Painel Admin</title>
    <link rel="stylesheet" href="css/admin_style.css">
    <style>
        .admin-content h1 { display: flex; justify-content: space-between; align-items: center; }
     
        .status-ativo { color: #28a745; font-weight: bold; }
        .status-inativo { color: #e64c4c; font-weight: bold; }
        .acoes a { color: #bb9a65; margin-right: 10px; }
        .admin-alert { padding: 1rem; margin-bottom: 1.5rem; border-radius: 6px; font-weight: 500; border: 1px solid; }
        .admin-alert.success { background-color: #2a4a34; color: #d1f0db; border-color: #28a745; }
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
                <?php
                if (isset($_GET['sucesso'])) {
                    $msg = $_GET['sucesso'] == '1' ? 'Marca salva com sucesso!' : 'Marca inativada com sucesso.';
                    echo '<div class="admin-alert success">' . htmlspecialchars($msg) . '</div>';
                }
                ?>
                <h1>
                    Gerenciar Marcas
                    <a href="marca_formulario.php" class="btn-novo">+ Nova Marca</a>
                </h1>
                
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>Slug</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($marcas)): ?>
                            <tr><td colspan="5" style="text-align:center;">Nenhuma marca cadastrada.</td></tr>
                        <?php else: ?>
                            <?php foreach ($marcas as $marca): ?>
                                <tr>
                                    <td><?php echo $marca['id']; ?></td>
                                    <td><?php echo htmlspecialchars($marca['nome']); ?></td>
                                    <td><?php echo htmlspecialchars($marca['slug']); ?></td>
                                    <td>
                                        <?php if ($marca['ativo']): ?>
                                            <span class="status-ativo">Ativo</span>
                                        <?php else: ?>
                                            <span class="status-inativo">Inativo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="acoes">
                                        <a href="marca_formulario.php?id=<?php echo $marca['id']; ?>" style="color: red;">Editar</a>
                                        <a href="#" class="link-excluir" 
                                           data-url="marca_excluir.php?id=<?php echo $marca['id']; ?>">Excluir</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
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
    // SCRIPT DO MODAL (REUTILIZADO)
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

        document.querySelectorAll('.link-excluir').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const url = this.getAttribute('data-url');
                const mensagem = 'Tem certeza que deseja INATIVAR esta marca?';
                abrirModal(url, mensagem);
            });
        });

        modalBtnCancelar.addEventListener('click', fecharModal);
        modal.addEventListener('click', function(e) {
            if (e.target === modal) fecharModal();
        });
    });
    </script>
</body>
</html>