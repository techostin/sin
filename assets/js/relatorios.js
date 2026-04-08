/**
 * Função para gerar relatório em PDF
 * @param {string} tipo - Tipo de relatório (associados, atividades, etc.)
 * @param {object} filtros - Objeto com os filtros a serem aplicados
 */
function gerarRelatorioPDF(tipo, filtros = {}) {
    try {
        // Construir a URL do relatório
        const baseUrl = (window.SITE_URL || '') + '/relatorios/' + tipo + '.php';
        const url = new URL(baseUrl, window.location.origin);
        url.searchParams.append('tipo', 'pdf');
        
        // Adicionar filtros à URL
        Object.keys(filtros).forEach(key => {
            if (filtros[key] !== '' && filtros[key] !== null && filtros[key] !== undefined) {
                url.searchParams.append(key, filtros[key]);
            }
        });
        
        // Abrir em uma nova aba para visualização
        window.open(url, '_blank');
        
    } catch (error) {
        console.error('Erro ao gerar relatório:', error);
        alert('Ocorreu um erro ao gerar o relatório. Por favor, tente novamente.');
    }
}

/**
 * Adiciona os listeners para os botões de geração de relatórios
 */
document.addEventListener('DOMContentLoaded', function() {
    // Adiciona evento aos botões de geração de PDF
    document.querySelectorAll('.btn-gerar-pdf').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            
            const tipo = this.dataset.tipo || 'associados';
            
            // Coletar filtros do formulário, se existir
            let filtros = {};
            const form = document.querySelector('form.filtros-relatorio');
            
            if (form) {
                const formData = new FormData(form);
                formData.forEach((value, key) => {
                    if (value) filtros[key] = value;
                });
            }
            
            // Chamar a função para gerar o PDF
            gerarRelatorioPDF(tipo, filtros);
        });
    });
    
    // Adiciona evento aos botões de exportar para Excel
    document.querySelectorAll('.btn-exportar-excel').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            
            const tipo = this.dataset.tipo || 'associados';
            const baseUrl = (window.SITE_URL || '') + '/relatorios/' + tipo + '.php';
            const url = new URL(baseUrl, window.location.origin);
            url.searchParams.append('tipo', 'excel');
            
            // Coletar filtros do formulário, se existir
            const form = document.querySelector('form.filtros-relatorio');
            if (form) {
                const formData = new FormData(form);
                formData.forEach((value, key) => {
                    if (value) url.searchParams.append(key, value);
                });
            }
            
            // Abrir em nova aba (Excel download)
            window.open(url.toString(), '_blank');
        });
    });
});
