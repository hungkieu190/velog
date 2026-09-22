#!/bin/bash
# tests/workflow/core001-smoke-controls-regression.sh
set -e
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

SUPERVISOR_DIR=$(command mktemp -d /tmp/velog-core001-smoke-regression.XXXXXXXX)
if [ ! -d "$SUPERVISOR_DIR" ]; then
    echo "Fail: Failed to allocate supervisor directory"
    exit 1
fi
export SUPERVISOR_DIR

cleanup_supervisor() {
    local origin=$?
    trap - EXIT INT TERM ERR
    local failed=false
    
    export INJECT_MODE=""
    
    if [ ! -f "$UNRELATED_SENTINEL/marker" ] || [ "$(cat "$UNRELATED_SENTINEL/marker")" != "unrelated marker" ]; then
        echo "Fail: Unrelated sentinel modified or deleted"
        failed=true
    fi
    
    for f in "$SUPERVISOR_DIR"/fixture_*; do
        if [ -f "$f" ]; then
            local p=$(cat "$f")
            if [ -d "$p" ]; then
                echo "Supervisor cleaning leftover: $p"
                local rm_stat=0
                "$REAL_RM" -rf "$p" >/dev/null 2>&1 || rm_stat=$?
                if [ $rm_stat -ne 0 ] || [ -d "$p" ]; then
                    echo "Fail: Supervisor failed to remove leftover $p (status: $rm_stat)"
                    failed=true
                fi
            fi
        fi
    done
    
    local sent_stat=0
    "$REAL_RM" -rf "$UNRELATED_SENTINEL" >/dev/null 2>&1 || sent_stat=$?
    if [ $sent_stat -ne 0 ] || [ -d "$UNRELATED_SENTINEL" ]; then
        failed=true
    fi
    
    local sup_stat=0
    "$REAL_RM" -rf "$SUPERVISOR_DIR" >/dev/null 2>&1 || sup_stat=$?
    if [ $sup_stat -ne 0 ] || [ -d "$SUPERVISOR_DIR" ]; then
        failed=true
    fi
    
    local final=$origin
    if [ $origin -eq 0 ]; then
        if [ "$failed" = "true" ]; then final=1; fi
    fi
    if [ $final -eq 0 ]; then
        echo "All regression controls passed."
    fi
    exit $final
}
trap cleanup_supervisor EXIT

UNRELATED_SENTINEL=$(command mktemp -d /tmp/velog-core001-smoke-unrelated.XXXXXXXX)
echo "unrelated marker" > "$UNRELATED_SENTINEL/marker"

ISOLATED_DIR="$SUPERVISOR_DIR/isolated copy"
mkdir -p "$ISOLATED_DIR"
cp "$DIR/core001-smoke-controls.sh" "$ISOLATED_DIR/"
cp "$DIR/core001-smoke-functions.sh" "$ISOLATED_DIR/"

mkdir -p "$SUPERVISOR_DIR/bin"
REAL_RM=$(command -v rm)
export REAL_RM

cat << 'EOF' > "$SUPERVISOR_DIR/bin/rm"
#!/bin/bash
if [ -z "$SUPERVISOR_DIR" ] || [ ! -d "$SUPERVISOR_DIR" ]; then
    echo "Fail: Supervisor context missing in rm wrapper" >&2
    exit 99
fi

if [ "$INJECT_MODE" = "persistent" ] && [[ "$*" == *"/tmp/velog-core001-smoke."* ]] && [[ "$*" != *"-sentinel."* ]] && [[ "$*" != *"-regression."* ]] && [[ "$*" != *"-unrelated."* ]] && [ "$PPID" = "$CONTROL_PID" ]; then
    for arg in "$@"; do
        if [[ "$arg" == /tmp/velog-core001-smoke.* ]]; then
            echo "$arg" > "$SUPERVISOR_DIR/fixture_persistent" || exit 99
            echo "INJECTED_RM_FAILURE_PERSISTENT"
            exit 9
        fi
    done
fi

if [ "$INJECT_MODE" = "transient" ] && [[ "$*" == *"/tmp/velog-core001-smoke."* ]] && [[ "$*" != *"-sentinel."* ]] && [[ "$*" != *"-regression."* ]] && [[ "$*" != *"-unrelated."* ]] && [ "$PPID" = "$CONTROL_PID" ]; then
    for arg in "$@"; do
        if [[ "$arg" == /tmp/velog-core001-smoke.* ]]; then
            if [ ! -f "$SUPERVISOR_DIR/transient_failed" ]; then
                echo "$arg" > "$SUPERVISOR_DIR/fixture_transient" || exit 99
                echo "INJECTED_RM_FAILURE_TRANSIENT"
                touch "$SUPERVISOR_DIR/transient_failed" || exit 99
                exit 9
            fi
        fi
    done
