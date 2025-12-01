 <section class="pre-footer">
        <div class="container">
            <h2>Receba nossas Novidades</h2>
            <p>Seja o primeiro a saber sobre nossos lançamentos exclusivos, campanhas e eventos especiais.</p>
           <form class="newsletter-form ajax-form" action="newsletter_processa.php" method="POST" data-type="newsletter">
                <input type="email" name="email_inscrito" placeholder="Digite seu e-mail" required>
                <button type="submit">Inscrever-se</button>
            </form>
        </div>
    </section>

    <footer class="main-footer">
        <div class="container footer-grid">
            
            <div class="footer-brand">
                <a href="index.php" class="footer-logo"><img src="<?php echo BASE_URL; ?>/img/logo.png">
                <p class="slogan">Autenticidade além do estilo.</p>
                <div class="footer-social">
                    <a href="https://www.instagram.com/LionCompanyofc?igsh=MTh1b3I3NGI2M21hcQ%3D%3D&utm_source=qr" aria-label="Instagram"><img src="img/icones/instagram.png"></a>
                    <a href="https://api.whatsapp.com/send/?phone=5574999564070&text=Lion+Company+%EF%BF%BD&type=phone_number&app_absent=0" aria-label="whatsapp"><img src="img/icones/whatsapp.png"></a>
                    <a href="#" aria-label="facebook"><img src="img/icones/facebook.png"></a>
                </div>
            </div>

            <div class="footer-column">
                <h4>Navegação</h4>
                <ul>
                    <li><a href="/LionCompany/produtos.php?categoria=roupas">Roupas</a></li>
                    <li><a href="/LionCompany/produtos.php?categoria=perfumes">Perfumes</a></li>
                    <li><a href="/LionCompany/produtos.php?categoria=calcados">Calçados</a></li>
                    <li><a href="/LionCompany/produtos.php?categoria=acessorios">Acessórios</a></li>
                    <li><a href="/LionCompany/produtos.php?categoria=marcas">Marcas</a></li>
                </ul>
            </div>

            <div class="footer-column">
                <h4>Contato</h4>
                <div class="contato">
                
                        <p>Av. Adolfo Mofithinho 395<br>
                        Irecê, BA</p>
                    </div>
                    <div class="contatos">
                        <a href="mailto:contato@LionCompany.com">contato@LionCompany.com</a>
                        <a href="tel:+5515999999999">(74) 99956-4070</a>
                    </div>
                
            </div>

            <div class="footer-column">
                <h4>Pagamento Seguro</h4>
                <div class="footer-payment">
                    <img src="img/icones/visa.png">
                    <img src="img/icones/mastercard.png">
                    <img src="img/icones/Logo-pix.png">
                    
                </div>
            </div>

        </div>
        <div class="footer-bottom">
            <p>&copy; 2025 Lion Company. Todos os direitos reservados. <a href="rpvtecnologia.com.br">Design & Desenvolvimento por RPV Tecnologia.</a></p>
        </div>
    </footer>

    <div id="welcome-modal-overlay" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <img src="img/logo.png" alt="Lion Company Logo" class="modal-logo">
                <button id="close-modal-btn" class="close-modal-btn" aria-label="Fechar">&times;</button>
            </div>
            <form class="modal-form ajax-form" action="newsletter_processa.php" method="POST" data-type="newsletter_modal">
                <h2><?php echo htmlspecialchars($config['modal_titulo'] ?? ''); ?></h2>
                <p><?php echo htmlspecialchars($config['modal_subtitulo'] ?? ''); ?></p>
                <form class="modal-form">
                    <input type="text" name="nome" placeholder="Seu Nome" required>
                    <input type="email" name="email" placeholder="Seu E-mail" required>
                    <button type="submit" class="modal-submit-btn">Adquirir</button>
                </form>
                <p class="modal-confirm-text">Receberá um email para confirmar sua inscrição</p>
            </div>
        </div>
    </div>

    <div id="modal-informacao-publico" class="modal-overlay">
        <div class="modal-content" style="max-width: 450px;">
            <div class="modal-header">
                <h3 id="modal-info-titulo-publico" style="font-family: var(--font-body); font-size: 1.5rem; margin: 0; color: #fff;">Aviso</h3>
                <button id="modal-info-btn-fechar" class="close-modal-btn" aria-label="Fechar">&times;</button>
            </div>
            <div class="modal-body" style="background: var(--color-background); text-align: center; padding: 30px;">
                <p id="modal-info-mensagem-publico" style="color: #ccc; font-size: 1.1rem; line-height: 1.6; margin: 0;">Mensagem aqui.</p>
            </div>
            <div class="modal-footer" style="background: #1a1a1a; padding: 20px; text-align: center;">
                <button id="modal-info-btn-ok-publico" class="modal-submit-btn" style="width: 100%; margin: 0;">OK</button>
            </div>
        </div>
    </div>

    <div id="toast" class="toast-notification"></div>

    <script src="<?php echo BASE_URL; ?>/js/script.js"></script>
    <script src="<?php echo BASE_URL; ?>/js/script-product-details.js"></script>
    <script src="<?php echo BASE_URL; ?>/js/script-carousel-product.js"></script>
    <script src="<?php echo BASE_URL; ?>/js/script_menu.js"></script>
    <script src="<?php echo BASE_URL; ?>/js/script_modal.js"></script>
    
