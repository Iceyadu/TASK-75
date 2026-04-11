#!/usr/bin/env bash
# RideCircle Carpool Marketplace -- Test Runner
# This script is the single entrypoint for running all tests.
#
# Usage:
#   ./run_tests.sh              # Run all tests
#   ./run_tests.sh unit         # Run unit tests only
#   ./run_tests.sh api          # Run API integration tests only
#   ./run_tests.sh --help       # Show usage
#
# Prerequisites:
#   - PHP 8.x with required extensions
#   - MySQL running and accessible
#   - Backend dependencies installed (composer install)
#
# Exit codes:
#   0 = all tests passed
#   1 = one or more tests failed
#   2 = environment or configuration error

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
UNIT_DIR="${SCRIPT_DIR}/unit_tests"
API_DIR="${SCRIPT_DIR}/API_tests"
BACKEND_DIR="${SCRIPT_DIR}/backend"
PHPUNIT="${BACKEND_DIR}/vendor/bin/phpunit"
PHPUNIT_CONFIG="${BACKEND_DIR}/phpunit.xml"
API_BASE_URL_DEFAULT="${API_BASE_URL:-http://localhost:8081}"
# Match repo/docker-compose.yml defaults for CI/API fixture loading
DB_SEED_USER="${DB_USERNAME:-ridecircle}"
DB_SEED_PASS="${DB_PASSWORD:-change_me_in_production}"
DB_SEED_NAME="${DB_DATABASE:-ridecircle}"
DB_ROOT_PASS="${DB_ROOT_PASSWORD:-rootsecret}"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

usage() {
    echo "Usage: $0 [unit|api|all|--help]"
    echo ""
    echo "  unit     Run backend unit tests only"
    echo "  api      Run API integration tests only"
    echo "  all      Run all tests (default)"
    echo "  --help   Show this help message"
    exit 0
}

log_info()  { echo -e "${GREEN}[INFO]${NC} $1"; }
log_warn()  { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }

check_prerequisites() {
    if ! command -v php &> /dev/null; then
        log_error "PHP is not installed or not in PATH"
        exit 2
    fi
    log_info "PHP version: $(php -v | head -n1)"

    if [ ! -f "$PHPUNIT" ]; then
        log_warn "PHPUnit not found at ${PHPUNIT}"
        if ! command -v composer &> /dev/null; then
            log_error "Composer is not installed or not in PATH."
            log_error "Run 'cd ${BACKEND_DIR} && composer install' to install dependencies."
            exit 2
        fi
        log_info "Installing backend dependencies with composer..."
        (
            cd "${BACKEND_DIR}"
            composer install --no-interaction --prefer-dist
        )
    fi

    if [ ! -f "$PHPUNIT" ]; then
        log_error "PHPUnit still not found after composer install at ${PHPUNIT}"
        exit 2
    fi

    if [ ! -f "$PHPUNIT_CONFIG" ]; then
        log_error "PHPUnit configuration not found at ${PHPUNIT_CONFIG}"
        exit 2
    fi
}

docker_bin() {
    if command -v docker &> /dev/null; then
        echo docker
        return 0
    fi
    return 1
}

mysql_container_ready() {
    local d c
    d="$(docker_bin)" || return 1
    # Require schema to be applied (initdb finished). Use root until app user exists.
    c="$("$d" exec ridecircle-mysql mysql -N -s \
        -uroot -p"${DB_ROOT_PASS}" \
        -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_SEED_NAME}' AND table_name='organizations'" 2>/dev/null || echo "0")"
    [[ "${c:-0}" == "1" ]]
}

wait_for_docker_mysql() {
    local d i max=90
    d="$(docker_bin)" || return 0
    if ! "$d" ps --format '{{.Names}}' 2>/dev/null | grep -qx 'ridecircle-mysql'; then
        return 0
    fi
    log_info "Waiting for MySQL container (ridecircle-mysql) to accept connections..."
    for i in $(seq 1 "$max"); do
        if mysql_container_ready; then
            log_info "MySQL is ready."
            return 0
        fi
        sleep 2
    done
    log_error "MySQL container did not become ready within $((max * 2))s."
    return 1
}

wait_for_api_backend() {
    local url code i max=60
    url="${API_BASE_URL_DEFAULT%/}/api/auth/me"
    log_info "Waiting for API backend at ${url}..."
    for i in $(seq 1 "$max"); do
        code="$(curl -s -o /dev/null -w '%{http_code}' \
            -H 'Accept: application/json' \
            "$url" 2>/dev/null || true)"
        if [[ "$code" == "401" || "$code" == "200" ]]; then
            log_info "API backend responded (HTTP ${code})."
            return 0
        fi
        sleep 2
    done
    log_error "API backend did not become ready at ${url} (last HTTP code: ${code:-none})."
    return 1
}

