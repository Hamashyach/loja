<?php

require 'bd/config.php';
require 'templates/header.php';


//logica titulo
$titulo_pagina = "Produtos";
if (isset($_GET['busca']) && !empty($_GET['busca'])) {
    $titulo_pagina = 'Busca: "' . htmlspecialchars($_GET['busca']) . '"';
} elseif(isset($_GET['categoria']) && !empty($_GET['categoria'])) {
    $slug_cat=strtolower($_GET['categoria']);

    $mapa_categorias = [
        'roupas'     => 'Roupas',
        'calcados'   => 'Calçados',
        'acessorios' => 'Acessórios',
        'perfumes'   => 'Perfumes',
        'marcas'     => 'Marcas'
    ];

    $titulo_pagina = $mapa_categorias[$slug_cat] ?? ucfirst($slug_cat);

} elseif (isset($_GET['marca']) && !empty($_GET['marca'])) {
    $titulo_pagina = ucfirst($_GET['marca']);
}

// --- BUSCAR PRODUTOS "IDEIA DE KIT" ---
try {
    $stmt_kits = $pdo->query("
        SELECT id, nome, imagem_principal 
        FROM tb_produtos 
        WHERE em_destaque_kit = 1 AND ativo = 1
        ORDER BY RAND() -- Ordena aleatoriamente
        LIMIT 5 -- Limita a 5 kits
    ");
    $kits = $stmt_kits->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $kits = []; 
}

// --- LÓGICA DE BUSCA DE PRODUTOS ---
try {
    $sql = "SELECT id, nome, preco, imagem_principal FROM tb_produtos WHERE ativo = 1";
    $sql .= " ORDER BY id DESC"; 

    $stmt = $pdo->query($sql);
    $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $produtos = []; 
}

// --- FILTRO LATERAL ---
try {
    $stmt_cats_aside = $pdo->query("SELECT nome, slug FROM tb_categorias WHERE ativo = 1 ORDER BY nome");
    $categorias_aside = $stmt_cats_aside->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt_marcas_aside = $pdo->query("SELECT nome, slug FROM tb_marcas WHERE ativo = 1 ORDER BY nome");
    $marcas_aside = $stmt_marcas_aside->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $categorias_aside = [];
    $marcas_aside = [];
}

// --- LÓGICA DE BUSCA E FILTRO DE PRODUTOS ---
$filtro_sql = " WHERE p.ativo = 1 ";
$params = [];
$categoria_atual = null;
$exibir_filtro_tamanho = true;

//  Filtrar por Categoria
if (isset($_GET['categoria']) && !empty($_GET['categoria'])) {
    $slug_cat = $_GET['categoria'];
   
    $stmt_cat_info = $pdo->prepare("SELECT id, nome, menu_principal, slug FROM tb_categorias WHERE slug = ?");
    $stmt_cat_info->execute([$slug_cat]);
    $categoria_atual = $stmt_cat_info->fetch(PDO::FETCH_ASSOC);

    if($categoria_atual) {
        $nome_maiusculo = strtoupper($categoria_atual['nome']);
    }

    $stmt_filhas = $pdo->prepare("SELECT id FROM tb_categorias WHERE menu_principal = ?");
    $stmt_filhas->execute([$nome_maiusculo]);
    $ids_filhas = $stmt_filhas->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($ids_filhas)) {
        $placeholders = implode(',', array_fill(0, count($ids_filhas), '?'));
        $filtro_sql .= " AND p.categoria_id IN ($placeholders) ";
            $params = array_merge($params, $ids_filhas);
        } else {
            $filtro_sql .= " AND c.slug = ? ";
            $params[] = $slug_cat;
    }
        if ($nome_maiusculo == 'PERFUMES' || $categoria_atual['menu_principal'] == 'PERFUMES') {
            $exibir_filtro_tamanho = false;
        }
    }

// --- FILTRO POR MARCA ---
if (isset($_GET['marca']) && !empty($_GET['marca'])) {
    $slug_marca = $_GET['marca'];
    $filtro_sql .= " AND m.slug = ? ";
    $params[] = $slug_marca;
}

// --- FILTRO POR BUSCA ---
if (isset($_GET['busca']) && !empty($_GET['busca'])) {
    $termo_busca = trim($_GET['busca']);
    $filtro_sql .= " AND (p.nome LIKE ? OR p.descricao LIKE ? OR p.sku LIKE ?) ";
    $termo_like = "%{$termo_busca}%";
    $params[] = $termo_like;
    $params[] = $termo_like;
    $params[] = $termo_like;
}

//filtro por tamanho

