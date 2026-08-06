#!/usr/bin/env bash
# =============================================================================
# VeLog — Quality Check Script
# Author: Mamflow <https://mamflow.com>
#
# Usage:
#   npm run check
#   bash bin/check.sh
#
# Runs:
#   1. PHP Syntax Lint
#   2. PHP_CodeSniffer (PHPCS)
#   3. PHPStan (level 8)
#
# Output:
#   - Console (color-coded)
#   - reports/check-YYYY-MM-DD_HH-MM-SS.log
# =============================================================================

set -euo pipefail

# ─── Paths ───────────────────────────────────────────────────────────────────
PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
REPORTS_DIR="${PLUGIN_DIR}/reports"
PHPCS="${PLUGIN_DIR}/vendor/bin/phpcs"
PHPSTAN="${PLUGIN_DIR}/vendor/bin/phpstan"
PHPSTAN_CONFIG="${PLUGIN_DIR}/phpstan.neon"
PHPCS_CONFIG="${PLUGIN_DIR}/phpcs.xml"

# ─── Colors ──────────────────────────────────────────────────────────────────
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
BOLD='\033[1m'
RESET='\033[0m'

# ─── Report file ─────────────────────────────────────────────────────────────
mkdir -p "${REPORTS_DIR}"
TIMESTAMP="$(date '+%Y-%m-%d_%H-%M-%S')"
REPORT_FILE="${REPORTS_DIR}/check-${TIMESTAMP}.log"

# ─── Helpers ─────────────────────────────────────────────────────────────────
log() {
    echo -e "$1" | tee -a "${REPORT_FILE}"
}

log_raw() {
    echo "$1" | tee -a "${REPORT_FILE}"
}

separator() {
    log "${CYAN}$(printf '%.0s─' {1..72})${RESET}"
}

pass() {
    log "${GREEN}${BOLD}  ✓ PASS${RESET}  $1"
}

fail() {
    log "${RED}${BOLD}  ✗ FAIL${RESET}  $1"
}

warn() {
    log "${YELLOW}${BOLD}  ⚠ WARN${RESET}  $1"
}

# ─── Header ──────────────────────────────────────────────────────────────────
{
    echo "============================================================"
    echo " VeLog Quality Check Report"
    echo " Author  : Mamflow (https://mamflow.com)"
    echo " Date    : $(date '+%Y-%m-%d %H:%M:%S %Z')"
    echo " Plugin  : ${PLUGIN_DIR}"
    echo "============================================================"
    echo ""
} | tee "${REPORT_FILE}"

ERRORS=0
WARNINGS=0

# ─── Check: vendor/ exists ───────────────────────────────────────────────────
separator
log "${BOLD}[0/3] Pre-flight: Composer dependencies${RESET}"
separator

if [ ! -f "${PHPCS}" ] || [ ! -f "${PHPSTAN}" ]; then
    fail "vendor/ not found. Run: composer install"
    log ""
    log "${RED}Aborting — run \`composer install\` first.${RESET}"
    echo "" >> "${REPORT_FILE}"
    echo "RESULT: ABORTED (missing vendor/)" >> "${REPORT_FILE}"
    exit 1
fi

pass "vendor/ present"
log ""

# ─── Check 1: PHP Syntax ─────────────────────────────────────────────────────
separator
log "${BOLD}[1/3] PHP Syntax Lint${RESET}"
separator

PHP_FILES=(
    "velog.php"
    "uninstall.php"
    "src/Core/Plugin.php"
    "src/Core/Loader.php"
    "src/Core/Activator.php"
    "src/Core/Deactivator.php"
    "tests/bootstrap.php"
)

SYNTAX_ERRORS=0
for file in "${PHP_FILES[@]}"; do
    full_path="${PLUGIN_DIR}/${file}"
    if [ -f "${full_path}" ]; then
        result=$(php -l "${full_path}" 2>&1)
        if echo "${result}" | grep -q "No syntax errors"; then
            pass "${file}"
        else
            fail "${file}"
            log_raw "     ${result}"
            (( SYNTAX_ERRORS++ )) || true
            (( ERRORS++ )) || true
        fi
    else
        warn "${file} — not found (skipped)"
        (( WARNINGS++ )) || true
    fi
done

if [ "${SYNTAX_ERRORS}" -eq 0 ]; then
    log ""
    log "${GREEN}  PHP Syntax: All files OK${RESET}"
else
    log ""
    log "${RED}  PHP Syntax: ${SYNTAX_ERRORS} file(s) with errors${RESET}"
fi

log ""

# ─── Check 2: PHPCS ──────────────────────────────────────────────────────────
separator
log "${BOLD}[2/3] PHP_CodeSniffer (WordPress Coding Standards)${RESET}"
separator

