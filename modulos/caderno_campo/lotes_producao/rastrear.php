<?php
$page_title = "Rastrear Lote de Produção";
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/menu.php';
require_once __DIR__ . '/../../../classes/LoteProducao.php';
require_once __DIR__ . '/../../../classes/Produto.php';
require_once __DIR__ . '/../../../classes/Lavoura.php';
require_once __DIR__ . '/../../../classes/Propriedade.php';

// Verificar se o usuário está logado
if (!$user->isLoggedIn()) {
    echo "<div class='alert alert-danger'>Você não tem permissão para acessar esta página.</div>";
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}

$lote_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$lote_producao = new LoteProducao();
$lote = $lote_producao->getById($lote_id);

if (!$lote) {
    echo "<div class='alert alert-danger'>Lote de produção não encontrado.</div>";
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}

$produto_obj = new Produto();
$produto = $produto_obj->getById($lote['produto_id']);

$lavoura = null;
$propriedade = null;
if ($produto && $produto['lavoura_id']) {
    $lavoura_obj = new Lavoura();
    $lavoura = $lavoura_obj->getById($produto['lavoura_id']);
    if ($lavoura && $lavoura['propriedade_id']) {
        $propriedade_obj = new Propriedade();
        $propriedade = $propriedade_obj->getById($lavoura['propriedade_id']);
    }
}

$atividades_do_lote = $lote_producao->getAtividadesByLoteId($lote_id);
$insumos_do_lote = $lote_producao->getInsumosByLoteId($lote_id);

?>

<h1 class="mt-4">Rastreabilidade do Lote: <?php echo htmlspecialchars($lote['nome_lote']); ?></h1>
<p>Detalhes completos sobre a origem e o histórico deste lote de produção.</p>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">Informações do Lote</div>
            <div class="card-body">
                <p style="display:none;"><strong>ID do Lote:</strong> <?php echo htmlspecialchars($lote['id']); ?></p>
                <p><strong>Nome do Lote:</strong> <?php echo htmlspecialchars($lote['nome_lote']); ?></p>
                <p><strong>Produto:</strong> <?php echo $produto ? htmlspecialchars($produto['nome']) : 'N/A'; ?></p>
                <p><strong>Data de Produção:</strong> <?php echo date('d/m/Y', strtotime($lote['data_producao'])); ?></p>
                <p><strong>Quantidade:</strong> <?php echo htmlspecialchars($lote['quantidade']) . ' ' . htmlspecialchars($lote['unidade_medida']); ?></p>
                <p><strong>Observações:</strong> <?php echo htmlspecialchars($lote['observacoes']); ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">Origem (Produto, Lavoura, Propriedade)</div>
            <div class="card-body">
                <p><strong>Produto:</strong> <?php echo $produto ? htmlspecialchars($produto['nome']) : 'N/A'; ?></p>
                <p><strong>Tipo de Produto:</strong> <?php echo $produto ? htmlspecialchars($produto['tipo']) : 'N/A'; ?></p>
                <p><strong>Lavoura de Origem:</strong> <?php echo $lavoura ? htmlspecialchars($lavoura['nome']) : 'N/A'; ?></p>
                <p><strong>Cultura da Lavoura:</strong> <?php echo $lavoura ? htmlspecialchars($lavoura['tipo_cultura'] ?? 'N/A') : 'N/A'; ?></p>
                <p><strong>Propriedade:</strong> <?php echo $propriedade ? htmlspecialchars($propriedade['nome']) : 'N/A'; ?></p>
                <p><strong>Endereço da Propriedade:</strong> <?php echo $propriedade ? htmlspecialchars($propriedade['endereco'] . ', ' . $propriedade['cidade'] . ' - ' . $propriedade['estado']) : 'N/A'; ?></p>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">Histórico de Atividades</div>
    <div class="card-body">
        <?php if (count($atividades_do_lote) > 0): ?>
        <table class="table table-striped table-bordered">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Hora</th>
                    <th>Atividade</th>
                    <th>Tipo</th>
                    <th>Status</th>
                    <th>Descrição</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($atividades_do_lote as $ativ): ?>
                <tr>
                    <td><?php echo date('d/m/Y', strtotime($ativ['data_atividade'])); ?></td>
                    <td><?php echo $ativ['hora_inicio'] . ' - ' . ($ativ['hora_fim'] ? $ativ['hora_fim'] : 'Em andamento'); ?></td>
                    <td><?php echo htmlspecialchars($ativ['nome']); ?></td>
                    <td><?php echo htmlspecialchars($ativ['tipo_atividade']); ?></td>
                    <td>
                        <?php 
                        $statusClass = '';
                        switch($ativ['status']) {
                            case 'Pendente': $statusClass = 'badge bg-warning'; break;
                            case 'Em andamento': $statusClass = 'badge bg-info'; break;
                            case 'Concluído': $statusClass = 'badge bg-success'; break;
                            default: $statusClass = 'badge bg-secondary';
                        }
                        ?>
                        <span class="<?php echo $statusClass; ?>"><?php echo htmlspecialchars($ativ['status']); ?></span>
                    </td>
                    <td><?php echo htmlspecialchars($ativ['descricao'] ?? '-'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p>Nenhuma atividade registrada para este lote.</p>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">Insumos Utilizados</div>
    <div class="card-body">
        <?php if (count($insumos_do_lote) > 0): ?>
        <table class="table table-striped table-bordered">
            <thead>
                <tr>
                    <th>Insumo</th>
                    <th>Data de Aplicação</th>
                    <th>Tipo de Atividade</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($insumos_do_lote as $ins): ?>
                <tr>
                    <td><?php echo htmlspecialchars($ins['insumo_utilisado']); ?></td>
                    <td><?php echo date('d/m/Y', strtotime($ins['data_atividade'])); ?></td>
                    <td><?php echo htmlspecialchars($ins['tipo_atividade']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p>Nenhum insumo registrado para este lote.</p>
        <?php endif; ?>
    </div>
</div>

<div class="mt-4">
    <a href="<?php echo SITE_URL; ?>/lotes" class="btn btn-secondary">Voltar para Lotes de Produção</a>
</div>

<?php
require_once __DIR__ . '/../../../includes/footer.php';
?>
