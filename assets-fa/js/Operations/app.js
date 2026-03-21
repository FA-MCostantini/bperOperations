const OperationsApp = {
    template: `
        <div class="container mt-4">
            <div class="row g-4">
                <div v-for="(op, index) in operations" :key="index" class="col-md-4 col-lg-3">
                    <div class="card operation-card h-100"
                         :class="{ disabled: !op.enabled }"
                         :title="!op.enabled ? 'Operazione temporaneamente non disponibile' : ''"
                         @click="openOperation(op)">
                        <div class="card-body text-center">
                            <i :class="'bi ' + op.icon + ' fs-1 text-' + op.color"></i>
                            <h5 class="card-title mt-3">{{ op.title }}</h5>
                            <p class="card-text text-muted">{{ op.description }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modale operazione -->
            <div class="modal fade" id="operationModal" tabindex="-1" data-bs-backdrop="static">
                <div class="modal-dialog modal-xl modal-dialog-scrollable" style="max-width: 95vw;">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">{{ currentOperation ? currentOperation.title : '' }}</h5>
                            <button type="button" class="btn-close" @click="closeModal"></button>
                        </div>
                        <div class="modal-body">
                            <div v-for="(op, index) in operations" :key="'container-' + index">
                                <div :id="'container-' + getContainerId(op)"
                                     v-show="currentOperation && currentOperation.jsPath === op.jsPath">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Toast container -->
            <div id="toast-container"></div>
        </div>
    `,

    data() {
        return {
            operations: [],
            currentOperation: null,
            loadedScripts: {},
            mountedApps: {},
            modal: null
        };
    },

    mounted() {
        this.fetchOperations();
    },

    methods: {
        fetchOperations() {
            fetch('./model/ajax/ajax_operations_view.php?action=list')
                .then(res => handleAjaxResponse(res, 'Errore nel caricamento delle operazioni'))
                .then(json => {
                    this.operations = json.data;
                })
                .catch(err => handleNetworkError(err, 'caricamento operazioni'));
        },

        getContainerId(op) {
            const parts = op.jsPath.split('/');
            const filename = parts[parts.length - 1].replace('.js', '');
            return filename;
        },

        openOperation(op) {
            if (!op.enabled) return;

            this.currentOperation = op;
            const containerId = this.getContainerId(op);

            if (!this.modal) {
                const modalEl = document.getElementById('operationModal');
                this.modal = new bootstrap.Modal(modalEl);
            }

            if (!this.loadedScripts[op.jsPath]) {
                const script = document.createElement('script');
                script.src = op.jsPath;
                script.onload = () => {
                    this.loadedScripts[op.jsPath] = true;
                    const appName = this.getAppName(op);
                    if (window[appName]) {
                        const vueApp = Vue.createApp(window[appName]);
                        this.mountedApps[op.jsPath] = vueApp.mount('#container-' + containerId);
                    }
                    this.modal.show();
                };
                script.onerror = () => {
                    showErrorToast('Errore nel caricamento del modulo: ' + op.title);
                };
                document.body.appendChild(script);
            } else {
                this.modal.show();
                this.$nextTick(() => {
                    document.dispatchEvent(new CustomEvent('modal-reopen', { detail: { operation: containerId } }));
                });
            }
        },

        getAppName(op) {
            const parts = op.jsPath.split('/');
            const filename = parts[parts.length - 1].replace('.js', '');
            return filename.charAt(0).toUpperCase() + filename.slice(1);
        },

        closeModal() {
            if (this.modal) {
                this.modal.hide();
            }
        }
    }
};

/**
 * Mostra un toast di errore con chiusura manuale (X) o automatica dopo 15 secondi.
 * @param {string} message  - Messaggio principale
 * @param {string} [detail] - Dettaglio tecnico (eccezione, stack trace)
 */
function showErrorToast(message, detail) {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const toastId = 'toast-' + Date.now();
    const safeMessage = escapeHtml(message);
    const detailHtml = detail
        ? '<hr class="my-1 border-light opacity-50">' +
          '<pre class="mb-0 small" style="white-space:pre-wrap;word-break:break-word;max-height:200px;overflow-y:auto;">' +
          escapeHtml(detail) + '</pre>'
        : '';

    const toastHtml =
        '<div id="' + toastId + '" class="toast align-items-center text-bg-danger border-0" role="alert" ' +
             'data-bs-autohide="true" data-bs-delay="15000">' +
        '<div class="d-flex">' +
        '<div class="toast-body">' + safeMessage + detailHtml + '</div>' +
        '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>' +
        '</div></div>';
    container.insertAdjacentHTML('beforeend', toastHtml);

    const toastEl = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastEl);
    toast.show();

    toastEl.addEventListener('hidden.bs.toast', function () {
        toastEl.remove();
    });
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
}

/**
 * Gestisce l'errore di una fetch AJAX mostrando un toast dettagliato.
 * Usare come: .then(res => handleAjaxResponse(res, 'Errore nel caricamento'))
 */
function handleAjaxResponse(response, fallbackMessage) {
    if (!response.ok) {
        return response.json().catch(function () { return {}; }).then(function (json) {
            var msg = (json && json.message) ? json.message : fallbackMessage + ' (HTTP ' + response.status + ')';
            var detail = (json && json.exception) ? json.exception : null;
            showErrorToast(msg, detail);
            var err = new Error(msg);
            err._toastShown = true;
            return Promise.reject(err);
        });
    }
    return response.json().then(function (json) {
        if (!json.success && json.success !== undefined) {
            var detail = json.exception || null;
            showErrorToast(json.message || fallbackMessage, detail);
            var err = new Error(json.message || fallbackMessage);
            err._toastShown = true;
            return Promise.reject(err);
        }
        return json;
    });
}

/**
 * Handler per errori di rete (catch di fetch).
 * Ignora errori gia' gestiti da handleAjaxResponse (doppio toast).
 * @param {Error} err
 * @param {string} context - Contesto dell'operazione fallita
 */
function handleNetworkError(err, context) {
    if (err && err._toastShown) return;
    var detail = err.message || '';
    if (err.name) detail = err.name + ': ' + detail;
    if (err.stack) detail += '\n' + err.stack;
    showErrorToast('Errore di connessione al server — ' + context, detail);
}