if (isset($_GET['tamanho']) && !empty($_GET['tamanho'])) {
    $filtro_tamanho = $_GET['tamanho'];
    $filtro_sql .=" AND EXISTS ( SELECT 1 FROM tb_produto_variacoes v WHERE v.produto_id = p.id AND V.tamanho = ? AND v.estoque > 0)";
    $params[] = $filtro_tamanho;
}

//filtro por preco
if (isset($_GET['preco_max']) && is_numeric($_GET['preco_max'])) {
    $preco_max = (float)$_GET['preco_max'];

    $filtro_sql .= " AND (
        CASE 
            WHEN p.preco_promocional IS NOT NULL AND p.preco_promocional > 0 THEN p.preco_promocional 
            ELSE p.preco 
        END
    ) <= ? ";
    
    $params[] = $preco_max;
}

// --- BUSCAR PRODUTOS ---
try {
    $sql_produtos = "SELECT 
                p.id, p.nome, p.preco, p.imagem_principal, p.estoque 
            FROM 
                tb_produtos p
            LEFT JOIN tb_categorias c ON p.categoria_id = c.id
            LEFT JOIN tb_marcas m ON p.marca_id = m.id
            " . $filtro_sql . 
            " ORDER BY p.id DESC";

    $stmt = $pdo->prepare($sql_produtos);
    $stmt->execute($params);
    $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $produtos = [];
    // echo $e->getMessage();
}


