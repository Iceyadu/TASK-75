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
        log_error "PHPUnit not found at ${PHPUNIT}"
        log_error "Run 'cd ${BACKEND_DIR} && composer install' to install dependencies."
        exit 2
    fi

    if [ ! -f "$PHPUNIT_CONFIG" ]; then
        log_error "PHPUnit configuration not found at ${PHPUNIT_CONFIG}"
        exit 2
    fi
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
