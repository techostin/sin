<?php
// Iniciar a sessão se ainda não estiver ativa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Incluir configuração do banco
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/User.php';

// Verificar autenticação do usuário
$user = new User();
if (!$user->isLoggedIn()) {
    header('Location: ' . SITE_URL . '/login');
    exit();
}

// Título da página
$pageTitle = 'Relatórios';

// Incluir cabeçalho
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/menu.php';

// Verificar permissões do usuário
$isAdmin = $user->isAdmin();
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="h3 mb-0"><?php echo $pageTitle; ?></h1>
            <p class="text-muted">Selecione o relatório que deseja gerar.</p>
        </div>
    </div>
    
    <div class="row">
        <!-- Relatório de Atividades -->
        <div class="col-12 col-md-6 col-lg-4 mb-4">
            <div class="card h-100 border-primary">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-calendar-check"></i> Atividades
                    </h5>
                </div>
                <div class="card-body">
                    <p class="card-text">Gere relatórios detalhados das atividades realizadas, com filtros por data, status, categoria e responsável.</p>
                    <ul class="mb-3">
                        <li>Atividades por período</li>
                        <li>Status de conclusão</li>
                        <li>Análise de produtividade</li>
                        <li>Exportação para PDF/Excel</li>
                    </ul>
                </div>
                <div class="card-footer bg-transparent">
                    <a href="<?php echo SITE_URL; ?>/relatorios?tipo=atividades" class="btn btn-primary stretched-link">
                        Acessar Relatório <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Relatório de Propriedades -->
        <div class="col-12 col-md-6 col-lg-4 mb-4">
            <div class="card h-100 border-success">
                <div class="card-header bg-success text-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-house"></i> Propriedades
                    </h5>
                </div>
                <div class="card-body">
                    <p class="card-text">Relatórios completos sobre as propriedades, incluindo áreas, localizações e atividades relacionadas.</p>
                    <ul class="mb-3">
                        <li>Dados cadastrais</li>
                        <li>Áreas e divisões</li>
                        <li>Atividades por propriedade</li>
                        <li>Mapas e localizações</li>
                    </ul>
                </div>
                <div class="card-footer bg-transparent">
                    <a href="<?php echo SITE_URL; ?>/relatorios?tipo=propriedades" class="btn btn-success stretched-link">
                        Acessar Relatório <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Relatório de Insumos -->
        <div class="col-12 col-md-6 col-lg-4 mb-4">
            <div class="card h-100 border-warning">
                <div class="card-header bg-warning text-dark">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-box-seam"></i> Insumos
                    </h5>
                </div>
                <div class="card-body">
                    <p class="card-text">Controle e análise de estoque de insumos, com alertas de validade e níveis mínimos.</p>
                    <ul class="mb-3">
                        <li>Níveis de estoque</li>
                        <li>Validade de produtos</li>
                        <li>Entradas e saídas</li>
                        <li>Análise de consumo</li>
                    </ul>
                </div>
                <div class="card-footer bg-transparent">
                    <a href="<?php echo SITE_URL; ?>/relatorios?tipo=insumos" class="btn btn-warning stretched-link">
                        Acessar Relatório <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Relatório Financeiro -->
        <div class="col-12 col-md-6 col-lg-4 mb-4">
            <div class="card h-100 border-info">
                <div class="card-header bg-info text-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-cash-stack"></i> Financeiro
                    </h5>
                </div>
                <div class="card-body">
                    <p class="card-text">Relatórios financeiros completos, incluindo receitas, despesas, fluxo de caixa e projeções.</p>
                    <ul class="mb-3">
                        <li>Fluxo de caixa</li>
                        <li>Receitas x Despesas</li>
                        <li>Contas a pagar/receber</li>
                        <li>Relatórios fiscais</li>
                    </ul>
                </div>
                <div class="card-footer bg-transparent">
                    <a href="<?php echo SITE_URL; ?>/relatorios?tipo=financeiro" class="btn btn-info text-white stretched-link">
                        Acessar Relatório <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Relatório Personalizado -->
        <div class="col-12 col-md-6 col-lg-4 mb-4">
            <div class="card h-100 border-secondary">
                <div class="card-header bg-secondary text-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-sliders"></i> Personalizado
                    </h5>
                </div>
                <div class="card-body">
                    <p class="card-text">Crie seus próprios relatórios personalizados, selecionando os campos e filtros desejados.</p>
                    <ul class="mb-3">
                        <li>Seleção de campos</li>
                        <li>Filtros avançados</li>
                        <li>Diferentes formatos de saída</li>
                        <li>Salvar modelos</li>
                    </ul>
                </div>
                <div class="card-footer bg-transparent">
                    <a href="<?php echo SITE_URL; ?>/relatorios?tipo=personalizado" class="btn btn-secondary stretched-link">
                        Criar Relatório <i class="bi bi-plus-circle"></i>
                    </a>
                </div>
            </div>
        </div>
        
        <?php if ($isAdmin): ?>
        <!-- Relatório de Usuários (apenas para administradores) -->
        <div class="col-12 col-md-6 col-lg-4 mb-4">
            <div class="card h-100 border-danger">
                <div class="card-header bg-danger text-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-people"></i> Usuários e Acessos
                    </h5>
                </div>
                <div class="card-body">
                    <p class="card-text">Relatórios de acesso e atividades dos usuários do sistema.</p>
                    <ul class="mb-3">
                        <li>Logs de acesso</li>
                        <li>Atividades por usuário</li>
                        <li>Permissões e níveis de acesso</li>
                        <li>Relatórios de segurança</li>
                    </ul>
                </div>
                <div class="card-footer bg-transparent">
                    <a href="#" class="btn btn-danger disabled stretched-link">
                        Em Desenvolvimento <i class="bi bi-tools"></i>
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Seção de relatórios recentes -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-clock-history"></i> Relatórios Recentes
                    </h5>
                </div>
                <div class="card-body">
                    <?php
                    // Simulando relatórios recentes (substituir por dados reais do banco de dados)
                    $relatoriosRecentes = [
                        [
                            'tipo' => 'financeiro',
                            'titulo' => 'Fluxo de Caixa Mensal',
                            'data' => date('d/m/Y H:i', strtotime('-2 days')),
                            'usuario' => $user->getNome(),
                            'downloads' => 3
                        ],
                        [
                            'tipo' => 'atividades',
                            'titulo' => 'Atividades da Semana',
                            'data' => date('d/m/Y H:i', strtotime('-5 days')),
                            'usuario' => 'João Silva',
                            'downloads' => 2
                        ],
                        [
                            'tipo' => 'insumos',
                            'titulo' => 'Estoque Crítico',
                            'data' => date('d/m/Y H:i', strtotime('-1 week')),
                            'usuario' => 'Maria Oliveira',
                            'downloads' => 5
                        ]
                    ];
                    
                    if (count($relatoriosRecentes) > 0):
                    ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Relatório</th>
                                    <th>Data de Geração</th>
                                    <th>Gerado por</th>
                                    <th>Downloads</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($relatoriosRecentes as $relatorio): 
                                    $icones = [
                                        'atividades' => 'calendar-check',
                                        'propriedades' => 'house',
                                        'insumos' => 'box-seam',
                                        'financeiro' => 'cash-stack',
                                        'personalizado' => 'sliders'
                                    ];
                                    $icone = $icones[$relatorio['tipo']] ?? 'file-earmark-text';
                                ?>
                                <tr>
                                    <td>
                                        <i class="bi bi-<?php echo $icone; ?> text-primary me-2"></i>
                                        <?php echo htmlspecialchars($relatorio['titulo']); ?>
                                    </td>
                                    <td><?php echo $relatorio['data']; ?></td>
                                    <td><?php echo htmlspecialchars($relatorio['usuario']); ?></td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <i class="bi bi-download me-1"></i> <?php echo $relatorio['downloads']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-primary" title="Visualizar">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary" title="Baixar">
                                                <i class="bi bi-download"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-danger" title="Excluir">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-4">
                        <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                        <p class="mt-2 text-muted">Nenhum relatório recente encontrado.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de ajuda -->
