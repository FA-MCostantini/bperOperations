# Schema Reference — bperOperations

Standard di riferimento: **IEEE 1471** — Documentazione architetturale

---

## 1. Diagramma ER (relazioni tra tabelle coinvolte)

```
ntt_bper.t_param_operation_type
  ├── id (PK)
  ├── operation_desc
  └── operation_code
        │
        │ 1:N
        ▼
ntt_bper.t_policy_operation
  ├── id (PK)
  ├── t_param_operation_type_id (FK → t_param_operation_type.id)
  ├── operation_status          [PENDING | CANCELLED | ...]
  ├── cancelled_date
  ├── company_code
  ├── company_policy_number
  ├── bper_policy_number
  ├── company_operation_id
  ├── premium
  ├── sent_date
  ├── user_abi
  ├── user_agency_code
  ├── user_cab
  ├── iban
  ├── customer_ndg
  ├── fiscal_code
  ├── fiscal_code_lgrp
  ├── code_rapporto
  └── product_code
        │
        │ 1:N
        ▼
ntt_bper.t_policy_operation_draft
  ├── id (PK)
  ├── policy_operation_id (FK → t_policy_operation.id)
  └── bper_policy_number
        │
        │ 1:N
        ▼
ntt_bper.t_int_policy_operation_docs          ntt_bper.t_ath_policy_operation_docs
  ├── t_policy_operation_draft_id (FK)          ├── t_policy_operation_draft_id (FK)
  └── ...                                       └── download_status [PENDING | ERROR]


ntt_bper.t_ath_policy_auth_code
  ├── code (UNIQUE)
  ├── insert_date (TIMESTAMP)
  ├── bper_contract_number
  └── operation_type_code [_RISPA | _RISTO]


ntt_bper.v_policy (VISTA, sola lettura)
  └── bper_policy_number


public.operation_audit_log
  ├── id (PK, SERIAL)
  ├── operation_name (TEXT)
  ├── payload (JSONB)
  ├── user_id (INT, default 0)
  └── created_at (TIMESTAMP, default NOW())
```

---

## 2. Dettaglio tabelle

### 2.1 ntt_bper.t_ath_policy_auth_code

**Usata da**: newRetrievalCode
**Volume**: ~362.000 record

| Colonna              | Tipo        | Vincoli                | Descrizione                        |
|----------------------|-------------|------------------------|------------------------------------|
| `code`               | VARCHAR     | UNIQUE, NOT NULL       | Codice riscatto `R<P/T><contract><n>` |
| `insert_date`        | TIMESTAMP   | NOT NULL               | Data/ora inserimento               |
| `bper_contract_number` | VARCHAR   |                        | Codice polizza                     |
| `operation_type_code`| VARCHAR     |                        | `_RISPA` (parziale) o `_RISTO` (totale) |

### 2.2 ntt_bper.v_policy

**Usata da**: newRetrievalCode (autocomplete)
**Volume**: ~500.000 record
**Tipo**: VISTA (sola lettura)

| Colonna              | Tipo        | Descrizione                       |
|----------------------|-------------|-----------------------------------|
| `bper_policy_number` | VARCHAR     | Codice polizza (lunghezza variabile) |

### 2.3 ntt_bper.t_policy_operation

**Usata da**: forceAnnulment
**Volume**: ~14.000 record (~50 nuovi/giorno)

