document.addEventListener('DOMContentLoaded', function() {
    
    const tabelaBody = document.getElementById('tabelaPropAnteriorBody');
    if (!tabelaBody) return; 

    
    const btnAdicionar = document.getElementById('btnAdicionarPropAnterior');
    const btnEditar = document.getElementById('btnEditarPropAnterior');
    const btnExcluir = document.getElementById('btnExcluirPropAnterior');
    
    const modal = document.getElementById('modalPropAnterior');
    const formPropAnterior = document.getElementById('formPropAnterior');
    const formVeiculo = document.getElementById('formVeiculo');
    
    const spanFechar = modal.querySelector('.close-button');
    const tituloModal = document.getElementById('modalPropAnteriorTitulo');

    let linhaSelecionada = null;
    let editandoLinha = null;

    
    tabelaBody.addEventListener('click', (event) => {
        const linhaClicada = event.target.closest('tr.clickable-row');
        if (!linhaClicada) return;

        if (linhaSelecionada) {
            linhaSelecionada.classList.remove('selected');
        }

        linhaClicada.classList.add('selected');
        linhaSelecionada = linhaClicada;

        
        if(btnEditar) btnEditar.disabled = false;
        if(btnExcluir) btnExcluir.disabled = false;
    });

    /** Desabilita os botões e limpa a seleção */
    function limparSelecao() {
        if (linhaSelecionada) {
            linhaSelecionada.classList.remove('selected');
            linhaSelecionada = null;
        }
        if(btnEditar) btnEditar.disabled = true;
        if(btnExcluir) btnExcluir.disabled = true;
    }
    
    

    
    if(btnEditar){
        btnEditar.addEventListener('click', () => {
            if (!linhaSelecionada) { 
                alert("Por favor, selecione um proprietário para editar.");
                return;
            }
            editandoLinha = linhaSelecionada;
            tituloModal.textContent = "Editar Proprietário Anterior";

            
            formPropAnterior.pan_nome.value = linhaSelecionada.inputs.nome.value;
            formPropAnterior.pan_cpf_cnpj.value = linhaSelecionada.inputs.cpf_cnpj.value;
            formPropAnterior.pan_cidade.value = linhaSelecionada.inputs.cidade.value;
            formPropAnterior.pan_uf.value = linhaSelecionada.inputs.uf.value;
            formPropAnterior.pan_data_transferencia.value = linhaSelecionada.inputs.data.value;

            modal.style.display = 'block';
        });
    }

    
    if(btnExcluir){
        btnExcluir.addEventListener('click', () => {
            if (!linhaSelecionada) { 
                alert("Por favor, selecione um proprietário para excluir.");
                return;
            }
            if (confirm('Tem certeza que deseja excluir este proprietário?')) {
                
                linhaSelecionada.hiddenContainer.remove();
                linhaSelecionada.remove();
                limparSelecao(); 

                if (tabelaBody.rows.length === 0) {
                    tabelaBody.innerHTML = '<tr id="nenhumPropAnterior"><td colspan="4" style="text-align: center;">Nenhum proprietário anterior cadastrado.</td></tr>';
                }
            }
        });
    }

    
    
    
    

    function adicionarLinhaNaTabela(dados) {
        document.getElementById('nenhumPropAnterior')?.remove();

        const newRow = tabelaBody.insertRow();
        newRow.classList.add('clickable-row');
        newRow.innerHTML = `
            <td>${dados.nome}</td>
            <td>${dados.cpf_cnpj}</td>
            <td>${dados.cidade} / ${dados.uf}</td>
            <td>${formatarDataParaExibicao(dados.data)}</td>
        `;
        
        criarInputsHidden(newRow, dados);
    }
    
    function atualizarLinhaNaTabela(linha, dados) {
        linha.cells[0].textContent = dados.nome;
        linha.cells[1].textContent = dados.cpf_cnpj;
        linha.cells[2].textContent = `${dados.cidade} / ${dados.uf}`;
        linha.cells[3].textContent = formatarDataParaExibicao(dados.data);

        linha.inputs.nome.value = dados.nome;
        linha.inputs.cpf_cnpj.value = dados.cpf_cnpj;
        linha.inputs.cidade.value = dados.cidade;
        linha.inputs.uf.value = dados.uf;
        linha.inputs.data.value = dados.data;
    }

    function criarInputsHidden(linha, dados) {
        const container = document.createElement('div');
        container.classList.add('hidden-prop-inputs');
        
        const inputs = {
            nome: criarInput('pan_nome[]', dados.nome),
            cpf_cnpj: criarInput('pan_cpf_cnpj[]', dados.cpf_cnpj),
            cidade: criarInput('pan_cidade[]', dados.cidade),
            uf: criarInput('pan_uf[]', dados.uf),
            data: criarInput('pan_data_transferencia[]', dados.data)
        };

        for (const key in inputs) {
            container.appendChild(inputs[key]);
        }
        
        formVeiculo.appendChild(container);
        
        linha.hiddenContainer = container;
        linha.inputs = inputs;
    }

    function criarInput(name, value) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value || '';
        return input;
    }

    function inicializarLinhasExistentes() {
        tabelaBody.querySelectorAll('tr[data-id]').forEach(linha => {
            const dados = {
                nome: linha.cells[0].textContent,
                cpf_cnpj: linha.cells[1].textContent,
                cidade: linha.cells[2].textContent.split(' / ')[0],
                uf: linha.cells[2].textContent.split(' / ')[1],
                data: formatarDataParaISO(linha.cells[3].textContent)
            };
            criarInputsHidden(linha, dados);
        });
    }

    function formatarDataParaExibicao(data) {
        if (!data) return '';
        const [ano, mes, dia] = data.split('-');
        return `${dia}/${mes}/${ano}`;
    }

    function formatarDataParaISO(data) {
        if (!data) return '';
        const [dia, mes, ano] = data.split('/');
        return `${ano}-${mes}-${dia}`;
    }
    
    if(btnAdicionar){
        btnAdicionar.addEventListener('click', () => {
            editandoLinha = null;
            formPropAnterior.reset();
            tituloModal.textContent = "Adicionar Proprietário Anterior";
            modal.style.display = 'block';
        });
    }
    
    spanFechar.addEventListener('click', () => modal.style.display = 'none');
    window.addEventListener('click', (event) => {
        if (event.target == modal) modal.style.display = 'none';
    });

    formPropAnterior.addEventListener('submit', function(e) {
        e.preventDefault();
        const dados = {
            nome: this.pan_nome.value,
            cpf_cnpj: this.pan_cpf_cnpj.value,
            cidade: this.pan_cidade.value,
            uf: this.pan_uf.value,
            data: this.pan_data_transferencia.value
        };
        
        if (editandoLinha) {
            atualizarLinhaNaTabela(editandoLinha, dados);
        } else {
            adicionarLinhaNaTabela(dados);
        }

        modal.style.display = 'none';
        limparSelecao();
    });

    inicializarLinhasExistentes();
});