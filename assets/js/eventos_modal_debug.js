// Versão com debug do modal de eventos
console.log('[Eventos Modal] Script carregado');

document.addEventListener('DOMContentLoaded', function() {
    console.log('[Eventos Modal] DOM carregado');
    console.log('[Eventos Modal] temEventosPendentes:', typeof window.temEventosPendentes !== 'undefined' ? window.temEventosPendentes : 'undefined');
    
    // Verificar se há eventos para mostrar (adicionado pelo PHP)
    if (typeof window.temEventosPendentes !== 'undefined' && window.temEventosPendentes) {
        console.log('[Eventos Modal] Iniciando criação do modal');
        
        // Criar o modal dinamicamente
        const modalHTML = `
        <div class="modal fade" id="eventosModal" tabindex="-1" aria-labelledby="eventosModalLabel" aria-hidden="true" data-bs-backdrop="static">
            <div class="modal-dialog modal-lg" style="max-width: 1456px;">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="eventosModalLabel">
                            <i class="bi bi-calendar-event me-2"></i>Seus Próximos Eventos
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                    <div class="modal-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Evento</th>
                                        <th>Data e Hora</th>
                                        <th>Local</th>
                                        <th>Status</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody id="tabelaEventos">
                                    <tr><td colspan="5" class="text-center py-4">
                                        <div class="spinner-border text-primary" role="status"></div>
                                        <p class="mt-2 mb-0">Carregando eventos...</p>
                                    </td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                        <a href="` + window.SITE_URL + `/eventos" class="btn btn-primary">
                            <i class="bi bi-calendar-week"></i> Ver Todos os Eventos
                        </a>
                    </div>
                </div>
            </div>
        </div>`;
        
        // Adicionar o modal ao final do body
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        console.log('[Eventos Modal] HTML do modal adicionado ao DOM');
        
        // Carregar os eventos via AJAX primeiro
        carregarEventosModal();
    } else {
        console.log('[Eventos Modal] Não há eventos para mostrar (temEventosPendentes não definido ou false)');
    }
});

