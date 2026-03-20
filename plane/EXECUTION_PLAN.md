# bperOperations — Piano di Esecuzione Multi-Agentico

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implementare l'applicazione bperOperations completa: backend PHP 8.2, frontend Vue 3 CDN, 3 operazioni amministrative, audit trail, infrastruttura di test.

**Architecture:** Architettura a layer con auto-discovery delle operazioni tramite `OperationInterface`. Ogni operazione separa business logic (classe Operation) e accesso dati (Repository). Frontend Vue 3 Options API via CDN con caricamento dinamico degli script. Database PostgreSQL 16 con transazioni atomiche via `TraitTryQuery`.

**Tech Stack:** PHP 8.2 (strict_types), PostgreSQL 16, Vue.js 3 CDN (Options API), Bootstrap 5 CDN, PHPUnit, Playwright.

---

## Mappa delle Fasi e Dipendenze

```
Phase 1: FOUNDATION ──────────────────────────────────────
  Agent 1 (INFRA-FIX)  →  Agent 2 (ARCH-CORE)
                                │
Phase 2: BACKEND ─────────────┬┴┬───────────────┬──────────
  Agent 3 (BE-NRC) ║ Agent 4 (BE-FA) ║ Agent 5 (BE-RDS) ║ Agent 11 (TEST-INFRA)
         │                    │                 │
Phase 3: BOOTSTRAP + AUTOLOADER ──┴─┴─────────┴────────────
  Agent 6 (BOOT+AUTOLOADER)
                                │
Phase 4: FRONTEND ────────────┬─┴─┬──────────────────────────
  Agent 7 (FE-MAIN) → Agent 8 (FE-NRC) ║ Agent 9 (FE-FA) ║ Agent 10 (FE-RDS)
                                │
Phase 5: TEST ────────────────┬─┬─┬────────────────────────
  Agent 12 (TEST-UNIT) ║ Agent 13 (TEST-INT) ║ Agent 14 (TEST-E2E) ║ Agent 15 (TEST-SEC)
```

**Legenda:** `→` = sequenziale, `║` = parallelo

---

## Phase 1: Foundation

### Agent 1 — INFRA-FIX

| Proprietà | Valore |
|-----------|--------|
| **Modello** | `haiku` |
| **Tipo** | Correzione bug e allineamento codice esistente |
| **Dipendenze** | Nessuna |
| **Durata stimata** | 2-3 minuti |

**Docs da leggere:** Nessuno (solo i file da modificare)

**Files:**
- Modify: `lib/autoloader.php`
- Modify: `src/model/Operations/AjaxResponseHelper.php`

**Pre-check:** Verificare che i file infrastrutturali esistano prima di procedere:
- `lib/Database.php` — deve esistere (connessione PDO, senza namespace)
- `lib/TraitTryQuery.php` — deve esistere (trait accesso DB, senza namespace)
- `lib/env_settings.php` — deve esistere (costanti ambiente)
Se uno qualsiasi manca, FERMARSI e segnalare il problema.

**Contesto per l'agente:**

Il file `autoloader.php` ha un bug logico: la guardia `if (defined('AUTOLOADER_LOADED'))` è invertita — dovrebbe essere `if (!defined('AUTOLOADER_LOADED'))`. Inoltre il path di AjaxResponseHelper è sbagliato (`../src/model/AjaxResponseHelper.php` dovrebbe essere `../src/model/Operations/AjaxResponseHelper.php`).

Il file `AjaxResponseHelper.php` ha il namespace errato: `FirstAdvisory\FAWill\model\Settimanale` deve diventare `FirstAdvisory\FAWill\model\Operations`.

- [ ] **Step 0:** Verificare l'esistenza di `lib/Database.php`, `lib/TraitTryQuery.php`, `lib/env_settings.php`. Se mancano, fermarsi e segnalare
- [ ] **Step 1:** Correggere la guardia in `autoloader.php` — da `if (defined('AUTOLOADER_LOADED'))` a `if (!defined('AUTOLOADER_LOADED'))`
- [ ] **Step 2:** Correggere il path di require di AjaxResponseHelper in `autoloader.php` — da `'/../src/model/AjaxResponseHelper.php'` a `'/../src/model/Operations/AjaxResponseHelper.php'`
- [ ] **Step 3:** Cambiare il namespace in `AjaxResponseHelper.php` — da `FirstAdvisory\FAWill\model\Settimanale` a `FirstAdvisory\FAWill\model\Operations`
- [ ] **Step 4:** Verificare che il file `autoloader.php` sia sintatticamente corretto: `php -l lib/autoloader.php`
- [ ] **Step 5:** Commit: `fix: correct autoloader guard and AjaxResponseHelper namespace`

---

### Agent 2 — ARCH-CORE

| Proprietà | Valore |
|-----------|--------|
| **Modello** | `sonnet` |
| **Tipo** | Creazione architettura base (interfaccia, abstract, factory, DTO, audit logger, estensione AjaxResponseHelper) |
| **Dipendenze** | Agent 1 completato |
| **Durata stimata** | 12-15 minuti |

**Docs da leggere:**
- `PROJECT.md` — sezioni 4 (Architettura Operation completa), 5 (Audit Trail), 2.1 (Naming)
- `docs/SCHEMA_REFERENCE.md` — sezione 2.8 (tabella `operation_audit_log`) e sezione 3 (DDL)
- `docs/QUERY_REFERENCE.md` — sezione 4 (Q-LOG-01)

**Files:**
- Create: `src/model/Operations/OperationInterface.php`
- Create: `src/model/Operations/AbstractOperation.php`
- Create: `src/model/Operations/OperationFactory.php`
- Create: `src/model/Operations/AjaxRequest.php`
- Create: `src/model/Operations/OperationAuditLogger.php`
- Modify: `src/model/Operations/AjaxResponseHelper.php` — aggiungere `getRequest()` e logging in `success()`
- Modify: `lib/autoloader.php` — aggiungere require delle nuove classi

**Contesto per l'agente:**

Tutti i file PHP devono avere `declare(strict_types=1)` e namespace `FirstAdvisory\FAWill\model\Operations`.

Le classi esistenti senza namespace (`Database`, `TraitTryQuery`) vanno usate direttamente (no `use` statement, sono globali).

**Architettura a 6 componenti:**

1. **`OperationInterface`** — contratto con 8 metodi: `getName(): string`, `getTitle(): string`, `getDescription(): string`, `getIcon(): string`, `getColor(): string`, `getJsPath(): string`, `isVisible(): bool`, `isEnabled(): bool`

2. **`AbstractOperation`** — classe abstract che `implements OperationInterface`, usa `TraitTryQuery`. Fornisce:
   - `getCurrentUserId(): int` (return 0, placeholder)
   - Implementazione default di `isVisible()` e `isEnabled()` che ritornano `true`
   - **NON contiene alcuna logica di logging** — il logging è responsabilità di AjaxResponseHelper

