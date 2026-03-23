window.ResetDocumentState = {
    template: `
        <div>
            <!-- Toolbar: filter + hide-completed + page size -->
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <div class="d-flex align-items-center gap-3">
                    <input
                        v-model="filterText"
                        type="text"
                        class="form-control form-control-sm w-auto"
                        placeholder="Filtra..."
                        @input="currentPage = 1"
                    />
                </div>
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
            <div class="table-card">
                <div class="table-responsive">
                    <table class="table table-hover align-middle border-0">
                        <thead>
                            <tr>
                                <th style="width:3rem;"></th>
                                <th
                                    v-for="col in columns"
                                    :key="col"
                                    @click="toggleSort(col)"
                                    style="cursor:pointer; user-select:none;"
                                >
                                    {{ col }}
                                    <i v-if="sortColumn === col" :class="sortAsc ? 'bi bi-sort-up' : 'bi bi-sort-down'"></i>
                                    <i v-else class="bi bi-arrow-down-up opacity-25"></i>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="paginatedRows.length === 0">
                                <td :colspan="columns.length + 1" class="text-center text-muted py-4">
                                    Nessun dato disponibile
                                </td>
                            </tr>
                            <tr
                                v-for="(row, idx) in paginatedRows"
                                :key="idx"
                            >
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
                                <td v-for="col in columns" :key="col">{{ row[col] }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="table-card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <small class="text-muted">
                        Pagina {{ currentPage }} di {{ totalPages }} &mdash; {{ filteredRows.length }} righe
                    </small>
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item" :class="{ disabled: currentPage === 1 }">
                                <a class="page-link" href="#" @click.prevent="currentPage > 1 && currentPage--">Precedente</a>
                            </li>
                            <li
                                v-for="page in visiblePages"
                                :key="page"
                                class="page-item"
                                :class="{ active: page === currentPage }"
                            >
                                <a class="page-link" href="#" @click.prevent="currentPage = page">{{ page }}</a>
                            </li>
                            <li class="page-item" :class="{ disabled: currentPage === totalPages }">
                                <a class="page-link" href="#" @click.prevent="currentPage < totalPages && currentPage++">Successivo</a>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>

            <!-- Conferma reset (Vue-driven, no nested Bootstrap modal) -->
            <div v-if="showConfirmDialog" id="modal-resetDocumentState" class="position-fixed top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center" style="z-index: 1060; background: rgba(0,0,0,0.5);">
                <div class="card shadow" style="min-width: 350px; max-width: 500px;">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Conferma Reset Stato</h5>
                        <button type="button" class="btn-close" @click="closeConfirmModal"></button>
                    </div>
                    <div class="card-body">
                        <p>Sei sicuro di voler resettare lo stato del documento selezionato?</p>
                    </div>
                    <div class="card-footer text-end">
                        <button type="button" class="btn btn-secondary me-2" @click="closeConfirmModal">Annulla</button>
                        <button type="button" class="btn btn-warning" @click="confirmChange">
                            <i class="bi bi-clock-history me-1"></i> Conferma Reset
                        </button>
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
            showConfirmDialog: false
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
        },

        visiblePages() {
            const total = this.totalPages;
            const current = this.currentPage;
            const windowSize = 10;

            let start = Math.max(1, current - Math.floor(windowSize / 2));
            let end = start + windowSize - 1;

            if (end > total) {
                end = total;
                start = Math.max(1, end - windowSize + 1);
            }

            const pages = [];
            for (let i = start; i <= end; i++) {
                pages.push(i);
            }
            return pages;
        }
    },

    methods: {
        fetchData() {
            fetch('./model/ajax/ajax_resetDocumentState_view.php?action=tabella')
                .then(res => handleAjaxResponse(res, 'Errore nel caricamento dei dati'))
                .then(json => {
                    const data = json.data;
                    if (!Array.isArray(data) || data.length === 0) {
                        this.allRows = [];
                        this.columns = [];
                        return;
                    }
                    this.allRows = data;
                    const excluded = ['id', 'IBAN', 'LGRP', 'Codice Rapporto'];
                    this.columns = Object.keys(data[0]).filter(k => !excluded.includes(k));
                    this.currentPage = 1;
                })
                .catch(err => handleNetworkError(err, 'caricamento reset stato documento'));
        },

        isPending(row) {
            return row['Doc. PENDING'] > 0;
        },

        changeStatus(id) {
            const row = this.allRows.find(r => r['id'] === id);
            if (!row || !this.isPending(row)) return;

            this.changeTargetId = id;
            this.showConfirmDialog = true;
        },

        closeConfirmModal() {
            this.showConfirmDialog = false;
            this.changeTargetId = null;
        },

        confirmChange() {
            if (this.changeTargetId === null) return;

            const formData = new URLSearchParams();
            formData.append('id', this.changeTargetId);

            fetch('./model/ajax/ajax_resetDocumentState_save.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString()
            })
                .then(res => handleAjaxResponse(res, 'Errore durante il reset dello stato'))
                .then(() => {
                    this.fetchData();
                })
                .catch(err => handleNetworkError(err, 'reset stato documento'))
                .finally(() => {
                    this.closeConfirmModal();
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