// Função para carregar os eventos no modal
function carregarEventosModal() {
    console.log('[Eventos Modal] Iniciando carregamento de eventos');
    console.log('[Eventos Modal] SITE_URL:', window.SITE_URL);
    
    const url = window.SITE_URL + '/api/get_eventos_pendentes_new.php';
    console.log('[Eventos Modal] URL da API:', url);
    
    fetch(url)
        .then(response => {
            console.log('[Eventos Modal] Resposta HTTP recebida:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('[Eventos Modal] Dados recebidos:', data);
            
            const modalEl = document.getElementById('eventosModal');
            if (!modalEl) {
                console.error('[Eventos Modal] Elemento do modal não encontrado!');
                return;
            }
            
            // Inicializar o modal
            const eventosModal = bootstrap.Modal.getOrCreateInstance(modalEl);
            
            if (data.success && data.eventos && data.eventos.length > 0) {
                console.log('[Eventos Modal] Renderizando', data.eventos.length, 'eventos');
                const tbody = document.getElementById('tabelaEventos');
                tbody.innerHTML = '';
                
                data.eventos.forEach(evento => {
                    const tr = document.createElement('tr');
                    const dataEvento = new Date(evento.data_evento);
                    const dataFormatada = dataEvento.toLocaleString('pt-BR', {
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                    
                    let statusClass = 'badge bg-secondary';
                    let statusText = 'Agendado';
                    
                    if (evento.status === 'Cancelado') {
                        statusClass = 'badge bg-danger';
                        statusText = 'Cancelado';
                    } else if (evento.status === 'Realizado') {
                        statusClass = 'badge bg-success';
                        statusText = 'Realizado';
                    } else if (new Date(evento.data_evento) < new Date()) {
                        statusClass = 'badge bg-warning text-dark';
                        statusText = 'Atrasado';
                    }
                    
                    tr.innerHTML = `
                        <td>
                            <strong>${evento.nome}</strong>
                            ${evento.descricao ? `<small class="d-block text-muted">${evento.descricao.substring(0, 50)}${evento.descricao.length > 50 ? '...' : ''}</small>` : ''}
                        </td>
                        <td>${dataFormatada}</td>
                        <td>${evento.local || '-'}</td>
                        <td><span class="${statusClass}">${statusText}</span></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary mb-1" onclick="visualizarEvento(${evento.id})">
                                <i class="bi bi-eye"></i> Detalhes
                            </button>
                            <button class="btn btn-sm btn-success" onclick="confirmarPresencaModal(this, ${evento.id})">
                                <i class="bi bi-check-circle"></i> Confirmar Presença
                            </button>
                        </td>
                    `;
                    
                    tbody.appendChild(tr);
                });
                
                console.log('[Eventos Modal] Mostrando modal');
                eventosModal.show();
            } else {
                console.log('[Eventos Modal] Nenhum evento encontrado na resposta da API');
                console.log('[Eventos Modal] success:', data.success);
                console.log('[Eventos Modal] eventos:', data.eventos);
                
                // Remover o modal do DOM
                modalEl.remove();
            }
        })
        .catch(error => {
            console.error('[Eventos Modal] Erro ao carregar eventos:', error);
            const tbody = document.getElementById('tabelaEventos');
            if (tbody) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="5" class="text-center text-danger py-4">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <p class="mt-2 mb-0">Erro ao carregar eventos: ${error.message}</p>
                            <p class="small text-muted">Verifique o console para mais detalhes</p>
                        </td>
                    </tr>`;
            }
            
            const modalEl = document.getElementById('eventosModal');
            if (modalEl) {
                // Inicializar o modal
                const eventosModal = bootstrap.Modal.getOrCreateInstance(modalEl);
                eventosModal.show();
            }
        });
}

// Função para confirmar presença no evento
function confirmarPresencaModal(btn, eventoId) {
    console.log('[Eventos Modal] Confirmando presença no evento:', eventoId);
    
    // Desabilitar botão durante a requisição
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Confirmando...';
    
    fetch(window.SITE_URL + '/api/toggle_presenca.php', {
        method: 'POST',
        headers: Object.assign({
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        }, window.csrfHeaders()),
        body: 'evento_id=' + encodeURIComponent(eventoId) + '&csrf_token=' + encodeURIComponent(window.CSRF_TOKEN)
    })
    .then(response => response.json())
    .then(data => {
        console.log('[Eventos Modal] Resposta da confirmação:', data);
        
        if (data.success && data.acao === 'adicionado') {
            // Sucesso - atualizar botão
            btn.classList.remove('btn-success');
            btn.classList.add('btn-info');
            btn.innerHTML = '<i class="bi bi-check-circle-fill"></i> Presença Confirmada';
            btn.disabled = true;
            
            // Mostrar mensagem de sucesso
            alert('Sua presença foi confirmada!');
        } else if (data.success && data.acao === 'removido') {
            // Presença cancelada
            btn.classList.remove('btn-info');
            btn.classList.add('btn-success');
            btn.innerHTML = '<i class="bi bi-check-circle"></i> Confirmar Presença';
            btn.disabled = false;
            
            alert('Sua presença foi cancelada.');
        } else {
            // Erro
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-circle"></i> Confirmar Presença';
            alert('Erro: ' + (data.erro || 'Não foi possível confirmar presença'));
        }
    })
    .catch(error => {
        console.error('[Eventos Modal] Erro ao confirmar presença:', error);
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-circle"></i> Confirmar Presença';
        alert('Erro ao confirmar presença. Tente novamente.');
    });
}

// Função para visualizar detalhes do evento
function visualizarEvento(eventoId) {
    console.log('[Eventos Modal] Visualizando evento:', eventoId);
    if (typeof window.visualizarEvento === 'function') {
        window.visualizarEvento(eventoId);
    }
}
