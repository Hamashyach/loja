<?php
require 'bd/config.php';
require 'templates/header.php';

// 1. Segurança: Login e Carrinho
if (!isset($_SESSION['cliente_logado']) || !isset($_SESSION['cliente_id'])) {
    echo "<script>window.location.href='login.php?aviso=login_necessario&redirect=checkout.php';</script>";
    exit;
}

if (empty($_SESSION['carrinho'])) {
    echo "<script>window.location.href='produtos.php?erro=carrinho_vazio';</script>";
    exit;
}

$cliente_id = (int)$_SESSION['cliente_id'];

// 2. Buscar Endereços do Cliente
try {
    $stmt = $pdo->prepare("SELECT * FROM tb_client_addresses WHERE cliente_id = ?");
    $stmt->execute([$cliente_id]);
    $enderecos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $enderecos = [];
}

// 3. Calcular Totais Iniciais
$total_produtos = 0;
$peso_total = 0; // Estimativa: 0.5kg por item
foreach ($_SESSION['carrinho'] as $id => $item) {
    // Precisamos buscar o preço atualizado do banco para segurança
    $stmt_p = $pdo->prepare("SELECT preco, preco_promocional FROM tb_produtos WHERE id = ?");
    $stmt_p->execute([$id]);
    $prod = $stmt_p->fetch();
    
    $preco = ($prod['preco_promocional'] > 0) ? $prod['preco_promocional'] : $prod['preco'];
    $total_produtos += $preco * $item['quantidade'];
    $peso_total += 0.5 * $item['quantidade']; 
}
?>

<main>
    <div class="container" style="padding: 40px 20px;">
        <div class="page-header">
            <h1>Finalizar Compra</h1>
        </div>

        <form action="checkout_processa.php" method="POST" id="form-checkout">
            <div class="checkout-grid" style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 40px;">
                
                <div class="checkout-left">
                    
                    <h3 style="border-bottom: 1px solid #333; padding-bottom: 10px; margin-bottom: 20px; color: #bb9a65;">1. Endereço de Entrega</h3>
                    
                    <?php if (empty($enderecos)): ?>
                        <div class="alert-box" style="background: #333; padding: 20px; border-radius: 6px;">
                            <p>Você não tem endereços cadastrados.</p>
                            <a href="endereco_formulario.php?redirect=checkout.php" class="cta-button">Cadastrar Endereço</a>
                        </div>
                    <?php else: ?>
                        <div class="address-list">
                            <?php foreach ($enderecos as $idx => $end): ?>
                                <label class="address-card" style="display: block; background: #1a1a1a; border: 1px solid #333; padding: 15px; margin-bottom: 10px; border-radius: 6px; cursor: pointer;">
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <input type="radio" name="endereco_id" value="<?php echo $end['id']; ?>" data-cep="<?php echo $end['cep']; ?>" <?php echo ($idx === 0) ? 'checked' : ''; ?> required onchange="calcularFrete()">
                                        <div>
                                            <strong><?php echo htmlspecialchars($end['tipo']); ?></strong><br>
                                            <span style="color: #ccc; font-size: 0.9rem;">
                                                <?php echo htmlspecialchars($end['endereco']) . ', ' . $end['numero']; ?><br>
                                                <?php echo htmlspecialchars($end['cidade']) . ' - ' . $end['estado']; ?><br>
                                                CEP: <?php echo htmlspecialchars($end['cep']); ?>
                                            </span>
                                        </div>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                            <a href="endereco_formulario.php?redirect=checkout.php" style="color: #bb9a65; font-size: 0.9rem;">+ Adicionar outro endereço</a>
                        </div>
                    <?php endif; ?>

                    <h3 style="border-bottom: 1px solid #333; padding-bottom: 10px; margin: 30px 0 20px 0; color: #bb9a65;">2. Opção de Frete</h3>
                    <div id="frete-options">
                        <p style="color: #888;">Selecione um endereço para calcular o frete.</p>
                    </div>

                </div>

                <div class="checkout-right">
                    <div class="cart-summary" style="background: #1a1a1a; padding: 30px; border-radius: 8px; border: 1px solid #333; position: sticky; top: 20px;">
                        <h3 style="margin-bottom: 20px;">Resumo do Pedido</h3>
                        
                        <div class="summary-row" style="display: flex; justify-content: space-between; margin-bottom: 10px; color: #ccc;">
                            <span>Produtos</span>
                            <span>R$ <?php echo number_format($total_produtos, 2, ',', '.'); ?></span>
                        </div>

                        <div class="summary-row" style="display: flex; justify-content: space-between; margin-bottom: 10px; color: #ccc;">
                            <span>Frete</span>
                            <span id="valor-frete-display">R$ 0,00</span>
                        </div>

                        <div class="summary-total" style="display: flex; justify-content: space-between; margin-top: 20px; padding-top: 20px; border-top: 1px solid #444; font-size: 1.3rem; font-weight: bold; color: #fff;">
                            <span>Total</span>
                            <span id="total-geral-display" style="color: #bb9a65;">R$ <?php echo number_format($total_produtos, 2, ',', '.'); ?></span>
                        </div>
                        
                        <input type="hidden" name="valor_produtos" id="input-valor-produtos" value="<?php echo $total_produtos; ?>">
                        <input type="hidden" name="valor_frete" id="input-valor-frete" value="0">
                        <input type="hidden" name="tipo_frete" id="input-tipo-frete" value="">
                        <input type="hidden" name="prazo_frete" id="input-prazo-frete" value="">

                        <div style="margin-top: 30px;">
                            <p style="font-size: 0.9rem; color: #888; margin-bottom: 10px;">Pagamento seguro via Mercado Pago:</p>
                            <button type="submit" class="cta-button" id="cta-button-pag" style="width: 100%;" id="btn-finalizar" disabled>
                                Pagar com Mercado Pago
                            </button>
                            <div style="display: flex; gap: 10px; justify-content: center; align-items: center; margin-top: 10px; opacity: 0.7;">
                                <img src="img/icones/visa.png" height="40">
                                <img src="img/icones/mastercard.png" height="20">
                                <img src="img/icones/Logo-pix.png" height="20">
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </form>
    </div>
