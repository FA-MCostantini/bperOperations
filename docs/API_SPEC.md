# Specifica API — bperOperations

Formato: **Endpoint-Action Reference**

---

## 1. Convenzioni generali

### 1.1 Base URL

Tutti gli endpoint sono relativi alla root del progetto:

```
./src/model/ajax/<file>.php?action=<action>
```

### 1.2 Formato richiesta

- **GET**: parametri via query string (`?action=tabella&param=valore`)
- **POST**: body `application/x-www-form-urlencoded` o `application/json`

### 1.3 Formato risposta

Tutte le risposte sono `Content-Type: application/json` con struttura `AjaxResponseHelper`:

**Successo** (HTTP 200):
```json
{
  "success": true,
  "data": <mixed>
}
```

**Errore** (HTTP 400/500):
```json
{
  "success": false,
  "message": "Descrizione leggibile",
  "exception": "Dettaglio tecnico (solo ENV_IS_DEV=true)"
}
```

**Operazione disabilitata** (HTTP 403):
```json
{
  "success": false,
  "message": "Operazione non disponibile"
}
```

Ogni endpoint ajax usa `OperationFactory::create()` per ottenere l'operazione. Se l'operazione è disabilitata, la Factory lancia l'eccezione 403 automaticamente.

---

## 2. MainApp — ajax_operations_view.php

### 2.1 GET ?action=list

Restituisce l'elenco delle operazioni disponibili.

**Parametri**: nessuno

**Risposta** (`data`):
```json
[
  {
    "title": "Inserimento Codice Riscatto",
    "description": "Inserisci un nuovo codice di riscatto parziale o totale",
    "icon": "bi-upc-scan",
    "color": "primary",
    "jsPath": "./assets-fa/js/Operations/newRetrievalCode.js",
    "enabled": true
  }
]
```

**Logica backend** (usa `OperationFactory::discoverAll()`):
1. Scansiona `src/model/Operations/`, istanzia le classi che implementano `OperationInterface`
2. Esclude le operazioni con `isVisible() = false`
3. Per le operazioni visibili, chiama i metodi di presentazione e aggiunge il campo `enabled` dal risultato di `isEnabled()`

---

## 3. newRetrievalCode — ajax_newRetrievalCode_view.php / _save.php

### 3.1 GET ?action=search — Autocomplete codice polizza

**Parametri**:

| Nome   | Tipo   | Obbligatorio | Descrizione                          |
|--------|--------|--------------|--------------------------------------|
| `q`    | string | Si           | Testo da cercare (min 2 caratteri)   |

**Risposta** (`data`):
```json
["05421607", "05435891", "05421608"]
```

Max 10 risultati. Query: `SELECT bper_policy_number FROM ntt_bper.v_policy WHERE bper_policy_number LIKE :q LIMIT 10`.

### 3.2 GET ?action=tabella — Codici esistenti per contratto

**Parametri**:

| Nome                   | Tipo   | Obbligatorio | Descrizione             |
|------------------------|--------|--------------|-------------------------|
| `bper_contract_number` | string | Si           | Codice polizza          |

**Risposta** (`data`):
```json
[
  {
    "insert_date": "2026-03-20 14:30:00",
    "code": "RT054216072",
    "operation_type_code": "_RISTO"
  }
]
```

Ordinamento: `insert_date DESC, operation_type_code`.

### 3.3 GET ?action=calc — Calcolo anteprima codice

**Parametri**:

| Nome                   | Tipo   | Obbligatorio | Descrizione                            |
|------------------------|--------|--------------|----------------------------------------|
| `bper_contract_number` | string | Si           | Codice polizza                         |
| `type`                 | string | Si           | `P` (Parziale) o `T` (Totale)         |

**Risposta successo** (`data`):
```json
{
  "code": "RT054216073",
  "next_n": 3
}
```

**Risposta errore** (limite raggiunto):
```json
{
  "success": false,
  "message": "Limite massimo codici raggiunto per questo contratto e tipo"
}
```

**Logica**: cerca codici esistenti con prefisso `R<P/T><contract_number>`, prende il MAX dell'ultimo carattere, somma 1. Se risultato > 9, restituisce errore.

### 3.4 POST — Inserimento nuovo codice

**Endpoint**: `ajax_newRetrievalCode_save.php`

**Parametri (body)**:

| Nome                   | Tipo   | Obbligatorio | Descrizione                            |
|------------------------|--------|--------------|----------------------------------------|
| `bper_contract_number` | string | Si           | Codice polizza                         |
| `type`                 | string | Si           | `P` (Parziale) o `T` (Totale)         |

**Risposta successo** (`data`):
```json
{
  "code": "RT054216073",
  "inserted": true
}
```

**Logica backend**:
1. Calcola il codice (stessa logica di `calc`)
2. Esegue INSERT con `ON CONFLICT DO NOTHING`
3. Scrive il log audit (solo su successo)

---

## 4. forceAnnulment — ajax_forceAnnulment_view.php / _save.php

### 4.1 GET ?action=tabella — Elenco operazioni

**Parametri**: nessuno

**Risposta** (`data`):
```json
[
  {
    "Operazione": "Sottoscrizione",
    "Codice": "SUB",
    "Stato": "PENDING",
    "Compagnia": "ABC",
    "Codice Compagnia": "12345",
    "Codice Banca": "05421607",
    "Premio": 1500.00,
    "Data invio": "2026-03-20",
    "ABI": "05387",
    "AGENZIA": "001",
    "CAB": "12345",
    "IBAN": "IT60X0542811101000000123456",
    "NDG": "12345678",
    "Cod. fisc.": "RSSMRA80A01H501Z",
    "LGRP": "",
    "Codice Rapporto": "RAP001",
    "Codice Prodotto": "PROD01",
    "id": 42
  }
]
```

Il campo `id` e presente nei dati ma NON deve essere mostrato in tabella. Il frontend si adatta dinamicamente alle colonne restituite (escluso `id`).

### 4.2 POST — Cancellazione operazione

**Endpoint**: `ajax_forceAnnulment_save.php`

**Parametri (body)**:

| Nome | Tipo | Obbligatorio | Descrizione                       |
|------|------|--------------|-----------------------------------|
| `id` | int  | Si           | ID dell'operazione da cancellare  |

**Risposta successo**:
```json
{
  "success": true,
  "data": { "deleted": true }
}
```

**Logica backend** (transazione atomica via `addQueryInStack` + `tryQueryStack`):
1. SELECT `bper_policy_number`, `company_operation_id` da `t_policy_operation` per `:id`
2. UPDATE `t_policy_operation` → `operation_status = 'CANCELLED'`, `cancelled_date = NOW()`
3. DELETE da `t_int_policy_operation_docs` (hard-delete documenti)
4. DELETE da `t_policy_operation_draft` (hard-delete draft)
5. Scrittura log audit

---

## 5. resetDocumentState — ajax_resetDocumentState_view.php / _save.php

### 5.1 GET ?action=tabella — Elenco draft con documenti PENDING/ERROR

**Parametri**: nessuno

**Risposta** (`data`):
```json
[
  {
    "Operazione": "Sottoscrizione",
    "Codice": "SUB",
    "Stato": "PENDING",
    "Compagnia": "ABC",
    "Codice Compagnia": "12345",
    "Codice Banca": "05421607",
    "Premio": 1500.00,
    "Data invio": "2026-03-20",
    "ABI": "05387",
    "AGENZIA": "001",
    "CAB": "12345",
    "IBAN": "IT60X0542811101000000123456",
    "NDG": "12345678",
    "Cod. fisc.": "RSSMRA80A01H501Z",
    "LGRP": "",
    "Codice Rapporto": "RAP001",
    "Codice Prodotto": "PROD01",
    "Doc. PENDING": 3,
    "Doc. ERROR": 0,
    "id": 17
  }
]
```

Il campo `id` (draft id) e presente nei dati ma NON deve essere mostrato in tabella. Le colonne `Doc. PENDING` e `Doc. ERROR` mostrano i conteggi.

### 5.2 POST — Cambio stato PENDING -> ERROR

**Endpoint**: `ajax_resetDocumentState_save.php`

**Parametri (body)**:

| Nome | Tipo | Obbligatorio | Descrizione                    |
|------|------|--------------|--------------------------------|
| `id` | int  | Si           | ID del draft (`tpod.id`)       |

**Risposta successo**:
```json
{
  "success": true,
  "data": { "updated": true }
}
```

**Logica backend**:
1. UPDATE `t_ath_policy_operation_docs` → `download_status = 'ERROR'` per tutti i documenti PENDING del draft
2. Scrittura log audit
