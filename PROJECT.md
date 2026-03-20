# bperOperations

App Vue.js per esporre operazioni amministrative sul database BPER.
Lingua dell'interfaccia: italiano.

---

## 1. Stack tecnologico

| Componente | Versione / Modalita                                                   |
|------------|-----------------------------------------------------------------------|
| PHP        | 8.2 (strict_types, enum, readonly, match, constructor promotion)      |
| PostgreSQL | 16                                                                    |
| Vue.js     | 3 via CDN — Options API                                               |
| Bootstrap  | 5 via CDN + Bootstrap Icons                                           |
| CSS custom | Solo se strettamente necessario (`assets-fa/css/operations/main.css`) |

---

## 2. Struttura del progetto

```
bperOperations/
  assets-fa/
    css/operations/           # CSS custom (solo se necessario)
    js/Operations/
      app.js                  # MainApp Vue.js
      newRetrievalCode.js     # App Vue operazione (auto-consistente)
      forceAnnulment.js       # App Vue operazione (auto-consistente)
      resetDocumentState.js   # App Vue operazione (auto-consistente)
  lib/
    autoloader.php            # Autoloader manuale (no PSR-4)
    Database.php              # Connessione PDO PostgreSQL (senza namespace)
    TraitTryQuery.php         # Trait accesso DB con gestione transazioni (senza namespace)
    env_settings.php          # Costanti ambiente (DB host, credenziali, ecc.)
  src/
    controller/Operations/
      ctl_operations.php      # Controller pagina principale
    model/
      Operations/
        OperationInterface.php       # Interfaccia comune a tutte le Operation
        AbstractOperation.php        # Classe abstract con logica condivisa (presentazione)
        OperationFactory.php         # Factory: istanziazione con check isEnabled()
        AjaxResponseHelper.php       # Input/output Ajax + audit logging automatico
        AjaxRequest.php              # DTO: dati della richiesta HTTP
        OperationAuditLogger.php     # Oggetto dedicato alla scrittura del log (usato da AjaxResponseHelper)
        NewRetrievalCode.php         # Operazione: inserimento codice riscatto
        ForceAnnulment.php           # Operazione: annullamento forzato
        ResetDocumentState.php       # Operazione: cambio stato documento
        NewRetrievalCodeRepository.php   # (interno a NewRetrievalCode)
        ForceAnnulmentRepository.php     # (interno a ForceAnnulment)
        ResetDocumentStateRepository.php # (interno a ResetDocumentState)
      ajax/
        ajax_operations_view.php     # Bootstrapping: elenco operazioni disponibili
        ajax_newRetrievalCode_view.php
        ajax_newRetrievalCode_save.php
        ajax_forceAnnulment_view.php
        ajax_forceAnnulment_save.php
        ajax_resetDocumentState_view.php
        ajax_resetDocumentState_save.php
  tests/                      # Dockerfile, docker-compose, fixture, test runner
  plane/                      # Documentazione di progetto (AQ, piani)
```

### 2.1 Convenzioni di naming

- **Namespace PHP**: `FirstAdvisory\FAWill\model\Operations` per i model, `FirstAdvisory\FAWill\controller\Operations` per i controller.
- **File JS**: nome in camelCase, identico al nome dell'operazione (es. `newRetrievalCode.js`).
- **File Ajax**: prefisso `ajax_`, suffisso `_view` (lettura) o `_save` (scrittura).
- **`Database.php` e `TraitTryQuery.php`**: restano senza namespace.
- **`AjaxResponseHelper.php`**: resta nella cartella `model/Operations/` come utility di servizio. Eventuali altri oggetti di utilità vanno nella stessa cartella.

### 2.2 Rilascio di una nuova operazione

Per aggiungere una nuova operazione, creare obbligatoriamente:

1. `assets-fa/js/Operations/<nomeOperazione>.js` — App Vue auto-consistente
2. `src/model/Operations/<NomeOperazione>.php` — Classe PHP che implementa `OperationInterface` ed estende `AbstractOperation`

