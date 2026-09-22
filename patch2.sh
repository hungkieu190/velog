cat << 'WRAPPER' > r9_3.sh
echo "=== R9-3: Force assertion failure ==="
cat << 'INNEREOF' > "$SUPERVISOR_DIR/bin/rm"
#!/bin/bash
if [ "$INJECT_MODE" = "assertion" ] && [ "$INJECT_NOW" = "1" ] && [[ "$*" == *"/tmp/velog-core001-smoke."* ]] && [[ "$*" != *"-sentinel."* ]] && [[ "$*" != *"-regression."* ]] && [[ "$*" != *"-unrelated."* ]]; then
    for arg in "$@"; do
        if [[ "$arg" == /tmp/velog-core001-smoke.* ]]; then
            echo "$arg" > "$SUPERVISOR_DIR/fixture_assertion"
            echo "INJECTED_RM_ASSERTION_FAILURE"
            exit 0
        fi
    done
fi
exec "$REAL_RM" "$@"
INNEREOF

export INJECT_MODE="assertion"
sed 's/command rm "$@"/INJECT_NOW=1 command rm "$@"/g' "$DIR/core001-smoke-controls.sh" > "$DIR/core001-smoke-controls-temp.sh"
bash -c 'export CONTROL_PID=$$; source "$1"' controls "$DIR/core001-smoke-controls-temp.sh" > "$SUPERVISOR_DIR/out_assertion.log" 2>&1
RES_ASSERTION=$?
command rm -f "$DIR/core001-smoke-controls-temp.sh"
if [ $RES_ASSERTION -eq 0 ]; then
    echo "Fail: Assertion failure resulted in exit 0"
    exit 1
fi
if ! grep -q "Fail: V3DIR was not removed" "$SUPERVISOR_DIR/out_assertion.log"; then
    echo "Fail: Expected assertion rejection not found"
    cat "$SUPERVISOR_DIR/out_assertion.log"
    exit 1
fi
echo "R9-3 assertion returned $RES_ASSERTION as expected"
WRAPPER

# Replace R9-3 section in tests/workflow/core001-smoke-controls-regression.sh
sed -i '/=== R9-3: Force assertion failure ===/,$d' tests/workflow/core001-smoke-controls-regression.sh
cat r9_3.sh >> tests/workflow/core001-smoke-controls-regression.sh
echo 'export INJECT_MODE=""' >> tests/workflow/core001-smoke-controls-regression.sh
echo 'echo "All regression controls passed."' >> tests/workflow/core001-smoke-controls-regression.sh
echo 'exit 0' >> tests/workflow/core001-smoke-controls-regression.sh
sed -i 's/# command rm -rf "$SUPERVISOR_DIR"/command rm -rf "$SUPERVISOR_DIR"/g' tests/workflow/core001-smoke-controls-regression.sh

bash tests/workflow/core001-smoke-controls-regression.sh > regression4.log 2>&1
echo $?
cat regression4.log