| Colonna                  | Tipo        | Vincoli    | Descrizione                          |
|--------------------------|-------------|------------|--------------------------------------|
| `id`                     | INTEGER     | PK         | Identificativo univoco               |
| `t_param_operation_type_id` | INTEGER  | FK         | Tipo operazione                      |
| `operation_status`       | VARCHAR     |            | `PENDING`, `CANCELLED`, etc.         |
| `cancelled_date`         | TIMESTAMP   |            | Data cancellazione (soft-delete)     |
| `company_code`           | VARCHAR     |            | Codice compagnia                     |
| `company_policy_number`  | VARCHAR     |            | Numero polizza compagnia             |
| `bper_policy_number`     | VARCHAR     |            | Codice polizza banca                 |
| `company_operation_id`   | VARCHAR     |            | ID operazione lato compagnia         |
| `premium`                | NUMERIC     |            | Premio                               |
| `sent_date`              | TIMESTAMP   |            | Data invio                           |
| `user_abi`               | VARCHAR     |            | Codice ABI                           |
| `user_agency_code`       | VARCHAR     |            | Codice agenzia                       |
| `user_cab`               | VARCHAR     |            | Codice CAB                           |
| `iban`                   | VARCHAR     |            | IBAN                                 |
| `customer_ndg`           | VARCHAR     |            | NDG cliente                          |
| `fiscal_code`            | VARCHAR     |            | Codice fiscale                       |
| `fiscal_code_lgrp`       | VARCHAR     |            | Codice fiscale LGRP                  |
| `code_rapporto`          | VARCHAR     |            | Codice rapporto                      |
| `product_code`           | VARCHAR     |            | Codice prodotto                      |

### 2.4 ntt_bper.t_param_operation_type

**Usata da**: forceAnnulment, resetDocumentState
**Tipo**: tabella parametrica

| Colonna          | Tipo    | Vincoli | Descrizione              |
|------------------|---------|---------|--------------------------|
| `id`             | INTEGER | PK      | Identificativo           |
| `operation_desc` | VARCHAR |         | Descrizione operazione   |
| `operation_code` | VARCHAR |         | Codice operazione        |

### 2.5 ntt_bper.t_policy_operation_draft

**Usata da**: forceAnnulment (hard-delete), resetDocumentState

| Colonna              | Tipo    | Vincoli | Descrizione                      |
|----------------------|---------|---------|----------------------------------|
| `id`                 | INTEGER | PK      | Identificativo draft             |
| `policy_operation_id`| INTEGER | FK      | Riferimento a `t_policy_operation.id` |
| `bper_policy_number` | VARCHAR |         | Codice polizza                   |

### 2.6 ntt_bper.t_int_policy_operation_docs

**Usata da**: forceAnnulment (hard-delete)

| Colonna                        | Tipo    | Vincoli | Descrizione         |
|--------------------------------|---------|---------|---------------------|
| `t_policy_operation_draft_id`  | INTEGER | FK      | Riferimento a draft |

### 2.7 ntt_bper.t_ath_policy_operation_docs

**Usata da**: resetDocumentState

| Colonna                        | Tipo    | Vincoli | Descrizione                       |
|--------------------------------|---------|---------|-----------------------------------|
| `t_policy_operation_draft_id`  | INTEGER | FK      | Riferimento a draft               |
| `download_status`              | VARCHAR |         | `PENDING` o `ERROR`               |

### 2.8 public.operation_audit_log

**Usata da**: tutte le operazioni (audit trail)
**Da creare**: si (vedi sezione 3)

| Colonna          | Tipo      | Vincoli               | Descrizione                      |
|------------------|-----------|-----------------------|----------------------------------|
| `id`             | SERIAL    | PK                    | Identificativo auto-incrementale |
| `operation_name` | TEXT      | NOT NULL              | Nome operazione                  |
| `payload`        | JSONB     | NOT NULL              | Dati della richiesta             |
| `user_id`        | INT       | NOT NULL, DEFAULT 0   | ID utente (integrazione futura)  |
| `created_at`     | TIMESTAMP | NOT NULL, DEFAULT NOW()| Data/ora operazione             |

---

## 3. DDL — Tabella da creare

```sql
CREATE TABLE IF NOT EXISTS public.operation_audit_log (
    id             SERIAL PRIMARY KEY,
    operation_name TEXT NOT NULL,
    payload        JSONB NOT NULL,
    user_id        INT NOT NULL DEFAULT 0,
    created_at     TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_audit_log_operation
    ON public.operation_audit_log (operation_name);

CREATE INDEX IF NOT EXISTS idx_audit_log_created_at
    ON public.operation_audit_log (created_at DESC);
```
