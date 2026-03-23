# Deployment — bperOperations

Riferimento: *DevOps Best Practices*

---

## 1. Prerequisiti

| Requisito           | Dettaglio                                                 |
|---------------------|-----------------------------------------------------------|
| Runtime PHP         | 8.2 con estensioni `pdo_pgsql`, `json`, `mbstring`        |
| Database PostgreSQL | 16, schema `ntt_bper` e `public` accessibili              |
| Web server          | Apache/Nginx configurato per esporre la root del progetto |
| Rete                | Il web server deve poter raggiungere il database          |
| CDN                 | Accesso a jsdelivr.net e unpkg.com (Vue.js, Bootstrap)    |

---

## 2. Struttura degli ambienti

| Ambiente    | Database      | Host DB | ENV_IS_DEV | Note                                  |
|-------------|---------------|---------|------------|---------------------------------------|
| Development | `fa-dev-bper` | `fa-db` | `true`     | Docker network `fa-universe`          |
| Test        | `fa-dev-bper` | `fa-db` | `true`     | Stesso DB di dev, fixture con cleanup |
| Production  | `fa-dev`      | `fa-db` | `false`    |                                       |

---

## 3. Configurazione ambiente

Il file `lib/env_settings.php` contiene le costanti di configurazione del database, nessun altro parametro da aggiungere.

---

## 4. Migrazione database

Prima del primo deploy, eseguire lo script di creazione della tabella audit log:

```sql
CREATE TABLE IF NOT EXISTS public.operation_audit_log (
    id         SERIAL PRIMARY KEY,
    operation_name TEXT NOT NULL,
    payload    JSONB NOT NULL,
    user_id    INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT NOW()
);
```

---

## 5. Procedura di deploy

### 5.1 Checklist pre-deploy

- [ ] Tutti i test passano (`./run_tests.sh`)
- [ ] Migrazione database eseguita (tabella `operation_audit_log`)

### 5.2 Passi

1. Copiare i file nel progetto fa-bper-platform nella stessa alberatura qua replicata
2. Verificare la connessione al database
3. Abilitare l'accesso alla pagina principale
4. Adattare l'accesso alle pagine ajax

Codice da aggiungere:
```PhP
use FirstAdvisory\FAWill\model\libs\cls_Auth;
require_once __DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'init.php';

// Gestione sessione e autenticazione
$clsAuth = unserialize($_SESSION['logged_user'] ?? '') ?: new cls_Auth();
```

Codice da rimuovere:
```PhP
require_once __DIR__ . '/../../../lib/autoloader.php';
```
---

## 6. Integrazione CI/CD

Al momento non e prevista una pipeline CI/CD automatizzata. Il deploy e manuale.

---

## 7. Monitoraggio post-deploy

| Cosa verificare            | Come                                                                   |
|----------------------------|------------------------------------------------------------------------|
| Pagina principale caricata | Accesso via browser, verificare le Card                                |
| Operazioni funzionanti     | Aprire ciascuna modale, verificare caricamento dati                    |
| Operazioni funzionanti     | Verificare le azioni di ciascuna pagina                                |
| Log audit                  | Eseguire un'operazione e verificare il record in `operation_audit_log` |
