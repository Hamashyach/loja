<?php
require_once 'auth_check.php';
require_once '../bd/config.php';


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
    $string = trim($string, '-');
    return $string;
}


if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: marcas.php");
    exit;
}

$acao = $_GET['acao'] ?? 'novo';


$nome = $_POST['nome'] ?? '';
$slug_input = $_POST['slug'] ?? '';
$ativo = $_POST['ativo'] ?? 0;

if (empty($nome)) {
    die("O campo 'Nome' é obrigatório.");
}

$slug = '';
if (!empty($slug_input)) {
    $slug = gerarSlug($slug_input);
} else {
    $slug = gerarSlug($nome);
}


try {
    if ($acao == 'novo') {
        
        $sql = "INSERT INTO tb_marcas (nome, slug, ativo) VALUES (?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nome, $slug, $ativo]);

    } elseif ($acao == 'editar' && isset($_GET['id'])) {
        
        $id = (int)$_GET['id'];
        $sql = "UPDATE tb_marcas SET nome = ?, slug = ?, ativo = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nome, $slug, $ativo, $id]);
    }

    
    header("Location: marcas.php?sucesso=1"); 
    exit;

} catch (PDOException $e) {
    if ($e->getCode() == '23000') {
        die("Erro: O 'slug' (URL) '{$slug}' já existe. Volte e escolha outro.");
    } else {
        die("Erro ao salvar marca: " . $e->getMessage());
    }
}
?>