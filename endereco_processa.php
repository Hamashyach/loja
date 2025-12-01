<?php
session_start();
require 'bd/config.php'; 

if (!isset($_SESSION['cliente_logado']) || !isset($_SESSION['cliente_id']) || $_SERVER["REQUEST_METHOD"] != "POST") {
    header('Location: login.php');
    exit;
}

$cliente_id = (int)$_SESSION['cliente_id'];
$acao = $_POST['acao'] ?? '';
$endereco_id = (int)($_POST['id'] ?? 0);


$tipo = $_POST['tipo'] ?? '';
$cep = $_POST['cep'] ?? '';
$endereco_rua = $_POST['endereco'] ?? '';
$numero = $_POST['numero'] ?? '';
$complemento = $_POST['complemento'] ?? '';
$bairro = $_POST['bairro'] ?? '';
$cidade = $_POST['cidade'] ?? '';
$estado = $_POST['estado'] ?? '';


if (empty($tipo) || empty($cep) || empty($endereco_rua) || empty($numero) || empty($bairro) || empty($cidade) || empty($estado)) {
    
    $redirect_id = $acao == 'editar' ? "&id=$endereco_id" : '';
    header("Location: endereco_formulario.php?erro=campos_obrigatorios$redirect_id");
    exit;
}

try {
    if ($acao == 'novo') {
        
        $sql = "INSERT INTO tb_client_addresses (cliente_id, tipo, cep, endereco, numero, complemento, bairro, cidade, estado) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$cliente_id, $tipo, $cep, $endereco_rua, $numero, $complemento, $bairro, $cidade, $estado]);
        $msg = 'sucesso=endereco_adicionado';

    } elseif ($acao == 'editar' && $endereco_id > 0) {
        
        $sql = "UPDATE tb_client_addresses SET tipo = ?, cep = ?, endereco = ?, numero = ?, complemento = ?, bairro = ?, cidade = ?, estado = ? 
                WHERE id = ? AND cliente_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$tipo, $cep, $endereco_rua, $numero, $complemento, $bairro, $cidade, $estado, $endereco_id, $cliente_id]);
        $msg = 'sucesso=endereco_atualizado';

    } else {
        header("Location: perfil.php?erro=acao_invalida#enderecos-panel");
        exit;
    }
    
    
    header("Location: perfil.php?$msg#enderecos-panel");
    exit;

} catch (PDOException $e) {
    
    header("Location: perfil.php?erro=db_endereco#enderecos-panel");
    exit;
}
?>