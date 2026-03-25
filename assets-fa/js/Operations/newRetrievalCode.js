window.NewRetrievalCode = {
    template: `
        <div>
            <!-- Input row: type dropdown + contract autocomplete + preview + insert icon -->
            <div class="d-flex align-items-end mb-3 gap-2">
                <div>
                    <label class="form-label mb-1">Tipo</label>
                    <select class="form-select" style="height: 38px;" v-model="type" @change="onTypeChange">
                        <option value="T">Riscatto Totale</option>
                        <option value="P">Riscatto Parziale</option>
                    </select>
                </div>
                <div style="position: relative; width: 220px; min-width: 180px;">
                    <label class="form-label mb-1">Numero Contratto</label>
                    <input
                        type="text"
                        class="form-control"
                        style="height: 38px;"
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
                <div class="ms-3">
                    <label class="form-label mb-1 d-block">Anteprima</label>
                    <div class="d-flex align-items-center fw-bold text-primary" style="height: 38px; font-size: 1.05rem; letter-spacing: 0.04em; min-width: 130px;">{{ formattedPreview || '\u2014' }}</div>
                </div>
                <div class="ms-3">
                    <label class="form-label mb-1 d-block">&nbsp;</label>
                    <i
                        class="bi nrc-insert-icon"
                        :class="canInsert ? 'bi-plus-circle-fill' : 'bi-plus-circle'"
                        :style="{ opacity: canInsert ? 1 : 0.35, cursor: canInsert ? 'pointer' : 'not-allowed' }"
                        @click="insertCode"
                        title="Inserisci codice"
                    ></i>
                </div>
            </div>

            <!-- Limit warning -->
            <div v-if="limitReached" class="alert alert-warning d-flex align-items-center" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <span>{{ limitMessage }}</span>
            </div>

            <!-- Existing codes table -->
            <div v-if="contractNumber && !showSuggestions">
                <div v-if="existingCodes.length === 0" class="text-muted fst-italic mt-2">Ancora nessun codice per questo contratto.</div>
                <div v-else class="table-card nrc-table-card">
                    <div class="nrc-table-tab">Codici esistenti</div>
                    <table class="table table-hover align-middle border-0">
                        <thead>
                            <tr>
                                <th>Data inserimento</th>
                                <th>Codice</th>
                                <th>Tipo operazione</th>
                                <th style="width: 3rem; text-align: center;">Stato</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, idx) in existingCodes" :key="idx" :class="{ 'row-unconsumed': isUnconsumed(row) }">
                                <td>{{ row.insert_date }}</td>
                                <td :class="{ 'code-unconsumed': isUnconsumed(row) }">{{ row.code }}</td>
                                <td>{{ row.operation_type_code === '_RISTO' ? 'Riscatto Totale' : 'Riscatto Parziale' }}</td>
                                <td class="text-center">
                                    <i v-if="isUnconsumed(row)" class="bi bi-check-circle-fill text-success" title="Valido"></i>
                                    <i v-else class="bi bi-dash-circle text-muted" title="Consumato"></i>
                                </td>
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
                            <p>Stai per inserire il seguente codice di riscatto:</p>
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

        isUnconsumed(row) {
            return row.consumed === false || row.consumed === 'f';
        },

        operationTypeCodeForCurrentType() {
            return this.type === 'T' ? '_RISTO' : '_RISPA';
        },

        hasUnconsumedCodeForType() {
            const opCode = this.operationTypeCodeForCurrentType();
            return this.existingCodes.some(row =>
                (row.consumed === false || row.consumed === 'f') &&
                row.operation_type_code === opCode
            );
        },

        insertCode() {
            if (!this.canInsert) return;

            if (this.hasUnconsumedCodeForType()) {
                const typeLabel = this.type === 'T' ? 'Riscatto Totale' : 'Riscatto Parziale';
                if (!confirm('Esiste già un codice valido per il contratto ' + this.contractNumber + ' e tipo operazione ' + typeLabel + ', sei sicuro di volerne aggiungere un altro?')) {
                    return;
                }
            }

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
                .catch(err => handleNetworkError(err, 'inserimento codice riscatto'));
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
