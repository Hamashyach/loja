<?php
// Namespaces do PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

session_start();
require 'bd/config.php';

// Carregamento do PHPMailer
require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);

    try {
        $stmt = $pdo->prepare("SELECT id, cliente_nome FROM tb_client_users WHERE cliente_email = ?");
        $stmt->execute([$email]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($usuario) {
            $token = bin2hex(random_bytes(32));
            $validade = date('Y-m-d H:i:s', strtotime('+1 hour'));
            $stmt_update = $pdo->prepare("UPDATE tb_client_users SET token_recuperacao = ?, token_validade = ? WHERE id = ?");
            $stmt_update->execute([$token, $validade, $usuario['id']]);

            $link = BASE_URL . "/redefinir_senha.php?token=" . $token;
            $mail = new PHPMailer(true);

            try {
                // --- CONFIGURAÇÕES  GMAIL ---
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'seu.email@gmail.com'; //EMAIL
                $mail->Password   = 'sua_senha_de_app';    // SENHA DE APP
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;
                $mail->CharSet    = 'UTF-8';

                $mail->setFrom('seu.email@gmail.com', 'Lion Company');
                $mail->addAddress($email, $usuario['cliente_nome']);

                $mail->isHTML(true);
                $mail->Subject = 'Redefinir Senha - Lion Company';
                
                $corpoEmail = "
                <div style='font-family: sans-serif; color: #333; padding: 20px;'>
                    <h2>Olá, " . htmlspecialchars($usuario['cliente_nome']) . "</h2>
                    <p>Recebemos um pedido para redefinir sua senha.</p>
                    <p><a href='$link' style='background: #bb9a65; color: #000; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>CLIQUE AQUI PARA REDEFINIR</a></p>
                    <p>Ou cole: $link</p>
                </div>";

                $mail->Body = $corpoEmail;
                $mail->AltBody = "Link para redefinir: $link";

                $mail->send();

                header("Location: login.php?aviso=email_enviado");
                exit;

            } catch (Exception $e) {
                die("Erro ao enviar e-mail: {$mail->ErrorInfo}");
            }

        } else {
            
            header("Location: esqueci_senha.php?erro=email_inexistente"); 
            exit;
        }

    } catch (PDOException $e) {
        die("Erro no sistema: " . $e->getMessage());
    }
}
header("Location: esqueci_senha.php");
exit;
?>