document.addEventListener('DOMContentLoaded', function() {
    
  
    const header = document.querySelector('.main-header');
    if (header) {
        const topBarHeight = document.querySelector('.top-bar')?.offsetHeight || 0;
        window.addEventListener('scroll', function() {
            if (window.scrollY > topBarHeight) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        });
    }

    // --- LÓGICA DO CARROSSEL DE DESTAQUES ---
    const highlightsContainer = document.querySelector('.highlights-section .carousel-container');
    if (highlightsContainer) {
        const track = highlightsContainer.querySelector('.carousel-track');
        const prevButton = highlightsContainer.querySelector('.carousel-button.prev');
        const nextButton = highlightsContainer.querySelector('.carousel-button.next');
        
        if (track && prevButton && nextButton) { 

        const updateButtons = () => {
            const canScrollLeft = track.scrollLeft > 0;
            const canScrollRight = track.scrollWidth > track.clientWidth + track.scrollLeft + 1;
            prevButton.disabled = !canScrollLeft;
            nextButton.disabled = !canScrollRight;
        };

        nextButton.addEventListener('click', () => {
            const slideWidth = track.querySelector('.carousel-slide').offsetWidth;
            track.scrollBy({ left: slideWidth + 30, behavior: 'smooth' });
        });

        prevButton.addEventListener('click', () => {
            const slideWidth = track.querySelector('.carousel-slide').offsetWidth;
            track.scrollBy({ left: -(slideWidth + 30), behavior: 'smooth' });
        });

        track.addEventListener('scroll', updateButtons);
        updateButtons();
    }
}

    const openSearchBtn = document.getElementById('open-search-btn');
    const searchOverlay = document.getElementById('search-overlay');
    const closeSearchBtn = document.getElementById('close-search-btn');
    const searchInput = document.getElementById('search-input');

    
    if (openSearchBtn && searchOverlay && closeSearchBtn) {

        const openSearch = (event) => {
            event.preventDefault(); 
            searchOverlay.classList.add('is-open');
            setTimeout(() => searchInput.focus(), 400); 
        };

        const closeSearch = () => {
            searchOverlay.classList.remove('is-open');
        };

        openSearchBtn.addEventListener('click', openSearch);
        closeSearchBtn.addEventListener('click', closeSearch);

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && searchOverlay.classList.contains('is-open')) {
                closeSearch();
            }
        });
    }

    // --- LÓGICA DO MODAL DE BOAS-VINDAS ---
    const welcomeModal = document.getElementById('welcome-modal-overlay');
    
   if (welcomeModal) {
    const closeModalBtn = document.getElementById('close-modal-btn');
    const heroSection = document.querySelector('.hero-section');
    const modalVisto = sessionStorage.getItem('modalVisto');

    if (heroSection && !modalVisto) {
        
        const openModal = () => {
            welcomeModal.classList.add('is-open');
            sessionStorage.setItem('modalVisto', 'true');
        };

        const closeModal = () => {
            welcomeModal.classList.remove('is-open');
        };

        setTimeout(openModal, 3000);
        closeModalBtn.addEventListener('click', closeModal);
        welcomeModal.addEventListener('click', (event) => {
            if (event.target === welcomeModal) {
                closeModal();
            }
        });
    }
}

   // --- LÓGICA DA TELA DE ABERTURA (SPLASH SCREEN) ---
    const splashScreen = document.getElementById('splash-screen');
    const splashAudio = document.getElementById('splash-audio');

    if (splashScreen && splashAudio) {
        
        if (sessionStorage.getItem('splashJaVisto') === 'true') {
            
            splashScreen.classList.add('hidden');

        } else {
            
            splashAudio.play().catch(error => {
                console.log("Autoplay da música foi bloqueado pelo navegador.");
            });

            const splashDuration = 3000; 

            setTimeout(() => {
                splashScreen.classList.add('hidden');
            }, splashDuration);

            sessionStorage.setItem('splashJaVisto', 'true');
        }
    }

    // --- LÓGICA DO MODAL DE CARRINHO (SIDE CART) ---
    const openCartBtn = document.getElementById('open-cart-btn');
    const cartOverlay = document.getElementById('cart-overlay');
    const cartModal = document.getElementById('cart-modal');
    const closeCartBtn = document.getElementById('close-cart-btn');

    if (openCartBtn && cartOverlay && cartModal && closeCartBtn) {
        
        const openCart = (event) => {
            event.preventDefault();
            cartOverlay.classList.add('is-open');
            cartModal.classList.add('is-open');
        };

        const closeCart = () => {
            cartOverlay.classList.remove('is-open');
            cartModal.classList.remove('is-open');
        };

        openCartBtn.addEventListener('click', openCart);
        closeCartBtn.addEventListener('click', closeCart);
        cartOverlay.addEventListener('click', closeCart);
    }

    const accountLayout = document.querySelector('.account-layout');

    if (accountLayout) {
        const navLinks = accountLayout.querySelectorAll('.account-nav a');
        const contentPanels = accountLayout.querySelectorAll('.content-panel');

        navLinks.forEach(link => {
            link.addEventListener('click', function(event) {
               
                event.preventDefault();

                if(this.getAttribute('href') === '#') return;

                const targetId = this.getAttribute('href').substring(1);

                navLinks.forEach(navLink => navLink.classList.remove('active'));
                this.classList.add('active');

                contentPanels.forEach(panel => {
                    if (panel.id === targetId) {
                        panel.classList.add('active');
                    } else {
                        panel.classList.remove('active');
                    }
                });
            });
        });
    }

   // --- LÓGICA DO LIVE PREVIEW (FORMULÁRIO DE PRODUTO) ---
    const productForm = document.getElementById('admin-product-form');
    if (productForm) {
        console.log("Live Preview ATIVADO!"); 
        
        const formNome = document.getElementById('form-nome');
        const formPreco = document.getElementById('form-preco');
        const formImagem = document.getElementById('form-imagem');
        const previewNome = document.getElementById('preview-nome');
        const previewPreco = document.getElementById('preview-preco');
        const previewImagem = document.getElementById('preview-imagem');

        if(formNome) {
            formNome.addEventListener('input', () => {
                previewNome.textContent = formNome.value || 'Nome do Produto';
            });
        }
        if(formPreco) {
            formPreco.addEventListener('input', () => {
                previewPreco.textContent = formPreco.value ? 'R$ ' + formPreco.value : 'R$ 0,00';
            });
        }
        if(formImagem) {
            formImagem.addEventListener('change', () => {
                const file = formImagem.files[0];
                if (file) {
                    previewImagem.src = URL.createObjectURL(file);
                }
            });
        }
    }

    // --- LÓGICA DO FORMULÁRIO DE CATEGORIAS (admin_categorias.php) ---
    const showFormBtn = document.getElementById('show-category-form-btn');
    const categoryForm = document.getElementById('category-form-container');
    
    if (showFormBtn && categoryForm) {
        
        showFormBtn.addEventListener('click', () => {
            categoryForm.style.display = 'block'; 
            showFormBtn.style.display = 'none'; 
        });

    }

    const cepInput = document.getElementById('cep');

    // Só executa se o campo CEP existir nesta página
    if (cepInput) {
        const statusSpan = document.getElementById('cep-status');
        const enderecoInput = document.getElementById('endereco');
        const bairroInput = document.getElementById('bairro');
        const cidadeInput = document.getElementById('cidade');
        const estadoInput = document.getElementById('estado');
        const numeroInput = document.getElementById('numero'); // Campo para focar

        // Função para limpar os campos de endereço
        const clearAddressFields = (message = "") => {
            statusSpan.textContent = message;
            enderecoInput.value = "";
            bairroInput.value = "";
            cidadeInput.value = "";
            estadoInput.value = "";
        };

        cepInput.addEventListener('blur', () => {
            let cep = cepInput.value.replace(/\D/g, ''); 

            if (cep.length === 8) {
                statusSpan.textContent = " (carregando...)";
                clearAddressFields(" (carregando...)");

                // Faz a chamada à API ViaCEP
                fetch(`https://viacep.com.br/ws/${cep}/json/`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.erro) {
                            // CEP não encontrado
                            clearAddressFields(" (CEP não encontrado)");
                            cepInput.value = "";
                        } else {
                            // Sucesso! Preenche os campos
                            statusSpan.textContent = ""; 
                            enderecoInput.value = data.logradouro;
                            bairroInput.value = data.bairro;
                            cidadeInput.value = data.localidade;
                            estadoInput.value = data.uf;
                            numeroInput.focus(); 
                        }
                    })
                    .catch(error => {
                        console.error('Erro ao buscar CEP:', error);
                        clearAddressFields(" (Erro ao buscar CEP)");
                    });
            } else if (cep.length > 0) {
                clearAddressFields(" (CEP inválido)");
            } else {
                clearAddressFields();
            }
        });
    }
// --- LÓGICA DO EDITOR VISUAL (admin_editor.php) ---
    const editorLayout = document.querySelector('.editor-layout');
    
    if (editorLayout) {
        const iframe = document.getElementById('preview-iframe');
        const btnDesktop = document.getElementById('device-desktop');
        const btnMobile = document.getElementById('device-mobile');

        // Lógica para carregar a página correta no iframe 
        const pageLinks = document.querySelectorAll('.editor-page-list a');
        pageLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                pageLinks.forEach(l => l.classList.remove('active'));

                this.classList.add('active');
                const urlParams = new URLSearchParams(this.search);
                const pagina = urlParams.get('pagina');
                iframe.src = `<?php echo BASE_URL; ?>/${pagina}.php`;
            });
        });
    }
});


