# Query Reference — bperOperations

Documentazione delle query SQL utilizzate dalle operazioni. Ogni query include: scopo, tabelle coinvolte, parametri e note sulle performance.

---

## 1. newRetrievalCode

### Q-NRC-01: Autocomplete codice polizza

**Scopo**: ricerca codici polizza per l'autocomplete del form
**Tabella**: `ntt_bper.v_policy` (vista, ~500.000 record)
**Tipo**: SELECT con LIKE

```sql
SELECT bper_policy_number
  FROM ntt_bper.v_policy
 WHERE bper_policy_number LIKE :search_term
 LIMIT 10;
```

| Parametro      | Tipo   | Esempio     | Note                                |
|----------------|--------|-------------|-------------------------------------|
| `:search_term` | string | `'054%'`    | Prefisso con `%` aggiunto dal backend. Min 2 caratteri |

**Performance**: la vista contiene ~500k record. Assicurarsi che esista un indice su `bper_policy_number`. Il `LIMIT 10` limita il risultato.

---

### Q-NRC-02: Codici esistenti per contratto

**Scopo**: mostrare i codici gia presenti per uno specifico contratto
**Tabella**: `ntt_bper.t_ath_policy_auth_code` (~362.000 record)
**Tipo**: SELECT con filtro

```sql
SELECT insert_date
     , code
     , operation_type_code
  FROM ntt_bper.t_ath_policy_auth_code
 WHERE bper_contract_number = :bper_contract_number
 ORDER BY insert_date DESC
        , operation_type_code;
```

| Parametro                | Tipo   | Esempio      |
|--------------------------|--------|--------------|
| `:bper_contract_number`  | string | `'05421607'` |

**Note**: mostra sia tipo P che T. Ordinamento per data decrescente.

---

### Q-NRC-03: Calcolo progressivo

**Scopo**: trovare il prossimo valore `n` per un dato contratto e tipo
**Tabella**: `ntt_bper.t_ath_policy_auth_code`
**Tipo**: SELECT con pattern matching

```sql
SELECT MAX(CAST(RIGHT(code, 1) AS INTEGER)) AS max_n
  FROM ntt_bper.t_ath_policy_auth_code
 WHERE code LIKE :code_prefix;
```

| Parametro       | Tipo   | Esempio        | Note                              |
|-----------------|--------|----------------|-----------------------------------|
| `:code_prefix`  | string | `'RT05421607%'`| Prefisso `R` + tipo + contract_number + `%` |

**Logica**:
- Se `max_n` e NULL (nessun codice esistente): `n = 1`
- Se `max_n < 9`: `n = max_n + 1`
- Se `max_n >= 9`: errore "Limite massimo raggiunto"

**Importante**: NON usare `COUNT+1`. Usare il MAX dell'ultimo carattere.

---

### Q-NRC-04: Inserimento codice

**Scopo**: inserire un nuovo codice di riscatto
**Tabella**: `ntt_bper.t_ath_policy_auth_code`
**Tipo**: INSERT con ON CONFLICT

```sql
INSERT INTO ntt_bper.t_ath_policy_auth_code
       (code, insert_date, bper_contract_number, operation_type_code)
VALUES (:code, NOW(), :bper_contract_number, :operation_type_code)
ON CONFLICT DO NOTHING;
```

| Parametro                | Tipo   | Esempio        |
|--------------------------|--------|----------------|
| `:code`                  | string | `'RT054216073'` |
| `:bper_contract_number`  | string | `'05421607'`   |
| `:operation_type_code`   | string | `'_RISTO'`     |

**Note**: constraint UNIQUE su colonna `code`. `ON CONFLICT DO NOTHING` evita errori su duplicati. Tipo `insert_date`: TIMESTAMP (non TIMESTAMPTZ).

---

## 2. forceAnnulment

### Q-FA-01: Elenco operazioni

**Scopo**: mostrare le operazioni non cancellate
**Tabelle**: `t_policy_operation` JOIN `t_param_operation_type`
**Tipo**: SELECT con JOIN

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
     , po.id
  FROM ntt_bper.t_policy_operation po
 INNER JOIN ntt_bper.t_param_operation_type pot
    ON po.t_param_operation_type_id = pot.id
 WHERE po.operation_status != 'CANCELLED';
```

**Performance**: ~14.000 record (con crescita di ~50/giorno). Indice consigliato su `operation_status`.

**Note**: il campo `po.id` e nascosto in tabella, usato solo per la POST di cancellazione. Il frontend si adatta dinamicamente alle colonne restituite.

---

### Q-FA-02: Estrazione dati per cancellazione

**Scopo**: recuperare i dati necessari per la cascata di cancellazione

```sql
SELECT bper_policy_number
     , company_operation_id
  FROM ntt_bper.t_policy_operation
 WHERE id = :id_to_delete;