Opzionalmente, se l'operazione necessita di chiamate Ajax:

1. `src/model/ajax/ajax_<nomeOperazione>_view.php` — Endpoint lettura
2. `src/model/ajax/ajax_<nomeOperazione>_save.php` — Endpoint scrittura

Non servono altre configurazioni: l'operazione comparirà automaticamente nella MainApp. Il logging e il check isEnabled() sono automatici grazie a `AjaxResponseHelper` e `OperationFactory`.

---

## 3. Architettura della MainApp

### 3.1 Controller (`ctl_operations.php`)

Il controller restituisce frammenti HTML tramite i metodi `getHead()`, `getContent()`, `getScript()`. In produzione un framework esterno chiama questi metodi per assemblare la pagina. Per i test, costruire un oggetto che chiama i metodi del controller e monta una pagina HTML completa e funzionante.

### 3.2 Bootstrapping delle operazioni

Il file `ajax_operations_view.php` gestisce le chiamate Ajax della MainApp. Usa un parametro `action` per distinguere le richieste (per flessibilità futura).

Con `action=list`, il backend:
1. Scansiona i file presenti in `src/model/Operations/`
2. Per ogni classe che implementa `OperationInterface`, verifica `isVisible()`
3. Le operazioni con `isVisible() = false` vengono **escluse** dalla risposta
4. Per le operazioni visibili, restituisce le informazioni di presentazione + flag `enabled`

Dati restituiti per ciascuna operazione:
- **Titolo** (es. "Inserimento Codice Riscatto")
- **Descrizione** (es. "Inserisci un nuovo codice di riscatto parziale o totale")
- **Icona Bootstrap Icons** (es. `bi-upc-scan`) — classe CSS dell'icona
- **Colore** (es. `primary`, `warning`, `danger`) — classe Bootstrap per la colorazione
- **Path del file JS** (es. `./assets-fa/js/Operations/newRetrievalCode.js`)
- **Enabled** (`true`/`false`) — indica se l'operazione è attiva o disabilitata

Tutti questi dati sono forniti dall'oggetto PHP dell'operazione (non hardcoded nel frontend).

### 3.3 Layout delle Card

Ogni operazione viene mostrata come una Card Bootstrap contenente:
- Icona grande colorata in alto (icona e colore forniti dal backend)
- Titolo dell'operazione
- Descrizione breve

Cliccando sulla card si apre una modale che contiene l'app Vue dell'operazione.

**Card disabilitata** (quando `enabled = false`):
- Opacità ridotta (0.5)
- Cursor `not-allowed`
- Tooltip Bootstrap: "Operazione temporaneamente non disponibile"
- Click handler disabilitato (non apre la modale)
- Il file JS dell'operazione **non viene caricato**

### 3.4 Caricamento dinamico delle operazioni

Ogni file JS definisce un oggetto Vue Options API come app separata e auto-consistente, riusabile anche al di fuori della MainApp.

Ciclo di vita:
1. **Prima apertura**: il file JS viene caricato via tag `<script>` dinamico, poi l'app Vue viene creata con `Vue.createApp(NomeOperation).mount('#container-dedicato')`.
2. **Chiusura modale**: l'app Vue **non viene smontata**. La modale viene semplicemente nascosta (show/hide CSS).
3. **Riaperture successive**: la modale viene mostrata. Lo stato del form e preservato. I dati delle tabelle vengono ricaricati dal server automaticamente per evitare dati stale.

Questo approccio garantisce:
- Isolamento tra operazioni (ciascuna e una Vue app indipendente)
- Preservazione dello stato utente tra aperture/chiusure
- Dati sempre aggiornati nelle tabelle
- Riusabilità in altri contesti

### 3.5 Chiamate Ajax

Le chiamate Ajax usano path relativi rispetto alla root del progetto (il web server espone direttamente la root).

Esempio: `./src/model/ajax/ajax_newRetrievalCode_view.php?action=tabella`

---

