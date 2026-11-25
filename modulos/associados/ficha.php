<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/Associado.php';
require_once __DIR__ . '/../../classes/Mensalidade.php';
require_once __DIR__ . '/../../classes/Carteirinha.php';
require_once __DIR__ . '/../../classes/AssociadoAssociacao.php';
require_once __DIR__ . '/../../classes/CSRFProtection.php';

$user = new User();
// Admin/Gestor OU membro da Diretoria — menu da Diretoria idêntico ao Admin
if (!$user->podeAcessarDiretoria()) {
    header('Location: ' . SITE_URL . '/login');
    exit;
}
$podeGerenciarCarteirinha = $user->hasPermission(NIVEL_GESTOR);

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: ' . SITE_URL . '/associados');
    exit;
}
$id = (int) $_GET['id'];

$associado = (new Associado())->getById($id);
if (!$associado) {
    header('Location: ' . SITE_URL . '/associados');
    exit;
}

$associadoModel = new Associado();
$cingulas = AssociadoAssociacao::getByAssociado($id);
$presencas = $associadoModel->getPresencas($id);
$mensalidadeModel = new Mensalidade();
$financeiro = $mensalidadeModel->getResumoFinanceiro($id);
$historico = $mensalidadeModel->getByAssociado($id);
$carteirinha = (new Carteirinha())->getByAssociado($id);

$page_title = "Ficha do Associado";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';

$data_nasc = !empty($associado['data_nascimento']) && $associado['data_nascimento'] !== '0000-00-00'
    ? date('d/m/Y', strtotime($associado['data_nascimento']))
    : '—';

function badgeSituacao($st)
{
    switch ($st) {
        case 'Inadimplente': return '<span class="badge bg-danger">Inadimplente</span>';
        case 'Afastado': return '<span class="badge bg-warning text-dark">Afastado</span>';
        case 'Desligado': case 'Falecido': return '<span class="badge bg-secondary">' . $st . '</span>';
        default: return '<span class="badge bg-success">Ativo</span>';
    }
}

$foto = !empty($associado['foto_url']) && file_exists('uploads/associados/' . $associado['foto_url'])
    ? SITE_URL . '/uploads/associados/' . htmlspecialchars($associado['foto_url'])
    : null;
?>

