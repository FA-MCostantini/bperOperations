# Deployment — bperOperations

Riferimento: *DevOps Best Practices*

---

## 1. Prerequisiti

| Requisito                | Dettaglio                                               |
|--------------------------|---------------------------------------------------------|
| Runtime PHP              | 8.2 con estensioni `pdo_pgsql`, `json`, `mbstring`     |
| Database PostgreSQL      | 16, schema `ntt_bper` e `public` accessibili            |
| Web server               | Apache/Nginx configurato per esporre la root del progetto |
| Rete                     | Il web server deve poter raggiungere il database        |
| CDN                      | Accesso a jsdelivr.net e unpkg.com (Vue.js, Bootstrap)  |

---

## 2. Struttura degli ambienti

| Ambiente      | Database          | Host DB   | ENV_IS_DEV | Note                              |
|---------------|-------------------|-----------|------------|-----------------------------------|
| Development   | `fa-dev-bper`     | `fa-db`   | `true`     | Docker network `fa-universe`      |
| Test          | `fa-dev-bper`     | `fa-db`   | `true`     | Stesso DB di dev, fixture con cleanup |
| Production    | TBD               | TBD       | `false`    | Configurazione futura             |

---

## 3. Configurazione ambiente

Il file `lib/env_settings.php` contiene le costanti di configurazione. Per il deploy in produzione, questo file deve essere aggiornato con i valori corretti:

```php
define('ENV_IS_DEV', false);
define('ENVIRONMENT_NAME', 'production');
define('ENV_DB_HOST', '<host-produzione>');
define('ENV_DB_PORT', '5432');
define('ENV_DB_USER', '<utente-produzione>');
define('ENV_DB_PASSWORD', '<password-produzione>');
define('ENV_DB_DATABABE', '<database-produzione>');
```

**Nota**: il file contiene credenziali e NON deve essere versionato con valori di produzione.

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
- [ ] `env_settings.php` configurato per l'ambiente target
- [ ] Migrazione database eseguita (tabella `operation_audit_log`)
- [ ] Web server configurato per esporre la root del progetto
- [ ] Accesso CDN verificato (jsdelivr.net, unpkg.com)

### 5.2 Passi

1. Copiare i file del progetto nel document root del web server
2. Verificare i permessi di lettura su tutti i file
3. Verificare la connessione al database
4. Testare l'accesso alla pagina principale
5. Verificare che le operazioni vengano caricate correttamente

### 5.3 Rollback

In caso di problemi:
1. Ripristinare i file dalla versione precedente
2. La tabella `operation_audit_log` non richiede rollback (solo INSERT, dati non distruttivi)

---

## 6. Integrazione CI/CD

Al momento non e prevista una pipeline CI/CD automatizzata. Il deploy e manuale. Quando sara necessario, la pipeline dovra:

1. Eseguire `./run_tests.sh`
2. Verificare che tutti i test passino
3. Copiare i file nell'ambiente target
4. Eseguire le migrazioni database
5. Notificare il team

---

## 7. Monitoraggio post-deploy

| Cosa verificare                        | Come                                                    |
|----------------------------------------|---------------------------------------------------------|
| Pagina principale caricata             | Accesso via browser, verificare le Card                 |
| Operazioni funzionanti                 | Aprire ciascuna modale, verificare caricamento dati     |
| Connessione DB                         | Verificare che le tabelle mostrino dati                 |
| Log audit                              | Eseguire un'operazione e verificare il record in `operation_audit_log` |
| Errori nascosti                        | Controllare i log del web server                        |
