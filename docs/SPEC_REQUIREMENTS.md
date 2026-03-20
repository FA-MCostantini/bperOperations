# Specifica dei Requisiti — bperOperations

Standard di riferimento: **ISO/IEC/IEEE 29148:2018**

---

## 1. Introduzione

### 1.1 Scopo

Questo documento definisce i vincoli infrastrutturali, le dipendenze tecnologiche e i requisiti funzionali e non funzionali del sistema bperOperations.

### 1.2 Ambito del sistema

bperOperations e un'applicazione web che espone operazioni amministrative su database PostgreSQL tramite un'interfaccia Vue.js. Il sistema e progettato per essere estensibile: nuove operazioni possono essere aggiunte senza modificare il codice esistente.

### 1.3 Definizioni e acronimi

Vedi `GLOSSARIO.md` per la terminologia di dominio.

---

## 2. Vincoli infrastrutturali

### 2.1 Ambiente di runtime

| Vincolo              | Specifica                                                |
|----------------------|----------------------------------------------------------|
| Runtime server       | PHP 8.2 con estensione PDO PostgreSQL (`pdo_pgsql`)      |
| Database             | PostgreSQL 16                                            |
| Web server           | Qualsiasi (Apache/Nginx), espone la root del progetto    |
| Rete                 | Docker network `fa-universe` (ambiente di sviluppo/test) |
| Protocollo           | HTTPS in produzione (gestito esternamente)               |

### 2.2 Dipendenze esterne (CDN)

| Libreria             | Versione  | Canale  |
|----------------------|-----------|---------|
| Vue.js               | 3.x       | CDN (unpkg) — `vue.global.prod.js` |
| Bootstrap CSS        | 5.3.x     | CDN (jsdelivr) |
| Bootstrap JS Bundle  | 5.3.x     | CDN (jsdelivr) |
| Bootstrap Icons      | 1.11.x    | CDN (jsdelivr) |
| SortableJS           | 1.15.x    | CDN (jsdelivr) |

Nessuna dipendenza npm/composer. Tutte le librerie sono caricate via CDN.

### 2.3 Dipendenze interne

| Componente         | Posizione              | Vincolato a                          |
|--------------------|------------------------|--------------------------------------|
| `Database.php`     | `lib/`                 | PDO, costanti da `env_settings.php`  |
| `TraitTryQuery.php`| `lib/`                 | `Database.php`, PDO                  |
| `env_settings.php` | `lib/`                 | Costanti PHP (definite staticamente) |
| `autoloader.php`   | `lib/`                 | Inclusione manuale, no PSR-4         |

### 2.4 Vincoli di compatibilita

| Vincolo              | Specifica                             |
|----------------------|---------------------------------------|
| Browser              | Nessun vincolo specifico (evergreen)  |
| Lingua               | Solo italiano                         |
| Autenticazione       | Fuori scope, gestita esternamente     |
| Autorizzazione       | Fuori scope, nessun middleware        |

---

## 3. Requisiti funzionali

### 3.1 RF-MAIN — MainApp

| ID       | Requisito                                                                                                         | Priorita |
|----------|-------------------------------------------------------------------------------------------------------------------|----------|
| RF-MAIN-01 | Il sistema DEVE mostrare l'elenco delle operazioni disponibili come Card Bootstrap.                             | MUST     |
| RF-MAIN-02 | Il sistema DEVE caricare dinamicamente l'elenco delle operazioni dal backend via Ajax (`action=list`).          | MUST     |
| RF-MAIN-03 | Ogni Card DEVE mostrare: icona colorata, titolo, descrizione — tutti forniti dal backend.                       | MUST     |
| RF-MAIN-04 | Cliccando su una Card, il sistema DEVE aprire una modale contenente l'app Vue dell'operazione.                  | MUST     |
| RF-MAIN-05 | Alla prima apertura, il file JS dell'operazione DEVE essere caricato dinamicamente via `<script>`.             | MUST     |
| RF-MAIN-06 | Alla chiusura della modale, l'app Vue NON DEVE essere smontata (persistenza stato).                             | MUST     |
| RF-MAIN-07 | Alla riapertura della modale, i dati delle tabelle DEVONO essere ricaricati dal server.                         | MUST     |
| RF-MAIN-08 | Il sistema DEVE scoprire automaticamente nuove operazioni senza configurazione aggiuntiva.                      | MUST     |

### 3.2 RF-NRC — newRetrievalCode

