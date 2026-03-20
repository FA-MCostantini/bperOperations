# Piano dei Test — bperOperations

Standard di riferimento: **BDD (Behavior-Driven Development)**

---

## Legenda

- **Fase U**: Unit Test (PHPUnit) — singoli componenti PHP
- **Fase I**: Integration Test (PHPUnit) — flusso completo backend
- **Fase E**: E2E Test (Playwright) — interfaccia utente
- **Fase S**: Security Test (PHPUnit + Playwright) — vulnerabilita

---

## 1. Unit Test (Fase U)

### U-NRC — newRetrievalCode

| ID      | Scenario                                                                                   | Given                                           | When                                         | Then                                                |
|---------|--------------------------------------------------------------------------------------------|--------------------------------------------------|----------------------------------------------|-----------------------------------------------------|
| U-NRC-01 | Generazione codice riscatto totale                                                        | contract_number="05421607", tipo=T, nessun codice esistente | Calcolo codice                          | Codice = "RT054216071" (n=1)                        |
| U-NRC-02 | Generazione codice riscatto parziale                                                      | contract_number="05435891", tipo=P, nessun codice esistente | Calcolo codice                          | Codice = "RP054358911" (n=1)                        |
| U-NRC-03 | Progressivo corretto con codici esistenti                                                  | Esistono codici con n=1,2,3 per lo stesso contratto/tipo    | Calcolo codice                          | n=4                                                 |
| U-NRC-04 | Progressivo con gap (n=1,3 esistenti)                                                     | Esistono codici con n=1 e n=3                               | Calcolo codice                          | n=4 (MAX+1, non riempie i gap)                      |
| U-NRC-05 | Limite raggiunto (n=9)                                                                     | Esistono 9 codici (n=1..9)                                  | Calcolo codice                          | Errore "Limite massimo codici raggiunto"            |
| U-NRC-06 | Mapping tipo P -> operation_type_code                                                      | tipo=P                                                       | Calcolo operation_type_code             | `_RISPA`                                            |
| U-NRC-07 | Mapping tipo T -> operation_type_code                                                      | tipo=T                                                       | Calcolo operation_type_code             | `_RISTO`                                            |
| U-NRC-08 | Contract number con lunghezza diversa                                                      | contract_number="1234567" (7 cifre)                          | Calcolo codice                          | Codice = "RT12345671"                               |

### U-FA — forceAnnulment

| ID      | Scenario                                                                                   | Given                                           | When                                         | Then                                                |
|---------|--------------------------------------------------------------------------------------------|--------------------------------------------------|----------------------------------------------|-----------------------------------------------------|
| U-FA-01 | Estrazione dati per cancellazione                                                          | Operazione esistente con id=42                   | Richiesta dati cancellazione                | Ritorna bper_policy_number e company_operation_id   |
| U-FA-02 | Operazione inesistente                                                                     | id=99999 non esistente                           | Richiesta dati cancellazione                | Errore "Operazione non trovata"                     |

### U-RDS — resetDocumentState

| ID       | Scenario                                                                                  | Given                                           | When                                         | Then                                                |
|----------|-------------------------------------------------------------------------------------------|--------------------------------------------------|----------------------------------------------|-----------------------------------------------------|
| U-RDS-01 | Conteggio documenti PENDING                                                               | Draft con 3 documenti PENDING                    | Query conteggio                              | Doc. PENDING=3, Doc. ERROR=0                        |
| U-RDS-02 | Conteggio documenti ERROR                                                                 | Draft con 3 documenti ERROR                      | Query conteggio                              | Doc. PENDING=0, Doc. ERROR=3                        |

### U-LOG — Audit Logger

| ID       | Scenario                                                                                  | Given                                           | When                                         | Then                                                |
|----------|-------------------------------------------------------------------------------------------|--------------------------------------------------|----------------------------------------------|-----------------------------------------------------|
| U-LOG-01 | AjaxResponseHelper logga su success con operazione                                        | AjaxRequest catturato, operazione passata        | AjaxResponseHelper::success($data, $op)      | Record inserito con getName() e payload da request  |
| U-LOG-02 | User ID default                                                                           | Nessun utente configurato                        | getCurrentUserId()                           | Ritorna 0                                           |
| U-LOG-03 | AjaxResponseHelper non logga su success senza operazione                                  | AjaxRequest catturato, nessuna operazione        | AjaxResponseHelper::success($data)           | Nessun record in operation_audit_log                |

### U-BOOT — Bootstrapping

