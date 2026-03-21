#!/bin/bash
#
# run_tests.sh — Esegue tutte le suite di test e produce un report finale.
#
# Uso:
#   ./run_tests.sh              # Tutte le suite (PHPStan + PHPUnit + Playwright)
#   ./run_tests.sh --php-only   # Solo PHPStan + PHPUnit (no browser)
#   ./run_tests.sh --e2e-only   # Solo Playwright E2E
#

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$SCRIPT_DIR"

# Colori per output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m'

# Contatori risultati
declare -A RESULTS
TOTAL_PASS=0
TOTAL_FAIL=0
TOTAL_SKIP=0

START_TIME=$(date +%s)

print_header() {
    echo ""
    echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${BOLD}  $1${NC}"
    echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
}

print_result() {
    local name="$1"
    local status="$2"
    case "$status" in
        PASS) echo -e "  ${GREEN}PASS${NC}  $name" ;;
        FAIL) echo -e "  ${RED}FAIL${NC}  $name" ;;
        SKIP) echo -e "  ${YELLOW}SKIP${NC}  $name" ;;
    esac
}

set_result() {
    local key="$1"
    local status="$2"
    RESULTS[$key]="$status"
    case "$status" in
        PASS) ((TOTAL_PASS++)) ;;
        FAIL) ((TOTAL_FAIL++)) ;;
        SKIP) ((TOTAL_SKIP++)) ;;
    esac
}

# ─── Parsing argomenti ────────────────────────────────────────────────────────
RUN_PHP=true
RUN_E2E=true

case "${1:-}" in
    --php-only)  RUN_E2E=false ;;
    --e2e-only)  RUN_PHP=false ;;
esac

# ─── 1. Migrazioni DB ────────────────────────────────────────────────────────
if [ "$RUN_PHP" = true ]; then
    print_header "1/4  Migrazioni database"
    docker compose -f tests/docker-compose.yml run --rm phpunit php -r "
        require_once 'lib/env_settings.php';
        \$dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', ENV_DB_HOST, ENV_DB_PORT, ENV_DB_DATABABE);
        \$pdo = new PDO(\$dsn, ENV_DB_USER, ENV_DB_PASSWORD);
        \$sql = file_get_contents('tests/migrations/001_create_audit_log.sql');
        \$pdo->exec(\$sql);
        echo 'Migration completed successfully.' . PHP_EOL;
    " 2>&1
    if [ $? -eq 0 ]; then
        set_result migration PASS
    else
        set_result migration FAIL
        echo -e "${RED}Migration fallita — i test PHPUnit potrebbero non funzionare.${NC}"
    fi
fi

# ─── 2. PHPStan ──────────────────────────────────────────────────────────────
if [ "$RUN_PHP" = true ]; then
    print_header "2/4  PHPStan (analisi statica livello 8)"
    docker compose -f tests/docker-compose.yml run --rm phpunit vendor/bin/phpstan analyse --no-progress --error-format=table 2>&1
    if [ $? -eq 0 ]; then
        set_result phpstan PASS
    else
        set_result phpstan FAIL
    fi
fi

# ─── 3. PHPUnit ──────────────────────────────────────────────────────────────
if [ "$RUN_PHP" = true ]; then
    print_header "3/4  PHPUnit (Unit + Integration + Security)"

    PHPUNIT_OUTPUT=$(docker compose -f tests/docker-compose.yml run --rm phpunit \
        vendor/bin/phpunit -c tests/phpunit.xml --testdox 2>&1)
    PHPUNIT_EXIT=$?

    echo "$PHPUNIT_OUTPUT"

    if [ $PHPUNIT_EXIT -eq 0 ]; then
        set_result phpunit_unit PASS
        set_result phpunit_integration PASS
        set_result phpunit_security PASS
    else
        set_result phpunit_unit FAIL
        set_result phpunit_integration FAIL
        set_result phpunit_security FAIL
    fi
fi

# ─── 4. Playwright E2E ───────────────────────────────────────────────────────
# Nomi dei file spec e le chiavi report corrispondenti
E2E_SPECS=("main-app" "new-retrieval-code" "force-annulment" "reset-document-state" "error-handling")
E2E_LABELS=("E-MAIN  (MainApp)" "E-NRC   (NewRetrievalCode)" "E-FA    (ForceAnnulment)" "E-RDS   (ResetDocumentState)" "E-ERR   (Error handling)")

