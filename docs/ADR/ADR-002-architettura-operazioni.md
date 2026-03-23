# ADR-002: Architettura delle operazioni

**Stato**: Accettato
**Data**: 2026-03-20
**Contesto**: Come strutturare le operazioni per garantire estensibilita e riuso

## Decisione

Ogni operazione e composta da:
1. **Classe PHP** (`src/model/Operations/`) — implementa `OperationInterface`, estende `AbstractOperation`
2. **Repository PHP** (`src/model/Operations/`) — accesso al database separato dalla logica di business
3. **File JS** (`assets-fa/js/Operations/`) — App Vue Options API auto-consistente
4. **File Ajax** (`src/model/ajax/`) — endpoint di lettura (`_view`) e scrittura (`_save`)

La scoperta delle operazioni e automatica: il bootstrapping scansiona la cartella e istanzia le classi che implementano l'interfaccia.

## Alternative considerate

- **Registro manuale**: un file di configurazione elenca le operazioni disponibili. Scartato perche richiede una modifica aggiuntiva per ogni nuova operazione.
- **Monolite Vue**: tutte le operazioni in un unico file JS. Scartato perche impedisce il riuso in contesti diversi.

## Motivazione

- **Estensibilita OCP**: aggiungere un'operazione = creare 2 file. Nessuna modifica al codice esistente.
- **Isolamento**: ogni operazione e una Vue app indipendente, riusabile altrove.
- **Separazione responsabilita**: Operation (business logic) + Repository (DB access) + JS (UI).

## Conseguenze

- Ogni operazione deve rispettare il contratto `OperationInterface`.
- L'audit log e gestito trasparentemente da `AjaxResponseHelper::success()` quando riceve un'istanza di `AbstractOperation`.
- I file Ajax seguono una naming convention rigida (`ajax_<nome>_view.php` / `_save.php`).
