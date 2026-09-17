#!/usr/bin/env bash
# Compatibility entry point for the validated Node release workflow.
set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."
exec node scripts/release.mjs
