#!/bin/bash
# tests/workflow/product-smoke-controls.sh
# Outer harness: runs four negative controls (each asserting its VELOG_CAUSE marker),
# then two parallel normal runs. All logs are retained in an owned directory.
set -euo pipefail

DIR_SELF="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Owned harness directory — never touches unrelated /tmp files.
HARNESS_DIR=$(mktemp -d /tmp/velog-controls.XXXXXXXX)
chmod 0700 "$HARNESS_DIR"

PARALLEL_PIDS=()
PARALLEL_LOG1=""
PARALLEL_LOG2=""

cleanup_harness() {
    local ec=$?
    set +e

    # Reap parallel children if still alive.
    for pid in "${PARALLEL_PIDS[@]:-}"; do
        if [ -n "$pid" ] && kill -0 "$pid" 2>/dev/null; then
            kill -TERM "$pid" 2>/dev/null
            for i in {1..5}; do
                kill -0 "$pid" 2>/dev/null || break
                sleep 1
            done
            if kill -0 "$pid" 2>/dev/null; then
                kill -KILL "$pid" 2>/dev/null
                sleep 1
            fi
            # Verify absence.
            if kill -0 "$pid" 2>/dev/null; then
                echo "CLEANUP_FAIL: parallel child $pid could not be reaped"
                ec=1
            fi
        fi
    done

    # Logs are retained — do NOT remove HARNESS_DIR contents.
    # Only remove the harness dir skeleton (logs already flushed above).
    echo "Harness logs retained in $HARNESS_DIR"
    exit $ec
}

trap 'cleanup_harness' EXIT INT TERM

# ----------------------------------------------------------------
# run_control: run one negative mode and assert its VELOG_CAUSE marker.
# Arguments: mode expected_exit wp_version expected_cause_substr
# ----------------------------------------------------------------
run_control() {
    local mode=$1
    local expected_code=$2
    local wp_version=$3
    local expected_cause=$4

    local log_file="$HARNESS_DIR/control-${mode}-${wp_version}.log"
    local cause_copy="/tmp/velog-cause-$(ls /tmp/velog-product-smoke.* 2>/dev/null | head -1 | xargs basename 2>/dev/null || echo unknown).log"

    echo "--- Control: $mode (WP $wp_version, expected exit: $expected_code) ---"
    set +e
    "$DIR_SELF/product-smoke.sh" \
        --task=CORE-003 \
        --wp-version="$wp_version" \
        --negative-mode="$mode" \
        2>&1 | tee "$log_file"
    local actual_code=${PIPESTATUS[0]}
    set -e

    # Assert expected exit code.
    if [ "$actual_code" -ne "$expected_code" ]; then
        echo "FAIL: $mode exited with $actual_code, expected $expected_code"
        exit 1
    fi

    # Assert that the cause marker emitted by the relevant validator is present.
    if ! grep -q "$expected_cause" "$log_file"; then
        echo "FAIL: $mode — expected cause marker '$expected_cause' not found in output"
        cat "$log_file"
        exit 1
    fi
    echo "PASS: $mode — cause marker confirmed: $expected_cause"

    # Assert no leaked smoke directories from this run.
    local leaks
    leaks=$(find /tmp -maxdepth 1 -name "velog-product-smoke.*" -type d 2>/dev/null | wc -l)
    if [ "$leaks" -gt 0 ]; then
        echo "FAIL: $mode leaked smoke directories:"
        find /tmp -maxdepth 1 -name "velog-product-smoke.*" -type d 2>/dev/null
        exit 1
    fi
}

# ----------------------------------------------------------------
# Four negative controls — each must reach its validator.
# ----------------------------------------------------------------
# db-start-failure: mariadbd exits due to missing datadir; cause emitted before DB ready check.
run_control "db-start-failure" 1 "6.4.3" "VELOG_CAUSE: db-start-failure"

# db-never-ready: DB socket never available; cause emitted from DB ready loop.
run_control "db-never-ready" 1 "6.7.2" "VELOG_CAUSE: db-never-ready"

# http-never-ready: PHP starts in missing dir; cause emitted from HTTP ready loop.
run_control "http-never-ready" 1 "6.4.3" "VELOG_CAUSE: http-never-ready"

# fixture-failure: fixture emits VELOG_CAUSE and exits 1; outer confirms marker.
run_control "fixture-failure" 1 "6.7.2" "VELOG_CAUSE: fixture-failure"

echo "--- All negative controls passed ---"

# ----------------------------------------------------------------
# Parallel normal runs — owned log files, no shared /tmp names.
# ----------------------------------------------------------------
PARALLEL_LOG1="$HARNESS_DIR/parallel-6.4.3.log"
PARALLEL_LOG2="$HARNESS_DIR/parallel-6.7.2.log"

echo "--- Running parallel normal mode ---"
set +e
"$DIR_SELF/product-smoke.sh" --task=CORE-003 --wp-version="6.4.3" >"$PARALLEL_LOG1" 2>&1 &
PID1=$!
PARALLEL_PIDS+=($PID1)

"$DIR_SELF/product-smoke.sh" --task=CORE-003 --wp-version="6.7.2" >"$PARALLEL_LOG2" 2>&1 &
PID2=$!
PARALLEL_PIDS+=($PID2)

wait $PID1
CODE1=$?
wait $PID2
CODE2=$?

# Remove from tracking after wait (already reaped).
PARALLEL_PIDS=()
set -e

if [ $CODE1 -ne 0 ]; then
    echo "FAIL: Parallel 6.4.3 failed (exit $CODE1)"
    cat "$PARALLEL_LOG1"
    exit 1
fi

if [ $CODE2 -ne 0 ]; then
    echo "FAIL: Parallel 6.7.2 failed (exit $CODE2)"
    cat "$PARALLEL_LOG2"
    exit 1
fi

# Assert no leaked smoke directories from parallel runs.
leaks=$(find /tmp -maxdepth 1 -name "velog-product-smoke.*" -type d 2>/dev/null | wc -l)
if [ "$leaks" -gt 0 ]; then
    echo "FAIL: Parallel runs leaked smoke directories"
    find /tmp -maxdepth 1 -name "velog-product-smoke.*" -type d 2>/dev/null
    exit 1
fi

echo "--- Outer controls passed. Logs in $HARNESS_DIR ---"
exit 0
