# Changelog

## [0.2.0] - 2026-03-21

### Annullamento Forzato (forceAnnulment)
- Modale allargata al 95% della viewport per tabelle più leggibili
- Paginazione a finestra: mostra max 10 numeri di pagina centrati sulla pagina corrente (Precedente/Successivo)
- Rimosse colonne IBAN, LGRP, Codice Rapporto per ridurre lo scroll orizzontale
- Le righe in stato COMPLETED sono escluse direttamente dalla query SQL (backend)
- Icona cestino spostata in una colonna dedicata (prima colonna), separata dal testo dell'operazione
- Migliorata grafica tabella: aggiunta striping alternato, icone di ordinamento su tutte le colonne, info riga/pagina
- Fix chiusura modale di conferma: dialogo di conferma eliminazione convertito da Bootstrap Modal nidificato a overlay Vue-driven per risolvere problemi di chiusura con modali nidificate

### Inserimento Codice Riscatto (newRetrievalCode)
- Ricerca contratto estesa al campo Codice Compagnia: i suggerimenti mostrano anche il codice compagnia tra parentesi quando diverso
- Bottone inserimento ridisegnato: solo icona "+" grande con stile moderno (48x48, bordi arrotondati)
- Anteprima codice spostata inline tra il campo Numero Contratto e il bottone, con label "Anteprima" sempre visibile
- Formattazione anteprima migliorata: "R T CODICE N" con spazi per leggibilità
- Rimossa specifica del progressivo dall'anteprima
- Tabella codici esistenti: titolo e intestazioni sempre visibili anche senza codici ("Ancora nessun codice per questo contratto.")

### Cambio Stato Documento (resetDocumentState)
- Modale allargata al 95% della viewport (condivisa con Annullamento Forzato)
- Paginazione a finestra: mostra max 10 numeri di pagina centrati sulla pagina corrente (Precedente/Successivo)
- Rimosse colonne IBAN, LGRP, Codice Rapporto
- Le righe in stato COMPLETED sono escluse direttamente dalla query SQL (backend)
- Migliorata grafica tabella: aggiunta striping alternato
- Fix chiusura modale di conferma: dialogo di conferma reset convertito da Bootstrap Modal nidificato a overlay Vue-driven

### Backend
- `NewRetrievalCodeRepository::searchPolicyNumber()` ora cerca anche per `company_policy_number` e restituisce oggetti con entrambi i campi

### Infrastruttura
- Rimosso prefisso `src/` da tutti gli URL AJAX nei file JS (coerenza con il progetto target)
- Aggiunto `tests/e2e/router.php` per il rewriting delle URL nel server di test (mappa `/model/ajax/` → `/src/model/ajax/`)
- Aggiornata configurazione Playwright per utilizzare il router PHP

## [0.1.0] - 2026-03-20

- Rilascio iniziale con tre operazioni: Annullamento Forzato, Inserimento Codice Riscatto, Cambio Stato Documento