try {
    if ($categoria_atual) {
     
        $menu_contexto = $categoria_atual['menu_principal'] ?: strtoupper($categoria_atual['nome']);
        $stmt_aside = $pdo->prepare("SELECT nome, slug FROM tb_categorias WHERE menu_principal = ? AND ativo = 1 ORDER BY nome");
        $stmt_aside->execute([$menu_contexto]);
        $categorias_aside = $stmt_aside->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($categorias_aside)) {
             $stmt_aside = $pdo->prepare("SELECT nome, slug FROM tb_categorias WHERE menu_principal = ? AND ativo = 1 ORDER BY nome");
             $stmt_aside->execute([strtoupper($categoria_atual['nome'])]);
             $categorias_aside = $stmt_aside->fetchAll(PDO::FETCH_ASSOC);
        }
    } else {
        $menus_fixos = ['ROUPAS', 'CALCADOS', 'PERFUMES', 'ACESSORIOS']; 
        $stmt_aside = $pdo->query("SELECT DISTINCT c.nome, c.slug FROM tb_categorias c JOIN tb_produtos p ON p.categoria_id = c.id WHERE c.ativo = 1 ORDER BY c.nome");
        $categorias_aside = $stmt_aside->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {}

$marcas_aside = [];
try {
 
    $sql_marcas_filtro = str_replace("AND m.slug = ?", "", $filtro_sql); 
   
    
    $sql_marcas = "SELECT DISTINCT m.nome, m.slug 
                   FROM tb_marcas m 
                   JOIN tb_produtos p ON p.marca_id = m.id 
                   LEFT JOIN tb_categorias c ON p.categoria_id = c.id 
                   " . $sql_marcas_filtro . " 
                   ORDER BY m.nome";

    $params_marcas = $params;
    if (isset($_GET['marca'])) { array_pop($params_marcas); } 

    $stmt_m = $pdo->prepare($sql_marcas);
    $stmt_m->execute($params_marcas);
    $marcas_aside = $stmt_m->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}


$tamanhos_disponiveis = [];
if ($exibir_filtro_tamanho) {
    try {
        $sql_tamanhos = "SELECT DISTINCT v.tamanho 
                         FROM tb_produto_variacoes v
                         JOIN tb_produtos p ON v.produto_id = p.id
                         LEFT JOIN tb_categorias c ON p.categoria_id = c.id
                         LEFT JOIN tb_marcas m ON p.marca_id = m.id
                         " . $filtro_sql . "
                         AND v.estoque > 0
                         AND v.tamanho != 'U' -- Oculta tamanho único
                         ORDER BY v.tamanho"; 
        
        $stmt_t = $pdo->prepare($sql_tamanhos);
        $stmt_t->execute($params);
        $tamanhos_disponiveis = $stmt_t->fetchAll(PDO::FETCH_COLUMN);

        if (empty($tamanhos_disponiveis)) {
            $exibir_filtro_tamanho = false;
        }
        
        $ordem_tamanhos = ['P', 'M', 'G', 'GG', 'XG', '34', '35', '36', '37', '38', '39', '40', '41', '42', '43', '44'];
        usort($tamanhos_disponiveis, function($a, $b) use ($ordem_tamanhos) {
            $posA = array_search($a, $ordem_tamanhos);
            $posB = array_search($b, $ordem_tamanhos);
            if ($posA === false) return 1;
            if ($posB === false) return -1;
            return $posA - $posB;
        });

    } catch (Exception $e) {}
}

// --- LÓGICA DE BUSCA ---
if (isset($_GET['busca']) && !empty($_GET['busca'])) {
    $termo_busca = trim($_GET['busca']);
    $where_clauses[] = "(p.nome LIKE ? OR p.descricao LIKE ? OR p.sku LIKE ?)"; 
    $termo_like = "%{$termo_busca}%";
    $params[] = $termo_like;
    $params[] = $termo_like;
    $params[] = $termo_like;
}

  // --- LÓGICA DE FAVORITOS---
$favoritos_ids = [];
if (isset($_SESSION['cliente_logado']) && $_SESSION['cliente_logado'] === true) {
    try {
        $stmt_favs = $pdo->prepare("SELECT produto_id FROM tb_client_favorites WHERE cliente_id = ?");
        $stmt_favs->execute([(int)$_SESSION['cliente_id']]);
        $favoritos_ids = $stmt_favs->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) { }
}
?>

    <div class="page-overlay"></div>
    <aside class="filter-sidebar">
        <div class="sidebar-header">
            <h3>Filtrar & Ordenar</h3>
            <button class="close-filter-btn" aria-label="Fechar filtros">&times;</button>
        </div>
        
        <div class="sidebar-content">
            
            <?php if (!empty($categorias_aside)): ?>
            <div class="filter-group">
                <h4 class="filter-title">
                    <?php echo $categoria_atual ? 'Categorias em ' . htmlspecialchars($categoria_atual['nome']) : 'Categorias'; ?>
                </h4>
                <div class="filter-options">
                    <?php foreach ($categorias_aside as $cat): ?>
                        <a href="produtos.php?categoria=<?php echo htmlspecialchars($cat['slug']); ?>" 
                           class="<?php echo (isset($_GET['categoria']) && $_GET['categoria'] == $cat['slug']) ? 'active-filter' : ''; ?>">
                            <?php echo htmlspecialchars($cat['nome']); ?>
                        </a>
                    <?php endforeach; ?>
                    
                    <?php if ($categoria_atual): ?>
                        <a href="produtos.php" style="margin-top: 10px; font-size: 0.9em; color: #888;">&larr; Ver Todas as Categorias</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($marcas_aside)): ?>
            <div class="filter-group">
                <h4 class="filter-title">Marcas</h4>
                <div class="filter-options">
                     <?php foreach ($marcas_aside as $marca): ?>
                        <?php 
                            $url_marca = "produtos.php?marca=" . htmlspecialchars($marca['slug']);
                            if (isset($_GET['categoria'])) {
                                $url_marca .= "&categoria=" . htmlspecialchars($_GET['categoria']);
                            }
                        ?>
                        <a href="<?php echo $url_marca; ?>"
                           class="<?php echo (isset($_GET['marca']) && $_GET['marca'] == $marca['slug']) ? 'active-filter' : ''; ?>">
                            <?php echo htmlspecialchars($marca['nome']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($exibir_filtro_tamanho && !empty($tamanhos_disponiveis)): ?>
            <div class="filter-group">
                <h4 class="filter-title">Tamanho</h4>
                <div class="size-options">
                    <?php foreach ($tamanhos_disponiveis as $tam): ?>
                        <div class="size-option"><?php echo $tam; ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="filter-group">
                <h4 class="filter-title">Faixa de Preço</h4>
                <div class="price-range">
                    <input type="range" id="price-slider" min="50" max="1000" value="500" class="price-range-slider">
                    <div class="price-display">Até <span id="price-value">R$ 500</span></div>
                </div>
            </div>           
        </div>
        
        <div class="sidebar-footer">
            <button class="done-btn">Aplicar Filtros</button>
        </div>          
    </aside>
        <main>
            <div class="container">
                <div class="page-header">
                    <h1><?php echo $titulo_pagina; ?></h1>
                    <button class="filter-toggle-btn" id="filter-toggle-btn">
                        <svg width="20" height="20" viewBox="0 0 20 20"><path d="M1 5h18M1 10h18M1 15h18" fill="none" stroke="currentColor" stroke-width="2"></path></svg>
                        <span>Filtrar</span>
                    </button>
                </div>

                <div class="product-grid" id="product-grid">

                    <?php if (empty($produtos)): ?>
                        <p style="text-align: center; grid-column: 1 / -1; color: #888;">Nenhum produto encontrado.</p>
                    <?php else: ?>
                        <?php foreach ($produtos as $produto): 
                            $link_produto = BASE_URL . '/produto-detalhe.php?id=' . $produto['id'];
                            $img_produto = $produto['imagem_principal'] ? (BASE_URL . '/uploads/produtos/' . htmlspecialchars($produto['imagem_principal'])) : (BASE_URL . '/img/placeholder.png');
                            $is_fav = in_array($produto['id'], $favoritos_ids); 
                            $icon_fav = $is_fav ? 'favorito.png' : 'favorito.png';
                        ?>
                            <div class="product-card">
                                <a href="<?php echo $link_produto; ?>">
                                    <div class="product-image-wrapper">
                                        <img src="<?php echo $img_produto; ?>" alt="<?php echo htmlspecialchars($produto['nome']); ?>">
                                        <a href="<?php echo $link_produto; ?>" class="quick-shop-button">Ver Produto</a>
                                    </div>
                                </a>
                                <div class="product-info">
                                    <a href="<?php echo $link_produto; ?>"><h3><?php echo htmlspecialchars($produto['nome']); ?></h3></a>
                                    <p class="price">R$ <?php echo number_format($produto['preco'], 2, ',', '.'); ?></p>
                                    
                                    <div class="product-actions">
                                        
                                        <form action="<?php echo BASE_URL; ?>/favorito_processa.php" class="ajax-form" data-type="favorito" method="POST" style="display: inline; margin: 0;">
                                            <input type="hidden" name="produto_id" value="<?php echo $produto['id']; ?>">
                                            <button type="submit" class="btn-action-icon" aria-label="<?php echo $is_fav ? 'Remover dos Favoritos' : 'Adicionar aos Favoritos'; ?>" title="<?php echo $is_fav ? 'Remover dos Favoritos' : 'Adicionar aos Favoritos'; ?>">
                                                <img src="<?php echo BASE_URL; ?>/img/icones/<?php echo $icon_fav; ?>" 
                                                     alt="Favorito"
                                                     style="<?php echo $is_fav ? 'filter: brightness(0.8) sepia(1) hue-rotate(-50deg) saturate(5);' : ''; ?>"> 
                                            </button>
                                        </form>

                                        <form action="<?php echo BASE_URL; ?>/carrinho_acoes.php" class="ajax-form" method="POST" data-type="carrinho" style="display: inline; margin: 0;">
                                            <input type="hidden" name="acao" value="adicionar">
                                            <input type="hidden" name="produto_id" value="<?php echo $produto['id']; ?>">
                                            <input type="hidden" name="quantidade" value="1">
                                            <button type="submit" class="btn-action-icon" aria-label="Adicionar ao Carrinho">
                                            <img src="<?php echo BASE_URL; ?>/img/icones/carrinho.png" alt="Carrinho"></button>
                                        </form>
                                        
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                </div> 
                <div class="load-more-container">
                    <button id="load-more-btn" class="cta-button">Veja Mais</button>
                </div>
            </div>

            <section class="explore-section container">
                <div class="explore-grid">
                    <div class="explore-content">
                        <p id="kit">Kit Ideal</p>
                        <h2>Ideia de Kit</h2>
                        <p>Encontre a combinação perfeita.</p>
                    </div>

                    <div class="explore-carousel">
                        <div class="carousel-container-small">
                            <div class="carousel-track-small">                     
                                <?php if (empty($kits)): ?>
                                    <p style="text-align: center; width: 100%; color: #888;">Nenhum kit configurado.</p>
                                <?php 
                                else: 
                                    foreach ($kits as $kit):
                                        $kit_img = $kit['imagem_principal'] ? (BASE_URL . '/uploads/produtos/' . htmlspecialchars($kit['imagem_principal'])) : (BASE_URL . '/img/placeholder.png');
                                        $kit_link = BASE_URL . '/produto-detalhe.php?id=' . $kit['id'];
                                ?>
                                    <div class="carousel-slide-small" style="background-image: url('<?php echo $kit_img; ?>');">
                                        <div class="slide-content-small">
                                            <p class="label" id="kit-label">Combinação Perfeita</p> <h2 style="color: #fff; font-size: 1.2em; letter-spacing: 0.3em;"><?php echo htmlspecialchars($kit['nome']); ?></h2>
                                            <a href="<?php echo $kit_link; ?>" class="cta-link" id="cta-link3">Ver Detalhes</a>
                                        </div>
                                    </div>
                                <?php 
                                    endforeach; 
                                endif; 
                                ?>

                            </div>
                            <!-- <button class="carousel-button-small prev" aria-label="Anterior">&lt;</button>
                            <button class="carousel-button-small next" aria-label="Próximo">&gt;</button> -->
                        </div>
                    </div>
                </div>
            </section>
        </main>
   
<?php
    require 'templates/footer.php';
?>
    
