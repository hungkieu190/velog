sed -i 's/command rm -rf "$SUPERVISOR_DIR"/# /g' tests/workflow/core001-smoke-controls-regression.sh
bash tests/workflow/core001-smoke-controls-regression.sh > regression3.log 2>&1
cat /tmp/velog-core001-smoke-regression.*/out_assertion.log
