<?php
require 'bd/config.php';
require 'templates/header.php';
?>

<main>
    <div class="login-section">
        <div class="form-container">
            <div class="form-header">
                <h1>Recuperar Senha</h1>
                <p>Digite seu e-mail para receber o link de redefinição.</p>
            </div>

            <form class="account-form" action="recuperar_processa.php" method="POST">
                <fieldset>
                    <div class="form-group">
                        <label for="email">E-mail cadastrado</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                </fieldset>

                <button type="submit" class="submit-btn">Enviar Link</button>
                
                <div style="text-align: center; margin-top: 20px;">
                    <a href="login.php" style="color: #888;">Voltar para Login</a>
                </div>
            </form>
        </div>
    </div>
</main>

<?php require 'templates/footer.php'; ?>