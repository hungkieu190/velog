#!/bin/bash
# tests/workflow/product-smoke-controls.sh
set -e

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

function run_control() {
    local mode=$1
    local expected_code=$2
    local wp_version=$3
    
    echo "Running control: $mode (expected exit: $expected_code)"
    set +e
    "$DIR/product-smoke.sh" --task=CORE-003 --wp-version="$wp_version" --negative-mode="$mode"
    local actual_code=$?
    set -e
    
    if [ $actual_code -ne $expected_code ]; then
        echo "Fail: $mode exited with $actual_code, expected $expected_code"
        exit 1
    fi
    
    # Check for leaked directories
    LEAKS=$(ls -d /tmp/velog-product-smoke.* 2>/dev/null | wc -l)
    if [ $LEAKS -gt 0 ]; then
        echo "Fail: $mode leaked directories"
        exit 1
    fi
}

# Run negative controls
run_control "db-start-failure" 1 "6.4.3"
run_control "db-never-ready" 1 "6.7.2"
run_control "http-never-ready" 1 "6.4.3"
run_control "fixture-failure" 1 "6.7.2"

echo "Negative controls passed. Running parallel normal mode..."

set +e
"$DIR/product-smoke.sh" --task=CORE-003 --wp-version="6.4.3" > /tmp/smoke-parallel-1.log 2>&1 &
PID1=$!
"$DIR/product-smoke.sh" --task=CORE-003 --wp-version="6.7.2" > /tmp/smoke-parallel-2.log 2>&1 &
PID2=$!

wait $PID1
CODE1=$?
wait $PID2
CODE2=$?
set -e

if [ $CODE1 -ne 0 ]; then
    echo "Fail: Parallel run 1 failed (exit $CODE1)"
    cat /tmp/smoke-parallel-1.log
    exit 1
fi

if [ $CODE2 -ne 0 ]; then
    echo "Fail: Parallel run 2 failed (exit $CODE2)"
    cat /tmp/smoke-parallel-2.log
    exit 1
fi

LEAKS=$(ls -d /tmp/velog-product-smoke.* 2>/dev/null | wc -l)
if [ $LEAKS -gt 0 ]; then
    echo "Fail: Parallel runs leaked directories"
    exit 1
fi

echo "Outer controls passed."
exit 0
