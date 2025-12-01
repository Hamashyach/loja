<?php
require_once 'auth_check.php';
require_once '../bd/config.php';

// 1. Validar IDs
$imagem_id = (int)($_GET['id'] ?? 0);
$produto_id = (int)($_GET['produto_id'] ?? 0); // Para onde voltar

if ($imagem_id <= 0 || $produto_id <= 0) {
    header("Location: produtos.php?erro=id_invalido");
    exit;
}

try {
    // 2. Buscar o caminho do ficheiro ANTES de apagar
    $stmt_find = $pdo->prepare("SELECT caminho_imagem FROM tb_produto_imagens WHERE id = ?");
    $stmt_find->execute([$imagem_id]);
    $imagem = $stmt_find->fetch();

    // 3. Apagar o registo do banco de dados
    $stmt_delete = $pdo->prepare("DELETE FROM tb_produto_imagens WHERE id = ?");
    $stmt_delete->execute([$imagem_id]);

    // 4. Apagar o ficheiro físico do servidor
    if ($imagem && file_exists('../' . $imagem['caminho_imagem'])) {
        unlink('../' . $imagem['caminho_imagem']);
    }

    // 5. Redirecionar de volta para o formulário do produto
    header("Location: produto_formulario.php?id=$produto_id&sucesso=imagem_apagada");
    exit;

} catch (PDOException $e) {
    die("Erro ao apagar imagem: " . $e->getMessage());
}
?>