## 4. Architettura delle Operation (backend)

### 4.1 Gerarchia delle classi

```
OperationInterface (interfaccia)
  └── AbstractOperation (classe abstract)
        ├── NewRetrievalCode  ← contiene NewRetrievalCodeRepository
        ├── ForceAnnulment    ← contiene ForceAnnulmentRepository
        └── ResetDocumentState ← contiene ResetDocumentStateRepository

OperationFactory      → istanzia Operation con check isEnabled()
AjaxResponseHelper    → gestisce input (AjaxRequest DTO), output JSON, audit log
AjaxRequest           → DTO con dati richiesta HTTP
OperationAuditLogger  → scrittura log (usato internamente da AjaxResponseHelper)
```

Ogni operazione separa:
- **Logica di business**: nella classe principale dell'operazione
- **Accesso al database**: in un oggetto Repository dedicato, **incapsulato** nell'operazione (l'endpoint ajax non conosce e non accede mai ai Repository)

### 4.2 OperationInterface

Definisce il contratto comune che ogni operazione deve rispettare.

**Metodi di presentazione:**
- `getName(): string` — identificativo dell'operazione (es. `newRetrievalCode`), usato per il log audit
- `getTitle(): string`
- `getDescription(): string`
- `getIcon(): string` — classe Bootstrap Icons (es. `bi-upc-scan`)
- `getColor(): string` — classe Bootstrap (es. `primary`)
- `getJsPath(): string` — path relativo al file JS dell'operazione

**Metodi di controllo accesso:**
- `isVisible(): bool` — se `false`, l'operazione non viene inclusa nella risposta del bootstrapping e la card non appare nel frontend. Le chiamate backend vengono comunque processate normalmente.
- `isEnabled(): bool` — se `false`, la `OperationFactory` lancia un'eccezione 403 al tentativo di creare l'operazione. Nel bootstrapping, l'operazione viene inclusa nella risposta con flag `enabled: false` e la card è disabilitata.

**Matrice di comportamento:**

| isVisible | isEnabled | Card frontend | Backend ajax |
|-----------|-----------|---------------|--------------|
| true      | true      | Visibile, cliccabile | Processa normalmente |
| true      | false     | Visibile, disabilitata (opacità ridotta, cursor not-allowed, tooltip) | Factory lancia 403 |
| false     | true      | Non presente | Processa normalmente |
| false     | false     | Non presente | Factory lancia 403 |

### 4.3 AbstractOperation

Estende la logica comune a tutte le operazioni:
- **Presentazione**: implementazione default dei metodi dell'interfaccia
- **Controllo accesso**: implementazione default di `isVisible()` e `isEnabled()` che ritornano `true`. Le sottoclassi possono sovrascrivere per disabilitare o nascondere l'operazione. Il check `isEnabled()` è applicato dalla `OperationFactory`, non dall'operazione stessa.
- **Accesso DB**: usa il `TraitTryQuery` per le operazioni database. Per le transazioni multi-statement, usa `addQueryInStack()` e `tryQueryStack()`.
- **Repository**: ogni sottoclasse crea il proprio Repository nel costruttore. Il Repository è un dettaglio implementativo interno.

**L'AbstractOperation NON gestisce il logging.** La responsabilità del log è di `AjaxResponseHelper` (vedi sezione 5).

### 4.4 OperationFactory

Unico punto di creazione delle operazioni per gli endpoint ajax. Garantisce che il check `isEnabled()` sia **intrinseco** — non è possibile ottenere un oggetto Operation disabilitato.

```php
class OperationFactory {
    /**
     * Per gli endpoint ajax: crea l'operazione o lancia 403 se disabilitata.
     */
    public static function create(string $className): AbstractOperation
    {
        $fqcn = 'FirstAdvisory\\FAWill\\model\\Operations\\' . $className;
        $operation = new $fqcn();
        if (!$operation->isEnabled()) {
            http_response_code(403);
            throw new \RuntimeException('Operazione non disponibile');
        }
        return $operation;
    }

    /**
     * Per il bootstrapping: restituisce tutte le operazioni visibili
     * con il flag enabled, senza bloccare le disabilitate.
     */
    public static function discoverAll(): array
    {
        // Scansiona src/model/Operations/, filtra OperationInterface,
        // esclude isVisible()=false, include enabled da isEnabled()
    }
}
```

