# ADR-004: Strategia di Audit Trail

**Stato**: Accettato
**Data**: 2026-03-20
**Contesto**: Tracciamento delle azioni eseguite dagli utenti sulle operazioni

## Decisione

- Tabella dedicata `public.operation_audit_log` con schema semplice (id, operation_name, payload JSONB, user_id INT, created_at TIMESTAMP).
- Il log viene scritto solo dopo il successo dell'operazione.
- La logica di log risiede in `AjaxResponseHelper::success()`, che scrive automaticamente il log quando riceve un'istanza di `AbstractOperation`.
- Il `user_id` e temporaneamente `0` (integrazione autenticazione futura).

## Alternative considerate

- **Log applicativo su file** (es. PSR-3 + file handler): non interrogabile, non strutturato. Scartato perche serve un audit trail consultabile.
- **Trigger PostgreSQL**: accoppia la logica al database. Scartato perche il payload proviene dal frontend e non e disponibile a livello di trigger.
- **Log anche su errore**: scartato su richiesta del committente (solo operazioni andate a buon fine).

## Motivazione

- Semplicita: una tabella con JSONB e sufficiente per il caso d'uso.
- Trasparenza: nessun codice aggiuntivo per le nuove operazioni (il logging e delegato a `AjaxResponseHelper`).
- Il payload JSONB consente flessibilita senza schema rigido per i dati loggati.

## Conseguenze

- Ogni nuova operazione che estende `AbstractOperation` ha il log automatico (tramite `AjaxResponseHelper`).
- Il campo `user_id` dovra essere aggiornato quando l'autenticazione sara integrata.
- Il payload dipende da cosa il frontend invia: non c'e validazione dello schema JSONB.
