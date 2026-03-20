# ADR-003: Caricamento dinamico e persistenza delle app Vue

**Stato**: Accettato
**Data**: 2026-03-20
**Contesto**: Come caricare e gestire il ciclo di vita delle app Vue delle operazioni

## Decisione

1. Alla prima apertura di una Card, il file JS viene caricato via `<script>` dinamico.
2. L'app Vue viene creata con `Vue.createApp(Operazione).mount('#container-dedicato')`.
3. Alla chiusura della modale, l'app **non viene smontata** (show/hide CSS).
4. Alla riapertura, i dati delle tabelle vengono ricaricati dal server.

## Alternative considerate

- **Mount/unmount ad ogni apertura**: piu pulito in termini di memoria, ma l'utente perde lo stato del form ad ogni chiusura. Scartato su richiesta esplicita del committente.
- **Iframe**: isolamento completo, ma complessita di comunicazione e stile incoerente. Scartato.

## Motivazione

- L'utente vuole preservare lo stato del form tra aperture/chiusure della modale.
- Con sole 3 operazioni, l'impatto in memoria e trascurabile.
- Il reload dei dati tabellari alla riapertura previene il rischio di dati stale.

## Conseguenze

- Ogni operazione ha un container DOM dedicato (non condiviso).
- Il JS viene caricato una sola volta per sessione.
- I dati del form sono preservati, i dati delle tabelle sono sempre freschi.
