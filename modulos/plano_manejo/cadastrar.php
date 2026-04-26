<?php
$page_title = "Novo Plano de Manejo Orgânico";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';
require_once __DIR__ . '/../../classes/Database.php';

// Verificar autenticação
if (!$user->isLoggedIn()) {
    echo "<div class='alert alert-danger'>Você não tem permissão para acessar esta página.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$db = Database::getInstance()->getConnection();

// Buscar propriedades para o select
$stmtProp = $db->query("SELECT id, propriedade FROM propriedades ORDER BY propriedade");
$propriedades = $stmtProp->fetchAll(PDO::FETCH_ASSOC);

// Buscar associados para o select
$stmtAssoc = $db->query("SELECT id, nome, sobrenome FROM associados ORDER BY nome");
$associados = $stmtAssoc->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Plano de Manejo Orgânico</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>/">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>/planos-manejo">Planos de Manejo</a></li>
        <li class="breadcrumb-item active">Novo Plano</li>
    </ol>

    <!-- Wizard Header -->
    <div class="card mb-4">
        <div class="card-body">
            <div id="wizard-steps" class="d-flex justify-content-between mb-4">
                <div class="step-item active" data-step="1">
                    <div class="step-circle">1</div>
                    <div class="step-label">Identificação</div>
                </div>
                <div class="step-item" data-step="2">
                    <div class="step-circle">2</div>
                    <div class="step-label">Propriedade</div>
                </div>
                <div class="step-item" data-step="3">
                    <div class="step-circle">3</div>
                    <div class="step-label">Práticas</div>
                </div>
                <div class="step-item" data-step="4">
                    <div class="step-circle">4</div>
                    <div class="step-label">Cultivos</div>
                </div>
                <div class="step-item" data-step="5">
                    <div class="step-circle">5</div>
                    <div class="step-label">Finalização</div>
                </div>
            </div>

            <form id="formPlanoManejo">
                <!-- Step 1: Identificação -->
                <div class="step-content" id="step-1">
                    <h5 class="mb-3">Informações Básicas</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="propriedade_id" class="form-label">Propriedade</label>
                            <select class="form-select" id="propriedade_id" name="propriedade_id" required>
                                <option value="">Selecione a Propriedade</option>
                                <?php foreach ($propriedades as $p): ?>
                                    <option value="<?php echo $p['id']; ?>"><?php echo $p['propriedade']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="associado_id" class="form-label">Agricultor Responsável</label>
                            <select class="form-select" id="associado_id" name="associado_id" required>
                                <option value="">Selecione o Agricultor</option>
                                <?php foreach ($associados as $a): ?>
                                    <option value="<?php echo $a['id']; ?>"><?php echo $a['nome'] . ' ' . $a['sobrenome']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="ano_safra" class="form-label">Ano Safra</label>
                            <input type="text" class="form-control" id="ano_safra" name="ano_safra" placeholder="Ex: 2025/2026" required>
                        </div>
                        <div class="col-md-8 mb-3">
                            <label for="titulo" class="form-label">Título do Plano</label>
                            <input type="text" class="form-control" id="titulo" name="titulo" placeholder="Ex: Plano Anual de Produção Orgânica">
                        </div>
                    </div>
                </div>

                <!-- Step 2: Propriedade -->
                <div class="step-content d-none" id="step-2">
                    <h5 class="mb-3">Caracterização da Área</h5>
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Área Orgânica (ha)</label>
                            <input type="number" step="0.01" class="form-control" name="area_organica_ha">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Área em Conversão (ha)</label>
                            <input type="number" step="0.01" class="form-control" name="area_conversao_ha">
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Solo (Tipo e Condição)</label>
                            <textarea class="form-control" name="descricao_solo" rows="2"></textarea>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Topografia</label>
                            <select class="form-select" name="topografia">
                                <option value="Plana">Plana</option>
                                <option value="Ondulada">Ondulada</option>
                                <option value="Montanhosa">Montanhosa</option>
                                <option value="Irregular">Irregular</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Práticas -->
                <div class="step-content d-none" id="step-3">
                    <h5 class="mb-3">Práticas de Manejo</h5>
                    <p class="text-muted small">Descreva brevemente como é feito o manejo em cada área:</p>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Manejo de Pragas e Doenças</label>
                            <textarea class="form-control" name="manejo_pragas" rows="3" placeholder="Ex: Caldas, Controle Biológico..."></textarea>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Manejo de Solo e Adubação</label>
                            <textarea class="form-control" name="adubacao_verde" rows="3" placeholder="Ex: Adubação verde, Compostagem..."></textarea>
                        </div>
                    </div>
                </div>

                <!-- Step 4: Cultivos -->
                <div class="step-content d-none" id="step-4">
                    <h5 class="mb-3">Planejamento de Cultivos</h5>
                    <div id="lista-cultivos">
                        <!-- Itens adicionados via JS -->
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm mb-3" id="add-cultivo">
                        <i class="bi bi-plus"></i> Adicionar Cultura
                    </button>
                </div>

                <!-- Step 5: Finalização + Preview DataTable -->
                <div class="step-content d-none" id="step-5">
                    <h5 class="mb-3">Revisão Final - Confirme os Dados</h5>
                    <div class="row mb-4">
                        <div class="col-md-8">
                            <div class="alert alert-info">
                                <i class="bi bi-eye"></i> Revise todas as informações antes de salvar. Os dados serão registrados permanentemente no sistema.
                            </div>
                        </div>
                        <div class="col-md-4 text-end">
                            <button type="button" class="btn btn-warning btn-sm" onclick="gerarPreview()">
                                <i class="bi bi-arrow-clockwise"></i> Atualizar Preview
                            </button>
                        </div>
                    </div>
                    
                    <!-- Preview Geral -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-card-text me-2"></i>Resumo do Plano</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3"><strong>Safra:</strong> <span id="preview-safra">-</span></div>
                                <div class="col-md-3"><strong>Título:</strong> <span id="preview-titulo">-</span></div>
                                <div class="col-md-3"><strong>Propriedade:</strong> <span id="preview-propriedade">-</span></div>
                                <div class="col-md-3"><strong>Agricultor:</strong> <span id="preview-agricultor">-</span></div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-md-3"><strong>Área Orgânica:</strong> <span id="preview-area-org">-</span> ha</div>
                                <div class="col-md-3"><strong>Área Conversão:</strong> <span id="preview-area-conv">-</span> ha</div>
                                <div class="col-md-3"><strong>Solo:</strong> <span id="preview-solo">-</span></div>
                                <div class="col-md-3"><strong>Topografia:</strong> <span id="preview-topo">-</span></div>
                            </div>
                        </div>
                    </div>

                    <!-- DataTable Cultivos -->
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><i class="bi bi-table me-2"></i>Lista de Cultivos Planejados</h6>
                            <span class="badge bg-info" id="total-cultivos">0 cultivos</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table id="previewCultivosTable" class="table table-sm mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Cultura</th>
                                            <th>Área (ha)</th>
                                            <th>Tipo Semente</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Observações Finais</label>
                        <textarea class="form-control" name="observacoes" rows="3" placeholder="Comentários adicionais..."></textarea>
                    </div>
                </div>

                <!-- Botões de Navegação -->
                <div class="mt-4 pt-3 border-top d-flex justify-content-between">
                    <button type="button" class="btn btn-secondary" id="prev-btn" disabled>Anterior</button>
                    <button type="button" class="btn btn-primary" id="next-btn">Próximo</button>
                    <button type="submit" class="btn btn-success d-none" id="submit-btn">Concluir Plano</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.step-item { text-align: center; flex: 1; position: relative; }
.step-circle { width: 40px; height: 40px; border-radius: 50%; background: #e9ecef; color: #6c757d; line-height: 40px; margin: 0 auto 10px; font-weight: bold; position: relative; z-index: 2; }
.step-item.active .step-circle { background: #198754; color: white; }
.step-item.completed .step-circle { background: #198754; color: white; }
.step-label { font-size: 12px; color: #6c757d; }
.step-item.active .step-label { color: #198754; font-weight: bold; }
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<script>
$(document).ready(function() {
    let currentStep = 1;
    const totalSteps = 5;

    function updateWizard() {
        $('.step-content').addClass('d-none');
        $(`#step-${currentStep}`).removeClass('d-none');
        
        $('.step-item').removeClass('active');
        $(`.step-item[data-step="${currentStep}"]`).addClass('active');

        $('#prev-btn').prop('disabled', currentStep === 1);
        
        if (currentStep === totalSteps) {
            // Ao entrar na etapa 5, gera o resumo final e troca o botão 'Próximo' por 'Salvar'
            if (typeof gerarPreview === 'function') {
                gerarPreview();
            }
            $('#next-btn').addClass('d-none');
            $('#submit-btn').removeClass('d-none');
        } else {
            $('#next-btn').removeClass('d-none');
            $('#submit-btn').addClass('d-none');
        }
    }

    $('#prev-btn').click(function() {
        if (currentStep > 1) {
            currentStep--;
            updateWizard();
        }
    });

    // Adicionar Cultivo
    $('#add-cultivo').click(function() {
        const index = $('#lista-cultivos .row').length;
        const html = `
            <div class="row border rounded p-3 mb-3 bg-light position-relative">
                <button type="button" class="btn-close position-absolute top-0 end-0 m-2 remove-cultivo" style="font-size: 0.7rem;"></button>
                <div class="col-md-4 mb-2">
                    <label class="form-label small">Cultura</label>
                    <input type="text" class="form-control form-control-sm" name="cultivos[${index}][cultura]" required>
                </div>
                <div class="col-md-4 mb-2">
                    <label class="form-label small">Área (ha)</label>
                    <input type="number" step="0.01" class="form-control form-control-sm" name="cultivos[${index}][area_cultivada_ha]">
                </div>
                <div class="col-md-4 mb-2">
                    <label class="form-label small">Semente</label>
                    <select class="form-select form-select-sm" name="cultivos[${index}][tipo_semente]">
                        <option value="Orgânica Certificada">Orgânica Certificada</option>
                        <option value="Convencional Não Tratada">Convencional Não Tratada</option>
                        <option value="Crioula">Crioula</option>
                        <option value="Própria">Própria</option>
                    </select>
                </div>
            </div>
        `;
        $('#lista-cultivos').append(html);
    });

    $(document).on('click', '.remove-cultivo', function() {
        $(this).closest('.row').remove();
    });

    // Função para gerar preview
    window.gerarPreview = function() {
        const formData = {
            propriedade_id: $('#propriedade_id').val(),
            associado_id: $('#associado_id').val(),
            ano_safra: $('#ano_safra').val(),
            titulo: $('#titulo').val(),
            area_organica_ha: $('input[name="area_organica_ha"]').val() || 0,
            area_conversao_ha: $('input[name="area_conversao_ha"]').val() || 0,
            descricao_solo: $('textarea[name="descricao_solo"]').val(),
            topografia: $('select[name="topografia"]').val(),
            manejo_pragas: $('textarea[name="manejo_pragas"]').val(),
            adubacao_verde: $('textarea[name="adubacao_verde"]').val(),
            cultivos: []
        };

        // Propriedade e agricultor
        const prop = $('#propriedade_id option:selected').text();
        const assoc = $('#associado_id option:selected').text();
        
        $('#preview-propriedade').text(prop || '-');
        $('#preview-agricultor').text(assoc || '-');
        $('#preview-safra').text(formData.ano_safra || '-');
        $('#preview-titulo').text(formData.titulo || '-');
        $('#preview-area-org').text(formData.area_organica_ha);
        $('#preview-area-conv').text(formData.area_conversao_ha);
        $('#preview-solo').text(formData.descricao_solo || '-');
        $('#preview-topo').text(formData.topografia || '-');

        // Cultivos DataTable
        const cultivosTable = $('#previewCultivosTable').DataTable({
            destroy: true,
            data: [],
            pageLength: 5,
            language: { url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json' },
            columns: [
                { data: 'cultura' },
                { data: 'area_cultivada_ha', render: function(data) { return data || '0.00'; } },
                { data: 'tipo_semente' }
            ]
        });

        // Coletar cultivos
        let cultivos = [];
        $('#lista-cultivos .row').each(function() {
            const cultura = $(this).find('input[name*="[cultura]"]').val();
            const area = $(this).find('input[name*="[area_cultivada_ha]"]').val() || 0;
            const semente = $(this).find('select[name*="[tipo_semente]"]').val();
            
            if (cultura) {
                cultivos.push({cultura, area_cultivada_ha: parseFloat(area).toFixed(2), tipo_semente});
            }
        });
        
        formData.cultivos = cultivos;
        cultivosTable.clear().rows.add(cultivos).draw();
        $('#total-cultivos').text(cultivos.length + ' cultivos');
    };

    // Navegação entre etapas (Next)
    $('#next-btn').click(function() {
        if (currentStep === 4) {
            gerarPreview();
        }
        if (currentStep < totalSteps) {
            currentStep++;
            updateWizard();
        }
    });

    $('#formPlanoManejo').submit(function(e) {
        e.preventDefault();
        
        if (!confirm('✅ Confirma preview? Salvar permanentemente no banco?')) {
            return false;
        }
        
        $('#submit-btn').prop('disabled', true).html('<i class="spinner-border spinner-border-sm"></i> Salvando...');

        const formData = {
            propriedade_id: parseInt($('#propriedade_id').val()),
            associado_id: parseInt($('#associado_id').val()),
            ano_safra: $('#ano_safra').val().trim(),
            titulo: $('#titulo').val().trim(),
            area_organica_ha: parseFloat($('input[name="area_organica_ha"]').val()) || 0,
            area_conversao_ha: parseFloat($('input[name="area_conversao_ha"]').val()) || 0,
            descricao_solo: $('textarea[name="descricao_solo"]').val().trim(),
            topografia: $('select[name="topografia"]').val(),
            manejo_pragas: $('textarea[name="manejo_pragas"]').val().trim(),
            adubacao_verde: $('textarea[name="adubacao_verde"]').val().trim(),
            observacoes: $('textarea[name="observacoes"]').val().trim(),
            cultivos: []
        };

        // Validar campos obrigatórios
        if (!formData.propriedade_id || !formData.associado_id || !formData.ano_safra || !formData.titulo) {
            alert('❌ Preencha todos os campos obrigatórios (*)');
            $('#submit-btn').prop('disabled', false).html('Concluir Plano');
            return;
        }

        // Cultivos
        let hasCultivo = false;
        $('#lista-cultivos .row').each(function() {
            const cultura = $(this).find('input[name*="[cultura]"]').val().trim();
            if (cultura) {
                hasCultivo = true;
                formData.cultivos.push({
                    cultura,
                    area_cultivada_ha: parseFloat($(this).find('input[name*="[area_cultivada_ha]"]').val()) || 0,
                    tipo_semente: $(this).find('select[name*="[tipo_semente]"]').val()
                });
            }
        });

        if (formData.cultivos.length === 0) {
            if (!confirm('Nenhum cultivo adicionado. Continuar mesmo assim?')) {
                $('#submit-btn').prop('disabled', false).html('Concluir Plano');
                return;
            }
        }

        console.log('Enviando:', formData); // Debug

        fetch('<?php echo SITE_URL; ?>/api/plano-manejo', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(formData)
        })
        .then(response => {
            if (!response.ok) throw new Error('HTTP ' + response.status);
            return response.json();
        })
        .then(data => {
            console.log('Resposta API:', data);
            if (data.success) {
                alert('✅ Plano salvo! ID: ' + data.id);
                window.location.href = '<?php echo SITE_URL; ?>/planos-manejo';
            } else {
                alert('❌ API Error: ' + (data.error || 'Erro desconhecido'));
            }
        })
        .catch(error => {
            console.error('Fetch Error:', error);
            alert('❌ Erro conexão: ' + error.message);
        })
        .finally(() => {
            $('#submit-btn').prop('disabled', false).html('Concluir Plano');
        });
    });

    // Inicializar apenas UMA VEZ
    updateWizard();
    $('#add-cultivo').trigger('click'); // Primeiro cultivo
});
</script>