| ID        | Requisito                                                                                                        | Priorita |
|-----------|------------------------------------------------------------------------------------------------------------------|----------|
| RF-NRC-01 | Il sistema DEVE mostrare un form con dropdown (Riscatto Parziale/Totale) e campo testo (codice polizza).        | MUST     |
| RF-NRC-02 | Il campo codice polizza DEVE fornire autocomplete server-side (debounce 2s, min 2 char, max 10 risultati).      | MUST     |
| RF-NRC-03 | Quando il codice polizza e inserito, il sistema DEVE mostrare la tabella dei codici esistenti per quel contratto.| MUST     |
| RF-NRC-04 | La tabella DEVE mostrare tutti i tipi (P e T), ordinati per `insert_date DESC, operation_type_code`.            | MUST     |
| RF-NRC-05 | Quando entrambi i campi sono compilati, il sistema DEVE mostrare l'anteprima del codice (calcolata dal backend).| MUST     |
| RF-NRC-06 | Il bottone di inserimento DEVE abilitarsi solo quando entrambi i campi sono compilati.                          | MUST     |
| RF-NRC-07 | Prima dell'inserimento, il sistema DEVE chiedere conferma tramite modale.                                       | MUST     |
| RF-NRC-08 | Dopo l'inserimento, il form NON DEVE essere ripulito e la tabella DEVE aggiornarsi.                             | MUST     |
| RF-NRC-09 | Il progressivo `n` DEVE essere una singola cifra (1-9). Al raggiungimento del limite 9, il sistema DEVE bloccare l'inserimento e mostrare un avviso. | MUST |
| RF-NRC-10 | La modifica e l'eliminazione di codici NON DEVONO essere previste.                                              | MUST     |

### 3.3 RF-FA — forceAnnulment

| ID       | Requisito                                                                                                         | Priorita |
|----------|-------------------------------------------------------------------------------------------------------------------|----------|
| RF-FA-01 | Il sistema DEVE mostrare una tabella ordinabile, paginata (20/50/100) e filtrabile (testo libero su tutte le colonne). | MUST |
| RF-FA-02 | L'interfaccia DEVE adattarsi dinamicamente alle colonne restituite dal backend.                                  | MUST     |
| RF-FA-03 | La prima colonna DEVE contenere un'icona cestino (`bi-trash`) per la cancellazione.                              | MUST     |
| RF-FA-04 | Prima della cancellazione, il sistema DEVE chiedere conferma tramite modale.                                     | MUST     |
| RF-FA-05 | La cancellazione DEVE eseguire soft-delete + hard-delete di docs e drafts in transazione atomica.                | MUST     |
| RF-FA-06 | Dopo la cancellazione, la tabella DEVE essere ricaricata dal server.                                             | MUST     |
| RF-FA-07 | Il campo `id` DEVE essere presente nei dati ma NON visibile in tabella.                                          | MUST     |

### 3.4 RF-RDS — resetDocumentState

| ID        | Requisito                                                                                                        | Priorita |
|-----------|------------------------------------------------------------------------------------------------------------------|----------|
| RF-RDS-01 | Il sistema DEVE mostrare una tabella con i draft che hanno documenti in stato PENDING o ERROR.                   | MUST     |
| RF-RDS-02 | La tabella DEVE mostrare le colonne calcolate "Doc. PENDING" e "Doc. ERROR" per ciascun draft.                  | MUST     |
| RF-RDS-03 | Le righe PENDING DEVONO avere sfondo bianco e icona orologio cliccabile. Le righe ERROR DEVONO avere sfondo grigio e icona X rossa non cliccabile. | MUST |
| RF-RDS-04 | Il cambio stato DEVE agire su tutti i documenti PENDING del draft selezionato in un'unica operazione.           | MUST     |
| RF-RDS-05 | Prima del cambio stato, il sistema DEVE chiedere conferma tramite modale.                                       | MUST     |
| RF-RDS-06 | Dopo il cambio stato, la tabella DEVE essere ricaricata dal server.                                             | MUST     |
| RF-RDS-07 | L'operazione DEVE lavorare per singolo draft (se esistono piu draft per la stessa operation, si duplicano).      | MUST     |

### 3.5 RF-LOG — Audit Trail

| ID        | Requisito                                                                                                       | Priorita |
|-----------|-------------------------------------------------------------------------------------------------------------------|----------|
| RF-LOG-01 | Ogni operazione che modifica dati DEVE generare un record nella tabella `public.operation_audit_log`.             | MUST     |
| RF-LOG-02 | Il log DEVE essere scritto solo in caso di successo dell'operazione.                                              | MUST     |
| RF-LOG-03 | Il payload del log DEVE contenere i dati inviati dal frontend al backend.                                         | MUST     |
| RF-LOG-04 | Il log DEVE essere trasparente: gestito da `AjaxResponseHelper`, senza codice esplicito nelle Operation.          | MUST     |
| RF-LOG-05 | Il campo `user_id` DEVE essere `INT NOT NULL DEFAULT 0` (integrazione futura).                                    | MUST     |

### 3.6 RF-ACC — Controllo accesso operazioni

