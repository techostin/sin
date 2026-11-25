<?php
/**
 * Modal de ciência de visita agendada (SPG).
 *
 * Exibido para o agricultor (login pela tabela associados) sempre que houver
 * visitas agendadas ainda não confirmadas, cuja data ainda não expirou.
 * O agricultor pode confirmar a ciência OU recusar a data (informando o motivo)
 * quando tiver outro compromisso. O status fica visível ao gestor/técnico.
 *
 * Incluído em includes/footer.php — roda em todas as páginas autenticadas.
 */

// Apenas agricultores logados pela tabela associados
if (($_SESSION['user_table'] ?? null) !== 'associados') {
    return;
}
?>
<!-- Modal de ciência de visitas agendadas (SPG) -->
<div class="modal fade" id="modalVisitaCiente" tabindex="-1" aria-labelledby="modalVisitaCienteLabel"
     data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="modalVisitaCienteLabel">
                    <i class="bi bi-calendar-check me-2"></i>Inspeções Agendadas
                </h5>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3">
                    Você tem inspeções agendadas na sua propriedade. Confirme que está
                    ciente da data ou informe que não poderá, para que a equipe técnica
                    seja notificada.
                </p>
                <div id="visitaCienteLista" class="visita-ciente-list">
                    <div class="text-center py-4">
                        <span class="spinner-border spinner-border-sm text-success"></span>
                        <span class="ms-2">Carregando agendamentos...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    var URL = '<?php echo SITE_URL; ?>/api/roteiros_inspecao.php';

    function fmtData(data) {
        if (!data) return '—';
        var d = new Date(data + (data.length === 10 ? 'T00:00:00' : ''));
        return d.toLocaleDateString('pt-BR');
    }

    function fmtHora(h) {
        return h ? String(h).slice(0, 5) : '—';
    }

    function renderItem(v) {
        var tecnico = v.tecnico_nome && v.tecnico_nome.trim() ? v.tecnico_nome.trim() : 'Equipe técnica';
        return '' +
            '<div class="visita-ciente-item border rounded p-3 mb-3" data-id="' + v.id + '">' +
                '<div class="d-flex justify-content-between align-items-start gap-2">' +
                    '<div class="fw-semibold text-success"><i class="bi bi-house-door me-1"></i>' + (v.propriedade_nome || 'Propriedade') + '</div>' +
                    '<span class="badge bg-info text-white">' + fmtData(v.data_visita) + ' · ' + fmtHora(v.hora_inicio) + '</span>' +
                '</div>' +
                '<div class="small text-muted mt-1">' +
                    '<i class="bi bi-card-checklist me-1"></i>' + (v.roteiro_nome || 'Roteiro de inspeção') +
                    ' &nbsp; <i class="bi bi-person-badge me-1"></i>' + tecnico +
                '</div>' +
                '<div class="form-check mt-3">' +
                    '<input class="form-check-input visita-ciente-check" type="checkbox" value="" id="ciente-check-' + v.id + '">' +
                    '<label class="form-check-label" for="ciente-check-' + v.id + '">' +
                        'Estou ciente desta inspeção agendada.' +
                    '</label>' +
                '</div>' +
                '<div class="d-grid mt-2">' +
                    '<button type="button" class="btn btn-outline-warning btn-sm btn-nao-ciente" data-id="' + v.id + '">' +
                        '<i class="bi bi-calendar-x me-1"></i>Não poderei nesta data' +
                    '</button>' +
                '</div>' +
                '<div class="nao-ciente-form d-none mt-2" id="nao-ciente-form-' + v.id + '">' +
                    '<textarea class="form-control form-control-sm nao-ciente-motivo" rows="2" maxlength="500" placeholder="Informe o motivo (ex.: outro compromisso na data) para que o técnico reagende a visita."></textarea>' +
                    '<div class="d-grid mt-2">' +
                        '<button type="button" class="btn btn-danger btn-sm btn-enviar-nao-ciente" data-id="' + v.id + '">' +
                            '<i class="bi bi-send me-1"></i>Enviar e solicitar reagendamento' +
                        '</button>' +
                    '</div>' +
                '</div>' +
            '</div>';
    }

    function checkHasConfirmed() {
        var checks = document.querySelectorAll('#visitaCienteLista .visita-ciente-check');
        var has = false;
        checks.forEach(function(c) { if (c.checked) has = true; });
        return has;
    }

    function removerItem(list, id) {
        var el = list.querySelector('.visita-ciente-item[data-id="' + id + '"]');
        if (el) el.remove();
        if (!list.querySelector('.visita-ciente-item')) {
            bootstrap.Modal.getInstance(document.getElementById('modalVisitaCiente')).hide();
        }
    }

    function load() {
        fetch(URL + '?action=visitas_ciente_pendentes')
            .then(function(r) { return r.json(); })
            .then(function(res) {
                var list = document.getElementById('visitaCienteLista');
                if (!res.success) return;
                var visitas = res.data || [];
                if (visitas.length === 0) return;

                var html = '';
                visitas.forEach(function(v) { html += renderItem(v); });
                html += '<div class="d-grid">' +
                            '<button type="button" id="btnVisitaCienteOk" class="btn btn-success btn-lg" disabled>' +
                                '<i class="bi bi-check-circle me-2"></i>Confirmar ciência' +
                            '</button>' +
                        '</div>';
                list.innerHTML = html;

                // Confirmar ciência das visitas marcadas
                list.querySelectorAll('.visita-ciente-check').forEach(function(c) {
                    c.addEventListener('change', function() {
                        document.getElementById('btnVisitaCienteOk').disabled = !checkHasConfirmed();
                    });
                });

                document.getElementById('btnVisitaCienteOk').addEventListener('click', function() {
                    var btn = this;
                    btn.disabled = true;
                    var pendentes = [];
                    list.querySelectorAll('.visita-ciente-item').forEach(function(item) {
                        var check = item.querySelector('.visita-ciente-check');
                        if (check && check.checked) pendentes.push(item.dataset.id);
                    });

                    var fila = pendentes.slice();
                    function proximo() {
                        if (fila.length === 0) {
                            pendentes.forEach(function(id) { removerItem(list, id); });
                            return;
                        }
                        var id = fila.shift();
                        fetch(URL + '?action=confirmar_ciente', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ visita_id: id })
                        })
                        .then(function(r) { return r.json(); })
                        .then(function(res) {
                            if (!res.success) {
                                var el = list.querySelector('.visita-ciente-item[data-id="' + id + '"]');
                                if (el) {
                                    var badge = document.createElement('div');
                                    badge.className = 'text-danger small mt-2';
                                    badge.textContent = res.error || 'Erro ao confirmar';
                                    el.appendChild(badge);
                                }
                            }
                            proximo();
                        })
                        .catch(function() { proximo(); });
                    }
                    proximo();
                });

                // Não poderei nesta data
                list.querySelectorAll('.btn-nao-ciente').forEach(function(b) {
                    b.addEventListener('click', function() {
                        var id = this.dataset.id;
                        var form = document.getElementById('nao-ciente-form-' + id);
                        if (form) form.classList.toggle('d-none');
                    });
                });

                list.querySelectorAll('.btn-enviar-nao-ciente').forEach(function(b) {
                    b.addEventListener('click', function() {
                        var id = this.dataset.id;
                        var item = list.querySelector('.visita-ciente-item[data-id="' + id + '"]');
                        var texto = item.querySelector('.nao-ciente-motivo').value.trim();
                        var btn = this;
                        btn.disabled = true;
                        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Enviando...';

                        fetch(URL + '?action=recusar_ciente', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ visita_id: id, motivo: texto })
                        })
                        .then(function(r) { return r.json(); })
                        .then(function(res) {
                            if (res.success) {
                                removerItem(list, id);
                            } else {
                                btn.disabled = false;
                                btn.innerHTML = '<i class="bi bi-send me-1"></i>Enviar e solicitar reagendamento';
                                var badge = document.createElement('div');
                                badge.className = 'text-danger small mt-2';
                                badge.textContent = res.error || 'Erro ao enviar';
                                item.appendChild(badge);
                            }
                        })
                        .catch(function() {
                            btn.disabled = false;
                            btn.innerHTML = '<i class="bi bi-send me-1"></i>Enviar e solicitar reagendamento';
                        });
                    });
                });

                bootstrap.Modal.getOrCreateInstance(document.getElementById('modalVisitaCiente')).show();
            })
            .catch(function(e) { console.error('VisitaCiente:', e); });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', load);
    } else {
        load();
    }
})();
</script>