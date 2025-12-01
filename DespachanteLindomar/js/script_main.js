
document.addEventListener('DOMContentLoaded', function() {

    // ---  SISTEMA DE ABAS (TABS) ---
    const allTabContainers = document.querySelectorAll('.tabs');
    allTabContainers.forEach(container => {
        const tabButtons = container.querySelectorAll(':scope > .tab-buttons .tab-button');
        const tabPanes = container.querySelectorAll(':scope > .tab-content > .tab-pane, :scope > .tab-pane');
        tabButtons.forEach(button => {
            button.addEventListener('click', () => {
                if (button.disabled) return;
                const targetId = button.dataset.tab;
                tabButtons.forEach(btn => btn.classList.remove('active'));
                tabPanes.forEach(pane => pane.classList.remove('active'));
                button.classList.add('active');
                const targetPane = document.getElementById(targetId);
                if (targetPane) {
                    targetPane.classList.add('active');
                }
            });
        });
    });

    // ---  CONTROLE DOS BOTÕES DA BARRA DE FERRAMENTAS ---
    const btnEditar = document.getElementById('btnEditar');
    const btnVisualizar = document.getElementById('btnVisualizar');
    const btnExcluir = document.getElementById('btnExcluir');
    const btnVisualizarFicha = document.getElementById('btnVisualizarFicha');
  
    
    let selectedId = null;
    const currentPage = window.location.pathname.split('/').pop();

    // Função para habilitar/desabilitar todos os botões de uma vez
    function setButtonsState(enabled) {
        if (enabled) {
            if (btnEditar) btnEditar.classList.remove('disabled');
            if (btnVisualizar) btnVisualizar.classList.remove('disabled');
            if (btnExcluir) btnExcluir.classList.remove('disabled');
            if (btnVisualizarFicha) btnVisualizarFicha.classList.remove('disabled');
          
        } else {
            if (btnEditar) btnEditar.classList.add('disabled');
            if (btnVisualizar) btnVisualizar.classList.add('disabled');
            if (btnExcluir) btnExcluir.classList.add('disabled');
            if (btnVisualizarFicha) btnVisualizarFicha.classList.add('disabled');
           
        }
    }

    // Evento de clique na tabela
    document.querySelectorAll('.data-table tbody').forEach(tbody => {
        tbody.addEventListener('click', function(event) {
            const row = event.target.closest('tr.clickable-row');
            if (!row) return;

            const isSelected = row.classList.contains('selected');
            document.querySelectorAll('tr.clickable-row').forEach(r => r.classList.remove('selected'));

            if (isSelected) {
                // Se o usuário clicou em uma linha já selecionada (para desmarcar)
                selectedId = null;
                setButtonsState(false); 
            } else {
                // Se o usuário selecionou uma nova linha
                row.classList.add('selected');
                selectedId = row.dataset.id;
                setButtonsState(true); 
            }
        });
    });

    // --- AÇÕES DOS BOTÕES ---
    if (btnEditar) {
        btnEditar.addEventListener('click', function(e) {
            if (selectedId) {
                // Atualiza o link antes de navegar
                this.href = `${currentPage}?action=edit&id=${selectedId}`;
            } else {
                e.preventDefault();
            }
        });
    }

    if (btnVisualizar) {
        btnVisualizar.addEventListener('click', function(e) {
            if (selectedId) {
                this.href = `${currentPage}?action=view&id=${selectedId}`;
            } else {
                e.preventDefault();
            }
        });
    }


   // Lógica do botão Excluir com o modal
   if (btnExcluir) {
        const customConfirmModal = document.getElementById('customConfirmModal');

        btnExcluir.addEventListener('click', function(event) {
            if (this.classList.contains('disabled') || !selectedId) {
                event.preventDefault();
                return;
            }

            this.href = `${currentPage}?action=delete&id=${selectedId}`;
            
            if (customConfirmModal) {
                event.preventDefault();
                
                const confirmBtn = document.getElementById('confirmBtn');
                const cancelBtn = document.getElementById('cancelBtn');
                const deleteUrl = this.href;

                customConfirmModal.style.display = 'block';

                confirmBtn.onclick = () => {
                    if (deleteUrl) {
                        window.location.href = deleteUrl;
                    }
                };

                cancelBtn.onclick = () => {
                    customConfirmModal.style.display = 'none';
                };

                window.onclick = (e) => {
                    if (e.target == customConfirmModal) {
                        customConfirmModal.style.display = 'none';
                    }
                };
            } 
            else { 
                if (!confirm('Tem certeza que deseja excluir este registro? Esta ação não pode ser desfeita.')) {
                    event.preventDefault();
                }
            }
        });
    }

    

  if (btnVisualizarFicha) {
        btnVisualizarFicha.addEventListener('click', function(e) {
            e.preventDefault();
            // 'selectedId' neste contexto conterá o CPF/CNPJ
            if (!this.classList.contains('disabled') && selectedId) {
                window.location.href = `ficha_financeira.php?cpf_cnpj=${encodeURIComponent(selectedId)}`;
            }
        });
    }

    const btnNovoLancamento = document.getElementById('btnNovoLancamento');
    const modalNovoLancamento = document.getElementById('modalNovoLancamento');
    const closeModalBtn = document.getElementById('closeModalNovoLancamento');
    const cancelModalBtn = document.getElementById('btnCancelarNovoLancamento');

    if (btnNovoLancamento) {
        btnNovoLancamento.addEventListener('click', (e) => {
            e.preventDefault();
            modalNovoLancamento.style.display = 'flex';
        });
    }

    if (closeModalBtn) {
        closeModalBtn.addEventListener('click', () => {
            modalNovoLancamento.style.display = 'none';
        });
    }

    if (cancelModalBtn) {
        cancelModalBtn.addEventListener('click', () => {
            modalNovoLancamento.style.display = 'none';
        });
    }
    window.addEventListener('click', (e) => {
        if (e.target == modalNovoLancamento) {
            modalNovoLancamento.style.display = 'none';
        }
    });
});