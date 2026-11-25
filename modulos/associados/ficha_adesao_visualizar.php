<?php
/**
 * Modal da Ficha de Adesão — carrega via AJAX para exibição em modal.
 * Suporta parâmetro ?modal=1 para retornar apenas o HTML do modal.
 * Suporta parâmetro ?pdf=1 para gerar PDF.
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/FichaAdesao.php';
require_once __DIR__ . '/../../classes/Associado.php';
require_once __DIR__ . '/../../classes/CSRFProtection.php';
require_once __DIR__ . '/../../classes/PortalAccess.php';

// DomPDF imports (must be at file level)
if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
}
use Dompdf\Dompdf;
use Dompdf\Options;

$modalMode = isset($_GET['modal']) && $_GET['modal'] === '1';
$pdfMode = isset($_GET['pdf']) && $_GET['pdf'] === '1';

$user = new User();
$model = new FichaAdesao();

// ── Avaliação (aprovar/recusar) via POST ──
$msgFlash = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    if (!$user->hasPermission(NIVEL_ADMIN) && !$user->isDiretoriaExecutiva()) {
        $msgFlash = ['tipo' => 'danger', 'texto' => 'Acesso negado.'];
    } elseif (!CSRFProtection::validatePostRequest()) {
        $msgFlash = ['tipo' => 'danger', 'texto' => 'Erro de segurança: token CSRF inválido.'];
    } else {
        $fichaIdPost = (int)($_POST['ficha_id'] ?? 0);
        $analisadoPor = (int)($_SESSION['user_id'] ?? $_SESSION['associado_id'] ?? 0);
        $res = $_POST['acao'] === 'aprovar'
            ? $model->aprovar($fichaIdPost, $analisadoPor)
            : $model->recusar($fichaIdPost, $analisadoPor, (string)($_POST['motivo'] ?? ''));
        $msgFlash = ['tipo' => $res['success'] ? 'success' : 'danger', 'texto' => $res['message']];
    }
}

$id = isset($_GET['id']) ? trim((string)$_GET['id']) : '';
$fichaId = 0;
if (ctype_digit($id)) {
    $fichaId = (int)$id;
} else {
    $fichaId = function_exists('decodeId') ? decodeId($id) : 0;
}

$dados = $fichaId > 0 ? $model->getById($fichaId) : null;
if (!$dados) {
    if ($modalMode) {
        echo "<div class='alert alert-warning p-3'>Ficha não encontrada.</div>";
    } else {
        echo "<div class='alert alert-warning m-4'>Ficha não encontrada.</div>";
    }
    exit;
}

$ficha = $dados['ficha'];
$produtos = $dados['produtos'];

// Permissão
// A regra de privilégio e a resolução do dono são centralizadas em
// PortalAccess/User. A ficha pode pertencer a um ASSOCIADO (associado_id) ou,
// no fluxo de pedido de acesso, a uma CONTA (usuario_id) — o titular é dono
// nos dois casos; qualquer outro usuário comum é negado.
$podeVerTodas = PortalAccess::podeConsultarOutros($user);
$associadoSessao = (int)$user->getAssociadoIdResolvido();
$usuarioSessao = (($_SESSION['user_table'] ?? '') === 'usuarios') ? (int)($_SESSION['user_id'] ?? 0) : 0;

$donoDaFicha = ((int)($ficha['associado_id'] ?? 0) > 0 && $associadoSessao > 0 && (int)$ficha['associado_id'] === $associadoSessao)
    || ((int)($ficha['usuario_id'] ?? 0) > 0 && $usuarioSessao > 0 && (int)$ficha['usuario_id'] === $usuarioSessao);

if (!$podeVerTodas && !$donoDaFicha) {
    if ($modalMode) {
        echo "<div class='alert alert-danger p-3'>Acesso negado.</div>";
    } else {
        echo "<div class='alert alert-danger m-4'>Acesso negado.</div>";
    }
    exit;
}

$associado = (new Associado())->getById((int)$ficha['associado_id']);
$nomeAssociado = $associado
    ? trim(($associado['nome'] ?? '') . ' ' . ($associado['sobrenome'] ?? ''))
    : trim($ficha['nome_completo'] ?? '');

$avaliador = '—';
if (!empty($ficha['analisado_por'])) {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT nome, sobrenome FROM associados WHERE id = ? LIMIT 1");
        $stmt->execute([(int)$ficha['analisado_por']]);
        $a = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($a) {
            $avaliador = trim($a['nome'] . ' ' . $a['sobrenome']);
        } else {
            $stmt = $db->prepare("SELECT nome, sobrenome FROM usuarios WHERE id = ? LIMIT 1");
            $stmt->execute([(int)$ficha['analisado_por']]);
            $u = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($u) $avaliador = trim($u['nome'] . ' ' . $u['sobrenome']);
        }
    } catch (PDOException $e) { /* mantém '—' */ }
}

function linha($rotulo, $valor, $col = 4) {
    $v = ($valor === null || $valor === '') ? '—' : htmlspecialchars((string)$valor);
    echo '<div class="col-md-' . $col . ' mb-2">';
    echo '<div class="small text-muted text-uppercase" style="font-size:.68rem; letter-spacing:.04em;">' . htmlspecialchars($rotulo) . '</div>';
    echo '<div class="fw-semibold">' . $v . '</div>';
    echo '</div>';
}