fi

if [ "$INJECT_MODE" = "assertion" ] && [ "$INJECT_NOW" = "1" ] && [[ "$*" == *"/tmp/velog-core001-smoke."* ]] && [[ "$*" != *"-sentinel."* ]] && [[ "$*" != *"-regression."* ]] && [[ "$*" != *"-unrelated."* ]]; then
    for arg in "$@"; do
        if [[ "$arg" == /tmp/velog-core001-smoke.* ]]; then
            echo "$arg" > "$SUPERVISOR_DIR/fixture_assertion" || exit 99
            echo "INJECTED_RM_ASSERTION_FAILURE"
            exit 0
        fi
    done
fi

if [ "$INJECT_MODE" = "supervisor_fail" ] && [[ "$*" == *"$SUPERVISOR_DIR"* ]]; then
    # Inject a failure during supervisor cleanup
    exit 9
fi

exec "$REAL_RM" "$@"
EOF
chmod +x "$SUPERVISOR_DIR/bin/rm"
export PATH="$SUPERVISOR_DIR/bin:$PATH"

echo "=== R10: Disabled injection negative control ==="
set +e
export INJECT_MODE="none"
bash -c 'export CONTROL_PID=$$; source "$1"' controls "$ISOLATED_DIR/core001-smoke-controls.sh" > "$SUPERVISOR_DIR/out_none.log" 2>&1
RES_NONE=$?
if [ $RES_NONE -ne 0 ]; then
    echo "Fail: Disabled injection should result in exit 0 (got $RES_NONE)"
    exit 1
fi
echo "Disabled injection passed"

echo "=== R10: Persistent outer rm failure ==="
export INJECT_MODE="persistent"
bash -c 'export CONTROL_PID=$$; source "$1"' controls "$ISOLATED_DIR/core001-smoke-controls.sh" > "$SUPERVISOR_DIR/out_persistent.log" 2>&1
RES_PERSISTENT=$?
if [ $RES_PERSISTENT -ne 1 ]; then
    echo "Fail: Persistent rm failure resulted in $RES_PERSISTENT instead of 1"
    exit 1
fi
if ! grep -q "INJECTED_RM_FAILURE_PERSISTENT" "$SUPERVISOR_DIR/out_persistent.log"; then
    echo "Fail: Persistent injection did not run"
    exit 1
fi
if [ ! -f "$SUPERVISOR_DIR/fixture_persistent" ]; then
    echo "Fail: Persistent state not written"
    exit 1
fi
echo "Persistent returned 1 as expected"

echo "=== R10: Transient outer rm failure ==="
export INJECT_MODE="transient"
bash -c 'export CONTROL_PID=$$; source "$1"' controls "$ISOLATED_DIR/core001-smoke-controls.sh" > "$SUPERVISOR_DIR/out_transient.log" 2>&1
RES_TRANSIENT=$?
if [ $RES_TRANSIENT -ne 1 ]; then
    echo "Fail: Transient rm failure resulted in $RES_TRANSIENT instead of 1 (should preserve origin failure)"
    exit 1
fi
if ! grep -q "INJECTED_RM_FAILURE_TRANSIENT" "$SUPERVISOR_DIR/out_transient.log"; then
    echo "Fail: Transient injection did not run"
    exit 1
fi
if [ ! -f "$SUPERVISOR_DIR/fixture_transient" ]; then
    echo "Fail: Transient state not written"
    exit 1
fi
transient_path=$(cat "$SUPERVISOR_DIR/fixture_transient")
if [ -d "$transient_path" ]; then
    echo "Fail: Transient path was not removed after retry"
    exit 1
fi
echo "Transient returned 1 as expected"

echo "=== R10: Force assertion failure ==="
sed -i 's/command rm "$@"/INJECT_NOW=1 command rm "$@"/g' "$ISOLATED_DIR/core001-smoke-controls.sh"
export INJECT_MODE="assertion"
bash -c 'export CONTROL_PID=$$; source "$1"' controls "$ISOLATED_DIR/core001-smoke-controls.sh" > "$SUPERVISOR_DIR/out_assertion.log" 2>&1
RES_ASSERTION=$?
if [ $RES_ASSERTION -ne 1 ]; then
    echo "Fail: Assertion failure resulted in $RES_ASSERTION instead of 1"
    exit 1
fi
if ! grep -q "Fail: V3DIR was not removed" "$SUPERVISOR_DIR/out_assertion.log"; then
    echo "Fail: Expected assertion rejection not found"
    exit 1
fi
if [ ! -f "$SUPERVISOR_DIR/fixture_assertion" ]; then
    echo "Fail: Assertion state not written"
    exit 1
fi
echo "Assertion returned 1 as expected"
export INJECT_MODE=""
