<?php
session_start();
require_once 'config.php'; 


$stage = $_SESSION['recovery_stage'] ?? 'email'; 
$user_email_validated = $_SESSION['recovery_email'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($stage === 'email') {

        $email = trim($_POST['email_recuperacao'] ?? '');
        
        try {
            $stmt = $conn->prepare("SELECT UCIDUSER, UCUSERNAME FROM USUARIO WHERE UCEMAIL = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {

                $_SESSION['recovery_stage'] = 'reset';
                $_SESSION['recovery_email'] = $email;
                $_SESSION['recovery_user_id'] = $user['UCIDUSER'];
                header("Location: esqueci_senha.php");
                exit;
            } else {
                $_SESSION['recovery_error'] = "E-mail não encontrado. Verifique se digitou corretamente.";
            }
        } catch (PDOException $e) {
            $_SESSION['recovery_error'] = "Erro de sistema. Tente novamente.";
        }

    } elseif ($stage === 'reset') {
        //  REDEFINIÇÃO DA SENHA ---
        $senha = $_POST['nova_senha'] ?? '';
        $confirma = $_POST['confirma_senha'] ?? '';
        $user_id = $_SESSION['recovery_user_id'] ?? null;

        try {
            if (empty($user_id)) {
                throw new Exception("Sessão expirada. Volte para a primeira etapa.");
            }
            if (strlen($senha) < 6) { 
                throw new Exception("A senha deve ter pelo menos 6 caracteres.");
            }
            if ($senha !== $confirma) {
                throw new Exception("A nova senha e a confirmação não coincidem.");
            }

            $hash_senha = password_hash($senha, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE USUARIO SET UCPASSWORD = ? WHERE UCIDUSER = ?");
            $stmt->execute([$hash_senha, $user_id]);

            // Sucesso! Limpa a sessão e redireciona.
            unset($_SESSION['recovery_stage']);
            unset($_SESSION['recovery_email']);
            unset($_SESSION['recovery_user_id']);
            $_SESSION['login_success'] = "Sua senha foi redefinida com sucesso!";
            header("Location: pagina_principal.php");
            exit;

        } catch (Exception $e) {
            $_SESSION['recovery_error'] = $e->getMessage();
        }
    }
}
$stage = $_SESSION['recovery_stage'] ?? 'email'; 
$user_email_validated = $_SESSION['recovery_email'] ?? '';

if ($stage === 'email') {
    unset($_SESSION['recovery_email']);
    unset($_SESSION['recovery_user_id']);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Despachante Express - Recuperar Senha</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="login-container">
        
        <div class="login-left">
            <img src="img/logo-sem-fundo.png" alt="Logo Lindomar Despachante" class="logo">
            <h3>Recuperação de Senha</h3>
        </div>

        <form class="login-right" action="esqueci_senha.php" method="POST">
            <div class="form-section">
                <h2><?= $stage === 'email' ? 'Esqueci minha senha' : 'Redefinir Senha' ?></h2>
                <p style="text-align: center; margin-bottom: 20px; color: #bdc3c7;">
                    <?= $stage === 'email' 
                        ? 'Digite o e-mail associado à sua conta para prosseguir.' 
                        : 'E-mail validado: <strong>' . htmlspecialchars($user_email_validated) . '</strong>. Digite sua nova senha.' 
                    ?>
                </p>

                <?php if (isset($_SESSION['recovery_error'])): ?>
                    <div style="color: red; padding: 10px; margin-bottom: 15px; text-align: center; border: 1px solid red; border-radius: 5px;">
                        <?= htmlspecialchars($_SESSION['recovery_error']); ?>
                    </div>
                    <?php unset($_SESSION['recovery_error']); ?>
                <?php endif; ?>

                <?php if ($stage === 'email'): ?>
                    <div class="input-group">
                        <input type="email" name="email_recuperacao" placeholder="Digite seu e-mail" required>
                    </div>
                    
                    <div class="options">
                        <a href="index.php">Voltar para o Login</a>
                    </div>
                    <button type="submit">Validar E-mail</button>
                    
                <?php elseif ($stage === 'reset'): ?>
                    <div class="input-group">
                        <input type="password" name="nova_senha" placeholder="Nova Senha" required>
                    </div>
                    <div class="input-group">
                        <input type="password" name="confirma_senha" placeholder="Confirme a Nova Senha" required>
                    </div>

                    <div class="options">
                        <a href="esqueci_senha.php">Tentar outro e-mail</a>
                    </div>
                    <button type="submit">Redefinir Senha</button>
                    
                <?php endif; ?>

            </div>
        </form>
    </div>
</body>
</html>