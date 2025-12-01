<?php
require_once 'auth_check.php';
require_once '../bd/config.php';

function gerarSkuAutomatico($nome) {
    $prefixo = substr(preg_replace('/[^a-zA-Z]/', '', $nome), 0, 3);
    $prefixo = strtoupper($prefixo);
    
    if (empty($prefixo)) $prefixo = 'PROD';
    $sufixo = mt_rand(1000, 9999); 
    
    return $prefixo . '-' . $sufixo; 
}

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: produtos.php");
    exit;
}

$acao = $_GET['acao'] ?? 'novo';
$produto_id_get = $_GET['id'] ?? null;
$nome = trim($_POST['nome'] ?? '');
$preco = str_replace(',', '.', $_POST['preco'] ?? '0');
$sku = trim($_POST['sku'] ?? '');

// CODIGO SKU AUTOMÁTICO
if (empty($sku)) {
    if ($acao == 'novo' || empty($produto_id_get)) {
        $sku = gerarSkuAutomatico($nome);
    } else {
        $sku = gerarSkuAutomatico($nome);
    }
}

$descricao = $_POST['descricao'] ?? '';
$cuidados = $_POST['cuidados'] ?? '';
$preco_promocional = str_replace(',', '.', $_POST['preco_promocional'] ?? '');
$categoria_id = !empty($_POST['categoria_id']) ? $_POST['categoria_id'] : null;
$marca_id = !empty($_POST['marca_id']) ? $_POST['marca_id'] : null;
$ativo = $_POST['ativo'] ?? 0;
$em_destaque_kit = isset($_POST['em_destaque_kit']) ? 1 : 0;
$preco_promocional = ($preco_promocional > 0) ? $preco_promocional : null;

if (empty($nome) || $preco <= 0) {
    $redirect_id = ($acao == 'editar') ? "&id=$produto_id_get" : '';
    header("Location: produto_formulario.php?erro=dados_invalidos" . $redirect_id);
    exit;
}

$tipo_estoque = $_POST['tipo_estoque'] ?? 'unico';
$variacoes_para_salvar = [];
$estoque_total_calculado = 0;

if ($tipo_estoque == 'unico') {
    
    $qtd = (int)($_POST['estoque_unico'] ?? 0);
    $variacoes_para_salvar[] = ['tamanho' => 'U', 'estoque' => $qtd];
    $estoque_total_calculado = $qtd;
} else {
    
    $variacoes = $_POST['variacoes'] ?? [];
    foreach ($variacoes as $v) {
        $tam = trim($v['tamanho'] ?? '');
        $qtd = (int)($v['estoque'] ?? 0);
        if (!empty($tam) && $qtd > 0) { 
            
            $variacoes_para_salvar[] = ['tamanho' => $tam, 'estoque' => $qtd];
            $estoque_total_calculado += $qtd;
        }
    }
}

$imagem_nome_db = null;
if (isset($_FILES['imagem_principal']) && $_FILES['imagem_principal']['error'] == UPLOAD_ERR_OK) {
    $ext = strtolower(pathinfo($_FILES['imagem_principal']['name'], PATHINFO_EXTENSION));
    $imagem_nome_db = uniqid('prod_') . '.' . $ext;
    move_uploaded_file($_FILES['imagem_principal']['tmp_name'], '../uploads/produtos/' . $imagem_nome_db);
}

$pdo->beginTransaction();

try {
    $produto_id = $produto_id_get;

    if ($acao == 'novo') {
        $sql = "INSERT INTO tb_produtos (nome, descricao, cuidados, preco, preco_promocional, sku, estoque, categoria_id, marca_id, ativo, em_destaque_kit, imagem_principal) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nome, $descricao, $cuidados, $preco, $preco_promocional, $sku, $estoque_total_calculado, $categoria_id, $marca_id, $ativo, $em_destaque_kit, $imagem_nome_db]);
        $produto_id = $pdo->lastInsertId();
    } elseif ($acao == 'editar' && $produto_id) {
        $sql = "UPDATE tb_produtos SET nome=?, descricao=?, cuidados=?, preco=?, preco_promocional=?, sku=?, estoque=?, categoria_id=?, marca_id=?, ativo=?, em_destaque_kit=?";
        $params = [$nome, $descricao, $cuidados, $preco, $preco_promocional, $sku, $estoque_total_calculado, $categoria_id, $marca_id, $ativo, $em_destaque_kit];
        
        if ($imagem_nome_db) {
            $sql .= ", imagem_principal=?";
            $params[] = $imagem_nome_db;
        }
        $sql .= " WHERE id=?";
        $params[] = $produto_id;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    
    if ($produto_id) {
        $pdo->prepare("DELETE FROM tb_produto_variacoes WHERE produto_id = ?")->execute([$produto_id]);
        $stmt_var = $pdo->prepare("INSERT INTO tb_produto_variacoes (produto_id, tamanho, estoque) VALUES (?, ?, ?)");
        foreach ($variacoes_para_salvar as $var) {
            $stmt_var->execute([$produto_id, $var['tamanho'], $var['estoque']]);
        }
    }

     if (isset($_FILES['galeria_imagens']) && !empty($_FILES['galeria_imagens']['name'][0])) {
        $galeria_files = $_FILES['galeria_imagens'];
        $caminho_galeria_base = '../uploads/produtos/galeria/';

        if (!is_dir($caminho_galeria_base)) {
            mkdir($caminho_galeria_base, 0755, true);
        }

        $sql_galeria = "INSERT INTO tb_produto_imagens (produto_id, caminho_imagem) VALUES (?, ?)";
        $stmt_galeria = $pdo->prepare($sql_galeria);

        foreach ($galeria_files['name'] as $key => $nome_ficheiro) {
            if ($galeria_files['error'][$key] == UPLOAD_ERR_OK) {
                $temp_ficheiro = $galeria_files['tmp_name'][$key];
                $extensao = strtolower(pathinfo($nome_ficheiro, PATHINFO_EXTENSION));
                $novo_nome_ficheiro = $produto_id . '_' . uniqid() . '.' . $extensao;
                $caminho_completo = $caminho_galeria_base . $novo_nome_ficheiro;

                if (move_uploaded_file($temp_ficheiro, $caminho_completo)) {
                    
                    $caminho_db = 'uploads/produtos/galeria/' . $novo_nome_ficheiro;
                    $stmt_galeria->execute([$produto_id, $caminho_db]);
                }
            }
        }
    }
    $pdo->commit();

    $redirect_url = ($acao == 'novo') ? "produto_formulario.php?id=$produto_id&sucesso=1" : "produtos.php?sucesso=1";
    header("Location: $redirect_url");
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    header("Location: produto_formulario.php?erro=erro_db&id=" . $produto_id_get);
    exit;
}
?>