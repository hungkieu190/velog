#!/usr/bin/env bash
# Preserve actual tool failures; never infer success from formatted output.
set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."
mkdir -p reports
report="reports/check-$(date '+%Y-%m-%d_%H-%M-%S').log"
{
  php -l velog.php
  php -l uninstall.php
  while IFS= read -r -d '' file; do
    php -l "$file"
  done < <(find src -name '*.php' -type f -print0)
  composer run phpcs
  composer run phpstan
} 2>&1 | tee "$report"
printf 'Quality checks passed. Report: %s\n' "$report"
