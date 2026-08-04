// SINPORG - PWA Offline Sync Engine
class OfflineSyncEngine {
    constructor() {
        this.dbName = 'sinporg_offline_db';
        this.dbVersion = 1;
        this.db = null;
        this.initDatabase();
        this.initNetworkListeners();
        this.setupFormsIntercept();
    }

    initDatabase() {
        const request = indexedDB.open(this.dbName, this.dbVersion);

        request.onerror = (event) => {
            console.error('Erro ao abrir o IndexedDB:', event.target.error);
        };

        request.onsuccess = (event) => {
            this.db = event.target.result;
            console.log('IndexedDB inicializado com sucesso.');
            // Tenta sincronizar ao iniciar se estiver online
            if (navigator.onLine) {
                this.syncQueuedData();
            }
        };

        request.onupgradeneeded = (event) => {
            const db = event.target.result;
            if (!db.objectStoreNames.contains('sync_queue')) {
                db.createObjectStore('sync_queue', { keyPath: 'id', autoIncrement: true });
            }
        };
    }

    initNetworkListeners() {
        window.addEventListener('online', () => {
            this.updateOnlineStatus(true);
            this.syncQueuedData();
        });

        window.addEventListener('offline', () => {
            this.updateOnlineStatus(false);
        });

        // Executar status inicial após o DOM carregar completamente
        document.addEventListener('DOMContentLoaded', () => {
            this.updateOnlineStatus(navigator.onLine);
        });
        // Receber sinal do Service Worker (Background Sync)
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.addEventListener('message', event => {
                if (event.data && event.data.type === 'TRIGGER_SYNC') {
                    this.syncQueuedData();
                }
            });
        }
    }

    updateOnlineStatus(isOnline) {
        let badge = document.getElementById('connection-status-badge');
        if (!badge) {
            badge = document.createElement('div');
            badge.id = 'connection-status-badge';
            badge.style.position = 'fixed';
            badge.style.top = '15px';
            badge.style.right = '70px';
            badge.style.zIndex = '9999';
            badge.style.padding = '8px 16px';
            badge.style.borderRadius = '50px';
            badge.style.fontSize = '14px';
            badge.style.fontWeight = 'bold';
            badge.style.boxShadow = '0 4px 6px rgba(0,0,0,0.1)';
            badge.style.transition = 'all 0.3s ease';
            document.body.appendChild(badge);
        }

        if (isOnline) {
            badge.innerHTML = '<i class="bi bi-wifi"></i> Online';
            badge.style.backgroundColor = '#d1e7dd';
            badge.style.color = '#0f5132';
            badge.style.border = '1px solid #badbcc';
            // Ocultar suavemente após 3 segundos
            setTimeout(() => {
                badge.style.opacity = '0';
            }, 3000);
        } else {
            badge.style.opacity = '1';
            badge.innerHTML = '<i class="bi bi-wifi-off"></i> Modo Offline (Dados Locais)';
            badge.style.backgroundColor = '#f8d7da';
            badge.style.color = '#842029';
            badge.style.border = '1px solid #f5c2c7';
        }
    }

    setupFormsIntercept() {
        document.addEventListener('submit', (event) => {
            const form = event.target;
            // Verificar se o formulário é de cadastro de atividade, insumo ou ocorrência
            const action = form.getAttribute('action') || '';
            const isTargetForm = form.id === 'form-atividade' || 
                                 form.id === 'form-insumo' || 
                                 form.id === 'form-ocorrencia' ||
                                 window.location.href.includes('/insumos/cadastrar') || 
                                 window.location.href.includes('/atividade-diaria/novo') ||
                                 window.location.href.includes('/ocorrencia/novo');

            if (isTargetForm && !navigator.onLine) {
                event.preventDefault();
                this.saveFormOffline(form);
            }
        });
    }

    saveFormOffline(form) {
        const formData = new FormData(form);
        const data = {};
        formData.forEach((value, key) => {
            data[key] = value;
        });

        // Identificar o tipo do registro para sincronizar no endpoint correto
        let type = 'atividade';
        if (window.location.href.includes('/insumos')) {
            type = 'insumo';
        } else if (window.location.href.includes('/ocorrencia')) {
            type = 'ocorrencia';
        }
        const url = window.location.href;

        const transaction = this.db.transaction(['sync_queue'], 'readwrite');
        const store = transaction.objectStore('sync_queue');
        
        const record = {
            type: type,
            url: url,
            payload: data,
            timestamp: new Date().getTime()
        };

        const request = store.add(record);

        request.onsuccess = () => {
            this.showNotification('Salvo offline', 'Sem conexão. Os dados serão enviados automaticamente quando você estiver online.', 'warning');
            // Registrar Background Sync para envio automático ao voltar online
            if ('serviceWorker' in navigator && 'SyncManager' in window) {
                navigator.serviceWorker.ready
                    .then(sw => sw.sync.register('sync-forms'))
                    .catch(() => { /* fallback: syncQueuedData ao voltar online já resolve */ });
            }
            form.reset();
            // Restaurar valores readonly/default se necessário
            const estq = document.getElementById('quantidade_estoque');
            if (estq) estq.value = '0';
        };

        request.onerror = (err) => {
            console.error('Erro ao salvar no IndexedDB:', err);
            this.showNotification('Erro', 'Não foi possível salvar os dados localmente.', 'danger');
        };
    }

    syncQueuedData() {
        if (!this.db) return;

        const transaction = this.db.transaction(['sync_queue'], 'readwrite');
        const store = transaction.objectStore('sync_queue');
        const request = store.getAll();

        request.onsuccess = () => {
            const records = request.result;
            if (records.length === 0) return;

            this.updateSyncBadgeStatus('syncing');
            let completedCount = 0;

            let processedCount = 0;
            const totalCount = records.length;

            records.forEach((record) => {
                const isBinaryPayload = Object.values(record.payload).some(value => value instanceof File || value instanceof Blob);
                const fetchOptions = {
                    method: 'POST'
                };

                if (isBinaryPayload) {
                    const formData = new FormData();
                    for (const key in record.payload) {
                        const value = record.payload[key];
                        if (Array.isArray(value)) {
                            value.forEach(item => formData.append(key, item));
                        } else {
                            formData.append(key, value);
                        }
                    }
                    fetchOptions.body = formData;
                } else {
                    const bodyParams = new URLSearchParams();
                    for (const key in record.payload) {
                        const value = record.payload[key];
                        if (Array.isArray(value)) {
                            value.forEach(item => bodyParams.append(key, item));
                        } else {
                            bodyParams.append(key, value);
                        }
                    }
                    fetchOptions.headers = {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    };
                    fetchOptions.body = bodyParams.toString();
                }

                fetch(record.url, fetchOptions)
                .then(response => {
                    if (response.ok) {
                        const deleteTransaction = this.db.transaction(['sync_queue'], 'readwrite');
                        deleteTransaction.objectStore('sync_queue').delete(record.id);
                        completedCount++;
                    }
                })
                .catch(err => {
                    console.error('Erro ao sincronizar registro:', err);
                })
                .finally(() => {
                    processedCount++;
                    if (processedCount === totalCount) {
                        this.updateSyncBadgeStatus('done');
                        if (completedCount > 0) {
                            this.showNotification('Sincronização', `${completedCount} registro(s) sincronizados com sucesso!`, 'success');
                        } else {
                            this.showNotification('Sincronização', 'Nenhum registro foi sincronizado. Verifique sua conexão e tente novamente.', 'warning');
                        }
                    }
                });
            });
        };
    }

    updateSyncBadgeStatus(status) {
        const badge = document.getElementById('connection-status-badge');
        if (!badge) return;

        badge.style.opacity = '1';
        if (status === 'syncing') {
            badge.innerHTML = '<i class="bi bi-arrow-repeat spin"></i> Sincronizando dados...';
            badge.style.backgroundColor = '#fff3cd';
            badge.style.color = '#664d03';
            badge.style.border = '1px solid #ffecb5';
        } else if (status === 'done') {
            badge.innerHTML = '<i class="bi bi-check-circle"></i> Sincronizado';
            badge.style.backgroundColor = '#d1e7dd';
            badge.style.color = '#0f5132';
            badge.style.border = '1px solid #badbcc';
            setTimeout(() => {
                badge.style.opacity = '0';
            }, 3000);
        }
    }

    showNotification(title, text, type) {
        // Toast Bootstrap 5 via #toast-container (inserido pelo footer.php)
        const container = document.getElementById('toast-container');
        if (container && typeof bootstrap !== 'undefined' && bootstrap.Toast) {
            const toastId = 'sync-toast-' + Date.now();
            const bgMap = { success: 'bg-success', warning: 'bg-warning text-dark', danger: 'bg-danger', info: 'bg-info text-dark' };
            const toastEl = document.createElement('div');
            toastEl.id = toastId;
            toastEl.className = `toast align-items-center ${bgMap[type] || 'bg-secondary'} border-0`;
            toastEl.setAttribute('role', 'alert');
            toastEl.setAttribute('aria-live', 'assertive');
            toastEl.setAttribute('aria-atomic', 'true');
            toastEl.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body fw-semibold">
                        <strong>${title}:</strong> ${text}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto"
                            data-bs-dismiss="toast" aria-label="Fechar"></button>
                </div>`;
            container.appendChild(toastEl);
            const toast = new bootstrap.Toast(toastEl, { delay: 5000 });
            toast.show();
            toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
        } else {
            // Fallback: alert div (Bootstrap não carregado ainda)
            const fallback = document.querySelector('.container-fluid') || document.body;
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show mt-3`;
            alertDiv.role = 'alert';
            alertDiv.style.zIndex = '99999';
            alertDiv.innerHTML = `<strong>${title}:</strong> ${text}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>`;
            fallback.insertBefore(alertDiv, fallback.firstChild);
        }
    }

    checkPendingCount(callback) {
        if (!this.db) {
            if (callback) callback(0);
            return;
        }
        if (!this.db.objectStoreNames.contains('sync_queue')) {
            if (callback) callback(0);
            return;
        }
        const tx = this.db.transaction(['sync_queue'], 'readonly');
        const store = tx.objectStore('sync_queue');
        const countReq = store.count();
        countReq.onsuccess = function() {
            if (callback) callback(countReq.result);
        };
        countReq.onerror = function() {
            if (callback) callback(0);
        };
    }
}

// Iniciar motor de sincronização
const syncEngine = new OfflineSyncEngine();
window.syncEngine = syncEngine;
