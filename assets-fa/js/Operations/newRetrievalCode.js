window.NewRetrievalCode = {
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
                            :key="s.bper_policy_number"
                            class="list-group-item list-group-item-action"
                            style="cursor: pointer;"
                            @mousedown.prevent="selectSuggestion(s)"
                        >{{ s.bper_policy_number }}<span v-if="s.company_policy_number && s.company_policy_number !== s.bper_policy_number" class="text-muted ms-1">({{ s.company_policy_number }})</span></li>
                    </ul>
                </div>
                <div class="col-auto">
                    <label class="form-label mb-1 d-block">Anteprima</label>
                    <div class="form-control-plaintext fw-bold" style="min-width: 120px;">{{ formattedPreview || '\u2014' }}</div>
                </div>
                <div class="col-auto">
                    <label class="form-label mb-1 d-block">&nbsp;</label>
                    <button class="btn btn-primary btn-lg d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; border-radius: 12px;" :disabled="!canInsert" @click="insertCode" title="Inserisci">
                        <i class="bi bi-plus-lg fs-4"></i>
                    </button>
                </div>
            </div>

            <!-- Limit warning -->
            <div v-if="limitReached" class="alert alert-warning d-flex align-items-center" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <span>{{ limitMessage }}</span>
            </div>

            <!-- Existing codes table -->
            <div v-if="contractNumber && !showSuggestions">
                <h6 class="mb-2">{{ existingCodes.length > 0 ? 'Codici esistenti' : 'Ancora nessun codice per questo contratto.' }}</h6>
                <div class="table-card">
                    <table class="table table-hover align-middle border-0">
                        <thead>
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
        },
        formattedPreview() {
            const code = this.previewCode;
            if (!code || code.length < 3) return code || '';
            // Format: "RT1234561" → "R T 123456 1"
            // first char (R), space, second char (T/P), space, middle chars, space, last char
            const first = code.charAt(0);
            const second = code.charAt(1);
            const last = code.charAt(code.length - 1);
            const middle = code.slice(2, code.length - 1);
            return first + ' ' + second + ' ' + middle + ' ' + last;
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
            fetch('./model/ajax/ajax_newRetrievalCode_view.php?action=search&q=' + encodeURIComponent(q))
                .then(res => handleAjaxResponse(res, 'Errore nella ricerca contratti'))
                .then(json => {
                    this.suggestions = (json.data || []).slice(0, 10);
                    this.showSuggestions = this.suggestions.length > 0;
                })
                .catch(err => handleNetworkError(err, 'ricerca contratti'));
        },

        selectSuggestion(s) {
            this.contractNumber = s.bper_policy_number;
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
            fetch('./model/ajax/ajax_newRetrievalCode_view.php?action=tabella&bper_contract_number=' + encodeURIComponent(this.contractNumber))
                .then(res => handleAjaxResponse(res, 'Errore nel caricamento della tabella'))
                .then(json => {
                    this.existingCodes = json.data || [];
                })
                .catch(err => handleNetworkError(err, 'caricamento codici esistenti'));
        },

        fetchPreview() {
            this.previewCode = '';
            this.nextN = null;
            this.limitReached = false;
            this.limitMessage = '';

            fetch(
                './model/ajax/ajax_newRetrievalCode_view.php?action=calc' +
                '&bper_contract_number=' + encodeURIComponent(this.contractNumber) +
                '&type=' + encodeURIComponent(this.type)
            )
                .then(res => {
                    if (!res.ok) {
                        return res.json().catch(() => ({})).then(json => {
                            showErrorToast(
                                (json && json.message) || 'Errore nel calcolo anteprima (HTTP ' + res.status + ')',
                                (json && json.exception) || null
                            );
                            var err = new Error('calc failed');
                            err._toastShown = true;
                            return Promise.reject(err);
                        });
                    }
                    return res.json();
                })
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
                .catch(err => handleNetworkError(err, 'calcolo anteprima codice'));
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

            fetch('./model/ajax/ajax_newRetrievalCode_save.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString()
            })
                .then(res => handleAjaxResponse(res, 'Errore durante l\'inserimento'))
                .then(() => {
                    this.closeConfirmModal();
                    this.fetchExistingCodes();
                    this.fetchPreview();
                })
                .catch(err => handleNetworkError(err, 'inserimento codice recupero'));
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
