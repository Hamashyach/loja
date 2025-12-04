<?php
require_once 'auth_check.php';
require_once '../bd/config.php';

function gerarSkuAutomatico($nome) {
    $prefixo = substr(preg_replace('/[^a-zA-Z]/', '', $nome), 0, 3);
    $prefixo = strtoupper($prefixo);
    if (empty($prefixo)) $prefixo = 'PROD';
    return $prefixo . '-' . mt_rand(1000, 9999); 
}

// Verifica e cria pasta de variações
$dir_variacoes = '../uploads/produtos/variacoes/';
if (!is_dir($dir_variacoes)) mkdir($dir_variacoes, 0755, true);

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: produtos.php");
    exit;
}

$acao = $_GET['acao'] ?? 'novo';
$produto_id_get = $_GET['id'] ?? null;

// --- DADOS BÁSICOS ---
$nome = trim($_POST['nome'] ?? '');
$preco = str_replace(',', '.', $_POST['preco'] ?? '0');
$sku = trim($_POST['sku'] ?? '');
if (empty($sku)) $sku = gerarSkuAutomatico($nome);

$descricao = $_POST['descricao'] ?? '';
$cuidados = $_POST['cuidados'] ?? '';
$preco_promocional = str_replace(',', '.', $_POST['preco_promocional'] ?? '');
$preco_promocional = ($preco_promocional > 0) ? $preco_promocional : null;

$categoria_id = !empty($_POST['categoria_id']) ? $_POST['categoria_id'] : null;
$marca_id = !empty($_POST['marca_id']) ? $_POST['marca_id'] : null;
$ativo = $_POST['ativo'] ?? 0;
$em_destaque_kit = isset($_POST['em_destaque_kit']) ? 1 : 0;

$peso_kg = str_replace(',', '.', $_POST['peso_kg'] ?? '0.300');
$altura_cm = (int)($_POST['altura_cm'] ?? 5);
$largura_cm = (int)($_POST['largura_cm'] ?? 20);
$comprimento_cm = (int)($_POST['comprimento_cm'] ?? 20);

// --- PROCESSAR VARIAÇÕES E IMAGENS ---
$variacoes_post = $_POST['variacoes'] ?? [];
$variacoes_files = $_FILES['variacoes'] ?? []; // Array complexo do PHP
$variacoes_para_salvar = [];
$estoque_total_calculado = 0;

foreach ($variacoes_post as $key => $v) {
    $cor = trim($v['cor'] ?? 'Padrão');
    $tam = trim($v['tamanho'] ?? 'U');
    $qtd = (int)($v['estoque'] ?? 0);
    $img_final = $v['imagem_antiga'] ?? null; // Começa com a antiga

    // Verifica se houve upload de nova imagem para esta variação
    if (isset($variacoes_files['name'][$key]['imagem']) && $variacoes_files['error'][$key]['imagem'] == UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($variacoes_files['name'][$key]['imagem'], PATHINFO_EXTENSION));
        $novo_nome = 'var_' . uniqid() . '.' . $ext;
        
        if (move_uploaded_file($variacoes_files['tmp_name'][$key]['imagem'], $dir_variacoes . $novo_nome)) {
            $img_final = $novo_nome; // Atualiza para a nova
        }
    }

    if (!empty($tam)) {
        $variacoes_para_salvar[] = [
            'cor_modelo' => $cor,
            'tamanho' => $tam,
            'estoque' => $qtd,
            'imagem' => $img_final
        ];
        $estoque_total_calculado += $qtd;
    }
}

// --- UPLOAD IMAGEM PRINCIPAL ---
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
        $sql = "INSERT INTO tb_produtos (nome, descricao, cuidados, preco, preco_promocional, sku, estoque, categoria_id, marca_id, ativo, em_destaque_kit, peso_kg, altura_cm, largura_cm, comprimento_cm, imagem_principal) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nome, $descricao, $cuidados, $preco, $preco_promocional, $sku, $estoque_total_calculado, $categoria_id, $marca_id, $ativo, $em_destaque_kit, $peso_kg, $altura_cm, $largura_cm, $comprimento_cm, $imagem_nome_db]);
        $produto_id = $pdo->lastInsertId();
    } elseif ($acao == 'editar' && $produto_id) {
        $sql = "UPDATE tb_produtos SET nome=?, descricao=?, cuidados=?, preco=?, preco_promocional=?, sku=?, estoque=?, categoria_id=?, marca_id=?, ativo=?, em_destaque_kit=?, peso_kg=?, altura_cm=?, largura_cm=?, comprimento_cm=?";
        $params = [$nome, $descricao, $cuidados, $preco, $preco_promocional, $sku, $estoque_total_calculado, $categoria_id, $marca_id, $ativo, $em_destaque_kit, $peso_kg, $altura_cm, $largura_cm, $comprimento_cm];
        
        if ($imagem_nome_db) {
            $sql .= ", imagem_principal=?";
            $params[] = $imagem_nome_db;
        }
        $sql .= " WHERE id=?";
        $params[] = $produto_id;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    // --- SALVAR VARIAÇÕES ---
    // Remove antigas para evitar duplicidade de chaves e reinserir atualizadas
    if ($produto_id) {
        $pdo->prepare("DELETE FROM tb_produto_variacoes WHERE produto_id = ?")->execute([$produto_id]);
        
        $stmt_var = $pdo->prepare("INSERT INTO tb_produto_variacoes (produto_id, cor_modelo, tamanho, estoque, imagem) VALUES (?, ?, ?, ?, ?)");
        
        foreach ($variacoes_para_salvar as $var) {
            $stmt_var->execute([
                $produto_id, 
                $var['cor_modelo'], 
                $var['tamanho'], 
                $var['estoque'],
                $var['imagem']
            ]);
        }
    }

    // --- UPLOAD GALERIA ---
    if (isset($_FILES['galeria_imagens']) && !empty($_FILES['galeria_imagens']['name'][0])) {
        $galeria_files = $_FILES['galeria_imagens'];
        $caminho_base = '../uploads/produtos/galeria/';
        if (!is_dir($caminho_base)) mkdir($caminho_base, 0755, true);

        $stmt_galeria = $pdo->prepare("INSERT INTO tb_produto_imagens (produto_id, caminho_imagem) VALUES (?, ?)");

        foreach ($galeria_files['name'] as $key => $nome_original) {
            if ($galeria_files['error'][$key] == UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($nome_original, PATHINFO_EXTENSION));
                $novo_nome = $produto_id . '_' . uniqid() . '.' . $ext;
                
                if (move_uploaded_file($galeria_files['tmp_name'][$key], $caminho_base . $novo_nome)) {
                    $stmt_galeria->execute([$produto_id, 'uploads/produtos/galeria/' . $novo_nome]);
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
    // Código 1062 = Duplicidade (ex: Azul M duas vezes)
    $erro_tipo = ($e->errorInfo[1] == 1062) ? 'variacao_duplicada' : 'erro_db';
    header("Location: produto_formulario.php?erro=$erro_tipo&id=" . $produto_id_get);
    exit;
}
?>