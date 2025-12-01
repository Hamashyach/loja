<?php
require_once 'auth_check.php';
require_once '../bd/config.php';

// Função Helper para gerar Slug (removendo acentos e espaços)
function gerarSlug($string) {
    $string = mb_strtolower($string, 'UTF-8');
    $string = preg_replace('/[áàãâä]/u', 'a', $string);
    $string = preg_replace('/[éèêë]/u', 'e', $string);
    $string = preg_replace('/[íìîï]/u', 'i', $string);
    $string = preg_replace('/[óòõôö]/u', 'o', $string);
    $string = preg_replace('/[úùûü]/u', 'u', $string);
    $string = preg_replace('/[ç]/u', 'c', $string);
    $string = preg_replace('/[^a-z0-9\s-]/', '', $string);
    $string = preg_replace('/[\s-]+/', '-', $string);
    return trim($string, '-');
}

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: categorias.php");
    exit;
}

$acao = $_GET['acao'] ?? 'novo';

// Coleta e Limpeza
$nome = trim($_POST['nome'] ?? '');
$slug_input = trim($_POST['slug'] ?? '');
$ativo = (int)($_POST['ativo'] ?? 0);
$label_destaque = trim($_POST['label_destaque'] ?? '');
$em_destaque = isset($_POST['em_destaque']) ? 1 : 0;
// Padroniza o menu principal para MAIÚSCULAS (ROUPAS, PERFUMES...)
$menu_principal = !empty($_POST['menu_principal']) ? strtoupper($_POST['menu_principal']) : null; 

if (empty($nome)) {
    header("Location: categoria_formulario.php?id=" . ($_GET['id'] ?? '') . "&erro=nome_vazio");
    exit;
}

// Gera slug se estiver vazio
$slug = !empty($slug_input) ? gerarSlug($slug_input) : gerarSlug($nome);

// Upload de Imagem (se houver)
$caminho_imagem = null;
if (isset($_FILES['imagem_destaque']) && $_FILES['imagem_destaque']['error'] == UPLOAD_ERR_OK) {
    $arquivo = $_FILES['imagem_destaque'];
    $upload_dir = '../uploads/site/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

    $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
    $novo_nome = 'cat_' . uniqid() . '.' . $extensao;
    
    if (move_uploaded_file($arquivo['tmp_name'], $upload_dir . $novo_nome)) {
        $caminho_imagem = 'uploads/site/' . $novo_nome;
    }
}

try {
    $pdo->beginTransaction();

    // Se esta categoria for destaque, remove o destaque das outras (se for regra de 1 destaque por vez)
    // Se quiser permitir múltiplos destaques (carrossel), remova este bloco IF.
    /* if ($em_destaque == 1) {
        $pdo->exec("UPDATE tb_categorias SET em_destaque = 0"); 
    }
    */

    if ($acao == 'novo') {
        $sql = "INSERT INTO tb_categorias (nome, slug, ativo, imagem_destaque, label_destaque, em_destaque, menu_principal) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nome, $slug, $ativo, $caminho_imagem, $label_destaque, $em_destaque, $menu_principal]);

    } elseif ($acao == 'editar' && isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        
        $sql = "UPDATE tb_categorias SET nome = ?, slug = ?, ativo = ?, label_destaque = ?, em_destaque = ?, menu_principal = ?";
        $params = [$nome, $slug, $ativo, $label_destaque, $em_destaque, $menu_principal];

        if ($caminho_imagem) {
            $sql .= ", imagem_destaque = ?";
            $params[] = $caminho_imagem;
        }
        
        $sql .= " WHERE id = ?";
        $params[] = $id;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    $pdo->commit();
    header("Location: categorias.php?sucesso=1");
    exit;

} catch (PDOException $e) {
    $pdo->rollBack();
    $redirect_id = isset($_GET['id']) ? "&id=" . $_GET['id'] : "";
    
    if ($e->getCode() == '23000') {
        header("Location: categoria_formulario.php?erro=slug_existente&slug=$slug" . $redirect_id);
    } else {
        // Log erro (opcional)
        header("Location: categoria_formulario.php?erro=erro_desconhecido" . $redirect_id);
    }
    exit;
}
?>