<script>
document.addEventListener('DOMContentLoaded', function() { 

    // --- LÓGICA DE MODAL DE AVISO ---
    const modalInfo = document.getElementById('modal-informacao-publico');
    
    if (modalInfo) {

        const modalTitulo = document.getElementById('modal-info-titulo-publico');
        const modalMensagem = document.getElementById('modal-info-mensagem-publico');
        const btnOk = document.getElementById('modal-info-btn-ok-publico');
        const btnFechar = document.getElementById('modal-info-btn-fechar');
      
        function abrirModalInfo(mensagem, titulo = 'Aviso') {
            if (modalInfo && modalTitulo && modalMensagem) {
                modalTitulo.textContent = titulo;
                modalMensagem.textContent = mensagem;
                modalInfo.classList.add('is-open'); 
            }
        }
        function fecharModalInfo() {
            if (modalInfo) {
                modalInfo.classList.remove('is-open');
            }
        }

        if(btnOk) btnOk.addEventListener('click', fecharModalInfo);
        if(btnFechar) btnFechar.addEventListener('click', fecharModalInfo);
        modalInfo.addEventListener('click', function(e) {
            if (e.target === modalInfo) {
                fecharModalInfo();
            }
        });

        // Lógica de URL Params (erro/sucesso)
        const urlParams = new URLSearchParams(window.location.search);
        let mensagemErro = '';
        let mensagemSucesso = '';

        if (urlParams.has('erro')) {
            const erroType = urlParams.get('erro');
            if (erroType === 'nome_vazio') mensagemErro = 'Nome e sobrenome não podem ficar vazios.';
            if (erroType === 'senhas_nao_coincidem') mensagemErro = 'A nova senha e a confirmação não são iguais.';
            if (erroType === 'senha_atual_invalida') mensagemErro = 'A sua senha atual está incorreta.';
            if (erroType === '1') mensagemErro = 'E-mail ou senha inválidos. Tente novamente.';
            if (erroType === 'sem_estoque') mensagemErro = 'Desculpe, um item no seu carrinho ficou sem estoque.';
            if (erroType === 'carrinho_vazio') mensagemErro = 'O seu carrinho está vazio.';
            if (erroType === 'login_necessario') mensagemErro = 'Você precisa de fazer login para finalizar a compra.';
            if (erroType === 'dados_invalidos') mensagemErro = 'Os dados do produto são inválidos.';
            if (erroType === 'sem_endereco') mensagemErro = 'Você precisa de cadastrar um endereço no seu perfil antes de finalizar a compra.';
            if (erroType === 'email_inexistente') mensagemErro = 'Este e-mail não possui cadastro em nossa loja. Verifique a digitação ou crie uma conta.';
        }
        
        if (urlParams.has('sucesso')) {
            const sucessoType = urlParams.get('sucesso');
            //perfil
            if (sucessoType === 'dados') mensagemSucesso = 'Os seus dados foram atualizados com sucesso!';
            if (sucessoType === 'senha') mensagemSucesso = 'A sua senha foi alterada com sucesso!';
            //cadastro
            if (sucessoType === '1') mensagemSucesso = 'Cadastro realizado com sucesso! Faça o seu login.';
            if (sucessoType === 'inscricao') mensagemSucesso = 'Inscrição realizada com sucesso! Bem-vindo(a)!';

            //Sucesso Newsletter
            if (sucessoType === 'inscricao') mensagemSucesso = 'Inscrição realizada com sucesso! Bem-vindo(a)!';

            if (type === 'newsletter_modal') {const welcomeModal = document.getElementById('welcome-modal-overlay');
                if (welcomeModal) {
                        welcomeModal.classList.remove('is-open');
                        localStorage.setItem('lion_modal_visto', 'true'); // Não mostra mais
                    }
                }

           //mudanca de senha
            if (sucessoType === 'senha_redefinida') {
                mensagemSucesso = 'Sua senha foi redefinida com sucesso! Acesse sua conta com a nova senha.';
            }
        }

        if (urlParams.has('aviso')) {
            const avisoType = urlParams.get('aviso');
            if (avisoType === 'login_necessario') mensagemErro = 'Você precisa de fazer login para aceder a esta página.';
            if (avisoType === 'erro_sessao') mensagemErro = 'Ocorreu um erro com a sua sessão. Por favor, faça login novamente.';
            if (avisoType === 'email_ja_inscrito') mensagemErro = 'Você já está inscrito(a) na nossa newsletter! Obrigado.';
            if (avisoType === 'email_enviado') mensagemErro = 'Se o e-mail estiver cadastrado, você receberá um link para redefinir sua senha em instantes.';
        }

        if (mensagemErro) {
            abrirModalInfo(mensagemErro, 'Ocorreu um Erro');
        } else if (mensagemSucesso) {
            abrirModalInfo(mensagemSucesso, 'Sucesso!');
        }
    } 
    
    // --- FUNÇÃO TOAST ---
    function showToast(message) {
        const toast = document.getElementById("toast");
        if(toast) {
            toast.textContent = message;
            toast.className = "toast-notification show";
            setTimeout(function(){ toast.className = toast.className.replace("show", ""); }, 3000);
        }
    }

    // --- LÓGICA CARRINHO E FAVORITOS ---
    const forms = document.querySelectorAll('.ajax-form');

    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault(); 

            const formData = new FormData(form);
            const actionUrl = form.getAttribute('action');
            const type = form.getAttribute('data-type'); 
            const btn = form.querySelector('button');
            const img = btn ? btn.querySelector('img') : null; 

            if(btn) {
                btn.style.transform = "scale(0.9)";
                setTimeout(() => btn.style.transform = "scale(1)", 150);
            }

            fetch(actionUrl, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest' 
                },
                body: formData
            })
            .then(response => {
                const contentType = response.headers.get("content-type");
                if (contentType && contentType.indexOf("application/json") !== -1) {
                    return response.json();
                } else {
                    throw new Error("Resposta não é JSON"); 
                }
            })
            .then(data => {
                if (data.erro_login) {
                    window.location.href = 'login.php?aviso=login_necessario';
                    return;
                }

                if (data.sucesso) {
                    showToast(data.mensagem); 
                    
                    if (type === 'favorito') {
                        if (data.acao === 'adicionado') {
                            if(img) img.style.filter = "brightness(0.8) sepia(1) hue-rotate(-50deg) saturate(5)"; 
                            if(btn) btn.setAttribute('title', 'Remover dos Favoritos');
                        } else {
                            if(img) img.style.filter = ""; 
                            if(btn) btn.setAttribute('title', 'Adicionar aos Favoritos');
                            
                            if (window.location.href.includes('perfil.php')) {
                                const item = form.closest('.produto-item');
                                if(item) item.remove();
                            }
                        }
                    }
                    if (type === 'carrinho') {
                        const cartLink = document.querySelector('.header-icons a[aria-label="Carrinho de Compras"]');
                        
                        if(cartLink) {
                            let cartBadge = cartLink.querySelector('span');

                            if (data.nova_contagem > 0) {
                                if (cartBadge) {
                                    cartBadge.textContent = data.nova_contagem; 
                                } else {
                                    const newBadge = document.createElement('span');
                                    newBadge.style.cssText = 'position: absolute; top: -10px; right: -10px; background-color: #bb9a65; color: #000; border-radius: 50%; width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: bold;';
                                    newBadge.textContent = data.nova_contagem;
                                    cartLink.appendChild(newBadge);
                                    cartLink.style.position = 'relative'; 
                                }
                            } else if (cartBadge) {
                                cartBadge.remove(); 
                            }
                        }
                    }
                } else {
                    showToast(data.mensagem || "Ocorreu um erro.");
                }
            })
            .catch(error => {
                console.error('Erro:', error);
            });
        });
    }); 

});
</script>
</body>
</html>