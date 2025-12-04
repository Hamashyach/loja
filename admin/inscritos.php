<?php
require_once 'auth_check.php';
require_once '../bd/config.php';

try {
    $sql = "SELECT id, email, data_inscricao, ativo FROM tb_inscritos ORDER BY data_inscricao DESC";
    $stmt = $pdo->query($sql);
    $inscritos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro ao buscar inscritos: " . $e->getMessage());
}

$admin_nome = $_SESSION['admin_nome'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Inscritos - Painel Admin</title>
    <link rel="stylesheet" href="css/admin_style.css">
    <style>
        /* Alerta de sucesso */
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
                <a href="marcas.php">Marcas</a>
                <a href="clientes.php">Clientes</a>
                <a href="inscritos.php" class="active">Inscritos</a> <a href="config_site.php">Config. do Site</a>
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
                if (isset($_GET['sucesso']) && $_GET['sucesso'] == 'status') {
                    echo '<div class="admin-alert success">Status do inscrito atualizado com sucesso!</div>';
                }
                ?>

                <h1>Gerenciar Inscritos (Página está em desenvolvimento)</h1>

                <!-- <a href="newsletter_enviar.php" class="btn-novo" 
                    style="background-color: #28a745; color: #fff; padding: 10px 15px;">
                    + Enviar Novidades
                    </a> -->
                
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Email</th>
                            <th>Data Inscrição</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($inscritos)): ?>
                            <tr>
                                <td colspan="5" style="text-align:center;">Ninguém se inscreveu ainda.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($inscritos as $inscrito): ?>
                                <tr>
                                    <td><?php echo $inscrito['id']; ?></td>
                                    <td><?php echo htmlspecialchars($inscrito['email']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($inscrito['data_inscricao'])); ?></td>
                                    <td>
                                        <?php if ($inscrito['ativo']): ?>
                                            <span class="status-ativo">Ativo</span>
                                        <?php else: ?>
                                            <span class="status-inativo">Desativado</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="acoes">
                                        <?php if ($inscrito['ativo']): ?>
                                            <a href="#" class="link-desativar link-excluir" 
                                               data-url="inscrito_status.php?id=<?php echo $inscrito['id']; ?>&acao=desativar">Desativar</a>
                                        <?php else: ?>
                                            <a href="inscrito_status.php?id=<?php echo $inscrito['id']; ?>&acao=ativar" class="link-ativar">Ativar</a>
                                        <?php endif; ?>
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
                const mensagem = 'Tem certeza que deseja DESATIVAR este inscrito? Ele não receberá mais newsletters.';
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