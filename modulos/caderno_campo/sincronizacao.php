<?php
$page_title = "Sincronização Offline";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/menu.php';

if (!$user->isLoggedIn()) {
    echo "<div class='alert alert-danger'>Você não tem permissão para acessar esta página.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}
?>

<div class="container-fluid px-4 py-4">
    <div class="row align-items-center mb-4">
        <div class="col-md-8">
            <h1 class="h2 fw-bold"><i class="bi bi-arrow-repeat text-primary me-2"></i>Sincronização Offline</h1>
            <p class="text-muted mb-0">Gerencie os dados salvos no dispositivo e sincronize com o servidor.</p>
        </div>
        <div class="col-md-4 text-md-end mt-3 mt-md-0">
            <span id="status-indicator" class="badge bg-secondary rounded-pill px-3 py-2 fs-6">
                <i class="bi bi-hourglass-split"></i> Verificando...
            </span>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center py-4">
                    <div class="bg-light-primary d-inline-flex p-3 rounded-3 mb-3">
                        <i class="bi bi-wifi fs-1 text-primary"></i>
                    </div>
                    <h5>Status da Conexão</h5>
                    <p class="mb-0" id="connection-status">Verificando...</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center py-4">
                    <div class="bg-light-success d-inline-flex p-3 rounded-3 mb-3">
                        <i class="bi bi-database fs-1 text-success"></i>
                    </div>
                    <h5>Registros Pendentes</h5>
                    <p class="display-6 fw-bold mb-0" id="pending-count">0</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center py-4">
                    <div class="bg-light-warning d-inline-flex p-3 rounded-3 mb-3">
                        <i class="bi bi-clock-history fs-1 text-warning"></i>
                    </div>
                    <h5>Última Sincronização</h5>
                    <p class="mb-0" id="last-sync">Nunca</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold"><i class="bi bi-list-ul me-2"></i>Registros na Fila de Sincronização</h5>
            <div>
                <button class="btn btn-success btn-sm me-2" onclick="processarSincronizacao()">
                    <i class="bi bi-arrow-repeat"></i> Sincronizar Agora
                </button>
                <button class="btn btn-outline-danger btn-sm" onclick="limparFila()">
                    <i class="bi bi-trash"></i> Limpar Fila
                </button>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle" id="sync-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tipo</th>
                            <th>Dados</th>
                            <th>Data/Hora</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody id="sync-table-body">
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                                Nenhum registro pendente de sincronização.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0 fw-bold"><i class="bi bi-info-circle me-2"></i>Como Funciona</h5>
        </div>
        <div class="card-body">
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="text-center">
                        <span class="step-circle d-inline-flex align-items-center justify-content-center bg-primary text-white rounded-circle mb-2" style="width:40px;height:40px;">1</span>
                        <h6>Registro Offline</h6>
                        <p class="text-muted small">Quando estiver sem internet, os dados são salvos automaticamente no seu dispositivo.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-center">
                        <span class="step-circle d-inline-flex align-items-center justify-content-center bg-primary text-white rounded-circle mb-2" style="width:40px;height:40px;">2</span>
                        <h6>Fila Local</h6>
                        <p class="text-muted small">Os registros ficam armazenados no navegador até que a conexão seja restabelecida.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-center">
                        <span class="step-circle d-inline-flex align-items-center justify-content-center bg-primary text-white rounded-circle mb-2" style="width:40px;height:40px;">3</span>
                        <h6>Sincronização Automática</h6>
                        <p class="text-muted small">Ao voltar a ficar online, os dados são enviados automaticamente para o servidor.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.bg-light-primary { background-color: #e7f1ff; }
.bg-light-success { background-color: #d1e7dd; }
.bg-light-warning { background-color: #fff3cd; }
.step-circle { font-weight: 700; font-size: 1.1rem; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    atualizarStatusConexao();
    carregarFilaSync();
    window.addEventListener('online', atualizarStatusConexao);
    window.addEventListener('offline', atualizarStatusConexao);
});

function atualizarStatusConexao() {
    const el = document.getElementById('connection-status');
    const ind = document.getElementById('status-indicator');
    if (navigator.onLine) {
        el.innerHTML = '<span class="text-success fw-bold"><i class="bi bi-wifi"></i> Online</span>';
        ind.className = 'badge bg-success rounded-pill px-3 py-2 fs-6';
        ind.innerHTML = '<i class="bi bi-wifi"></i> Online';
    } else {
        el.innerHTML = '<span class="text-danger fw-bold"><i class="bi bi-wifi-off"></i> Offline</span>';
        ind.className = 'badge bg-danger rounded-pill px-3 py-2 fs-6';
        ind.innerHTML = '<i class="bi bi-wifi-off"></i> Offline';
    }
}

function carregarFilaSync() {
    const request = indexedDB.open('sinporg_offline_db', 1);
    request.onsuccess = function(event) {
        const db = event.target.result;
        if (!db.objectStoreNames.contains('sync_queue')) {
            document.getElementById('pending-count').textContent = '0';
            return;
        }
        const tx = db.transaction(['sync_queue'], 'readonly');
        const store = tx.objectStore('sync_queue');
        const countReq = store.count();
        countReq.onsuccess = function() {
            document.getElementById('pending-count').textContent = countReq.result;
        };
        const getAllReq = store.getAll();
        getAllReq.onsuccess = function() {
            const records = getAllReq.result;
            const tbody = document.getElementById('sync-table-body');
            if (!records || records.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4"><i class="bi bi-inbox fs-2 d-block mb-2"></i>Nenhum registro pendente de sincronização.</td></tr>';
                return;
            }
            tbody.innerHTML = '';
            records.forEach(function(rec) {
                const data = new Date(rec.timestamp);
                const tipo = rec.type || 'desconhecido';
                const tipoIcon = tipo === 'insumo' ? 'bi-droplet' : tipo === 'ocorrencia' ? 'bi-exclamation-triangle' : 'bi-calendar-check';
                const payloadPreview = rec.payload ? Object.entries(rec.payload).slice(0, 3).map(([k, v]) => `${k}: ${v}`).join(', ') : '...';
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><code>#${rec.id}</code></td>
                    <td><span class="badge bg-info"><i class="bi ${tipoIcon} me-1"></i>${tipo}</span></td>
                    <td><small class="text-muted">${payloadPreview}</small></td>
                    <td><small>${data.toLocaleString('pt-BR')}</small></td>
                    <td>
                        <button class="btn btn-outline-danger btn-sm" onclick="removerRegistro(${rec.id})" title="Remover">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        };
    };
    request.onerror = function() {
        document.getElementById('pending-count').textContent = '0';
    };
}

function processarSincronizacao() {
    if (!navigator.onLine) {
        alert('Você está offline. Conecte-se à internet para sincronizar.');
        return;
    }
    const btn = document.querySelector('button[onclick="processarSincronizacao()"]');
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-arrow-repeat spin"></i> Sincronizando...';

    if (window.syncEngine && window.syncEngine.syncQueuedData) {
        window.syncEngine.syncQueuedData();
    }

    const request = indexedDB.open('sinporg_offline_db', 1);
    request.onsuccess = function(event) {
        const db = event.target.result;
        if (!db.objectStoreNames.contains('sync_queue')) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Sincronizar Agora';
            return;
        }
        const tx = db.transaction(['sync_queue'], 'readonly');
        const store = tx.objectStore('sync_queue');
        const getAllReq = store.getAll();
        getAllReq.onsuccess = function() {
            const records = getAllReq.result;
            if (records.length === 0) {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Sincronizar Agora';
                return;
            }
            let completed = 0;
            let failed = 0;
            records.forEach(function(rec) {
                const bodyParams = new URLSearchParams();
                if (rec.payload) {
                    Object.entries(rec.payload).forEach(([key, value]) => bodyParams.append(key, value));
                }
                fetch(rec.url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: bodyParams.toString()
                })
                .then(function(response) {
                    if (response.ok) {
                        const delTx = db.transaction(['sync_queue'], 'readwrite');
                        delTx.objectStore('sync_queue').delete(rec.id);
                        completed++;
                    } else {
                        failed++;
                    }
                })
                .catch(function() { failed++; })
                .then(function() {
                    if (completed + failed === records.length) {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Sincronizar Agora';
                        carregarFilaSync();
                        const msg = completed > 0 ? completed + ' registro(s) sincronizados com sucesso!' : 'Nenhum registro sincronizado.';
                        const cls = completed > 0 ? 'success' : 'warning';
                        const div = document.createElement('div');
                        div.className = 'alert alert-' + cls + ' alert-dismissible fade show mt-3';
                        div.innerHTML = '<strong><i class="bi bi-' + (completed > 0 ? 'check-circle' : 'exclamation-triangle') + '"></i> ' + msg + '</strong><button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
                        document.querySelector('.container-fluid').insertBefore(div, document.querySelector('.container-fluid').firstChild);
                    }
                });
            });
        };
    };
}

function limparFila() {
    if (!confirm('Tem certeza que deseja limpar toda a fila de sincronização? Os dados não salvos serão perdidos.')) return;
    const request = indexedDB.open('sinporg_offline_db', 1);
    request.onsuccess = function(event) {
        const db = event.target.result;
        if (!db.objectStoreNames.contains('sync_queue')) return;
        const tx = db.transaction(['sync_queue'], 'readwrite');
        const store = tx.objectStore('sync_queue');
        store.clear();
        tx.oncomplete = function() {
            carregarFilaSync();
            document.getElementById('pending-count').textContent = '0';
        };
    };
}

function removerRegistro(id) {
    if (!confirm('Remover este registro da fila?')) return;
    const request = indexedDB.open('sinporg_offline_db', 1);
    request.onsuccess = function(event) {
        const db = event.target.result;
        const tx = db.transaction(['sync_queue'], 'readwrite');
        tx.objectStore('sync_queue').delete(id);
        tx.oncomplete = function() { carregarFilaSync(); };
    };
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
