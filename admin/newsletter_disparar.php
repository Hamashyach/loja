<?php
require_once 'auth_check.php';
require_once '../bd/config.php';

// Carrega PHPMailer (Ajuste o caminho conforme onde você salvou a pasta PHPMailer)
require '../PHPMailer/Exception.php';
require '../PHPMailer/PHPMailer.php';
require '../PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: inscritos.php");
    exit;
}

// 1. Prepara o Upload da Imagem (se houver)
$caminho_anexo = null;
if (isset($_FILES['imagem_campanha']) && $_FILES['imagem_campanha']['error'] == UPLOAD_ERR_OK) {
    $ext = pathinfo($_FILES['imagem_campanha']['name'], PATHINFO_EXTENSION);
    $nome_img = 'campanha_' . time() . '.' . $ext;
    $caminho_anexo = '../uploads/temp/' . $nome_img; // Pasta temporária
    
    if (!is_dir('../uploads/temp/')) mkdir('../uploads/temp/', 0755, true);
    move_uploaded_file($_FILES['imagem_campanha']['tmp_name'], $caminho_anexo);
}

// 2. Busca Inscritos
$stmt = $pdo->query("SELECT email, nome FROM tb_inscritos");
$destinatarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($destinatarios)) {
    die("Nenhum inscrito para enviar.");
}

// 3. Configura PHPMailer
$mail = new PHPMailer(true);
$assunto = $_POST['assunto'];
$mensagem_texto = $_POST['mensagem'];

// Aumenta tempo de execução para não travar no meio (0 = infinito)
set_time_limit(0); 

try {
    // --- CONFIGURAÇÃO SMTP (IGUAL AO RECUPERAR SENHA) ---
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'seu.email@gmail.com'; // <--- COLOQUE SEU EMAIL
    $mail->Password   = 'sua_senha_de_app';    // <--- COLOQUE SUA SENHA DE APP
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->CharSet    = 'UTF-8';
    $mail->setFrom('seu.email@gmail.com', 'Lion Company');

    // Configura conteúdo
    $mail->isHTML(true);
    $mail->Subject = $assunto;

    // Se tiver imagem, anexa
    if ($caminho_anexo) {
        $mail->addAttachment($caminho_anexo, 'Novidade Lion Company');
    }

    // Loop de envio (Um por um para personalização)
    $enviados = 0;
    foreach ($destinatarios as $dest) {
        $mail->clearAddresses(); // Limpa destinatário anterior
        $mail->addAddress($dest['email'], $dest['nome'] ?? '');
        
        // Personaliza corpo (Ex: Olá Fulano)
        $nome_cliente = $dest['nome'] ? $dest['nome'] : 'Cliente';
        
        $corpo = "<div style='font-family: Arial; color: #333;'>";
        $corpo .= "<h2>Olá, $nome_cliente!</h2>";
        $corpo .= "<p>" . nl2br($mensagem_texto) . "</p>";
        $corpo .= "<br><hr><small>Enviado por Lion Company</small>";
        $corpo .= "</div>";
        
        $mail->Body = $corpo;
        $mail->AltBody = strip_tags($corpo);

        try {
            $mail->send();
            $enviados++;
        } catch (Exception $e) {
            // Se falhar um, continua para o próximo
            continue;
        }
        
        // Pausa pequena para não ser bloqueado pelo SMTP (opcional)
        usleep(500000); // 0.5 segundos
    }

    // Limpeza da imagem temporária
    if ($caminho_anexo && file_exists($caminho_anexo)) {
        unlink($caminho_anexo);
    }

    echo "<script>alert('Campanha enviada para $enviados inscritos com sucesso!'); window.location.href='inscritos.php';</script>";

} catch (Exception $e) {
    die("Erro geral no envio: " . $mail->ErrorInfo);
}
?>