<style>
    .tab-content { min-height: 320px; }
    .ficha-tabs .nav-link { border-radius: 0.5rem 0.5rem 0 0; font-weight: 500; }
    .ficha-tabs .nav-link.active { background-color: #0d6efd; color: #fff; }
</style>

<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-person-vcard-fill text-primary me-2"></i>Ficha do Associado</h1>
            <span class="text-muted"><?php echo htmlspecialchars($associado['nome'] . ' ' . $associado['sobrenome']); ?> • ID <?php echo $id; ?></span>
        </div>
<div class="d-flex gap-2 flex-wrap">
            <?php if ($carteirinha): ?>
            <a href="<?php echo SITE_URL; ?>/associado/<?php echo encodeId($id); ?>/carteirinha-pdf" target="_blank" class="btn btn-outline-success btn-sm"><i class="bi bi-credit-card-2-front me-1"></i>Carteirinha (PDF)</a>
            <?php endif; ?>
            <a href="<?php echo SITE_URL; ?>/associado/<?php echo encodeId($id); ?>/carteirinha-impressa" target="_blank" class="btn btn-outline-dark btn-sm"><i class="bi bi-printer me-1"></i>Imprimir/PDF Completo</a>
            <?php if ($podeGerenciarCarteirinha && !empty($associado['email'])): ?>
            <form action="<?php echo SITE_URL; ?>/associado/<?php echo encodeId($id); ?>/carteirinha-enviar" method="post" class="d-inline" onsubmit="return confirm('Enviar a carteirinha por e-mail para o associado?');">
                <?php echo CSRFProtection::getTokenField(); ?>
                <button type="submit" class="btn btn-outline-primary btn-sm" title="Enviar por e-mail"><i class="bi bi-envelope-paper me-1"></i>Enviar por E-mail</button>
            </form>
            <?php endif; ?>
            <?php if ($podeGerenciarCarteirinha): ?>
            <a href="<?php echo SITE_URL; ?>/associado/<?php echo encodeId($id); ?>/carteirinha-gerar" onclick="return confirm('Gerar/renovar a carteirinha digital deste associado?')" class="btn btn-success btn-sm"><i class="bi bi-qr-code me-1"></i><?php echo $carteirinha ? 'Renovar Carteirinha' : 'Gerar Carteirinha'; ?></a>
            <?php endif; ?>
            <a href="<?php echo SITE_URL; ?>/associado/<?php echo encodeId($id); ?>/editar" class="btn btn-info btn-sm"><i class="bi bi-pencil me-1"></i>Editar</a>
            <a href="<?php echo SITE_URL; ?>/associados" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Voltar</a>
        </div>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show"><?php echo htmlspecialchars($_SESSION['success_message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <div class="row g-3">
        <!-- Coluna esquerda: cartão de identificação -->
        <div class="col-lg-4">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-primary text-white"><i class="bi bi-person-circle me-1"></i>Identificação</div>
                <div class="card-body text-center">
                    <?php if ($foto): ?>
                        <img src="<?php echo $foto; ?>" class="rounded-circle border mb-3" style="width:110px;height:110px;object-fit:cover;" alt="Foto">
                    <?php else: ?>
                        <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center mx-auto mb-3" style="width:110px;height:110px;font-size:44px;"><i class="bi bi-person"></i></div>
                    <?php endif; ?>
                    <h5 class="mb-1"><?php echo htmlspecialchars($associado['nome'] . ' ' . $associado['sobrenome']); ?></h5>
                    <div class="mb-2">
                        <span class="badge bg-primary"><?php echo htmlspecialchars(ucfirst($associado['tipo_associado'] ?? 'Efetivo')); ?></span>
                        <?php echo badgeSituacao($associado['status_associado'] ?? 'Ativo'); ?>
                        <span class="badge bg-<?php echo ($associado['status'] ?? '') === 'ativo' ? 'success' : 'warning'; ?>">Login: <?php echo ucfirst(htmlspecialchars($associado['status'] ?? '')); ?></span>
                    </div>
                    <hr>
                    <div class="text-start small">
                        <p class="mb-1"><strong>Telefone:</strong> <?php echo htmlspecialchars($associado['telefone'] ?? '—'); ?></p>
                        <p class="mb-1"><strong>E-mail:</strong> <?php echo htmlspecialchars($associado['email'] ?? '—'); ?></p>
                        <p class="mb-1"><strong>CPF:</strong> <?php echo htmlspecialchars($associado['cpf'] ?? '—'); ?></p>
                        <p class="mb-1"><strong>Nascimento:</strong> <?php echo $data_nasc; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Coluna direita: abas -->
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header p-0 border-0">
                    <ul class="nav nav-tabs ficha-tabs px-2 pt-2" id="fichaTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="tab-dados" data-bs-toggle="tab" data-bs-target="#pane-dados" type="button" role="tab"><i class="bi bi-person-lines-fill me-1"></i>Dados</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-financeiro" data-bs-toggle="tab" data-bs-target="#pane-financeiro" type="button" role="tab"><i class="bi bi-cash-stack me-1"></i>Financeiro</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-presenca" data-bs-toggle="tab" data-bs-target="#pane-presenca" type="button" role="tab"><i class="bi bi-calendar-check me-1"></i>Presença</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-vinculos" data-bs-toggle="tab" data-bs-target="#pane-vinculos" type="button" role="tab"><i class="bi bi-building me-1"></i>Vínculos</button>
                        </li>
                    </ul>
                </div>
                <div class="card-body tab-content">

                    <!-- Aba DADOS -->
                    <div class="tab-pane fade show active" id="pane-dados" role="tabpanel" aria-labelledby="tab-dados">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <p class="mb-1"><strong>RG:</strong> <?php echo htmlspecialchars($associado['rg'] ?? '—'); ?></p>
                                <p class="mb-1"><strong>Profissão:</strong> <?php echo htmlspecialchars($associado['profissao'] ?? '—'); ?></p>
                                <p class="mb-1"><strong>Endereço:</strong> <?php echo htmlspecialchars(trim(($associado['endereco'] ?? '') . ' ' . ($associado['numero'] ?? ''))); ?></p>
                                <p class="mb-1"><strong>Bairro/Cidade:</strong> <?php echo htmlspecialchars(($associado['bairro'] ?? '') . ' - ' . ($associado['cidade'] ?? '')); ?></p>
                                <p class="mb-1"><strong>CEP:</strong> <?php echo htmlspecialchars($associado['cep'] ?? '—'); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-1"><strong>Admissão:</strong> <?php echo !empty($associado['data_admissao']) ? date('d/m/Y', strtotime($associado['data_admissao'])) : '—'; ?></p>
                                <p class="mb-1"><strong>Desligamento:</strong> <?php echo !empty($associado['data_desligamento']) ? date('d/m/Y', strtotime($associado['data_desligamento'])) : '—'; ?></p>
                                <p class="mb-1"><strong>Registro CNPO:</strong> <?php echo htmlspecialchars($associado['registro_cnpo'] ?? '—'); ?></p>
                            </div>
                        </div>
                        <hr>
                        <h6 class="fw-bold">Membro de Associação</h6>
                        <?php if (empty($cingulas)): ?>
                            <span class="text-muted small">Sem vínculos.</span>
                        <?php else: ?>
                            <?php foreach ($cingulas as $ac): ?>
                                <span class="badge rounded-pill text-white mb-1 me-1" style="background:linear-gradient(135deg,#2f9e6f,#2b9d8f);"><?php echo htmlspecialchars(!empty($ac['sigla']) ? $ac['sigla'] : $ac['nome']); ?></span>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Aba FINANCEIRO -->
                    <div class="tab-pane fade" id="pane-financeiro" role="tabpanel" aria-labelledby="tab-financeiro">
                        <div class="row g-3 mb-3">
                            <div class="col-4">
                                <div class="card text-white bg-success shadow-sm text-center">
                                    <div class="card-body py-3">
                                        <div class="fs-4 fw-bold">R$ <?php echo number_format((float)$financeiro['total_pago'], 2, ',', '.'); ?></div>
                                        <div class="small">Pago</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="card text-white bg-danger shadow-sm text-center">
                                    <div class="card-body py-3">
                                        <div class="fs-4 fw-bold">R$ <?php echo number_format((float)$financeiro['total_em_aberto'], 2, ',', '.'); ?></div>
                                        <div class="small">Em aberto (<?php echo (int)$financeiro['qtd_em_aberto']; ?>)</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="card text-white bg-info shadow-sm text-center">
                                    <div class="card-body py-3">
                                        <div class="fs-4 fw-bold"><?php echo (int)$financeiro['qtd_pago']; ?></div>
                                        <div class="small">Quitadas</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <?php if (empty($historico)): ?>
                                <span class="text-muted small">Nenhuma mensalidade lançada.</span>
                            <?php else: ?>
                                <table class="table table-sm table-striped mb-0">
                                    <thead><tr>
                                        <th>Referência</th><th>Vencimento</th><th>Plano</th><th>Valor</th><th>Situação</th><th>Pagamento</th>
                                    </tr></thead>
                                    <tbody>
                                    <?php foreach ($historico as $m): ?>
                                        <tr>
                                            <td><?php echo str_pad((string)$m['mes_referencia'], 2, '0', STR_PAD_LEFT) . '/' . $m['ano_referencia']; ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($m['data_vencimento'])); ?></td>
                                            <td><?php echo $m['plano_nome'] ? htmlspecialchars($m['plano_nome']) : '—'; ?></td>
                                            <td>R$ <?php echo number_format((float)$m['valor_cobrado'], 2, ',', '.'); ?></td>
                                            <td>
                                                <?php
                                                $cor = $m['status_mensalidade'] === 'pago' ? 'success' : ($m['status_mensalidade'] === 'atrasado' ? 'danger' : 'warning text-dark');
                                                echo '<span class="badge bg-' . $cor . '">' . ucfirst(htmlspecialchars($m['status_mensalidade'])) . '</span>';
                                                ?>
                                            </td>
                                            <td><?php echo $m['data_pagamento'] ? date('d/m/Y', strtotime($m['data_pagamento'])) : '—'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Aba PRESENÇA -->
                    <div class="tab-pane fade" id="pane-presenca" role="tabpanel" aria-labelledby="tab-presenca">
                        <div class="table-responsive">
                            <?php if (empty($presencas)): ?>
                                <span class="text-muted small">Nenhum registro de presença.</span>
                            <?php else: ?>
                                <table class="table table-sm table-striped mb-0">
                                    <thead><tr><th>Evento</th><th>Data</th><th>Situação</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($presencas as $p): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($p['evento_nome'] ?? '—'); ?></td>
                                            <td><?php echo !empty($p['data_evento']) ? date('d/m/Y', strtotime($p['data_evento'])) : '—'; ?></td>
                                            <td><span class="badge bg-<?php echo $p['status_presenca'] === 'Presente' ? 'success' : ($p['status_presenca'] === 'Ausente' ? 'secondary' : 'info'); ?>"><?php echo htmlspecialchars($p['status_presenca'] ?? '—'); ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Aba VÍNCULOS -->
                    <div class="tab-pane fade" id="pane-vinculos" role="tabpanel" aria-labelledby="tab-vinculos">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold mb-0">Associações vinculadas</h6>
                            <?php if ($user->hasPermission(NIVEL_ADMIN)): ?>
                            <a href="<?php echo SITE_URL; ?>/associacao/associados" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil-square me-1"></i>Gerenciar Vínculos</a>
                            <?php endif; ?>
                        </div>
                        <?php if (empty($cingulas)): ?>
                            <span class="text-muted small">Nenhum vínculo registrado. Use "Gerenciar Vínculos" para associar este associado a uma ou mais associações.</span>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-striped mb-0">
                                    <thead><tr><th>Associação</th><th>Sigla</th><th>Vínculo desde</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($cingulas as $ac): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($ac['nome']); ?></td>
                                            <td><span class="badge rounded-pill text-white" style="background:linear-gradient(135deg,#2f9e6f,#2b9d8f);"><?php echo htmlspecialchars($ac['sigla'] ?? '—'); ?></span></td>
                                            <td><?php echo !empty($ac['data_vinculo']) ? date('d/m/Y', strtotime($ac['data_vinculo'])) : '—'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>