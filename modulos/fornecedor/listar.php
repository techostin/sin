<?php
$page_title = "Listar Fornecedores";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
require_once __DIR__ . '/../../classes/Fornecedor.php';

// Apenas Admin ou Gestor podem ver a lista completa
if (!$user->isLoggedIn()) {
    echo "<div class='alert alert-danger'>Você não tem permissão para acessar esta página.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

// Instancia a classe Fornecedor e busca os dados
$fornecedor_obj = new Fornecedor();
$fornecedores = $fornecedor_obj->getAll();
?>

<h1 class="mt-4">Fornecedores Cadastrados</h1>
<p>Abaixo está a lista de todos os fornecedores registrados no sistema.</p>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Lista de Fornecedores</span>
        <a href="<?php echo SITE_URL; ?>/fornecedor/novo" class="btn btn-primary btn-sm">Adicionar Novo Fornecedor</a>
    </div>
    <div class="card-body">
        <table id="fornecedoresTable" class="table table-striped table-bordered" style="width:100%">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome</th>
                    <th>Representante</th>
                    <th>Cidade</th>
                    <th>Estado</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($fornecedores) > 0): ?>
                    <?php foreach ($fornecedores as $forn): ?>
                        <tr>
                            <td><?php echo htmlspecialchars(isset($forn['id']) ? $forn['id'] : ''); ?></td>
                            <td><?php echo htmlspecialchars(isset($forn['nome']) ? $forn['nome'] : ''); ?></td>
                            <td><?php echo htmlspecialchars(isset($forn['representante']) ? $forn['representante'] : ''); ?></td>
                            <td><?php echo htmlspecialchars(isset($forn['cidade_nome']) ? $forn['cidade_nome'] : 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars(isset($forn['estado_sigla']) ? $forn['estado_sigla'] : 'N/A'); ?></td>
                            <td>
                                <a href="<?php echo SITE_URL; ?>/fornecedor/<?php echo $forn['id']; ?>/editar" class="btn btn-info btn-sm" title="Editar"><i class="bi bi-pencil-fill"></i></a>
                                <button class="btn btn-danger btn-sm" title="Exclusão desabilitada - Clique para mais informações" onclick="mostrarAdvertenciaExclusao()"><i class="bi bi-trash-fill"></i></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center">Nenhum fornecedor encontrado.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>

<style>
.btn-danger[title*="desabilitada"] {
    opacity: 0.6;
    cursor: pointer;
    position: relative;
}

.btn-danger[title*="desabilitada"]:hover {
    opacity: 0.8;
    transform: scale(1.05);
    transition: all 0.2s ease;
}

.btn-danger[title*="desabilitada"]::after {
    content: "🚫";
    position: absolute;
    top: -5px;
    right: -5px;
    font-size: 10px;
    background: #dc3545;
    color: white;
    border-radius: 50%;
    width: 16px;
    height: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
}
</style>

<script>
    $(document).ready(function() {
        try {
            var dados = <?php echo json_encode($fornecedores); ?>;
            console.log("Dados para fornecedoresTable:", dados);
            if (dados.length > 0) {
                console.log("Primeira linha de dados:", dados[0]);
                console.log("Número de colunas na primeira linha:", Object.keys(dados[0]).length);
                console.log("Campos disponíveis:", Object.keys(dados[0]));
            }
            var table = $('#fornecedoresTable');
            if (table.length === 0) {
                console.error("Tabela fornecedoresTable não encontrada!");
                return;
            }
            var rows = table.find('tbody tr');
            console.log("Número de linhas na tabela:", rows.length);
            var dataTable = table.DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json"
                },
                "responsive": true,
                "autoWidth": false,
                "columnDefs": [
                    {
                        "targets": -1,
                        "orderable": false,
                        "searchable": false
                    }
                ]
            });
            console.log("DataTable inicializado com sucesso!");
        } catch (error) {
            console.error("Erro ao inicializar DataTable:", error);
        }
    });

function mostrarAdvertenciaExclusao() {
    // Criar modal de advertência
    const modalHtml = `
        <div class="modal fade" id="modalAdvertencia" tabindex="-1" aria-labelledby="modalAdvertenciaLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title" id="modalAdvertenciaLabel">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            Aviso Importante
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="text-center mb-3">
                            <i class="bi bi-shield-exclamation text-warning" style="font-size: 3rem;"></i>
                        </div>
                        <p class="text-center"><strong>A funcionalidade de exclusão de fornecedores foi temporariamente desabilitada.</strong></p>
                        <p>Esta medida foi implementada para:</p>
                        <ul>
                            <li>🔒 Proteger dados importantes do sistema</li>
                            <li>⚠️ Evitar perda acidental de informações</li>
                            <li>🛡️ Manter a integridade dos registros</li>
                        </ul>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Alternativas disponíveis:</strong><br>
                            • Utilize a opção de <strong>Editar</strong> para fazer alterações<br>
                            • Entre em contato com o administrador do sistema se necessário
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">
                            <i class="bi bi-check-circle me-2"></i>Entendi
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remover modal anterior se existir
    $('#modalAdvertencia').remove();
    
    // Adicionar modal ao body
    $('body').append(modalHtml);
    
    // Mostrar modal
    $('#modalAdvertencia').modal('show');
    
    // Remover modal do DOM após fechar
    $('#modalAdvertencia').on('hidden.bs.modal', function() {
        $(this).remove();
    });
}
</script> 