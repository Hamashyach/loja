document.addEventListener('DOMContentLoaded', function() {
    const btnCalcular = document.getElementById('btnCalcularIpva');
    if (!btnCalcular) return;

    
    const valorVenalInput = document.getElementById('ipva_valor_venal');
    const tipoVeiculoSelect = document.getElementById('ipva_tipo_veiculo');
    
    
    const aliquotaInput = document.getElementById('ipva_aliquota');
    const jurosInput = document.getElementById('ipva_juros');
    const multaInput = document.getElementById('ipva_multa');

    
    const resultadoDiv = document.getElementById('resultadoIpva');
    const resValorVenal = document.getElementById('res_valor_venal');
    const resTipoVeiculo = document.getElementById('res_tipo_veiculo');
    const resAliquota = document.getElementById('res_aliquota');
    const resValorIpva = document.getElementById('res_valor_ipva');
    
    
    const ipvaHiddenInput = document.getElementById('vei_valor_ipva');

    /**
     * Preenche os campos de alíquota, juros e multa com base no tipo selecionado.
     */
    function preencherDetalhes() {
        const tipoDescricao = tipoVeiculoSelect.value;
        const dadosDoTipo = dadosEmplacamento[tipoDescricao]; 

        if (dadosDoTipo) {
            aliquotaInput.value = parseFloat(dadosDoTipo.EMT_ALIQUOTA).toFixed(2).replace('.', ',');
            jurosInput.value = parseFloat(dadosDoTipo.EMT_ALIQ_JUROS_IPVA).toFixed(2).replace('.', ',');
            multaInput.value = parseFloat(dadosDoTipo.EMT_ALIQ_MULTA_IPVA).toFixed(2).replace('.', ',');
        } else {
            
            aliquotaInput.value = '';
            jurosInput.value = '';
            multaInput.value = '';
        }
    }

    

    
    tipoVeiculoSelect.addEventListener('change', preencherDetalhes);

    
    btnCalcular.addEventListener('click', () => {
        let valorVenal = parseFloat(valorVenalInput.value.replace(/[^\d,]/g, '').replace(',', '.'));
        
        
        let aliquota = parseFloat(aliquotaInput.value.replace(',', '.'));

        if (isNaN(valorVenal) || valorVenal <= 0) {
            alert('Por favor, insira um valor venal válido.');
            return;
        }
        if (isNaN(aliquota)) {
            alert('Por favor, selecione um tipo de veículo para carregar a alíquota.');
            return;
        }

        
        if (tipoVeiculoSelect.value === 'Automóveis' && valorVenal > 150000) {
            aliquota = parseFloat(dadosEmplacamento['Automoveis_luxo'].EMT_ALIQUOTA || 3.0);
            aliquotaInput.value = aliquota.toFixed(2).replace('.', ','); 
        }

        const valorIpva = (valorVenal * aliquota) / 100;

        
        if (ipvaHiddenInput) {
            ipvaHiddenInput.value = valorIpva.toFixed(2);
        }

        
        const formatoMoeda = { style: 'currency', currency: 'BRL' };
        resValorVenal.textContent = valorVenal.toLocaleString('pt-BR', formatoMoeda);
        resTipoVeiculo.textContent = tipoVeiculoSelect.value;
        resAliquota.textContent = aliquota.toFixed(2).replace('.', ',') + '%';
        resValorIpva.textContent = valorIpva.toLocaleString('pt-BR', formatoMoeda);
        resultadoDiv.style.display = 'block';
    });

    
    if (valorVenalInput) {
        valorVenalInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = (value / 100).toLocaleString('pt-BR', { minimumFractionDigits: 2 });
            if (value === '0,00') { e.target.value = ''; } 
            else { e.target.value = 'R$ ' + value; }
        });
    }

    
    preencherDetalhes();
});