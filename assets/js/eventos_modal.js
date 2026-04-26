// Função para exibir o modal de eventos ao fazer login
document.addEventListener('DOMContentLoaded', function() {
    // Verificar se há eventos para mostrar (adicionado pelo PHP)
    if (typeof window.temEventosPendentes !== 'undefined' && window.temEventosPendentes) {
        // Criar o modal dinamicamente
        const modalHTML = `
        <div class="modal fade" id="eventosModal" tabindex="-1" aria-labelledby="eventosModalLabel" aria-hidden="true" data-bs-backdrop="static">
            <div class="modal-dialog modal-lg">
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
        
        // Carregar os eventos via AJAX primeiro
        carregarEventosModal();
    }
});

// Função para carregar os eventos no modal
function carregarEventosModal() {
    // Usar a variável global SITE_URL que deve ser definida no header.php
    fetch(window.SITE_URL + '/api/get_eventos_pendentes_new.php')
        .then(response => response.json())
        .then(data => {
            console.log('Resposta da API:', data);
            
            // Inicializar o modal
            const eventosModal = new bootstrap.Modal(document.getElementById('eventosModal'));
            
            if (data.success && data.eventos && data.eventos.length > 0) {
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
                    
                    // Definir classe de status
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
                            <button class="btn btn-sm btn-outline-primary" onclick="visualizarEvento(${evento.id})">
                                <i class="bi bi-eye"></i> Detalhes
                            </button>
                        </td>
                    `;
                    
                    tbody.appendChild(tr);
                });
                
                // Mostrar o modal apenas se houver eventos
                eventosModal.show();
            } else {
                // Se não houver eventos, não mostrar o modal
                console.log('Nenhum evento pendente encontrado. Modal não será exibido.');
                // Remover o modal do DOM
                const modalEl = document.getElementById('eventosModal');
                if (modalEl) {
                    modalEl.remove();
                }
            }
        })
        .catch(error => {
            console.error('Erro ao carregar eventos:', error);
            document.getElementById('tabelaEventos').innerHTML = `
                <tr>
                    <td colspan="5" class="text-center text-danger py-4">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <p class="mt-2 mb-0">Erro ao carregar eventos. Tente novamente mais tarde.</p>
                    </td>
                </tr>`;
            // Mostrar o modal mesmo com erro para informar o usuário
            const eventosModal = new bootstrap.Modal(document.getElementById('eventosModal'));
            eventosModal.show();
        });
}

// Função para visualizar detalhes do evento (já existente no eventos.js)
function visualizarEvento(eventoId) {
    // Implementação já existente no eventos.js
    // Esta função será chamada quando o usuário clicar em "Detalhes"
    // Certifique-se de que ela está disponível globalmente
    if (typeof window.visualizarEvento === 'function') {
        window.visualizarEvento(eventoId);
    }
}
