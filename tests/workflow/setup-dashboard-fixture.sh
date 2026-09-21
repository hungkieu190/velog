#!/bin/bash
set -e

WORK_DIR=$(mktemp -d /tmp/velog-wf002-browser-XXXXXX)
cp -r ai-document scripts "$WORK_DIR/"
mkdir -p "$WORK_DIR/src/css"
cp src/css/progress.css "$WORK_DIR/src/css/"

cd "$WORK_DIR"
# Setup fixtures
cat << 'EOF' > ai-document/implementation-checklist.md
## Current focus

- Task: FIXTURE
- Status: STATUS_PLACEHOLDER

## Open work
- [ ] FIXTURE: Test
EOF

cat << 'EOF' > ai-document/tasks/FIXTURE.md
# FIXTURE: Test
## Current handoff
- Status: STATUS_PLACEHOLDER
- Next actor: Builder
EOF

# Start server
node scripts/progress-dashboard.mjs --port=4188 > server.log 2>&1 &
SERVER_PID=$!
echo $SERVER_PID > server.pid

sleep 2

# Test fetch (V4)
curl --fail http://127.0.0.1:4188/ > fetch_html.log
curl --fail http://127.0.0.1:4188/progress.css > fetch_css.log

echo "Server running on 4188 with PID $SERVER_PID"
echo "$WORK_DIR"
