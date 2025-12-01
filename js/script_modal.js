document.addEventListener('DOMContentLoaded', function() {
    const filterToggleButton = document.getElementById('filter-toggle-btn');
    const filterSidebar = document.querySelector('.filter-sidebar');
    const closeFilterButton = document.querySelector('.close-filter-btn');
    const pageOverlay = document.querySelector('.page-overlay');
    const priceSlider = document.getElementById('price-slider');
    const priceDisplay = document.getElementById('price-value');
    
    

    if (filterToggleButton && filterSidebar && closeFilterButton && pageOverlay) {
        
        const openFilter = () => {
            filterSidebar.classList.add('is-open');
            pageOverlay.classList.add('is-open');
        };

        const closeFilter = () => {
            filterSidebar.classList.remove('is-open');
            pageOverlay.classList.remove('is-open');
        };

        filterToggleButton.addEventListener('click', openFilter);
        closeFilterButton.addEventListener('click', closeFilter);
        pageOverlay.addEventListener('click', closeFilter);
    }

 
    //logica de filtro por preco
    if (priceSlider && priceDisplay) {
       const urlParams = new URLSearchParams(window.location.search);
       if (urlParams.has('preco_max')) {
        priceSlider.value = urlParams.get('preco_max');
        priceDisplay.textContent = 'R$' + urlParams.get('preco_max');
       }

       priceSlider.addEventListener('input', function() {
        priceDisplay.textContent = 'R$' + this.value;
       });
    }

    //logica de filtro por tamanho

    const sizeOptions = document.querySelectorAll('.size-option'); 
    let selectedSize = null;
    
    const urlParams = new URLSearchParams(window.location.search);
    if(urlParams.has('tamanho')){
        selectedSize = urlParams.get('tamanho');
        
        sizeOptions.forEach(opt => {
            if (opt.textContent.trim() === selectedSize) {
                opt.classList.add('selected');
            }
        });    
    }
    sizeOptions.forEach(option => {
        option.addEventListener('click', function() {
            sizeOptions.forEach(opt => opt.classList.remove('selected'));
            this.classList.add('selected');
            selectedSize = this.textContent.trim();
        });
    });

    // --- BOTÃO APLICAR FILTROS ---
    const applyBtn = document.querySelector('.done-btn');
    
    if (applyBtn) {
        applyBtn.addEventListener('click', function() {
            const currentUrl = new URL(window.location.href);
        
            if (priceSlider) {
                currentUrl.searchParams.set('preco_max', priceSlider.value);
            }
            if (selectedSize) {
                currentUrl.searchParams.set('tamanho', selectedSize);
            } else {
                currentUrl.searchParams.delete('tamanho'); 
            }

            window.location.href = currentUrl.toString();
        });
    }

    //logica botão-veja mais
    const productGrid = document.getElementById('product-grid');
    const loadMoreBtn = document.getElementById('load-more-btn');

    if (productGrid && loadMoreBtn) {
        
        const itemsToShowInitially = 8; 
        const itemsToLoadOnClick = 4;   

        const allProducts = Array.from(productGrid.getElementsByClassName('product-card'));
        
        allProducts.forEach((product, index) => {
            if (index >= itemsToShowInitially) {
                product.classList.add('hidden');
            }
        });

        const updateButtonVisibility = () => {
            const hiddenProducts = productGrid.querySelectorAll('.product-card.hidden');
            if (hiddenProducts.length === 0) {
                loadMoreBtn.style.display = 'none';
            }
        };

        loadMoreBtn.addEventListener('click', () => {
            const hiddenProducts = Array.from(productGrid.getElementsByClassName('hidden'));
            
            for (let i = 0; i < itemsToLoadOnClick && i < hiddenProducts.length; i++) {
                hiddenProducts[i].classList.remove('hidden');
            }
            
           
            updateButtonVisibility();
        });

        updateButtonVisibility();
    }


});