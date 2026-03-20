const ResetDocumentState = {
    template: `
        <div>
            <!-- Toolbar: filter + page size -->
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <input
                    v-model="filterText"
                    type="text"
                    class="form-control form-control-sm w-auto"
                    placeholder="Filtra..."
                    @input="currentPage = 1"
                />
                <div class="d-flex align-items-center gap-2">
                    <label class="mb-0 small">Righe per pagina:</label>
                    <select v-model.number="pageSize" class="form-select form-select-sm w-auto" @change="currentPage = 1">
                        <option :value="10">10</option>
                        <option :value="20">20</option>
                        <option :value="50">50</option>
                        <option :value="100">100</option>
                    </select>
                </div>
            </div>

            <!-- Table -->
            <div class="table-responsive">
                <table class="table table-bordered table-hover table-sm align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th style="width:3rem;"></th>
                            <th
                                v-for="col in columns"
                                :key="col"
                                @click="toggleSort(col)"
                                style="cursor:pointer; user-select:none;"
                            >
                                {{ col }}
                                <i
                                    v-if="sortColumn === col"
                                    :class="sortAsc ? 'bi bi-sort-up' : 'bi bi-sort-down'"
                                ></i>
                                <i v-else class="bi bi-arrow-down-up text-secondary opacity-50"></i>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-if="paginatedRows.length === 0"
                        >
                            <td :colspan="columns.length + 1" class="text-center text-muted py-4">
                                Nessun dato disponibile
                            </td>
                        </tr>
                        <tr
                            v-for="(row, idx) in paginatedRows"
                            :key="idx"
                            :class="isPending(row) ? '' : 'bg-light'"
                            :style="isPending(row) ? 'background-color: #fff;' : ''"
                        >
                            <!-- Status icon column -->
                            <td class="text-center">
                                <i
                                    v-if="isPending(row)"
                                    class="bi bi-clock-history text-warning fs-5"
                                    style="cursor:pointer;"
                                    title="Reset stato documento"
                                    @click="changeStatus(row['id'])"
                                ></i>
                                <i
                                    v-else
                                    class="bi bi-x-circle-fill text-danger fs-5"
                                    title="Nessun documento pending"
                                ></i>
                            </td>
                            <!-- Data columns -->
                            <td v-for="col in columns" :key="col">{{ row[col] }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="d-flex justify-content-between align-items-center mt-2 flex-wrap gap-2">
                <small class="text-muted">
                    Pagina {{ currentPage }} di {{ totalPages }} &mdash; {{ filteredRows.length }} righe
                </small>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item" :class="{ disabled: currentPage === 1 }">
                            <a class="page-link" href="#" @click.prevent="currentPage = 1">&laquo;</a>
                        </li>
                        <li class="page-item" :class="{ disabled: currentPage === 1 }">
                            <a class="page-link" href="#" @click.prevent="currentPage > 1 && currentPage--">&lsaquo;</a>
                        </li>
                        <li class="page-item" :class="{ disabled: currentPage === totalPages }">
                            <a class="page-link" href="#" @click.prevent="currentPage < totalPages && currentPage++">&rsaquo;</a>
                        </li>
                        <li class="page-item" :class="{ disabled: currentPage === totalPages }">
                            <a class="page-link" href="#" @click.prevent="currentPage = totalPages">&raquo;</a>
                        </li>
                    </ul>
                </nav>
            </div>

            <!-- Confirm modal -->
            <div class="modal fade" id="modal-resetDocumentState" tabindex="-1" data-bs-backdrop="static">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Conferma Reset Stato</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p>Sei sicuro di voler resettare lo stato del documento selezionato?</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                            <button type="button" class="btn btn-warning" @click="confirmChange">
                                <i class="bi bi-clock-history me-1"></i> Conferma Reset
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `,

    data() {
        return {
            allRows: [],
            columns: [],
            filterText: '',
            sortColumn: '',
            sortAsc: true,
            pageSize: 20,
            currentPage: 1,
            changeTargetId: null,
            _confirmModal: null
        };
    },

    computed: {
        filteredRows() {
            if (!this.filterText.trim()) {
                return this.allRows;
            }
            const needle = this.filterText.trim().toLowerCase();
            return this.allRows.filter(row =>
                this.columns.some(col =>
                    String(row[col] ?? '').toLowerCase().includes(needle)
                )
            );
        },

        sortedRows() {
            if (!this.sortColumn) {
                return this.filteredRows;
            }
            const col = this.sortColumn;
            const asc = this.sortAsc;
            return [...this.filteredRows].sort((a, b) => {
                const va = a[col] ?? '';
                const vb = b[col] ?? '';
                if (va < vb) return asc ? -1 : 1;
                if (va > vb) return asc ? 1 : -1;
                return 0;
            });
        },

        paginatedRows() {
            const start = (this.currentPage - 1) * this.pageSize;
            return this.sortedRows.slice(start, start + this.pageSize);
        },

        totalPages() {
            return Math.max(1, Math.ceil(this.filteredRows.length / this.pageSize));
        }
    },

    methods: {
        fetchData() {
            fetch('./src/model/ajax/ajax_resetDocumentState_view.php?action=tabella')
                .then(res => res.json())
                .then(data => {
                    if (!Array.isArray(data) || data.length === 0) {
                        this.allRows = [];
                        this.columns = [];
                        return;
                    }
                    this.allRows = data;
                    const excluded = ['id'];
                    this.columns = Object.keys(data[0]).filter(k => !excluded.includes(k));
                    this.currentPage = 1;
                })
                .catch(() => {
                    if (typeof showErrorToast === 'function') {
                        showErrorToast('Errore nel caricamento dei dati');
                    }
                });
        },

        isPending(row) {
            return row['Doc. PENDING'] > 0;
        },

        changeStatus(id) {
            const row = this.allRows.find(r => r['id'] === id);
            if (!row || !this.isPending(row)) return;

            this.changeTargetId = id;

            if (!this._confirmModal) {
                const el = document.getElementById('modal-resetDocumentState');
                if (el) {
                    this._confirmModal = new bootstrap.Modal(el);
                }
            }
            if (this._confirmModal) {
                this._confirmModal.show();
            }
        },

        confirmChange() {
            if (this.changeTargetId === null) return;

            const formData = new URLSearchParams();
            formData.append('id', this.changeTargetId);

            fetch('./src/model/ajax/ajax_resetDocumentState_save.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString()
            })
                .then(res => res.json())
                .then(result => {
                    if (result.success) {
                        this.fetchData();
                    } else {
                        if (typeof showErrorToast === 'function') {
                            showErrorToast(result.message || 'Errore durante il reset dello stato');
                        }
                    }
                })
                .catch(() => {
                    if (typeof showErrorToast === 'function') {
                        showErrorToast('Errore di connessione al server');
                    }
                })
                .finally(() => {
                    if (this._confirmModal) {
                        this._confirmModal.hide();
                    }
                    this.changeTargetId = null;
                });
        },

        toggleSort(col) {
            if (this.sortColumn === col) {
                this.sortAsc = !this.sortAsc;
            } else {
                this.sortColumn = col;
                this.sortAsc = true;
            }
            this.currentPage = 1;
        },

        onModalReopen() {
            this.fetchData();
        }
    },

    mounted() {
        this.fetchData();
        document.addEventListener('modal-reopen', this.onModalReopen);
    },

    beforeUnmount() {
        document.removeEventListener('modal-reopen', this.onModalReopen);
    }
};
