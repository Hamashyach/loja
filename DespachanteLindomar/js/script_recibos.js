
let linhaServicoAtiva = null;

document.addEventListener('DOMContentLoaded', function() {
    
    
    
    

    
    const tabelaItensServico = document.getElementById('tabelaItensServico');
    if (!tabelaItensServico) return; 

    const btnAdicionarServico = document.getElementById('btnAdicionarServico');
    const totalServicosSpan = document.getElementById('valorTotalServicos');
    const hiddenTotalInput = document.getElementById('hiddenTotalServicos'); 
    const hiddenExtensoInput = document.getElementById('valorExtensoHidden');
    const modalServico = document.getElementById('modalTipoServico');

    
    function calcularTotal() {
        let total = 0;
        tabelaItensServico.querySelectorAll('.item-valor').forEach(input => {
            const valor = parseFloat(input.value.replace(/[^\d,]/g, '').replace(',', '.')) || 0;
            total += valor;
        });
        
        totalServicosSpan.textContent = total.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
        
        if (hiddenTotalInput) {
            hiddenTotalInput.value = total.toFixed(2);
        }
        
        
        
        
        
    }

    
    function aplicarMascaraDinheiro(input) {
        if (!input) return;
        input.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = (value / 100).toLocaleString('pt-BR', { minimumFractionDigits: 2 });
            e.target.value = (value === '0,00') ? '' : 'R$ ' + value;
            calcularTotal(); 
        });
        
        
        input.addEventListener('blur', calcularTotal);
    }

    
    function adicionarLinhaServico(servico = null) {
        if (tabelaItensServico.rows.length >= 5) {
            alert('Você atingiu o limite de 5 serviços por recibo.');
            return;
        }

        const newRow = tabelaItensServico.insertRow();
        
        
        const desc = servico ? `${servico.id} - ${servico.nome}` : '';
        const id = servico ? servico.id : '';
        const valor = servico ? parseFloat(servico.valor).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }) : 'R$ 0,00';

        newRow.innerHTML = `
            <td>
                <div class="input-with-button">
                    <input type="text" name="servicos_desc[]" placeholder="Clique para buscar o serviço..." value="${desc}" readonly>
                    <input type="hidden" name="servicos_id[]" value="${id}">
                    <button type="button" class="btn-lookup btn-buscar-servico">...</button>
                </div>
            </td>
            <td>
                <input type="text" name="servicos_valor[]" class="money-mask item-valor" value="${valor}">
            </td>
            <td>
                <button type="button" class="btn-remover-servico btn-danger">X</button>
            </td>
        `;

        aplicarMascaraDinheiro(newRow.querySelector('.money-mask'));
    }

    
    tabelaItensServico.addEventListener('click', function(event) {
        const target = event.target;

        
        if (target.classList.contains('btn-buscar-servico')) {
            linhaServicoAtiva = target.closest('tr'); 
            if (modalServico) {
                
                modalServico.style.display = 'flex'; 
                document.getElementById('buscaTipoServicoInput').focus();
            }
        }

        
        if (target.classList.contains('btn-remover-servico')) {
            target.closest('tr').remove();
            calcularTotal(); 
        }
    });

    
    if (btnAdicionarServico) {
        btnAdicionarServico.addEventListener('click', () => adicionarLinhaServico(null));
    }
    
    
    
    if (typeof setupModalSearch === 'function') {
        setupModalSearch({
            modalId: 'modalTipoServico',
            
            inputId: 'buscaTipoServicoInput',
            resultsId: 'listaResultadosTipoServico',
            url: 'modais/buscar_tipo_servico.php', 
            onSelect: function(item) {
                if (window.linhaServicoAtiva) {
                    const displayField = window.linhaServicoAtiva.querySelector('input[name="servicos_desc[]"]');
                    const hiddenField = window.linhaServicoAtiva.querySelector('input[name="servicos_id[]"]');
                    const valorField = window.linhaServicoAtiva.querySelector('.item-valor');

                    if (displayField) displayField.value = item.nome;
                    if (hiddenField) hiddenField.value = item.id;
                    
                    if (valorField && item.tse_vlunitario) {
                        let valor = parseFloat(item.tse_vlunitit);
                        valorField.value = valor.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
                        calcularTotal(); 
                    }
                    window.linhaServicoAtiva = null; 
                }
            }
        });
    } else {
        console.error("Função setupModalSearch() não encontrada. Verifique se 'script_modal.js' está carregado.");
    }

    
    const codigoRecibo = document.querySelector('input[name="RES_CODIGO"]').value;
    if (tabelaItensServico.rows.length === 0 && !codigoRecibo) {
        adicionarLinhaServico();
    }
});


function imprimirRecibo() {
    const formAtivo = document.getElementById('formReciboServicos');
    if (!formAtivo) {
        alert("Erro: Formulário não encontrado.");
        return;
    }
    
    
    const numero = formAtivo.querySelector('input[name="RES_CODIGO"]').value || 'NOVO';
    const valor = document.getElementById('valorTotalServicos').textContent; 
    
    
    const clienteNome = formAtivo.querySelector('#cliente_display').value;
    const clienteCpf = formAtivo.querySelector('#cpf_cnpj').value;
    const valorExtenso = formAtivo.querySelector('input[name="valor_extenso_hidden"]').value; 
    
    
    
    const cidade = 'Sua Cidade'; 
    const data = formAtivo.querySelector('input[name="RES_DATA"]').value;
    const emitenteNome = formAtivo.querySelector('#cod_desp_input').value.split(' - ')[1] || formAtivo.querySelector('#cod_desp_input').value;
    const emitenteCpf = formAtivo.querySelector('#despcpf_cnpj').value;

    
    document.getElementById('print_numero').textContent = numero;
    document.getElementById('print_valor').textContent = valor;
    
    document.getElementById('print_cliente_nome').textContent = clienteNome;
    document.getElementById('print_cliente_cpf').textContent = clienteCpf;
    document.getElementById('print_valor_extenso').textContent = valorExtenso;

    
    const listaServicos = document.getElementById('print_servicos_lista');
    listaServicos.innerHTML = ''; 
    
    formAtivo.querySelectorAll('#tabelaItensServico tr').forEach(row => {
        const desc = row.querySelector('input[name="servicos_desc[]"]').value;
        const val = row.querySelector('input[name="servicos_valor[]"]').value;
        if (desc) {
            const p = document.createElement('p');
            p.innerHTML = `- ${desc} <strong>(${val})</strong>`; 
            listaServicos.appendChild(p);
        }
    });

    document.getElementById('print_cidade_data').textContent = `${cidade}, ${data}.`;
    document.getElementById('print_emitente_nome').textContent = emitenteNome;
    document.getElementById('print_emitente_cpf').textContent = emitenteCpf;
    document.getElementById('print_pagante_nome').textContent = clienteNome;
    document.getElementById('print_pagante_cpf').textContent = clienteCpf;

    window.print();
}