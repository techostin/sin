<?php
/**
 * Portal do Associado - Área Logada
 * Dashboard com: 2ª via boleto/PIX, carteirinha digital QR, edição perfil
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/Associado.php';
require_once __DIR__ . '/../../classes/Mensalidade.php';
require_once __DIR__ . '/../../classes/Carteirinha.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/CSRFProtection.php';
require_once __DIR__ . '/../../classes/FichaAdesao.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar se associado está logado
if (!isset($_SESSION['associado_id'])) {
    header('Location: ' . SITE_URL . '/portal/login');
    exit;
}

$associadoId = (int) $_SESSION['associado_id'];
$db = Database::getInstance()->getConnection();

$associadoModel = new Associado();
$associado = $associadoModel->getById($associadoId);

if (!$associado) {
    session_destroy();
    header('Location: ' . SITE_URL . '/portal/login');
    exit;
}

// Verificar se o associado tem cargo de gestão em alguma associação
$isGestorAssociacaoPortal = false;
$stmtPortal = $db->prepare("
    SELECT 1 FROM associado_associacao 
    WHERE associado_id = ? 
    AND tipo_vinculo IN ('diretor','secretario','tesoureiro','comissao_educativa','comissao_etica','conselho_fiscal','presidente','vice-presidente')
");
$stmtPortal->execute([$associadoId]);
$isGestorAssociacaoPortal = $stmtPortal->fetchColumn() > 0;

$mensalidadeModel = new Mensalidade();
$carteirinhaModel = new Carteirinha();

// Buscar dados
$mensalidades = $mensalidadeModel->getByAssociado($associadoId);
$carteirinha = $carteirinhaModel->getByAssociado($associadoId);
$financeiro = $mensalidadeModel->getResumoFinanceiro($associadoId);

// Mensalidades em aberto (pendente/atrasado)
$emAberto = array_filter($mensalidades, function($m) {
    return in_array($m['status_mensalidade'], ['pendente', 'atrasado']);
});

// Mensalidades pagas
$pagas = array_filter($mensalidades, function($m) {
    return $m['status_mensalidade'] === 'pago';
});

// Buscar ficha de adesão do associado
$fichaModel = new FichaAdesao();
$fichaCompleta = $fichaModel->getCompletaByAssociadoId($associadoId);
$ficha = $fichaCompleta ? $fichaCompleta['ficha'] : null;
$fichaProdutos = $fichaCompleta ? $fichaCompleta['produtos'] : [];
$fichaStatus = $ficha ? $ficha['status'] : 'sem_ficha';

// Processar atualização de perfil
$msgPerfil = '';
$msgPerfilTipo = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'atualizar_perfil') {
    if (CSRFProtection::validatePostRequest()) {
        // Apenas strings (um array, ex.: telefone[]=x, causaria TypeError/500)
        $texto = static function ($campo) {
            $valor = $_POST[$campo] ?? '';
            return is_string($valor) ? trim($valor) : '';
        };

        $dados = [
            'telefone' => $texto('telefone'),
            'email' => $texto('email'),
            'endereco' => $texto('endereco'),
            'numero' => $texto('numero'),
            'complemento' => $texto('complemento'),
            'bairro' => $texto('bairro'),
            'cidade' => (int) ($_POST['cidade'] ?? 0),
            'cep' => preg_replace('/\D/', '', is_string($_POST['cep'] ?? null) ? $_POST['cep'] : ''),
        ];

        // Segurança: o e-mail é a chave de vínculo entre o associado e o usuário
        // do sistema. Permitir trocar para um e-mail já existente em outra conta
        // abriria caminho para escalonamento de privilégio.
        $emailRecusado = false;
        $emailNovo = (string) $dados['email'];
        if ($emailNovo !== '' && strcasecmp($emailNovo, (string) ($associado['email'] ?? '')) !== 0) {
            try {
                $stmtEmail = $db->prepare("SELECT COUNT(*) FROM associados WHERE email = ? AND id <> ?");
                $stmtEmail->execute([$emailNovo, $associadoId]);
                $emUso = (int) $stmtEmail->fetchColumn() > 0;

                if (!$emUso) {
                    $stmtEmail = $db->prepare("SELECT COUNT(*) FROM usuarios WHERE email = ?");
                    $stmtEmail->execute([$emailNovo]);
                    $emUso = (int) $stmtEmail->fetchColumn() > 0;
                }

                if ($emUso) {
                    unset($dados['email']);
                    $emailRecusado = true;
                }
            } catch (PDOException $e) {
                error_log('Portal area: falha ao validar e-mail - ' . $e->getMessage());
                unset($dados['email']);
                $emailRecusado = true;
            }
        }

        $campos = [];
        $valores = [];
        foreach ($dados as $campo => $valor) {
            if ($valor !== '' && $campo !== 'cidade') {
                $campos[] = "$campo = ?";
                $valores[] = $valor;
            } elseif ($campo === 'cidade' && $valor > 0) {
                $campos[] = "$campo = ?";
                $valores[] = $valor;
            }
        }
        
        if ($campos) {
            $valores[] = $associadoId;
            $sql = "UPDATE associados SET " . implode(', ', $campos) . ", ultima_atualizacao = NOW() WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute($valores);
            
            // Atualizar sessão
            $_SESSION['associado_email'] = $dados['email'] ?? $_SESSION['associado_email'];
            
            if ($emailRecusado) {
                $msgPerfil = 'Dados atualizados. O e-mail informado já está em uso por outra conta e por isso NÃO foi alterado.';
                $msgPerfilTipo = 'warning';
            } else {
                $msgPerfil = 'Perfil atualizado com sucesso!';
                $msgPerfilTipo = 'success';
            }
            
            // Recarregar dados
            $associado = $associadoModel->getById($associadoId);
        }
    } else {
        $msgPerfil = 'Erro de segurança: token inválido.';
        $msgPerfilTipo = 'danger';
    }
}

$page_title = "Minha Área - Portal do Associado";
// A página já exige $_SESSION['associado_id'] (linhas iniciais). Autorizamos o
// header a renderizar também para quem entrou apenas pelo Portal (sem sessão
// do sistema principal), como no fluxo do Link Mágico.
$PERMITIR_SESSAO_PORTAL = true;
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
?>
<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-person-circle text-primary me-2"></i>Minha Área</h1>
            <span class="text-muted">Bem-vindo, <?php echo htmlspecialchars($associado['nome']); ?>!</span>
        </div>
        <div class="d-flex gap-2">
            <a href="<?php echo SITE_URL; ?>/portal/logout" class="btn btn-outline-danger btn-sm"><i class="bi bi-box-arrow-right me-1"></i> Sair</a>
        </div>
    </div>

    <?php if ($msgPerfil): ?>
        <div class="alert alert-<?php echo $msgPerfilTipo; ?> alert-dismissible fade show">
            <?php echo $msgPerfil; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Cards Resumo -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Mensalidades em Aberto</h6>
                    <h3 class="mb-0 text-danger"><?php echo count($emAberto); ?></h3>
                    <small class="text-muted">R$ <?php echo number_format(array_sum(array_column($emAberto, 'valor_cobrado')), 2, ',', '.'); ?></small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Mensalidades Pagas</h6>
                    <h3 class="mb-0 text-success"><?php echo count($pagas); ?></h3>
                    <small class="text-muted">R$ <?php echo number_format((float)$financeiro['total_pago'], 2, ',', '.'); ?></small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Carteirinha</h6>
                    <h3 class="mb-0 <?php echo $carteirinha ? 'text-success' : 'text-warning'; ?>">
                        <?php echo $carteirinha ? 'Ativa' : 'Não Gerada'; ?>
                    </h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Status</h6>
                    <span class="badge bg-<?php 
                        switch ($associado['status_associado'] ?? 'Ativo') {
                            case 'Inadimplente': echo 'danger'; break;
                            case 'Afastado': echo 'warning text-dark'; break;
                            case 'Desligado': case 'Falecido': echo 'secondary'; break;
                            default: echo 'success';
                        }
                    ?>">
                        <?php echo htmlspecialchars($associado['status_associado'] ?? 'Ativo'); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <!-- Coluna Esquerda: Ações Rápidas -->
        <div class="col-lg-4">
            <!-- Carteirinha Digital -->
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-credit-card-2-front me-2"></i>Carteirinha Digital</h5>
                </div>
                <div class="card-body text-center">
                    <?php if ($carteirinha): ?>
                        <div class="mb-3">
                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo urlencode(SITE_URL . '/carteirinha/' . $carteirinha['token']); ?>" alt="QR Code Carteirinha" class="img-fluid border rounded">
                        </div>
                        <p class="mb-2"><strong><?php echo htmlspecialchars($associado['nome'] . ' ' . $associado['sobrenome']); ?></strong></p>
                        <p class="text-muted small mb-1">CPF: <?php echo htmlspecialchars($associado['cpf'] ?? '—'); ?></p>
                        <p class="text-muted small mb-3">Associação: <?php echo htmlspecialchars($associado['associacao_nome'] ?? '—'); ?></p>
                        <div class="d-grid gap-2">
                            <a href="<?php echo SITE_URL; ?>/associado/<?php echo $associadoId; ?>/carteirinha-pdf" target="_blank" class="btn btn-success"><i class="bi bi-file-earmark-pdf me-1"></i> PDF da Carteirinha</a>
                            <a href="<?php echo SITE_URL; ?>/carteirinha/<?php echo $carteirinha['token']; ?>" target="_blank" class="btn btn-outline-primary"><i class="bi bi-qr-code me-1"></i> Ver Pública</a>
                        </div>
                    <?php else: ?>
                        <div class="text-muted mb-3">
                            <i class="bi bi-credit-card-2-front" style="font-size: 3rem;"></i>
                            <p class="mt-2">Carteirinha não gerada ainda.</p>
                        </div>
                        <form method="post" action="<?php echo SITE_URL; ?>/associado/<?php echo $associadoId; ?>/carteirinha-gerar" class="d-inline" onsubmit="return confirm('Gerar nova carteirinha digital?');">
                            <?php echo CSRFProtection::getTokenField(); ?>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-qr-code me-1"></i> Gerar Carteirinha</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Mensalidades em Aberto -->
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-cash-coin me-2"></i>Mensalidades em Aberto</h5>
                    <span class="badge bg-danger"><?php echo count($emAberto); ?></span>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($emAberto)): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-check-circle-fill text-success" style="font-size: 2rem;"></i>
                            <p class="mt-2 mb-0">Nenhuma mensalidade em aberto!</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($emAberto as $m): 
                                $atraso = (strtotime($m['data_vencimento']) < strtotime('today')) ? ' (ATRASADA)' : '';
                                $cor = $atraso ? 'text-danger fw-bold' : 'text-warning';
                            ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-bold"><?php echo str_pad((string)$m['mes_referencia'], 2, '0', STR_PAD_LEFT) . '/' . $m['ano_referencia']; ?></div>
                                    <small class="text-muted">Vence: <?php echo date('d/m/Y', strtotime($m['data_vencimento'])); ?></small>
                                </div>
                                <div class="text-end">
                                    <div class="fs-6 <?php echo $cor; ?>">R$ <?php echo number_format((float)$m['valor_cobrado'], 2, ',', '.'); ?></div>
                                    <span class="badge bg-<?php echo $atraso ? 'danger' : 'warning text-dark'; ?>"><?php echo ucfirst($m['status_mensalidade']) . $atraso; ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Últimas Pagamentos -->
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Últimos Pagamentos</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($pagas)): ?>
                        <div class="text-center py-4 text-muted">Nenhum pagamento registrado.</div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach (array_slice($pagas, 0, 5) as $m): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-bold"><?php echo str_pad((string)$m['mes_referencia'], 2, '0', STR_PAD_LEFT) . '/' . $m['ano_referencia']; ?></div>
                                    <small class="text-muted">Pago em: <?php echo $m['data_pagamento'] ? date('d/m/Y', strtotime($m['data_pagamento'])) : '—'; ?></small>
                                </div>
                                <div class="text-end text-success fw-bold">R$ <?php echo number_format((float)$m['valor_cobrado'], 2, ',', '.'); ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Coluna Direita: Abas -->
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header p-0 border-0">
                    <ul class="nav nav-tabs" id="areaTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="tab-boletos" data-bs-toggle="tab" data-bs-target="#pane-boletos" type="button" role="tab">
                                <i class="bi bi-file-earmark-text me-1"></i> 2ª Via Boletos/PIX
                            </li>
                            <?php if ($isGestorAssociacaoPortal): ?>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="tab-assembleias" data-bs-toggle="tab" data-bs-target="#pane-assembleias" type="button" role="tab">
                                    <i class="bi bi-people-fill me-1"></i> Assembleias e Votações
                                </button>
                            </li>
                            <?php endif; ?>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="tab-perfil" data-bs-toggle="tab" data-bs-target="#pane-perfil" type="button" role="tab">
                                    <i class="bi bi-person-gear me-1"></i> Meu Perfil
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="tab-ficha" data-bs-toggle="tab" data-bs-target="#pane-ficha" type="button" role="tab">
                                    <i class="bi bi-person-vcard me-1"></i> Minha Ficha
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="tab-historico" data-bs-toggle="tab" data-bs-target="#pane-historico" type="button" role="tab">
                                    <i class="bi bi-clock-history me-1"></i> Histórico Financeiro
                                </button>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body tab-content">
                        <!-- Aba Boletos/PIX -->
                        <div class="tab-pane fade show active" id="pane-boletos" role="tabpanel">
                            <h6 class="mb-3">Mensalidades em Aberto - Gere PIX ou veja detalhes</h6>
                            <?php if (empty($emAberto)): ?>
                                <div class="alert alert-success">Todas as mensalidades estão em dia!</div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Referência</th>
                                                <th>Vencimento</th>
                                                <th>Valor</th>
                                                <th>Situação</th>
                                                <th>Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($emAberto as $m): 
                                                $atraso = (strtotime($m['data_vencimento']) < strtotime('today'));
                                            ?>
                                            <tr class="<?php echo $atraso ? 'table-danger' : ''; ?>">
                                                <td><?php echo str_pad((string)$m['mes_referencia'], 2, '0', STR_PAD_LEFT) . '/' . $m['ano_referencia']; ?></td>
                                                <td><?php echo date('d/m/Y', strtotime($m['data_vencimento'])); ?></td>
                                                <td class="fw-bold">R$ <?php echo number_format((float)$m['valor_cobrado'], 2, ',', '.'); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $atraso ? 'danger' : 'warning text-dark'; ?>">
                                                        <?php echo ucfirst($m['status_mensalidade']) . ($atraso ? ' - ATRASADA' : ''); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="<?php echo SITE_URL; ?>/mensalidade/<?php echo $m['id']; ?>/baixa" class="btn btn-sm btn-primary" target="_blank">
                                                        <i class="bi bi-qr-code me-1"></i> Gerar PIX
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Aba Perfil -->
                        <div class="tab-pane fade" id="pane-perfil" role="tabpanel">
                            <form method="post" class="mt-3">
                                <?php echo CSRFProtection::getTokenField(); ?>
                                <input type="hidden" name="acao" value="atualizar_perfil">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Nome Completo</label>
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($associado['nome'] . ' ' . $associado['sobrenome']); ?>" readonly>
                                        <div class="form-text">Para alterar nome, entre em contato com a secretaria.</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">CPF</label>
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($associado['cpf'] ?? '—'); ?>" readonly>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">E-mail</label>
                                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($associado['email'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Telefone/WhatsApp</label>
                                        <input type="tel" name="telefone" class="form-control telefone-mask" value="<?php echo htmlspecialchars($associado['telefone'] ?? ''); ?>" placeholder="(00) 00000-0000">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">CEP</label>
                                        <input type="text" name="cep" class="form-control cep-mask" value="<?php echo htmlspecialchars($associado['cep'] ?? ''); ?>" placeholder="00000-000">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Endereço</label>
                                        <input type="text" name="endereco" class="form-control" value="<?php echo htmlspecialchars($associado['endereco'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Número</label>
                                        <input type="text" name="numero" class="form-control" value="<?php echo htmlspecialchars($associado['numero'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Complemento</label>
                                        <input type="text" name="complemento" class="form-control" value="<?php echo htmlspecialchars($associado['complemento'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Bairro</label>
                                        <input type="text" name="bairro" class="form-control" value="<?php echo htmlspecialchars($associado['bairro'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Cidade</label>
                                        <select name="cidade" class="form-select">
                                            <option value="">Selecione...</option>
                                            <?php
                                            $stmt = $db->query("SELECT c.id, c.nome, e.uf FROM cidades c JOIN estados e ON e.id = c.estado WHERE e.uf = 'RS' ORDER BY c.nome");
                                            while ($c = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                                $sel = ($associado['cidade'] == $c['id']) ? 'selected' : '';
                                                echo "<option value='{$c['id']}' $sel>" . htmlspecialchars($c['nome']) . " - RS</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="d-grid gap-2 mt-3">
                                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Salvar Alterações</button>
                                </div>
                            </form>
                        </div>

                        <!-- Aba Minha Ficha -->
                        <div class="tab-pane fade" id="pane-ficha" role="tabpanel">
                            <div class="mt-3">
                                <div class="card shadow-sm mb-3">
                                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0"><i class="bi bi-person-vcard me-2"></i>Minha Ficha de Adesão</h5>
                                        <span class="badge bg-<?php 
                                            echo match($fichaStatus) {
                                                'aprovada' => 'success',
                                                'pendente' => 'warning text-dark',
                                                'recusada' => 'danger',
                                                'rascunho' => 'secondary',
                                                default => 'info'
                                            }; 
                                        ?>">
                                            <?php echo ucfirst($fichaStatus); ?>
                                        </span>
                                    </div>
                                    <div class="card-body">
                                        <?php if ($fichaStatus === 'sem_ficha'): ?>
                                            <div class="alert alert-info mb-3">
                                                <i class="bi bi-info-circle me-1"></i>
                                                Você ainda não iniciou a Ficha de Adesão ao Quadro Associativo.
                                            </div>
                                            <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                                                <button type="button" class="btn btn-primary btn-lg" onclick="abrirModalFicha()">
                                                    <i class="bi bi-person-plus me-1"></i> Preencher Minha Ficha
                                                </button>
                                            </div>
                                            
                                        <?php elseif ($fichaStatus === 'rascunho'): ?>
                                            <div class="alert alert-secondary mb-3">
                                                <i class="bi bi-pencil me-1"></i>
                                                Você tem uma ficha em <strong>rascunho</strong> (salva mas não submetida).
                                            </div>
                                            <div class="row g-3 mb-3">
                                                <div class="col-md-6">
                                                    <strong>Iniciada em:</strong>
                                                    <div><?php echo $ficha['created_at'] ? date('d/m/Y H:i', strtotime($ficha['created_at'])) : '—'; ?></div>
                                                </div>
                                                <div class="col-md-6">
                                                    <strong>Última atualização:</strong>
                                                    <div><?php echo $ficha['updated_at'] ? date('d/m/Y H:i', strtotime($ficha['updated_at'])) : '—'; ?></div>
                                                </div>
                                            </div>
                                            <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                                                <button type="button" class="btn btn-primary btn-lg" onclick="abrirModalFicha()">
                                                    <i class="bi bi-pencil me-1"></i> Continuar Preenchimento
                                                </button>
                                                <button type="button" class="btn btn-outline-secondary" onclick="abrirFichaModal(<?php echo $ficha['id']; ?>)">
                                                    <i class="bi bi-eye me-1"></i> Visualizar Rascunho
                                                </button>
                                            </div>
                                            
                                        <?php elseif ($fichaStatus === 'aprovada'): ?>
                                            <div class="alert alert-success mb-3">
                                                <i class="bi bi-check-circle-fill me-1"></i>
                                                Sua ficha foi <strong>aprovada</strong> pela Diretoria!
                                            </div>
                                            <div class="row g-3 mb-3">
                                                <div class="col-md-4">
                                                    <strong>Nº de Cadastro:</strong>
                                                    <div><?php echo htmlspecialchars($ficha['numero_cadastro'] ?? '—'); ?></div>
                                                </div>
                                                <div class="col-md-4">
                                                    <strong>Data de Entrada:</strong>
                                                    <div><?php echo $ficha['data_entrada'] ? date('d/m/Y', strtotime($ficha['data_entrada'])) : '—'; ?></div>
                                                </div>
                                                <div class="col-md-4">
                                                    <strong>Tipo de Associado:</strong>
                                                    <div><?php echo htmlspecialchars(FichaAdesao::labelTipoAssociado($ficha['tipo_associado'])); ?></div>
                                                </div>
                                            </div>
                                            <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                                                <button type="button" class="btn btn-outline-primary" onclick="abrirFichaModal(<?php echo $ficha['id']; ?>)">
                                                    <i class="bi bi-eye me-1"></i> Ver Ficha Completa
                                                </button>
                                            </div>
                                            
                                        <?php elseif ($fichaStatus === 'pendente'): ?>
                                            <div class="alert alert-warning mb-3">
                                                <i class="bi bi-hourglass-split me-1"></i>
                                                Sua ficha foi <strong>submetida</strong> e aguarda análise da Diretoria.
                                            </div>
                                            <div class="row g-3 mb-3">
                                                <div class="col-md-6">
                                                    <strong>Submetida em:</strong>
                                                    <div><?php echo $ficha['data_submissao'] ? date('d/m/Y H:i', strtotime($ficha['data_submissao'])) : '—'; ?></div>
                                                </div>
                                                <div class="col-md-6">
                                                    <strong>Status:</strong>
                                                    <div><span class="badge bg-warning text-dark">Pendente de análise</span></div>
                                                </div>
                                            </div>
                                            <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                                                <button type="button" class="btn btn-outline-primary" onclick="abrirFichaModal(<?php echo $ficha['id']; ?>)">
                                                    <i class="bi bi-eye me-1"></i> Ver Ficha Submetida
                                                </button>
                                            </div>
                                            
                                        <?php elseif ($fichaStatus === 'recusada'): ?>
                                            <div class="alert alert-danger mb-3">
                                                <i class="bi bi-x-octagon-fill me-1"></i>
                                                Sua ficha foi <strong>recusada</strong> pela Diretoria.
                                            </div>
                                            <?php if (!empty($ficha['parecer_diretoria'])): ?>
                                            <div class="card mb-3 border-danger">
                                                <div class="card-header bg-danger text-white">Parecer da Diretoria</div>
                                                <div class="card-body">
                                                    <?php echo nl2br(htmlspecialchars($ficha['parecer_diretoria'])); ?>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                            <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                                                <button type="button" class="btn btn-primary btn-lg" onclick="abrirModalFicha()">
                                                    <i class="bi bi-pencil me-1"></i> Corrigir e Reenviar
                                                </button>
                                                <button type="button" class="btn btn-outline-secondary" onclick="abrirFichaModal(<?php echo $ficha['id']; ?>)">
                                                    <i class="bi bi-eye me-1"></i> Ver Ficha Recusada
                                                </button>
                                            </div>
                                            
                                        <?php else: ?>
                                            <div class="alert alert-secondary mb-3">
                                                <i class="bi bi-file-text me-1"></i>
                                                Status da ficha: <?php echo htmlspecialchars($fichaStatus); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Aba Histórico -->
                        <div class="tab-pane fade" id="pane-historico" role="tabpanel">
                            <div class="table-responsive">
                                <?php if (empty($mensalidades)): ?>
                                    <div class="alert alert-info">Nenhuma mensalidade encontrada.</div>
                                <?php else: ?>
                                    <table class="table table-hover mb-0" id="tabelaHistorico">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Referência</th>
                                                <th>Vencimento</th>
                                                <th>Plano</th>
                                                <th>Valor</th>
                                                <th>Situação</th>
                                                <th>Pagamento</th>
                                                <th>Método</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($mensalidades as $m): ?>
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
                                                <td><?php echo $m['metodo_pagamento'] ?? '—'; ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                        </div>
                        </div>

                        <!-- Aba Assembleias e Votações (apenas para cargos de gestão) -->
                        <?php if ($isGestorAssociacaoPortal): ?>
                        <div class="tab-pane fade" id="pane-assembleias" role="tabpanel">
                            <div class="card shadow-sm">
                                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><i class="bi bi-people-fill text-primary me-2"></i>Minhas Assembleias</h5>
                                    <a href="<?php echo SITE_URL; ?>/assembleia" class="btn btn-sm btn-outline-primary" target="_blank"><i class="bi bi-box-arrow-up-right me-1"></i> Ver Todas</a>
                                </div>
                                <div class="card-body">
                                    <?php
                                    // Buscar assembleias onde o associado pode votar/gerenciar
                                    $stmtAss = $db->prepare("
                                        SELECT a.*, ac.nome as associacao_nome, ac.sigla as associacao_sigla
                                        FROM assembleias a
                                        LEFT JOIN associacoes ac ON ac.id = a.associacao_id
                                        WHERE (a.associacao_id IS NULL OR a.associacao_id IN (
                                            SELECT associacao_id FROM associado_associacao WHERE associado_id = ?
                                        ))
                                        AND a.status IN ('rascunho','ativa','encerrada')
                                        ORDER BY a.data_inicio DESC
                                    ");
                                    $stmtAss->execute([$associadoId]);
                                    $minhasAssembleias = $stmtAss->fetchAll(PDO::FETCH_ASSOC);
                                    ?>
                                    <?php if (empty($minhasAssembleias)): ?>
                                    <div class="text-center py-4 text-muted">
                                        <i class="bi bi-calendar-x" style="font-size: 2rem;"></i>
                                        <p class="mt-2">Nenhuma assembleia disponível no momento.</p>
                                    </div>
                                    <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0" id="tabelaMinhasAssembleias">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Título</th>
                                                    <th>Associação</th>
                                                    <th>Período</th>
                                                    <th>Status</th>
                                                    <th class="text-center">Ações</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($minhasAssembleias as $ass): 
                                                    $agora = new DateTime();
                                                    $inicio = new DateTime($ass['data_inicio']);
                                                    $fim = new DateTime($ass['data_fim']);
                                                    $ativa = $ass['status'] === 'ativa' && $agora >= $inicio && $agora <= $fim;
                                                    $naoIniciada = $agora < $inicio;
                                                    $encerrada = $ass['status'] === 'encerrada' || $agora > $fim;
                                                    
                                                    // Verificar se já votou
                                                    $stmtVoto = $db->prepare("SELECT COUNT(*) FROM assembleia_votos WHERE assembleia_id = ? AND associado_id = ?");
                                                    $stmtVoto->execute([$ass['id'], $associadoId]);
                                                    $jaVotou = $stmtVoto->fetchColumn() > 0;
                                                ?>
                                                <tr class="<?php echo $ativa ? 'table-success' : ($naoIniciada ? 'table-warning' : ($encerrada ? 'table-secondary' : '')); ?>">
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($ass['titulo']); ?></strong>
                                                        <?php if (!empty($ass['descricao'])): ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars(mb_substr($ass['descricao'], 0, 80)); ?>...</small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo $ass['associacao_nome'] ? htmlspecialchars($ass['associacao_nome'].' ('.$ass['associacao_sigla'].')') : '<span class="text-muted">Todas</span>'; ?></td>
                                                    <td>
                                                        <div><?php echo date('d/m/Y H:i', strtotime($ass['data_inicio'])); ?></div>
                                                        <small class="text-muted">até <?php echo date('d/m/Y H:i', strtotime($ass['data_fim'])); ?></small>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $ativa ? 'success' : ($naoIniciada ? 'warning text-dark' : ($encerrada ? 'secondary' : 'info')); ?>">
                                                            <?php echo $ativa ? 'VOTAÇÃO ABERTA' : ($naoIniciada ? 'AGUARDANDO INÍCIO' : 'ENCERRADA'); ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <?php if ($ativa): ?>
                                                        <a href="<?php echo SITE_URL; ?>/assembleia/<?php echo $ass['id']; ?>" class="btn btn-sm btn-primary" <?php echo $jaVotou ? 'disabled' : ''; ?>>
                                                            <i class="bi bi-<?php echo $jaVotou ? 'check-circle' : 'ballot'; ?> me-1"></i>
                                                            <?php echo $jaVotou ? 'Já Votou' : 'Votar'; ?>
                                                        </a>
                                                        <?php else: ?>
                                                        <a href="<?php echo SITE_URL; ?>/assembleia/<?php echo $ass['id']; ?>/resultado" class="btn btn-sm btn-outline-info" target="_blank">
                                                            <i class="bi bi-graph-up me-1"></i> Ver Resultado
                                                        </a>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

<script>
$(document).ready(function() {
    $('#tabelaHistorico').DataTable({
        "language": { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json" },
        "pageLength": 10,
        "order": [[0, 'desc']]
    });
});

// Máscaras
document.querySelectorAll('.cep-mask').forEach(input => {
    input.addEventListener('input', e => {
        let v = e.target.value.replace(/\D/g, '');
        v = v.replace(/^(\d{5})(\d)/, '$1-$2');
        e.target.value = v;
    });
});

document.querySelectorAll('.telefone-mask').forEach(input => {
    input.addEventListener('input', e => {
        let v = e.target.value.replace(/\D/g, '');
        if (v.length <= 10) {
            v = v.replace(/^(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3');
        } else {
            v = v.replace(/^(\d{2})(\d{5})(\d{0,4})/, '($1) $2-$3');
        }
        e.target.value = v;
    });
});

// Função para abrir o modal da ficha de adesão
function abrirModalFicha() {
    var modalEl = document.getElementById('modalFichaAdesao');
    if (modalEl && window.bootstrap) {
        var modal = new bootstrap.Modal(modalEl);
        modal.show();
    }
}

/**
 * Abre a ficha de adesão do associado em modal via AJAX
 */
