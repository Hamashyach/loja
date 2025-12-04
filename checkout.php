<?php
require 'bd/config.php';
require 'templates/header.php';

if (!isset($_SESSION['cliente_logado']) || !isset($_SESSION['cliente_id'])) {
    echo "<script>window.location.href='login.php?aviso=login_necessario&redirect=checkout.php';</script>";
    exit;
}
if (empty($_SESSION['carrinho'])) {
    echo "<script>window.location.href='produtos.php?erro=carrinho_vazio';</script>";
    exit;
}

$cliente_id = (int)$_SESSION['cliente_id'];

try {
    $stmt = $pdo->prepare("SELECT * FROM tb_client_addresses WHERE cliente_id = ?");
    $stmt->execute([$cliente_id]);
    $enderecos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $enderecos = []; }

try {
    $stmt_email = $pdo->prepare("SELECT cliente_email FROM tb_client_users WHERE id = ?");
    $stmt_email->execute([$cliente_id]);
    $email_cliente = $stmt_email->fetchColumn();
} catch (Exception $e) { $email_cliente = "cliente@teste.com"; }

$total_produtos = 0;
foreach ($_SESSION['carrinho'] as $id => $item) {
    $stmt_p = $pdo->prepare("SELECT preco, preco_promocional FROM tb_produtos WHERE id = ?");
    $stmt_p->execute([$id]);
    $prod = $stmt_p->fetch();
    $preco = ($prod['preco_promocional'] > 0) ? $prod['preco_promocional'] : $prod['preco'];
    $total_produtos += $preco * $item['quantidade'];
}
?>

<script src="https://sdk.mercadopago.com/js/v2"></script>

<main>
    <div class="container" style="padding: 40px 20px;">
        <div class="page-header"><h1>Finalizar Compra</h1></div>

        <form id="form-checkout">
            <div class="checkout-grid" style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 40px;">
                
                <div class="checkout-left">
                    <h3 style="border-bottom: 1px solid #333; padding-bottom: 10px; margin-bottom: 20px; color: #bb9a65;">1. Endereço</h3>
                    <?php if (empty($enderecos)): ?>
                        <p>Sem endereços.</p> <a href="endereco_formulario.php" class="cta-button">Cadastrar</a>
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

                    <h3 style="border-bottom: 1px solid #333; padding-bottom: 10px; margin: 30px 0 20px 0; color: #bb9a65;">2. Frete</h3>
                    <div id="frete-options"><p style="color:#888;">Carregando...</p></div>
                </div>

                <div class="checkout-right">
                    <div class="cart-summary" style="background:#1a1a1a; padding:30px; border-radius:8px; border:1px solid #333;">
                        <h3 style="margin-bottom:20px;">Resumo</h3>
                        <div class="summary-row" style="display:flex; justify-content:space-between; margin-bottom:10px; color:#ccc;">
                            <span>Produtos</span> <span>R$ <?php echo number_format($total_produtos, 2, ',', '.'); ?></span>
                        </div>
                        <div class="summary-row" style="display:flex; justify-content:space-between; margin-bottom:10px; color:#ccc;">
                            <span>Frete</span> <span id="valor-frete-display">R$ 0,00</span>
                        </div>
                        <div class="summary-total" style="display:flex; justify-content:space-between; margin-top:20px; padding-top:20px; border-top:1px solid #444; font-size:1.3rem; font-weight:bold; color:#fff;">
                            <span>Total</span> <span id="total-geral-display" style="color:#bb9a65;">R$ <?php echo number_format($total_produtos, 2, ',', '.'); ?></span>
                        </div>
                        
                        <input type="hidden" name="frete_servico_id" id="input-frete-id">
                        <input type="hidden" name="valor_produtos" id="input-valor-produtos" value="<?php echo $total_produtos; ?>">
                        <input type="hidden" name="valor_frete" id="input-valor-frete" value="0">
                        <input type="hidden" name="tipo_frete" id="input-tipo-frete">
                        <input type="hidden" name="prazo_frete" id="input-prazo-frete">

                        <input type="hidden" name="token" id="mp-token">
                        <input type="hidden" name="issuer" id="mp-issuer">
                        <input type="hidden" name="paymentMethodId" id="mp-paymentMethodId">
                        <input type="hidden" name="installments" id="mp-installments">

                        <div style="margin-top:30px;">
                            <div id="paymentBrick_container"></div>
                            <div id="statusScreenBrick_container"></div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</main>

<script>
// VARIAVEIS
const totalProdutos = parseFloat(document.getElementById('input-valor-produtos').value);
let totalComFrete = totalProdutos;

// INICIALIZA MERCADO PAGO
const mp = new MercadoPago("APP_USR-ab4fdc11-2373-4caa-aefd-acf9429f1557", { locale: 'pt-BR' });
const bricksBuilder = mp.bricks();

