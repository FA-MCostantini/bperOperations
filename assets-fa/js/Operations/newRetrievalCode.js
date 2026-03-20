const NewRetrievalCode = {
    template: `
        <div>
            <!-- Input row: type dropdown + contract autocomplete + insert button -->
            <div class="row g-2 align-items-end mb-3">
                <div class="col-auto">
                    <label class="form-label mb-1">Tipo</label>
                    <select class="form-select" v-model="type" @change="onTypeChange">
                        <option value="T">Riscatto Totale</option>
                        <option value="P">Riscatto Parziale</option>
                    </select>
                </div>
                <div class="col" style="position: relative;">
                    <label class="form-label mb-1">Numero Contratto</label>
                    <input
                        type="text"
                        class="form-control"
                        placeholder="Cerca contratto..."
                        v-model="contractNumber"
                        @input="onInput"
                        @blur="hideSuggestionsDelayed"
                        autocomplete="off"
                    />
                    <ul
                        v-if="showSuggestions && suggestions.length > 0"
                        class="list-group"
                        style="position: absolute; z-index: 1000; width: 100%; max-height: 220px; overflow-y: auto; top: 100%; left: 0;"
                    >
                        <li
                            v-for="s in suggestions"
                            :key="s"
                            class="list-group-item list-group-item-action"
                            style="cursor: pointer;"
                            @mousedown.prevent="selectSuggestion(s)"
                        >{{ s }}</li>
                    </ul>
                </div>
                <div class="col-auto">
                    <label class="form-label mb-1 d-block">&nbsp;</label>
                    <button
                        class="btn btn-primary"
                        :disabled="!canInsert"
                        @click="insertCode"
                    >
                        <i class="bi bi-plus-circle"></i> Inserisci
                    </button>
                </div>
            </div>

            <!-- Limit warning -->
            <div v-if="limitReached" class="alert alert-warning d-flex align-items-center" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <span>{{ limitMessage }}</span>
            </div>

            <!-- Preview section -->
            <div v-if="previewCode && !limitReached" class="alert alert-info mb-3">
                <strong>Anteprima codice:</strong> {{ previewCode }}
                <span v-if="nextN !== null" class="ms-2 text-muted">(progressivo: {{ nextN }})</span>
            </div>

            <!-- Existing codes table -->
            <div v-if="existingCodes.length > 0">
                <h6 class="mb-2">Codici esistenti</h6>
                <table class="table table-sm table-bordered table-striped">
                    <thead class="table-dark">
                        <tr>
                            <th>Data inserimento</th>
                            <th>Codice</th>
                            <th>Tipo operazione</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, idx) in existingCodes" :key="idx">
                            <td>{{ row.insert_date }}</td>
                            <td>{{ row.code }}</td>
                            <td>{{ row.operation_type_code }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-else-if="contractNumber && !showSuggestions" class="text-muted small">
                Nessun codice esistente per questo contratto.
            </div>

            <!-- Confirmation modal -->
            <div class="modal fade" id="confirmInsertModal" tabindex="-1" data-bs-backdrop="static">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Conferma inserimento</h5>
                            <button type="button" class="btn-close" @click="closeConfirmModal"></button>
                        </div>
                        <div class="modal-body">
                            <p>Stai per inserire il seguente codice di recupero:</p>
                            <ul>
                                <li><strong>Contratto:</strong> {{ contractNumber }}</li>
                                <li><strong>Tipo:</strong> {{ type === 'T' ? 'Riscatto Totale' : 'Riscatto Parziale' }}</li>
                                <li v-if="previewCode"><strong>Codice:</strong> {{ previewCode }}</li>
                            </ul>
                            <p>Confermi l'operazione?</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" @click="closeConfirmModal">Annulla</button>
                            <button type="button" class="btn btn-primary" @click="confirmInsert">
                                <i class="bi bi-check-circle"></i> Conferma
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `,

    data() {
        return {
            type: 'T',
            contractNumber: '',
            suggestions: [],
            showSuggestions: false,
            existingCodes: [],
            previewCode: '',
            nextN: null,
            limitReached: false,
            limitMessage: '',
            debounceTimer: null,
            confirmModal: null
        };
    },

    computed: {
        canInsert() {
            return this.contractNumber.trim() !== '' && this.type !== '' && !this.limitReached;
        }
    },

    mounted() {
        document.addEventListener('modal-reopen', this.onModalReopen);
    },

    beforeUnmount() {
        document.removeEventListener('modal-reopen', this.onModalReopen);
    },

    methods: {
        onInput() {
            if (this.debounceTimer) {
                clearTimeout(this.debounceTimer);
            }
            const val = this.contractNumber.trim();
            if (val.length < 2) {
                this.suggestions = [];
                this.showSuggestions = false;
                return;
            }
            this.debounceTimer = setTimeout(() => {
                this.fetchSuggestions();
            }, 2000);
        },

        fetchSuggestions() {
            const q = this.contractNumber.trim();
            if (q.length < 2) return;
            fetch('./src/model/ajax/ajax_newRetrievalCode_view.php?action=search&q=' + encodeURIComponent(q))
                .then(res => res.json())
                .then(json => {
                    if (json.success) {
                        this.suggestions = (json.data || []).slice(0, 10);
                        this.showSuggestions = this.suggestions.length > 0;
                    } else {
                        showErrorToast(json.message || 'Errore nella ricerca contratti');
                    }
                })
                .catch(() => showErrorToast('Errore di connessione al server'));
        },

        selectSuggestion(value) {
            this.contractNumber = value;
            this.showSuggestions = false;
            this.suggestions = [];
            this.fetchExistingCodes();
            this.fetchPreview();
        },

        hideSuggestionsDelayed() {
            setTimeout(() => {
                this.showSuggestions = false;
            }, 200);
        },

        fetchExistingCodes() {
            fetch('./src/model/ajax/ajax_newRetrievalCode_view.php?action=tabella&bper_contract_number=' + encodeURIComponent(this.contractNumber))
                .then(res => res.json())
                .then(json => {
                    if (json.success) {
                        this.existingCodes = json.data || [];
                    } else {
                        showErrorToast(json.message || 'Errore nel caricamento della tabella');
                    }
                })
                .catch(() => showErrorToast('Errore di connessione al server'));
        },

        fetchPreview() {
            this.previewCode = '';
            this.nextN = null;
            this.limitReached = false;
            this.limitMessage = '';

            fetch(
                './src/model/ajax/ajax_newRetrievalCode_view.php?action=calc' +
                '&bper_contract_number=' + encodeURIComponent(this.contractNumber) +
                '&type=' + encodeURIComponent(this.type)
            )
                .then(res => res.json())
                .then(json => {
                    if (json.success) {
                        this.previewCode = json.data.code || '';
                        this.nextN = json.data.next_n !== undefined ? json.data.next_n : null;
                        this.limitReached = false;
                        this.limitMessage = '';
                    } else {
                        this.limitReached = true;
                        this.limitMessage = json.message || 'Limite raggiunto per questo contratto e tipo operazione';
                        this.previewCode = '';
                        this.nextN = null;
                    }
                })
                .catch(() => showErrorToast('Errore di connessione al server'));
        },

        onTypeChange() {
            if (this.contractNumber.trim() !== '') {
                this.fetchPreview();
            }
        },

        insertCode() {
            if (!this.canInsert) return;
            if (!this.confirmModal) {
                const modalEl = document.getElementById('confirmInsertModal');
                this.confirmModal = new bootstrap.Modal(modalEl);
            }
            this.confirmModal.show();
        },

        closeConfirmModal() {
            if (this.confirmModal) {
                this.confirmModal.hide();
            }
        },

        confirmInsert() {
            const body = new URLSearchParams({
                bper_contract_number: this.contractNumber,
                type: this.type
            });

            fetch('./src/model/ajax/ajax_newRetrievalCode_save.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString()
            })
                .then(res => res.json())
                .then(json => {
                    if (json.success) {
                        this.closeConfirmModal();
                        this.fetchExistingCodes();
                        this.fetchPreview();
                    } else {
                        showErrorToast(json.message || 'Errore durante l\'inserimento');
                    }
                })
                .catch(() => showErrorToast('Errore di connessione al server'));
        },

        onModalReopen(event) {
            if (event.detail && event.detail.operation === 'newRetrievalCode') {
                if (this.contractNumber.trim() !== '') {
                    this.fetchExistingCodes();
                }
            }
        }
    }
};
