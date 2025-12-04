<?php
require_once 'auth_check.php';
require_once '../bd/config.php';

try {
    $sql = "SELECT id, cliente_nome, cliente_sobrenome, cliente_email, data_cadastro, ativo 
            FROM tb_client_users 
            ORDER BY data_cadastro DESC";

    $stmt = $pdo->query($sql);
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro ao buscar clientes: " . $e->getMessage());
}

$admin_nome = $_SESSION['admin_nome'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Clientes - Painel Admin</title>
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
                <a href="clientes.php" class="active">Clientes</a> 
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
                if (isset($_GET['sucesso']) && $_GET['sucesso'] == 'status') {
                    echo '<div class="admin-alert success">Status do cliente atualizado com sucesso!</div>';
                }
                ?>

                <h1>Gerenciar Clientes</h1>
                
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>Email</th>
                            <th>Data Cadastro</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($clientes)): ?>
                            <tr>
                                <td colspan="6" style="text-align:center;">Nenhum cliente cadastrado.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($clientes as $cliente): ?>
                                <tr>
                                    <td><?php echo $cliente['id']; ?></td>
                                    <td><?php echo htmlspecialchars($cliente['cliente_nome'] . ' ' . $cliente['cliente_sobrenome']); ?></td>
                                    <td><?php echo htmlspecialchars($cliente['cliente_email']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($cliente['data_cadastro'])); ?></td>
                                    <td>
                                        <?php if ($cliente['ativo']): ?>
                                            <span class="status-ativo">Ativo</span>
                                        <?php else: ?>
                                            <span class="status-inativo">Inativo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="acoes">
                                        <a href="cliente_detalhe.php?id=<?php echo $cliente['id']; ?>">Ver Detalhes</a>
                                        <?php if ($cliente['ativo']): ?>
                                            <a href="#" class="link-desativar link-excluir" 
                                               data-url="cliente_status.php?id=<?php echo $cliente['id']; ?>&acao=desativar" style="color: red;">Desativar</a>
                                        <?php else: ?>
                                            <a href="cliente_status.php?id=<?php echo $cliente['id']; ?>&acao=ativar" class="link-ativar">Ativar</a>
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
                const mensagem = 'Tem certeza que deseja DESATIVAR este cliente? Ele não poderá mais fazer login.';
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