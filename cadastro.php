<?php
// /cadastro.php

require 'bd/config.php';
require 'templates/header.php';
?>

<main>
    <div class="login-section"> <div class="form-container">
            <div class="form-header">
                <h1>Criar Conta</h1>
                <p>Já tem uma conta? <a href="login.php">Faça login</a></p>
            </div>

            <form id="cadastro-form" class="account-form" action="cadastro_processa.php" method="POST">
                
                <fieldset>
                    <legend>Dados Pessoais</legend>
                    <div class="form-grid two-cols">
                        <div class="form-group">
                            <label for="nome">Nome</label>
                            <input type="text" id="nome" name="nome" required>
                        </div>
                        <div class="form-group">
                            <label for="sobrenome">Sobrenome</label>
                            <input type="text" id="sobrenome" name="sobrenome" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="email">E-mail</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                </fieldset>

                <fieldset>
                    <legend>Endereço</legend>
                    <div class="form-group">
                        <label for="cep">CEP <span id="cep-status"></span></label>
                        <input type="text" id="cep" name="cep" placeholder="00000-000" maxlength="9" required>
                    </div>
                    <div class="form-group">
                        <label for="endereco">Endereço</label>
                        <input type="text" id="endereco" name="endereco" required readonly style="background-color: #333; color: #888;">
                    </div>
                    <div class="form-grid two-cols">
                        <div class="form-group">
                            <label for="numero">Número</label>
                            <input type="text" id="numero" name="numero" required>
                        </div>
                        <div class="form-group">
                            <label for="complemento">Complemento</label>
                            <input type="text" id="complemento" name="complemento">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="bairro">Bairro</label>
                        <input type="text" id="bairro" name="bairro" required readonly style="background-color: #333; color: #888;">
                    </div>
                    <div class="form-grid two-cols">
                        <div class="form-group">
                            <label for="cidade">Cidade</label>
                            <input type="text" id="cidade" name="cidade" required readonly style="background-color: #333; color: #888;">
                        </div>
                        <div class="form-group">
                            <label for="estado">Estado</label>
                            <input type="text" id="estado" name="estado" required readonly style="background-color: #333; color: #888;">
                        </div>
                    </div>
                </fieldset>
                <fieldset>
                    <legend>Segurança</legend>
                    <div class="form-group">
                        <label for="senha">Senha</label>
                        <input type="password" id="senha" name="senha" required>
                    </div>
                    <div class="form-group">
                        <label for="confirmar-senha">Confirmar Senha</label>
                        <input type="password" id="confirmar-senha" name="confirmar-senha" required>
                    </div>
                </fieldset>

                <button type="submit" class="submit-btn">Criar Minha Conta</button>
            </form>
        </div>
    </div>
</main>

<?php
    require 'templates/footer.php';
?>