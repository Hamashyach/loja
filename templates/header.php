<?php
session_start();
require_once dirname(__DIR__) . '/bd/config.php';

$carrinho_count = 0;
if (!empty($_SESSION['carrinho'])) {
    foreach ($_SESSION['carrinho'] as $item) {
        $carrinho_count += $item['quantidade'];
    }
}

try {
    $stmt = $pdo->query("SELECT config_key, config_value FROM tb_site_config"); 
    $configs_raw = $stmt->fetchAll();
    $config = [];
    foreach ($configs_raw as $item) {
        $config[$item['config_key']] = $item['config_value']; 
    }
} catch(PDOException $e) {
    die("Erro ao buscar configurações do site: " . $e->getMessage());
}

//menu
try {
    $stmt_cats = $pdo->query("SELECT nome, menu_principal, slug FROM tb_categorias WHERE ativo = 1 ORDER BY nome");
    $categorias_raw = $stmt_cats->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt_marcas = $pdo->query("SELECT nome, slug FROM tb_marcas WHERE ativo = 1 ORDER BY nome");
    $marcas_menu = $stmt_marcas->fetchAll(PDO::FETCH_ASSOC);
    
    $menu_data = [];

    $itens_principais_map = [
        'ROUPAS'     => 'ROUPAS',
        'PERFUMES'   => 'PERFUMES',
        'CALCADOS'   => 'CALÇADOS', 
        'ACESSORIOS' => 'ACESSÓRIOS',
        'MARCAS'     => 'MARCAS'
    ]; 

    foreach ($categorias_raw as $cat) {
        $nome = htmlspecialchars($cat['nome']);
        $pai_raw = strtoupper($cat['menu_principal'] ?? '');
        if (array_key_exists($pai_raw, $itens_principais_map)) {
            if (!isset($menu_data[$pai_raw])) {
                $menu_data[$pai_raw] = [];
            }
            if (strtoupper($nome) != $pai_raw) {
                $menu_data[$pai_raw][] = [
                    'nome' => $nome, 
                    'slug' => $cat['slug'] 
                ];
            }
        }
    }
} catch (PDOException $e) {
    $menu_data = [];
    $marcas_menu = [];
    $itens_principais_map = [];
}

$cliente_logado = isset($_SESSION['cliente_logado']) && $_SESSION['cliente_logado'] === true;
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lion Company - Autenticidade Além do Estilo</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/style.css">
</head>
<body>


    <div id="splash-screen" class="splash-screen">
        <div class="splash-content">
            <img src="img/icones/logo3danimado.gif" alt="Logo animado" class="splash-logo-gif">
        </div>
        <audio id="splash-audio" src="img/IMG_7084--1-.mp4" preload="auto"></audio>
    </div>

    <div class="top-bar">
        <p>ENTREGA GRATUITA NA CIDADE</p>
    </div>

    <header class="main-header">
        <div class="container">
            <div class="header-logo">
                <a href="<?php echo BASE_URL; ?>/index.php">
                    <img src="img/logo.png" alt="Lion Company Logo">
                </a>
            </div>

           <nav class="main-nav">
                <ul>
                    <?php foreach ($itens_principais_map as $chave_db => $rotulo_exibicao): ?>
                        
                        <?php 
                      
                        
                        $submenus = $menu_data[$chave_db] ?? [];
                        $link_pai = BASE_URL . "/produtos.php?categoria=" . strtolower($chave_db); 
                        ?>

                        <?php if ($chave_db == 'MARCAS'): ?>
                            <li class="has-megamenu">
                                <a href="<?php echo BASE_URL; ?>/produtos.php?menu=marcas"><?php echo $rotulo_exibicao; ?></a>
                                <div class="mega-menu">
                                    <div class="mega-menu-column">
                                        <h4>TODAS AS MARCAS</h4>
                                        <ul>
                                            <?php foreach ($marcas_menu as $marca): ?>
                                                <li><a href="<?php echo BASE_URL; ?>/produtos.php?marca=<?php echo htmlspecialchars($marca['slug']); ?>"><?php echo htmlspecialchars($marca['nome']); ?></a></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                            </li>
                        
                        <?php elseif (!empty($submenus)): ?>
                            <li class="has-megamenu">
                                <a href="<?php echo $link_pai; ?>"><?php echo $rotulo_exibicao; ?></a>
                                <div class="mega-menu">
                                    <div class="mega-menu-column">
                                        <h4><?php echo $rotulo_exibicao; ?></h4> <ul>
                                            <?php foreach ($submenus as $sub): ?>
                                                <li><a href="<?php echo BASE_URL; ?>/produtos.php?categoria=<?php echo htmlspecialchars($sub['slug']); ?>"><?php echo $sub['nome']; ?></a></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                            </li>

                        <?php else: ?>
                            <li><a href="<?php echo $link_pai; ?>"><?php echo $rotulo_exibicao; ?></a></li>
                        <?php endif; ?>

                    <?php endforeach; ?>
                </ul>
            </nav>

            <button class="hamburger-btn" aria-label="Abrir menu" aria-expanded="false">
                <span class="line"></span>
                <span class="line"></span>
                <span class="line"></span>
            </button>

             <form action="<?php echo BASE_URL; ?>/produtos.php" method="GET" class="header-search-form">
                    <input type="text" name="busca" placeholder="Buscar..." required>
                    <button type="submit" aria-label="Pesquisar">
                        <img src="img/icones/pesquisa.png" alt="Lupa">
                    </button>
                </form>

            <div class="header-icons">
                <?php if (isset($_SESSION['cliente_logado']) && $_SESSION['cliente_logado'] === true): ?>
                    <a href="<?php echo BASE_URL; ?>/perfil.php" aria-label="Minha Conta">
                        <img style= "margin-top: 5px;" src="<?php echo BASE_URL; ?>/img/icones/perfil.png">
                    </a>
                <?php else: ?>
                    <a href="<?php echo BASE_URL; ?>/login.php" aria-label="Fazer Login">
                        <img style= "margin-top: 5px;" src="<?php echo BASE_URL; ?>/img/icones/perfil.png">
                    </a>
                <?php endif; ?>
                <?php if (isset($_SESSION['cliente_logado']) && $_SESSION['cliente_logado'] === true): ?>
                <a href="<?php echo BASE_URL; ?>/perfil.php#favoritos-panel" aria-label="Favoritos">
                    <img style= "margin-top: 5px;" src="img/icones/favorito.png"></a>
                <?php endif; ?>
                <a href="<?php echo BASE_URL; ?>/carrinho.php" aria-label="Carrinho de Compras" style="position: relative;">
                    <img style= "margin-top: 5px;" src="<?php echo BASE_URL; ?>/img/icones/carrinho.png">
                    
                    <?php if ($carrinho_count > 0): ?>
                    <span style="position: absolute; top: -10px; right: -10px; background-color: #bb9a65; 
                                color: #000; border-radius: 50%; width: 22px; height: 22px; 
                                display: flex; align-items: center; justify-content: center; 
                                font-size: 12px; font-weight: bold;">
                        <?php echo $carrinho_count; ?>
                    </span>
                    <?php endif; ?>
                </a>
                 <?php if (isset($_SESSION['cliente_logado']) && $_SESSION['cliente_logado'] === true): ?>
                    <a style="padding-top: 5px;" href="<?php echo BASE_URL; ?>/logout_cliente.php">Sair</a>
                <?php endif; ?>
            </div>
        </div>
    </header>
 