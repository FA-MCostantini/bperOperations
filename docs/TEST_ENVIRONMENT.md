# Ambiente di Test — bperOperations

Standard di riferimento: **IEEE 829 — Test Documentation**

---

## 1. Architettura dell'ambiente di test

```
┌─────────────────────────────────────────────────┐
│                Rete: fa-universe                │
│                                                 │
│  ┌──────────────┐       ┌──────────────────┐    │
│  │   fa-db       │       │  bper-test       │    │
│  │  PostgreSQL 16│◄──────│  PHP 8.2         │    │
│  │  (esistente)  │       │  PHPUnit         │    │
│  │               │       │  Playwright      │    │
│  └──────────────┘       └──────────────────┘    │
│                                                 │
└─────────────────────────────────────────────────┘
```

Il container di test (`bper-test`) si collega alla rete Docker `fa-universe` dove e gia presente il container database `fa-db`.

---

## 2. Configurazione Docker

### 2.1 docker-compose.yml

Posizione: `tests/docker-compose.yml`

```yaml
services:
  bper-test:
    build:
      context: .
      dockerfile: Dockerfile
    volumes:
      - ../:/app
    working_dir: /app
    networks:
      - fa-universe
    depends_on: []

networks:
  fa-universe:
    name: fa-universe
    external: true
```

### 2.2 Dockerfile

Posizione: `tests/Dockerfile`

Requisiti dell'immagine:
- PHP 8.2 con estensioni: `pdo_pgsql`, `json`, `mbstring`
- PHPUnit (ultima versione compatibile con PHP 8.2)
- Node.js + Playwright (per test E2E)
- Composer (per installare PHPUnit)

---

## 3. Connessione al database

| Parametro | Valore         | Sorgente              |
|-----------|----------------|-----------------------|
| Host      | `fa-db`        | `lib/env_settings.php` |
| Porta     | `5432`         | `lib/env_settings.php` |
| Database  | `fa-dev-bper`  | `lib/env_settings.php` |
| Utente    | `postgres`     | `lib/env_settings.php` |
| Password  | `local!Passw0rd` | `lib/env_settings.php` |

Il database di test e lo stesso database di sviluppo. Non si e in produzione: l'ambiente e dedicato ai test.

---

## 4. Fixture

### 4.1 Strategia

Le fixture inseriscono dati direttamente nelle tabelle reali del database. Al termine di ogni test, i dati fittizi vengono rimossi.

### 4.2 Pattern di cleanup

```
setUp()    → INSERT dati fittizi con identificatori univoci (es. prefisso 'TEST_')
test()     → Esecuzione test
tearDown() → DELETE dei dati fittizi inseriti
```

### 4.3 Tabelle coinvolte

| Tabella                                  | Operazione che la usa       | Tipo dati fixture                    |
|------------------------------------------|-----------------------------|--------------------------------------|
| `ntt_bper.t_ath_policy_auth_code`        | newRetrievalCode            | Codici riscatto fittizi              |
| `ntt_bper.v_policy` (vista, sola lettura)| newRetrievalCode            | Non inseribili (vista). Usare dati esistenti o creare tabella base |
| `ntt_bper.t_policy_operation`            | forceAnnulment              | Operazioni fittizie                  |
| `ntt_bper.t_param_operation_type`        | forceAnnulment, resetDocumentState | Parametri tipo operazione (probabilmente gia popolata) |
| `ntt_bper.t_policy_operation_draft`      | forceAnnulment, resetDocumentState | Draft fittizi                   |
| `ntt_bper.t_int_policy_operation_docs`   | forceAnnulment              | Documenti operazione fittizi         |
| `ntt_bper.t_ath_policy_operation_docs`   | resetDocumentState          | Documenti con stato PENDING/ERROR    |
| `public.operation_audit_log`             | Tutte                       | Log generati dai test (da rimuovere) |

### 4.4 Isolamento

- Usare valori univoci e riconoscibili (es. `bper_contract_number = 'TEST00001'`)
- Il `tearDown()` rimuove solo i record con identificatori di test
- I test non devono dipendere dall'ordine di esecuzione

---

## 5. Pagina HTML di test

Per i test E2E con Playwright, costruire una pagina HTML completa assemblando i frammenti del controller:

```php
$ctl = new ctl_operations();
$html = '<!DOCTYPE html><html><head>'
      . $ctl->getHead()
      . '</head><body>'
      . $ctl->getContent()
      . $ctl->getScript()
      . '</body></html>';
```

Servire questa pagina tramite il web server PHP built-in (`php -S`) all'interno del container di test.

---

## 6. Esecuzione

### 6.1 Script di lancio

Nella root del progetto: `run_tests.sh`

```bash
#!/bin/bash
cd tests
docker compose up --build --abort-on-container-exit
docker compose down
```

### 6.2 Report

Al termine dei test, generare:
- Report PHPUnit in formato JUnit XML + testo leggibile
- Report Playwright in formato HTML
- Entrambi salvati nella cartella `tests/reports/`
