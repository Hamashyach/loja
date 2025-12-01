
<?php
require 'templates/header.php'; 

// --- LÓGICA DE PREVIEW E CONFIGURAÇÃO ---
$is_preview = (
    isset($_GET['preview']) && $_GET['preview'] == 'true' &&
    isset($_SESSION['admin_logado']) && $_SESSION['admin_logado'] === true
);

$db_config = get_all_configs($pdo);

$config = $db_config; 
if ($is_preview && isset($_SESSION['preview_data'])) {
    $config = array_merge($db_config, $_SESSION['preview_data']);
}

// Hero
$hero_imagem = $config['hero_imagem'] ?? 'img/banner3.jpg';
$hero_titulo = $config['hero_titulo'] ?? 'Autenticidade além do estilo.';

// Content Grid 1
$grid1_imagem = $config['grid1_imagem'] ?? 'img/breezer1 (2).png';
$grid1_label = $config['grid1_label'] ?? 'Lion Company';
$grid1_titulo = $config['grid1_titulo'] ?? 'Breezer Style';
$grid1_texto = $config['grid1_texto'] ?? 'Uma celebração à herança do streetwear. Qualidade, artesanato e um estilo único.';
$grid1_link = $config['grid1_link'] ?? 'produtos.php';

// Content Grid 2
$grid2_imagem = $config['grid2_imagem'] ?? 'img/breezer1 (1).png';
$grid2_label = $config['grid2_label'] ?? 'Lion Company';
$grid2_titulo = $config['grid2_titulo'] ?? 'Breezer Style';
$grid2_texto = $config['grid2_texto'] ?? 'Uma celebração à herança do streetwear. Qualidade, artesanato e um estilo único.';
$grid2_link = $config['grid2_link'] ?? 'produtos.php';

// Feature Banner (Calçados)
$feature_banner_imagem = $config['feature_banner_imagem'] ?? 'img/tenis-removebg-preview.png';
$feature_banner_label = $config['feature_banner_label'] ?? 'Lion Company Footwear';
$feature_banner_titulo = $config['feature_banner_titulo'] ?? 'Design que Deixa Marcas';
$feature_banner_texto = $config['feature_banner_texto'] ?? 'Construídos para durar e desenhados para impressionar. Nossa linha de calçados une o artesanato tradicional com uma estética urbana contemporânea, oferecendo conforto inigualável a cada passo.';
$feature_banner_link = $config['feature_banner_link'] ?? 'produtos.php';

// Feature Perfum
$perfum_imagem = $config['perfum_imagem'] ?? 'img/perfume-fundo.png';
$perfum_label = $config['perfum_label'] ?? 'Perfumes';
$perfum_titulo = $config['perfum_titulo'] ?? 'Brand Collection';
$perfum_texto = $config['perfum_texto'] ?? 'A Brand Collection revela sua essência com fragrâncias marcantes que traduzem personalidade e deixam uma memória olfativa inesquecível.';
$perfum_link = $config['perfum_link'] ?? 'produtos.php';

