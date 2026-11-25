<?php
$page_title = "Agendar Nova Inspeção";
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/menu.php';
require_once __DIR__ . '/../../../classes/Database.php';

// Verificar autenticação
if (!$user->isLoggedIn()) {
    echo "<div class='alert alert-danger'>Acesso negado.</div>";
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}

$userNivel = $_SESSION['user_nivel'] ?? NIVEL_USUARIO;
$isAdmin = $userNivel <= NIVEL_GESTOR;

if (!$isAdmin) {
    echo "<div class='alert alert-danger'>Acesso negado. Apenas administradores ou gestores podem agendar visitas.</div>";
    require_once __DIR__ . '/../../../includes/footer.php';
    exit;
}
?>

<div class="gestor-modern">
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/gestor-modern.css">
<div class="page-bg px-2 px-md-0" style="min-height:100%; padding-bottom:1px;">

    <div class="gestor-hero">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 flex-wrap">
            <div>
                <h1 class="mb-1"><i class="bi bi-calendar-plus me-2" style="color:var(--gm-primary);"></i>Agendar Nova Inspeção</h1>
                <p class="mb-0">Agende visitas de fiscalização participativa (SPG/OCS) para os produtores.</p>
            </div>
            <a href="<?php echo SITE_URL; ?>/visitas-tecnicas" class="btn btn-assoc-ghost btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Voltar para Listagem
            </a>
        </div>
    </div>

    <div class="row g-4">
        <!-- Coluna de Agendamento -->
        <div class="col-lg-7">
            <div class="gm-card">
                <div class="card-head fw-bold">
                    <i class="bi bi-file-earmark-ruled me-2" style="color:var(--gm-primary);"></i>Formulário de Agendamento
                </div>
                <div class="p-4">
                    <form id="formAgendarVisita" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Agricultor *</label>
                            <select class="form-select" name="associado_id" id="select_associado" required>
                                <option value="">Selecione o Agricultor...</option>
                            </select>
                            <div class="invalid-feedback">A seleção do agricultor é obrigatória.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Propriedade *</label>
                            <select class="form-select" name="propriedade_id" id="select_propriedade" required disabled>
                                <option value="">Selecione primeiro o Agricultor</option>
                            </select>
                            <div class="invalid-feedback">A seleção da propriedade é obrigatória.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Roteiro / Template de Inspeção *</label>
                            <select class="form-select" name="roteiro_id" id="select_roteiro" required>
                                <option value="">Carregando roteiros disponíveis...</option>
                            </select>
                            <div class="invalid-feedback">A seleção do modelo de roteiro é obrigatória.</div>
                        </div>

                        <div class="row mb-3 g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Data da Visita *</label>
                                <input type="date" class="form-control" name="data_visita" required min="<?php echo date('Y-m-d'); ?>">
                                <div class="invalid-feedback">Uma data de visita válida é obrigatória.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Técnico Responsável</label>
                                <select class="form-select" name="tecnico_responsavel_id" id="select_tecnico">
                                    <option value="<?php echo $_SESSION['user_id']; ?>">Eu mesmo (<?php echo htmlspecialchars($_SESSION['user_nome'] ?? 'Técnico'); ?>)</option>
                                    <!-- Carregado via AJAX caso haja outros técnicos -->
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Membros Acompanhantes (Opcional)</label>
                            <input type="text" class="form-control" name="acompanhantes" placeholder="Ex: João da Silva (Comissão de Ética), Maria Oliveira">
                            <small class="text-muted">Nomes separados por vírgula dos membros da comissão de ética.</small>
                        </div>

                        <div class="d-flex justify-content-end gap-3 mt-4 pt-3 border-top">
                            <a href="<?php echo SITE_URL; ?>/visitas-tecnicas" class="btn btn-outline-secondary px-4 shadow-sm">Cancelar</a>
                            <button type="submit" class="btn btn-primary px-5 fw-bold shadow-sm" id="btn-agendar">
                                <i class="bi bi-calendar-check-fill me-1"></i> Confirmar Agendamento
                            </button>
                        </div>
</form>
                </div> <!-- /p-4 -->
            </div> <!-- /gm-card -->
        </div>

        <!-- Coluna Informativa e Resumo -->
        <div class="col-lg-5">
            <div class="gm-card bg-light mb-4">
                <div class="p-4">
                    <h5 class="fw-bold mb-3"><i class="bi bi-info-circle text-primary me-2"></i>Como Funciona o SPG</h5>
                    <p class="small text-muted mb-2">No <strong>Sistema Participativo de Garantia (SPG)</strong>, as inspeções são realizadas por uma equipe de técnicos e agricultores (visitas de pares).</p>
                    <p class="small text-muted mb-2">Ao agendar uma visita, você define o <strong>modelo de roteiro</strong> (templates de checklists como Visita Inicial, Regular, Renovação, etc.) e o agricultor receberá a notificação em seu painel.</p>
                    <p class="small text-muted mb-0">Durante a visita, a conformidade de cada item é avaliada e as irregularidades são registradas no banco de dados.</p>
                </div>
            </div>

            <!-- Card de Resumo do Agricultor Selecionado (Dinamico) -->
            <div class="gm-card d-none" id="card-agricultor-resumo">
                <div class="card-head fw-bold">
                    <i class="bi bi-person-lines-fill me-2" style="color:var(--gm-primary);"></i>Resumo do Agricultor
                </div>
                <div class="card-body" id="resumo-conteudo">
                    <!-- Dinâmico por JS -->
                </div>
            </div>
        </div>
    </div>