| ID         | Requisito                                                                                                        | Priorita |
|------------|------------------------------------------------------------------------------------------------------------------|----------|
| RF-ACC-01  | `OperationInterface` DEVE definire i metodi `isVisible(): bool` e `isEnabled(): bool`.                          | MUST     |
| RF-ACC-02  | `AbstractOperation` DEVE fornire implementazione default di `isVisible()` e `isEnabled()` che ritornano `true`. | MUST     |
| RF-ACC-03  | Le operazioni con `isVisible() = false` NON DEVONO essere incluse nella risposta di `action=list`.             | MUST     |
| RF-ACC-04  | Le operazioni con `isEnabled() = false` DEVONO essere incluse nella risposta con flag `enabled: false`.         | MUST     |
| RF-ACC-05  | La Card di un'operazione disabilitata DEVE avere opacità ridotta, cursor `not-allowed` e tooltip informativo.   | MUST     |
| RF-ACC-06  | Il click su una Card disabilitata NON DEVE aprire la modale né caricare il file JS.                             | MUST     |
| RF-ACC-07  | `OperationFactory::create()` DEVE lanciare eccezione HTTP 403 se `isEnabled() = false`.                        | MUST     |
| RF-ACC-08  | Le chiamate backend a un'operazione con `isVisible() = false` e `isEnabled() = true` DEVONO essere processate normalmente. | MUST |
| RF-ACC-09  | Gli endpoint ajax DEVONO usare `OperationFactory::create()` per ottenere l'operazione (mai istanziazione diretta). | MUST   |

### 3.7 RF-DTA — Gestione dati richiesta e logging

| ID         | Requisito                                                                                                        | Priorita |
|------------|------------------------------------------------------------------------------------------------------------------|----------|
| RF-DTA-01  | `AjaxResponseHelper` DEVE esporre `getRequest(): AjaxRequest` che cattura i dati della richiesta in un DTO.    | MUST     |
| RF-DTA-02  | I metodi delle Operation DEVONO accettare `AjaxRequest` come input (non `$_GET`/`$_POST` diretti).             | MUST     |
| RF-DTA-03  | `AjaxResponseHelper::success()` DEVE accettare un parametro opzionale `AbstractOperation` per il logging.       | MUST     |
| RF-DTA-04  | Quando l'operazione è passata a `success()`, il log audit DEVE essere scritto automaticamente.                  | MUST     |
| RF-DTA-05  | I Repository DEVONO essere incapsulati nelle Operation. Gli endpoint ajax NON DEVONO accedere ai Repository.    | MUST     |
| RF-DTA-06  | `OperationInterface` DEVE definire `getName(): string` per l'identificazione nel log audit.                     | MUST     |

### 3.8 RF-ERR — Gestione errori

| ID        | Requisito                                                                                                       | Priorita |
|-----------|-------------------------------------------------------------------------------------------------------------------|----------|
| RF-ERR-01 | Tutte le risposte Ajax DEVONO usare il formato `AjaxResponseHelper`.                                             | MUST     |
| RF-ERR-02 | Gli errori DEVONO essere mostrati come Toast Bootstrap sovrapposto a tutti gli elementi.                          | MUST     |
| RF-ERR-03 | Il Toast DEVE restare visibile fino a chiusura esplicita dell'utente.                                             | MUST     |
| RF-ERR-04 | Le risposte 403 (operazione disabilitata) DEVONO usare il messaggio "Operazione non disponibile".                | MUST     |

---

## 4. Requisiti non funzionali

| ID       | Categoria       | Requisito                                                                               |
|----------|-----------------|-----------------------------------------------------------------------------------------|
| RNF-01   | Estensibilita   | Aggiungere una nuova operazione DEVE richiedere solo la creazione di 2 file (PHP + JS). |
| RNF-02   | Sicurezza       | Tutte le query SQL DEVONO usare prepared statement con named parameters (`:param`).     |
| RNF-03   | Sicurezza       | Ogni output HTML DEVE essere escaped con `htmlspecialchars()`.                          |
| RNF-04   | Sicurezza       | Ogni input utente DEVE essere validato prima dell'uso.                                  |
| RNF-05   | Performance     | L'autocomplete DEVE essere server-side con debounce (500k record nella vista).          |
| RNF-06   | Manutenibilita  | Il frontend delle tabelle DEVE adattarsi dinamicamente alle colonne del backend.        |
| RNF-07   | Testabilita     | Il sistema DEVE essere testabile in ambiente Docker isolato.                            |
| RNF-08   | Affidabilita    | Le operazioni multi-statement DEVONO essere eseguite in transazione atomica.            |
| RNF-09   | Sicurezza       | I controlli `isEnabled()` DEVONO essere applicati lato backend (non solo frontend).     |