// --- BUSCAR CATEGORIAS EM DESTAQUE ---
try {
    $stmt_destaques = $pdo->query("
        SELECT nome, slug, imagem_destaque, label_destaque 
        FROM tb_categorias 
        WHERE em_destaque = 1 AND ativo = 1
        ORDER BY nome
    ");
    $destaques = $stmt_destaques->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $destaques = []; 
}

// ---  BUSCAR PRODUTOS "IDEIA DE KIT" ---
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
?>
    <main>
       <?php 
        // ---MOSTRA A BARRA DE PREVIEW ---
        if ($is_preview): 
        ?>
            <div style="background-color: #bb9a65; color: #000; text-align: center; padding: 15px; font-weight: bold; position: fixed; top: 0; left: 0; width: 100%; z-index: 99999;">
                MODO DE VISUALIZAÇÃO. Estas alterações não são públicas.
                <a href="<?php echo strtok($_SERVER["REQUEST_URI"], '?'); ?>" style="color: #fff; background: #333; padding: 5px 10px; margin-left: 20px; text-decoration: none; border-radius: 4px;">Sair do Preview</a>
            </div>
            <div style="height: 50px;"></div> <?php 
        endif; 
        ?>

        <section class="hero-section" style="background-image: url('<?php echo htmlspecialchars($hero_imagem); ?>');">
            <div class="hero-content">
                <p>Lion Company</p>
                <h1><?php echo htmlspecialchars($hero_titulo); ?></h1>
                <a href="produtos.php" class="cta-link">EXPLORE NOSSOS PRODUTOS</a>
            </div>
        </section>

        <section class="content-grid">
            <div class="grid-item" style="background-image: url('<?php echo BASE_URL . '/' . htmlspecialchars($grid1_imagem); ?>');">
                <div class="grid-item-content">
                    <p class="label"><?php echo htmlspecialchars($grid1_label); ?></p>
                    <h2><?php echo htmlspecialchars($grid1_titulo); ?></h2>
                    <p><?php echo htmlspecialchars($grid1_texto); ?></p>
                    <a href="<?php echo BASE_URL . '/' . htmlspecialchars($grid1_link); ?>" class="cta-link" id="cta-link3">EXPLORAR</a>
                </div>
            </div>
            <div class="grid-item" style="background-image: url('<?php echo BASE_URL . '/' . htmlspecialchars($grid2_imagem); ?>');">
                <div class="grid-item-content">
                    <p class="label"><?php echo htmlspecialchars($grid2_label); ?></p>
                    <h2><?php echo htmlspecialchars($grid2_titulo); ?></h2>
                    <p><?php echo htmlspecialchars($grid2_texto); ?></p>
                    <a href="<?php echo BASE_URL . '/' . htmlspecialchars($grid2_link); ?>" class="cta-link" id="cta-link3">EXPLORAR</a>
                </div>
            </div>
        </section>

        <section class="feature-banner">
            <div class="container banner-grid">
                <div class="banner-content">
                    <p class="label"><?php echo htmlspecialchars($feature_banner_label); ?></p>
                    <h2><?php echo htmlspecialchars($feature_banner_titulo); ?></h2>
                    <p><?php echo htmlspecialchars($feature_banner_texto); ?></p>
                    <a href="<?php echo BASE_URL . '/' . htmlspecialchars($feature_banner_link); ?>" class="cta-link dark" id="cta-link2">Ver todos os calçados</a>
                </div>
                <div class="banner-image">
                    <img src="<?php echo BASE_URL . '/' . htmlspecialchars($feature_banner_imagem); ?>" alt="Tênis da Lion Company">
                </div>
            </div>
        </section>

        <section class="highlights-section container">
            <div class="section-header">
                <p id="kit">Os melhores</p>
                <h2>Destaques</h2>
                <p>Explore nossas coleções das melhores marcas e mais.</p>
            </div>

            <div class="carousel-container">
                
                <div class="carousel-track"> 
                    
                    <?php 
                    if (empty($destaques)): 
                    ?>
                        <p style="text-align: center; width: 100%; color: #888;">Nenhum destaque configurado no momento.</p>
                    <?php 
                    else: 
                        foreach ($destaques as $destaque): 
                            $img = $destaque['imagem_destaque'] ? (BASE_URL . '/' . htmlspecialchars($destaque['imagem_destaque'])) : (BASE_URL . '/img/placeholder.png');
                            $link = BASE_URL . '/produtos.php?categoria=' . htmlspecialchars($destaque['slug']);
                    ?>
                        <div class="carousel-slide" style="background-image: url('<?php echo $img; ?>');">
                            <div class="slide-content">
                                <p class="label"><?php echo htmlspecialchars($destaque['label_destaque'] ?? ''); ?></p>
                                <h2><?php echo htmlspecialchars($destaque['nome']); ?></h2>
                                <a href="<?php echo $link; ?>" class="cta-link">COMPRAR AGORA</a>
                            </div>
                        </div>
                    <?php 
                        endforeach; 
                    endif; 
                    ?>
                </div>

                <button class="carousel-button prev" aria-label="Slide anterior">&lt;</button>
                <button class="carousel-button next" aria-label="Próximo slide">&gt;</button>
            </div>
        </section>
        
        <section class="feature-perfum" style="background-image: url('<?php echo BASE_URL . '/' . htmlspecialchars($perfum_imagem); ?>');">
            <div class="feature-content">
                <p class="label"><?php echo htmlspecialchars($perfum_label); ?></p>
                <h1><?php echo htmlspecialchars($perfum_titulo); ?></h1>
                <p><?php echo htmlspecialchars($perfum_texto); ?></p>
                <a href="<?php echo BASE_URL . '/' . htmlspecialchars($perfum_link); ?>" class="cta-link">Conheça nossa Coleção</a>
            </div>
        </section>
    </main> 
<?php
    require 'templates/footer.php';
?>