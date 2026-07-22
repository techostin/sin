<?php
$page_title = "Validação de Insumos";
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/menu.php';
require_once __DIR__ . '/../../../classes/Database.php';
require_once __DIR__ . '/../../../classes/Insumo.php';

if (!$user->isLoggedIn()) {
    echo "<div class='alert alert-danger'>Você não tem permissão para acessar esta página.</div>";
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}

// Apenas Admin/Gestor podem acessar validação de insumos
if (!$user->hasPermission(NIVEL_GESTOR)) {
    echo "<div class='alert alert-danger'>Acesso restrito a gestores e administradores.</div>";
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}

$db = Database::getInstance()->getConnection();

$filtro_nome = $_GET['nome'] ?? '';
$filtro_status = $_GET['status'] ?? 'todos';
$pagina = max(1, (int)($_GET['pagina'] ?? 1));
$por_pagina = 50;
$offset = ($pagina - 1) * $por_pagina;

$where = [];
$params = [];
if ($filtro_nome) {
    $where[] = "(i.nome LIKE :nome OR f.nome LIKE :fornecedor_nome)";
    $params[':nome'] = "%$filtro_nome%";
    $params[':fornecedor_nome'] = "%$filtro_nome%";
}

$where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

try {
    $count_sql = "SELECT COUNT(*) FROM insumos i LEFT JOIN fornecedores f ON i.fornecedor_id = f.id $where_clause";
    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $total_registros = $stmt->fetchColumn();
    $total_paginas = max(1, ceil($total_registros / $por_pagina));

    $sql = "SELECT i.*, f.nome as fornecedor_nome FROM insumos i
            LEFT JOIN fornecedores f ON i.fornecedor_id = f.id
            $where_clause
            ORDER BY i.nome ASC LIMIT $por_pagina OFFSET $offset";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $insumos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $insumos = [];
    $total_registros = 0;
    $total_paginas = 1;
}
?>

<div class="container-fluid px-4 py-4">
    <div class="row align-items-center mb-4">
        <div class="col-md-8">
            <h1 class="h2 fw-bold"><i class="bi bi-shield-check text-success me-2"></i>Validação de Insumos</h1>
            <p class="text-muted mb-0">Verifique se os insumos cadastrados estão em conformidade com a Portaria 52/2021.</p>
        </div>
        <div class="col-md-4 text-md-end mt-3 mt-md-0">
            <a href="<?php echo SITE_URL; ?>/caderno-campo/insumo/novo" class="btn btn-success">
                <i class="bi bi-plus-circle"></i> Novo Insumo
            </a>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Buscar Insumo</label>
                    <input type="text" class="form-control" name="nome" value="<?php echo htmlspecialchars($filtro_nome); ?>" placeholder="Nome do insumo ou fornecedor...">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="todos" <?php echo $filtro_status === 'todos' ? 'selected' : ''; ?>>Todos</option>
                        <option value="validado" <?php echo $filtro_status === 'validado' ? 'selected' : ''; ?>>Validados</option>
                        <option value="nao_validado" <?php echo $filtro_status === 'nao_validado' ? 'selected' : ''; ?>>Não Validados</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i> Filtrar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold">
                <i class="bi bi-list-ul me-2"></i>Insumos Cadastrados
                <span class="badge bg-secondary ms-2"><?php echo $total_registros; ?></span>
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Insumo</th>
                            <th>Fornecedor</th>
                            <th>Estoque</th>
                            <th>Validade</th>
                            <th>Conformidade</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($insumos)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                                    Nenhum insumo encontrado.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($insumos as $ins): 
                                $stmt_check = $db->prepare("SELECT permitido, restricoes, categoria FROM insumos_organicos_aprovados WHERE nome LIKE ? OR nome_comercial LIKE ? LIMIT 1");
                                $like = "%{$ins['nome']}%";
                                $stmt_check->execute([$like, $like]);
                                $aprovado = $stmt_check->fetch(PDO::FETCH_ASSOC);

                                if ($aprovado && $aprovado['permitido']) {
                                    $badge_class = 'bg-success';
                                    $badge_text = 'Permitido';
                                    $icon = 'bi-check-circle-fill';
                                } elseif ($aprovado && !$aprovado['permitido']) {
                                    $badge_class = 'bg-warning text-dark';
                                    $badge_text = 'Restrito';
                                    $icon = 'bi-exclamation-triangle-fill';
                                } else {
                                    $badge_class = 'bg-secondary';
                                    $badge_text = 'Não Verificado';
                                    $icon = 'bi-question-circle-fill';
                                }
                            ?>
                                <tr>
                                    <td class="fw-semibold"><?php echo htmlspecialchars($ins['nome']); ?></td>
                                    <td><?php echo htmlspecialchars($ins['fornecedor_nome'] ?? '-'); ?></td>
                                    <td><?php echo $ins['quantidade_estoque'] ?? 0; ?></td>
                                    <td><?php echo !empty($ins['data_validade']) ? date('d/m/Y', strtotime($ins['data_validade'])) : '-'; ?></td>
                                    <td>
                                        <span class="badge <?php echo $badge_class; ?> rounded-pill">
                                            <i class="bi <?php echo $icon; ?> me-1"></i><?php echo $badge_text; ?>
                                        </span>
                                        <?php if ($aprovado && !empty($aprovado['restricoes'])): ?>
                                            <br><small class="text-muted" title="<?php echo htmlspecialchars($aprovado['restricoes']); ?>"><?php echo htmlspecialchars(mb_substr($aprovado['restricoes'], 0, 40)) . '...'; ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="<?php echo SITE_URL; ?>/caderno-campo/insumo/<?php echo $ins['uuid'] ?? $ins['id']; ?>/editar" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if ($total_paginas > 1): ?>
        <div class="card-footer bg-white">
            <nav>
                <ul class="pagination justify-content-center mb-0">
                    <?php for ($p = 1; $p <= $total_paginas; $p++): ?>
                        <li class="page-item <?php echo $p === $pagina ? 'active' : ''; ?>">
                            <a class="page-link" href="?pagina=<?php echo $p; ?>&nome=<?php echo urlencode($filtro_nome); ?>&status=<?php echo $filtro_status; ?>"><?php echo $p; ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
