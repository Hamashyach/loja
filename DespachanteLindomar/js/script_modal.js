document.addEventListener('DOMContentLoaded', function() {

    /**
     @param {object} options 
     @param {object} [options.extraFields]
    */
    function setupModalSearch(options) {
        const modal = document.getElementById(options.modalId);
        const btnAbrir = options.btnId ? document.getElementById(options.btnId) : null;
        
        if (!modal || !btnAbrir) return;

        const spanFechar = modal.querySelector('.close-button');
        const buscaInput = document.getElementById(options.inputId);
        const resultadosDiv = document.getElementById(options.resultsId);
        const campoNomeDisplay = options.displayFieldId ? document.getElementById(options.displayFieldId) : null;
        const campoIdHidden = options.hiddenFieldId ? document.getElementById(options.hiddenFieldId) : null;
        async function buscarDados(termo = '') {
            try {
                const url = options.buildUrl ? options.buildUrl(termo) : `${options.url}?q=${encodeURIComponent(termo)}`;
                const response = await fetch(url);
                if (!response.ok) throw new Error('Erro na rede.');
                
                const dados = await response.json();
                
                resultadosDiv.innerHTML = '';
                if (dados.length > 0) {
                    dados.forEach(item => {
                        const div = document.createElement('div');
                        div.textContent = `${item.id} - ${item.nome}`;
                        div.dataset.id = item.id;
                        div.dataset.nome = `${item.id} - ${item.nome}`;

                        for (const key in item) {
                            if (item.hasOwnProperty(key)) {
                                div.dataset[key] = item[key];
                            }
                        }
                        div.style.cursor = 'pointer';
                        resultadosDiv.appendChild(div);
                    });
                } else {
                    resultadosDiv.innerHTML = '<div>Nenhum resultado encontrado.</div>';
                }
            } catch (error) {
                console.error('Erro ao buscar dados:', error);
                resultadosDiv.innerHTML = '<div>Erro ao carregar dados.</div>';
            }
        }

        if (btnAbrir) {
            btnAbrir.addEventListener('click', () => {
                modal.style.display = 'flex';
                buscaInput.focus();
                buscarDados();
            });
        }

        spanFechar.addEventListener('click', () => modal.style.display = 'none');
        buscaInput.addEventListener('keyup', () => buscarDados(buscaInput.value));
      
        resultadosDiv.addEventListener('click', (event) => {
            const target = event.target;
            if (target && target.dataset.id) {


            if (options.modalId === 'modalTipoServico' && window.linhaServicoAtiva) {
                    const displayField = linhaServicoAtiva.querySelector('input[name="servicos_desc[]"]');
                    const hiddenField = linhaServicoAtiva.querySelector('input[name="servicos_id[]"]');
                    const valorField = linhaServicoAtiva.querySelector('.item-valor');

                    if (displayField) displayField.value = target.dataset.nome;
                    if (hiddenField) hiddenField.value = target.dataset.id;
                    
                    if (valorField && target.dataset.tse_vlunitario) {
                        let valor = parseFloat(target.dataset.tse_vlunitario);
                        valorField.value = valor.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
                        valorField.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                    window.linhaServicoAtiva = null; 
        
                } else if (campoNomeDisplay && campoIdHidden) {
                    campoNomeDisplay.value = target.dataset.nome;
                    campoIdHidden.value = target.dataset.id;
                }


                if(options.extraFields) {
                    for(const key in options.extraFields) {
                        const fieldId = options.extraFields[key];
                        const fieldElement = document.getElementById(fieldId);
                        if(fieldElement) {
                            const value = target.dataset[key];
                            fieldElement.value = (value && value !== 'null') ? value : '';
                        }
                    }
                }
                
                if (options.onSelect) {
                    options.onSelect(target.dataset);
                }
            
                modal.style.display = 'none';
            }
        });

        window.addEventListener('click', (event) => {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
    }


    // --- CONFIGURAÇÃO MODAIS ---

    // modal de Despachantes
    setupModalSearch({
        modalId: 'modalDespachante',
        btnId: 'btnAbrirModalDespachante',
        inputId: 'buscaDespachanteInput',
        resultsId: 'listaResultadosDespachante',
        displayFieldId: 'cod_desp_input', 
        hiddenFieldId: 'cod_desp_hidden', 
        url: 'modais/buscar_despachante.php',
        extraFields:{
            despcpf_cnpj: 'despcpf_cnpj'
        }
    });

    // Configura o modal de Clientes
    setupModalSearch({
        modalId: 'modalCliente',
        btnId: 'btnAbrirModalCliente',
        inputId: 'buscaClienteInput',
        resultsId: 'listaResultadosCliente',
        displayFieldId: 'cliente_nome_display',
        hiddenFieldId: 'cliente_id_hidden',
        url: 'modais/buscar_cliente.php',
        extraFields:{
            cpf_cnpj: 'cpf_cnpj'
        }
    });

    // modal municipios
    setupModalSearch({
        modalId: 'modalMunicipio',
        btnId: 'btnAbrirModalMunicipio',
        inputId: 'buscaMunicipioInput',
        resultsId: 'listaResultadosMunicipio',
        displayFieldId: 'municipio_display',
        hiddenFieldId: 'cod_muni_hidden',
        url: 'modais/buscar_municipio.php',
        extraFields: { 
            uf: 'uf' 
        }
    });

    //modal Marca
     setupModalSearch({
        modalId: 'modalMarca',
        btnId: 'btnAbrirModalMarca',
        inputId: 'buscaMarcaInput',
        resultsId: 'listaResultadosMarca',
        displayFieldId: 'marca_display',
        hiddenFieldId: 'cod_marca_hidden',
        url: 'modais/buscar_marca.php',
    });

    //modal Cliente
     setupModalSearch({
        modalId: 'modalCliente',
        btnId: 'btnAbrirModalCliente',
        inputId: 'buscaClienteInput',
        resultsId: 'listaResultadosCliente',
        displayFieldId: 'cliente_display',
        hiddenFieldId: 'cod_cliente_hidden',
        url: 'modais/buscar_cliente.php',
        extraFields: { 
            cpf_cnpj: 'cpf_cnpj',
            endereco: 'endereco',
            numero: 'numero',
            bairro: 'bairro',
           
        }
    });

        //modal veiculo
     setupModalSearch({
        modalId: 'modalVeiculo',
        btnId: 'btnAbrirModalVeiculo',
        inputId: 'buscaVeiculoInput',
        resultsId: 'listaResultadosVeiculo',
        displayFieldId: 'veiculo_display',
        hiddenFieldId: 'veiculo_hidden',
        url: 'modais/buscar_veiculo.php',
        extraFields: { 
            placa: 'placa',
            renavam: 'renavam',
            chassi: 'chassi'
           
        }
    });

    //modal Categoria
     setupModalSearch({
        modalId: 'modalCategoria',
        btnId: 'btnAbrirModalCategoria',
        inputId: 'buscaCategoriaInput',
        resultsId: 'listaResultadosCategoria',
        displayFieldId: 'categoria_display',
        hiddenFieldId: 'cod_categoria_hidden',
        url: 'modais/buscar_categoria.php',
    });

    //modal Tipo
     setupModalSearch({
        modalId: 'modalTipo',
        btnId: 'btnAbrirModalTipo',
        inputId: 'buscaTipoInput',
        resultsId: 'listaResultadosTipo',
        displayFieldId: 'tipo_display',
        hiddenFieldId: 'cod_tipo_hidden',
        url: 'modais/buscar_tipo.php',
    });

    //modal Tipo 1
     setupModalSearch({
        modalId: 'modalTipo',
        btnId: 'btnAbrirModalTipo1',
        inputId: 'buscaTipoInput',
        resultsId: 'listaResultadosTipo',
        displayFieldId: 'tipo_display1',
        hiddenFieldId: 'cod_tipo_hidden1',
        url: 'modais/buscar_tipo.php',
    });

    //modal Tipo Carroceria
     setupModalSearch({
        modalId: 'modalCarroceria',
        btnId: 'btnAbrirModalCarroceria',
        inputId: 'buscaCarroceriaInput',
        resultsId: 'listaResultadosCarroceria',
        displayFieldId: 'carroceria_display',
        hiddenFieldId: 'cod_carroceria_hidden',
        url: 'modais/buscar_carroceria.php',
    });

    //modal Cor
     setupModalSearch({
        modalId: 'modalCor',
        btnId: 'btnAbrirModalCor',
        inputId: 'buscaCorInput',
        resultsId: 'listaResultadosCor',
        displayFieldId: 'cor_display',
        hiddenFieldId: 'cod_cor_hidden',
        url: 'modais/buscar_cor.php',
    });

     //modal Especie
     setupModalSearch({
        modalId: 'modalEspecie',
        btnId: 'btnAbrirModalEspecie',
        inputId: 'buscaEspecieInput',
        resultsId: 'listaResultadosEspecie',
        displayFieldId: 'especie_display',
        hiddenFieldId: 'cod_especie_hidden',
        url: 'modais/buscar_especie.php',
    });

     //modal Combustivel
     setupModalSearch({
        modalId: 'modalCombustivel',
        btnId: 'btnAbrirModalCombustivel',
        inputId: 'buscaCombustivelInput',
        resultsId: 'listaResultadosCombustivel',
        displayFieldId: 'combustivel_display',
        hiddenFieldId: 'cod_combustivel_hidden',
        url: 'modais/buscar_combustivel.php',
    });


     //modal Restricoes
     setupModalSearch({
        modalId: 'modalRestricoes',
        btnId: 'btnAbrirModalRestricoes',
        inputId: 'buscaRestricoesInput',
        resultsId: 'listaResultadosRestricoes',
        displayFieldId: 'restricoes_display',
        hiddenFieldId: 'cod_restricoes_hidden',
        url: 'modais/buscar_restricoes.php',
    });

    //modal Tipo de Servico
     setupModalSearch({
        modalId: 'modalTipoServico',
        btnId: 'btnAbrirModalTipoServico',
        inputId: 'buscaTipoServicoInput',
        resultsId: 'listaResultadosTipoServico',
        displayFieldId: 'tipo_servico_display',
        hiddenFieldId: 'cod_tipo_servico_hidden',
        url: 'modais/buscar_tipo_servico.php',
        extraFields: { 
            tse_vlunitario : 'tse_vlunitario'
           
        }
    });

     //modal Tipo de Servico2
     setupModalSearch({
        modalId: 'modalTipoServico2',
        btnId: 'btnAbrirModalTipoServico2',
        inputId: 'buscaTipoServicoInput2',
        resultsId: 'listaResultadosTipoServico2',
        displayFieldId: 'tipo_servico_display2',
        hiddenFieldId: 'cod_tipo_servico_hidden2',
        url: 'modais/buscar_tipo_servico2.php',
        extraFields: { 
            tse_vlunitario2 : 'tse_vlunitario2'
           
        }
    });

    //modal Tipo de Servico3
     setupModalSearch({
        modalId: 'modalTipoServico3',
        btnId: 'btnAbrirModalTipoServico3',
        inputId: 'buscaTipoServicoInput3',
        resultsId: 'listaResultadosTipoServico3',
        displayFieldId: 'tipo_servico_display3',
        hiddenFieldId: 'cod_tipo_servico_hidden3',
        url: 'modais/buscar_tipo_servico3.php',
        extraFields: { 
            tse_vlunitario3 : 'tse_vlunitario3'
           
        }
    });

      //modal Tipo de Servico4
     setupModalSearch({
        modalId: 'modalTipoServico4',
        btnId: 'btnAbrirModalTipoServico4',
        inputId: 'buscaTipoServicoInput4',
        resultsId: 'listaResultadosTipoServico4',
        displayFieldId: 'tipo_servico_display4',
        hiddenFieldId: 'cod_tipo_servico_hidden4',
        url: 'modais/buscar_tipo_servico4.php',
        extraFields: { 
            tse_vlunitario4 : 'tse_vlunitario4'
           
        }
    });

        //modal Tipo de Servico5
     setupModalSearch({
        modalId: 'modalTipoServico5',
        btnId: 'btnAbrirModalTipoServico5',
        inputId: 'buscaTipoServicoInput5',
        resultsId: 'listaResultadosTipoServico5',
        displayFieldId: 'tipo_servico_display5',
        hiddenFieldId: 'cod_tipo_servico_hidden5',
        url: 'modais/buscar_tipo_servico5.php',
        extraFields: { 
            tse_vlunitario5 : 'tse_vlunitario5'
           
        }
    });

    


     //modal Situação Processo
     setupModalSearch({
        modalId: 'modalProcesso',
        btnId: 'btnAbrirModalProcesso',
        inputId: 'buscaProcessoInput',
        resultsId: 'listaResultadosProcesso',
        displayFieldId: 'processo_display',
        hiddenFieldId: 'cod_processo_hidden',
        url: 'modais/buscar_processo.php',
        
    });

    //modal CONDIÇÃO PAGAMENTO
     setupModalSearch({
        modalId: 'modalPagamento',
        btnId: 'btnAbrirModalPagamento',
        inputId: 'buscaPagamentoInput',
        resultsId: 'listaResultadosPagamento',
        displayFieldId: 'pagamento_display',
        hiddenFieldId: 'cod_pagamento_hidden',
        url: 'modais/buscar_pagamento.php',
        
    });

    // Cliente 
    if (!document.getElementById('formEmplacamento')) {
    setupModalSearch({
        modalId: 'modalCliente',
        btnId: 'servicos_btnAbrirModalCliente', 
        inputId: 'buscaClienteInput',
        resultsId: 'listaResultadosCliente',
        displayFieldId: 'servicos_cliente_display',
        hiddenFieldId: 'servicos_cod_cliente_hidden', 
        url: 'modais/buscar_cliente.php',
        extraFields: { 
            cpf_cnpj: 'servicos_cpf_cnpj'
        },
        onSelect: function(item) {
             
                document.getElementById('servicos_cliente_display').value = item.nome;
                document.getElementById('servicos_cod_cliente_hidden').value = item.cpf_cnpj; 
                document.getElementById('veiculo_display').value = '';
                document.getElementById('veiculo_id_hidden').value = '';
                document.getElementById('vei_placa').value = '';
                document.getElementById('vei_renavam').value = '';
                document.getElementById('vei_chassi').value = '';

                document.getElementById('btnAbrirModalVeiculoCliente').disabled = false;
            }
        });
    }

    // Veículos do Cliente 
    if (!document.getElementById('formEmplacamento')) {
    setupModalSearch({
        modalId: 'modalVeiculoCliente',
        btnId: 'btnAbrirModalVeiculoCliente',
        inputId: 'buscaVeiculoClienteInput',
        resultsId: 'listaResultadosVeiculoCliente',
        buildUrl: function(termo) {
            const clienteCpf = document.getElementById('servicos_cod_cliente_hidden').value;

            if (!clienteCpf) {
                return null;
            }

            return `modais/buscar_veiculos_por_cliente.php?q=${encodeURIComponent(termo)}&cliente_cpf=${encodeURIComponent(clienteCpf)}`;
        },                
            onSelect: function(item) {
                console.log('Veículo selecionado:', item); 

                document.getElementById('veiculo_display').value = item.MODELO || item.modelo;
                document.getElementById('veiculo_id_hidden').value = item.CODIGO || item.codigo || item.id;
                document.getElementById('vei_placa').value = item.PLACA_UF || item.placa || '';
                document.getElementById('vei_renavam').value = item.RENAVAM || item.renavam || '';
                document.getElementById('vei_chassi').value = item.CHASSI || item.chassi || '';
            }
        });
    }

    setupModalSearch({
        modalId: 'modalTipoServico',
        inputId: 'buscaTipoServicoInput',
        resultsId: 'listaResultadosTipoServico',
        url: 'modais/buscar_tipo_servico.php',
    });

    // Ordem de Serviço
  setupModalSearch({
        modalId: 'modalOrdemServico',
        btnId: 'btnAbrirModalOS',
        inputId: 'buscaOrdemServicoInput',
        resultsId: 'listaResultadosOrdemServico',
        displayFieldId: 'proc_os_display',
        hiddenFieldId: 'proc_os_hidden',
        url: 'modais/buscar_tipo_servico5.php',
        extraFields: { 
            proc_cond_pag_display : 'proc_cond_pag_display',
            proc_valor_total: 'proc_valor_total',
            proc_sit_pag_display : 'proc_sit_pag_display',
            cliente: 'cliente',
            proc_placa_display : 'proc_placa_display',
            proc_modelo_display : 'proc_modelo_display',
            proc_renavam_display : 'proc_renavam_display',
            proc_despachante_display : 'proc_despachante_display',
            proc_servico_display : 'proc_servico_display'

        }
    });
   

    // --- LÓGICA PARA BUSCA DE CEP (API VIA CEP) ---
   /**
 * Configura uma busca de CEP genérica para um formulário.
 * @param {object} fieldIds 
 * @param {string} fieldIds.cep 
 * @param {string} fieldIds.logradouro 
 * @param {string} fieldIds.bairro 
 * @param {string} fieldIds.cidade 
 * @param {string} fieldIds.uf 
 * @param {string} [fieldIds.ibge]
 * @param {string} [fieldIds.numero] 
 */
function configurarBuscaCep(fieldIds) {
    const cepInput = document.getElementById(fieldIds.cep);


    if (!cepInput) {
        return;
    }

    cepInput.addEventListener('blur', async function() {
        const cep = this.value.replace(/\D/g, ''); 

        if (cep.length !== 8) {
            return; 
        }

      
        const campoLogradouro = document.getElementById(fieldIds.logradouro);
        const campoBairro = document.getElementById(fieldIds.bairro);
        const campoCidade = document.getElementById(fieldIds.cidade);
        const campoUf = document.getElementById(fieldIds.uf);
        const campoIbge = fieldIds.ibge ? document.getElementById(fieldIds.ibge) : null;
   
        const limparCampos = () => {
            if (campoLogradouro) campoLogradouro.value = "";
            if (campoBairro) campoBairro.value = "";
            if (campoCidade) campoCidade.value = "";
            if (campoUf) campoUf.value = "";
            if (campoIbge) campoIbge.value = "";
        };

        try {
            
            if (campoLogradouro) campoLogradouro.value = "Buscando...";
            if (campoBairro) campoBairro.value = "Buscando...";
            if (campoCidade) campoCidade.value = "Buscando...";
            if (campoUf) campoUf.value = "Buscando...";

            const response = await fetch(`https://viacep.com.br/ws/${cep}/json/`);
            if (!response.ok) throw new Error('Não foi possível buscar o CEP.');
            
            const data = await response.json();

            if (data.erro) {
                alert('CEP não encontrado. Verifique o número digitado.');
                limparCampos();
                return;
            }

            // Preenche os campos com os dados da API
            if (campoLogradouro) campoLogradouro.value = data.logradouro;
            if (campoBairro) campoBairro.value = data.bairro;
            if (campoCidade) campoCidade.value = data.localidade;
            if (campoUf) campoUf.value = data.uf;
            if (campoIbge && data.ibge) campoIbge.value = data.ibge;

            // Foca no campo de número, se ele for especificado
            if (fieldIds.numero) {
                const campoNumero = document.getElementById(fieldIds.numero);
                if (campoNumero) campoNumero.focus();
            }

        } catch (error) {
            console.error('Erro ao buscar CEP:', error);
            alert('Ocorreu um erro ao buscar o CEP. Verifique sua conexão com a internet.');
            limparCampos();
        }
    });
}

//despachante

configurarBuscaCep({
        cep: 'CEP', 
        logradouro: 'ENDERECO',
        bairro: 'BAIRRO',
        cidade: 'CIDADE', 
        uf: 'ESTADO',  
        numero: 'NUMERO'
    });

//clientes 
configurarBuscaCep({
        cep: 'CEP_LOC',
        logradouro: 'ENDERECO',
        bairro: 'BAIRRO',
        ibge: 'COD_MUNI', 
        cidade: 'COD_MUNI',
        uf: 'UF_ENDERECO',
        numero: 'NUMERO'
    });

//MUNICIPIOS
configurarBuscaCep({
        cep: 'CEP',
        ibge: 'COD_MUNI', 
        cidade: 'MUNICIPIO',
        uf: 'ESTADO',
    });

//POSTO VISTORIA
configurarBuscaCep({
        cep: 'POV_CEP',
        logradouro: 'POV_ENDERECO',
        bairro: 'POV_BAIRRO', 
        cidade: 'POV_MUNICIPIO',
        uf: 'POV_ESTADO',
        numero: 'POV_NUMERO'
    });
});