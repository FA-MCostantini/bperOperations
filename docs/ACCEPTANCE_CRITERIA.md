# Criteri di Accettazione — bperOperations

Standard di riferimento: **EARS (Easy Approach to Requirements Syntax)**

---

## Legenda pattern EARS

| Pattern              | Forma                                                        |
|----------------------|--------------------------------------------------------------|
| **Ubiquitous**       | Il sistema DEVE `<azione>`.                                  |
| **Event-Driven**     | QUANDO `<evento>`, il sistema DEVE `<azione>`.               |
| **State-Driven**     | MENTRE `<stato>`, il sistema DEVE `<azione>`.                |
| **Optional Feature** | DOVE `<feature>` e disponibile, il sistema DEVE `<azione>`.  |
| **Unwanted**         | SE `<condizione indesiderata>`, il sistema DEVE `<azione>`.  |

---

## AC-MAIN — MainApp

### AC-MAIN-01: Visualizzazione elenco operazioni

QUANDO la MainApp viene caricata, il sistema DEVE effettuare una chiamata Ajax a `ajax_operations_view.php?action=list` e mostrare una Card Bootstrap per ciascuna operazione restituita.

**Verifica**: le card mostrano icona, titolo e descrizione per tutte le operazioni che implementano `OperationInterface`.

### AC-MAIN-02: Apertura modale operazione

QUANDO l'utente clicca su una Card, il sistema DEVE aprire una modale Bootstrap. SE il file JS dell'operazione non e ancora stato caricato, il sistema DEVE caricarlo dinamicamente via `<script>` e creare l'app Vue con `Vue.createApp().mount()`.

**Verifica**: la modale si apre, l'app Vue e funzionante, il form e operativo.

### AC-MAIN-03: Persistenza stato operazione

QUANDO l'utente chiude la modale e la riapre, il sistema DEVE preservare lo stato del form (campi compilati, selezioni). I dati delle tabelle DEVONO essere ricaricati dal server.

**Verifica**: compilare un form, chiudere la modale, riaprirla — i campi del form mantengono i valori, la tabella mostra dati aggiornati.

### AC-MAIN-04: Scoperta automatica operazioni

QUANDO viene aggiunto un nuovo file PHP in `src/model/Operations/` che implementa `OperationInterface` e un file JS corrispondente, il sistema DEVE mostrare automaticamente la nuova operazione nella MainApp senza ulteriori configurazioni.

**Verifica**: creare una classe PHP stub + file JS, ricaricare la pagina — la nuova Card appare.

---

## AC-NRC — newRetrievalCode

### AC-NRC-01: Autocomplete codice polizza

QUANDO l'utente digita almeno 2 caratteri nel campo codice polizza E sono trascorsi 2 secondi dall'ultimo input, il sistema DEVE effettuare una chiamata Ajax server-side e mostrare fino a 10 suggerimenti.

**Verifica**: digitare "054" — dopo 2 secondi appare la lista suggerimenti con max 10 risultati.

### AC-NRC-02: Tabella codici esistenti

QUANDO l'utente seleziona un codice polizza dall'autocomplete, il sistema DEVE mostrare sotto al form la tabella dei codici gia presenti per quel contratto (sia tipo P che T), ordinati per `insert_date DESC, operation_type_code`.

**Verifica**: selezionare un contratto con codici esistenti — la tabella appare con le colonne `insert_date`, `code`, `operation_type_code`.

### AC-NRC-03: Anteprima codice

QUANDO entrambi i campi del form (dropdown e codice polizza) sono compilati, il sistema DEVE chiamare `GET:calc` e mostrare l'anteprima del codice che verra inserito.

**Verifica**: selezionare "Riscatto Totale" e un contratto — sotto al form appare il codice calcolato (es. `RT054216073`).

### AC-NRC-04: Inserimento codice

QUANDO l'utente preme il bottone di inserimento (`bi-plus-circle`), il sistema DEVE mostrare una modale di conferma. QUANDO l'utente conferma, il sistema DEVE inserire il codice e aggiornare la tabella. Il form NON DEVE essere ripulito.

