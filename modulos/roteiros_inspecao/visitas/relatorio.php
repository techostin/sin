<?php
$page_title = "Detalhes da Inspeção";
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/menu.php';

// Aceitar tanto id numérico quanto hashid
$id = $_GET['id'] ?? null;
$hashid = $_GET['hashid'] ?? null;

// Se não tem id numérico mas tem hashid, buscar o id
if (!$id && $hashid) {
    require_once __DIR__ . '/../../../classes/Database.php';
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT id FROM visitas_tecnicas WHERE hashid = ?");
    $stmt->execute([$hashid]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $id = $row['id'];
    }
}

if (!$id) {
    echo "<div class='alert alert-danger mt-4'>ID da visita ausente.</div>";
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}

$id = (int) $id;

// Anti-IDOR: a visita técnica pertence a um associado (visitas_tecnicas.associado_id).
// Admin/Gestor abrem qualquer relatório; nível 3 só o do próprio associado.
require_once __DIR__ . '/../../../classes/Database.php';
$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("SELECT associado_id FROM visitas_tecnicas WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$donoVisita = (int) $stmt->fetchColumn();
if (!$user->isGestorOuAdmin()
    && ($donoVisita <= 0 || $donoVisita !== (int) $user->getAssociadoIdResolvido())) {
    echo "<div class='alert alert-danger mt-4'>Acesso negado: esta visita pertence a outro produtor.</div>";
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}
?>

<div class="gestor-modern">
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/gestor-modern.css">
<div class="page-bg px-2 px-md-0" style="min-height:100%; padding-bottom:1px;">

<!-- Vue App -->
<div id="vue-relatorio">
    <div class="gestor-hero">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 flex-wrap">
            <div>
                <h1 class="mb-1"><i class="bi bi-file-earmark-text me-2" style="color:var(--gm-primary);"></i>Resumo da Visita Técnica</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>/visitas-tecnicas">Visitas</a></li>
                        <li class="breadcrumb-item active">Detalhes #{{ visita ? visita.id : '...' }}</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
    <div class="text-end mb-3" v-if="visita">
        <div class="btn-group">
            <a :href="'<?php echo SITE_URL; ?>/api/roteiros_inspecao.php?action=relatorio&id=' + visita.id"
               target="_blank" class="btn btn-assoc-danger">
                <i class="bi bi-file-earmark-pdf"></i> Download PDF
            </a>
            <a href="<?php echo SITE_URL; ?>/visitas-tecnicas" class="btn btn-assoc-ghost">Voltar</a>
        </div>
    </div>

    <!-- Estado: Carregando -->
    <div v-if="loading" class="text-center py-5">
        <div class="spinner-border text-primary" role="status"></div>
        <p class="mt-2 text-muted">Carregando dados da inspeção...</p>
    </div>

    <!-- Estado: Erro -->
    <div v-else-if="erro" class="alert alert-danger mt-2">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <strong>Erro ao carregar:</strong> {{ erro }}
        <div class="mt-2">
            <a href="<?php echo SITE_URL; ?>/visitas-tecnicas" class="btn btn-sm btn-outline-danger">Voltar</a>
        </div>
    </div>

    <!-- Estado: Conteúdo -->
    <div v-else-if="visita" class="row">
        <!-- Info Lateral -->
        <div class="col-lg-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white fw-bold">Informações da Propriedade</div>
                <div class="card-body">
                    <p class="mb-1 text-muted small">AGRICULTOR</p>
                    <p class="fw-bold">{{ visita.associado_nome }} {{ visita.associado_sobrenome }}</p>

                    <p class="mb-1 text-muted small">PROPRIEDADE</p>
                    <p class="fw-bold">{{ visita.propriedade_nome }}</p>

                    <p class="mb-1 text-muted small">DATA DA VISITA</p>
                    <p class="fw-bold">{{ formatDate(visita.data_visita) }}</p>

                    <hr>

                    <p class="mb-1 text-muted small">CONFORMIDADE GERAL</p>
                    <span :class="'badge fs-6 bg-' + (visita.conformidade === 'Conforme' ? 'success' : 'warning')">
                        {{ visita.conformidade }}
                    </span>

                    <p class="mb-1 mt-3 text-muted small">PONTUAÇÃO</p>
                    <div class="progress" style="height: 25px;">
                        <div class="progress-bar bg-success fw-bold" role="progressbar"
                             :style="{width: visita.pontuacao + '%'}"
                             :aria-valuenow="visita.pontuacao" aria-valuemin="0" aria-valuemax="100">
                            {{ visita.pontuacao }}%
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white fw-bold">Parecer Técnico</div>
                <div class="card-body">
                    <p>{{ visita.observacoes || 'Nenhuma observação registrada.' }}</p>
                </div>
            </div>
        </div>

        <!-- Checklist -->
        <div class="col-lg-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white fw-bold">Itens do Checklist ({{ visita.roteiro_nome }})</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Item / Requisito</th>
                                    <th width="120">Status</th>
                                    <th>Observação</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(res, itemId) in visita.resultado" :key="itemId">
                                    <td>{{ getItemName(itemId) }}</td>
                                    <td>
                                        <span v-if="res.conforme === true || res.conforme === 'true'"
                                              class="text-success fw-bold">
                                            <i class="bi bi-check-circle"></i> Conforme
                                        </span>
                                        <span v-else class="text-danger fw-bold">
                                            <i class="bi bi-x-circle"></i> Não Conforme
                                        </span>
                                    </td>
                                    <td class="small text-muted">{{ res.observacao || '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white fw-bold">Evidências e Assinaturas</div>
                <div class="card-body">
                    <div v-if="visita.assinatura_produtor_url || visita.assinatura_tecnico_url" class="row text-center mb-3">
                        <div class="col-6">
                            <img v-if="visita.assinatura_produtor_url"
                                 :src="visita.assinatura_produtor_url"
                                 class="img-fluid border rounded" style="max-height: 100px;">
                            <div class="mt-2 text-muted small border-top pt-1">Assinatura do Produtor</div>
                        </div>
                        <div class="col-6">
                            <img v-if="visita.assinatura_tecnico_url"
                                 :src="visita.assinatura_tecnico_url"
                                 class="img-fluid border rounded" style="max-height: 100px;">
                            <div class="mt-2 text-muted small border-top pt-1">Assinatura do Técnico</div>
                        </div>
                    </div>
                    <p v-else class="text-muted">Nenhuma assinatura registrada.</p>
                </div>
            </div>
        </div>
    </div>
</div>

</div> <!-- /page-bg -->
</div> <!-- /gestor-modern -->

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>

<!-- Vue após o footer para garantir que o DOM está pronto -->
<script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
<script>
(function() {
    const { createApp } = Vue;
    const VISITA_ID = <?php echo (int)$id; ?>;
    const API_BASE  = '<?php echo SITE_URL; ?>/api/roteiros_inspecao.php';
    // Checklist padrão injetado para resolução de nomes (produção/processamento orgânico)
    const Window_ChecklistPadrao = <?php
        require_once __DIR__ . '/../../../classes/ChecklistPadrao.php';
        $padraoJson = [];
        foreach (ChecklistPadrao::itens() as $p) {
            $padraoJson[$p['id']] = ['item' => $p['item'], 'categoria' => $p['categoria']];
        }
        echo json_encode($padraoJson);
    ?>;

    createApp({
        data() {
            return { visita: null, template: [], loading: true, erro: null };
        },
        mounted() { this.fetchData(); },
        methods: {
            async fetchData() {
                try {
                    const r1 = await fetch(`${API_BASE}?action=visitas&id=${VISITA_ID}`);
                    if (!r1.ok) throw new Error(`HTTP ${r1.status} ao buscar visita`);
                    const d1 = await r1.json();
                    if (!d1.success || !d1.data) throw new Error('Visita não encontrada.');
                    this.visita = d1.data;

                    const r2 = await fetch(`${API_BASE}?action=templates`);
                    if (!r2.ok) throw new Error(`HTTP ${r2.status} ao buscar templates`);
                    const d2 = await r2.json();
                    const t = (d2.data || []).find(r => r.id == this.visita.roteiro_id);
                    if (t && t.checklist) this.template = t.checklist;

                } catch (err) {
                    this.erro = err.message;
                    console.error('[Relatorio]', err);
                } finally {
                    this.loading = false;
                }
            },
            formatDate(d) {
                return d ? new Date(d).toLocaleDateString('pt-BR') : '—';
            },
            getItemName(id) {
                const item = this.template.find(i => String(i.id) === String(id));
                if (item) return (item.item || item.pergunta || `Item #${id}`);
                // Fallback: itens do checklist padrão (produção/processamento orgânico)
                const padrao = Window_ChecklistPadrao || {};
                if (padrao[id]) return padrao[id].item;
                return `Item #${id}`;
            }
        }
    }).mount('#vue-relatorio');
})();
</script>
