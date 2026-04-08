<?php
$page_title = "Listar Associações";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
require_once __DIR__ . '/../../classes/Associacao.php';

// Apenas Admin ou Gestor podem ver a lista completa
if (!$user->isLoggedIn()) {
    echo "<div class='alert alert-danger'>Você não tem permissão para acessar esta página.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$associacao = new Associacao();
$associacoes = $associacao->getAll();

?>

<h1 class="mt-4">Associações Cadastradas</h1>
<p>Abaixo está a lista de todas as associações registradas no sistema.</p>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Lista de Associações</span>
        <a href="<?php echo SITE_URL; ?>/associacao/novo" class="btn btn-primary btn-sm">Adicionar Nova</a>
    </div>
    <div class="card-body">
        <table id="associacoesTable" class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome</th>
                    <th>Sigla</th>
                    <th>Cidade</th>
                    <th>Estado</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($associacoes) > 0): ?>
                    <?php foreach ($associacoes as $assoc): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($assoc['id']); ?></td>
                            <td><?php echo htmlspecialchars($assoc['nome']); ?></td>
                            <td><?php echo htmlspecialchars($assoc['sigla'] ?: '-'); ?></td>
                            <td><?php echo htmlspecialchars($assoc['nome_cidade'] ?: '-'); ?></td>
                            <td><?php echo htmlspecialchars($assoc['nome_estado'] ? $assoc['nome_estado'] . ' (' . $assoc['uf'] . ')' : '-'); ?></td>
                            <td><span class="badge bg-<?php echo ($assoc['status'] == 'ativo') ? 'success' : 'warning'; ?>"><?php echo ucfirst(htmlspecialchars($assoc['status'])); ?></span></td>
                            <td>
                                <button class="btn btn-primary btn-sm" title="Ver Detalhes" onclick="verDetalhes(<?php echo $assoc['id']; ?>)"><i class="bi bi-eye-fill"></i></button>
                                <form action="<?php echo SITE_URL; ?>/associacao/editar" method="POST" style="display:inline;">
                                    <input type="hidden" name="id" value="<?php echo $assoc['id']; ?>">
                                    <button type="submit" class="btn btn-info btn-sm" title="Editar"><i class="bi bi-pencil-fill"></i></button>
                                </form>
                                <button class="btn btn-danger btn-sm" title="Exclusão desabilitada - Clique para mais informações" onclick="mostrarAdvertenciaExclusao()"><i class="bi bi-trash-fill"></i></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center">Nenhuma associação encontrada.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal para exibir detalhes da associação -->
<div class="modal fade" id="modalDetalhes" tabindex="-1" aria-labelledby="modalDetalhesLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalDetalhesLabel">Detalhes da Associação</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="modalDetalhesBody">
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Carregando...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

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
    $('#associacoesTable').DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json"
        },
        "order": [[1, "asc"]], // Ordenar por nome
        "pageLength": 25,
        "responsive": true
    });
});

function verDetalhes(id) {
    // Mostrar modal com spinner
    $('#modalDetalhes').modal('show');
    $('#modalDetalhesBody').html('<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Carregando...</span></div></div>');
    
    // Fazer requisição AJAX para buscar detalhes
    fetch(`<?php echo SITE_URL; ?>/api/get_associacao_detalhes.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const assoc = data.data;
                let html = `
                    <div class="row">
                        <div class="col-md-6">
                            <h6><strong>Informações Básicas</strong></h6>
                            <p><strong>Nome:</strong> ${assoc.nome || '-'}</p>
                            <p><strong>Sigla:</strong> ${assoc.sigla || '-'}</p>
                            <p><strong>Status:</strong> <span class="badge bg-${assoc.status === 'ativo' ? 'success' : 'warning'}">${assoc.status ? assoc.status.charAt(0).toUpperCase() + assoc.status.slice(1) : '-'}</span></p>
                            <p><strong>Data de Fundação:</strong> ${assoc.data_fundacao ? new Date(assoc.data_fundacao).toLocaleDateString('pt-BR') : '-'}</p>
                        </div>
                        <div class="col-md-6">
                            <h6><strong>Localização</strong></h6>
                            <p><strong>Cidade:</strong> ${assoc.nome_cidade || '-'}</p>
                            <p><strong>Estado:</strong> ${assoc.nome_estado ? assoc.nome_estado + ' (' + assoc.uf + ')' : '-'}</p>
                            <p><strong>CEP:</strong> ${assoc.cep || '-'}</p>
                            <p><strong>Endereço:</strong> ${assoc.endereco || '-'}</p>
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-md-6">
                            <h6><strong>Contato</strong></h6>
                            <p><strong>Telefone:</strong> ${assoc.telefone || '-'}</p>
                            <p><strong>E-mail:</strong> ${assoc.email || '-'}</p>
                            <p><strong>Site:</strong> ${assoc.site ? '<a href="' + assoc.site + '" target="_blank">' + assoc.site + '</a>' : '-'}</p>
                        </div>
                        <div class="col-md-6">
                            <h6><strong>Responsável</strong></h6>
                            <p><strong>Nome:</strong> ${assoc.responsavel || '-'}</p>
                            <p><strong>Cargo:</strong> ${assoc.cargo_responsavel || '-'}</p>
                        </div>
                    </div>
                `;
                
                if (assoc.descricao || assoc.objetivo) {
                    html += `
                        <hr>
                        <div class="row">
                            ${assoc.descricao ? `
                                <div class="col-md-6">
                                    <h6><strong>Descrição</strong></h6>
                                    <p>${assoc.descricao}</p>
                                </div>
                            ` : ''}
                            ${assoc.objetivo ? `
                                <div class="col-md-6">
                                    <h6><strong>Objetivo</strong></h6>
                                    <p>${assoc.objetivo}</p>
                                </div>
                            ` : ''}
                        </div>
                    `;
                }
                
                $('#modalDetalhesBody').html(html);
            } else {
                $('#modalDetalhesBody').html('<div class="alert alert-danger">Erro ao carregar detalhes da associação.</div>');
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            $('#modalDetalhesBody').html('<div class="alert alert-danger">Erro ao carregar detalhes da associação.</div>');
        });
}

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
                        <p class="text-center"><strong>A funcionalidade de exclusão de associações foi temporariamente desabilitada.</strong></p>
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