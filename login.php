<?php

require 'bd/config.php';
require 'templates/header.php';


?>

    <main>
        <div class="login-section">
            <div class="form-container">
                <div class="form-header">
                    <h1>Fazer Login</h1>
                    <p>Não tem uma conta? <a href="cadastro.php">Criar conta</a></p>
                </div>

                <form id="login-form" class="account-form" action="login_processa.php" method="POST">
                    <fieldset>
                        <div class="form-group">
                            <label for="email">E-mail</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="senha">Senha</label>
                            <input type="password" id="senha" name="senha" required>
                            <div style="text-align: right; margin-top: 5px;">
                                <a href="esqueci_senha.php" style="font-size: 0.85rem; color: #888;">Esqueci minha senha</a>
                            </div>
                        </div>

                    </fieldset>

                    <button type="submit" class="submit-btn">Entrar</button>
                </form>
            </div>
        </div>
    </main>

<?php
    require 'templates/footer.php';
?>