| ID        | Scenario                                                                                 | Given                                           | When                                         | Then                                                |
|-----------|------------------------------------------------------------------------------------------|--------------------------------------------------|----------------------------------------------|-----------------------------------------------------|
| U-BOOT-01 | Scoperta automatica via OperationFactory                                                 | 3 classi che implementano OperationInterface     | OperationFactory::discoverAll()              | Ritorna 3 operazioni con name, title, description, icon, enabled |
| U-BOOT-02 | Classe non-operation ignorata                                                            | AjaxResponseHelper, Factory nella stessa cartella | OperationFactory::discoverAll()             | Solo classi Operation nell'elenco                   |
| U-BOOT-03 | Dati presentazione completi                                                              | Classe NewRetrievalCode                          | OperationFactory::discoverAll()              | name, title, description, icon, color, jsPath, enabled tutti popolati |

### U-ACC — Controllo accesso operazioni

| ID       | Scenario                                                                                  | Given                                           | When                                         | Then                                                |
|----------|-------------------------------------------------------------------------------------------|--------------------------------------------------|----------------------------------------------|-----------------------------------------------------|
| U-ACC-01 | Default isVisible e isEnabled                                                             | Operazione che estende AbstractOperation         | Chiama isVisible() e isEnabled()             | Entrambi ritornano true                             |
| U-ACC-02 | Factory lancia 403 se operazione disabilitata                                             | Operazione con isEnabled() override a false      | OperationFactory::create()                   | Lancia RuntimeException, HTTP 403                   |
| U-ACC-03 | Factory restituisce operazione se abilitata                                               | Operazione con isEnabled() = true (default)      | OperationFactory::create()                   | Oggetto Operation valido                            |
| U-ACC-04 | discoverAll esclude operazioni non visibili                                               | Operazione con isVisible() override a false      | OperationFactory::discoverAll()              | Operazione non presente nell'elenco                 |
| U-ACC-05 | discoverAll include flag enabled=false                                                    | Operazione visibile con isEnabled() = false      | OperationFactory::discoverAll()              | Operazione presente con enabled=false               |

---

## 2. Integration Test (Fase I)

### I-NRC — newRetrievalCode (flusso completo)

| ID      | Scenario                                                                                   | Given                                           | When                                         | Then                                                |
|---------|--------------------------------------------------------------------------------------------|--------------------------------------------------|----------------------------------------------|-----------------------------------------------------|
| I-NRC-01 | Inserimento codice via Ajax                                                               | Contract number valido, nessun codice esistente  | POST inserimento                             | Record inserito nel DB, risposta success=true       |
| I-NRC-02 | Inserimento duplicato                                                                     | Codice gia esistente nel DB                      | POST inserimento                             | ON CONFLICT DO NOTHING, nessun errore               |
| I-NRC-03 | Calcolo anteprima via Ajax                                                                | Contract number con 2 codici esistenti           | GET calc                                     | Risposta con codice corretto e next_n=3             |
| I-NRC-04 | Autocomplete via Ajax                                                                     | Almeno un contract number che inizia con "054"   | GET search con q="054"                       | Lista con max 10 risultati                          |
| I-NRC-05 | Tabella codici via Ajax                                                                   | Contract number con codici esistenti             | GET tabella                                  | Lista codici ordinati per insert_date DESC          |
| I-NRC-06 | Audit log dopo inserimento                                                                | Inserimento riuscito                             | Verifica tabella audit                       | Record log presente con operation_name e payload    |

### I-FA — forceAnnulment (flusso completo)

| ID      | Scenario                                                                                   | Given                                           | When                                         | Then                                                |
|---------|--------------------------------------------------------------------------------------------|--------------------------------------------------|----------------------------------------------|-----------------------------------------------------|
| I-FA-01 | Cancellazione completa via Ajax                                                            | Operazione con draft e documenti                 | POST delete                                  | Stato=CANCELLED, draft e docs rimossi, log scritto  |
| I-FA-02 | Transazione atomica su errore                                                              | Errore simulato sul 3o statement                 | POST delete                                  | Rollback completo, nessuna modifica nel DB          |
| I-FA-03 | Elenco operazioni via Ajax                                                                 | Mix di operazioni CANCELLED e non                | GET tabella                                  | Solo operazioni non-CANCELLED restituite            |
| I-FA-04 | Audit log dopo cancellazione                                                               | Cancellazione riuscita                           | Verifica tabella audit                       | Record log presente                                 |

### I-RDS — resetDocumentState (flusso completo)