// 1. RENDERIZA PAGAMENTO
async function renderPaymentBrick() {
    const settings = {
        initialization: {
            amount: totalComFrete,
            payer: { email: '<?php echo $email_cliente; ?>' },
        },
        customization: {
            paymentMethods: {
                ticket: "all", bankTransfer: "all", creditCard: "all", debitCard: "all",
            },
            visual: {
                style: {
                    theme: 'dark',
                    customVariables: {
                        formBackgroundColor: '#1a1a1a',
                        baseColor: '#bb9a65',
                        inputBackgroundColor: '#2a2a2a',
                        inputTextColor: '#ffffff', 
                    }
                }
            },
        },
        callbacks: {
            onReady: () => {},
            onSubmit: ({ selectedPaymentMethod, formData }) => {
                return new Promise((resolve, reject) => {
                    // Preenche hiddens
                    document.getElementById('mp-paymentMethodId').value = selectedPaymentMethod;
                    if (formData) {
                        document.getElementById('mp-token').value = formData.token || '';
                        document.getElementById('mp-issuer').value = formData.issuer_id || '';
                        document.getElementById('mp-installments').value = formData.installments || 1;
                    }

                    // Prepara dados para envio AJAX
                    const formElement = document.getElementById('form-checkout');
                    const dataToSend = new FormData(formElement);

                    // Envia para o PHP sem recarregar a página
                    fetch('checkout_processa.php', {
                        method: 'POST',
                        body: dataToSend
                    })
                    .then(response => response.json())
                    .then(result => {
                        if(result.sucesso) {
                            // SUCESSO! Mostra a tela de Status
                            document.getElementById('paymentBrick_container').style.display = 'none'; // Esconde formulario
                            renderStatusScreenBrick(result.payment_id); // Mostra resultado (Pix/Boleto)
                            resolve();
                        } else {
                            alert("Erro: " + (result.msg || "Erro desconhecido"));
                            reject();
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        alert("Erro de conexão ao processar pagamento.");
                        reject();
                    });
                });
            },
            onError: (error) => { console.error(error); },
        },
    };
    
    document.getElementById('paymentBrick_container').innerHTML = '';
    window.paymentBrickController = await bricksBuilder.create('payment', 'paymentBrick_container', settings);
}

// 2. RENDERIZA TELA DE STATUS (O Pulo do Gato para Pix/Boleto na tela)
async function renderStatusScreenBrick(paymentId) {
    const settings = {
        initialization: {
            paymentId: paymentId, // ID recebido do PHP
        },
        customization: {
            visual: {
                style: {
                    theme: 'dark', // Mantém tema escuro
                    customVariables: {
                        baseColor: '#bb9a65',
                        successColor: '#2ecc71',
                        warningColor: '#f1c40f',
                        errorColor: '#e74c3c'
                    }
                }
            },
            backUrls: {
                return: '<?php echo BASE_URL; ?>/perfil.php' // Botão "Voltar"
            }
        },
        callbacks: {
            onReady: () => {
                // Scroll suave até o status
                document.getElementById('statusScreenBrick_container').scrollIntoView({ behavior: 'smooth' });
            },
            onError: (error) => { console.error(error); },
        },
    };
    window.statusScreenBrickController = await bricksBuilder.create('statusScreen', 'statusScreenBrick_container', settings);
}

// Lógica de Frete (Mantida igual)
function calcularFrete() {
    const enderecoSelecionado = document.querySelector('input[name="endereco_id"]:checked');
    if (!enderecoSelecionado) return;
    const cep = enderecoSelecionado.getAttribute('data-cep');
    const divFrete = document.getElementById('frete-options');
    divFrete.innerHTML = '<p style="color:#bb9a65;">Calculando...</p>';
    const formData = new FormData();
    formData.append('cep', cep);

    fetch('api/frete_calculo.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        divFrete.innerHTML = '';
        if (data.erro) { divFrete.innerHTML = `<p style="color:red;">${data.msg}</p>`; }
        else {
            let html = '';
            data.opcoes.forEach((opcao, i) => {
                const checked = i === 0 ? 'checked' : '';
                if(i===0) atualizarTotalDados(opcao.valor, opcao.prazo, opcao.nome, opcao.id);
                html += `<label class="address-card" style="display:flex; justify-content:space-between; align-items:center; background:#1a1a1a; border:1px solid #333; padding:15px; margin-bottom:10px; border-radius:6px; cursor:pointer;">
                    <div style="display:flex; gap:15px; align-items:center;">
                        <input type="radio" name="opcao_frete" value="${opcao.nome}" data-id="${opcao.id}" data-valor="${opcao.valor}" data-prazo="${opcao.prazo}" onchange="atualizarTotal(this)" ${checked} style="accent-color:#bb9a65;">
                        ${opcao.foto ? `<img src="${opcao.foto}" style="height:25px;">` : ''}
                        <div><strong style="color:#fff;">${opcao.nome}</strong><br><span style="color:#ccc; font-size:0.85rem;">${opcao.prazo} dias</span></div>
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
    if (window.paymentBrickController) window.paymentBrickController.update({ amount: totalComFrete });
}

function atualizarTotal(el) {
    atualizarTotalDados(parseFloat(el.getAttribute('data-valor')), el.getAttribute('data-prazo'), el.value, el.getAttribute('data-id'));
}

document.addEventListener('DOMContentLoaded', () => {
    calcularFrete();
    renderPaymentBrick();
});
</script>

<?php require 'templates/footer.php'; ?>