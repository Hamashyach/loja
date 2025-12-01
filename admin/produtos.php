<?php

require_once 'auth_check.php';

require_once '../bd/config.php';

try {
    $stmt = $pdo->query("
        SELECT 
            p.id, p.nome, p.preco, p.estoque, p.ativo, p.imagem_principal,
            c.nome AS categoria_nome 
        FROM 
            tb_produtos p
        LEFT JOIN 
            tb_categorias c ON p.categoria_id = c.id
        ORDER BY 
            p.id DESC
    ");
    $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro ao buscar produtos: " . $e->getMessage());
}

$admin_nome = $_SESSION['admin_nome'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Produtos - Painel Admin</title>
    <link rel="stylesheet" href="css/admin_style.css">   
    <style>
        .admin-content h1 {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .btn-novo {
            font-size: 0.9rem;
            font-weight: 600;
            padding: 0.6rem 1.2rem;
            color: #000;
            background-color: var(--color-accent, #bb9a65); /* Cor de destaque */
            border-radius: 6px;
            text-decoration: none;
            transition: background-color 0.2s;
        }
        .btn-novo:hover {
            background-color: #a98a54;
            text-decoration: none;
        }
        .product-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 2rem;
            background-color: #1a1a1a;
        }
        .product-table th, .product-table td {
            border: 1px solid #333;
            padding: 0.75rem 1rem;
            text-align: left;
        }
        .product-table th {
            background-color: #252525;
            font-size: 0.9rem;
        }
        .product-table td {
            font-size: 0.95rem;
        }
        .product-table .produto-img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 4px;
        }
        .status-ativo { color: #28a745; font-weight: bold; }
        .status-inativo { color: #e64c4c; font-weight: bold; }
        .acoes a {
            color: #bb9a65;
            margin-right: 10px;
        }

        /* Adicionar dentro do <style> em /admin/produtos.php */
        .admin-alert {
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 6px;
            font-weight: 500;
            border: 1px solid;
        }
        .admin-alert.success {
            background-color: #2a4a34; /* Tom de verde escuro */
            color: #d1f0db;
            border-color: #28a745;
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
                <?php
                    // --- MENSAGEM DE SUCESSO ---
                    if (isset($_GET['sucesso'])) {
                        $msg = '';
                        if ($_GET['sucesso'] == '1') {
                            $msg = 'Produto salvo com sucesso!';
                        } elseif ($_GET['sucesso'] == '2') {
                            $msg = 'Produto inativado (movido para lixeira) com sucesso.';
                        }
                        
                        if ($msg) {
                            echo '<div class="admin-alert success">' . htmlspecialchars($msg) . '</div>';
                        }
                    }
                    // --- (FIM) BLOCO DE MENSAGEM DE SUCESSO ---
                    ?>
                <h1>
                    Gerenciar Produtos
                    <a href="produto_formulario.php" class="btn-novo">+ Adicionar Produto</a>
                </h1>
                
                <table class="product-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Imagem</th>
                            <th>Nome</th>
                            <th>Categoria</th>
                            <th>Preço</th>
                            <th>Estoque</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($produtos)): ?>
                            <tr>
                                <td colspan="8" style="text-align:center;">Nenhum produto cadastrado.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($produtos as $produto): ?>
                                <tr>
                                    <td><?php echo $produto['id']; ?></td>
                                    <td>
                                        <?php if (!empty($produto['imagem_principal'])): ?>
                                            <img src="../uploads/produtos/<?php echo htmlspecialchars($produto['imagem_principal']); ?>" alt="" class="produto-img">
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($produto['nome']); ?></td>
                                    <td><?php echo htmlspecialchars($produto['categoria_nome'] ?? 'N/A'); ?></td>
                                    <td>R$ <?php echo number_format($produto['preco'], 2, ',', '.'); ?></td>
                                    <td><?php 
                                            $estoque = (int)$produto['estoque'];
                                            if ($estoque == 0) {
                                                echo '<span style="color: red; font-weight: bold;">Esgotado</span>';
                                            } elseif ($estoque < 5) {
                                                echo '<span style="color: orange; font-weight: bold;">' . $estoque . ' (Baixo)</span>';
                                            } else {
                                                echo $estoque;
                                            }
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($produto['ativo']): ?>
                                            <span class="status-ativo">Ativo</span>
                                        <?php else: ?>
                                            <span class="status-inativo">Inativo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="acoes">
                                        <a href="produto_formulario.php?id=<?php echo $produto['id']; ?>">Editar</a>
                                        <a href="#" class="link-excluir" data-url="produto_excluir.php?id=<?php echo $produto['id']; ?>">Excluir</a>
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
            <p id="modal-mensagem">Tem certeza que deseja executar esta ação?</p>
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

        // Função para abrir o modal
        function abrirModal(url, mensagem) {
            modalMensagem.textContent = mensagem;
            modalBtnOk.href = url;
            modal.classList.add('is-open');
        }

        // Função para fechar o modal
        function fecharModal() {
            modal.classList.remove('is-open');
        }

        // Adiciona evento para TODOS os links de "Excluir"
        document.querySelectorAll('.link-excluir').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault(); // Impede o link de navegar
                const url = this.getAttribute('data-url');
                const mensagem = 'Tem certeza que deseja INATIVAR este produto? Ele será movido para a lixeira.';
                abrirModal(url, mensagem);
            });
        });

        // Eventos dos botões do modal
        modalBtnCancelar.addEventListener('click', fecharModal);
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                fecharModal(); // Fecha se clicar fora da caixa
            }
        });
    });
    </script>

    


</body>
</html>