PHPCS_OUTPUT=$(
    "${PHPCS}" \
        velog.php uninstall.php src/ \
        --standard="${PHPCS_CONFIG}" \
        --report=full \
        --colors \
        2>&1
) || true

PHPCS_ERRORS=$(echo "${PHPCS_OUTPUT}" | grep -c "| ERROR" || true)
PHPCS_WARNINGS=$(echo "${PHPCS_OUTPUT}" | grep -c "| WARNING" || true)
PHPCS_ERRORS=$(echo "${PHPCS_ERRORS}" | tr -d '[:space:]')
PHPCS_WARNINGS=$(echo "${PHPCS_WARNINGS}" | tr -d '[:space:]')

# Write raw output (strip ANSI for log file)
echo "${PHPCS_OUTPUT}" | sed 's/\x1b\[[0-9;]*m//g' >> "${REPORT_FILE}"

if [ "${PHPCS_ERRORS}" -eq 0 ] && [ "${PHPCS_WARNINGS}" -eq 0 ]; then
    log "${GREEN}  PHPCS: No errors or warnings${RESET}"
elif [ "${PHPCS_ERRORS}" -eq 0 ]; then
    warn "PHPCS: 0 errors, ${PHPCS_WARNINGS} warning(s)"
    (( WARNINGS += PHPCS_WARNINGS )) || true
else
    fail "PHPCS: ${PHPCS_ERRORS} error(s), ${PHPCS_WARNINGS} warning(s)"
    (( ERRORS += PHPCS_ERRORS )) || true
fi

log ""

# ─── Check 3: PHPStan ────────────────────────────────────────────────────────
separator
log "${BOLD}[3/3] PHPStan (Level 8)${RESET}"
separator

PHPSTAN_OUTPUT=$(
    "${PHPSTAN}" analyse \
        --configuration="${PHPSTAN_CONFIG}" \
        --no-progress \
        --error-format=table \
        2>&1
) || true

PHPSTAN_STATUS=$?

# Strip noisy upgrade warnings from output
PHPSTAN_CLEAN=$(echo "${PHPSTAN_OUTPUT}" | grep -v "old version\|phpstan.org\|blog\|Upgrade\|last release\|days ago\|new features\|improvements\|versions were released\|PHPStan 2\|emoji\|️\|⚠" || true)

echo "${PHPSTAN_CLEAN}" | sed 's/\x1b\[[0-9;]*m//g' >> "${REPORT_FILE}"

if echo "${PHPSTAN_CLEAN}" | grep -q "\[OK\] No errors"; then
    pass "PHPStan: No errors"
    log "${GREEN}  PHPStan Level 8: PASSED${RESET}"
elif echo "${PHPSTAN_CLEAN}" | grep -q "\[ERROR\]"; then
    PHPSTAN_ERROR_COUNT=$(echo "${PHPSTAN_CLEAN}" | grep "Found" | grep -oE '[0-9]+' | head -1 || echo "?")
    fail "PHPStan: ${PHPSTAN_ERROR_COUNT} error(s) found"
    (( ERRORS++ )) || true
else
    log "${CYAN}  PHPStan: completed (check log for details)${RESET}"
fi

log ""

# ─── Summary ─────────────────────────────────────────────────────────────────
separator
log "${BOLD}SUMMARY${RESET}"
separator
log "  Total Errors   : ${ERRORS}"
log "  Total Warnings : ${WARNINGS}"
log "  Report saved   : ${REPORT_FILE}"
log ""

if [ "${ERRORS}" -eq 0 ]; then
    log "${GREEN}${BOLD}  ✓ ALL CHECKS PASSED${RESET}"
    FINAL_STATUS="PASSED"
    EXIT_CODE=0
else
    log "${RED}${BOLD}  ✗ CHECKS FAILED — ${ERRORS} error(s) found${RESET}"
    FINAL_STATUS="FAILED"
    EXIT_CODE=1
fi

log ""

# ─── Write final result to log (plain text) ──────────────────────────────────
{
    echo ""
    echo "============================================================"
    echo " RESULT : ${FINAL_STATUS}"
    echo " ERRORS : ${ERRORS}"
    echo " WARNS  : ${WARNINGS}"
    echo " END    : $(date '+%Y-%m-%d %H:%M:%S %Z')"
    echo "============================================================"
} >> "${REPORT_FILE}"

# ─── Link latest report ──────────────────────────────────────────────────────
LATEST_LINK="${REPORTS_DIR}/latest.log"
ln -sf "$(basename "${REPORT_FILE}")" "${LATEST_LINK}" 2>/dev/null || \
    cp "${REPORT_FILE}" "${LATEST_LINK}"

log "${CYAN}  Latest report → ${LATEST_LINK}${RESET}"
log ""

exit ${EXIT_CODE}
