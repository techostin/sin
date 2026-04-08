// Máscaras para campos numéricos
class InputMasks {
    constructor() {
        this.initMasks();
    }

    initMasks() {
        // Máscara para CPF (000.000.000-00)
        this.applyMask('cpf', '000.000.000-00');
        
        // Máscara para CNPJ (00.000.000/0000-00)
        this.applyMask('cnpj', '00.000.000/0000-00');
        
        // Máscara para RG (00.000.000-00)
        this.applyMaskByClass('mask-rg', '00.000.000-00');
        
        // Máscara para telefone ((00) 00000-0000)
        this.applyMask('telefone', '(00) 00000-0000');
        
        // Máscara para CEP (00000-000)
        this.applyMask('cep', '00000-000');
        
        // Máscara para valores monetários (R$ 0,00)
        this.applyMoneyMask('valor');
        this.applyMoneyMask('preco');
        this.applyMoneyMask('custo');
        
        // Máscara para números decimais (0,00)
        this.applyDecimalMask('area_plantada');
        this.applyDecimalMask('quantidade');
        this.applyDecimalMask('quantidade_produzida');
        this.applyDecimalMask('quantidade_estoque');
        this.applyDecimalMask('estoq_anterior');
        this.applyDecimalMask('quant_recebida');
        
        // Máscara para números inteiros
        this.applyIntegerMask('numero');
    }

    applyMask(fieldName, mask) {
        const elements = document.querySelectorAll(`[name="${fieldName}"], [id="${fieldName}"]`);
        elements.forEach(element => {
            element.addEventListener('input', (e) => {
                let value = e.target.value.replace(/\D/g, '');
                let result = '';
                let maskIndex = 0;
                
                for (let i = 0; i < mask.length && maskIndex < value.length; i++) {
                    if (mask[i] === '0') {
                        result += value[maskIndex];
                        maskIndex++;
                    } else {
                        result += mask[i];
                    }
                }
                
                e.target.value = result;
            });
        });
    }

    applyMaskByClass(className, mask) {
        const elements = document.querySelectorAll(`.${className}`);
        elements.forEach(element => {
            element.addEventListener('input', (e) => {
                let value = e.target.value.replace(/\D/g, '');
                let result = '';
                let maskIndex = 0;
                
                for (let i = 0; i < mask.length && maskIndex < value.length; i++) {
                    if (mask[i] === '0') {
                        result += value[maskIndex];
                        maskIndex++;
                    } else {
                        result += mask[i];
                    }
                }
                
                e.target.value = result;
            });
        });
    }

    applyMoneyMask(fieldName) {
        const elements = document.querySelectorAll(`[name*="${fieldName}"], [id*="${fieldName}"]`);
        elements.forEach(element => {
            element.addEventListener('input', (e) => {
                let value = e.target.value.replace(/\D/g, '');
                value = (parseFloat(value) / 100).toFixed(2);
                value = value.replace('.', ',');
                value = value.replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1.');
                e.target.value = value;
            });
            
            // Formatar valor inicial se existir
            if (element.value) {
                let value = element.value.replace(/\D/g, '');
                value = (parseFloat(value) / 100).toFixed(2);
                value = value.replace('.', ',');
                value = value.replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1.');
                element.value = value;
            }
        });
    }

    applyDecimalMask(fieldName) {
        const elements = document.querySelectorAll(`[name*="${fieldName}"], [id*="${fieldName}"]`);
        elements.forEach(element => {
            element.addEventListener('input', (e) => {
                let value = e.target.value.replace(/[^\d,]/g, '');
                value = value.replace(',', '.');
                if (value.includes('.')) {
                    let parts = value.split('.');
                    if (parts[1].length > 2) {
                        parts[1] = parts[1].substring(0, 2);
                    }
                    value = parts.join('.');
                }
                e.target.value = value;
            });
        });
    }

    applyIntegerMask(fieldName) {
        const elements = document.querySelectorAll(`[name*="${fieldName}"], [id*="${fieldName}"]`);
        elements.forEach(element => {
            element.addEventListener('input', (e) => {
                e.target.value = e.target.value.replace(/\D/g, '');
            });
        });
    }

    // Função para limpar máscara antes de enviar formulário
    static clearMasks(form) {
        const maskedFields = form.querySelectorAll('input[name*="cpf"], input[name*="cnpj"], input[name*="rg"], input[name*="telefone"], input[name*="cep"], input[name*="valor"], input[name*="preco"], input[name*="custo"]');
        
        maskedFields.forEach(field => {
            field.value = field.value.replace(/\D/g, '');
        });
    }

    // Função para formatar CPF
    static formatCPF(cpf) {
        cpf = cpf.replace(/\D/g, '');
        return cpf.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
    }

    // Função para formatar CNPJ
    static formatCNPJ(cnpj) {
        cnpj = cnpj.replace(/\D/g, '');
        return cnpj.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5');
    }

    // Função para formatar telefone
    static formatPhone(phone) {
        phone = phone.replace(/\D/g, '');
        if (phone.length === 11) {
            return phone.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
        } else if (phone.length === 10) {
            return phone.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3');
        }
        return phone;
    }

    // Função para formatar CEP
    static formatCEP(cep) {
        cep = cep.replace(/\D/g, '');
        return cep.replace(/(\d{5})(\d{3})/, '$1-$2');
    }

    // Função para formatar valor monetário
    static formatMoney(value) {
        value = value.replace(/\D/g, '');
        value = (parseFloat(value) / 100).toFixed(2);
        value = value.replace('.', ',');
        return value.replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1.');
    }
}

// Inicializar máscaras quando o DOM estiver carregado
document.addEventListener('DOMContentLoaded', function() {
    new InputMasks();
    
    // Aplicar máscaras em formulários existentes
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            InputMasks.clearMasks(this);
        });
    });
}); 