</main>

<script>
// Variáveis globais
const totalProdutos = parseFloat(document.getElementById('input-valor-produtos').value);

function calcularFrete() {
    const enderecoSelecionado = document.querySelector('input[name="endereco_id"]:checked');
    if (!enderecoSelecionado) return;

    const cep = enderecoSelecionado.getAttribute('data-cep');
    const divFrete = document.getElementById('frete-options');
    
    divFrete.innerHTML = '<p style="color: #bb9a65;">Calculando frete...</p>';

    // Chama a API de frete criada
    const formData = new FormData();
    formData.append('cep', cep);
    formData.append('peso', <?php echo $peso_total; ?>);
    formData.append('valor', <?php echo $total_produtos; ?>);

    fetch('api/frete_calculo.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.erro) {
            divFrete.innerHTML = '<p style="color: red;">Erro ao calcular frete.</p>';
        } else {
            let html = '';
            
            // Opção PAC
            html += `
                <label class="address-card" style="display: flex; justify-content: space-between; align-items: center; background: #1a1a1a; border: 1px solid #333; padding: 15px; margin-bottom: 10px; border-radius: 6px; cursor: pointer;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <input type="radio" name="opcao_frete" value="PAC" 
                               data-valor="${data.pac.valor}" 
                               data-prazo="${data.pac.prazo}" 
                               onchange="atualizarTotal(this)" required>
                        <div>
                            <strong>PAC</strong><br>
                            <span style="color: #ccc; font-size: 0.9rem;">${data.pac.prazo} dias úteis</span>
                        </div>
                    </div>
                    <div style="font-weight: bold; color: #fff;">R$ ${data.pac.preco}</div>
                </label>
            `;

            // Opção SEDEX
            html += `
                <label class="address-card" style="display: flex; justify-content: space-between; align-items: center; background: #1a1a1a; border: 1px solid #333; padding: 15px; margin-bottom: 10px; border-radius: 6px; cursor: pointer;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <input type="radio" name="opcao_frete" value="SEDEX" 
                               data-valor="${data.sedex.valor}" 
                               data-prazo="${data.sedex.prazo}" 
                               onchange="atualizarTotal(this)" required>
                        <div>
                            <strong>SEDEX</strong><br>
                            <span style="color: #ccc; font-size: 0.9rem;">${data.sedex.prazo} dias úteis</span>
                        </div>
                    </div>
                    <div style="font-weight: bold; color: #fff;">R$ ${data.sedex.preco}</div>
                </label>
            `;
            
            divFrete.innerHTML = html;
        }
    })
    .catch(err => {
        console.error(err);
        divFrete.innerHTML = '<p style="color: red;">Erro de conexão.</p>';
    });
}

function atualizarTotal(radioFrete) {
    const valorFrete = parseFloat(radioFrete.getAttribute('data-valor'));
    const prazoFrete = radioFrete.getAttribute('data-prazo');
    const tipoFrete = radioFrete.value;

    // Atualiza inputs hidden para envio
    document.getElementById('input-valor-frete').value = valorFrete;
    document.getElementById('input-tipo-frete').value = tipoFrete;
    document.getElementById('input-prazo-frete').value = prazoFrete;

    // Atualiza visual
    document.getElementById('valor-frete-display').textContent = 'R$ ' + valorFrete.toLocaleString('pt-BR', {minimumFractionDigits: 2});
    
    const totalGeral = totalProdutos + valorFrete;
    document.getElementById('total-geral-display').textContent = 'R$ ' + totalGeral.toLocaleString('pt-BR', {minimumFractionDigits: 2});

    // Habilita botão de pagar
    const btnFinalizar = document.getElementById('btn-finalizar');
    btnFinalizar.disabled = false;
    btnFinalizar.style.opacity = 1;
    btnFinalizar.style.cursor = 'pointer';
}

// Calcula frete inicial se já tiver endereço marcado
document.addEventListener('DOMContentLoaded', function() {
    calcularFrete();
});
</script>

<?php require 'templates/footer.php'; ?>