| ID       | Scenario                                                                                  | Given                                           | When                                         | Then                                                |
|----------|-------------------------------------------------------------------------------------------|--------------------------------------------------|----------------------------------------------|-----------------------------------------------------|
| I-RDS-01 | Cambio stato via Ajax                                                                     | Draft con 3 documenti PENDING                    | POST update                                  | Tutti i documenti passati a ERROR, log scritto      |
| I-RDS-02 | Nessun effetto su documenti gia ERROR                                                     | Draft con documenti ERROR                        | POST update                                  | Nessuna modifica (WHERE download_status='PENDING')  |
| I-RDS-03 | Elenco draft via Ajax                                                                     | Draft con documenti PENDING e ERROR              | GET tabella                                  | Conteggi corretti per PENDING e ERROR               |
| I-RDS-04 | Audit log dopo cambio stato                                                               | Cambio stato riuscito                            | Verifica tabella audit                       | Record log presente                                 |

### I-ACC — Controllo accesso (flusso completo)

| ID       | Scenario                                                                                  | Given                                           | When                                         | Then                                                |
|----------|-------------------------------------------------------------------------------------------|--------------------------------------------------|----------------------------------------------|-----------------------------------------------------|
| I-ACC-01 | OperationFactory blocca con 403 se operazione disabilitata                                | Operazione con isEnabled() = false               | OperationFactory::create() nell'endpoint     | HTTP 403, message="Operazione non disponibile"      |
| I-ACC-02 | Endpoint processa normalmente se visibile=false ma abilitata                              | Operazione con isVisible()=false, isEnabled()=true | OperationFactory::create() nell'endpoint   | Risposta success=true, elaborazione normale         |
| I-ACC-03 | AjaxResponseHelper logga automaticamente su success con operazione                        | Endpoint save con operazione passata a success() | AjaxResponseHelper::success($data, $op)      | Record in operation_audit_log con getName() e payload DTO |
| I-ACC-04 | AjaxResponseHelper non logga su success senza operazione                                  | Endpoint view senza operazione passata           | AjaxResponseHelper::success($data)           | Nessun record in operation_audit_log                |

---

## 3. E2E Test (Fase E)

### E-MAIN — MainApp

| ID       | Scenario                                                                                  | Given                                           | When                                         | Then                                                |
|----------|-------------------------------------------------------------------------------------------|--------------------------------------------------|----------------------------------------------|-----------------------------------------------------|
| E-MAIN-01 | Caricamento pagina e card                                                                | Pagina caricata                                  | Attendo rendering                            | 3 Card visibili con icona, titolo, descrizione      |
| E-MAIN-02 | Apertura modale operazione                                                               | Card visibili                                    | Click su Card newRetrievalCode               | Modale aperta con form dell'operazione              |
| E-MAIN-03 | Persistenza stato form                                                                   | Form compilato nella modale                      | Chiudo e riapro la modale                    | I campi mantengono i valori inseriti                |
| E-MAIN-04 | Refresh dati tabella alla riapertura                                                     | Tabella visibile, chiudo la modale               | Riapro la modale                             | La tabella viene ricaricata dal server              |
| E-MAIN-05 | Card disabilitata non cliccabile                                                         | Operazione con enabled=false nel JSON            | Click sulla Card disabilitata                | Nessuna modale si apre, card ha opacità ridotta     |
| E-MAIN-06 | Operazione invisibile non presente                                                       | Operazione con isVisible()=false                 | Pagina caricata                              | Nessuna Card per quell'operazione                   |

### E-NRC — newRetrievalCode

| ID       | Scenario                                                                                  | Given                                           | When                                         | Then                                                |
|----------|-------------------------------------------------------------------------------------------|--------------------------------------------------|----------------------------------------------|-----------------------------------------------------|
| E-NRC-01 | Autocomplete attivazione                                                                 | Campo polizza vuoto                              | Digito 2 caratteri e attendo 2s              | Lista suggerimenti appare (max 10)                  |
| E-NRC-02 | Bottone disabilitato con campi vuoti                                                     | Form vuoto                                       | Verifico stato bottone                       | Bottone disabilitato                                |
| E-NRC-03 | Bottone abilitato con form completo                                                      | Dropdown e campo polizza compilati               | Verifico stato bottone                       | Bottone abilitato                                   |
| E-NRC-04 | Anteprima codice                                                                         | Entrambi i campi compilati                       | Attendo rendering anteprima                  | Codice calcolato visibile sotto il form             |
| E-NRC-05 | Modale di conferma prima di inserimento                                                  | Bottone abilitato                                | Click su bottone inserimento                 | Modale di conferma appare                           |
| E-NRC-06 | Inserimento e aggiornamento tabella                                                      | Modale di conferma aperta                        | Confermo inserimento                         | Tabella aggiornata, form non ripulito               |
| E-NRC-07 | Avviso limite raggiunto                                                                  | 9 codici gia esistenti per contratto/tipo        | Compilo il form                              | Avviso visibile, bottone disabilitato               |

### E-FA — forceAnnulment