ensure_docker_api_seed_data() {
    local d count
    d="$(docker_bin)" || return 0
    if ! "$d" ps --format '{{.Names}}' 2>/dev/null | grep -qx 'ridecircle-mysql'; then
        log_warn "ridecircle-mysql not running; skipping automatic DB seed (ensure org/users exist for API tests)."
        return 0
    fi
    if ! "$d" ps --format '{{.Names}}' 2>/dev/null | grep -qx 'ridecircle-backend'; then
        log_warn "ridecircle-backend not running; cannot run DatabaseSeeder in container."
        return 0
    fi
    count="$( "$d" exec ridecircle-mysql mysql -N -s \
        -u"${DB_SEED_USER}" -p"${DB_SEED_PASS}" \
        -D "${DB_SEED_NAME}" \
        -e "SELECT COUNT(*) FROM organizations" 2>/dev/null || echo "0")"
    if [[ "${count:-0}" != "0" ]]; then
        log_info "Database already has organizations row(s); skipping seed."
        return 0
    fi
    log_info "Loading API fixture data (DatabaseSeeder) into MySQL..."
    if ! "$d" exec ridecircle-backend php /var/www/html/database/seeds/DatabaseSeeder.php 2>/dev/null \
        | "$d" exec -i ridecircle-mysql mysql -u"${DB_SEED_USER}" -p"${DB_SEED_PASS}" -D "${DB_SEED_NAME}"; then
        log_error "DatabaseSeeder failed. API integration tests will likely fail."
        return 1
    fi
    log_info "API fixture data loaded."
    return 0
}

# Add second-tenant rows for cross-org API tests when DB was created from an older single-org seed.
ensure_second_organization_fixtures() {
    local d
    d="$(docker_bin)" || return 0
    if ! "$d" ps --format '{{.Names}}' 2>/dev/null | grep -qx 'ridecircle-mysql'; then
        return 0
    fi
    if ! "$d" ps --format '{{.Names}}' 2>/dev/null | grep -qx 'ridecircle-backend'; then
        return 0
    fi
    log_info "Ensuring second-organization fixtures (idempotent)..."
    if ! "$d" exec ridecircle-backend php /var/www/html/database/seeds/EnsureSecondOrganization.php 2>/dev/null \
        | "$d" exec -i ridecircle-mysql mysql -u"${DB_SEED_USER}" -p"${DB_SEED_PASS}" -D "${DB_SEED_NAME}"; then
        log_warn "EnsureSecondOrganization failed (non-fatal if already applied)."
    fi
    return 0
}

run_unit_tests() {
    log_info "Running unit tests..."
    if [ -d "$UNIT_DIR" ] && [ "$(ls -A "$UNIT_DIR" 2>/dev/null)" ]; then
        cd "${BACKEND_DIR}"
        php vendor/bin/phpunit --configuration phpunit.xml --testsuite unit
    else
        log_warn "No unit tests found in ${UNIT_DIR}"
    fi
}

run_api_tests() {
    log_info "Running API integration tests..."
    export API_BASE_URL="${API_BASE_URL_DEFAULT}"
    log_info "API_BASE_URL=${API_BASE_URL}"
    # CI runs docker compose up then tests immediately; MySQL init + schema can take >30s.
    wait_for_docker_mysql || exit 2
    wait_for_api_backend || exit 2
    ensure_docker_api_seed_data || exit 2
    ensure_second_organization_fixtures
    if [ -d "$API_DIR" ] && [ "$(ls -A "$API_DIR" 2>/dev/null)" ]; then
        cd "${BACKEND_DIR}"
        php vendor/bin/phpunit --configuration phpunit.xml --testsuite api
    else
        log_warn "No API tests found in ${API_DIR}"
    fi
}

# Main
case "${1:-all}" in
    unit)
        check_prerequisites
        run_unit_tests
        ;;
    api)
        check_prerequisites
        run_api_tests
        ;;
    all)
        check_prerequisites
        run_unit_tests
        run_api_tests
        ;;
    --help|-h)
        usage
        ;;
    *)
        log_error "Unknown argument: $1"
        usage
        ;;
esac

log_info "Test run complete."
exit 0
