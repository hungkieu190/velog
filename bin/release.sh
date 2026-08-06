#!/usr/bin/env bash
# =============================================================================
# VeLog — Release Packaging Script
# Author: Mamflow <https://mamflow.com>
#
# Usage:
#   npm run release
#   bash bin/release.sh
#
# Steps:
#   1. Run npm run check (PHPCS + PHPStan + Syntax)
#   2. Run npm run build (Compile assets minified)
#   3. Extract version from velog.php
#   4. Package plugin into /release/velog-v{version}.zip
# =============================================================================

set -euo pipefail

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
RELEASE_DIR="${PLUGIN_DIR}/release"
PLUGIN_SLUG="velog"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
BOLD='\033[1m'
RESET='\033[0m'

echo -e "${CYAN}${BOLD}============================================================${RESET}"
echo -e "${CYAN}${BOLD} VeLog Plugin Release Packaging${RESET}"
echo -e "${CYAN}${BOLD} Author: Mamflow (https://mamflow.com)${RESET}"
echo -e "${CYAN}${BOLD}============================================================${RESET}\n"

# Step 1: Pre-release Quality Check
echo -e "${BOLD}[1/4] Running Quality Checks...${RESET}"
if npm run check; then
    echo -e "${GREEN}  ✓ Quality checks passed.${RESET}\n"
else
    echo -e "${RED}  ✗ Quality checks failed! Aborting release.${RESET}"
    exit 1
fi

# Step 2: Build Assets
echo -e "${BOLD}[2/4] Building Production Assets...${RESET}"
if npm run build; then
    echo -e "${GREEN}  ✓ Production assets built successfully.${RESET}\n"
else
    echo -e "${RED}  ✗ Asset build failed! Aborting release.${RESET}"
    exit 1
fi

# Step 3: Extract Version
echo -e "${BOLD}[3/4] Reading Plugin Version...${RESET}"
VERSION=$(grep -i "Version:" "${PLUGIN_DIR}/velog.php" | head -1 | awk '{print $NF}' | tr -d '\r\n')
if [ -z "${VERSION}" ]; then
    VERSION="0.1.0"
fi
echo -e "${GREEN}  ✓ Plugin Version: ${VERSION}${RESET}\n"

# Step 4: Create Zip Package
echo -e "${BOLD}[4/4] Creating Release Zip Package...${RESET}"
mkdir -p "${RELEASE_DIR}"

ZIP_NAME="${PLUGIN_SLUG}-v${VERSION}.zip"
ZIP_PATH="${RELEASE_DIR}/${ZIP_NAME}"

# Move to parent folder to preserve root plugin directory inside zip
PARENT_DIR="$(dirname "${PLUGIN_DIR}")"
BUILD_SLUG="$(basename "${PLUGIN_DIR}")"

cd "${PARENT_DIR}"

rm -f "${ZIP_PATH}"

zip -r -q "${ZIP_PATH}" "${BUILD_SLUG}" \
    -x "${BUILD_SLUG}/.git/*" \
    -x "${BUILD_SLUG}/.github/*" \
    -x "${BUILD_SLUG}/node_modules/*" \
    -x "${BUILD_SLUG}/vendor/*" \
    -x "${BUILD_SLUG}/src/assets/*" \
    -x "${BUILD_SLUG}/tests/*" \
    -x "${BUILD_SLUG}/docs/*" \
    -x "${BUILD_SLUG}/plans/*" \
    -x "${BUILD_SLUG}/rules/*" \
    -x "${BUILD_SLUG}/reports/*" \
    -x "${BUILD_SLUG}/release/*" \
    -x "${BUILD_SLUG}/bin/*" \
    -x "${BUILD_SLUG}/*.neon" \
    -x "${BUILD_SLUG}/phpcs.xml" \
    -x "${BUILD_SLUG}/composer.json" \
    -x "${BUILD_SLUG}/composer.lock" \
    -x "${BUILD_SLUG}/package.json" \
    -x "${BUILD_SLUG}/package-lock.json" \
    -x "${BUILD_SLUG}/.editorconfig" \
    -x "${BUILD_SLUG}/.gitignore" \
    -x "${BUILD_SLUG}/AGENT.md" \
    -x "${BUILD_SLUG}/CONTRIBUTING.md" \
    -x "${BUILD_SLUG}/.phpunit.result.cache" \
    -x "*.map"

FILE_SIZE=$(du -h "${ZIP_PATH}" | cut -f1)

echo -e "${GREEN}${BOLD}============================================================${RESET}"
echo -e "${GREEN}${BOLD} RELEASE PACKAGE CREATED SUCCESSFULLY!${RESET}"
echo -e "${GREEN}${BOLD} Zip File : ${ZIP_PATH}${RESET}"
echo -e "${GREEN}${BOLD} Size     : ${FILE_SIZE}${RESET}"
echo -e "${GREEN}${BOLD}============================================================${RESET}\n"
