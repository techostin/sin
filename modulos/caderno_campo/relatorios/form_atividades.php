<?php
// Iniciar a sessão se ainda não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    die('Acesso negado. Por favor, faça login para acessar este relatório.');
}

// Incluir configuração do banco
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../classes/Database.php';

$page_title = "Relatório de Atividades";
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/menu.php';

try {
    $db = Database::getInstance()->getConnection();

    // Buscar categorias para o select
    $stmt = $db->query("SELECT id, nome FROM categorias_atividades ORDER BY nome");
    $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Buscar propriedades para o select
    $stmt = $db->query("SELECT id, propriedade FROM propriedades ORDER BY propriedade");
    $propriedades = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Se for admin ou gestor, buscar todos os associados, senão apenas o próprio
    if ($_SESSION['user_nivel'] == 1 || $_SESSION['user_nivel'] == 2) {
        $stmt = $db->query("SELECT id, nome, sobrenome FROM associados ORDER BY nome, sobrenome");
    } else {
        $stmt = $db->prepare("SELECT id, nome, sobrenome FROM associados WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
    }
    $associados = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Erro ao carregar dados: " . $e->getMessage() . "</div>";
}
?>

<div class="container-fluid">
    <h1 class="mt-4">Relatório de Atividades</h1>
    <p class="text-muted">Preencha os filtros abaixo para gerar o relatório de atividades.</p>

    <div class="card mb-4">
        <div class="card-header">
            <i class="bi bi-funnel"></i> Filtros do Relatório
        </div>
        <div class="card-body">
            <form id="formRelatorio" action="atividades.php" method="get" target="_blank">
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label for="data_inicio" class="form-label">Data Inicial</label>
                        <input type="date" class="form-control" id="data_inicio" name="data_inicio"
                            value="<?php echo date('Y-m-01'); ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="data_fim" class="form-label">Data Final</label>
                        <input type="date" class="form-control" id="data_fim" name="data_fim"
                            value="<?php echo date('Y-m-t'); ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">Todos</option>
                            <option value="Pendente">Pendente</option>
                            <option value="Em andamento">Em andamento</option>
                            <option value="Concluído">Concluído</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="categoria_id" class="form-label">Categoria</label>
                        <select class="form-select" id="categoria_id" name="categoria_id">
                            <option value="">Todas as categorias</option>
                            <?php foreach ($categorias as $categoria): ?>
                                <option value="<?php echo $categoria['id']; ?>">
                                    <?php echo htmlspecialchars($categoria['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="propriedade_id" class="form-label">Propriedade</label>
                        <select class="form-select" id="propriedade_id" name="propriedade_id">
                            <option value="">Todas as propriedades</option>
                            <?php foreach ($propriedades as $propriedade): ?>
                                <option value="<?php echo $propriedade['id']; ?>">
                                    <?php echo htmlspecialchars($propriedade['propriedade']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if ($_SESSION['user_nivel'] == 1 || $_SESSION['user_nivel'] == 2): ?>
                        <div class="col-md-4">
                            <label for="associado_id" class="form-label">Associado</label>
                            <select class="form-select" id="associado_id" name="associado_id">
                                <option value="">Todos os associados</option>
                                <?php foreach ($associados as $associado): ?>
                                    <option value="<?php echo $associado['id']; ?>">
                                        <?php echo htmlspecialchars($associado['nome'] . ' ' . $associado['sobrenome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php else: ?>
                        <input type="hidden" name="associado_id" value="<?php echo $_SESSION['user_id']; ?>">
                    <?php endif; ?>
                </div>

                <div class="row mb-3">
                    <div class="col-12">
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-danger" onclick="gerarRelatorio('pdf')">
                                <i class="bi bi-file-earmark-pdf"></i> Exportar PDF
                            </button>
                            <button type="button" class="btn btn-success" onclick="gerarRelatorio('excel')">
                                <i class="bi bi-file-earmark-excel"></i> Exportar Excel
                            </button>
                        </div>
                        <button type="button" class="btn btn-outline-secondary ms-2" onclick="limparFiltros()">
                            <i class="bi bi-arrow-counterclockwise"></i> Limpar Filtros
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <i class="bi bi-info-circle"></i> Instruções
        </div>
        <div class="card-body">
            <p>Utilize os filtros acima para personalizar o relatório de acordo com suas necessidades:</p>
            <ul>
                <li><strong>Período:</strong> Selecione o intervalo de datas para o relatório.</li>
                <li><strong>Status:</strong> Filtre por atividades pendentes, em andamento ou concluídas.</li>
                <li><strong>Categoria:</strong> Selecione uma categoria específica de atividades.</li>
                <li><strong>Propriedade:</strong> Filtre por propriedade específica.</li>
                <?php if ($_SESSION['user_nivel'] == 1 || $_SESSION['user_nivel'] == 2): ?>
                    <li><strong>Associado:</strong> Selecione um associado específico (apenas administradores e gestores).
                    </li>
                <?php endif; ?>
            </ul>
            <p class="mb-0">Após configurar os filtros, clique em "Gerar Relatório" para visualizar o PDF.</p>
        </div>
    </div>
</div>

<script>
    function gerarRelatorio(formato) {
        // Obter dados do formulário
        const form = document.getElementById('formRelatorio');
        const formData = new FormData(form);

        // Construir URL baseado no formato
        let url;
        if (formato === 'pdf') {
            url = 'atividades.php';
        } else if (formato === 'excel') {
            url = 'atividades_excel.php';
        }

        // Converter FormData para query string
        const params = new URLSearchParams(formData);

        // Abrir em nova aba
        window.open(url + '?' + params.toString(), '_blank');
    }

    function limparFiltros() {
        // Redefinir valores padrão
        document.getElementById('data_inicio').value = '<?php echo date('Y-m-01'); ?>';
        document.getElementById('data_fim').value = '<?php echo date('Y-m-t'); ?>';
        document.getElementById('status').value = '';
        document.getElementById('categoria_id').value = '';
        document.getElementById('propriedade_id').value = '';
        <?php if ($_SESSION['user_nivel'] == 1 || $_SESSION['user_nivel'] == 2): ?>
            document.getElementById('associado_id').value = '';
        <?php endif; ?>
    }

    // Desabilitar datas futuras
    window.onload = function () {
        var today = new Date().toISOString().split('T')[0];
        document.getElementById('data_fim').max = today;

        // Validar data inicial <= data final
        document.getElementById('data_inicio').addEventListener('change', function () {
            document.getElementById('data_fim').min = this.value;
        });

        // Se data final for menor que a inicial, ajustar
        document.getElementById('data_fim').addEventListener('change', function () {
            var dataInicio = document.getElementById('data_inicio').value;
            if (this.value < dataInicio) {
                this.value = dataInicio;
            }
        });
    };
</script>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>