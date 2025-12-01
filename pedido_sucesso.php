<?php
require 'templates/header.php';

if (!isset($_SESSION['ultimo_pedido_id'])) {
    header("Location: index.php");
    exit;
}

$pedido_id = $_SESSION['ultimo_pedido_id'];

unset($_SESSION['ultimo_pedido_id']);

?>
    <style>
        .success-container {
            text-align: center;
            padding: 80px 20px;
            margin: 40px auto;
            max-width: 700px;
            background-color: #1a1a1a;
            border: 1px solid #333;
            border-radius: 8px;
        }
        .success-container h1 {
            color: var(--color-accent);
            font-size: 2.5rem;
            margin-bottom: 15px;
        }
        .success-container p {
            font-size: 1.2rem;
            color: #ccc;
            line-height: 1.7;
        }
        .order-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: #fff;
            background-color: #333;
            padding: 10px 20px;
            border-radius: 6px;
            display: inline-block;
            margin: 25px 0;
        }
    </style>

    <main>
        <div class="container">
            <div class="success-container">
                <h1>Obrigado pela sua compra!</h1>
                <p>Seu pedido foi recebido com sucesso e está sendo processado.</p>
                <p>O número do seu pedido é:</p>
                <div class="order-number">
                    Pedido #<?php echo htmlspecialchars($pedido_id); ?>
                </div>
                <p>Você receberá atualizações sobre o status no seu e-mail.</p>
                <br>
                <a href="<?php echo BASE_URL; ?>/perfil.php" class="cta-button">Ver Meus Pedidos</a>
            </div>
        </div>
    </main>
   
<?php
    require 'templates/footer.php';
?>