3. **`OperationFactory`** — punto unico di creazione delle Operation per gli endpoint ajax:
   - `create(string $className): AbstractOperation` — costruisce il FQCN (`FirstAdvisory\FAWill\model\Operations\` + $className), istanzia, verifica `isEnabled()`. Se false: `http_response_code(403)` + throw `\RuntimeException('Operazione non disponibile')`
   - `discoverAll(): array` — scansiona `src/model/Operations/` con `glob()`, per ogni classe che implementa OperationInterface: se `isVisible()` è false la esclude, altrimenti raccoglie name/title/description/icon/color/jsPath + `'enabled' => $instance->isEnabled()`

4. **`AjaxRequest`** — DTO immutabile:
   - Proprietà readonly: `string $method`, `string $action`, `array $params`
   - Metodo `get(string $key, mixed $default = null): mixed` → ritorna `$this->params[$key] ?? $default`
   - Il costruttore riceve `$_SERVER['REQUEST_METHOD']`, `$_GET['action'] ?? ''`, e `array_merge($_GET, $_POST)` (POST sovrascrive GET)

5. **`OperationAuditLogger`** — classe con `use TraitTryQuery`, metodo `log(string $operationName, array $payload, int $userId): void` che esegue la INSERT in `public.operation_audit_log` con `:payload::jsonb`

6. **`AjaxResponseHelper`** (da estendere) — aggiungere:
   - Proprietà statica `private static ?AjaxRequest $currentRequest = null`
   - Metodo statico `getRequest(): AjaxRequest` — crea e memorizza il DTO, lo ritorna
   - Modificare `success(mixed $data, ?AbstractOperation $operation = null): void` — se `$operation` è passato E `self::$currentRequest` esiste: crea `OperationAuditLogger`, chiama `->log($operation->getName(), self::$currentRequest->params, $operation->getCurrentUserId())`

- [ ] **Step 1:** Creare `OperationInterface.php` con 8 metodi (getName, getTitle, getDescription, getIcon, getColor, getJsPath, isVisible, isEnabled)
- [ ] **Step 2:** Verificare sintassi: `php -l src/model/Operations/OperationInterface.php`
- [ ] **Step 3:** Creare `AjaxRequest.php` — DTO con readonly properties e metodo `get()`
- [ ] **Step 4:** Creare `OperationAuditLogger.php` — classe con `use TraitTryQuery`, metodo `log()` con INSERT in `operation_audit_log`
- [ ] **Step 5:** Verificare sintassi: `php -l src/model/Operations/AjaxRequest.php && php -l src/model/Operations/OperationAuditLogger.php`
- [ ] **Step 6:** Creare `AbstractOperation.php` — classe abstract che `implements OperationInterface`, usa `TraitTryQuery`, implementa `getCurrentUserId(): int` (return 0), implementa `isVisible(): bool` (return true) e `isEnabled(): bool` (return true). **Nessuna logica di logging.**
- [ ] **Step 7:** Verificare sintassi: `php -l src/model/Operations/AbstractOperation.php`
- [ ] **Step 8:** Creare `OperationFactory.php` con `create()` e `discoverAll()` come descritto sopra
- [ ] **Step 9:** Verificare sintassi: `php -l src/model/Operations/OperationFactory.php`
- [ ] **Step 10:** Estendere `AjaxResponseHelper.php` — aggiungere proprietà statica `$currentRequest`, metodo `getRequest(): AjaxRequest`, modificare `success()` per accettare `?AbstractOperation` e loggare automaticamente
- [ ] **Step 11:** Verificare sintassi: `php -l src/model/Operations/AjaxResponseHelper.php`
- [ ] **Step 12:** Aggiornare `autoloader.php` con i require delle nuove classi nell'ordine: AjaxRequest → OperationInterface → OperationAuditLogger → AbstractOperation → OperationFactory (dopo AjaxResponseHelper)
- [ ] **Step 13:** Commit: `feat: add architecture core (Interface, Abstract, Factory, AjaxRequest DTO, AuditLogger, AjaxResponseHelper extension)`

---

## Phase 2: Backend Operations

> I 3 agenti di questa fase sono **paralleli** e **indipendenti**. Ciascuno crea un Repository (incapsulato nell'Operation) e una classe Operation. I metodi delle Operation accettano `AjaxRequest` come input. Gli endpoint ajax usano `OperationFactory::create()` e `AjaxResponseHelper::getRequest()`/`success()`.

### Agent 3 — BE-NRC (newRetrievalCode Backend)

| Proprietà | Valore |
|-----------|--------|
| **Modello** | `sonnet` |
| **Tipo** | Backend più complesso: logica di generazione codice, progressivo, autocomplete |
| **Dipendenze** | Agent 2 completato |
| **Durata stimata** | 10-12 minuti |

**Docs da leggere:**
- `PROJECT.md` — sezione 7.1 (newRetrievalCode)
- `docs/QUERY_REFERENCE.md` — sezioni Q-NRC-01, Q-NRC-02, Q-NRC-03, Q-NRC-04
- `docs/API_SPEC.md` — sezione 3 (newRetrievalCode)
- `docs/SCHEMA_REFERENCE.md` — sezioni 2.1 (t_ath_policy_auth_code), 2.2 (v_policy)

**Files:**
- Create: `src/model/Operations/NewRetrievalCodeRepository.php`
- Create: `src/model/Operations/NewRetrievalCode.php`
- Create: `src/model/ajax/ajax_newRetrievalCode_view.php`
- Create: `src/model/ajax/ajax_newRetrievalCode_save.php`

> **NOTA:** NON modificare `lib/autoloader.php`. L'aggiornamento dell'autoloader per tutte le classi di Phase 2 è centralizzato in Agent 6 (BOOT) per evitare conflitti tra agenti paralleli.

**Contesto per l'agente:**

`NewRetrievalCodeRepository` usa `TraitTryQuery`. Contiene 4 metodi, uno per ciascuna query documentata:
- `searchPolicyNumber(string $searchTerm): array` — Q-NRC-01, autocomplete con `LIKE` e `LIMIT 10`
- `getExistingCodes(string $bperContractNumber): array` — Q-NRC-02, codici esistenti ordinati
- `calculateNextCode(string $codePrefix): ?int` — Q-NRC-03, MAX dell'ultimo carattere. Ritorna null se nessun codice
- `insertCode(string $code, string $bperContractNumber, string $operationTypeCode): void` — Q-NRC-04, INSERT ON CONFLICT DO NOTHING

`NewRetrievalCode` estende `AbstractOperation`. Implementa gli 8 metodi dell'interfaccia (getName ritorna `'newRetrievalCode'`). **Il repository è interno**: il costruttore crea `$this->repository = new NewRetrievalCodeRepository()`.

**Metodi pubblici dell'Operation** (accettano `AjaxRequest`):
- `searchPolicy(AjaxRequest $request): array` — delega a `$this->repository->searchPolicyNumber($request->get('q'))`
- `getExistingCodes(AjaxRequest $request): array` — delega a `$this->repository->getExistingCodes($request->get('bper_contract_number'))`
- `calculatePreview(AjaxRequest $request): array` — calcola codice, ritorna `['code' => ..., 'next_n' => ...]` o lancia eccezione se n > 9
- `insert(AjaxRequest $request): array` — calcola codice, inserisce via repository, ritorna `['code' => ..., 'inserted' => true]`

**Logica di business interna:**
- `getOperationTypeCode(string $type): string` — mapping P→`_RISPA`, T→`_RISTO` (usare `match`)
- `generateCode(string $contractNumber, string $type): array` — calcola codice completo

**Pattern endpoint ajax** (da seguire per TUTTI i file ajax):
```php
<?php declare(strict_types=1);
require_once __DIR__ . '/../../../lib/autoloader.php';
header('Content-Type: application/json');
use FirstAdvisory\FAWill\model\Operations\{AjaxResponseHelper, OperationFactory};
try {
    $request = AjaxResponseHelper::getRequest();
    $operation = OperationFactory::create('NewRetrievalCode');  // 403 automatico se disabilitata
    switch ($request->action) {
        case 'search': AjaxResponseHelper::success($operation->searchPolicy($request)); break;
        // ...
    }
} catch (\Throwable $e) {
    AjaxResponseHelper::error($e->getMessage(), $e);
}
```

`ajax_newRetrievalCode_view.php`: 3 action (`search`, `tabella`, `calc`). **Nessun log** (lettura).
`ajax_newRetrievalCode_save.php`: POST → `AjaxResponseHelper::success($result, $operation)` — **log automatico**.

Ogni query DEVE usare named parameters (`:param`), MAI `?`.

- [ ] **Step 1:** Creare `NewRetrievalCodeRepository.php` con i 4 metodi. Ogni metodo usa `$this->tryQuery()` con named parameters e `$this->getQueryRecords()` o `$this->getQueryRecord()` per fetchare i risultati
- [ ] **Step 2:** Verificare sintassi: `php -l src/model/Operations/NewRetrievalCodeRepository.php`
- [ ] **Step 3:** Creare `NewRetrievalCode.php`. Il costruttore crea `$this->repository = new NewRetrievalCodeRepository()`. Implementare getName()='newRetrievalCode', title="Inserimento Codice Riscatto", description="Inserisci un nuovo codice di riscatto parziale o totale", icon="bi-upc-scan", color="primary", jsPath="./assets-fa/js/Operations/newRetrievalCode.js". Implementare i 4 metodi pubblici che accettano `AjaxRequest`
- [ ] **Step 4:** Verificare sintassi: `php -l src/model/Operations/NewRetrievalCode.php`
- [ ] **Step 5:** Creare `ajax_newRetrievalCode_view.php` usando il pattern Factory + AjaxResponseHelper. Switch su action (search, tabella, calc). La validazione input avviene nei metodi dell'Operation, non nell'endpoint
- [ ] **Step 6:** Creare `ajax_newRetrievalCode_save.php` — POST → `$operation->insert($request)` → `AjaxResponseHelper::success($result, $operation)` (log automatico)
- [ ] **Step 7:** Verificare sintassi di entrambi i file ajax
- [ ] **Step 8:** Commit: `feat: implement newRetrievalCode backend (repository, operation, ajax endpoints)`

---

### Agent 4 — BE-FA (forceAnnulment Backend)

| Proprietà | Valore |
|-----------|--------|
| **Modello** | `sonnet` |
| **Tipo** | Backend con transazione atomica multi-statement |
| **Dipendenze** | Agent 2 completato |
| **Durata stimata** | 8-10 minuti |

**Docs da leggere:**
- `PROJECT.md` — sezione 7.2 (forceAnnulment)
- `docs/QUERY_REFERENCE.md` — sezioni Q-FA-01, Q-FA-02, Q-FA-03, Q-FA-04, Q-FA-05
- `docs/API_SPEC.md` — sezione 4 (forceAnnulment)
- `docs/SCHEMA_REFERENCE.md` — sezioni 2.3, 2.4, 2.5, 2.6

**Files:**
- Create: `src/model/Operations/ForceAnnulmentRepository.php`
- Create: `src/model/Operations/ForceAnnulment.php`
- Create: `src/model/ajax/ajax_forceAnnulment_view.php`
- Create: `src/model/ajax/ajax_forceAnnulment_save.php`

> **NOTA:** NON modificare `lib/autoloader.php`. L'aggiornamento è centralizzato in Agent 6.

**Contesto per l'agente:**

`ForceAnnulmentRepository` usa `TraitTryQuery`. Contiene:
- `getOperationList(): array` — Q-FA-01, SELECT con JOIN, WHERE status != 'CANCELLED'
- `getOperationData(int $id): array|false` — Q-FA-02, estrae bper_policy_number e company_operation_id
- `deleteOperation(string $bperPolicyNumber, string $companyOperationId): void` — Q-FA-03 + Q-FA-04 + Q-FA-05 in transazione atomica. Usa `addQueryInStack()` per i 3 statement e `tryQueryStack()` per eseguirli atomicamente

La cancellazione è una cascata: 1) soft-delete operazione (UPDATE status=CANCELLED), 2) hard-delete documenti, 3) hard-delete draft.

`ForceAnnulment` estende `AbstractOperation`. getName()='forceAnnulment', title="Annullamento Forzato", description="Annulla operazioni inserite per errore", icon="bi-trash", color="danger", jsPath="./assets-fa/js/Operations/forceAnnulment.js". **Il repository è interno** (`$this->repository = new ForceAnnulmentRepository()` nel costruttore).

**Metodi pubblici** (accettano `AjaxRequest`):
- `getOperations(AjaxRequest $request): array` — delega a repository
- `delete(AjaxRequest $request): array` — estrae dati da repository, esegue cancellazione atomica, ritorna `['deleted' => true]`

**Endpoint ajax** — usano Factory + AjaxResponseHelper come Agent 3:
- `ajax_forceAnnulment_view.php`: action `tabella` → `AjaxResponseHelper::success($operation->getOperations($request))` (no log)
- `ajax_forceAnnulment_save.php`: POST → `AjaxResponseHelper::success($result, $operation)` (log automatico)

- [ ] **Step 1:** Creare `ForceAnnulmentRepository.php` con i 3 metodi. Il metodo `deleteOperation` usa `addQueryInStack()` per i 3 statement SQL e `tryQueryStack()` per esecuzione atomica
- [ ] **Step 2:** Verificare sintassi: `php -l src/model/Operations/ForceAnnulmentRepository.php`
- [ ] **Step 3:** Creare `ForceAnnulment.php`. Costruttore crea repository interno. Implementare getName()='forceAnnulment', metodi presentazione, metodi pubblici `getOperations(AjaxRequest)` e `delete(AjaxRequest)` che delegano al repository
- [ ] **Step 4:** Verificare sintassi: `php -l src/model/Operations/ForceAnnulment.php`
- [ ] **Step 5:** Creare `ajax_forceAnnulment_view.php` con pattern Factory + AjaxResponseHelper, action `tabella`
- [ ] **Step 6:** Creare `ajax_forceAnnulment_save.php` — POST → `$operation->delete($request)` → `AjaxResponseHelper::success($result, $operation)` (log automatico)
- [ ] **Step 7:** Verificare sintassi file ajax
- [ ] **Step 8:** Commit: `feat: implement forceAnnulment backend (repository, operation, ajax endpoints)`

---

### Agent 5 — BE-RDS (resetDocumentState Backend)

| Proprietà | Valore |
|-----------|--------|
| **Modello** | `sonnet` |
| **Tipo** | Backend con query complessa (4 JOIN, GROUP BY, COUNT FILTER) |
| **Dipendenze** | Agent 2 completato |
| **Durata stimata** | 6-8 minuti |

**Docs da leggere:**
- `PROJECT.md` — sezione 7.3 (resetDocumentState)
- `docs/QUERY_REFERENCE.md` — sezioni Q-RDS-01, Q-RDS-02
- `docs/API_SPEC.md` — sezione 5 (resetDocumentState)

**Files:**
- Create: `src/model/Operations/ResetDocumentStateRepository.php`
- Create: `src/model/Operations/ResetDocumentState.php`
- Create: `src/model/ajax/ajax_resetDocumentState_view.php`
- Create: `src/model/ajax/ajax_resetDocumentState_save.php`

> **NOTA:** NON modificare `lib/autoloader.php`. L'aggiornamento è centralizzato in Agent 6.

**Contesto per l'agente:**

`ResetDocumentStateRepository` usa `TraitTryQuery`. Contiene:
- `getDraftList(): array` — Q-RDS-01, SELECT con 4 JOIN + GROUP BY + COUNT FILTER
- `updateDocumentStatus(int $draftId): void` — Q-RDS-02, UPDATE status PENDING→ERROR

**Pattern Repository (da seguire per entrambi i metodi):**
```php
<?php declare(strict_types=1);
namespace FirstAdvisory\FAWill\model\Operations;
class ResetDocumentStateRepository {
    use \TraitTryQuery;  // trait globale senza namespace
    public function getDraftList(): array {
        $stmt = $this->tryQuery("SELECT ... FROM ...");  // query da Q-RDS-01
        return $this->getQueryRecords($stmt);
    }
    public function updateDocumentStatus(int $draftId): void {
        $this->tryQuery("UPDATE ...", [':id' => $draftId]);  // query da Q-RDS-02
    }
}
```

`ResetDocumentState` estende `AbstractOperation`. getName()='resetDocumentState', title="Cambio Stato Documento", description="Forza lo stato dei documenti da PENDING a ERROR", icon="bi-arrow-repeat", color="warning", jsPath="./assets-fa/js/Operations/resetDocumentState.js". **Il repository è interno** (`$this->repository = new ResetDocumentStateRepository()` nel costruttore).

**Metodi pubblici** (accettano `AjaxRequest`):
- `getDrafts(AjaxRequest $request): array` — delega a repository
- `updateStatus(AjaxRequest $request): array` — delega a repository, ritorna `['updated' => true]`

**Endpoint ajax** — usano Factory + AjaxResponseHelper come Agent 3:
- `ajax_resetDocumentState_view.php`: action `tabella` → `AjaxResponseHelper::success($operation->getDrafts($request))` (no log)
- `ajax_resetDocumentState_save.php`: POST → `AjaxResponseHelper::success($result, $operation)` (log automatico)

- [ ] **Step 1:** Creare `ResetDocumentStateRepository.php` con i 2 metodi. La query Q-RDS-01 è complessa (4 JOIN, GROUP BY, COUNT FILTER) — copiarla esattamente da QUERY_REFERENCE.md
- [ ] **Step 2:** Verificare sintassi: `php -l src/model/Operations/ResetDocumentStateRepository.php`
- [ ] **Step 3:** Creare `ResetDocumentState.php`. Costruttore crea repository interno. Implementare getName()='resetDocumentState', metodi presentazione, metodi pubblici `getDrafts(AjaxRequest)` e `updateStatus(AjaxRequest)` che delegano al repository
- [ ] **Step 4:** Creare `ajax_resetDocumentState_view.php` e `ajax_resetDocumentState_save.php` con pattern Factory + AjaxResponseHelper. Save usa `success($result, $operation)` per log automatico
- [ ] **Step 5:** Verificare sintassi file ajax
- [ ] **Step 6:** Commit: `feat: implement resetDocumentState backend (repository, operation, ajax endpoints)`

---

## Phase 3: Backend Integration

### Agent 6 — BOOT (Bootstrapping + Autoloader Consolidation)

| Proprietà | Valore |
|-----------|--------|
| **Modello** | `sonnet` |
| **Tipo** | Consolidamento autoloader per tutte le classi Phase 2 + auto-discovery endpoint |
| **Dipendenze** | Agents 3, 4, 5 completati |
| **Durata stimata** | 5-7 minuti |

**Docs da leggere:**
- `PROJECT.md` — sezioni 3.2 (Bootstrapping), 4.2 (OperationInterface)
- `docs/API_SPEC.md` — sezione 2 (MainApp ajax_operations_view.php)

**Files:**
- Modify: `lib/autoloader.php` — aggiungere require di TUTTE le classi create in Phase 2 (6 file)
- Modify: `src/model/ajax/ajax_operations_view.php`

**Contesto per l'agente:**

**PASSO 1 — Autoloader:** Aggiungere in `autoloader.php` i require delle 6 classi create dagli Agent 3, 4, 5 nell'ordine corretto di dipendenza (Repository prima della classe Operation):

```php
// Livello 3: Repository
require_once __DIR__ . '/../src/model/Operations/NewRetrievalCodeRepository.php';
require_once __DIR__ . '/../src/model/Operations/ForceAnnulmentRepository.php';
require_once __DIR__ . '/../src/model/Operations/ResetDocumentStateRepository.php';

// Livello 4: Operation
require_once __DIR__ . '/../src/model/Operations/NewRetrievalCode.php';
require_once __DIR__ . '/../src/model/Operations/ForceAnnulment.php';
require_once __DIR__ . '/../src/model/Operations/ResetDocumentState.php';
```

**PASSO 2 — Auto-discovery:** Riscrivere `ajax_operations_view.php` (attualmente è un placeholder). Con `action=list`:

```php
$request = AjaxResponseHelper::getRequest();
$operations = OperationFactory::discoverAll();  // gestisce tutto: scansione, filtro isVisible, flag enabled
AjaxResponseHelper::success($operations);        // no log (è una lettura)
```

La logica di auto-discovery è interamente in `OperationFactory::discoverAll()` (creata da Agent 2). L'endpoint è minimale — solo 3 righe nel try/catch.

- [ ] **Step 1:** Aggiornare `autoloader.php` con i require delle 6 classi (3 Repository + 3 Operation) dopo le classi di Agent 2
- [ ] **Step 2:** Riscrivere `ajax_operations_view.php` — usare `OperationFactory::discoverAll()` (3 righe nel try/catch)
- [ ] **Step 3:** Verificare sintassi: `php -l lib/autoloader.php && php -l src/model/ajax/ajax_operations_view.php`
- [ ] **Step 4:** Commit: `feat: consolidate autoloader and implement operation auto-discovery`

---

## Phase 4: Frontend

> I 4 agenti frontend sono **paralleli**. Agent 7 (FE-MAIN) deve essere completato prima di Agent 8, 9, 10 per garantire che la struttura della MainApp (modale, container, gestione script) sia definita. Tuttavia Agent 8, 9, 10 sono tra loro paralleli.

### Agent 7 — FE-MAIN (MainApp Vue.js)

| Proprietà | Valore |
|-----------|--------|
| **Modello** | `sonnet` |
| **Tipo** | App Vue principale: card rendering, modal management, dynamic script loading |
| **Dipendenze** | Agent 6 completato |
| **Durata stimata** | 10-12 minuti |

**Docs da leggere:**
- `PROJECT.md` — sezioni 3 (Architettura MainApp), 6 (Gestione errori)
- `docs/API_SPEC.md` — sezione 2 (MainApp ajax_operations_view.php)
- `docs/ACCEPTANCE_CRITERIA.md` — sezioni AC-MAIN, AC-ERR

**Files:**
- Create: `assets-fa/js/Operations/app.js`
- Create: `assets-fa/css/operations/main.css`
- Modify: `src/controller/Operations/ctl_operations.php` (se necessario aggiornare)

**Contesto per l'agente:**

`app.js` definisce un oggetto globale `OperationsApp` che è un Vue 3 Options API component. Il controller lo monta con `Vue.createApp(OperationsApp).mount('#app_operations')`.

**Responsabilità della MainApp:**

1. **Fetch operazioni**: al mount, GET `./src/model/ajax/ajax_operations_view.php?action=list` per ottenere l'elenco operazioni
2. **Render card**: per ogni operazione, mostrare una Card Bootstrap (icona colorata, titolo, descrizione)
3. **Gestione modale**: cliccando una card, aprire una modale Bootstrap. Il body della modale contiene un `<div>` con id dedicato per il mount dell'app Vue dell'operazione
4. **Caricamento dinamico script**: alla prima apertura di un'operazione, creare un `<script>` tag dinamico con `src` = `jsPath` dall'operazione. Quando lo script è caricato, creare l'app Vue e montarla nel container dedicato
5. **Show/hide**: alla chiusura della modale, nasconderla (CSS display). Alla riapertura, mostrarla e triggherare un evento custom `modal-reopen` che le operazioni ascoltano per ricaricare i dati tabellari
6. **Toast errori**: gestione globale degli errori Ajax con Toast Bootstrap sovrapposto a tutto (z-index alto), persistente fino a chiusura esplicita

**Struttura template nella MainApp:**
- Container con `class="row g-4"` per le card
- Una modale Bootstrap riutilizzata per tutte le operazioni, con titolo dinamico
- Un div `#toast-container` in posizione fissa per i toast di errore

**Variabili data Vue:**
- `operations: []` — elenco operazioni dal backend
- `currentOperation: null` — operazione attualmente selezionata
- `loadedApps: {}` — mappa `jsPath → true` per tracciare script già caricati
- `mountedApps: {}` — mappa `jsPath → vueAppInstance` per tracciare app Vue montate

**Il controller `ctl_operations.php`** non va modificato nella struttura (i metodi getHead/getContent/getScript sono già corretti). Verificare solo che il path dello script app.js nel `getScript()` sia corretto (attualmente `./assets-fa/js/operations/app.js` — controllare la casing: la cartella è `Operations` con O maiuscola).

- [ ] **Step 1:** Verificare la casing del path in `ctl_operations.php` → `getScript()`. Se necessario correggere da `operations` a `Operations`
- [ ] **Step 2:** Creare `assets-fa/css/operations/main.css` con stili minimi: cursor pointer sulle card, transizione hover, z-index alto per toast container
- [ ] **Step 3:** Creare `app.js` con l'oggetto `OperationsApp` — definire il template Vue (card grid + modale + toast container) usando template literal. Implementare `mounted()` che chiama fetch operazioni
- [ ] **Step 4:** Implementare il metodo `openOperation(operation)`: se `operation.enabled === false` → non fare nulla (return). Se script non caricato → creare `<script>` tag, su onload creare e montare l'app Vue; se già caricato → mostrare la modale e dispatchiare evento `modal-reopen`. Nel template, le card con `enabled: false` devono avere: classe CSS `opacity-50`, stile `cursor: not-allowed`, attributo `title="Operazione temporaneamente non disponibile"` (tooltip Bootstrap), nessun handler click
- [ ] **Step 5:** Implementare la funzione globale `showErrorToast(message)` accessibile dalle operazioni figlie per mostrare errori
- [ ] **Step 6:** Verificare che il file sia sintatticamente corretto aprendo la console browser (non eseguibile direttamente da CLI)
- [ ] **Step 7:** Commit: `feat: implement MainApp with card rendering, dynamic loading, and error toast`

---

### Agent 8 — FE-NRC (newRetrievalCode Frontend)

| Proprietà | Valore |
|-----------|--------|
| **Modello** | `sonnet` |
| **Tipo** | Frontend più complesso: autocomplete, form validation, preview, tabella |
| **Dipendenze** | Agent 7 completato |
| **Durata stimata** | 10-12 minuti |

**Docs da leggere:**
- `PROJECT.md` — sezione 7.1 (newRetrievalCode interfaccia)
- `docs/API_SPEC.md` — sezione 3 (newRetrievalCode endpoints)
- `docs/ACCEPTANCE_CRITERIA.md` — sezione AC-NRC

**Files:**
- Create: `assets-fa/js/Operations/newRetrievalCode.js`

**Contesto per l'agente:**

Il file definisce un oggetto globale `NewRetrievalCode` (Vue 3 Options API). La MainApp lo usa così: `Vue.createApp(NewRetrievalCode).mount('#container-newRetrievalCode')`.

**Interfaccia:** form orizzontale con 2 campi + bottone, sotto il form la tabella codici e l'anteprima.

**Template Vue (template literal):**
1. Riga form: dropdown tipo riscatto (P/T) + campo testo con autocomplete + bottone `bi-plus-circle` (disabilitato se campi incompleti)
2. Sotto il form: div anteprima codice (visibile solo quando `previewCode` è valorizzato)
3. Sotto l'anteprima: avviso limite raggiunto (visibile se `limitReached`)
4. Tabella codici esistenti (visibile quando `contractNumber` è valorizzato)
5. Modale conferma inserimento (Bootstrap modal)

**Autocomplete:**
- Debounce 2 secondi dopo l'ultimo input
- Minimo 2 caratteri
- Chiama `GET ./src/model/ajax/ajax_newRetrievalCode_view.php?action=search&q=...`
- Mostra dropdown con max 10 risultati sotto il campo
- Alla selezione: popola il campo, chiude il dropdown, triggera caricamento tabella e calcolo anteprima

**Data Vue:**
- `type: 'T'` — tipo riscatto selezionato
- `contractNumber: ''` — codice polizza
- `suggestions: []` — risultati autocomplete
- `showSuggestions: false`
- `existingCodes: []` — codici nella tabella
- `previewCode: ''` — anteprima codice calcolato
- `nextN: null`
- `limitReached: false` — limite 9 raggiunto
- `limitMessage: ''`
- `debounceTimer: null`

**Metodi Vue:**
- `onInput()` — gestisce debounce e chiama `fetchSuggestions()`
- `fetchSuggestions()` — GET search
- `selectSuggestion(value)` — popola campo, chiama `fetchExistingCodes()` e `fetchPreview()`
- `fetchExistingCodes()` — GET tabella
- `fetchPreview()` — GET calc (gestire errore limite)
- `insertCode()` — mostra modale conferma
- `confirmInsert()` — POST insert, aggiorna tabella, chiude modale conferma
- `onModalReopen()` — listener per evento `modal-reopen`, ricarica tabella se contractNumber è valorizzato

**Gestione errori:** usare la funzione globale `showErrorToast(message)` definita dalla MainApp.

- [ ] **Step 1:** Creare `newRetrievalCode.js` con la struttura base: oggetto `NewRetrievalCode` con template, data, methods, mounted
- [ ] **Step 2:** Implementare l'autocomplete con debounce 2s e dropdown suggerimenti
- [ ] **Step 3:** Implementare `fetchExistingCodes()` e `fetchPreview()` con gestione del caso limite raggiunto
- [ ] **Step 4:** Implementare `insertCode()` con modale conferma e `confirmInsert()` con POST e aggiornamento tabella
- [ ] **Step 5:** Implementare listener per `modal-reopen` che ricarica la tabella
- [ ] **Step 6:** Commit: `feat: implement newRetrievalCode Vue frontend with autocomplete and preview`

---

### Agent 9 — FE-FA (forceAnnulment Frontend)

| Proprietà | Valore |
|-----------|--------|
| **Modello** | `sonnet` |
| **Tipo** | Frontend tabellare con paginazione, filtro, ordinamento e azione cancellazione |
| **Dipendenze** | Agent 7 completato |
| **Durata stimata** | 6-8 minuti |

**Docs da leggere:**
- `PROJECT.md` — sezione 7.2 (forceAnnulment interfaccia)
- `docs/API_SPEC.md` — sezione 4 (forceAnnulment endpoints)
- `docs/ACCEPTANCE_CRITERIA.md` — sezione AC-FA

**Files:**
- Create: `assets-fa/js/Operations/forceAnnulment.js`

**Contesto per l'agente:**

Oggetto globale `ForceAnnulment` (Vue 3 Options API). La MainApp lo monta con `Vue.createApp(ForceAnnulment).mount('#container-forceAnnulment')`.

**Template:** tabella con paginazione, filtro, ordinamento. La prima colonna è l'icona cestino per cancellare.

**Caratteristiche tabella:**
- Il frontend si adatta dinamicamente alle colonne restituite dal backend. Pattern per estrarre le colonne: `this.columns = Object.keys(data[0]).filter(k => k !== 'id')`. Escludere il campo `id` dalla visualizzazione ma conservarlo nei dati per la POST
- Paginazione client-side: default 20, opzioni 20/50/100
- Filtro testuale client-side su tutte le colonne
- Ordinamento client-side per colonna cliccando sull'header (toggle asc/desc)

**Data Vue:**
- `allRows: []` — tutti i dati dal server
- `columns: []` — nomi colonne (escluso `id`)
- `filterText: ''`
- `sortColumn: ''`
- `sortAsc: true`
- `pageSize: 20`
- `currentPage: 1`
- `deleteTargetId: null` — id dell'operazione da cancellare (per la modale conferma)

**Computed Vue:**
- `filteredRows` — filtra `allRows` per `filterText` su tutte le colonne
- `sortedRows` — ordina `filteredRows` per `sortColumn`
- `paginatedRows` — slice per paginazione
- `totalPages`

**Metodi:**
- `fetchData()` — GET tabella, popola `allRows` e `columns`
- `deleteOperation(id)` — apre modale conferma
- `confirmDelete()` — POST delete con `id`, poi richiama `fetchData()`
- `onModalReopen()` — richiama `fetchData()`

- [ ] **Step 1:** Creare `forceAnnulment.js` con template tabellare Bootstrap (thead dinamico, tbody con v-for, paginazione, filtro, selettore pageSize)
- [ ] **Step 2:** Implementare computed per filtro, ordinamento e paginazione client-side
- [ ] **Step 3:** Implementare fetch dati, cancellazione con modale conferma, e ricaricamento
- [ ] **Step 4:** Implementare listener `modal-reopen`
- [ ] **Step 5:** Commit: `feat: implement forceAnnulment Vue frontend with dynamic table`

---

### Agent 10 — FE-RDS (resetDocumentState Frontend)

| Proprietà | Valore |
|-----------|--------|
| **Modello** | `sonnet` |
| **Tipo** | Frontend tabellare simile a FA ma con differenziazione visuale per stato |
| **Dipendenze** | Agent 7 completato |
| **Durata stimata** | 6-8 minuti |

**Docs da leggere:**
- `PROJECT.md` — sezione 7.3 (resetDocumentState interfaccia)
- `docs/API_SPEC.md` — sezione 5 (resetDocumentState endpoints)
- `docs/ACCEPTANCE_CRITERIA.md` — sezione AC-RDS

**Files:**
- Create: `assets-fa/js/Operations/resetDocumentState.js`

**Contesto per l'agente:**

Oggetto globale `ResetDocumentState` (Vue 3 Options API). Montato con `Vue.createApp(ResetDocumentState).mount('#container-resetDocumentState')`.

**Template:** tabella con stessa struttura di forceAnnulment (paginazione, filtro, ordinamento). Differenza nella prima colonna e nello sfondo riga.

**Differenziazione visuale:**
- Righe con `"Doc. PENDING" > 0`: sfondo bianco, prima colonna = icona `bi-clock-history` cliccabile (color warning)
- Righe con `"Doc. PENDING" == 0` (tutti ERROR): sfondo grigio (`bg-light`), prima colonna = icona `bi-x-circle-fill` rossa, NON cliccabile

**Colonne:** dinamiche come FA, escludere `id`. Le colonne `Doc. PENDING` e `Doc. ERROR` sono visibili.

**Data/Computed/Metodi:** stessa struttura di forceAnnulment con:
- `isPending(row)` — ritorna `row['Doc. PENDING'] > 0`
- `changeStatus(id)` — apre modale conferma (solo se isPending)
- `confirmChange()` — POST update con `id`, poi richiama `fetchData()`

- [ ] **Step 1:** Creare `resetDocumentState.js` copiando la struttura tabellare da forceAnnulment (stessi pattern per paginazione, filtro, ordinamento)
- [ ] **Step 2:** Modificare la prima colonna per differenziare icona e azione in base allo stato PENDING/ERROR
- [ ] **Step 3:** Aggiungere stile inline o classe per sfondo grigio sulle righe ERROR
- [ ] **Step 4:** Implementare modale conferma e POST update
- [ ] **Step 5:** Commit: `feat: implement resetDocumentState Vue frontend with status differentiation`

---

## Phase 5: Test Infrastructure

### Agent 11 — TEST-INFRA

| Proprietà | Valore |
|-----------|--------|
| **Modello** | `haiku` |
| **Tipo** | Infrastruttura Docker per test, script runner, classe base PHPUnit |
| **Dipendenze** | Nessuna (può essere eseguito in parallelo con le Phase 2-4) |
| **Durata stimata** | 5-7 minuti |

**Docs da leggere:**
- `PROJECT.md` — sezione 8 (Test)
- `docs/TEST_ENVIRONMENT.md`
- `docs/SCHEMA_REFERENCE.md` — sezione 3 (DDL)
- `docs/DEPLOY.md` — sezione 4 (Migrazione database)

**Files:**
- Create: `composer.json` (nella root del progetto — richiesto dal Dockerfile per `composer install`)
- Create: `tests/Dockerfile`
- Create: `tests/docker-compose.yml`
- Create: `tests/phpunit.xml`
- Create: `tests/BperTestCase.php`
- Create: `tests/migrations/001_create_audit_log.sql`
- Create: `run_tests.sh` (nella root del progetto)

**Contesto per l'agente:**

**composer.json:** minimo indispensabile per PHPUnit. Contenuto:
```json
{
    "require-dev": {
        "phpunit/phpunit": "^10.5"
    },
    "autoload": {
        "classmap": ["src/", "lib/"]
    }
}
```

**Dockerfile:** basato su `php:8.2-cli`. Installare estensioni `pdo_pgsql`, `json`, `mbstring`. Installare Composer, poi `composer install`. Copiare il progetto.

**docker-compose.yml:** un solo servizio `phpunit` che si collega alla rete Docker esterna `fa-universe` (dove gira `fa-db`). Mount del codice come volume.

```yaml
networks:
  fa-universe:
    name: fa-universe
    external: true
```

**phpunit.xml:** configurazione PHPUnit con testsuites: `unit`, `integration`, `security`. Cartelle: `tests/Unit/`, `tests/Integration/`, `tests/Security/`.

**BperTestCase.php:** classe base che estende `PHPUnit\Framework\TestCase`. Fornisce:
- Connessione PDO al database di test (usa le costanti da `env_settings.php`)
- Metodo `setUp()` / `tearDown()` per fixture con cleanup
- Constanti per prefisso test data: `TEST_PREFIX = 'TEST_'`
- Helper per inserire/rimuovere fixture

**migrations/001_create_audit_log.sql:** contiene il DDL per la tabella `operation_audit_log` da SCHEMA_REFERENCE.md sezione 3.

**run_tests.sh:** script bash eseguibile che:
1. Esegue la migrazione DB: `docker compose -f tests/docker-compose.yml run phpunit php -r "..."` per applicare `tests/migrations/001_create_audit_log.sql` via PDO (IF NOT EXISTS)
2. Lancia i test PHPUnit: `docker compose -f tests/docker-compose.yml run phpunit vendor/bin/phpunit`
3. (Opzionale) Lancia i test E2E Playwright: `cd tests/e2e && npx playwright test` — richiede Node.js e che il server PHP di test sia raggiungibile
4. Ritorna l'exit code combinato dei test

- [ ] **Step 1:** Creare `composer.json` nella root del progetto con la dipendenza PHPUnit e l'autoload classmap
- [ ] **Step 2:** Creare `tests/migrations/001_create_audit_log.sql` con DDL dalla documentazione
- [ ] **Step 3:** Creare `tests/Dockerfile` con PHP 8.2, estensioni, Composer, `composer install`
- [ ] **Step 4:** Creare `tests/docker-compose.yml` con rete esterna `fa-universe`
- [ ] **Step 5:** Creare `tests/phpunit.xml` con 3 testsuites
- [ ] **Step 6:** Creare `tests/BperTestCase.php` con setup/teardown, connessione DB, helper fixture
- [ ] **Step 7:** Creare `run_tests.sh` nella root con migrazione DB + PHPUnit + sezione Playwright opzionale, renderlo eseguibile con `chmod +x`
- [ ] **Step 8:** Creare le cartelle: `tests/Unit/`, `tests/Integration/`, `tests/Security/`
- [ ] **Step 9:** Commit: `feat: add test infrastructure (Docker, PHPUnit config, base test case, migration, composer.json)`

---

## Phase 6: Tests

> I 4 agenti test sono **paralleli** e indipendenti. Ciascuno si concentra su un tipo di test specifico.

### Agent 12 — TEST-UNIT

| Proprietà | Valore |
|-----------|--------|
| **Modello** | `sonnet` |
| **Tipo** | Unit test PHPUnit per tutti i componenti PHP |
| **Dipendenze** | Phase 1-5 completate |
| **Durata stimata** | 12-15 minuti |

**Docs da leggere:**
- `docs/EXPLAIN_TEST.md` — sezione 1 (Unit Test, tutti gli scenari U-*)
- `docs/QUERY_REFERENCE.md` — per comprendere la logica da testare
- I file PHP implementati: tutti i file in `src/model/Operations/`

**Files:**
- Create: `tests/Unit/NewRetrievalCodeTest.php` — scenari U-NRC-01 → U-NRC-08
- Create: `tests/Unit/ForceAnnulmentTest.php` — scenari U-FA-01 → U-FA-02
- Create: `tests/Unit/ResetDocumentStateTest.php` — scenari U-RDS-01 → U-RDS-02
- Create: `tests/Unit/AuditLoggerTest.php` — scenari U-LOG-01 → U-LOG-03
- Create: `tests/Unit/BootstrapTest.php` — scenari U-BOOT-01 → U-BOOT-03
- Create: `tests/Unit/AccessControlTest.php` — scenari U-ACC-01 → U-ACC-05

**Contesto per l'agente:**

I test unitari verificano la logica di business in isolamento. Per i metodi che dipendono dal DB (repository), i test useranno fixture reali nel database (come da TEST_ENVIRONMENT.md — non si usano mock per il DB).

Ogni metodo di test DEVE avere il nome nel formato: `testUNRC01_GenerazioneCodiceRiscattoTotale()` (ID scenario + descrizione breve).

Leggere i file PHP implementati per capire le firme dei metodi e le classi da testare. NON assumere le firme — leggerle dal codice.

**Test U-NRC (8 scenari):** testare la logica di `generateCode()`, `getOperationTypeCode()`, e il calcolo del progressivo. Usare fixture con `TEST_` prefix per contract_number.

**Test U-FA (2 scenari):** testare `getOperationData()` del repository con fixture inserita e con id inesistente.

**Test U-RDS (2 scenari):** testare i conteggi PENDING/ERROR del repository con fixture.

**Test U-LOG (3 scenari):** testare che `AjaxResponseHelper::success($data, $operation)` scriva il log automaticamente, che `getCurrentUserId()` ritorni 0, e che `success($data)` senza operazione non loggi.

**Test U-BOOT (3 scenari):** testare la scoperta automatica delle operazioni (scansione cartella, esclusione classi non-operation, dati presentazione completi).

**Test U-ACC (5 scenari):** testare isVisible/isEnabled defaults, assertEnabled() che lancia eccezione quando disabilitata, auto-discovery che esclude operazioni non visibili, auto-discovery che include flag enabled=false. Per i test U-ACC-02 e U-ACC-04/05, creare una classe stub temporanea che sovrascrive isVisible/isEnabled.

- [ ] **Step 1:** Leggere tutti i file PHP in `src/model/Operations/` per capire le firme e la struttura delle classi
- [ ] **Step 2:** Creare `NewRetrievalCodeTest.php` con 8 metodi di test (U-NRC-01 → U-NRC-08). Usare fixture con contract_number che inizia con `TEST_`. Cleanup nel tearDown
- [ ] **Step 3:** Creare `ForceAnnulmentTest.php` con 2 metodi di test
- [ ] **Step 4:** Creare `ResetDocumentStateTest.php` con 2 metodi di test
- [ ] **Step 5:** Creare `AuditLoggerTest.php` con 3 metodi di test
- [ ] **Step 6:** Creare `BootstrapTest.php` con 3 metodi di test
- [ ] **Step 6b:** Creare `AccessControlTest.php` con 5 metodi di test (U-ACC-01 → U-ACC-05) — creare una classe stub anonima o interna che sovrascrive isVisible/isEnabled per i test
- [ ] **Step 7:** Eseguire i test: `docker compose -f tests/docker-compose.yml run phpunit vendor/bin/phpunit --testsuite unit`
- [ ] **Step 8:** Correggere eventuali fallimenti
- [ ] **Step 9:** Commit: `test: add unit tests for all operations (U-NRC, U-FA, U-RDS, U-LOG, U-BOOT, U-ACC)`

---

### Agent 13 — TEST-INT

| Proprietà | Valore |
|-----------|--------|
| **Modello** | `sonnet` |
| **Tipo** | Integration test PHPUnit — flusso completo backend via Ajax |
| **Dipendenze** | Phase 1-5 completate |
| **Durata stimata** | 12-15 minuti |

**Docs da leggere:**
- `docs/EXPLAIN_TEST.md` — sezione 2 (Integration Test, scenari I-*)
- `docs/API_SPEC.md` — per request/response di tutti gli endpoint
- I file PHP in `src/model/ajax/`

**Files:**
- Create: `tests/Integration/NewRetrievalCodeIntegrationTest.php` — I-NRC-01 → I-NRC-06
- Create: `tests/Integration/ForceAnnulmentIntegrationTest.php` — I-FA-01 → I-FA-04
- Create: `tests/Integration/ResetDocumentStateIntegrationTest.php` — I-RDS-01 → I-RDS-04
- Create: `tests/Integration/AccessControlIntegrationTest.php` — I-ACC-01 → I-ACC-02

**Contesto per l'agente:**

I test di integrazione simulano le chiamate Ajax invocando direttamente le classi PHP (non via HTTP). Testano il flusso completo: dalla ricezione dei parametri alla risposta JSON, passando per DB.

Ogni test:
1. Inserisce fixture nel DB
2. Istanzia la classe Operation e/o il Repository
3. Chiama il metodo con i parametri simulati
4. Verifica la risposta E lo stato del DB
5. Cleanup delle fixture nel tearDown

**I-NRC-01:** inserire codice, verificare che esista nel DB e che la risposta sia success=true.
**I-NRC-02:** inserire duplicato, verificare ON CONFLICT DO NOTHING (no errore).
**I-NRC-06:** dopo inserimento, verificare che esista un record in `operation_audit_log`.

**I-FA-01:** inserire fixture completa (operation + draft + docs), cancellare, verificare status=CANCELLED, draft e docs rimossi.
**I-FA-02:** simulare errore durante la transazione, verificare rollback completo.

- [ ] **Step 1:** Leggere i file ajax per capire come vengono invocate le operazioni
- [ ] **Step 2:** Creare `NewRetrievalCodeIntegrationTest.php` con 6 test (I-NRC-01 → I-NRC-06)
- [ ] **Step 3:** Creare `ForceAnnulmentIntegrationTest.php` con 4 test (I-FA-01 → I-FA-04)
- [ ] **Step 4:** Creare `ResetDocumentStateIntegrationTest.php` con 4 test (I-RDS-01 → I-RDS-04)
- [ ] **Step 4b:** Creare `AccessControlIntegrationTest.php` con 2 test (I-ACC-01, I-ACC-02) — testare che l'endpoint restituisca 403 con operazione disabilitata e che processi normalmente se invisibile ma abilitata
- [ ] **Step 5:** Eseguire i test: `docker compose -f tests/docker-compose.yml run phpunit vendor/bin/phpunit --testsuite integration`
- [ ] **Step 6:** Correggere eventuali fallimenti
- [ ] **Step 7:** Commit: `test: add integration tests for all operations (I-NRC, I-FA, I-RDS, I-ACC)`

---

### Agent 14 — TEST-E2E

| Proprietà | Valore |
|-----------|--------|
| **Modello** | `sonnet` |
| **Tipo** | Test E2E Playwright — interfaccia utente completa |
| **Dipendenze** | Phase 1-5 completate |
| **Durata stimata** | 15-18 minuti |

**Docs da leggere:**
- `docs/EXPLAIN_TEST.md` — sezione 3 (E2E Test, scenari E-*)
- `docs/ACCEPTANCE_CRITERIA.md` — tutte le sezioni AC
- `docs/TEST_ENVIRONMENT.md` — per la configurazione dell'ambiente Playwright

**Files:**
- Create: `tests/e2e/playwright.config.ts`
- Create: `tests/e2e/package.json`
- Create: `tests/e2e/tests/main-app.spec.ts` — E-MAIN-01 → E-MAIN-06
- Create: `tests/e2e/tests/new-retrieval-code.spec.ts` — E-NRC-01 → E-NRC-07
- Create: `tests/e2e/tests/force-annulment.spec.ts` — E-FA-01 → E-FA-06
- Create: `tests/e2e/tests/reset-document-state.spec.ts` — E-RDS-01 → E-RDS-04
- Create: `tests/e2e/tests/error-handling.spec.ts` — E-ERR-01 → E-ERR-03
- Create: `tests/e2e/global-setup.ts` — inserimento fixture DB con prefisso `TEST_E2E_`
- Create: `tests/e2e/global-teardown.ts` — cleanup fixture DB
- Create: `tests/e2e/test-page.php` — pagina HTML assemblata dal controller per i test

**Contesto per l'agente:**

`test-page.php` assembla una pagina HTML completa usando i metodi del controller `ctl_operations` (getHead, getContent, getScript), simulando il framework esterno che in produzione fa la stessa cosa.

`playwright.config.ts` deve puntare al server PHP di test. Opzioni:
- Usare il built-in PHP server: `php -S localhost:8080` dalla root del progetto
- Configurare `webServer` in playwright config per lanciarlo automaticamente

I test E2E verificano l'interfaccia utente completa. Ogni test corrisponde a uno scenario della sezione 3 di EXPLAIN_TEST.md.

**Meccanismo fixture DB per E2E:**
I test devono inserire fixture nel DB prima dell'esecuzione e rimuoverle dopo. Approccio raccomandato:
1. Creare `tests/e2e/global-setup.ts` che usa la libreria Node `pg` per connettersi al DB PostgreSQL (`fa-db:5432`, credenziali da `env_settings.php`)
2. In `global-setup.ts`: inserire fixture con prefisso `TEST_E2E_` in tutte le tabelle rilevanti (policies, operations, drafts, documents)
3. Creare `tests/e2e/global-teardown.ts` per rimuovere tutte le fixture con `DELETE WHERE ... LIKE 'TEST_E2E_%'`
4. Registrare entrambi in `playwright.config.ts` con `globalSetup` e `globalTeardown`
5. Aggiungere `pg` alle dipendenze in `tests/e2e/package.json`

- [ ] **Step 1:** Creare `tests/e2e/package.json` con dipendenze Playwright e `pg`
- [ ] **Step 2:** Creare `tests/e2e/playwright.config.ts` con webServer PHP built-in, globalSetup e globalTeardown
- [ ] **Step 3:** Creare `tests/e2e/global-setup.ts` con inserimento fixture DB (prefisso `TEST_E2E_`) e `tests/e2e/global-teardown.ts` con cleanup
- [ ] **Step 4:** Creare `tests/e2e/test-page.php` che assembla la pagina dal controller
- [ ] **Step 5:** Creare `main-app.spec.ts` con 6 test (E-MAIN-01 → E-MAIN-06). E-MAIN-05 testa che una Card con enabled=false abbia opacità ridotta e non sia cliccabile. E-MAIN-06 testa che un'operazione con isVisible()=false non generi alcuna Card
- [ ] **Step 6:** Creare `new-retrieval-code.spec.ts` con 7 test (E-NRC-01 → E-NRC-07)
- [ ] **Step 7:** Creare `force-annulment.spec.ts` con 6 test (E-FA-01 → E-FA-06)
- [ ] **Step 8:** Creare `reset-document-state.spec.ts` con 4 test (E-RDS-01 → E-RDS-04)
- [ ] **Step 9:** Creare `error-handling.spec.ts` con 3 test (E-ERR-01 → E-ERR-03)
- [ ] **Step 10:** Eseguire: `cd tests/e2e && npm install && npx playwright install && npx playwright test`
- [ ] **Step 11:** Correggere eventuali fallimenti
- [ ] **Step 12:** Commit: `test: add E2E Playwright tests (E-MAIN, E-NRC, E-FA, E-RDS, E-ERR)`

---

### Agent 15 — TEST-SEC

| Proprietà | Valore |
|-----------|--------|
| **Modello** | `sonnet` |
| **Tipo** | Security test — SQL injection, XSS, tampering, info leak |
| **Dipendenze** | Phase 1-5 completate |
| **Durata stimata** | 8-10 minuti |

**Docs da leggere:**
- `docs/EXPLAIN_TEST.md` — sezione 4 (Security Test, scenari S-01 → S-10)
- `docs/API_SPEC.md` — per capire tutti gli endpoint e parametri da attaccare

**Files:**
- Create: `tests/Security/SqlInjectionTest.php` — S-01, S-02
- Create: `tests/Security/XssTest.php` — S-03, S-04
- Create: `tests/Security/ParameterTamperingTest.php` — S-05, S-06
- Create: `tests/Security/HttpMethodTest.php` — S-07
- Create: `tests/Security/InputValidationTest.php` — S-08, S-09
- Create: `tests/Security/InfoLeakTest.php` — S-10

**Contesto per l'agente:**

I test di sicurezza verificano che il backend respinga correttamente input malevoli.

**S-01, S-02 (SQL Injection):** inviare payload come `'; DROP TABLE --`, `' OR '1'='1`, `UNION SELECT` nei parametri GET e POST. Verificare che non causino errori SQL e che i prepared statement neutralizzino i payload.

**S-03, S-04 (XSS):** inserire `<script>alert(1)</script>` nei dati. Verificare che l'output JSON sia escapato (il test verifica che il dato venga restituito come stringa, non come HTML eseguibile).

**S-05, S-06 (Tampering):** inviare id negativi, stringhe, null, array. Verificare risposta di errore.

**S-07 (HTTP Method):** verificare che POST su endpoint GET e viceversa venga rifiutato.

**S-08, S-09 (Validation):** inviare tipo diverso da P/T, contract_number vuoto o con caratteri speciali.

**S-10 (Info Leak):** con `ENV_IS_DEV=false`, verificare che il campo `exception` non sia presente nella risposta errore.

- [ ] **Step 1:** Creare `SqlInjectionTest.php` con payload injection per GET e POST
- [ ] **Step 2:** Creare `XssTest.php` con payload XSS
- [ ] **Step 3:** Creare `ParameterTamperingTest.php` con id invalidi
- [ ] **Step 4:** Creare `HttpMethodTest.php` con metodi HTTP errati
- [ ] **Step 5:** Creare `InputValidationTest.php` con valori invalidi per tipo e contract_number
- [ ] **Step 6:** Creare `InfoLeakTest.php` verificando assenza di `exception` con ENV_IS_DEV=false
- [ ] **Step 7:** Eseguire: `docker compose -f tests/docker-compose.yml run phpunit vendor/bin/phpunit --testsuite security`
- [ ] **Step 8:** Correggere eventuali fallimenti
- [ ] **Step 9:** Commit: `test: add security tests (S-01 through S-10)`

---

## Riepilogo Agenti

| # | Nome | Modello | Fase | Tipo | Files Principali |
|---|------|---------|------|------|------------------|
| 1 | INFRA-FIX | `haiku` | 1 | Fix bug | autoloader.php, AjaxResponseHelper.php |
| 2 | ARCH-CORE | `sonnet` | 1 | Architettura | Interface, Abstract, Factory, AjaxRequest, AuditLogger, AjaxResponseHelper ext. |
| 3 | BE-NRC | `sonnet` | 2 | Backend | NRC Repository + Operation + Ajax |
| 4 | BE-FA | `sonnet` | 2 | Backend | FA Repository + Operation + Ajax |
| 5 | BE-RDS | `sonnet` | 2 | Backend | RDS Repository + Operation + Ajax |
| 6 | BOOT | `sonnet` | 3 | Wiring | autoloader.php, ajax_operations_view.php |
| 7 | FE-MAIN | `sonnet` | 4 | Frontend | app.js, main.css |
| 8 | FE-NRC | `sonnet` | 4 | Frontend | newRetrievalCode.js |
| 9 | FE-FA | `sonnet` | 4 | Frontend | forceAnnulment.js |
| 10 | FE-RDS | `sonnet` | 4 | Frontend | resetDocumentState.js |
| 11 | TEST-INFRA | `haiku` | 2 (║) | Infrastruttura | composer.json, Dockerfile, docker-compose, phpunit.xml, migration |
| 12 | TEST-UNIT | `sonnet` | 6 | Test | 6 file test unitari (+ AccessControlTest) |
| 13 | TEST-INT | `sonnet` | 6 | Test | 4 file test integrazione (+ AccessControlIntegrationTest) |
| 14 | TEST-E2E | `sonnet` | 6 | Test | 7 file test Playwright + global-setup/teardown |
| 15 | TEST-SEC | `sonnet` | 6 | Test | 6 file test sicurezza |

**Totale:** 15 agenti — 2 haiku (Agent 1 INFRA-FIX, Agent 11 TEST-INFRA), 13 sonnet, 0 opus

**Parallelismo massimo:**
- Phase 2: 3 agenti paralleli (BE-NRC ║ BE-FA ║ BE-RDS)
- Phase 4: 3 agenti paralleli (FE-NRC ║ FE-FA ║ FE-RDS), dopo FE-MAIN
- Phase 5: 1 agente (può girare in parallelo con Phase 2-4)
- Phase 6: 4 agenti paralleli (TEST-UNIT ║ TEST-INT ║ TEST-E2E ║ TEST-SEC)

---

## Ordine di Esecuzione

```
Sequenziale: Agent 1 → Agent 2
Parallelo:   Agent 3 ║ Agent 4 ║ Agent 5 ║ Agent 11
Sequenziale: Agent 6
Sequenziale: Agent 7
Parallelo:   Agent 8 ║ Agent 9 ║ Agent 10
Parallelo:   Agent 12 ║ Agent 13 ║ Agent 14 ║ Agent 15
```

**Totale commit:** 15 (uno per agente)
