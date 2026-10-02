#!/usr/bin/env bash
# Exercise cleanup failures without signaling a process or deleting a directory.
set -euo pipefail

plugin_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../../.." && pwd)"
control_dir="$(mktemp -d /tmp/velog-data001-controls.XXXXXXXX)"
trap 'rm -f -- "$control_dir"/*.log; rmdir -- "$control_dir" 2>/dev/null || true' EXIT
cd "$plugin_root"

run_control() {
    local name="$1"
    local marker="$2"
    local log="$control_dir/$name.log"
    shift 2
    local status=0
    php tests/fixtures/wp-integration/data001/run.php --wp-version=6.4.3 "$@" >"$log" 2>&1 || status=$?
    if (( status == 0 )) || ! grep -Fq "$marker" "$log"; then
        cat "$log"
        printf 'FAIL: %s exit or diagnostic\n' "$name" >&2
        return 1
    fi
    if grep -q 'Child exit unproved' "$log"; then
        cat "$log"
        printf 'FAIL: %s left an unproved child\n' "$name" >&2
        return 1
    fi
    local path
    path="$(sed -n 's/.*Temp base: *//p' "$log" | head -n 1)"
    if [[ ! "$path" =~ ^/tmp/velog_data001_6\.4\.3_[0-9]+_[a-f0-9]{16}$ ]] || [[ -e "$path" ]]; then
        cat "$log"
        printf 'FAIL: %s owned directory remains or path is invalid\n' "$name" >&2
        return 1
    fi
    printf 'PASS: %s\n' "$name"
}

run_control pid-mismatch 'ERROR: PID file content does not match child PID' --inject-pid-mismatch
run_control shutdown-fail 'ERROR: Socket shutdown command failed' --inject-shutdown-fail
run_control rm-fail 'ERROR: Injected directory removal failure.' --inject-rm-fail

sentinel="$control_dir/sentinel"
printf 'owned sentinel\n' >"$sentinel"
status=0
php tests/fixtures/wp-integration/data001/run.php --wp-version=6.4.3 --inject-sentinel >"$control_dir/sentinel.log" 2>&1 || status=$?
if (( status == 0 )) || ! grep -Fq 'ERROR: Unrelated sentinel marker.' "$control_dir/sentinel.log" || [[ "$(cat "$sentinel")" != 'owned sentinel' ]]; then
    printf 'FAIL: unrelated sentinel\n' >&2
    exit 1
fi
rm -- "$sentinel"
printf 'PASS: unrelated sentinel\n'