function abrirFichaModal(fichaId) {
    const modalEl = document.getElementById('modalFichaVisualizacao');
    if (!modalEl || !window.bootstrap) return;
    
    const modal = new bootstrap.Modal(modalEl);
    const modalBody = modalEl.querySelector('.modal-body');
    
    // Mostrar loading
    modalBody.innerHTML = `
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Carregando...</span>
            </div>
            <p class="mt-2 text-muted">Carregando ficha de adesão...</p>
        </div>
    `;
    modal.show();
    
    // Carregar via AJAX
    fetch('<?php echo SITE_URL; ?>/cadastro-ficha/visualizar?id=' + fichaId + '&modal=1', {
        credentials: 'same-origin',
        headers: {
            'X-CSRF-Token': window.CSRF_TOKEN || ''
        }
    })
    .then(response => response.text())
    .then(html => {
        modalBody.innerHTML = html;
        
        // Re-inicializar tooltips se necessário
        if (window.bootstrap && bootstrap.Tooltip) {
            const tooltips = modalEl.querySelectorAll('[data-bs-toggle="tooltip"]');
            tooltips.forEach(el => new bootstrap.Tooltip(el));
        }
    })
    .catch(err => {
        modalBody.innerHTML = `
            <div class="alert alert-danger text-center p-4">
                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                Erro ao carregar a ficha. Tente novamente.
            </div>
        `;
    });
}
</script>

<!-- Modal para visualização da ficha de adesão -->
<div class="modal fade" id="modalFichaVisualizacao" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-person-vcard-fill me-2"></i>Ficha de Adesão</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <!-- Conteúdo carregado via AJAX -->
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>