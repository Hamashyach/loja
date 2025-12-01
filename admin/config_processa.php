<?php

require_once 'auth_check.php';
require_once '../bd/config.php';

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: config_site.php");
    exit;
}
function salvar_config($pdo, $key, $value) {
    try {
        $sql = "INSERT INTO tb_site_config (config_key, config_value) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE config_value = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$key, $value, $value]);
    } catch (PDOException $e) {
        
    }
}

function processar_upload_config($pdo, $file_input_name, $config_key, $prefix) {
    if (isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]['error'] == UPLOAD_ERR_OK) {
        
        $arquivo = $_FILES[$file_input_name];
        $upload_dir = '../uploads/site/'; 
        
        $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
        $novo_nome_arquivo = $prefix . time() . '.' . $extensao;
        $caminho_completo = $upload_dir . $novo_nome_arquivo;

        if (move_uploaded_file($arquivo['tmp_name'], $caminho_completo)) {
            $caminho_db = 'uploads/site/' . $novo_nome_arquivo;
            salvar_config($pdo, $config_key, $caminho_db);
        }
    }
}

// --- SALVAR OS CAMPOS DE TEXTO ---
// Hero e Modal
salvar_config($pdo, 'hero_titulo', $_POST['hero_titulo'] ?? '');
$modal_ativo = isset($_POST['modal_ativo']) ? '1' : '0';
salvar_config($pdo, 'modal_ativo', $modal_ativo);
salvar_config($pdo, 'modal_titulo', $_POST['modal_titulo'] ?? ''); 
salvar_config($pdo, 'modal_subtitulo', $_POST['modal_subtitulo'] ?? ''); 

// Grid 1
salvar_config($pdo, 'grid1_label', $_POST['grid1_label'] ?? '');
salvar_config($pdo, 'grid1_titulo', $_POST['grid1_titulo'] ?? '');
salvar_config($pdo, 'grid1_texto', $_POST['grid1_texto'] ?? '');
salvar_config($pdo, 'grid1_link', $_POST['grid1_link'] ?? '');

// Grid 2
salvar_config($pdo, 'grid2_label', $_POST['grid2_label'] ?? '');
salvar_config($pdo, 'grid2_titulo', $_POST['grid2_titulo'] ?? '');
salvar_config($pdo, 'grid2_texto', $_POST['grid2_texto'] ?? '');
salvar_config($pdo, 'grid2_link', $_POST['grid2_link'] ?? '');

// Banner Calçados
salvar_config($pdo, 'feature_banner_label', $_POST['feature_banner_label'] ?? '');
salvar_config($pdo, 'feature_banner_titulo', $_POST['feature_banner_titulo'] ?? '');
salvar_config($pdo, 'feature_banner_texto', $_POST['feature_banner_texto'] ?? '');
salvar_config($pdo, 'feature_banner_link', $_POST['feature_banner_link'] ?? '');

//  Perfume
salvar_config($pdo, 'perfum_label', $_POST['perfum_label'] ?? '');
salvar_config($pdo, 'perfum_titulo', $_POST['perfum_titulo'] ?? '');
salvar_config($pdo, 'perfum_texto', $_POST['perfum_texto'] ?? '');
salvar_config($pdo, 'perfum_link', $_POST['perfum_link'] ?? '');

processar_upload_config($pdo, 'hero_imagem', 'hero_imagem', 'hero_banner_');
processar_upload_config($pdo, 'grid1_imagem', 'grid1_imagem', 'grid1_');
processar_upload_config($pdo, 'grid2_imagem', 'grid2_imagem', 'grid2_');
processar_upload_config($pdo, 'feature_banner_imagem', 'feature_banner_imagem', 'feature_banner_');
processar_upload_config($pdo, 'perfum_imagem', 'perfum_imagem', 'perfum_banner_');

header("Location: config_site.php?sucesso=1");
exit;
?>