### 4.5 AjaxRequest (DTO)

Oggetto immutabile che incapsula i dati della richiesta HTTP. Creato da `AjaxResponseHelper::getRequest()`.

```php
class AjaxRequest {
    public readonly string $method;     // GET o POST
    public readonly string $action;     // valore del parametro 'action' (se presente)
    public readonly array $params;      // tutti i parametri (GET + POST)

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }
}
```

I metodi delle Operation accettano `AjaxRequest` come input:
```php
$operation->insert($request);          // save
$operation->searchPolicy($request);    // view
```

### 4.6 Utente loggato

L'autenticazione e gestita esternamente a questo progetto. L'Abstract fornisce un metodo `getCurrentUserId(): int` che per ora ritorna `0`. L'integrazione con il sistema di autenticazione sarà fatta in seguito sovrascrivendo questo metodo. Le chiamate Ajax non verificano l'autenticazione (fuori scope).

---

## 5. Audit Trail

### 5.1 Tabella di log

Schema: `public`, Nome tabella: `operation_audit_log`

```sql
CREATE TABLE public.operation_audit_log (
    id         SERIAL PRIMARY KEY,
    operation_name TEXT NOT NULL,
    payload    JSONB NOT NULL,
    user_id    INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT NOW()
);
```

### 5.2 Logica di tracciamento

- L'oggetto `OperationAuditLogger` è responsabile della scrittura del log.
- `AjaxResponseHelper` è responsabile dell'invocazione del logger.
- Il log viene scritto **automaticamente** da `AjaxResponseHelper::success()` quando viene passato l'oggetto Operation.
- I dati del log provengono da:
  - `operation_name`: da `$operation->getName()`
  - `payload`: dal DTO `AjaxRequest` catturato da `AjaxResponseHelper::getRequest()`
  - `user_id`: da `$operation->getCurrentUserId()`
  - `created_at`: `NOW()` nel database
- Il log viene scritto **solo se l'operazione ha successo** (cioè solo quando si chiama `success()` con l'operazione). `error()` non logga mai.
- Nessuna classe Operation deve occuparsi del logging — è completamente trasparente.

### 5.3 Flusso completo di un endpoint save

```php
// ajax_newRetrievalCode_save.php
$request = AjaxResponseHelper::getRequest();                 // 1. cattura input in DTO
$operation = OperationFactory::create('NewRetrievalCode');    // 2. crea operazione (403 se disabilitata)
$result = $operation->insert($request);                       // 3. logica di business (repository interno)
AjaxResponseHelper::success($result, $operation);             // 4. risposta JSON + log audit automatico
```

### 5.4 Flusso completo di un endpoint view

```php
// ajax_newRetrievalCode_view.php
$request = AjaxResponseHelper::getRequest();
$operation = OperationFactory::create('NewRetrievalCode');
switch ($request->action) {
    case 'search':
        AjaxResponseHelper::success($operation->searchPolicy($request));  // no log (nessuna operation passata)
        break;
    case 'tabella':
        AjaxResponseHelper::success($operation->getExistingCodes($request));  // no log
        break;
    case 'calc':
        AjaxResponseHelper::success($operation->calculatePreview($request));  // no log
        break;
}
```

---

## 6. Gestione degli errori

### 6.1 Formato standard

Tutte le risposte Ajax usano `AjaxResponseHelper`:

**Successo**: `{ "success": true, "data": ... }`
**Errore**: `{ "success": false, "message": "...", "exception": "..." }` (exception solo in ambiente dev)
**Operazione disabilitata (403)**: `{ "success": false, "message": "Operazione non disponibile" }` con `http_response_code(403)`

