<?php
require 'bd/config.php';
require 'templates/header.php';

$token = $_GET['token'] ?? '';
$token_valido = false;

if (!empty($token)) {
    $stmt = $pdo->prepare("SELECT id FROM tb_client_users WHERE token_recuperacao = ? AND token_validade > NOW()");
    $stmt->execute([$token]);
    if ($stmt->fetch()) {
        $token_valido = true;
    }
}
?>

<main>
    <div class="login-section">
        <div class="form-container">
            <div class="form-header">
                <h1>Criar Nova Senha</h1>
            </div>

            <?php if ($token_valido): ?>
                <form class="account-form" action="redefinir_processa.php" method="POST">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    
                    <fieldset>
                        <div class="form-group">
                            <label for="nova_senha">Nova Senha</label>
                            <input type="password" id="nova_senha" name="nova_senha" required minlength="6">
                        </div>
                        <div class="form-group">
                            <label for="confirmar_senha">Confirmar Nova Senha</label>
                            <input type="password" id="confirmar_senha" name="confirmar_senha" required minlength="6">
                        </div>
                    </fieldset>

                    <button type="submit" class="submit-btn">Salvar Nova Senha</button>
                </form>
            <?php else: ?>
                <div style="text-align: center; padding: 20px; border: 1px solid #e64c4c; border-radius: 6px; color: #e64c4c;">
                    <p>Este link é inválido ou expirou.</p>
                    <a href="esqueci_senha.php" class="cta-button" style="margin-top: 15px; display: inline-block;">Solicitar Novo Link</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php require 'templates/footer.php'; ?>