<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Despachante Express - Login</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php
    session_start();
    ?>
    <div class="login-container">
    
    <div class="login-left">
        <img src="img/logo-sem-fundo.png" alt="Logo Lindomar Despachante" class="logo">
        <h3>Olá, bem-vindo!</h3>
    </div>

    <form class="login-right" action="login.php" method="POST">
        <div class="form-section" id="login-section">
            <h2>Login</h2>

            <?php
                if (isset($_SESSION['login_error'])): ?>
                    <div style="color: red; padding: 5px; margin-top: 10px; text-align: center;">
                        <?= htmlspecialchars($_SESSION['login_error']); ?>
                    </div>
                <?php 
                    unset($_SESSION['login_error']); 
                endif;
                ?>
            <div class="input-group">
                <input type="email" name="login" placeholder="e-mail" required>
            </div>
            <div class="input-group">
                <input type="password" name="senha" placeholder="Senha" required>
            </div>
            <div class="options">
                <a href="esqueci_senha.php">Esqueci minha senha</a>
            </div>
            <button type="submit">Login</button>
        </div>
    </form>

    <script>
        const loginSection = document.getElementById('login-section');
        const cadastroSection = document.getElementById('cadastro-section');
        const showCadastroBtn = document.getElementById('show-cadastro-btn');
        const showLoginBtn = document.getElementById('show-login');
        
        showCadastroBtn.addEventListener('click', (event) => {
            event.preventDefault();
            loginSection.style.display = 'none';
            cadastroSection.style.display = 'block';
        });

        showLoginBtn.addEventListener('click', () => {
            cadastroSection.style.display = 'none';
            loginSection.style.display = 'block';
        });
    </script>
</body>
</html>