### 6.2 Controllo abilitazione

Il check `isEnabled()` è **intrinseco** nella `OperationFactory`. L'endpoint ajax non deve eseguire alcun controllo manuale: se l'operazione è disabilitata, la Factory lancia un'eccezione 403 prima che l'endpoint ottenga l'oggetto. Il blocco try/catch standard dell'endpoint cattura l'eccezione e la trasforma in risposta JSON via `AjaxResponseHelper::error()`.

### 6.3 Visualizzazione errori

Gli errori vengono mostrati come **Toast Bootstrap sovrapposto a tutto** (non dentro la modale). Il toast resta visibile fino a chiusura esplicita da parte dell'utente.

---

## 7. Le operazioni

### 7.1 newRetrievalCode — Inserimento codice riscatto

**Scopo**: aggiungere codici di riscatto nella tabella `ntt_bper.t_ath_policy_auth_code` (362.093 record attuali). Non sono previste modifica o eliminazione di codici.

#### 7.1.1 Interfaccia (form)

Due campi disposti in linea orizzontale con le label sopra a ciascuno:

1. **Dropdown** — Tipo riscatto: "Riscatto Parziale" (P) / "Riscatto Totale" (T)
2. **Campo testo** — Codice polizza (contract_number) con autocomplete

**Autocomplete del codice polizza**:
- Sorgente dati: `SELECT bper_policy_number FROM ntt_bper.v_policy` (~500.000 record)
- Filtro server-side con `LIKE`
- Debounce: 2 secondi
- Minimo caratteri per attivare la ricerca: 2
- Massimo risultati restituiti: 10

**Comportamento del form**:
- Quando il codice polizza e stato inserito: mostrare sotto al form la tabella dei codici gia presenti per quel contratto (tutti i tipi, sia P che T).
- Quando entrambi i campi sono compilati: mostrare l'anteprima del codice che verrà inserito (calcolata dal backend via `GET:calc`).
- Il bottone di inserimento (icona `bi-plus-circle`) si abilita solo quando entrambi i campi sono compilati.
- Alla pressione del bottone: modale di conferma, poi inserimento e aggiornamento tabella.
- Il form **non viene ripulito** dopo l'inserimento (la tabella resta visibile).

**Limite progressivo**: quando il progressivo raggiunge 9 (massimo), il backend restituisce un errore specifico ("Limite massimo codici raggiunto per questo contratto e tipo"), il bottone resta disabilitato e viene mostrato un avviso sotto l'anteprima.

#### 7.1.2 Tabella codici esistenti

- Tabella: `ntt_bper.t_ath_policy_auth_code`
- Filtro: per `bper_contract_number` (mostra sia tipo P che T)
- Colonne visibili: `insert_date`, `code`, `operation_type_code`
- Ordinamento default: `insert_date DESC, operation_type_code`

#### 7.1.3 Logica di generazione del codice

Formato: `R<P/T><contract_number><n>`

| Componente        | Descrizione                                                      |
|-------------------|------------------------------------------------------------------|
| `R`               | Lettera fissa                                                    |
| `P` oppure `T`    | P = Riscatto Parziale, T = Riscatto Totale                       |
| `contract_number` | Codice polizza da `ntt_bper.v_policy` (lunghezza variabile)      |
| `n`               | Progressivo a singola cifra (1-9), vincolo progettuale massimo 9 |

**Calcolo del progressivo `n`**:
1. Cercare tutti i codici esistenti con prefisso `R<P/T><contract_number>` nella tabella `t_ath_policy_auth_code`
2. Prendere l'ultimo carattere di ciascun codice trovato
3. Trovare il valore massimo
4. Sommare 1

NON usare `COUNT+1`.

**Campi da popolare in fase di inserimento**:

| Colonna                | Valore                       |
|------------------------|------------------------------|
| `code`                 | `R<P/T><contract_number><n>` |
| `insert_date`          | `NOW()` (tipo `timestamp`)   |
| `bper_contract_number` | `<contract_number>`          |
| `operation_type_code`  | `_RISPA` se P, `_RISTO` se T |