**Verifica**: inserire un codice, confermare — la tabella si aggiorna con il nuovo codice, il form mantiene i valori.

### AC-NRC-05: Limite progressivo

QUANDO il progressivo raggiunge 9 per uno specifico contratto e tipo, il sistema DEVE disabilitare il bottone di inserimento e mostrare il messaggio "Limite massimo codici raggiunto per questo contratto e tipo".

**Verifica**: creare 9 codici per lo stesso contratto/tipo — al tentativo del 10mo, il bottone e disabilitato e l'avviso e visibile.

### AC-NRC-06: Bottone disabilitato

MENTRE uno o entrambi i campi del form sono vuoti, il bottone di inserimento DEVE essere disabilitato.

**Verifica**: caricare l'operazione — il bottone e disabilitato. Compilare solo un campo — resta disabilitato. Compilare entrambi — si abilita.

---

## AC-FA — forceAnnulment

### AC-FA-01: Caricamento tabella

QUANDO l'operazione viene aperta, il sistema DEVE caricare e mostrare l'elenco delle operazioni con stato diverso da CANCELLED, in una tabella paginata (default 20 righe).

**Verifica**: la tabella si popola, la paginazione mostra 20 righe, il selettore pagine funziona.

### AC-FA-02: Paginazione

QUANDO l'utente cambia la dimensione della pagina (20/50/100), la tabella DEVE aggiornarsi mostrando il numero di righe selezionato.

**Verifica**: selezionare 50 — la tabella mostra 50 righe per pagina.

### AC-FA-03: Filtro testuale

QUANDO l'utente digita nel campo filtro, la tabella DEVE filtrare su tutte le colonne mostrando solo le righe corrispondenti.

**Verifica**: digitare un codice polizza — solo le righe con quel codice vengono mostrate.

### AC-FA-04: Ordinamento

QUANDO l'utente clicca sull'header di una colonna, la tabella DEVE ordinarsi per quella colonna (toggle ascendente/discendente).

**Verifica**: cliccare su "Data invio" — le righe si ordinano per data.

### AC-FA-05: Cancellazione con conferma

QUANDO l'utente clicca sull'icona cestino, il sistema DEVE mostrare una modale di conferma. QUANDO l'utente conferma, il sistema DEVE eseguire la cancellazione (soft-delete + hard-delete in transazione) e ricaricare la tabella dal server.

**Verifica**: cancellare un'operazione — la modale appare, alla conferma la riga scompare dalla tabella, i dati correlati sono rimossi dal database.

### AC-FA-06: Interfaccia flessibile

QUANDO il backend aggiunge o rimuove colonne dallo statement SQL, il frontend DEVE adattarsi automaticamente senza modifiche al codice JavaScript.

**Verifica**: aggiungere una colonna allo statement SQL — la nuova colonna appare in tabella.

---

## AC-RDS — resetDocumentState

### AC-RDS-01: Visualizzazione stati

QUANDO l'operazione viene aperta, il sistema DEVE mostrare i draft con documenti PENDING o ERROR. Le colonne "Doc. PENDING" e "Doc. ERROR" DEVONO mostrare i rispettivi conteggi.

**Verifica**: la tabella mostra i conteggi corretti per ciascun draft.

### AC-RDS-02: Differenziazione visuale

MENTRE una riga ha documenti in stato PENDING, la riga DEVE avere sfondo bianco e icona orologio cliccabile. MENTRE una riga ha documenti in stato ERROR, la riga DEVE avere sfondo grigio e icona X rossa non cliccabile.

**Verifica**: righe PENDING sono bianche e cliccabili, righe ERROR sono grigie e non cliccabili.

### AC-RDS-03: Cambio stato

QUANDO l'utente clicca sull'icona di una riga PENDING, il sistema DEVE mostrare una modale di conferma. QUANDO l'utente conferma, il sistema DEVE cambiare lo stato di TUTTI i documenti PENDING di quel draft in ERROR e ricaricare la tabella.

