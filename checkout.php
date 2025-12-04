<?php
require 'bd/config.php';
require 'templates/header.php';

// Verificações
if (!isset($_SESSION['cliente_logado']) || !isset($_SESSION['cliente_id'])) {
    echo "<script>window.location.href='login.php?aviso=login_necessario&redirect=checkout.php';</script>";
    exit;
}
if (empty($_SESSION['carrinho'])) {
    echo "<script>window.location.href='produtos.php?erro=carrinho_vazio';</script>";
    exit;
}

$cliente_id = (int)$_SESSION['cliente_id'];

// Busca Endereços
try {
    $stmt = $pdo->prepare("SELECT * FROM tb_client_addresses WHERE cliente_id = ?");
    $stmt->execute([$cliente_id]);
    $enderecos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $enderecos = []; }

// Total Produtos
$total_produtos = 0;
foreach ($_SESSION['carrinho'] as $id => $item) {
    $stmt_p = $pdo->prepare("SELECT preco, preco_promocional FROM tb_produtos WHERE id = ?");
    $stmt_p->execute([$id]);
    $prod = $stmt_p->fetch();
    if($prod){
        $preco = ($prod['preco_promocional'] > 0) ? $prod['preco_promocional'] : $prod['preco'];
        $total_produtos += $preco * $item['quantidade'];
    }
}
?>

<main>
    <div class="container" style="padding: 40px 20px;">
        <div class="page-header"><h1>Finalizar Pedido</h1></div>

        <form id="form-checkout">
            <div class="checkout-grid" style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 40px;">
                
                <div class="checkout-left">
                    <h3 style="border-bottom: 1px solid #333; padding-bottom: 10px; margin-bottom: 20px; color: #bb9a65;">1. Endereço de Entrega</h3>
                    <?php if (empty($enderecos)): ?>
                        <p>Você não tem endereços cadastrados.</p> 
                        <a href="endereco_formulario.php" class="cta-button">Cadastrar Endereço</a>
                    <?php else: ?>
                        <div class="address-list">
                            <?php foreach ($enderecos as $idx => $end): ?>
                                <label class="address-card" style="display:block; background:#1a1a1a; padding:15px; margin-bottom:10px; border:1px solid #333; border-radius:6px; cursor:pointer;">
                                    <div style="display:flex; align-items:center; gap:10px;">
                                        <input type="radio" name="endereco_id" value="<?php echo $end['id']; ?>" data-cep="<?php echo $end['cep']; ?>" <?php echo ($idx === 0) ? 'checked' : ''; ?> onchange="calcularFrete()">
                                        <div>
                                            <strong><?php echo htmlspecialchars($end['tipo']); ?></strong><br>
                                            <span style="color:#ccc; font-size:0.9rem;">
                                                <?php echo htmlspecialchars($end['endereco']) . ', ' . $end['numero']; ?><br>
                                                CEP: <?php echo htmlspecialchars($end['cep']); ?>
                                            </span>
                                        </div>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <h3 style="border-bottom: 1px solid #333; padding-bottom: 10px; margin: 30px 0 20px 0; color: #bb9a65;">2. Opções de Envio</h3>
                    <div id="frete-options">
                        <p style="color:#888;">Selecione um endereço para calcular o frete.</p>
                    </div>
                </div>

                <div class="checkout-right">
                    <div class="cart-summary" style="background:#1a1a1a; padding:30px; border-radius:8px; border:1px solid #333; position: sticky; top: 20px;">
                        <h3 style="margin-bottom:20px;">Resumo do Pedido</h3>
                        
                        <div class="summary-row" style="display:flex; justify-content:space-between; margin-bottom:10px; color:#ccc;">
                            <span>Subtotal</span> <span>R$ <?php echo number_format($total_produtos, 2, ',', '.'); ?></span>
                        </div>
                        <div class="summary-row" style="display:flex; justify-content:space-between; margin-bottom:10px; color:#ccc;">
                            <span>Frete</span> <span id="valor-frete-display">R$ 0,00</span>
                        </div>
                        <div class="summary-total" style="display:flex; justify-content:space-between; margin-top:20px; padding-top:20px; border-top:1px solid #444; font-size:1.3rem; font-weight:bold; color:#fff;">
                            <span>Total</span> <span id="total-geral-display" style="color:#bb9a65;">R$ <?php echo number_format($total_produtos, 2, ',', '.'); ?></span>
                        </div>
                        
                        <input type="hidden" name="frete_servico_id" id="input-frete-id">
                        <input type="hidden" name="valor_frete" id="input-valor-frete" value="0">
                        <input type="hidden" name="tipo_frete" id="input-tipo-frete">
                        <input type="hidden" name="prazo_frete" id="input-prazo-frete">

                        <div style="margin-top:30px;">
                            <button type="button" onclick="irParaPagamento()" id="btn-pagar" class="cta-button" style="font-family:Roboto; width:100%; padding:15px; font-size:1.1rem; cursor:pointer; background: #b98e48; color: #ffffffff; border:none; border-radius: 6px; font-weight: bold; transition: 0.3s;">
                                Ir para pagamento
                            </button>
                            <p style="font-size: 0.8rem; color: #888; text-align: center; margin-top: 10px;">
                                Ambiente seguro Mercado Pago (Pix, Crédito, Débito)
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</main>

