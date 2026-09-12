<?php
require_once __DIR__ . '/../classes/Lembrete.php';

$lembrete = new Lembrete();
$lembretes = $lembrete->verificarLembretes(7); // Verificar próximos 7 dias

// Só exibir lembretes se houver algum
if (!empty($lembretes)):
?>
<!-- Lembretes de Eventos -->
<div class="lembretes-container" style="position: fixed; top: 20px; right: 20px; z-index: 1050; max-width: 400px;">
    <?php foreach ($lembretes as $tipo => $lembrete_info): ?>
        <div class="alert <?php echo $lembrete_info['classe']; ?> alert-dismissible fade show shadow-sm" role="alert">
            <div class="d-flex align-items-start">
                <i class="bi <?php echo $lembrete_info['icone']; ?> fs-4 me-2 mt-1"></i>
                <div class="flex-grow-1">
                    <h6 class="alert-heading mb-1"><?php echo $lembrete_info['titulo']; ?></h6>
                    <p class="mb-2 small"><?php echo $lembrete_info['mensagem']; ?></p>
                    
                    <?php if (!empty($lembrete_info['eventos'])): ?>
                        <div class="eventos-lista">
                            <?php foreach ($lembrete_info['eventos'] as $evento): ?>
                                <div class="evento-item border-start border-2 ps-2 mb-1" style="border-color: <?php echo $tipo === 'hoje' ? '#ffc107' : ($tipo === 'atrasados' ? '#dc3545' : '#0dcaf0'); ?> !important;">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <strong class="small"><?php echo htmlspecialchars($evento['nome']); ?></strong>
                                            <div class="text-muted small">
                                                <i class="bi bi-clock"></i> 
                                                <?php echo $lembrete->formatarDataEvento($evento['data_evento']); ?>
                                            </div>
                                            <?php if (!empty($evento['local'])): ?>
                                                <div class="text-muted small">
                                                    <i class="bi bi-geo-alt"></i> 
                                                    <?php echo htmlspecialchars($evento['local']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="ms-2">
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-primary" 
                                                    title="Ver detalhes"
                                                    onclick="visualizarEvento(<?php echo $evento['id']; ?>)">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
    <?php endforeach; ?>
</div>

<style>
.lembretes-container .alert {
    margin-bottom: 10px;
    border: none;
    border-radius: 8px;
}

.lembretes-container .evento-item {
    background-color: rgba(255, 255, 255, 0.1);
    border-radius: 4px;
    padding: 4px 8px;
}

.lembretes-container .evento-item:hover {
    background-color: rgba(255, 255, 255, 0.2);
}

.lembretes-container .btn-close {
    position: absolute;
    top: 8px;
    right: 8px;
}

/* Animação de entrada */
@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

.lembretes-container .alert {
    animation: slideInRight 0.5s ease-out;
}

/* Responsividade */
@media (max-width: 768px) {
    .lembretes-container {
        position: fixed;
        top: 10px;
        right: 10px;
        left: 10px;
        max-width: none;
    }
}
</style>

<script>
// Auto-hide lembretes após 10 segundos (exceto eventos de hoje)
document.addEventListener('DOMContentLoaded', function() {
    const lembretes = document.querySelectorAll('.lembretes-container .alert');
    
    lembretes.forEach(function(lembrete) {
        // Não auto-hide eventos de hoje ou atrasados
        if (!lembrete.classList.contains('alert-warning') && !lembrete.classList.contains('alert-danger')) {
            setTimeout(function() {
                const bsAlert = new bootstrap.Alert(lembrete);
                bsAlert.close();
            }, 10000); // 10 segundos
        }
    });
    
    // Salvar no localStorage que o usuário viu os lembretes
    const hoje = new Date().toDateString();
    const lembretesVistos = JSON.parse(localStorage.getItem('lembretesVistos') || '{}');
    lembretesVistos[hoje] = true;
    localStorage.setItem('lembretesVistos', JSON.stringify(lembretesVistos));
});

// Função para visualizar detalhes do evento
function visualizarEvento(eventoId) {
    // Mostrar loading no modal
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEvento'));
    const modalBody = document.getElementById('modalEventoBody');
    const modalTitle = document.getElementById('modalEventoTitle');
    
    modalTitle.innerHTML = '<i class="bi bi-hourglass-split"></i> Carregando...';
    modalBody.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"></div><p class="mt-2">Carregando detalhes do evento...</p></div>';
    modal.show();
    
    // Fazer requisição AJAX
    fetch('<?php echo SITE_URL; ?>/evento/' + eventoId, {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            modalTitle.innerHTML = data.titulo;
            modalBody.innerHTML = data.html;
        } else {
            modalTitle.innerHTML = 'Erro';
            modalBody.innerHTML = '<div class="alert alert-danger">Erro ao carregar detalhes do evento: ' + (data.erro || 'Erro desconhecido') + '</div>';
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        modalTitle.innerHTML = 'Erro';
        modalBody.innerHTML = '<div class="alert alert-danger">Erro ao carregar detalhes do evento. Tente novamente.</div>';
    });
}
</script>

<!-- Modal para visualizar detalhes do evento -->
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
<?php endif; ?> 