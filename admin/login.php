<?php
session_start();
// Se o admin já estiver logado, redireciona para o painel
if (isset($_SESSION['admin_logado'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Painel Admin</title>
    <link rel="stylesheet" href="css/admin_style.css">
</head>
<body>

    <div class="login-wrapper">
        <div class="login-container">
            <h1>Painel Admin</h1>

            <?php
            // Mostra mensagem de erro
            if (isset($_GET['erro'])) {
                $msg = $_GET['erro'] == '1' ? 'Usuário ou senha inválidos.' : 'Acesso negado. Faça login.';
                echo '<div class="admin-alert error">' . htmlspecialchars($msg) . '</div>';
            }
            ?>

            <form action="processa/admin_login_processa.php" method="POST">
                <div class="form-group">
                    <label for="email">E-mail</label>
                    <input type="email" id="email" name="email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="senha">Senha</label>
                    <input type="password" id="senha" name="senha" class="form-control" required>
                </div>
                <button type="submit" class="admin-btn">Entrar</button>
            </form>
        </div>
    </div>

</body>
</html>