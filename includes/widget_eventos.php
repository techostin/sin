<?php
// Iniciar a sessão se ainda não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../classes/Lembrete.php';
require_once __DIR__ . '/../config/config.php';

// B-03: exibição de erros centralizada em APP_DEBUG (produção: só log).
require_once __DIR__ . '/../config/error_config.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    error_log('Usuário não está logado. Redirecionando para login.');
    // Não redirecionar aqui para não quebrar o layout, apenas registrar o erro
    echo '<!-- Usuário não está logado -->';
    return;
}

$lembrete = new Lembrete();
$eventos_proximos = $lembrete->getEventosProximos(14); // Próximos 14 dias
$eventos_hoje = $lembrete->getEventosHoje();
$total_eventos = count($eventos_proximos) + count($eventos_hoje);

// Log para depuração
error_log('Eventos de hoje: ' . print_r($eventos_hoje, true));
error_log('Eventos próximos: ' . print_r($eventos_proximos, true));
error_log('Total de eventos: ' . $total_eventos);

?>

<!-- Widget de Eventos Próximos -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <i class="bi bi-calendar-event-fill text-primary me-2"></i>
            <strong>Próximos Eventos</strong>
            <?php if ($total_eventos > 0): ?>
                <span class="badge bg-primary ms-2"><?php echo $total_eventos; ?></span>
            <?php endif; ?>
        </div>
        <a href="<?php echo SITE_URL; ?>/eventos" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-eye"></i> Ver Todos
        </a>
    </div>
    <div class="card-body">
        <?php if ($total_eventos > 0): ?>
            <div class="eventos-lista">
                <?php 
                // Combinar eventos de hoje e próximos, removendo duplicatas
                $todos_eventos = array_merge($eventos_hoje, $eventos_proximos);
                $eventos_unicos = [];
                $ids_vistos = [];
                
                foreach ($todos_eventos as $evento) {
                    if (!in_array($evento['id'], $ids_vistos)) {
                        $eventos_unicos[] = $evento;
                        $ids_vistos[] = $evento['id'];
                    }
                }
                
                // Ordenar por data
                usort($eventos_unicos, function($a, $b) {
                    return strtotime($a['data_evento']) - strtotime($b['data_evento']);
                });
                
                // Mostrar apenas os próximos 5 eventos
                $eventos_exibir = array_slice($eventos_unicos, 0, 5);
                
                foreach ($eventos_exibir as $evento): 
                    $dias_para_evento = $lembrete->diasParaEvento($evento['data_evento']);
                    $data_formatada = $lembrete->formatarDataEvento($evento['data_evento']);
                    
                    // Definir cor baseada na proximidade
                    if ($dias_para_evento == 0) {
                        $cor_borda = '#ffc107'; // Hoje - amarelo
                        $classe_badge = 'bg-warning';
                    } elseif ($dias_para_evento == 1) {
                        $cor_borda = '#fd7e14'; // Amanhã - laranja
                        $classe_badge = 'bg-warning';
                    } elseif ($dias_para_evento <= 3) {
                        $cor_borda = '#0dcaf0'; // Próximos 3 dias - azul claro
                        $classe_badge = 'bg-info';
                    } else {
                        $cor_borda = '#6c757d'; // Outros - cinza
                        $classe_badge = 'bg-secondary';
                    }
                ?>
                    <div class="evento-item d-flex align-items-start p-2 mb-2 rounded" 
                         style="border-left: 4px solid <?php echo $cor_borda; ?>; background-color: rgba(0,0,0,0.02);">
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1"><?php echo htmlspecialchars($evento['nome']); ?></h6>
                                    <div class="text-muted small">
                                        <i class="bi bi-clock"></i> <?php echo $data_formatada; ?>
                                    </div>
                                    <?php if (!empty($evento['local'])): ?>
                                        <div class="text-muted small">
                                            <i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($evento['local']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($evento['descricao'])): ?>
                                        <div class="text-muted small mt-1">
                                            <?php echo htmlspecialchars(substr($evento['descricao'], 0, 100)); ?>
                                            <?php if (strlen($evento['descricao']) > 100): ?>...<?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="ms-2 d-flex flex-column align-items-end">
                                    <span class="badge <?php echo $classe_badge; ?> small mb-1">
                                        <?php 
                                        if ($dias_para_evento == 0) {
                                            echo 'HOJE';
                                        } elseif ($dias_para_evento == 1) {
                                            echo 'AMANHÃ';
                                        } else {
                                            echo $dias_para_evento . ' dias';
                                        }
                                        ?>
                                    </span>
                                    <button type="button" 
                                            class="btn btn-sm btn-outline-primary" 
                                            title="Ver detalhes"
                                            onclick="visualizarEvento(<?php echo $evento['id']; ?>)">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <?php if (count($eventos_unicos) > 5): ?>
                    <div class="text-center mt-2">
                        <small class="text-muted">
                            E mais <?php echo count($eventos_unicos) - 5; ?> evento(s)...
                        </small>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-4">
                <i class="bi bi-calendar-x display-4 text-muted"></i>
                <p class="text-muted mt-2">Nenhum evento agendado para os próximos dias.</p>
                <a href="<?php echo SITE_URL; ?>/evento/novo" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-plus"></i> Agendar Evento
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.evento-item:hover {
    background-color: rgba(0,0,0,0.05) !important;
    transition: background-color 0.2s ease;
}

.evento-item h6 {
    font-size: 0.9rem;
    margin-bottom: 0.25rem;
}

.evento-item .badge {
    font-size: 0.7rem;
}
</style> 