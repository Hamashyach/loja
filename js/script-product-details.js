


function selectSize(element) {
    
    document.querySelectorAll('.size-option').forEach(el => el.classList.remove('selected'));   
    element.classList.add('selected');

    const tamanho = element.getAttribute('data-value');
    document.getElementById('selected-size').value = tamanho;
    
    const estoque = parseInt(element.getAttribute('data-stock'));
    const stockDisplay = document.getElementById('stock-display');
    const qtyInput = document.getElementById('quantity-input');
    const btnCompra = document.getElementById('btn-compra');

    if (estoque > 0) {
        stockDisplay.textContent = `(${estoque} disponíveis)`;
        stockDisplay.style.color = '#888';
        qtyInput.setAttribute('max', estoque);
        qtyInput.value = 1; 
        btnCompra.disabled = false;
        btnCompra.textContent = "Adicionar ao Carrinho";
        btnCompra.style.opacity = "1";
        btnCompra.style.cursor = "pointer";
    } else {
        stockDisplay.textContent = "(Esgotado neste tamanho)";
        stockDisplay.style.color = "#e64c4c";
        qtyInput.setAttribute('max', 0);
        qtyInput.value = 0;
        btnCompra.disabled = true;
        btnCompra.textContent = "Esgotado";
        btnCompra.style.opacity = "0.5";
        btnCompra.style.cursor = "not-allowed";
    }
      
    const sizeError = document.getElementById('size-error');
    if(sizeError) sizeError.style.display = 'none';
}

function updateQty(change) {
    const input = document.getElementById('quantity-input');
    let val = parseInt(input.value);
    const max = parseInt(input.getAttribute('max')) || 1; 
    
    let newVal = val + change;
    
    if (newVal >= 1 && newVal <= max) {
        input.value = newVal;
    }
}

  function toggleAccordion(header) {
        const content = header.nextElementSibling;
        const icon = header.querySelector('span');
        
        if (content.style.maxHeight) {
            content.style.maxHeight = null; 
            if(icon) icon.textContent = "+";
            header.classList.remove('active');
        } else {
            content.style.maxHeight = content.scrollHeight + "px"; 
            if(icon) icon.textContent = "-";
            header.classList.add('active');
        }
    }

function changeImage(src) {
    const mainImage = document.getElementById('main-product-image');
    if(mainImage) mainImage.src = src;
 
}

document.addEventListener('DOMContentLoaded', function(){

    const pdpGrid = document.querySelector('.pdp-grid');
    
    if (pdpGrid) {
        
        const mainImage = document.getElementById('main-product-image');
        const thumbnails = document.querySelectorAll('.thumbnail');
        
        thumbnails.forEach(thumb => {
            thumb.addEventListener('click', function() {
                thumbnails.forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                if(mainImage) mainImage.src = this.src;
            });
        });

        
        const formCompra = document.getElementById('form-compra');
        if (formCompra) {
            formCompra.addEventListener('submit', function(e) {
                const sizeInput = document.getElementById('selected-size');
                const sizeError = document.getElementById('size-error');
                
                
                if (sizeInput && sizeInput.value === "") {
                    e.preventDefault(); 
                    if(sizeError) sizeError.style.display = 'block';
                }
            });
        }
    }
});