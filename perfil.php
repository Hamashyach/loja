<?php
require 'templates/header.php'; 

if (!isset($_SESSION['cliente_logado']) || !isset($_SESSION['cliente_id'])) {
    header('Location: login.php?aviso=login_necessario');
    exit;
}

$cliente_id = (int)$_SESSION['cliente_id'];

// --- BUSCAR DADOS DO CLIENTE E SEUS PEDIDOS/ENDEREÇOS ---
try {
    // Buscar Dados Pessoais
    $stmt_cliente = $pdo->prepare("SELECT * FROM tb_client_users WHERE id = ?");
    $stmt_cliente->execute([$cliente_id]);
    $cliente = $stmt_cliente->fetch(PDO::FETCH_ASSOC);

    // Buscar Endereços
    $stmt_enderecos = $pdo->prepare("SELECT * FROM tb_client_addresses WHERE cliente_id = ?");
    $stmt_enderecos->execute([$cliente_id]);
    $enderecos = $stmt_enderecos->fetchAll(PDO::FETCH_ASSOC);

    // Buscar Histórico de Pedidos
    $stmt_pedidos = $pdo->prepare("
        SELECT id, data_pedido, valor_total, status_entrega 
        FROM tb_pedidos 
        WHERE cliente_id = ? 
        ORDER BY data_pedido DESC
    ");
    $stmt_pedidos->execute([$cliente_id]);
    $pedidos = $stmt_pedidos->fetchAll(PDO::FETCH_ASSOC);

    // --- Buscar Favoritos ---
    $favoritos = []; 
    try {
        $stmt_favoritos = $pdo->prepare("
            SELECT 
                p.id, p.nome, p.preco, p.preco_promocional, p.imagem_principal
            FROM 
                tb_client_favorites f
            JOIN 
                tb_produtos p ON f.produto_id = p.id
            WHERE 
                f.cliente_id = ? AND p.ativo = 1
        ");
        $stmt_favoritos->execute([$cliente_id]);
        $favoritos = $stmt_favoritos->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
         echo("Erro ao buscar favoritos: " . $e->getMessage());
    }
 

} catch (PDOException $e) {
    die("Erro ao carregar dados do perfil: " . $e->getMessage());
}

if (!$cliente) {
    session_destroy();
    header('Location: login.php?aviso=erro_sessao');
    exit;
}

?>
    <main>
        <div class="container">
            <div class="page-header">
                <h1>Minha Conta</h1>

                <p style="color: #ccc; margin-top: -30px;">Olá, <?php echo htmlspecialchars($cliente['cliente_nome']); ?>!</p>
            </div>

            <div class="account-layout">
                <aside class="account-nav">
                    <ul>
                        <li><a href="#pedidos-panel" class="active">Meus Pedidos</a></li>
                        <li><a href="#favoritos-panel">Favoritos</a></li>
                        <li><a href="#dados-panel">Meus Dados</a></li>
                        <li><a href="#enderecos-panel">Endereços</a></li>
                    </ul>
                </aside>

                <div class="account-content">
                    
                    <div id="pedidos-panel" class="content-panel active">
                        <h2>Histórico de Pedidos</h2>
                        
                        <?php if (empty($pedidos)): ?>
                            <p style="color: #888;">Você ainda não fez nenhum pedido.</p>
                        <?php else: ?>
                            <?php foreach ($pedidos as $pedido): ?>
                                <div class="order-item">
                                    <div class="order-header">
                                        <div>
                                            <strong>Pedido #<?php echo $pedido['id']; ?></strong>
                                            <span> - <?php echo date('d/m/Y H:i', strtotime($pedido['data_pedido'])); ?></span>
                                        </div>
                                        <?php
                                            $status_class = 'delivered'; 
                                            if ($pedido['status_entrega'] == 'Enviado') $status_class = 'shipped';
                                            if ($pedido['status_entrega'] == 'Nao Enviado') $status_class = 'pending';
                                        ?>
                                        <span class="order-status <?php echo $status_class; ?>">
                                            <?php echo htmlspecialchars($pedido['status_entrega']); ?>
                                        </span>
                                    </div>
                                    <div class="order-body">
                                        <p>Total de R$ <?php echo number_format($pedido['valor_total'], 2, ',', '.'); ?></p>
                                    </div>
                                    <div class="order-footer">
                                        <span>Total: R$ <?php echo number_format($pedido['valor_total'], 2, ',', '.'); ?></span>
                                        <a href="pedido_detalhe_cliente.php?id=<?php echo $pedido['id']; ?>" class="details-btn">Ver Detalhes</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                    </div>
                    <div id="favoritos-panel" class="content-panel">
                        <h2>Meus Favoritos</h2>
                        <?php if (empty($favoritos)): ?>
                            <p style="color: #888;">Você não tem produtos favoritos. Navegue na loja e adicione alguns!</p>
                        <?php else: ?>
                            <div class="favoritos-list">
                                <?php foreach ($favoritos as $fav): ?>
                                    <div class="produto-item">
                                        <a href="produto-detalhe.php?id=<?php echo $fav['id']; ?>">
                                            <img src="uploads/produtos/<?php echo htmlspecialchars($fav['imagem_principal']); ?>" alt="<?php echo htmlspecialchars($fav['nome']); ?>">
                                            <h4><?php echo htmlspecialchars($fav['nome']); ?></h4>
                                            <p class="preco">
                                                <?php if ($fav['preco_promocional'] && $fav['preco_promocional'] < $fav['preco']): ?>
                                                    De <span class="preco-antigo">R$ <?php echo number_format($fav['preco'], 2, ',', '.'); ?></span>
                                                    para <span class="preco-promo">R$ <?php echo number_format($fav['preco_promocional'], 2, ',', '.'); ?></span>
                                                <?php else: ?>
                                                    R$ <?php echo number_format($fav['preco'], 2, ',', '.'); ?>
                                                <?php endif; ?>
                                            </p>
                                        </a>
                                        <form action="favorito_processa.php" method="POST" class="ajax-form" data-type="favorito" style="display: inline;">
                                            <input type="hidden" name="produto_id" value="<?php echo $fav['id']; ?>">
                                            <button type="submit" class="btn-remover-fav">Remover</button>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div id="dados-panel" class="content-panel">
                        <h2>Meus Dados</h2>
                        <form class="account-form" action="perfil_atualizar.php" method="POST">
                            <input type="hidden" name="acao" value="dados">
                            <fieldset>
                                <div class="form-grid two-cols">
                                    <div class="form-group">
                                        <label for="nome">Nome</label>
                                        <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($cliente['cliente_nome']); ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="sobrenome">Sobrenome</label>
                                        <input type="text" id="sobrenome" name="sobrenome" value="<?php echo htmlspecialchars($cliente['cliente_sobrenome']); ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="cpf">CPF</label>
                                        <input type="text" id="cpf" name="cpf" value="<?php echo htmlspecialchars($cliente['cliente_cpf']); ?>" required>
                                    </div>
                                    

                                </div>
                                <div class="form-group">
                                    <label for="email">E-mail</label>
                                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($cliente['cliente_email']); ?>">
                                </div>

                                <div class="form-group">
                                    <label for="contato">Contato</label>
                                    <input type="contato" id="contato" name="contato" value="<?php echo htmlspecialchars($cliente['cliente_contato'] ?? ''); ?> " placeholder="(00) 00000-0000">
                                </div>
                            </fieldset>
                            <button type="submit" class="submit-btn">Salvar Alterações</button>
                        </form>
                        
                        <h2 style="margin-top: 40px;">Alterar Senha</h2>
                        <form class="account-form" action="perfil_atualizar.php" method="POST">
                            <input type="hidden" name="acao" value="senha">
                             <fieldset>
                                <div class="form-group">
                                    <label for="senha_atual">Senha Atual</label>
                                    <input type="password" id="senha_atual" name="senha_atual" required>
                                </div>
                                <div class="form-grid two-cols">
                                    <div class="form-group">
                                        <label for="nova_senha">Nova Senha</label>
                                        <input type="password" id="nova_senha" name="nova_senha" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="confirmar_nova_senha">Confirmar Nova Senha</label>
                                        <input type="password" id="confirmar_nova_senha" name="confirmar_nova_senha" required>
                                    </div>
                                </div>
                            </fieldset>
                            <button type="submit" class="submit-btn">Alterar Senha</button>
                        </form>
                    </div>
                    <div id="enderecos-panel" class="content-panel">
                        <h2 class= "h2-address" style="justify-content: space-between; display: flex;">Meus Endereços <a href="endereco_formulario.php" class="btn-novo" >+ Adicionar Novo</a></h2>
                       
                        
                        <?php if (empty($enderecos)): ?>
                             <p style="color: #888;">Nenhum endereço cadastrado.</p>
                        <?php else: ?>
                            <?php foreach ($enderecos as $endereco): ?>
                                <div style="background: #222; padding: 20px; border-radius: 6px; margin-bottom: 20px;">
                                  <a href="endereco_formulario.php?id=<?php echo $endereco['id']; ?>" style=" color: #bb9a65; text-decoration: underline;">Editar</a><br>
                                    <strong><?php echo htmlspecialchars($endereco['tipo']); ?> </strong>
                                    <?php echo htmlspecialchars($endereco['endereco']); ?>, <?php echo htmlspecialchars($endereco['numero']); ?><br>
                                    <?php echo htmlspecialchars($endereco['bairro']); ?>, <?php echo htmlspecialchars($endereco['cidade']); ?> - <?php echo htmlspecialchars($endereco['estado']); ?><br>
                                    CEP: <?php echo htmlspecialchars($endereco['cep']); ?>
                                    </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                         </div>
                    </div>
            </div>
        </div>
    </main>

  <?php
    require 'templates/footer.php';
?>