if [ "$RUN_E2E" = true ]; then
    print_header "4/4  Playwright E2E"

    if [ ! -d "tests/e2e" ] || [ ! -f "tests/e2e/package.json" ]; then
        echo -e "${YELLOW}Directory tests/e2e non trovata — skip.${NC}"
        for spec in "${E2E_SPECS[@]}"; do
            set_result "e2e_${spec//-/_}" SKIP
        done
    else
        cd tests/e2e

        # Installa dipendenze se necessario
        if [ ! -d "node_modules" ] || [ ! -f "node_modules/.package-lock.json" ]; then
            echo "Installazione dipendenze npm..."
            npm install --silent 2>/dev/null
        fi

        # Verifica che Chromium sia installato
        echo "Verifica browser Playwright..."
        npx playwright install --with-deps chromium 2>&1

        # Esegui i test — usa reporter JSON per parsing affidabile + list per output leggibile
        echo ""
        echo "Esecuzione test E2E..."
        E2E_JSON_FILE=$(mktemp /tmp/pw-results-XXXXXX.json)

        npx playwright test --reporter=list,json 2>&1 | tee /dev/stderr
        E2E_EXIT=${PIPESTATUS[0]}

        # Prova a leggere il report JSON (Playwright lo scrive su stdout con reporter=json)
        # Se il JSON non è disponibile, usiamo l'exit code globale
        # Il reporter json scrive su PLAYWRIGHT_JSON_OUTPUT_NAME se impostato
        PLAYWRIGHT_JSON_OUTPUT_NAME="$E2E_JSON_FILE" npx playwright test --reporter=json 2>/dev/null > "$E2E_JSON_FILE" || true

        # Analizza i risultati per ogni spec file
        for spec in "${E2E_SPECS[@]}"; do
            key="e2e_${spec//-/_}"

            if [ -s "$E2E_JSON_FILE" ] && command -v node &>/dev/null; then
                # Parsing JSON con Node.js per risultati precisi
                SPEC_STATUS=$(node -e "
                    try {
                        const r = JSON.parse(require('fs').readFileSync('$E2E_JSON_FILE', 'utf8'));
                        const suites = (r.suites || []);
                        const specs = [];
                        function collect(s) {
                            if (s.file && s.file.includes('${spec}')) specs.push(...(s.specs || []));
                            (s.suites || []).forEach(collect);
                        }
                        suites.forEach(collect);
                        if (specs.length === 0) { console.log('SKIP'); process.exit(); }
                        const failed = specs.some(s => s.tests && s.tests.some(t =>
                            t.results && t.results.some(r => r.status !== 'passed' && r.status !== 'skipped')
                        ));
                        console.log(failed ? 'FAIL' : 'PASS');
                    } catch(e) { console.log('UNKNOWN'); }
                " 2>/dev/null)

                case "$SPEC_STATUS" in
                    PASS|FAIL|SKIP) set_result "$key" "$SPEC_STATUS" ;;
                    *)
                        # Fallback: se non si riesce a parsare il JSON, usa l'exit code globale
                        if [ $E2E_EXIT -eq 0 ]; then
                            set_result "$key" PASS
                        else
                            set_result "$key" FAIL
                        fi
                        ;;
                esac
            else
                # Nessun JSON disponibile — usa l'exit code globale
                if [ $E2E_EXIT -eq 0 ]; then
                    set_result "$key" PASS
                else
                    set_result "$key" FAIL
                fi
            fi
        done

        rm -f "$E2E_JSON_FILE"
        cd "$SCRIPT_DIR"
    fi
fi

# ─── Report Finale ───────────────────────────────────────────────────────────
END_TIME=$(date +%s)
DURATION=$((END_TIME - START_TIME))

echo ""
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BOLD}  REPORT FINALE${NC}"
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

if [ "$RUN_PHP" = true ]; then
    echo -e "${BOLD}  Analisi statica${NC}"
    print_result "DB Migration" "${RESULTS[migration]:-SKIP}"
    print_result "PHPStan livello 8" "${RESULTS[phpstan]:-SKIP}"
    echo ""

    echo -e "${BOLD}  PHPUnit${NC}"
    print_result "Unit tests" "${RESULTS[phpunit_unit]:-SKIP}"
    print_result "Integration tests" "${RESULTS[phpunit_integration]:-SKIP}"
    print_result "Security tests" "${RESULTS[phpunit_security]:-SKIP}"
    echo ""
fi

if [ "$RUN_E2E" = true ]; then
    echo -e "${BOLD}  Playwright E2E${NC}"
    for i in "${!E2E_SPECS[@]}"; do
        key="e2e_${E2E_SPECS[$i]//-/_}"
        print_result "${E2E_LABELS[$i]}" "${RESULTS[$key]:-SKIP}"
    done
    echo ""
fi

echo -e "${CYAN}───────────────────────────────────────────────────────────────────${NC}"
TOTAL=$((TOTAL_PASS + TOTAL_FAIL + TOTAL_SKIP))
echo -e "  Totale: ${BOLD}${TOTAL}${NC} suite  |  ${GREEN}${TOTAL_PASS} pass${NC}  |  ${RED}${TOTAL_FAIL} fail${NC}  |  ${YELLOW}${TOTAL_SKIP} skip${NC}"
echo -e "  Durata: ${DURATION}s"
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

if [ $TOTAL_FAIL -gt 0 ]; then
    exit 1
fi
exit 0
