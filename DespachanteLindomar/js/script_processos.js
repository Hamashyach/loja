
function preencherFormularioProcesso(data) {
    
    
    document.getElementById('proc_os_display').value = data.id || ''; 
    
    document.getElementById('proc_cliente_display').value = data.cliente_nome || '';
    document.getElementById('proc_placa_display').value = data.placa || '';
    document.getElementById('proc_modelo_display').value = data.modelo || '';
    document.getElementById('proc_renavam_display').value = data.renavam || '';
    document.getElementById('proc_servico_display').value = data.servico_nome || 'Ver na OS';
    
    
    document.querySelector('form#formProcesso input[name="PRS_ORS_CODIGO"]').value = data.id || '';
    document.querySelector('form#formProcesso input[name="PRS_CPF_CNPJ"]').value = data.cliente_cpf || '';
    document.querySelector('form#formProcesso input[name="PRS_VEI_CODIGO"]').value = data.veiculo_codigo || '';
    document.querySelector('form#formProcesso input[name="PRS_TSE_CODIGO"]').value = data.servico_id || '';

    
    const campoCondPag = document.getElementById('financeiro_cond_pag');
    const campoValor = document.getElementById('financeiro_valor_total');
    
    if (campoCondPag) {
        campoCondPag.value = data.cond_pagamento || '';
    }
    if (campoValor) {
        const valor = parseFloat(data.valor_total) || 0;
        campoValor.value = valor.toLocaleString('pt-BR', { minimumFractionDigits: 2 });
    }
}

document.addEventListener('DOMContentLoaded', function() {
    
    const btnBuscaVeiculo = document.getElementById('btnAbrirModalVeiculoProc');
    if (btnBuscaVeiculo) {
        window.habilitarBuscaVeiculo = function(cpfCnpj) {
            window.clienteCpfSelecionadoParaProcesso = cpfCnpj;
            btnBuscaVeiculo.disabled = false;
            document.getElementById('proc_veiculo_display').placeholder = "Clique para buscar o veículo...";
        }
    }
});