**Statement di inserimento**:
```sql
INSERT INTO ntt_bper.t_ath_policy_auth_code
       (code, insert_date, bper_contract_number, operation_type_code)
VALUES (:code, NOW(), :bper_contract_number, :operation_type_code)
ON CONFLICT DO NOTHING;
```

Esiste un vincolo UNIQUE sulla colonna `code`.

#### 7.1.4 Chiamate Ajax

| Metodo | Action  | Descrizione                                                                                   |
|--------|---------|-----------------------------------------------------------------------------------------------|
| GET    | tabella | Codici esistenti filtrati per `bper_contract_number`                                          |
| GET    | calc    | Calcola il codice che verrebbe inserito (anteprima). Restituisce errore se limite 9 raggiunto |
| GET    | search  | Autocomplete: cerca contract_number in `v_policy` (max 10 risultati)                          |
| POST   | insert  | Inserisce il nuovo codice                                                                     |

---

### 7.2 forceAnnulment — Annullamento forzato di un'operazione

**Scopo**: annullare operazioni inserite per errore nella tabella `ntt_bper.t_policy_operation` (14.000+ record, ~50 nuovi/giorno). Le righe piu rilevanti sono le piu recenti.

#### 7.2.1 Interfaccia (tabella)

Tabella ordinabile, paginata e filtrabile.

- **Paginazione**: default 20 righe per pagina. Opzioni: 20, 50, 100.
- **Filtro**: libero testuale su tutte le colonne.
- **Ordinamento**: per colonna, default per data invio decrescente.
- **Interfaccia flessibile**: il frontend si adatta dinamicamente alle colonne restituite dal backend. Se vengono aggiunti/rimossi campi nello statement SQL, l'interfaccia si adegua senza modifiche al JS.

**Prima colonna**: icona cestino (`bi-trash`) per la cancellazione. Alla pressione: modale di conferma, poi esecuzione e ricaricamento tabella dal server.

#### 7.2.2 Query di lettura

```sql
SELECT pot.operation_desc       AS "Operazione"
     , pot.operation_code       AS "Codice"
     , po.operation_status      AS "Stato"
     , po.company_code          AS "Compagnia"
     , po.company_policy_number AS "Codice Compagnia"
     , po.bper_policy_number    AS "Codice Banca"
     , po.premium               AS "Premio"
     , po.sent_date             AS "Data invio"
     , po.user_abi              AS "ABI"
     , po.user_agency_code      AS "AGENZIA"
     , po.user_cab              AS "CAB"
     , po.iban                  AS "IBAN"
     , po.customer_ndg          AS "NDG"
     , po.fiscal_code           AS "Cod. fisc."
     , po.fiscal_code_lgrp      AS "LGRP"
     , po.code_rapporto         AS "Codice Rapporto"
     , po.product_code          AS "Codice Prodotto"
     , po.id  -- campo nascosto, serve solo per la POST
  FROM ntt_bper.t_policy_operation po
 INNER JOIN ntt_bper.t_param_operation_type pot
    ON po.t_param_operation_type_id = pot.id
 WHERE po.operation_status != 'CANCELLED';
```

Il campo `po.id` non e visibile in tabella ma viene passato al backend nella POST. Gli altri campi necessari alla cancellazione (`bper_policy_number`, `company_operation_id`) vengono ricavati dal backend a partire dall'id.

#### 7.2.3 Logica di cancellazione (transazione atomica)

Usare `addQueryInStack()` e `tryQueryStack()` del `TraitTryQuery` per eseguire i 3 statement in un'unica transazione:

```sql
-- 0. Estrazione dati per la cancellazione
SELECT bper_policy_number
     , company_operation_id
  FROM ntt_bper.t_policy_operation
 WHERE id = :id_to_delete;

-- 1. Soft-Delete: aggiornamento stato operazione
UPDATE ntt_bper.t_policy_operation
   SET operation_status = 'CANCELLED'
     , cancelled_date = NOW()
 WHERE bper_policy_number = :bper_policy_number
   AND company_operation_id = :company_operation_id;

-- 2. Hard-Delete: documenti dell'operazione
DELETE FROM ntt_bper.t_int_policy_operation_docs doc
      USING ntt_bper.t_policy_operation_draft draft
       JOIN ntt_bper.t_policy_operation op
         ON draft.policy_operation_id = op.id
      WHERE doc.t_policy_operation_draft_id = draft.id
        AND op.bper_policy_number = :bper_policy_number
        AND op.company_operation_id = :company_operation_id;

-- 3. Hard-Delete: draft dell'operazione
DELETE FROM ntt_bper.t_policy_operation_draft
 WHERE policy_operation_id = (
           SELECT id
             FROM ntt_bper.t_policy_operation
            WHERE bper_policy_number = :bper_policy_number
              AND company_operation_id = :company_operation_id
       )
   AND bper_policy_number = :bper_policy_number;
```

#### 7.2.4 Chiamate Ajax

| Metodo | Action  | Descrizione                                                                            |
|--------|---------|----------------------------------------------------------------------------------------|
| GET    | tabella | Elenco operazioni (escluse CANCELLED). Chiamata all'apertura e dopo ogni cancellazione |
| POST   | delete  | Cancellazione di una specifica operazione per id                                       |

---

### 7.3 resetDocumentState — Cambio di stato di un documento

**Scopo**: forzare lo stato dei documenti da PENDING a ERROR per i draft della tabella `ntt_bper.t_policy_operation_draft` / `ntt_bper.t_ath_policy_operation_docs`. I record non sono mai moltissimi.

#### 7.3.1 Interfaccia (tabella)

Per uniformità con forceAnnulment: tabella ordinabile, paginata e filtrabile (stesse regole: default 20 righe, opzioni 50/100, filtro testuale su tutte le colonne).

**Interfaccia flessibile**: come per forceAnnulment, il frontend si adatta dinamicamente alle colonne restituite dal backend.

**Prima colonna — icona di stato/azione**:
- Righe con documenti PENDING: icona orologio con punto esclamativo (es. `bi-clock-history`), riga con sfondo **bianco** (abilitata). Cliccabile per forzare il cambio di stato a ERROR. Modale di conferma prima dell'esecuzione.
- Righe con documenti ERROR: icona X rossa (es. `bi-x-circle-fill` in rosso), riga con sfondo **grigio** (disabilitata). Nessuna azione possibile.

**Colonne aggiuntive calcolate**:
- `"Doc. PENDING"`: conteggio dei documenti in stato PENDING per ciascun draft
- `"Doc. ERROR"`: conteggio dei documenti in stato ERROR per ciascun draft

Queste colonne servono a dare evidenza all'utente dell'effetto della propria operazione. Non ci si aspettano situazioni miste (tutti i documenti di un draft sono nello stesso stato), ma i conteggi rendono visibile lo stato.

Dopo il cambio di stato: ricaricare la lista dal server.

#### 7.3.2 Query di lettura

La query deve mostrare le righe che hanno almeno un documento in stato PENDING o ERROR, raggruppate per draft con i conteggi per stato. L'operazione agisce per singolo draft.