```

---

### Q-FA-03: Soft-delete operazione

```sql
UPDATE ntt_bper.t_policy_operation
   SET operation_status = 'CANCELLED'
     , cancelled_date = NOW()
 WHERE bper_policy_number = :bper_policy_number
   AND company_operation_id = :company_operation_id;
```

---

### Q-FA-04: Hard-delete documenti operazione

```sql
DELETE FROM ntt_bper.t_int_policy_operation_docs doc
      USING ntt_bper.t_policy_operation_draft draft
       JOIN ntt_bper.t_policy_operation op
         ON draft.policy_operation_id = op.id
      WHERE doc.t_policy_operation_draft_id = draft.id
        AND op.bper_policy_number = :bper_policy_number
        AND op.company_operation_id = :company_operation_id;
```

---

### Q-FA-05: Hard-delete draft operazione

```sql
DELETE FROM ntt_bper.t_policy_operation_draft
 WHERE policy_operation_id = (
           SELECT id
             FROM ntt_bper.t_policy_operation
            WHERE bper_policy_number = :bper_policy_number
              AND company_operation_id = :company_operation_id
       )
   AND bper_policy_number = :bper_policy_number;
```

**Nota transazionale**: Q-FA-03, Q-FA-04 e Q-FA-05 DEVONO essere eseguiti in un'unica transazione atomica tramite `addQueryInStack()` + `tryQueryStack()`.

---

## 3. resetDocumentState

### Q-RDS-01: Elenco draft con documenti PENDING/ERROR

**Scopo**: mostrare i draft raggruppati con conteggi per stato documento
**Tabelle**: JOIN tra `t_policy_operation_draft`, `t_ath_policy_operation_docs`, `t_policy_operation`, `t_param_operation_type`
**Tipo**: SELECT con GROUP BY e FILTER

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
     , tpod.id
  FROM ntt_bper.t_policy_operation_draft tpod
 INNER JOIN ntt_bper.t_ath_policy_operation_docs tapod
    ON tpod.id = tapod.t_policy_operation_draft_id
 INNER JOIN ntt_bper.t_policy_operation po
    ON po.id = tpod.policy_operation_id
 INNER JOIN ntt_bper.t_param_operation_type pot
    ON po.t_param_operation_type_id = pot.id
 WHERE tapod.download_status IN ('PENDING', 'ERROR')
 GROUP BY tpod.id
        , pot.operation_desc, pot.operation_code
        , po.operation_status, po.company_code
        , po.company_policy_number, po.bper_policy_number
        , po.premium, po.sent_date
        , po.user_abi, po.user_agency_code, po.user_cab
        , po.iban, po.customer_ndg, po.fiscal_code
        , po.fiscal_code_lgrp, po.code_rapporto, po.product_code;
```

**Note**:
- `COUNT(*) FILTER`: sintassi PostgreSQL per conteggi condizionali
- Non ci si aspettano situazioni miste PENDING/ERROR per lo stesso draft
- Se un'operation ha piu draft, appaiono piu righe (si lavora per singolo draft)
- Il campo `tpod.id` e nascosto in tabella, usato per la POST

---

### Q-RDS-02: Cambio stato documenti

**Scopo**: forzare tutti i documenti PENDING di un draft a ERROR

```sql
UPDATE ntt_bper.t_ath_policy_operation_docs tapod
   SET download_status = 'ERROR'
  FROM ntt_bper.t_policy_operation_draft tpod
 WHERE tpod.id = tapod.t_policy_operation_draft_id
   AND tpod.id = :id
   AND tapod.download_status = 'PENDING';
```

| Parametro | Tipo | Descrizione                         |
|-----------|------|-------------------------------------|
| `:id`     | int  | ID del draft (`t_policy_operation_draft.id`) |

---

## 4. Audit Log

### Q-LOG-01: Inserimento log

```sql
INSERT INTO public.operation_audit_log
       (operation_name, payload, user_id, created_at)
VALUES (:operation_name, :payload::jsonb, :user_id, NOW());
```

| Parametro          | Tipo   | Descrizione                          |
|--------------------|--------|--------------------------------------|
| `:operation_name`  | string | Nome dell'operazione                 |
| `:payload`         | string | JSON con i dati della richiesta      |
| `:user_id`         | int    | ID utente (0 = placeholder)          |
