const ForceAnnulment = {
    template: `
        <div>
            <!-- Filtro e selezione dimensione pagina -->
            <div class="row mb-3 g-2 align-items-center">
                <div class="col-md-6">
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
                        <option :value="20">20 per pagina</option>
                        <option :value="50">50 per pagina</option>
                        <option :value="100">100 per pagina</option>
                    </select>
                </div>
            </div>

            <!-- Tabella dinamica -->
            <div class="table-responsive">
                <table class="table table-bordered table-hover table-sm">
                    <thead class="table-dark">
                        <tr>
                            <th
                                v-for="col in columns"
                                :key="col"
                                style="cursor: pointer; user-select: none;"
                                @click="toggleSort(col)"
                            >
                                {{ col }}
                                <span v-if="sortColumn === col">
                                    <i :class="sortAsc ? 'bi bi-sort-up' : 'bi bi-sort-down'"></i>
                                </span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="paginatedRows.length === 0">
                            <td :colspan="columns.length" class="text-center text-muted">Nessun dato disponibile</td>
                        </tr>
                        <tr v-for="(row, rowIndex) in paginatedRows" :key="rowIndex">
                            <td
                                v-for="(col, colIndex) in columns"
                                :key="col"
                            >
                                <span v-if="colIndex === 0">
                                    <i
                                        class="bi bi-trash text-danger me-2"
                                        style="cursor: pointer;"
                                        @click="deleteOperation(row.id)"
                                        title="Elimina"
                                    ></i>
                                    {{ row[col] }}
                                </span>
                                <span v-else>{{ row[col] }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Paginazione -->
            <nav v-if="totalPages > 1">
                <ul class="pagination pagination-sm justify-content-center flex-wrap">
                    <li class="page-item" :class="{ disabled: currentPage === 1 }">
                        <button class="page-link" @click="currentPage--" :disabled="currentPage === 1">Precedente</button>
                    </li>
                    <li
                        v-for="page in totalPages"
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

            <!-- Modale di conferma eliminazione -->
            <div class="modal fade" id="deleteConfirmModal" tabindex="-1" data-bs-backdrop="static">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Conferma eliminazione</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            Sei sicuro di voler eliminare questa operazione? L'azione non è reversibile.
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                            <button type="button" class="btn btn-danger" @click="confirmDelete">Elimina</button>
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
            deleteTargetId: null,
            deleteModal: null
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
            return Math.ceil(this.filteredRows.length / this.pageSize);
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
            fetch('./src/model/ajax/ajax_forceAnnulment_view.php?action=tabella')
                .then(res => res.json())
                .then(json => {
                    if (json.success) {
                        this.allRows = json.data;
                        this.columns = json.data.length > 0
                            ? Object.keys(json.data[0]).filter(k => k !== 'id')
                            : [];
                        this.currentPage = 1;
                    } else {
                        showErrorToast(json.message || 'Errore nel caricamento dei dati');
                    }
                })
                .catch(() => showErrorToast('Errore di connessione al server'));
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
            const modalEl = document.getElementById('deleteConfirmModal');
            if (!this.deleteModal) {
                this.deleteModal = new bootstrap.Modal(modalEl);
            }
            this.deleteModal.show();
        },

        confirmDelete() {
            if (this.deleteTargetId === null) return;

            const body = new URLSearchParams();
            body.append('id', this.deleteTargetId);

            fetch('./src/model/ajax/ajax_forceAnnulment_save.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString()
            })
                .then(res => res.json())
                .then(json => {
                    if (json.success) {
                        this.deleteTargetId = null;
                        if (this.deleteModal) {
                            this.deleteModal.hide();
                        }
                        this.fetchData();
                    } else {
                        showErrorToast(json.message || 'Errore durante l\'eliminazione');
                    }
                })
                .catch(() => showErrorToast('Errore di connessione al server'));
        },

        onModalReopen() {
            this.fetchData();
        }
    }
};