```sql
SELECT pot.operation_desc                AS "Operazione"
     , pot.operation_code                AS "Codice"
     , po.operation_status               AS "Stato"
     , po.company_code                   AS "Compagnia"
     , po.company_policy_number          AS "Codice Compagnia"
     , po.bper_policy_number             AS "Codice Banca"
     , po.premium                        AS "Premio"
     , po.sent_date                      AS "Data invio"
     , po.user_abi                       AS "ABI"
     , po.user_agency_code               AS "AGENZIA"
     , po.user_cab                       AS "CAB"
     , po.iban                           AS "IBAN"
     , po.customer_ndg                   AS "NDG"
     , po.fiscal_code                    AS "Cod. fisc."
     , po.fiscal_code_lgrp               AS "LGRP"
     , po.code_rapporto                  AS "Codice Rapporto"
     , po.product_code                   AS "Codice Prodotto"
     , COUNT(*) FILTER (WHERE tapod.download_status = 'PENDING') AS "Doc. PENDING"
     , COUNT(*) FILTER (WHERE tapod.download_status = 'ERROR')   AS "Doc. ERROR"
     , tpod.id  -- campo nascosto, serve per la POST
  FROM ntt_bper.t_policy_operation_draft tpod
 INNER JOIN ntt_bper.t_ath_policy_operation_docs tapod
    ON tpod.id = tapod.t_policy_operation_draft_id
 INNER JOIN ntt_bper.t_policy_operation po
    ON po.id = tpod.policy_operation_id
 INNER JOIN ntt_bper.t_param_operation_type pot
    ON po.t_param_operation_type_id = pot.id
 WHERE tapod.download_status IN ('PENDING', 'ERROR')
 GROUP BY tpod.id
        , pot.operation_desc
        , pot.operation_code
        , po.operation_status
        , po.company_code
        , po.company_policy_number
        , po.bper_policy_number
        , po.premium
        , po.sent_date
        , po.user_abi
        , po.user_agency_code
        , po.user_cab
        , po.iban
        , po.customer_ndg
        , po.fiscal_code
        , po.fiscal_code_lgrp
        , po.code_rapporto
        , po.product_code;
```

Nota: non ci si aspettano piu draft per la stessa operation, ma se dovesse capitare le righe si duplicano correttamente (si lavora per singolo draft).

#### 7.3.3 Logica di cambio stato

Cambia tutti i documenti PENDING del draft selezionato in ERROR:

```sql
UPDATE ntt_bper.t_ath_policy_operation_docs tapod
   SET download_status = 'ERROR'
  FROM ntt_bper.t_policy_operation_draft tpod
 WHERE tpod.id = tapod.t_policy_operation_draft_id
   AND tpod.id = :id
   AND tapod.download_status = 'PENDING';
```

#### 7.3.4 Chiamate Ajax

| Metodo | Action  | Descrizione                                                                                |
|--------|---------|--------------------------------------------------------------------------------------------|
| GET    | tabella | Elenco draft con documenti PENDING o ERROR. Chiamata all'apertura e dopo ogni cambio stato |
| POST   | update  | Cambio stato PENDING -> ERROR per tutti i documenti di un draft specifico                  |

---

## 8. Test

### 8.1 Tipologie di test

- **Unit test**: correttezza dei singoli componenti PHP (repository, logica di business, generazione codice)
- **Integration test**: funzionalità end-to-end delle operazioni esposte
- **Security test**: verifica assenza di vulnerabilità (SQL injection, XSS, CSRF)

### 8.2 Framework

- **PHP**: PHPUnit (versione compatibile con PHP 8.2)
- **Frontend E2E**: Playwright

### 8.3 Ambiente Docker

La cartella `tests/` contiene Dockerfile, `docker-compose.yml` e tutto il necessario.

L'immagine si collega alla rete Docker esistente dove gira il database:

```yaml
networks:
  fa-universe:
    name: fa-universe
    external: true
```

**Database di test**: stesso database di sviluppo `fa-dev-bper` (host: `fa-db`, porta: `5432`), stesse tabelle. Non si e in produzione, l'ambiente e dedicato ai tests.

### 8.4 Fixture e cleanup

Le fixture inseriscono dati fittizi direttamente nelle tabelle reali del database. Al termine di ogni test, i dati inseriti devono essere rimossi per non lasciare il database sporco.

### 8.5 Report e esecuzione

- Al termine dei test, generare un report leggibile sia dagli agenti di sviluppo (per correggere il codice) sia dall'utente (per verificare il lavoro finale).
- Nella root del progetto, predisporre uno script eseguibile per lanciare l'intera suite di test.