[$labelStatus, $corStatus] = FichaAdesao::labelStatus($ficha['status']);
$setProducao = array_values(array_intersect_key(FichaAdesao::SISTEMAS_PRODUCAO, array_flip(array_filter(explode(',', (string)$ficha['sistemas_producao'])))));
$setCanais = array_values(array_intersect_key(FichaAdesao::CANAIS_COMERCIALIZACAO, array_flip(array_filter(explode(',', (string)$ficha['canais_comercializacao'])))));

// Se for modo PDF, gerar PDF
if ($pdfMode) {
    gerarPDFFicha($ficha, $produtos, $nomeAssociado, $avaliador, $labelStatus, $corStatus, $setProducao, $setCanais);
    exit;
}

// Cabeçalho HTML (apenas se não for modal)
if (!$modalMode) {
    require_once __DIR__ . '/../../includes/header.php';
    require_once __DIR__ . '/../../includes/menu.php';
}
?>

<?php if ($modalMode): ?>
<!-- Modal content only - no header/menu -->
<div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
        <div class="modal-header bg-primary text-white">
            <h5 class="modal-title"><i class="bi bi-person-vcard-fill me-2"></i>Ficha de Adesão #<?php echo (int)$ficha['id']; ?></h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body p-0">
<?php else: ?>
<!-- Full page layout -->
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/associados-modern.css">
<style>
    .ficha-view { max-width: 1050px; margin: 0 auto; background:#fff; border:1px solid #dce7dc; border-radius:.6rem; padding:1.4rem; box-shadow: 0 .25rem .75rem rgba(0,0,0,.06); }
    .ficha-view-header { text-align:center; border-bottom:4px solid #2f9e6f; padding-bottom:.9rem; margin-bottom:1rem; }
    .ficha-view-header h3 { font-weight:800; color:#155d3a; letter-spacing:.03em; }
    .ficha-view-secao { background:#eaf6ee; border-left:5px solid #2f9e6f; padding:.5rem .9rem; margin:1.3rem 0 .6rem; font-weight:700; color:#155d3a; text-transform:uppercase; letter-spacing:.04em; font-size:.85rem; border-radius:.3rem; }
    .ficha-view-tabela th { font-size:.78rem; text-transform:uppercase; letter-spacing:.03em; }
    @media print {
        #wrapper > nav, .no-print, #menu-toggle, .ficha-view-acoes { display:none !important; }
        .ficha-view { box-shadow:none; border:none; max-width:100%; }
    }
</style>

<div class="container-fluid px-4 py-3">
    <?php if ($msgFlash): ?>
        <div class="alert alert-<?php echo $msgFlash['tipo']; ?> alert-dismissible fade show no-print" role="alert">
            <i class="bi bi-<?php echo $msgFlash['tipo'] === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>-fill me-1"></i>
            <?php echo htmlspecialchars($msgFlash['texto']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <div class="ficha-view-acoes d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 no-print">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-person-vcard-fill text-primary me-2"></i>Ficha de Adesão ao Quadro Associativo</h1>
            <span class="text-muted small">Ficha #<?php echo (int)$ficha['id']; ?> &bull; <?php echo htmlspecialchars($nomeAssociado); ?></span>
        </div>
        <div class="d-flex gap-2">
            <a href="<?php echo SITE_URL; ?>/cadastro-ficha/visualizar?id=<?php echo (int)$ficha['id']; ?>&pdf=1" class="btn btn-outline-success btn-sm" target="_blank"><i class="bi bi-file-earmark-pdf me-1"></i> PDF</a>
            <button class="btn btn-outline-dark btn-sm" onclick="window.print()"><i class="bi bi-printer me-1"></i> Imprimir</button>
            <?php if ($podeVerTodas): ?>
            <a href="<?php echo SITE_URL; ?>/cadastros-pendentes" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> Voltar aos Pendentes</a>
            <?php else: ?>
            <a href="<?php echo SITE_URL; ?>/portal/area" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> Voltar</a>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<div class="<?php echo $modalMode ? 'p-3' : 'ficha-view'; ?>">

    <!-- Cabeçalho -->
    <div class="<?php echo $modalMode ? 'text-center mb-3 pb-2 border-bottom' : 'ficha-view-header'; ?>">
        <div class="small fw-semibold text-uppercase">AAES/RS &mdash; Associação de Agricultores Ecologistas Solidários do RS</div>
        <h3 class="mt-1 mb-1">FICHA DE CADASTRO DE ASSOCIADO</h3>
        <div class="small text-muted">Ficha de Adesão ao Quadro Associativo &bull; Rev. 2025</div>
    </div>

    <div class="d-flex justify-content-between flex-wrap gap-2 mb-1">
        <span class="badge bg-<?php echo $corStatus; ?> fs-6">
            <i class="bi bi-<?php echo $ficha['status'] === FichaAdesao::STATUS_APROVADA ? 'check-circle' : ($ficha['status'] === FichaAdesao::STATUS_RECUSADA ? 'x-circle' : ($ficha['status'] === FichaAdesao::STATUS_PENDENTE ? 'hourglass-split' : 'pencil')); ?>-fill me-1"></i>
            <?php echo htmlspecialchars($labelStatus); ?>
        </span>
        <span class="text-muted small align-self-center">
            <?php if ($ficha['status'] === FichaAdesao::STATUS_PENDENTE && $ficha['data_submissao']): ?>
                Submetida em <?php echo date('d/m/Y \à\s H:i', strtotime($ficha['data_submissao'])); ?>
            <?php elseif ($ficha['data_analise']): ?>
                Analisada em <?php echo date('d/m/Y \à\s H:i', strtotime($ficha['data_analise'])); ?> por <?php echo htmlspecialchars($avaliador); ?>
            <?php endif; ?>
        </span>
    </div>

    <?php if ($ficha['status'] === FichaAdesao::STATUS_RECUSADA && !empty($ficha['parecer_diretoria'])): ?>
    <div class="alert alert-danger py-2 small mt-2">
        <i class="bi bi-exclamation-octagon-fill me-1"></i>
        <strong>Parecer da Diretoria:</strong> <?php echo nl2br(htmlspecialchars($ficha['parecer_diretoria'])); ?>
    </div>
    <?php endif; ?>

    <div class="row g-3 mt-2">
        <div class="col-md-4"><?php linha('Nº de Cadastro', $ficha['numero_cadastro']); ?></div>
        <div class="col-md-4"><?php linha('Data de Entrada', $ficha['data_entrada'] ? date('d/m/Y', strtotime($ficha['data_entrada'])) : null); ?></div>
        <div class="col-md-4"><?php linha('Tipo de Associado', FichaAdesao::labelTipoAssociado($ficha['tipo_associado'])); ?></div>
    </div>

    <!-- 1. Identificação Pessoal -->
    <div class="ficha-view-secao">1. Identificação Pessoal</div>
    <div class="row g-2">
        <?php linha('Nome Completo', $ficha['nome_completo'], 6); ?>
        <?php linha('Nome Social', $ficha['nome_social'], 6); ?>
        <?php linha('Data de Nascimento', $ficha['data_nascimento'] ? date('d/m/Y', strtotime($ficha['data_nascimento'])) : null, 3); ?>
        <?php linha('CPF', $ficha['cpf'], 3); ?>
        <?php linha('RG / Órgão Emissor', $ficha['rg_orgao_emissor'], 6); ?>
        <?php linha('Sexo / Gênero', $ficha['sexo_genero'], 3); ?>
        <?php linha('Nacionalidade', $ficha['nacionalidade'], 3); ?>
        <?php linha('Estado Civil', $ficha['estado_civil'], 3); ?>
        <?php linha('Escolaridade', $ficha['escolaridade'], 3); ?>
    </div>

    <!-- 2. Endereço -->
    <div class="ficha-view-secao">2. Endereço</div>
    <div class="row g-2">
        <?php linha('Logradouro / Linha / Comunidade', $ficha['logradouro'], 6); ?>
        <?php linha('Número / Lote', $ficha['numero'], 3); ?>
        <?php linha('Complemento / Localidade', $ficha['complemento'], 3); ?>
        <?php linha('Bairro / Distrito', $ficha['bairro'], 4); ?>
        <?php linha('Município', $ficha['municipio'], 4); ?>
        <?php linha('Estado', $ficha['estado'], 2); ?>
        <?php linha('CEP', $ficha['cep'], 2); ?>
        <?php linha('Zona', isset(FichaAdesao::ZONAS[$ficha['zona']]) ? FichaAdesao::ZONAS[$ficha['zona']] : null, 4); ?>
        <?php linha('Latitude / Longitude', $ficha['latitude_longitude'], 4); ?>
        <?php linha('Georreferenciamento SNCR', $ficha['georreferenciamento_sncr'], 4); ?>
    </div>

    <!-- 3. Contato -->
    <div class="ficha-view-secao">3. Contato</div>
    <div class="row g-2">
        <?php linha('Telefone / WhatsApp', $ficha['telefone_whatsapp'], 4); ?>
        <?php linha('Telefone Alternativo', $ficha['telefone_alternativo'], 4); ?>
        <?php linha('E-mail', $ficha['email'], 4); ?>
    </div>

    <!-- 4. Dados da Propriedade Rural -->
    <div class="ficha-view-secao">4. Dados da Propriedade Rural</div>
    <div class="row g-2">
        <?php linha('Nome / Denominação da Propriedade', $ficha['nome_propriedade'], 6); ?>
        <?php linha('Nº do NIRF / ITR', $ficha['nirf_itr'], 6); ?>
        <?php linha('Área Total (ha)', $ficha['area_total_ha'], 4); ?>
        <?php linha('Área Cultivada (ha)', $ficha['area_cultivada_ha'], 4); ?>
        <?php linha('Área de Preservação (ha)', $ficha['area_preservacao_ha'], 4); ?>
        <?php linha('Situação Fundiária', isset(FichaAdesao::SITUACOES_FUNDIARIAS[$ficha['situacao_fundiaria']]) ? FichaAdesao::SITUACOES_FUNDIARIAS[$ficha['situacao_fundiaria']] : null, 4); ?>
        <?php linha('DAP / CAF (nº)', $ficha['dap_caf'], 4); ?>
        <?php linha('Pessoas na Família', $ficha['pessoas_familia'], 2); ?>
        <?php linha('Pessoas que Trabalham na Propriedade', $ficha['pessoas_trabalham'], 4); ?>
        <?php linha('Renda Mensal Estimada (R$)', $ficha['renda_mensal_estimada'] !== null && $ficha['renda_mensal_estimada'] !== '' ? number_format((float)$ficha['renda_mensal_estimada'], 2, ',', '.') : null, 4); ?>
    </div>

    <!-- 5. Produção Agroecológica -->
    <div class="ficha-view-secao">5. Produção Agroecológica</div>
    <div class="row g-2">
        <?php linha('Tempo de Produção Agroecológica', $ficha['tempo_producao_agroecologica'], 4); ?>
        <?php linha('Certificação Orgânica', $ficha['certificacao_organica'], 8); ?>
        <?php linha('Tipo de Certificação', isset(FichaAdesao::TIPOS_CERTIFICACAO[$ficha['tipo_certificacao']]) ? FichaAdesao::TIPOS_CERTIFICACAO[$ficha['tipo_certificacao']] : null, 4); ?>
        <div class="col-md-8">
            <div class="small text-muted text-uppercase" style="font-size:.68rem; letter-spacing:.04em;">Sistemas de Produção Utilizados</div>
            <?php if (empty($setProducao)): ?>
                <div class="fw-semibold text-muted">—</div>
            <?php else: ?>
                <div>
                    <?php foreach ($setProducao as $sp): ?>
                        <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle me-1 mb-1"><?php echo htmlspecialchars($sp); ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-12">
            <div class="small text-muted text-uppercase fw-bold mb-1" style="font-size:.68rem; letter-spacing:.04em;">Principais Produtos / Culturas</div>
            <?php if (empty($produtos)): ?>
                <span class="text-muted">—</span>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered ficha-view-tabela mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width:30px;">#</th>
                                <th>Produto / Cultura</th>
                                <th>Variedade / Espécie</th>
                                <th>Produção Estimada</th>
                                <th>Unidade</th>
                                <th>Destino Principal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($produtos as $i => $pr): ?>
                            <tr>
                                <td class="text-center"><?php echo $i + 1; ?></td>
                                <td><?php echo htmlspecialchars($pr['produto_cultura'] ?? '—'); ?></td>
                                <td><?php echo htmlspecialchars($pr['variedade_especie'] ?? '—'); ?></td>
                                <td><?php echo htmlspecialchars($pr['producao_estimada'] ?? '—'); ?></td>
                                <td><?php echo htmlspecialchars($pr['unidade'] ?? '—'); ?></td>
                                <td><?php echo htmlspecialchars($pr['destino_principal'] ?? '—'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-md-6">
            <div class="small text-muted text-uppercase" style="font-size:.68rem; letter-spacing:.04em;">Canais de Comercialização</div>
            <?php if (empty($setCanais)): ?>
                <div class="fw-semibold text-muted">—</div>
            <?php else: ?>
                <div>
                    <?php foreach ($setCanais as $cc): ?>
                        <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle me-1 mb-1"><?php echo htmlspecialchars($cc); ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php linha('Participa de Outra Organização / Cooperativa', $ficha['outra_organizacao'], 6); ?>
    </div>

    <!-- 6. Dados Bancários -->
    <div class="ficha-view-secao">6. Dados Bancários (para recebimentos / repasses)</div>
    <div class="row g-2">
        <?php linha('Banco', $ficha['banco'], 4); ?>
        <?php linha('Agência', $ficha['agencia'], 4); ?>
        <?php linha('Conta (corrente / poupança)', $ficha['conta'], 4); ?>
        <?php linha('Chave Pix', $ficha['chave_pix'], 6); ?>
        <?php linha('Titular da Conta', $ficha['titular_conta'], 6); ?>
    </div>

    <!-- 7. Observações -->
    <div class="ficha-view-secao">7. Observações / Informações Complementares</div>
    <p class="mb-0"><?php echo $ficha['observacoes'] ? nl2br(htmlspecialchars($ficha['observacoes'])) : '<span class="text-muted">—</span>'; ?></p>

    <!-- Assinaturas -->
    <div class="row g-3 mt-4 pt-3" style="border-top:2px dashed #9db8a6;">
        <div class="col-md-6">
            <div class="small text-muted text-uppercase" style="font-size:.68rem; letter-spacing:.04em;">Assinatura do(a) Associado(a)</div>
            <div class="fw-semibold"><?php echo htmlspecialchars($nomeAssociado); ?></div>
            <div class="small text-muted">
                Nome legível: <?php echo htmlspecialchars($nomeAssociado); ?> &bull;
                Data: <?php echo $ficha['data_submissao'] ? date('d/m/Y', strtotime($ficha['data_submissao'])) : date('d/m/Y', strtotime($ficha['created_at'])); ?>
            </div>
            <div class="small mt-1">
                <?php echo !empty($ficha['aceite_termos']) ? '<i class="bi bi-check-circle-fill text-success me-1"></i>Declaração de veracidade e LGPD aceitas.' : '<i class="bi bi-dash-circle text-warning me-1"></i>Aceite não registrado.'; ?>
            </div>
        </div>
        <div class="col-md-6">
            <div class="small text-muted text-uppercase" style="font-size:.68rem; letter-spacing:.04em;">Responsável pelo Cadastro (AAES/RS)</div>
            <div class="fw-semibold"><?php echo htmlspecialchars($avaliador); ?></div>
            <div class="small text-muted">
                <?php if ($ficha['status'] === FichaAdesao::STATUS_APROVADA): ?>
                    Aprovada em <?php echo date('d/m/Y', strtotime($ficha['data_analise'])); ?>
                    <?php if ($ficha['numero_cadastro']): ?> &bull; Nº <?php echo htmlspecialchars($ficha['numero_cadastro']); ?><?php endif; ?>
                <?php else: ?>
                    Aguardando avaliação da Diretoria/Presidência.
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="mt-3 p-2 small" style="background:#f6f4e8; border:1px solid #e4dcb8; border-radius:.4rem; color:#5b5434;">
        <i class="bi bi-shield-lock me-1"></i>
        <strong>Aviso de privacidade:</strong> os dados coletados nesta ficha são utilizados exclusivamente para fins de gestão associativa, acesso a políticas públicas e comercialização de produtos, em conformidade com a Lei Geral de Proteção de Dados (Lei nº 13.709/2018). O(a) associado(a) pode solicitar alteração, exclusão ou portabilidade de seus dados a qualquer momento junto à secretaria da associação.
    </div>

    <?php if ($podeVerTodas && $ficha['status'] === FichaAdesao::STATUS_PENDENTE): ?>
    <div class="d-flex gap-2 justify-content-end mt-4 no-print">
        <form method="POST" class="d-inline"
              onsubmit="return confirm('Aprovar esta ficha de adesão? O número de cadastro será gerado e o associado será vinculado ao quadro.');">
            <?php echo CSRFProtection::getTokenField(); ?>
            <input type="hidden" name="acao" value="aprovar">
            <input type="hidden" name="ficha_id" value="<?php echo (int)$ficha['id']; ?>">
            <button class="btn btn-success"><i class="bi bi-check-lg me-1"></i> Aprovar Ficha</button>
        </form>
        <button type="button" class="btn btn-outline-danger" id="btnRecusarNaFicha"><i class="bi bi-x-lg me-1"></i> Recusar</button>
    </div>
    <?php endif; ?>

</div>

<?php if ($modalMode): ?>
        </div>
        <div class="modal-footer border-0 pt-0">
            <div class="d-flex justify-content-between w-100">
                <div>
                    <a href="<?php echo SITE_URL; ?>/cadastro-ficha/visualizar?id=<?php echo (int)$ficha['id']; ?>&pdf=1" class="btn btn-outline-success btn-sm" target="_blank"><i class="bi bi-file-earmark-pdf me-1"></i> PDF</a>
                    <button class="btn btn-outline-dark btn-sm" onclick="window.print()"><i class="bi bi-printer me-1"></i> Imprimir</button>
                </div>
                <div>
                    <?php if ($podeVerTodas && $ficha['status'] === FichaAdesao::STATUS_PENDENTE): ?>
                    <form method="POST" class="d-inline"
                          onsubmit="return confirm('Aprovar esta ficha de adesão? O número de cadastro será gerado e o associado será vinculado ao quadro.');">
                        <?php echo CSRFProtection::getTokenField(); ?>
                        <input type="hidden" name="acao" value="aprovar">
                        <input type="hidden" name="ficha_id" value="<?php echo (int)$ficha['id']; ?>">
                        <button class="btn btn-success btn-sm"><i class="bi bi-check-lg me-1"></i> Aprovar</button>
                    </form>
                    <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#modalRecusarFichaModal" data-ficha-id="<?php echo (int)$ficha['id']; ?>"><i class="bi bi-x-lg me-1"></i> Recusar</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php else: ?>
    </div>
</div>

<?php if ($podeVerTodas && $ficha['status'] === FichaAdesao::STATUS_PENDENTE): ?>
<!-- Modal de recusa -->
<div class="modal fade" id="modalRecusarFichaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <?php echo CSRFProtection::getTokenField(); ?>
                <input type="hidden" name="acao" value="recusar">
                <input type="hidden" name="ficha_id" id="modalRecusarFichaId" value="">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-x-octagon text-danger me-2"></i>Recusar Ficha de Adesão</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label small fw-semibold">Parecer da Diretoria (motivo da recusa) <span class="text-danger">*</span></label>
                    <textarea class="form-control" name="motivo" rows="4" maxlength="2000" required placeholder="Ex.: documentação incompleta, informações divergentes..."></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger btn-sm"><i class="bi bi-x-lg me-1"></i> Confirmar Recusa</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
document.addEventListener('click', function(e) {
    if (e.target.closest('[data-bs-target="#modalRecusarFichaModal"]')) {
        const btn = e.target.closest('[data-bs-target="#modalRecusarFichaModal"]');
        document.getElementById('modalRecusarFichaId').value = btn.dataset.fichaId;
    }
});
</script>
<?php endif; ?>

<?php if (!$modalMode): ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
<?php endif; ?>

<?php
/**
 * Gera PDF da ficha de adesão usando DomPDF
 */
function gerarPDFFicha($ficha, $produtos, $nomeAssociado, $avaliador, $labelStatus, $corStatus, $setProducao, $setCanais) {
    if (!class_exists(Dompdf::class)) {
        die('DomPDF não instalado. Execute: composer require dompdf/dompdf');
    }

    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $options->set('isHtml5ParserEnabled', true);
    $options->set('defaultFont', 'DejaVu Sans');

    $dompdf = new Dompdf($options);

    $html = gerarHTMLPDFFicha($ficha, $produtos, $nomeAssociado, $avaliador, $labelStatus, $corStatus, $setProducao, $setCanais);

    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $filename = 'Ficha_Adesao_' . ($ficha['numero_cadastro'] ?? 'ID_' . $ficha['id']) . '_' . date('Ymd') . '.pdf';
    $dompdf->stream($filename, ['Attachment' => true]);
    exit;
}

function gerarHTMLPDFFicha($ficha, $produtos, $nomeAssociado, $avaliador, $labelStatus, $corStatus, $setProducao, $setCanais) {
    $statusColors = [
        'aprovada' => '#198754',
        'pendente' => '#ffc107',
        'recusada' => '#dc3545',
        'rascunho' => '#6c757d'
    ];
    $statusColor = $statusColors[$ficha['status']] ?? '#6c757d';

    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <style>
            @page { margin: 20mm 15mm; }
            body { font-family: 'DejaVu Sans', sans-serif; font-size: 10pt; line-height: 1.4; color: #222; }
            .header { text-align: center; border-bottom: 3px solid #2f9e6f; padding-bottom: 10mm; margin-bottom: 10mm; }
            .header h1 { font-size: 16pt; font-weight: bold; color: #155d3a; margin: 5mm 0 2mm; }
            .header .org { font-size: 9pt; color: #555; text-transform: uppercase; letter-spacing: 0.05em; }
            .status-badge { display: inline-block; padding: 3px 10px; border-radius: 3px; color: white; font-weight: bold; font-size: 9pt; background: <?php echo $statusColor; ?>; }
            .secao { background: #eaf6ee; border-left: 4px solid #2f9e6f; padding: 4mm 6mm; margin: 6mm 0 3mm; font-weight: bold; color: #155d3a; text-transform: uppercase; letter-spacing: 0.04em; font-size: 8pt; }
            .grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 3mm; margin-bottom: 2mm; }
            .campo { break-inside: avoid; }
            .campo label { font-size: 6.5pt; text-transform: uppercase; letter-spacing: 0.04em; color: #666; display: block; margin-bottom: 1px; }
            .campo span { font-size: 9pt; font-weight: 500; }
            .tabela-produtos { width: 100%; border-collapse: collapse; margin-top: 3mm; font-size: 8pt; }
            .tabela-produtos th, .tabela-produtos td { border: 1px solid #ccc; padding: 3mm; text-align: left; }
            .tabela-produtos th { background: #f0f0f0; font-weight: bold; text-transform: uppercase; font-size: 7pt; }
            .assinaturas { display: grid; grid-template-columns: 1fr 1fr; gap: 10mm; margin-top: 15mm; padding-top: 5mm; border-top: 1px dashed #999; }
            .assinatura-bloco .label { font-size: 7pt; text-transform: uppercase; color: #666; margin-bottom: 2mm; }
            .assinatura-bloco .nome { font-weight: bold; font-size: 10pt; }
            .assinatura-bloco .info { font-size: 8pt; color: #555; margin-top: 1mm; }
            .badge { display: inline-block; padding: 1px 6px; border-radius: 3px; font-size: 7pt; font-weight: bold; margin-right: 2px; margin-bottom: 2px; }
            .badge-success { background: #d4edda; color: #155724; }
            .badge-warning { background: #fff3cd; color: #856404; }
            .badge-info { background: #d1ecf1; color: #0c5460; }
            .page-break { page-break-before: always; }
            .no-print { display: none; }
        </style>
    </head>
    <body>
        <!-- Cabeçalho -->
        <div class="header">
            <div class="org">AAES/RS &mdash; Associação de Agricultores Ecologistas Solidários do RS</div>
            <h1>FICHA DE CADASTRO DE ASSOCIADO</h1>
            <div class="org">Ficha de Adesão ao Quadro Associativo &bull; Rev. 2025</div>
        </div>

        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:5mm;">
            <span class="status-badge"><?php echo $labelStatus; ?></span>
            <div style="font-size:8pt; color:#666;">
                <?php if ($ficha['status'] === 'pendente' && $ficha['data_submissao']): ?>
                    Submetida em <?php echo date('d/m/Y H:i', strtotime($ficha['data_submissao'])); ?>
                <?php elseif ($ficha['data_analise']): ?>
                    Analisada em <?php echo date('d/m/Y H:i', strtotime($ficha['data_analise'])); ?> por <?php echo htmlspecialchars($avaliador); ?>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($ficha['status'] === 'recusada' && !empty($ficha['parecer_diretoria'])): ?>
        <div style="background:#f8d7da; border:1px solid #f5c6cb; color:#721c24; padding:5mm; border-radius:3px; margin-bottom:5mm; font-size:9pt;">
            <strong>Parecer da Diretoria:</strong> <?php echo nl2br(htmlspecialchars($ficha['parecer_diretoria'])); ?>
        </div>
        <?php endif; ?>

        <div class="grid">
            <div class="campo"><label>Nº de Cadastro</label><span><?php echo htmlspecialchars($ficha['numero_cadastro'] ?? '—'); ?></span></div>
            <div class="campo"><label>Data de Entrada</label><span><?php echo $ficha['data_entrada'] ? date('d/m/Y', strtotime($ficha['data_entrada'])) : '—'; ?></span></div>
            <div class="campo"><label>Tipo de Associado</label><span><?php echo htmlspecialchars(\FichaAdesao::labelTipoAssociado($ficha['tipo_associado'])); ?></span></div>
            <div class="campo"><label>Ficha ID</label><span>#<?php echo (int)$ficha['id']; ?></span></div>
        </div>

        <!-- 1. Identificação Pessoal -->
        <div class="secao">1. Identificação Pessoal</div>
        <div class="grid">
            <div class="campo" style="grid-column:span 3"><label>Nome Completo</label><span><?php echo htmlspecialchars($ficha['nome_completo'] ?? '—'); ?></span></div>
            <div class="campo"><label>Nome Social</label><span><?php echo htmlspecialchars($ficha['nome_social'] ?? '—'); ?></span></div>
            <div class="campo"><label>Data Nascimento</label><span><?php echo $ficha['data_nascimento'] ? date('d/m/Y', strtotime($ficha['data_nascimento'])) : '—'; ?></span></div>
            <div class="campo"><label>CPF</label><span><?php echo htmlspecialchars($ficha['cpf'] ?? '—'); ?></span></div>
            <div class="campo" style="grid-column:span 3"><label>RG / Órgão Emissor</label><span><?php echo htmlspecialchars($ficha['rg_orgao_emissor'] ?? '—'); ?></span></div>
            <div class="campo"><label>Sexo / Gênero</label><span><?php echo htmlspecialchars($ficha['sexo_genero'] ?? '—'); ?></span></div>
            <div class="campo"><label>Nacionalidade</label><span><?php echo htmlspecialchars($ficha['nacionalidade'] ?? '—'); ?></span></div>
            <div class="campo"><label>Estado Civil</label><span><?php echo htmlspecialchars($ficha['estado_civil'] ?? '—'); ?></span></div>
            <div class="campo"><label>Escolaridade</label><span><?php echo htmlspecialchars($ficha['escolaridade'] ?? '—'); ?></span></div>
        </div>

        <!-- 2. Endereço -->
        <div class="secao">2. Endereço</div>
        <div class="grid">
            <div class="campo" style="grid-column:span 3"><label>Logradouro / Linha / Comunidade</label><span><?php echo htmlspecialchars($ficha['logradouro'] ?? '—'); ?></span></div>
            <div class="campo"><label>Número / Lote</label><span><?php echo htmlspecialchars($ficha['numero'] ?? '—'); ?></span></div>
            <div class="campo"><label>Complemento / Localidade</label><span><?php echo htmlspecialchars($ficha['complemento'] ?? '—'); ?></span></div>
            <div class="campo"><label>Bairro / Distrito</label><span><?php echo htmlspecialchars($ficha['bairro'] ?? '—'); ?></span></div>
            <div class="campo"><label>Município</label><span><?php echo htmlspecialchars($ficha['municipio'] ?? '—'); ?></span></div>
            <div class="campo"><label>Estado</label><span><?php echo htmlspecialchars($ficha['estado'] ?? '—'); ?></span></div>
            <div class="campo"><label>CEP</label><span><?php echo htmlspecialchars($ficha['cep'] ?? '—'); ?></span></div>
            <div class="campo"><label>Zona</label><span><?php echo isset(\FichaAdesao::ZONAS[$ficha['zona']]) ? \FichaAdesao::ZONAS[$ficha['zona']] : '—'; ?></span></div>
            <div class="campo"><label>Lat/Long</label><span><?php echo htmlspecialchars($ficha['latitude_longitude'] ?? '—'); ?></span></div>
            <div class="campo"><label>Georref. SNCR</label><span><?php echo htmlspecialchars($ficha['georreferenciamento_sncr'] ?? '—'); ?></span></div>
        </div>

        <!-- 3. Contato -->
        <div class="secao">3. Contato</div>
        <div class="grid">
            <div class="campo"><label>Telefone / WhatsApp</label><span><?php echo htmlspecialchars($ficha['telefone_whatsapp'] ?? '—'); ?></span></div>
            <div class="campo"><label>Telefone Alternativo</label><span><?php echo htmlspecialchars($ficha['telefone_alternativo'] ?? '—'); ?></span></div>
            <div class="campo" style="grid-column:span 2"><label>E-mail</label><span><?php echo htmlspecialchars($ficha['email'] ?? '—'); ?></span></div>
        </div>

        <!-- 4. Dados da Propriedade Rural -->
        <div class="secao">4. Dados da Propriedade Rural</div>
        <div class="grid">
            <div class="campo" style="grid-column:span 3"><label>Nome / Denominação da Propriedade</label><span><?php echo htmlspecialchars($ficha['nome_propriedade'] ?? '—'); ?></span></div>
            <div class="campo" style="grid-column:span 3"><label>Nº do NIRF / ITR</label><span><?php echo htmlspecialchars($ficha['nirf_itr'] ?? '—'); ?></span></div>
            <div class="campo"><label>Área Total (ha)</label><span><?php echo htmlspecialchars($ficha['area_total_ha'] ?? '—'); ?></span></div>
            <div class="campo"><label>Área Cultivada (ha)</label><span><?php echo htmlspecialchars($ficha['area_cultivada_ha'] ?? '—'); ?></span></div>
            <div class="campo"><label>Área Preservação (ha)</label><span><?php echo htmlspecialchars($ficha['area_preservacao_ha'] ?? '—'); ?></span></div>
            <div class="campo"><label>Situação Fundiária</label><span><?php echo isset(\FichaAdesao::SITUACOES_FUNDIARIAS[$ficha['situacao_fundiaria']]) ? \FichaAdesao::SITUACOES_FUNDIARIAS[$ficha['situacao_fundiaria']] : '—'; ?></span></div>
            <div class="campo"><label>DAP / CAF</label><span><?php echo htmlspecialchars($ficha['dap_caf'] ?? '—'); ?></span></div>
            <div class="campo"><label>Pessoas na Família</label><span><?php echo htmlspecialchars($ficha['pessoas_familia'] ?? '—'); ?></span></div>
            <div class="campo"><label>Pessoas que Trabalham</label><span><?php echo htmlspecialchars($ficha['pessoas_trabalham'] ?? '—'); ?></span></div>
            <div class="campo"><label>Renda Mensal (R$)</label><span><?php echo $ficha['renda_mensal_estimada'] !== null && $ficha['renda_mensal_estimada'] !== '' ? number_format((float)$ficha['renda_mensal_estimada'], 2, ',', '.') : '—'; ?></span></div>
        </div>

        <!-- 5. Produção Agroecológica -->
        <div class="secao">5. Produção Agroecológica</div>
        <div class="grid">
            <div class="campo"><label>Tempo Produção Agroecológica</label><span><?php echo htmlspecialchars($ficha['tempo_producao_agroecologica'] ?? '—'); ?></span></div>
            <div class="campo" style="grid-column:span 3"><label>Certificação Orgânica</label><span><?php echo htmlspecialchars($ficha['certificacao_organica'] ?? '—'); ?></span></div>
            <div class="campo"><label>Tipo de Certificação</label><span><?php echo isset(\FichaAdesao::TIPOS_CERTIFICACAO[$ficha['tipo_certificacao']]) ? \FichaAdesao::TIPOS_CERTIFICACAO[$ficha['tipo_certificacao']] : '—'; ?></span></div>
        </div>
        <div style="margin-top:3mm;">
            <div class="campo" style="display:block; margin-bottom:2mm;"><label>Sistemas de Produção</label></div>
            <div>
                <?php if (empty($setProducao)): ?>
                    <span style="color:#999;">—</span>
                <?php else: ?>
                    <?php foreach ($setProducao as $sp): ?>
                        <span class="badge badge-success"><?php echo htmlspecialchars($sp); ?></span>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div style="margin-top:3mm;">
            <div class="campo" style="display:block; margin-bottom:2mm;"><label>Principais Produtos / Culturas</label></div>
            <?php if (empty($produtos)): ?>
                <span style="color:#999;">—</span>
            <?php else: ?>
                <table class="tabela-produtos">
                    <thead><tr><th>#</th><th>Produto / Cultura</th><th>Variedade / Espécie</th><th>Produção Estimada</th><th>Unidade</th><th>Destino Principal</th></tr></thead>
                    <tbody>
                        <?php foreach ($produtos as $i => $pr): ?>
                        <tr>
                            <td><?php echo $i + 1; ?></td>
                            <td><?php echo htmlspecialchars($pr['produto_cultura'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($pr['variedade_especie'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($pr['producao_estimada'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($pr['unidade'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($pr['destino_principal'] ?? '—'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="grid" style="margin-top:3mm;">
            <div class="campo" style="grid-column:span 2">
                <label>Canais de Comercialização</label>
                <?php if (empty($setCanais)): ?>
                    <span style="color:#999;">—</span>
                <?php else: ?>
                    <?php foreach ($setCanais as $cc): ?>
                        <span class="badge badge-info"><?php echo htmlspecialchars($cc); ?></span>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="campo" style="grid-column:span 2"><label>Outra Organização / Cooperativa</label><span><?php echo htmlspecialchars($ficha['outra_organizacao'] ?? '—'); ?></span></div>
        </div>

        <!-- 6. Dados Bancários -->
        <div class="secao">6. Dados Bancários</div>
        <div class="grid">
            <div class="campo"><label>Banco</label><span><?php echo htmlspecialchars($ficha['banco'] ?? '—'); ?></span></div>
            <div class="campo"><label>Agência</label><span><?php echo htmlspecialchars($ficha['agencia'] ?? '—'); ?></span></div>
            <div class="campo"><label>Conta</label><span><?php echo htmlspecialchars($ficha['conta'] ?? '—'); ?></span></div>
            <div class="campo"><label>Chave Pix</label><span><?php echo htmlspecialchars($ficha['chave_pix'] ?? '—'); ?></span></div>
            <div class="campo" style="grid-column:span 2"><label>Titular da Conta</label><span><?php echo htmlspecialchars($ficha['titular_conta'] ?? '—'); ?></span></div>
        </div>

        <!-- 7. Observações -->
        <div class="secao">7. Observações</div>
        <div style="font-size:9pt; white-space:pre-wrap;"><?php echo nl2br(htmlspecialchars($ficha['observacoes'] ?? '—')); ?></div>

        <!-- Assinaturas -->
        <div class="assinaturas">
            <div class="assinatura-bloco">
                <div class="label">Assinatura do(a) Associado(a)</div>
                <div class="nome"><?php echo htmlspecialchars($nomeAssociado); ?></div>
                <div class="info">
                    Nome legível: <?php echo htmlspecialchars($nomeAssociado); ?> &bull;
                    Data: <?php echo $ficha['data_submissao'] ? date('d/m/Y', strtotime($ficha['data_submissao'])) : date('d/m/Y', strtotime($ficha['created_at'])); ?>
                </div>
                <div class="info" style="margin-top:2mm;">
                    <?php echo !empty($ficha['aceite_termos']) ? '☑ Declaração de veracidade e LGPD aceitas.' : '☐ Aceite não registrado.'; ?>
                </div>
            </div>
            <div class="assinatura-bloco">
                <div class="label">Responsável pelo Cadastro (AAES/RS)</div>
                <div class="nome"><?php echo htmlspecialchars($avaliador); ?></div>
                <div class="info">
                    <?php if ($ficha['status'] === 'aprovada'): ?>
                        Aprovada em <?php echo date('d/m/Y', strtotime($ficha['data_analise'])); ?>
                        <?php if ($ficha['numero_cadastro']): ?> &bull; Nº <?php echo htmlspecialchars($ficha['numero_cadastro']); ?><?php endif; ?>
                    <?php else: ?>
                        Aguardando avaliação da Diretoria/Presidência.
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div style="margin-top:10mm; padding:3mm; background:#f6f4e8; border:1px solid #e4dcb8; border-radius:3px; font-size:7.5pt; color:#5b5434;">
            <strong>Aviso de privacidade:</strong> os dados coletados nesta ficha são utilizados exclusivamente para fins de gestão associativa, acesso a políticas públicas e comercialização de produtos, em conformidade com a Lei Geral de Proteção de Dados (Lei nº 13.709/2018).
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}
?>
<?php endif; ?>
<?php if (!$modalMode): ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
<?php endif; ?>