</div> <!-- /page-bg -->
</div> <!-- /gestor-modern -->

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>

<script>
$(document).ready(function() {
    // 1. Carregar Roteiros / Templates
    fetch('<?php echo SITE_URL; ?>/api/roteiros_inspecao.php?action=templates')
        .then(res => res.json())
        .then(data => {
            const sel = $('#select_roteiro');
            sel.empty().append('<option value="">Selecione o Roteiro...</option>');
            (data.data || []).forEach(t => {
                sel.append(`<option value="${t.id}">${t.nome} (${t.tipo})</option>`);
            });
        })
        .catch(err => console.error('Erro Roteiros:', err));

    // 2. Carregar Associados / Agricultores
    fetch('<?php echo SITE_URL; ?>/api/associados/listar.php')
        .then(res => res.json())
        .then(data => {
            console.log('Associados data:', data); // Debug
            const sel = $('#select_associado');
            sel.empty().append('<option value="">Selecione o Agricultor...</option>');
            (data.data || []).forEach(a => {
                sel.append(`<option value="${a.id}" 
                    data-uf="${a.uf || ''}" 
                    data-cidade="${a.cidade || ''}" 
                    data-email="${a.email || ''}" 
                    data-associacao="${a.nome_associacao || ''}">${a.nome}</option>`);
            });
        })
        .catch(err => console.error('Erro Associados:', err));

    // 3. Monitorar Mudança de Agricultor
    $('#select_associado').change(function() {
        const aid = $(this).val();
        const selP = $('#select_propriedade');
        const cardRes = $('#card-agricultor-resumo');
        
        if (!aid) {
            selP.empty().append('<option value="">Selecione primeiro o Agricultor</option>').prop('disabled', true);
            cardRes.addClass('d-none');
            return;
        }

        // Exibir Resumo no Painel Lateral
        const opt = $(this).find('option:selected');
        $('#resumo-conteudo').html(`
            <p class="mb-1"><strong>E-mail:</strong> ${opt.data('email') || 'Não informado'}</p>
            <p class="mb-1"><strong>Associação:</strong> ${opt.data('associacao') || 'Nenhuma vinculada'}</p>
            <p class="mb-0"><strong>Cidade/UF:</strong> ${opt.data('cidade') || '—'}/${opt.data('uf') || '—'}</p>
        `);
        cardRes.removeClass('d-none');

        // Carregar Propriedades
        selP.empty().append('<option value="">Carregando propriedades...</option>').prop('disabled', false);
        fetch(`<?php echo SITE_URL; ?>/api/get_propriedades_associado.php?associado_id=${aid}`)
            .then(r => r.json())
            .then(data => {
                selP.empty().append('<option value="">Selecione a Propriedade...</option>');
                const list = Array.isArray(data) ? data : [];
                if (list.length > 0) {
                    list.forEach(p => selP.append(`<option value="${p.id}">${p.nome}</option>`));
                } else {
                    selP.append('<option disabled value="">Nenhuma propriedade cadastrada para este agricultor</option>');
                }
            })
            .catch(() => {
                selP.empty().append('<option disabled value="">Erro ao buscar propriedades</option>');
            });
    });

    // 4. Enviar Form (Agendamento)
    $('#formAgendarVisita').submit(function(e) {
        e.preventDefault();
        
        if (!this.checkValidity()) {
            e.stopPropagation();
            $(this).addClass('was-validated');
            return;
        }

        $('#btn-agendar').prop('disabled', true).html('<i class="spinner-border spinner-border-sm me-1"></i> Agendando...');

        const payload = {};
        $(this).serializeArray().forEach(i => payload[i.name] = i.value);

        fetch('<?php echo SITE_URL; ?>/api/roteiros_inspecao.php?action=visitas', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('✅ Inspeção agendada com sucesso!');
                window.location.href = '<?php echo SITE_URL; ?>/visitas-tecnicas';
            } else {
                alert('❌ Erro ao agendar visita: ' + (data.error || 'Erro interno.'));
                $('#btn-agendar').prop('disabled', false).html('<i class="bi bi-calendar-check-fill me-1"></i> Confirmar Agendamento');
            }
        })
        .catch(err => {
            console.error('Agendar Error:', err);
            alert('❌ Erro de comunicação com o servidor.');
            $('#btn-agendar').prop('disabled', false).html('<i class="bi bi-calendar-check-fill me-1"></i> Confirmar Agendamento');
        });
    });
});
</script>