| ID       | Scenario                                                                                  | Given                                           | When                                         | Then                                                |
|----------|-------------------------------------------------------------------------------------------|--------------------------------------------------|----------------------------------------------|-----------------------------------------------------|
| E-FA-01  | Tabella caricata con paginazione                                                         | Modale aperta                                    | Attendo caricamento                          | Tabella con 20 righe, selettore pagina funzionante  |
| E-FA-02  | Cambio dimensione pagina                                                                 | Tabella caricata                                 | Seleziono 50 righe per pagina                | Tabella mostra 50 righe                             |
| E-FA-03  | Filtro testuale                                                                          | Tabella caricata                                 | Digito nel campo filtro                      | Righe filtrate su tutte le colonne                  |
| E-FA-04  | Ordinamento colonna                                                                      | Tabella caricata                                 | Click su header "Data invio"                 | Righe ordinate per data                             |
| E-FA-05  | Cancellazione con conferma                                                               | Tabella caricata                                 | Click cestino → conferma                     | Riga scompare, tabella ricaricata                   |
| E-FA-06  | Cancellazione annullata                                                                  | Modale conferma aperta                           | Annullo                                      | Nessuna modifica, riga ancora presente              |

### E-RDS — resetDocumentState

| ID       | Scenario                                                                                  | Given                                           | When                                         | Then                                                |
|----------|-------------------------------------------------------------------------------------------|--------------------------------------------------|----------------------------------------------|-----------------------------------------------------|
| E-RDS-01 | Righe PENDING e ERROR differenziate                                                      | Draft PENDING e draft ERROR presenti             | Attendo rendering                            | PENDING: sfondo bianco, icona orologio. ERROR: sfondo grigio, icona X |
| E-RDS-02 | Click su riga PENDING                                                                    | Riga PENDING visibile                            | Click icona orologio                         | Modale conferma appare                              |
| E-RDS-03 | Click su riga ERROR (bloccato)                                                           | Riga ERROR visibile                              | Click icona X                                | Nessuna azione eseguita                             |
| E-RDS-04 | Cambio stato e ricaricamento                                                             | Modale conferma aperta                           | Confermo                                     | Tabella ricaricata, riga diventa grigia             |

### E-ERR — Gestione errori

| ID       | Scenario                                                                                  | Given                                           | When                                         | Then                                                |
|----------|-------------------------------------------------------------------------------------------|--------------------------------------------------|----------------------------------------------|-----------------------------------------------------|
| E-ERR-01 | Toast su errore Ajax                                                                     | Errore backend simulato                          | Chiamata Ajax fallisce                       | Toast visibile sovrapposto a tutto                  |
| E-ERR-02 | Toast persistente                                                                        | Toast visibile                                   | Interagisco con altri elementi               | Toast resta visibile                                |
| E-ERR-03 | Chiusura toast                                                                           | Toast visibile                                   | Click sul pulsante chiudi                    | Toast scompare                                      |

---

## 4. Security Test (Fase S)

| ID     | Scenario                                                     | Tipo          | Descrizione                                                                     |
|--------|--------------------------------------------------------------|---------------|---------------------------------------------------------------------------------|
| S-01   | SQL Injection su parametri GET                                | Injection     | Inviare payload SQL injection nei parametri delle chiamate GET e verificare che vengano neutralizzati dai prepared statement |
| S-02   | SQL Injection su parametri POST                               | Injection     | Inviare payload SQL injection nei body POST e verificare che vengano neutralizzati |
| S-03   | XSS su campi visualizzati in tabella                          | XSS           | Inserire payload `<script>alert(1)</script>` nei dati e verificare che vengano escaped nell'output HTML |
| S-04   | XSS su autocomplete                                          | XSS           | Inviare payload XSS nel parametro di ricerca e verificare che i suggerimenti siano escaped |
| S-05   | Parameter tampering su id cancellazione                       | Tampering     | Inviare id non validi (negativi, stringhe, null) e verificare che il backend li rifiuti |
| S-06   | Parameter tampering su id cambio stato                        | Tampering     | Inviare id non validi e verificare gestione errore corretta |
| S-07   | HTTP method enforcement                                       | Method        | Inviare POST dove e atteso GET e viceversa, verificare che il backend rifiuti   |
| S-08   | Input validation su tipo riscatto                             | Validation    | Inviare valori diversi da P/T e verificare che il backend li rifiuti            |
| S-09   | Input validation su contract_number                           | Validation    | Inviare contract_number vuoto o con caratteri speciali e verificare il rifiuto  |
| S-10   | Risposta errore non espone dettagli in produzione             | Info Leak     | Con ENV_IS_DEV=false, verificare che il campo `exception` non sia presente nella risposta |
