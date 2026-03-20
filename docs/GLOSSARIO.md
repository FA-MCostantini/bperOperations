# Glossario — bperOperations

Standard di riferimento: **DDD (Domain-Driven Design) — Ubiquitous Language**

---

## Termini di dominio

| Termine                   | Definizione                                                                                                  | Contesto d'uso                         |
|---------------------------|--------------------------------------------------------------------------------------------------------------|----------------------------------------|
| **Operazione** (Operation) | Unita funzionale che espone un'azione amministrativa sul database. Composta da classe PHP, repository e app Vue. | Architettura generale                 |
| **MainApp**               | Applicazione Vue.js principale che mostra l'elenco delle operazioni e gestisce l'apertura delle modali.       | Frontend                              |
| **Card**                  | Componente Bootstrap che rappresenta visualmente un'operazione nella MainApp (icona, titolo, descrizione).    | Frontend                              |
| **Bootstrapping**         | Fase iniziale in cui la MainApp richiede al backend l'elenco delle operazioni disponibili.                   | Architettura MainApp                  |
| **Codice Riscatto**       | Codice alfanumerico nel formato `R<P/T><contract_number><n>` inserito nella tabella `t_ath_policy_auth_code`. | Operazione newRetrievalCode            |
| **Riscatto Parziale**     | Tipo di riscatto identificato dalla lettera `P` nel codice e dal valore `_RISPA` nel campo `operation_type_code`. | Operazione newRetrievalCode        |
| **Riscatto Totale**       | Tipo di riscatto identificato dalla lettera `T` nel codice e dal valore `_RISTO` nel campo `operation_type_code`. | Operazione newRetrievalCode        |
| **Contract Number**       | Codice polizza (`bper_policy_number`) proveniente dalla vista `ntt_bper.v_policy`. Lunghezza variabile.       | Operazione newRetrievalCode            |
| **Progressivo (n)**       | Singola cifra (1-9) che distingue codici diversi per lo stesso contratto e tipo. Vincolo massimo: 9.         | Operazione newRetrievalCode            |
| **Annullamento Forzato**  | Cancellazione di un'operazione inserita per errore. Soft-delete sulla `t_policy_operation` + hard-delete di docs e drafts. | Operazione forceAnnulment     |
| **Soft-Delete**           | Cambio stato a `CANCELLED` con data di cancellazione, senza rimozione fisica del record.                     | Operazione forceAnnulment              |
| **Hard-Delete**           | Rimozione fisica dei record dalle tabelle `t_int_policy_operation_docs` e `t_policy_operation_draft`.         | Operazione forceAnnulment              |
| **Draft**                 | Record nella tabella `t_policy_operation_draft` che rappresenta una bozza di operazione associata a documenti.| Operazione resetDocumentState          |
| **Documento** (Doc)       | Record nella tabella `t_ath_policy_operation_docs` associato a un draft. Ha uno stato di download (`download_status`). | Operazione resetDocumentState |
| **Download Status**       | Stato del documento: `PENDING` (in attesa di elaborazione) o `ERROR` (forzato manualmente).                   | Operazione resetDocumentState          |
| **Audit Log**             | Record nella tabella `public.operation_audit_log` che traccia chi ha fatto cosa e quando.                     | Trasversale                            |

## Termini tecnici

| Termine                   | Definizione                                                                                                  | Contesto d'uso                         |
|---------------------------|--------------------------------------------------------------------------------------------------------------|----------------------------------------|
| **TraitTryQuery**         | Trait PHP che fornisce accesso al database con gestione automatica di transazioni, prepared statement e error handling. | Infrastruttura                  |
| **QueryStack**            | Meccanismo del `TraitTryQuery` per accodare statement SQL (`addQueryInStack`) ed eseguirli in un'unica transazione (`tryQueryStack`). | Transazioni multi-statement |
| **AjaxResponseHelper**    | Classe utility che incapsula le risposte Ajax in un formato JSON standard (`success`/`data`/`message`).       | Comunicazione frontend-backend         |
| **OperationInterface**    | Interfaccia PHP che definisce il contratto per le operazioni (metodi di presentazione: titolo, icona, ecc.).  | Architettura Operation                 |
| **AbstractOperation**     | Classe abstract PHP che implementa la logica comune (audit log, presentazione) ereditata da tutte le operazioni. | Architettura Operation            |
| **Repository**            | Classe PHP dedicata all'accesso database per una specifica operazione. Separa la persistenza dalla logica di business. | Architettura Operation          |

## Acronimi

| Acronimo | Significato                              |
|----------|------------------------------------------|
| ABI      | Codice di Avviamento Bancario            |
| CAB      | Codice di Avviamento della Banca         |
| BPER     | Banca Popolare dell'Emilia Romagna       |
| CDN      | Content Delivery Network                 |
| IBAN     | International Bank Account Number        |
| NDG      | Numero Di Gestione (identificativo cliente) |
| LGRP     | Codice fiscale del legale rappresentante del gruppo |
| PDO      | PHP Data Objects                         |
| SFC      | Single File Component (Vue.js)           |
