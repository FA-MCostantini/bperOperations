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
                <div class="modal-dialog modal-xl modal-dialog-scrollable">
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
            fetch('./src/model/ajax/ajax_operations_view.php?action=list')
                .then(res => res.json())
                .then(json => {
                    if (json.success) {
                        this.operations = json.data;
                    } else {
                        showErrorToast(json.message || 'Errore nel caricamento delle operazioni');
                    }
                })
                .catch(() => showErrorToast('Errore di connessione al server'));
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

function showErrorToast(message) {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const toastId = 'toast-' + Date.now();
    const toastHtml = '<div id="' + toastId + '" class="toast align-items-center text-bg-danger border-0" role="alert" data-bs-autohide="false">' +
        '<div class="d-flex">' +
        '<div class="toast-body">' + message + '</div>' +
        '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>' +
        '</div></div>';
    container.insertAdjacentHTML('beforeend', toastHtml);

    const toastEl = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastEl);
    toast.show();
}
