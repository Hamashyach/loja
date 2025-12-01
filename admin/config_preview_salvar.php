<?php
require_once 'auth_check.php';

$_SESSION['preview_data'] = [];

function processar_upload_preview($file_input_name, $config_key, $prefix) {
    if (isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]['error'] == UPLOAD_ERR_OK) {
        
        $arquivo = $_FILES[$file_input_name];
        $upload_dir = '../uploads/site/temp/'; 
        
        $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
        $novo_nome_arquivo = 'temp_' . $prefix . time() . '.' . $extensao;
        $caminho_completo = $upload_dir . $novo_nome_arquivo;

        if (move_uploaded_file($arquivo['tmp_name'], $caminho_completo)) {
            $caminho_sessao = 'uploads/site/temp/' . $novo_nome_arquivo;
            $_SESSION['preview_data'][$config_key] = $caminho_sessao;
        }
    }
}

// SALVANDO OS DADOS DE TEXTO NA SESSÃO 
//hero e modal
$_SESSION['preview_data']['hero_titulo'] = $_POST['hero_titulo'] ?? '';
$_SESSION['preview_data']['modal_ativo'] = isset($_POST['modal_ativo']) ? '1' : '0';
$_SESSION['preview_data']['modal_titulo'] = $_POST['modal_titulo'] ?? '';
$_SESSION['preview_data']['modal_subtitulo'] = $_POST['modal_subtitulo'] ?? '';

// Grid 1
$_SESSION['preview_data']['grid1_label'] = $_POST['grid1_label'] ?? '';
$_SESSION['preview_data']['grid1_titulo'] = $_POST['grid1_titulo'] ?? '';
$_SESSION['preview_data']['grid1_texto'] = $_POST['grid1_texto'] ?? '';
$_SESSION['preview_data']['grid1_link'] = $_POST['grid1_link'] ?? '';

// Grid 2
$_SESSION['preview_data']['grid2_label'] = $_POST['grid2_label'] ?? '';
$_SESSION['preview_data']['grid2_titulo'] = $_POST['grid2_titulo'] ?? '';
$_SESSION['preview_data']['grid2_texto'] = $_POST['grid2_texto'] ?? '';
$_SESSION['preview_data']['grid2_link'] = $_POST['grid2_link'] ?? '';

// Feature Banner
$_SESSION['preview_data']['feature_banner_label'] = $_POST['feature_banner_label'] ?? '';
$_SESSION['preview_data']['feature_banner_titulo'] = $_POST['feature_banner_titulo'] ?? '';
$_SESSION['preview_data']['feature_banner_texto'] = $_POST['feature_banner_texto'] ?? '';
$_SESSION['preview_data']['feature_banner_link'] = $_POST['feature_banner_link'] ?? '';

// Feature Perfum
$_SESSION['preview_data']['perfum_label'] = $_POST['perfum_label'] ?? '';
$_SESSION['preview_data']['perfum_titulo'] = $_POST['perfum_titulo'] ?? '';
$_SESSION['preview_data']['perfum_texto'] = $_POST['perfum_texto'] ?? '';
$_SESSION['preview_data']['perfum_link'] = $_POST['perfum_link'] ?? '';

processar_upload_preview('hero_imagem', 'hero_imagem', 'hero_');
processar_upload_preview('grid1_imagem', 'grid1_imagem', 'grid1_');
processar_upload_preview('grid2_imagem', 'grid2_imagem', 'grid2_');
processar_upload_preview('feature_banner_imagem', 'feature_banner_imagem', 'feature_');
processar_upload_preview('perfum_imagem', 'perfum_imagem', 'perfum_');

header('Content-Type: application/json');
echo json_encode(['success' => true]);
exit;
?>