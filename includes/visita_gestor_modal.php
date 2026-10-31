<?php
/**
 * Modal de acompanhamento de confirmações de visitas agendadas (SPG) — GESTOR.
 *
 * Idêntico em estrutura ao modal do agricultor: lista as visitas agendadas
 * (data atual ou futura) de TODOS os agricultores com o status de confirmação
 * (Confirmado / Recusou / Pendente) e permite, no mesmo modal, reagendar
 * qualquer visita informando nova data e hora, além de registrar a ciência
 * do gestor sobre o status atual de cada agendamento.
 *
 * A ciência é individual por card e fica salva no banco (visitas_tecnicas)
 * para relatórios futuros. O modal só pode ser fechado quando a ciência
 * tiver sido registrada em todos os cards.
 *
 * Exibido sempre que houver visitas agendadas, em todas as páginas
 * autenticadas do gestor/técnico (login pela tabela usuarios).
 */

$gstNivel = $_SESSION['user_nivel'] ?? NIVEL_USUARIO;
if ($gstNivel > NIVEL_GESTOR) {
    return;
}
?>
<!-- Modal de confirmações/reagendamento de visitas (SPG) -->
<div class="modal fade" id="modalVisitasGestor" tabindex="-1" aria-labelledby="modalVisitasGestorLabel"
     data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,#2e7d32,#198754);color:#fff;">
                <h5 class="modal-title" id="modalVisitasGestorLabel">
                    <i class="bi bi-clipboard2-check me-2"></i>Confirmações de Visitas Agendadas
                </h5>
                <button type="button" class="btn-close btn-close-white" id="gestorModalFechar"
                        data-bs-dismiss="modal" aria-label="Fechar" disabled
                        title="Registre a ciência em todos os cards para fechar"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3">
                    Acompanhe a confirmação dos agricultores sobre as inspeções agendadas.
                    Clique em <strong>Ciente</strong> para registrar que você tomou ciência do status atual
                    ou em <strong>Reagendar</strong> para alterar a data/hora; o agricultor
                    será notificado novamente sobre a nova data.
                </p>
                <div id="gestorCienteAlerta" class="alert alert-success d-none mb-3 small">
                    <i class="bi bi-check-circle me-1"></i>Todas as visitas com ciência registrada. Você já pode fechar este modal.
                </div>
                <div id="gestorCienteStatus" class="d-none mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="small text-muted"><i class="bi bi-eye me-1"></i>Ciência do gestor registrada</span>
                        <span class="small fw-semibold" id="gestorCienteContador">0 de 0</span>
                    </div>
                    <div class="progress" style="height:8px;">
                        <div class="progress-bar bg-success" id="gestorCienteBarra" style="width:0%"></div>
                    </div>
                </div>
                <div id="visitasGestorLista" class="visitas-gestor-list">
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

    function hoje() {
        var d = new Date();
        var mm = String(d.getMonth() + 1).padStart(2, '0');
        var dd = String(d.getDate()).padStart(2, '0');
        return d.getFullYear() + '-' + mm + '-' + dd;
    }

    function statusBadge(v) {
        if (v.ciente) {
            return '<span class="badge bg-success" title="Confirmado em ' + fmtData(v.ciente_em) + ' ' + (v.ciente_em ? String(v.ciente_em).slice(11,16) : '') + '"><i class="bi bi-check-circle me-1"></i>Confirmado</span>';
        }
        if (v.ciente_recusada) {
            return '<span class="badge bg-danger" title="Recusou em ' + fmtData(v.ciente_recusada_em) + ' — Motivo: ' + (v.ciente_recusada_motivo || 'não informado') + '"><i class="bi bi-calendar-x me-1"></i>Recusou</span>';
        }
        return '<span class="badge bg-warning text-dark" title="Agricultor ainda não respondeu"><i class="bi bi-hourglass-split me-1"></i>Pendente</span>';
    }

    function gestorCienteBtn(v) {
        if (v.gestor_ciente) {
            return '<button type="button" class="btn btn-success btn-sm btn-gestor-ciente" data-id="' + v.id + '" disabled title="Ciência registrada em ' + fmtData(v.gestor_ciente_em) + ' ' + (v.gestor_ciente_em ? String(v.gestor_ciente_em).slice(11,16) : '') + '">' +
                       '<i class="bi bi-check-circle me-1"></i>Ciência registrada' +
                   '</button>';
        }
        return '<button type="button" class="btn btn-outline-success btn-sm btn-gestor-ciente" data-id="' + v.id + '" title="Registrar que você tomou ciência deste status">' +
                   '<i class="bi bi-eye me-1"></i>Ciente' +
               '</button>';
    }

    function renderItem(v) {
        var tecnico = v.tecnico_nome && v.tecnico_nome.trim() ? v.tecnico_nome.trim() : 'Equipe técnica';
        var motivo = (v.ciente_recusada_motivo && v.ciente_recusada_motivo.trim())
            ? v.ciente_recusada_motivo.trim() : '';
        var motivoHtml = motivo
            ? '<div class="alert alert-danger py-1 px-2 mt-2 mb-2 small"><i class="bi bi-exclamation-triangle me-1"></i><strong>Motivo:</strong> ' + motivo + '</div>'
            : '';

        return '' +
            '<div class="visitas-gestor-item border rounded p-3 mb-3" data-id="' + v.id + '"' + (v.gestor_ciente ? ' data-gestor-ciente="1"' : '') + '>' +
                '<div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">' +
                    '<div>' +
                        '<div class="fw-semibold text-success"><i class="bi bi-person-fill me-1"></i>' + (v.associado_nome || 'Agricultor') + '</div>' +
                        '<div class="small text-muted"><i class="bi bi-house-door me-1"></i>' + (v.propriedade_nome || 'Propriedade') + '</div>' +
                    '</div>' +
                    '<div class="text-end">' +
                        '<div class="mb-1">' + statusBadge(v) + '</div>' +
                        '<span class="badge bg-info text-white">' + fmtData(v.data_visita) + ' · ' + fmtHora(v.hora_inicio) + '</span>' +
                    '</div>' +
                '</div>' +
                '<div class="small text-muted mt-1">' +
                    '<i class="bi bi-card-checklist me-1"></i>' + (v.roteiro_nome || 'Roteiro de inspeção') +
                    ' &nbsp; <i class="bi bi-person-badge me-1"></i>' + tecnico +
                '</div>' +
                motivoHtml +
                '<div class="d-flex flex-wrap gap-2 mt-2">' +
                    '<button type="button" class="btn btn-outline-primary btn-sm btn-gestor-reagendar" data-id="' + v.id + '">' +
                        '<i class="bi bi-calendar-event me-1"></i>Reagendar' +
                    '</button>' +
                    gestorCienteBtn(v) +
                '</div>' +
                '<div class="gestor-reagendar-form d-none mt-2" id="gestor-reagendar-form-' + v.id + '">' +
                    '<div class="row g-2 align-items-end">' +
                        '<div class="col-6 col-sm-4">' +
                            '<label class="form-label small mb-1">Nova data</label>' +
                            '<input type="date" class="form-control form-control-sm gestor-nova-data" min="' + hoje() + '" value="' + v.data_visita + '">' +
                        '</div>' +
                        '<div class="col-6 col-sm-3">' +
                            '<label class="form-label small mb-1">Nova hora (opcional)</label>' +
                            '<input type="time" class="form-control form-control-sm gestor-nova-hora" value="' + (v.hora_inicio ? String(v.hora_inicio).slice(0,5) : '') + '">' +
                        '</div>' +
                        '<div class="col-12 col-sm-5 d-grid">' +
                            '<button type="button" class="btn btn-success btn-sm gestor-confirmar-reagendar" data-id="' + v.id + '">' +
                                '<i class="bi bi-calendar-check me-1"></i>Confirmar reagendamento' +
                            '</button>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
                '<div class="gestor-ciente-msg mt-2"></div>' +
            '</div>';
    }

    function atualizarProgresso() {
        var list = document.getElementById('visitasGestorLista');
        var items = list.querySelectorAll('.visitas-gestor-item');
        var total = items.length;
        var feitos = 0;
        items.forEach(function(it) {
            if (it.dataset.gestorCiente === '1') feitos++;
        });

        var status = document.getElementById('gestorCienteStatus');
        var contador = document.getElementById('gestorCienteContador');
        var barra = document.getElementById('gestorCienteBarra');
        var fechar = document.getElementById('gestorModalFechar');

        if (status) status.classList.remove('d-none');
        if (contador) contador.textContent = feitos + ' de ' + total;
        if (barra) barra.style.width = (total ? Math.round((feitos / total) * 100) : 100) + '%';
        if (fechar) fechar.disabled = total > 0 && feitos < total;
    }

    function verificarFechar(autoClose) {
        var list = document.getElementById('visitasGestorLista');
        var items = list.querySelectorAll('.visitas-gestor-item');
        if (!items.length) return;

        var todos = true;
        items.forEach(function(it) {
            if (it.dataset.gestorCiente !== '1') todos = false;
        });
        if (!todos) return;

        var alerta = document.getElementById('gestorCienteAlerta');
        if (alerta) alerta.classList.remove('d-none');

        if (autoClose) {
            setTimeout(function() {
                var inst = bootstrap.Modal.getInstance(document.getElementById('modalVisitasGestor'));
                if (inst) inst.hide();
            }, 900);
        }
    }

    function removerItem(list, id) {
        var el = list.querySelector('.visitas-gestor-item[data-id="' + id + '"]');
        if (el) el.remove();
        atualizarProgresso();
        if (!list.querySelector('.visitas-gestor-item')) {
            bootstrap.Modal.getInstance(document.getElementById('modalVisitasGestor')).hide();
        }
    }

    function load() {
        fetch(URL + '?action=visitas_agendadas_status')
            .then(function(r) { return r.json(); })
            .then(function(res) {
                var list = document.getElementById('visitasGestorLista');
                if (!res.success) return;
                var visitas = res.data || [];
                if (visitas.length === 0) return;

                var html = '';
                visitas.forEach(function(v) { html += renderItem(v); });
                list.innerHTML = html;

                // Abrir/fechar formulário de reagendamento
                list.querySelectorAll('.btn-gestor-reagendar').forEach(function(b) {
                    b.addEventListener('click', function() {
                        var id = this.dataset.id;
                        var form = document.getElementById('gestor-reagendar-form-' + id);
                        if (form) form.classList.toggle('d-none');
                    });
                });

                // Confirmar reagendamento
                list.querySelectorAll('.gestor-confirmar-reagendar').forEach(function(b) {
                    b.addEventListener('click', function() {
                        var id = this.dataset.id;
                        var item = list.querySelector('.visitas-gestor-item[data-id="' + id + '"]');
                        var data = item.querySelector('.gestor-nova-data').value;
                        var hora = item.querySelector('.gestor-nova-hora').value;
                        var btn = this;
                        btn.disabled = true;
                        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Reagendando...';

                        fetch(URL + '?action=reagendar_visita', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ visita_id: id, data_visita: data, hora_inicio: hora })
                        })
                        .then(function(r) { return r.json(); })
                        .then(function(res) {
                            if (res.success) {
                                removerItem(list, id);
                            } else {
                                btn.disabled = false;
                                btn.innerHTML = '<i class="bi bi-calendar-check me-1"></i>Confirmar reagendamento';
                                var badge = document.createElement('div');
                                badge.className = 'text-danger small mt-2';
                                badge.textContent = res.error || 'Erro ao reagendar';
                                item.appendChild(badge);
                            }
                        })
                        .catch(function() {
                            btn.disabled = false;
                            btn.innerHTML = '<i class="bi bi-calendar-check me-1"></i>Confirmar reagendamento';
                        });
                    });
                });

                // Registrar ciência do gestor (individual por card)
                list.querySelectorAll('.btn-gestor-ciente').forEach(function(b) {
                    b.addEventListener('click', function() {
                        if (this.disabled) return;
                        var id = this.dataset.id;
                        var item = list.querySelector('.visitas-gestor-item[data-id="' + id + '"]');
                        var btn = this;
                        btn.disabled = true;
                        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Registrando...';

                        fetch(URL + '?action=gestor_ciente', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ visita_id: id })
                        })
                        .then(function(r) { return r.json(); })
                        .then(function(res) {
                            var msg = item.querySelector('.gestor-ciente-msg');
                            if (res.success) {
                                item.dataset.gestorCiente = '1';
                                btn.className = 'btn btn-success btn-sm btn-gestor-ciente';
                                btn.disabled = true;
                                btn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Ciência registrada';
                                if (msg) msg.innerHTML = '<div class="alert alert-success py-1 px-2 mb-0 small"><i class="bi bi-check-circle me-1"></i>Ciência registrada pelo gestor</div>';
                                atualizarProgresso();
                                verificarFechar(true);
                            } else {
                                btn.disabled = false;
                                btn.innerHTML = '<i class="bi bi-eye me-1"></i>Ciente';
                                if (msg) msg.innerHTML = '<div class="text-danger small">' + (res.error || 'Erro ao registrar ciência') + '</div>';
                            }
                        })
                        .catch(function() {
                            btn.disabled = false;
                            btn.innerHTML = '<i class="bi bi-eye me-1"></i>Ciente';
                        });
                    });
                });

                atualizarProgresso();
                verificarFechar(false);

                bootstrap.Modal.getOrCreateInstance(document.getElementById('modalVisitasGestor')).show();
            })
            .catch(function(e) { console.error('VisitasGestor:', e); });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', load);
    } else {
        load();
    }
})();
</script>
