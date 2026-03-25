// Note: modal width is handled by the parent app.js which opens this component inside a modal-xl dialog.
window.ForceAnnulment = {
    template: `
        <div>
            <!-- Filtro, nascondi completati e selezione dimensione pagina -->
            <div class="row mb-3 g-2 align-items-center">
                <div class="col-md-5">
                    <input
                        type="text"
                        class="form-control"
                        placeholder="Cerca..."
                        v-model="filterText"
                        @input="currentPage = 1"
                    />
                </div>
                <div class="col-md-3 ms-auto">
                    <select class="form-select" v-model="pageSize" @change="currentPage = 1">
                        <option :value="15">15 per pagina</option>
                        <option :value="50">50 per pagina</option>
                        <option :value="100">100 per pagina</option>
                    </select>
                </div>
            </div>

            <!-- Tabella dinamica -->
            <div class="table-card">
                <div class="table-responsive">
                    <table class="table table-hover align-middle border-0">
                        <thead>
                            <tr>
                                <th style="width:3rem;"></th>
                                <th
                                    v-for="col in columns"
                                    :key="col"
                                    style="cursor: pointer; user-select: none;"
                                    @click="toggleSort(col)"
                                >
                                    {{ col }}
                                    <i v-if="sortColumn === col" :class="sortAsc ? 'bi bi-sort-up' : 'bi bi-sort-down'"></i>
                                    <i v-else class="bi bi-arrow-down-up opacity-25"></i>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="paginatedRows.length === 0">
                                <td :colspan="columns.length + 1" class="text-center text-muted py-4">Nessun dato disponibile</td>
                            </tr>
                            <tr v-for="(row, rowIndex) in paginatedRows" :key="rowIndex">
                                <td class="text-center">
                                    <i
                                        v-if="row['Stato'] !== 'CANCELLED'"
                                        class="bi bi-trash action-icon icon-danger"
                                        @click="deleteOperation(row.id)"
                                        title="Elimina"
                                    ></i>
                                </td>
                                <td v-for="col in columns" :key="col">{{ row[col] }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="table-card-footer d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2">
                    <small class="text-muted">
                        Pagina {{ currentPage }} di {{ totalPages }} &mdash; {{ filteredRows.length }} righe
                    </small>
                    <nav v-if="totalPages > 1">
                        <ul class="pagination pagination-sm justify-content-center mb-0">
                            <li class="page-item" :class="{ disabled: currentPage === 1 }">
                                <button class="page-link" @click="currentPage--" :disabled="currentPage === 1">Precedente</button>
                            </li>
                            <li
                                v-for="page in visiblePages"
                                :key="page"
                                class="page-item"
                                :class="{ active: currentPage === page }"
                            >
                                <button class="page-link" @click="currentPage = page">{{ page }}</button>
                            </li>
                            <li class="page-item" :class="{ disabled: currentPage === totalPages }">
                                <button class="page-link" @click="currentPage++" :disabled="currentPage === totalPages">Successivo</button>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>

            <!-- Conferma eliminazione (Vue-driven, no nested Bootstrap modal) -->
            <div v-if="showDeleteConfirm" id="deleteConfirmModal" class="position-fixed top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center" style="z-index: 1060; background: rgba(0,0,0,0.5);">
                <div class="card shadow" style="min-width: 350px; max-width: 500px;">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Conferma eliminazione</h5>
                        <button type="button" class="btn-close" @click="closeDeleteModal"></button>
                    </div>
                    <div class="card-body">
                        Sei sicuro di voler eliminare questa operazione? L'azione non è reversibile.
                    </div>
                    <div class="card-footer text-end">
                        <button type="button" class="btn btn-secondary me-2" @click="closeDeleteModal">Annulla</button>
                        <button type="button" class="btn btn-danger" @click="confirmDelete">Elimina</button>
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
            pageSize: 15,
            currentPage: 1,
            deleteTargetId: null,
            showDeleteConfirm: false
        };
    },

    computed: {
        filteredRows() {
            if (!this.filterText.trim()) {
                return this.allRows;
            }
            const search = this.filterText.toLowerCase();
            return this.allRows.filter(row =>
                this.columns.some(col =>
                    String(row[col] ?? '').toLowerCase().includes(search)
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
                const valA = a[col] ?? '';
                const valB = b[col] ?? '';
                if (valA < valB) return asc ? -1 : 1;
                if (valA > valB) return asc ? 1 : -1;
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

            if (total <= windowSize) {
                return Array.from({ length: total }, (_, i) => i + 1);
            }

            let start = Math.max(1, current - Math.floor(windowSize / 2));
            let end = start + windowSize - 1;

            if (end > total) {
                end = total;
                start = Math.max(1, end - windowSize + 1);
            }

            return Array.from({ length: end - start + 1 }, (_, i) => start + i);
        }
    },

    mounted() {
        this.fetchData();
        document.addEventListener('modal-reopen', this.onModalReopen);
    },

    beforeUnmount() {
        document.removeEventListener('modal-reopen', this.onModalReopen);
    },

    methods: {
        fetchData() {
            const excludedColumns = ['IBAN', 'LGRP', 'Codice Rapporto'];
            fetch('./model/ajax/ajax_forceAnnulment_view.php?action=tabella')
                .then(res => handleAjaxResponse(res, 'Errore nel caricamento dei dati'))
                .then(json => {
                    this.allRows = json.data;
                    this.columns = json.data.length > 0
                        ? Object.keys(json.data[0]).filter(k => k !== 'id' && !excludedColumns.includes(k))
                        : [];
                    this.currentPage = 1;
                })
                .catch(err => handleNetworkError(err, 'caricamento annullamenti forzati'));
        },

        toggleSort(col) {
            if (this.sortColumn === col) {
                this.sortAsc = !this.sortAsc;
            } else {
                this.sortColumn = col;
                this.sortAsc = true;
            }
        },

        deleteOperation(id) {
            this.deleteTargetId = id;
            this.showDeleteConfirm = true;
        },

        closeDeleteModal() {
            this.showDeleteConfirm = false;
            this.deleteTargetId = null;
        },

        confirmDelete() {
            if (this.deleteTargetId === null) return;

            const body = new URLSearchParams();
            body.append('id', this.deleteTargetId);

            fetch('./model/ajax/ajax_forceAnnulment_save.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString()
            })
                .then(res => handleAjaxResponse(res, 'Errore durante l\'eliminazione'))
                .then(() => {
                    this.closeDeleteModal();
                    this.fetchData();
                })
                .catch(err => handleNetworkError(err, 'eliminazione annullamento forzato'));
        },

        onModalReopen() {
            this.fetchData();
        }
    }
};