<script>
const totalProdutos = <?php echo $total_produtos; ?>;
let totalComFrete = totalProdutos;

// Lógica de Frete
function calcularFrete() {
    const enderecoSelecionado = document.querySelector('input[name="endereco_id"]:checked');
    if (!enderecoSelecionado) return;
    
    const cep = enderecoSelecionado.getAttribute('data-cep');
    const divFrete = document.getElementById('frete-options');
    
    divFrete.innerHTML = '<p style="color:#bb9a65;">Calculando opções de envio...</p>';
    
    const formData = new FormData();
    formData.append('cep', cep);

    fetch('api/frete_calculo.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        divFrete.innerHTML = '';
        if (data.erro) { 
            divFrete.innerHTML = `<p style="color:#e74c3c;">${data.msg}</p>`; 
        } else {
            let html = '';
            data.opcoes.forEach((opcao, i) => {
                const checked = i === 0 ? 'checked' : '';
                if(i === 0) atualizarTotalDados(opcao.valor, opcao.prazo, opcao.nome, opcao.id);
                
                html += `
                <label class="address-card" style="display:flex; justify-content:space-between; align-items:center; background:#1a1a1a; border:1px solid #333; padding:15px; margin-bottom:10px; border-radius:6px; cursor:pointer;">
                    <div style="display:flex; gap:15px; align-items:center;">
                        <input type="radio" name="opcao_frete" 
                            value="${opcao.nome}" 
                            data-id="${opcao.id}" 
                            data-valor="${opcao.valor}" 
                            data-prazo="${opcao.prazo}" 
                            onchange="atualizarTotal(this)" 
                            ${checked} 
                            style="accent-color:#bb9a65;">
                        
                        ${opcao.foto ? `<img src="${opcao.foto}" style="height:25px; border-radius:4px;">` : ''}
                        
                        <div>
                            <strong style="color:#fff;">${opcao.nome}</strong><br>
                            <span style="color:#ccc; font-size:0.85rem;">Entrega em até ${opcao.prazo} dias úteis</span>
                        </div>
                    </div>
                    <div style="font-weight:bold; color:#bb9a65;">R$ ${opcao.preco_formatado}</div>
                </label>`;
            });
            divFrete.innerHTML = html;
        }
    });
}

function atualizarTotalDados(valor, prazo, tipo, id) {
    document.getElementById('input-valor-frete').value = valor;
    document.getElementById('input-tipo-frete').value = tipo;
    document.getElementById('input-prazo-frete').value = prazo;
    document.getElementById('input-frete-id').value = id;
    
    document.getElementById('valor-frete-display').textContent = 'R$ ' + valor.toLocaleString('pt-BR', {minimumFractionDigits:2});
    
    totalComFrete = totalProdutos + valor;
    document.getElementById('total-geral-display').textContent = 'R$ ' + totalComFrete.toLocaleString('pt-BR', {minimumFractionDigits:2});
}

function atualizarTotal(el) {
    atualizarTotalDados(
        parseFloat(el.getAttribute('data-valor')), 
        el.getAttribute('data-prazo'), 
        el.value, 
        el.getAttribute('data-id')
    );
}

// Botão de Pagamento
function irParaPagamento() {
    const btn = document.getElementById('btn-pagar');
    const freteId = document.getElementById('input-frete-id').value;

    if(!freteId) {
        alert("Por favor, selecione uma opção de frete antes de continuar.");
        return;
    }

    btn.disabled = true;
    btn.innerHTML = 'Processando...';

    const formElement = document.getElementById('form-checkout');
    const dataToSend = new FormData(formElement);

    fetch('checkout_processa.php', {
        method: 'POST',
        body: dataToSend
    })
    .then(response => response.json())
    .then(result => {
        if(result.sucesso && result.redirect) {
            window.location.href = result.redirect; 
        } else {
            alert("Erro: " + (result.msg || "Erro desconhecido"));
            btn.disabled = false;
            btn.innerHTML = 'Ir para pagamento';
        }
    })
    .catch(err => {
        console.error(err);
        alert("Erro de conexão.");
        btn.disabled = false;
        btn.innerHTML = 'Ir para pagamento';
    });
}

document.addEventListener('DOMContentLoaded', () => {
    calcularFrete();
});
</script>

<?php require 'templates/footer.php'; ?>