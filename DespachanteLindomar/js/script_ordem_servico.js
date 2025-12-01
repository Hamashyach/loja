document.addEventListener('DOMContentLoaded', () => {
    const itensContainer = document.getElementById('itens-container');
    const btnAdicionarItem = document.getElementById('btnAdicionarItem');
    const templateItemRow = document.getElementById('template-item-row');
    const btnGerarProcesso = document.getElementById('btnGerarProcesso');


    // Converte texto "1.234,56" → 1234.56
    const parseMoeda = (valor) => {
        if (!valor) return 0;
        return parseFloat(valor.toString().replace(/\./g, '').replace(',', '.')) || 0;
    };

    // Formata número 1234.56 → "1.234,56"
    const formatarMoeda = (valor) => {
        if (isNaN(valor)) return '0,00';
        return valor.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };

    // === Funções de cálculo ===

    function calcularTotalGeral() {
        let totalGeral = 0;

        itensContainer.querySelectorAll('.item-row').forEach(linha => {
            totalGeral += parseMoeda(linha.querySelector('.item-vltotal').value);
        });

        document.getElementById('valorTotalServicos').innerText = `R$ ${formatarMoeda(totalGeral)}`;
        document.getElementById('valor_total_hidden').value = totalGeral.toFixed(2);
    }

    function calcularTotalLinha(linha) {
        const qtde = parseInt(linha.querySelector('.item-qtde').value) || 0;
        const vlUnitario = parseMoeda(linha.querySelector('.item-vlunitario').value);

        const total = qtde * vlUnitario;

        linha.querySelector('.item-vltotal').value = formatarMoeda(total);
        calcularTotalGeral();
    }

    // === Eventos ===

    if (itensContainer) {
        // Seleção de serviço
        itensContainer.addEventListener('change', (event) => {
            if (event.target.classList.contains('item-servico-select')) {
                const select = event.target;
                const valorSelecionado = parseFloat(select.options[select.selectedIndex].dataset.valor) || 0;
                const linha = select.closest('.item-row');


                linha.querySelector('.item-vlunitario').value = formatarMoeda(valorSelecionado);
                linha.querySelector('.item-qtde').value = 1; 
                calcularTotalLinha(linha);
            }
        });

        // Edição de quantidade ou valor unitário
        itensContainer.addEventListener('input', (event) => {
            if (event.target.classList.contains('item-qtde') || event.target.classList.contains('item-vlunitario')) {
                const linha = event.target.closest('.item-row');
                calcularTotalLinha(linha);
            }
        });

        // Remover item
        itensContainer.addEventListener('click', (event) => {
            if (event.target.classList.contains('btn-remover-item')) {
                event.preventDefault();
                event.target.closest('.item-row').remove();
                calcularTotalGeral();
            }
        });
    }

    // === Adicionar item ===
    if (btnAdicionarItem) {
        btnAdicionarItem.addEventListener('click', () => {
            if (templateItemRow) {
                const novaLinha = templateItemRow.content.cloneNode(true);
                itensContainer.appendChild(novaLinha);
            }
        });
    }

   // === Gerar processo ===
if (btnGerarProcesso) {
    // Pega as referências do modal que acabamos de adicionar no HTML
    const modal = document.getElementById('modalGerarProcesso');
    const closeModalBtn = modal.querySelector('.close-button');
    const listaItensDiv = document.getElementById('listaItensParaProcesso');
    const osCodigoSpan = document.getElementById('modalOsCodigo');

    btnGerarProcesso.addEventListener('click', async (event) => {
        event.preventDefault(); // Impede qualquer ação padrão

        const osCodigoInput = document.querySelector('input[name="ORS_CODIGO"]');
        const osCodigo = osCodigoInput ? osCodigoInput.value : '';

        // 1. Validar se a OS já foi salva (usando sua função de mensagem)
        if (!osCodigo || osCodigo === 'NOVO' || osCodigo === '') {
            mostrarMensagemPersonalizada('⚠️ Você precisa salvar a Ordem de Serviço antes de gerar um processo.', 'error');
            return;
        }

        // 2. Abrir o modal e buscar os itens via API
        osCodigoSpan.textContent = osCodigo;
        listaItensDiv.innerHTML = '<p>Carregando serviços disponíveis...</p>';
        modal.style.display = 'block';

        try {
            const response = await fetch(`api_get_itens_os.php?os_id=${osCodigo}`);
            if (!response.ok) throw new Error('Falha ao buscar os dados da OS.');
            
            const itens = await response.json();
            listaItensDiv.innerHTML = ''; // Limpa a mensagem de "carregando"
            
            if (itens && itens.length > 0) {
                itens.forEach(item => {
                    const a = document.createElement('a');
                    a.className = 'result-item';
                    // 3. Monta o link CORRETO que a sua página de processo já espera
                    a.href = `cadastro_processos.php?action=generate_from_os&os_id=${item.ORI_ORS_CODIGO}&item_id=${item.ORI_ITEM}`;
                    a.innerHTML = `<strong>Item ${item.ORI_ITEM}:</strong> ${item.TSE_DESCRICAO}`;
                    listaItensDiv.appendChild(a);
                });
            } else {
                listaItensDiv.innerHTML = '<p>Não há serviços disponíveis para gerar processo nesta OS (todos os serviços já podem ter sido processados).</p>';
            }
        } catch (error) {
            console.error('Erro:', error);
            listaItensDiv.innerHTML = '<p>Ocorreu um erro ao carregar os serviços.</p>';
        }
    });

    // Funções para fechar o modal
    closeModalBtn.addEventListener('click', () => {
        modal.style.display = 'none';
    });

    window.addEventListener('click', (event) => {
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    });
}

   function mostrarMensagemPersonalizada(mensagem, tipo = 'success') {
  const alertBox = document.createElement('div');
  alertBox.classList.add('custom-alert');
  if (tipo === 'error') alertBox.classList.add('error');
  alertBox.innerText = mensagem;
  document.body.appendChild(alertBox);
  
  setTimeout(() => alertBox.style.opacity = '1', 100);
  
  setTimeout(() => {
    alertBox.style.opacity = '0';
    setTimeout(() => alertBox.remove(), 500);
  }, 3000);
}
});
