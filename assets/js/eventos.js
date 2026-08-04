// Função para visualizar detalhes do evento
function visualizarEvento(eventoId) {
    console.log('Função visualizarEvento chamada com ID:', eventoId);
    
    // Verificar se o modal existe
    let modal = document.getElementById('modalEvento');
    if (!modal) {
        console.log('Modal não encontrado, criando...');
        criarModalEvento();
        modal = document.getElementById('modalEvento');
    }
    
    const modalBody = document.getElementById('modalEventoBody');
    const modalTitle = document.getElementById('modalEventoTitle');
    
    if (!modalBody || !modalTitle) {
        console.error('Elementos do modal não encontrados');
        return;
    }
    
    modalTitle.innerHTML = '<i class="bi bi-hourglass-split"></i> Carregando...';
    modalBody.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"></div><p class="mt-2">Carregando detalhes do evento...</p></div>';
    
    const bootstrapModal = new bootstrap.Modal(modal);
    bootstrapModal.show();
    
    // URL da requisição
    const url = window.SITE_URL + '/evento/' + eventoId;
    console.log('Fazendo requisição para:', url);
    
    // Fazer requisição AJAX
    fetch(url, {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        console.log('Response status:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('Dados recebidos:', data);
        if (data.success) {
            modalTitle.innerHTML = data.titulo;
            modalBody.innerHTML = data.html;
        } else {
            modalTitle.innerHTML = 'Erro';
            modalBody.innerHTML = '<div class="alert alert-danger">Erro ao carregar detalhes do evento: ' + (data.erro || 'Erro desconhecido') + '</div>';
        }
    })
    .catch(error => {
        console.error('Erro na requisição:', error);
        modalTitle.innerHTML = 'Erro';
        modalBody.innerHTML = '<div class="alert alert-danger">Erro ao carregar detalhes do evento. Tente novamente.</div>';
    });
}

// Função para criar o modal se não existir
function criarModalEvento() {
    if (!document.getElementById('modalEvento')) {
        const modalHTML = `
            <div class="modal fade" id="modalEvento" tabindex="-1" aria-labelledby="modalEventoTitle" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalEventoTitle">
                                <i class="bi bi-calendar-event-fill text-primary me-2"></i>
                                Detalhes do Evento
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                        </div>
                        <div class="modal-body" id="modalEventoBody">
                            <!-- Conteúdo será carregado via AJAX -->
                        </div>
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        console.log('Modal criado com sucesso');
    }
}

// Inicializar quando o DOM estiver pronto
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM carregado, inicializando modal de eventos...');
    criarModalEvento();
});

/**
 * Função para confirmar ou cancelar presença no evento
 * @param {number} eventoId 
 */
function togglePresenca(eventoId) {
    const url = window.SITE_URL + '/api/toggle_presenca.php';
    const formData = new FormData();
    formData.append('evento_id', eventoId);
    formData.append('csrf_token', window.CSRF_TOKEN);
    
    // Pegar o botão que disparou o evento (se houver)
    const btn = event ? event.currentTarget : null;
    if (btn) btn.disabled = true;
    
    fetch(url, {
        method: 'POST',
        headers: Object.assign({'X-Requested-With': 'XMLHttpRequest'}, window.csrfHeaders()),
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Mostrar mensagem de confirmação no modal
            const msgContainer = document.getElementById('presenca-msg-container');
            if (msgContainer) {
                const alertClass = data.acao === 'adicionado' ? 'alert-success' : 'alert-warning';
                msgContainer.innerHTML = `
                    <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                        <i class="bi ${data.acao === 'adicionado' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'} me-2"></i>
                        ${data.mensagem}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>`;
            }
            
            // Recarregar os detalhes do evento para atualizar o botão e a contagem após um curto delay
            // para que o usuário possa ver a mensagem
            setTimeout(() => {
                visualizarEvento(eventoId);
            }, 1500);
        } else {
            alert('Erro: ' + (data.erro || 'Erro desconhecido ao atualizar presença.'));
            if (btn) btn.disabled = false;
        }
    })
    .catch(error => {
        console.error('Erro ao alternar presença:', error);
        alert('Erro ao processar sua solicitação. Tente novamente.');
        if (btn) btn.disabled = false;
    });
}