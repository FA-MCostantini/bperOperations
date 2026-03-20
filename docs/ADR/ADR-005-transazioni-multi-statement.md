# ADR-005: Gestione transazioni multi-statement

**Stato**: Accettato
**Data**: 2026-03-20
**Contesto**: Le operazioni di cancellazione (forceAnnulment) richiedono 3 statement SQL atomici

## Decisione

Usare i metodi `addQueryInStack()` e `tryQueryStack()` del `TraitTryQuery` esistente per:
1. Accodare gli statement nella stack
2. Eseguirli in sequenza all'interno di un'unica transazione
3. Rollback automatico in caso di errore su qualsiasi statement

## Alternative considerate

- **Transazione manuale con BEGIN/COMMIT**: possibile ma non coerente con il pattern gia in uso nel progetto.
- **Stored procedure PostgreSQL**: accoppia la logica al database, rende piu difficile il testing e la manutenzione.

## Motivazione

- Il `TraitTryQuery` e gia presente nel progetto e fornisce gestione transazionale robusta.
- I metodi `addQueryInStack/tryQueryStack` sono pensati esattamente per questo caso d'uso.
- Coerenza con il codice esistente.

## Conseguenze

- Gli statement vengono preparati e accodati, non eseguiti immediatamente.
- In caso di errore su qualsiasi statement, tutti vengono annullati (rollback).
- Il codice delle operazioni non gestisce direttamente BEGIN/COMMIT.
