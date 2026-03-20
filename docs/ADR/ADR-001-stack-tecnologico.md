# ADR-001: Stack tecnologico

**Stato**: Accettato
**Data**: 2026-03-20
**Contesto**: Scelta dello stack per bperOperations

## Decisione

| Componente | Scelta               | Alternativa considerata |
|------------|----------------------|-------------------------|
| Backend    | PHP 8.2              | PHP 7.4 (scartato: manca supporto enum, readonly, match) |
| Database   | PostgreSQL 16        | Nessuna alternativa (vincolo infrastrutturale esistente)  |
| Frontend   | Vue.js 3 CDN (Options API) | Vue.js SPA con build tool (scartato: complessita eccessiva per il contesto) |
| CSS        | Bootstrap 5 CDN      | Tailwind CSS (scartato: non presente nell'ecosistema esistente) |

## Motivazione

- **PHP 8.2**: il runtime di produzione e gia PHP 8.2. Le feature moderne (enum, readonly, match, constructor promotion) migliorano leggibilita e sicurezza del codice.
- **Vue.js CDN Options API**: il progetto non ha un build system (webpack/vite). Le operazioni sono app auto-consistenti caricate dinamicamente. L'Options API e sufficiente per la complessita delle interfacce richieste.
- **Bootstrap 5 CDN**: coerente con l'ecosistema del framework esterno che assembla la pagina in produzione.
- **Nessun package manager**: tutte le dipendenze via CDN, nessun `npm install` o `composer install` richiesto.

## Conseguenze

- Non si possono usare feature che richiedono un build step (SFC `.vue`, TypeScript, Pinia).
- Le app Vue sono definite come oggetti JavaScript puri (Options API).
- Il CSS custom e minimale (Bootstrap copre la maggior parte dei casi).
