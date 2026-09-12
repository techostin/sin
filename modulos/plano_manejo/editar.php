<?php
$page_title = "Editar Plano de Manejo";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
require_once __DIR__ . '/../../classes/Database.php';

if (!$user->isLoggedIn()) {
    echo "<div class='alert alert-danger'>Você não tem permissão para acessar esta página.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$pode_editar_status = $user->hasPermission(NIVEL_GESTOR);

$id = $_GET['id'] ?? null;
if (!$id || !is_numeric($id)) {
    echo "<div class='alert alert-danger'>ID de plano inválido.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$db = Database::getInstance()->getConnection();
$stmtProp = $db->query("SELECT id, propriedade FROM propriedades ORDER BY propriedade");
$propriedades = $stmtProp->fetchAll(PDO::FETCH_ASSOC);
$stmtAssoc = $db->query("SELECT id, nome, sobrenome FROM associados ORDER BY nome");
$associados = $stmtAssoc->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="gestor-modern">
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/gestor-modern.css">
<div class="page-bg px-2 px-md-0" style="min-height:100%; padding-bottom:1px;">

    <div class="gestor-hero">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 flex-wrap">
            <h1 class="mb-1"><i class="bi bi-pencil-square me-2" style="color:var(--gm-primary);"></i>Editar Plano de Manejo</h1>
            <a href="<?php echo SITE_URL; ?>/plano-manejo/<?php echo (int) $id; ?>/visualizar" class="btn btn-assoc-ghost btn-sm">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>
    </div>

    <div id="msg-box"></div>

    <form id="formEditarPlano" novalidate>
        <div class="gm-card mb-4">
            <div class="card-head">Informações Básicas</div>
            <div class="p-4 row g-3">
                <div class="col-md-4">
                    <label class="form-label">Propriedade</label>
                    <select class="form-select" name="propriedade_id" id="propriedade_id" required>
                        <option value="">Selecione</option>
                        <?php foreach ($propriedades as $p): ?>
                            <option value="<?php echo (int) $p['id']; ?>"><?php echo htmlspecialchars($p['propriedade']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Agricultor Responsável</label>
                    <select class="form-select" name="associado_id" id="associado_id" required>
                        <option value="">Selecione</option>
                        <?php foreach ($associados as $a): ?>
                            <option value="<?php echo (int) $a['id']; ?>"><?php echo htmlspecialchars($a['nome'] . ' ' . $a['sobrenome']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Ano Safra</label>
                    <input class="form-control" type="text" name="ano_safra" id="ano_safra" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <?php if ($pode_editar_status): ?>
                        <select class="form-select" name="status" id="status">
                            <option value="Rascunho">Rascunho</option>
                            <option value="Em Análise">Em Análise</option>
                            <option value="Aprovado">Aprovado</option>
                            <option value="Reprovado">Reprovado</option>
                            <option value="Revisão Necessária">Revisão Necessária</option>
                        </select>
                    <?php else: ?>
                        <!-- Campo hidden garante que o status atual seja enviado na requisição -->
                        <input type="hidden" id="status" name="status" value="">
                        <div id="status-display" class="form-control-plaintext">
                            <span id="status-badge" class="badge bg-secondary">Carregando...</span>
                        </div>
                        <small class="text-muted">Somente Admin/Gestor podem alterar o status.</small>
                    <?php endif; ?>
                </div>
                <div class="col-md-12">
                    <label class="form-label">Título</label>
                    <input class="form-control" type="text" name="titulo" id="titulo" required>
                </div>
            </div>
        </div>

        <div class="gm-card mb-4">
            <div class="card-head">Caracterização e Práticas de Manejo</div>
            <div class="p-4 row g-3">
                <div class="col-md-3">
                    <label class="form-label">Área Orgânica (ha)</label>
                    <input class="form-control" type="number" step="0.01" name="area_organica_ha" id="area_organica_ha">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Área em Conversão (ha)</label>
                    <input class="form-control" type="number" step="0.01" name="area_conversao_ha" id="area_conversao_ha">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Topografia</label>
                    <select class="form-select" name="topografia" id="topografia">
                        <option value="Plana">Plana</option>
                        <option value="Ondulada">Ondulada</option>
                        <option value="Montanhosa">Montanhosa</option>
                        <option value="Irregular">Irregular</option>
                    </select>
                </div>
                <div class="col-md-12">
                    <label class="form-label">Descrição do Solo</label>
                    <textarea class="form-control" rows="2" name="descricao_solo" id="descricao_solo"></textarea>
                </div>
            </div>
            <div class="border-top pt-3 mt-2 px-4 pb-4">
                <h6 class="text-success mb-3"><i class="bi bi-tools"></i> Práticas de Manejo e Conservação</h6>
                <p class="text-muted small mb-3">Registre detalhadamente as práticas adotadas para refletir a intenção do agricultor:</p>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="bi bi-bug text-danger"></i> Manejo de Pragas</label>
                        <textarea class="form-control" rows="3" name="manejo_pragas" id="manejo_pragas" placeholder="Ex: Caldas naturais, Controle biológico, Armadilhas..."></textarea>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="bi bi-virus text-danger"></i> Manejo de Doenças</label>
                        <textarea class="form-control" rows="3" name="manejo_doencas" id="manejo_doencas" placeholder="Ex: Calda bordalesa, Eliminação de plantas doentes..."></textarea>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="bi bi-tree text-success"></i> Práticas de Conservação</label>
                        <textarea class="form-control" rows="3" name="praticas_conservacao" id="praticas_conservacao" placeholder="Ex: Curvas de nível, Plantio direto, Cobertura morta..."></textarea>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="bi bi-arrow-repeat text-primary"></i> Rotação de Culturas</label>
                        <textarea class="form-control" rows="3" name="rotacao_culturas" id="rotacao_culturas" placeholder="Ex: Rotação milho/feijão, Consórcio, Pousio..."></textarea>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="bi bi-flower2 text-warning"></i> Compostagem / Adubação Verde</label>
                        <textarea class="form-control" rows="3" name="compostagem" id="compostagem" placeholder="Ex: Composteira, Bokashi, Esterco curtido..."></textarea>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="bi bi-droplet text-info"></i> Biofertilizantes</label>
                        <textarea class="form-control" rows="3" name="biofertilizantes" id="biofertilizantes" placeholder="Ex: Biofertilizante líquido, EM, Chorume..."></textarea>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="bi bi-droplet-half text-primary"></i> Fontes de Água</label>
                        <textarea class="form-control" rows="3" name="fonte_agua" id="fonte_agua" placeholder="Ex: Nascente, Poço, Rio, Captação de chuva..."></textarea>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="bi bi-sun text-warning"></i> Clima / Microclima</label>
                        <textarea class="form-control" rows="3" name="clima" id="clima" placeholder="Ex: Temperatura média, Período chuvoso, Geada..."></textarea>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="form-label"><i class="bi bi-heart-pulse text-danger"></i> Manejo de Solo e Adubação</label>
                        <textarea class="form-control" rows="3" name="adubacao_verde" id="adubacao_verde" placeholder="Ex: Adubação verde, Cobertura morta, Minhocário..."></textarea>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="form-label">Observações</label>
                        <textarea class="form-control" rows="2" name="observacoes" id="observacoes"></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="gm-card mb-4">
            <div class="card-head d-flex justify-content-between align-items-center">
                <span>Cultivos</span>
                <button type="button" class="btn btn-outline-primary btn-sm" id="btnAddCultivo">
                    <i class="bi bi-plus"></i> Adicionar cultivo
                </button>
            </div>
            <div class="p-4">
                <div id="lista-cultivos"></div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mb-4">
            <a href="<?php echo SITE_URL; ?>/planos-manejo" class="btn btn-assoc-ghost">Cancelar</a>
            <button type="submit" id="btnSalvar" class="btn btn-assoc-success">
                <i class="bi bi-save"></i> Salvar alterações
            </button>
        </div>
    </form>

</div> <!-- /page-bg -->
</div> <!-- /gestor-modern -->

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<script>
$(function() {
    const planoId = <?php echo (int) $id; ?>;

    function addCultivo(cultivo = {}) {
        const html = `
            <div class="row g-2 border rounded p-2 mb-2 cultivo-row">
                <div class="col-md-4">
                    <input class="form-control form-control-sm cultivo-cultura" placeholder="Cultura" value="${(cultivo.cultura || '').replace(/"/g, '&quot;')}">
                </div>
                <div class="col-md-3">
                    <input class="form-control form-control-sm cultivo-area" type="number" step="0.01" placeholder="Área (ha)" value="${cultivo.area_cultivada_ha || ''}">
                </div>
                <div class="col-md-4">
                    <select class="form-select form-select-sm cultivo-semente">
                        <option value="Orgânica Certificada">Orgânica Certificada</option>
                        <option value="Convencional Não Tratada">Convencional Não Tratada</option>
                        <option value="Crioula">Crioula</option>
                        <option value="Própria">Própria</option>
                    </select>
                </div>
                <div class="col-md-1 d-grid">
                    <button type="button" class="btn btn-outline-danger btn-sm btn-remove-cultivo">&times;</button>
                </div>
            </div>`;
        $('#lista-cultivos').append(html);
        const $last = $('#lista-cultivos .cultivo-row').last();
        $last.find('.cultivo-semente').val(cultivo.tipo_semente || 'Orgânica Certificada');
    }

    function showMsg(type, text) {
        $('#msg-box').html(`<div class="alert alert-${type}">${text}</div>`);
    }

    $('#btnAddCultivo').on('click', function() { addCultivo(); });
    $(document).on('click', '.btn-remove-cultivo', function() {
        $(this).closest('.cultivo-row').remove();
    });

    fetch(`<?php echo SITE_URL; ?>/api/plano_manejo.php?id=${planoId}`)
        .then(res => res.json())
        .then(resp => {
            if (!resp.success || !resp.data) {
                showMsg('danger', 'Não foi possível carregar o plano para edição.');
                return;
            }

            const p = resp.data;
            $('#propriedade_id').val(p.propriedade_id);
            $('#associado_id').val(p.associado_id);
            $('#ano_safra').val(p.ano_safra || '');
            $('#titulo').val(p.titulo || '');

            // Normaliza e aplica o status
            const statusVal = p.status || 'Rascunho';
            $('#status').val(statusVal);

            // Se usuário não pode editar status, exibe badge visual
            <?php if (!$pode_editar_status): ?>
            const badgeColors = {
                'Aprovado': 'success', 'Rascunho': 'secondary',
                'Em Análise': 'info', 'Reprovado': 'danger',
                'Revisão Necessária': 'warning'
            };
            $('#status').val(statusVal); // garante hidden recebe o valor correto
            $('#status-badge')
                .removeClass()
                .addClass(`badge bg-${badgeColors[statusVal] || 'secondary'}`)
                .text(statusVal);
            <?php endif; ?>

            $('#area_organica_ha').val(p.area_organica_ha || 0);
            $('#area_conversao_ha').val(p.area_conversao_ha || 0);
            $('#topografia').val(p.topografia || 'Plana');
            $('#descricao_solo').val(p.descricao_solo || '');
            
            // Práticas de manejo
            $('#manejo_pragas').val(p.manejo_pragas || '');
            $('#manejo_doencas').val(p.manejo_doencas || '');
            $('#praticas_conservacao').val(p.praticas_conservacao || '');
            $('#rotacao_culturas').val(p.rotacao_culturas || '');
            $('#compostagem').val(p.compostagem || '');
            $('#biofertilizantes').val(p.biofertilizantes || '');
            $('#fonte_agua').val(p.fonte_agua || '');
            $('#clima').val(p.clima || '');
            $('#adubacao_verde').val(p.adubacao_verde || '');
            $('#observacoes').val(p.observacoes || '');

            $('#lista-cultivos').empty();
            if (Array.isArray(p.cultivos) && p.cultivos.length > 0) {
                p.cultivos.forEach(addCultivo);
            } else {
                addCultivo();
            }
        })
        .catch(() => showMsg('danger', 'Erro ao carregar dados do plano.'));

    $('#formEditarPlano').on('submit', function(e) {
        e.preventDefault();

        const payload = {
            id: planoId,
            propriedade_id: parseInt($('#propriedade_id').val(), 10) || null,
            associado_id: parseInt($('#associado_id').val(), 10) || null,
            ano_safra: $('#ano_safra').val().trim(),
            titulo: $('#titulo').val().trim(),
            status: $('#status').val(),
            area_organica_ha: parseFloat($('#area_organica_ha').val()) || 0,
            area_conversao_ha: parseFloat($('#area_conversao_ha').val()) || 0,
            topografia: $('#topografia').val(),
            descricao_solo: $('#descricao_solo').val().trim(),
            manejo_pragas: $('#manejo_pragas').val().trim(),
            adubacao_verde: $('#adubacao_verde').val().trim(),
            observacoes: $('#observacoes').val().trim(),
            cultivos: []
        };

        $('.cultivo-row').each(function() {
            const cultura = $(this).find('.cultivo-cultura').val().trim();
            if (!cultura) return;
            payload.cultivos.push({
                cultura,
                area_cultivada_ha: parseFloat($(this).find('.cultivo-area').val()) || 0,
                tipo_semente: $(this).find('.cultivo-semente').val()
            });
        });

        if (!payload.propriedade_id || !payload.associado_id || !payload.ano_safra || !payload.titulo) {
            showMsg('warning', 'Preencha propriedade, agricultor, safra e título.');
            return;
        }

        $('#btnSalvar').prop('disabled', true).text('Salvando...');
        fetch('<?php echo SITE_URL; ?>/api/plano_manejo.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(resp => {
            if (resp.success) {
                showMsg('success', 'Plano atualizado com sucesso.');
                setTimeout(() => {
                    window.location.href = `<?php echo SITE_URL; ?>/plano-manejo/${planoId}/visualizar`;
                }, 700);
            } else {
                showMsg('danger', resp.error || 'Erro ao atualizar plano.');
            }
        })
        .catch(() => showMsg('danger', 'Erro de comunicação com a API.'))
        .finally(() => {
            $('#btnSalvar').prop('disabled', false).html('<i class="bi bi-save"></i> Salvar alterações');
        });
    });
});
</script>
