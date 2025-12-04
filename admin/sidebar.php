<?php
$pagina_atual = basename($_SERVER['PHP_SELF']);
?>

<aside class="admin-sidebar">
    <div class="logo">
        <img src="../img/logo.png" alt="Lion Company" style="max-height: 60px;">
    </div>
    
    <nav class="admin-nav">
        <a href="dashboard.php" class="<?php echo ($pagina_atual == 'dashboard.php') ? 'active' : ''; ?>">
            <img src="../img/icones/inicio.png" class="menu-icon"> Dashboard
        </a>
        
        <a href="pedidos.php" class="<?php echo ($pagina_atual == 'pedidos.php' || $pagina_atual == 'pedido_detalhe.php') ? 'active' : ''; ?>">
            <img src="../img/icones/pedidos.png" class="menu-icon"> Pedidos
        </a>
        
        <a href="produtos.php" class="<?php echo ($pagina_atual == 'produtos.php' || $pagina_atual == 'produto_formulario.php') ? 'active' : ''; ?>">
            <img src="../img/icones/produtos.png" class="menu-icon"> Produtos
        </a>
        
        <a href="categorias.php" class="<?php echo ($pagina_atual == 'categorias.php' || $pagina_atual == 'categoria_formulario.php') ? 'active' : ''; ?>">
            <img src="../img/icones/pesquisa.png" class="menu-icon"> Categorias
        </a>
        
        <a href="marcas.php" class="<?php echo ($pagina_atual == 'marcas.php' || $pagina_atual == 'marca_formulario.php') ? 'active' : ''; ?>">
            <img src="../img/icones/logo_3D.png" class="menu-icon"> Marcas
        </a>
        
        <a href="clientes.php" class="<?php echo ($pagina_atual == 'clientes.php') ? 'active' : ''; ?>">
            <img src="../img/icones/perfil.png" class="menu-icon"> Clientes
        </a>

        <a href="inscritos.php" class="<?php echo ($pagina_atual == 'inscritos.php') ? 'active' : ''; ?>">
            <img src="../img/icones/favorito.png" class="menu-icon"> Inscritos
        </a>
        
        <a href="admin_usuarios.php" class="<?php echo ($pagina_atual == 'admin_usuarios.php') ? 'active' : ''; ?>">
            <img src="../img/icones/configuracao.png" class="menu-icon"> Admins
        </a>
        
        <a href="config_site.php" class="<?php echo ($pagina_atual == 'config_site.php') ? 'active' : ''; ?>">
            <img src="../img/icones/configuracao.png" class="menu-icon"> Configurações
        </a>
    </nav>
</aside>