<div class="modal fade" id="ajudaModal" tabindex="-1" aria-labelledby="ajudaModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="ajudaModalLabel">
                    <i class="bi bi-question-circle"></i> Ajuda - Módulo de Relatórios
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <h6>Como usar os relatórios:</h6>
                <ol>
                    <li>Selecione o tipo de relatório desejado clicando em um dos cartões acima.</li>
                    <li>Preencha os filtros conforme necessário para refinar sua busca.</li>
                    <li>Escolha o formato de saída (PDF, Excel, etc.).</li>
                    <li>Clique em "Gerar Relatório" para visualizar ou baixar o relatório.</li>
                </ol>
                
                <h6 class="mt-4">Dicas:</h6>
                <ul>
                    <li>Utilize os filtros para obter relatórios mais precisos e relevantes.</li>
                    <li>Você pode salvar os filtros usados recentemente para agilizar consultas futuras.</li>
                    <li>Os relatórios podem ser exportados para diferentes formatos, dependendo de suas necessidades.</li>
                    <li>Para relatórios personalizados, utilize a opção "Personalizado" para criar relatórios com campos específicos.</li>
                </ul>
                
                <div class="alert alert-info mt-4">
                    <h6><i class="bi bi-info-circle"></i> Suporte Técnico</h6>
                    <p class="mb-0">Em caso de dúvidas ou problemas, entre em contato com o suporte técnico através do email: <a href="mailto:suporte@sistemas.com.br">suporte@sistemas.com.br</a></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Atualizar a data/hora atual
    function atualizarDataHora() {
        const agora = new Date();
        const opcoes = { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        };
        document.getElementById('data-hora-atual').textContent = 
            'Gerado em: ' + agora.toLocaleDateString('pt-BR', opcoes);
    }
    
    // Atualizar a cada minuto
    atualizarDataHora();
    setInterval(atualizarDataHora, 60000);
    
    // Adicionar efeito de hover nos cartões
    const cards = document.querySelectorAll('.card');
    cards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transition = 'transform 0.2s, box-shadow 0.2s';
            this.style.transform = 'translateY(-5px)';
            this.style.boxShadow = '0 0.5rem 1rem rgba(0, 0, 0, 0.15)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = '0 0.125rem 0.25rem rgba(0, 0, 0, 0.075)';
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