**Verifica**: cambiare stato di un draft — tutti i documenti passano a ERROR, la riga diventa grigia con icona X.

### AC-RDS-04: Blocco azione su ERROR

MENTRE una riga ha documenti in stato ERROR, il sistema NON DEVE permettere alcuna azione su quella riga.

**Verifica**: tentare di cliccare sull'icona di una riga ERROR — nessuna azione viene eseguita.

---

## AC-LOG — Audit Trail

### AC-LOG-01: Tracciamento automatico

QUANDO un'operazione di modifica dati ha successo, il sistema DEVE inserire un record nella tabella `public.operation_audit_log` con: `operation_name`, `payload` (dati frontend), `user_id` (0 per ora), `created_at`.

**Verifica**: eseguire un inserimento/cancellazione/cambio stato — verificare che il record di log esista nel database.

### AC-LOG-02: Nessun log su errore

SE un'operazione fallisce, il sistema NON DEVE scrivere alcun record di log.

**Verifica**: provocare un errore (es. conflitto codice) — verificare che nessun record di log venga creato.

### AC-LOG-03: Logging trasparente via AjaxResponseHelper

QUANDO un endpoint save chiama `AjaxResponseHelper::success($data, $operation)`, il log DEVE essere scritto automaticamente senza codice esplicito nell'operazione. `operation_name` viene da `$operation->getName()`, il payload dal DTO catturato da `getRequest()`.

**Verifica**: creare un'operazione stub, chiamare success() con l'operazione — il log viene scritto senza che la classe Operation contenga codice di logging.

---

## AC-ACC — Controllo accesso operazioni

### AC-ACC-01: Operazione non visibile

QUANDO un'operazione ha `isVisible() = false`, il sistema NON DEVE includerla nella risposta di `action=list` e nessuna Card DEVE apparire per quella operazione.

**Verifica**: sovrascrivere `isVisible()` con `return false` in un'operazione — la Card non appare, la risposta JSON non contiene l'operazione.

### AC-ACC-02: Operazione disabilitata — frontend

QUANDO un'operazione ha `isEnabled() = false`, la Card DEVE essere visibile ma con opacità ridotta (0.5), cursor `not-allowed` e tooltip "Operazione temporaneamente non disponibile". Il click sulla Card NON DEVE aprire la modale.

**Verifica**: sovrascrivere `isEnabled()` con `return false` — la Card appare disabilitata, il click non produce effetto.

### AC-ACC-03: Operazione disabilitata — Factory blocca con 403

QUANDO un endpoint ajax chiama `OperationFactory::create()` per un'operazione con `isEnabled() = false`, la Factory DEVE lanciare un'eccezione che genera HTTP 403 e `{"success": false, "message": "Operazione non disponibile"}`.

**Verifica**: disabilitare un'operazione e inviare una richiesta al suo endpoint — la risposta è 403, senza che l'endpoint contenga codice di verifica esplicito.

### AC-ACC-04: Operazione invisibile ma abilitata — backend processa

QUANDO un'operazione ha `isVisible() = false` e `isEnabled() = true`, le chiamate backend DEVONO essere processate normalmente (nessun blocco).

**Verifica**: rendere un'operazione invisibile ma abilitata — la Card non appare ma le chiamate API funzionano.

---

## AC-ERR — Gestione errori

### AC-ERR-01: Toast su errore Ajax

QUANDO una chiamata Ajax restituisce `success: false`, il sistema DEVE mostrare un Toast Bootstrap sovrapposto a tutti gli elementi con il messaggio di errore.

**Verifica**: provocare un errore backend — il Toast appare sopra la modale.

### AC-ERR-02: Chiusura esplicita

MENTRE un Toast di errore e visibile, il sistema DEVE mantenerlo visibile fino a che l'utente non lo chiude esplicitamente.

**Verifica**: provocare un errore — il Toast resta visibile anche dopo scroll o interazione con altri elementi. Si chiude solo cliccando la X.
