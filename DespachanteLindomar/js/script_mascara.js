document.addEventListener('DOMContentLoaded', function() {
    /**
     * Máscara para CPF/CNPJ 
     * @param {string} value
     * @returns {string} 
     */
    function maskCpfCnpj(value) {
        value = value.replace(/\D/g, ""); 
        if (value.length <= 11) {
            // CPF: 000.000.000-00
            value = value.replace(/(\d{3})(\d)/, "$1.$2");
            value = value.replace(/(\d{3})(\d)/, "$1.$2");
            value = value.replace(/(\d{3})(\d{1,2})$/, "$1-$2");
        } else {
            // CNPJ: 00.000.000/0000-00
            value = value.replace(/^(\d{2})(\d)/, "$1.$2");
            value = value.replace(/^(\d{2})\.(\d{3})(\d)/, "$1.$2.$3");
            value = value.replace(/\.(\d{3})(\d)/, ".$1/$2");
            value = value.replace(/(\d{4})(\d)/, "$1-$2");
        }
        return value;
    }

    /**
     * Máscara para CEP.
     * @param {string} value 
     * @returns {string} 
     */
    function maskCep(value) {
        value = value.replace(/\D/g, ""); 
        value = value.replace(/^(\d{5})(\d)/, "$1-$2"); 
        return value;
    }

    /**
     * Máscara para Telefone
     * @param {string} value 
     * @returns {string}
     */
    function maskPhone(value) {
        value = value.replace(/\D/g, ""); 
        value = value.replace(/^(\d{2})(\d)/g, "($1) $2"); 
        value = value.replace(/(\d)(\d{4})$/, "$1-$2");
        return value;
    }


    // --- APLICAÇÃO DAS MÁSCARAS ---

    const cpfCnpjInput = document.getElementById('CPF_CNPJ');
    if (cpfCnpjInput) {
        cpfCnpjInput.addEventListener('input', (e) => {
            e.target.value = maskCpfCnpj(e.target.value);
        });
    }

    const cpfPropInput = document.getElementById('PROPRIETARIO_CPF');
    if (cpfPropInput) {
        cpfPropInput.addEventListener('input', (e) => {
            e.target.value = maskCpfCnpj(e.target.value);
        });
    }

    const cpf1Input = document.getElementById('FIV_COMP_CPF_CNPJ');
    if (cpf1Input) {
        cpf1Input.addEventListener('input', (e) => {
            e.target.value = maskCpfCnpj(e.target.value);
        });
    }

     const cpf2Input = document.getElementById('FIV_VENDEDOR_CPF_CNPJ');
    if (cpf2Input) {
        cpf2Input.addEventListener('input', (e) => {
            e.target.value = maskCpfCnpj(e.target.value);
        });
    }

    const cepInput = document.getElementById('CEP_LOC');
    if (cepInput) {
        cepInput.addEventListener('input', (e) => {
            e.target.value = maskCep(e.target.value);
        });
    }

     const cep1Input = document.getElementById('CEP');
    if (cep1Input) {
        cep1Input.addEventListener('input', (e) => {
            e.target.value = maskCep(e.target.value);
        });
    }

     const cep2Input = document.getElementById('POV_CEP');
    if (cep2Input) {
        cep2Input.addEventListener('input', (e) => {
            e.target.value = maskCep(e.target.value);
        });
    }

      const cep3Input = document.getElementById('FIV_VEND_CEP');
    if (cep3Input) {
        cep3Input.addEventListener('input', (e) => {
            e.target.value = maskCep(e.target.value);
        });
    }

    const celularInput = document.getElementById('CLI_PESSOA_CONTATO_FONE');
    if (celularInput) {
        celularInput.addEventListener('input', (e) => {
            e.target.value = maskPhone(e.target.value);
        });
    }

    const celular1Input = document.getElementById('CELULAR');
    if (celular1Input) {
        celular1Input.addEventListener('input', (e) => {
            e.target.value = maskPhone(e.target.value);
        });
    }

    const celular2Input = document.getElementById('FIV_VEND_CELULAR');
    if (celular2Input) {
        celular2Input.addEventListener('input', (e) => {
            e.target.value = maskPhone(e.target.value);
        });
    }

    
    const celular3Input = document.getElementById('FIV_COMP_CELULAR');
    if (celular3Input) {
        celular3Input.addEventListener('input', (e) => {
            e.target.value = maskPhone(e.target.value);
        });
    }

    const residencialInput = document.getElementById('TELEFONE_RESIDENCIAL');
    if (residencialInput) {
        residencialInput.addEventListener('input', (e) => {
            e.target.value = maskPhone(e.target.value);
        });
    }

    const telefoneInput = document.getElementById('TELEFONE');
    if (telefoneInput) {
        telefoneInput.addEventListener('input', (e) => {
            e.target.value = maskPhone(e.target.value);
        });
    }

    const residencial2Input = document.getElementById('FIV_VEND_TELEFONE');
    if (residencial2Input) {
        residencial2Input.addEventListener('input', (e) => {
            e.target.value = maskPhone(e.target.value);
        });
    }

      const residencial3Input = document.getElementById('FIV_COMP_TELEFONE');
    if (residencial3Input) {
        residencial3Input.addEventListener('input', (e) => {
            e.target.value = maskPhone(e.target.value);
        });
    }

    const povtelefoneInput = document.getElementById('POV_TELEFONE');
    if (povtelefoneInput) {
        povtelefoneInput.addEventListener('input', (e) => {
            e.target.value = maskPhone(e.target.value);
        });
    }
});