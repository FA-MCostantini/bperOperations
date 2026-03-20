# Strategia di Logging — bperOperations

Formato di riferimento: **Structured JSON Logging (ECS-compatible)**

---

## 1. Livelli di log

| Livello    | Uso                                                                 | Esempio                                           |
|------------|---------------------------------------------------------------------|---------------------------------------------------|
| `ERROR`    | Errore che impedisce il completamento dell'operazione               | Query fallita, connessione DB persa               |
| `WARNING`  | Situazione anomala ma gestita                                       | Limite progressivo raggiunto, conflict su INSERT   |
| `INFO`     | Azione di business completata con successo                          | Codice inserito, operazione annullata              |
| `DEBUG`    | Dettaglio tecnico utile in sviluppo                                 | Statement SQL eseguito, parametri della request    |

---

## 2. Canali di log

### 2.1 Audit Trail (database)

**Canale**: tabella `public.operation_audit_log`
**Quando**: dopo ogni operazione di modifica dati andata a buon fine
**Formato record**:

```json
{
  "id": 1,
  "operation_name": "newRetrievalCode",
  "payload": {
    "bper_contract_number": "05421607",
    "operation_type_code": "_RISTO",
    "code": "RT054216072"
  },
  "user_id": 0,
  "created_at": "2026-03-20T14:30:00"
}
```

**Regole**:
- Solo su successo (nessun log su errore)
- Il payload contiene esattamente i dati inviati dal frontend
- Il `user_id` e decorato dal logger (non dall'operazione)
- Il `created_at` e decorato dal database (`DEFAULT NOW()`)

### 2.2 Error Log (applicativo)

**Canale**: output PHP standard (`stderr` in produzione, `stdout` in sviluppo)
**Quando**: su ogni eccezione catturata nei file Ajax
**Formato**: JSON strutturato

```json
{
  "timestamp": "2026-03-20T14:30:00+01:00",
  "level": "ERROR",
  "message": "Errore durante l'inserimento del codice",
  "context": {
    "operation": "newRetrievalCode",
    "action": "insert",
    "error": "UNIQUE violation on column code",
    "file": "ajax_newRetrievalCode_save.php",
    "line": 42
  }
}
```

### 2.3 Debug Log (solo sviluppo)

**Canale**: `TraitTryQuery::debugPrintException()` — output HTML inline
**Quando**: `ENV_IS_DEV === true`
**Contenuto**: messaggio eccezione, stack trace, statement SQL
**Nota**: questo canale e gia presente nel codice esistente e viene mantenuto per compatibilita. In produzione (`ENV_IS_DEV === false`) non produce output.

---

## 3. Formato risposta Ajax (frontend)

Tutte le risposte Ajax seguono il formato `AjaxResponseHelper`:

**Successo**:
```json
{
  "success": true,
  "data": { ... }
}
```

**Errore**:
```json
{
  "success": false,
  "message": "Descrizione leggibile dell'errore",
  "exception": "Dettaglio tecnico (solo in ambiente dev)"
}
```

Il campo `exception` viene popolato solo quando `ENV_IS_DEV === true`.

---

## 4. Sicurezza dei log

| Regola                                          | Applicazione                          |
|-------------------------------------------------|---------------------------------------|
| Mai loggare credenziali o password              | Nessuna credenziale transita nel payload |
| Mai loggare dati sensibili in chiaro            | I codici fiscali sono dati di business, non segreti in questo contesto |
| Sanitizzare l'output di debug in produzione     | `debugPrintException` disabilitato quando `ENV_IS_DEV === false` |
| Il campo `exception` nella risposta Ajax e solo dev | Controllato da `AjaxResponseHelper::error()` |

---

## 5. Retention

| Canale          | Retention                                             |
|-----------------|-------------------------------------------------------|
| Audit Trail DB  | Indefinita (tabella `operation_audit_log`)             |
| Error Log       | Gestito dall'infrastruttura di hosting (fuori scope)  |
| Debug Log       | Solo sessione corrente (output inline, non persistito)|
