#!/bin/bash
# tests/workflow/core001-smoke-controls.sh

set -e
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$DIR/core001-smoke-functions.sh"

echo "=== V1: detector and normal_gate assertions ==="
ACTUAL_NOTICE_HTML="Notice: Function _load_textdomain_just_in_time was called <strong>incorrectly</strong>. Translation loading for the <code>velog</code> domain was triggered too early. This is usually an indicator for some code in the plugin or theme running too early. Translations should be loaded at the <code>init</code> action or later. Please see <a href=\"https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/\">Debugging in WordPress</a> for more information. (This message was added in version 6.7.0.) in /tmp/velog-core001-smoke-4627/wp/wp-includes/functions.php on line 6114"
ACTUAL_NOTICE_PLAIN="Notice: Function _load_textdomain_just_in_time was called incorrectly. Translation loading for the velog domain was triggered too early."
OTHER_NOTICE="Notice: Function _load_textdomain_just_in_time was called <strong>incorrectly</strong>. Translation loading for the <code>other_domain</code> domain was triggered too early. (in /path/to/velog/something)"
CLEAN_OUTPUT="Normal load translation: Xin Chào\nWP-CLI Booted"

if ! velog_early_warning_detector "$ACTUAL_NOTICE_HTML"; then echo "Fail: detector HTML" && exit 1; fi
if ! velog_early_warning_detector "$ACTUAL_NOTICE_PLAIN"; then echo "Fail: detector PLAIN" && exit 1; fi

set +e
velog_early_warning_detector "$OTHER_NOTICE"
RES=$?
set -e
if [ $RES -ne 1 ]; then echo "Fail: detector OTHER" && exit 1; fi

if ! normal_gate "$CLEAN_OUTPUT" 0; then echo "Fail: normal gate clean" && exit 1; fi
if normal_gate "$(printf "%s\n%s" "$CLEAN_OUTPUT" "$ACTUAL_NOTICE_HTML")" 0; then echo "Fail: contaminated HTML" && exit 1; fi
if normal_gate "$(printf "%s\n%s" "$CLEAN_OUTPUT" "$ACTUAL_NOTICE_PLAIN")" 0; then echo "Fail: contaminated PLAIN" && exit 1; fi

echo "=== V2: normal gate failure assertions ==="
if normal_gate "$CLEAN_OUTPUT" 7; then echo "Fail: nonzero wp_exit" && exit 1; fi

set +e
velog_early_warning_detector
RES=$?
set -e
if [ $RES -ne 2 ]; then echo "Fail: detector unset input" && exit 1; fi

set +e
(
    sed() { return 2; }
    export -f sed
    velog_early_warning_detector "$ACTUAL_NOTICE_HTML"
    exit $?
)
RES=$?
set -e
if [ $RES -ne 2 ]; then echo "Fail: sed processing error not >1" && exit 1; fi

set +e
(
    sed() { return 2; }
    export -f sed
    normal_gate "$CLEAN_OUTPUT" 0
    exit $?
)
RES=$?
set -e
if [ $RES -eq 0 ]; then echo "Fail: normal_gate accepted sed processing error" && exit 1; fi

set +e
(
    grep() {
        if [[ "$*" == *"_load_textdomain_just_in_time"* ]]; then return 2; fi
        command grep "$@"
    }
    export -f grep
    velog_early_warning_detector "$ACTUAL_NOTICE_HTML"
    exit $?
)
RES=$?
set -e
if [ $RES -ne 2 ]; then echo "Fail: grep processing error not >1" && exit 1; fi

set +e
(
    grep() {
        if [[ "$*" == *"_load_textdomain_just_in_time"* ]]; then return 2; fi
        command grep "$@"
    }
    export -f grep
    normal_gate "$CLEAN_OUTPUT" 0
    exit $?
)
RES=$?
set -e
if [ $RES -eq 0 ]; then echo "Fail: normal_gate accepted grep processing error" && exit 1; fi


echo "=== V3: Allocation and cleanup controls ==="
SENTINEL=$(mktemp -d /tmp/velog-core001-smoke-sentinel.XXXXXXXX)
cleanup_sentinel() {
    rm -rf "$SENTINEL"
}
trap cleanup_sentinel EXIT

(
    source "$DIR/core001-smoke-functions.sh"
    reserve_work_dir
    echo "$WORK_DIR" > "$SENTINEL/dir1"
)
DIR1=$(cat "$SENTINEL/dir1")
if [ -d "$DIR1" ]; then echo "Fail: DIR1 not cleaned" && exit 1; fi

(
    source "$DIR/core001-smoke-functions.sh"
    reserve_work_dir
    echo "$WORK_DIR" > "$SENTINEL/dir2"
)
DIR2=$(cat "$SENTINEL/dir2")
if [ -d "$DIR2" ]; then echo "Fail: DIR2 not cleaned" && exit 1; fi
if [ "$DIR1" == "$DIR2" ]; then echo "Fail: same dir" && exit 1; fi

set +e
(
    source "$DIR/core001-smoke-functions.sh"
    mktemp() { return 1; }
    export -f mktemp
    reserve_work_dir
    exit $?
)
RES=$?
set -e
if [ $RES -eq 0 ]; then echo "Fail: alloc failure" && exit 1; fi
if [ ! -d "$SENTINEL" ]; then echo "Fail: sentinel cleaned" && exit 1; fi

echo "=== V4: Readiness dead-child/timeout ==="
(
    source "$DIR/core001-smoke-functions.sh"
    reserve_work_dir
    bash -c 'exit 0' &
    DB_PID=$!
    echo "$WORK_DIR" > "$SENTINEL/v4dir1"
    echo "$DB_PID" > "$SENTINEL/v4pid1"
    
    START=$(date +%s)
    if wait_ready "$DB_PID" "$WORK_DIR/mysql.sock" 2; then exit 1; fi
    DUR=$(($(date +%s) - START))
    if [ $DUR -gt 5 ]; then exit 1; fi
    exit 0
)
V4DIR1=$(cat "$SENTINEL/v4dir1")
V4PID1=$(cat "$SENTINEL/v4pid1")
if [ -d "$V4DIR1" ]; then echo "Fail: V4DIR1 not cleaned" && exit 1; fi
if kill -0 "$V4PID1" 2>/dev/null; then echo "Fail: child alive" && exit 1; fi

(
    source "$DIR/core001-smoke-functions.sh"
    reserve_work_dir
    sleep 10 &
    DB_PID=$!
    echo "$WORK_DIR" > "$SENTINEL/v4dir2"
    echo "$DB_PID" > "$SENTINEL/v4pid2"
    
    START=$(date +%s)
    if wait_ready "$DB_PID" "$WORK_DIR/mysql.sock" 2; then exit 1; fi
    DUR=$(($(date +%s) - START))
    if [ $DUR -gt 5 ]; then exit 1; fi
    exit 0
)
V4DIR2=$(cat "$SENTINEL/v4dir2")
V4PID2=$(cat "$SENTINEL/v4pid2")
if [ -d "$V4DIR2" ]; then echo "Fail: V4DIR2 not cleaned" && exit 1; fi
if kill -0 "$V4PID2" 2>/dev/null; then echo "Fail: child alive" && exit 1; fi

echo "=== V5: Ready child positive control & injected failure ==="
set +e
(
    source "$DIR/core001-smoke-functions.sh"
    reserve_work_dir
    mkdir -p "$WORK_DIR/bin"
    cat << 'EOF' > "$WORK_DIR/bin/mysqladmin"
#!/bin/bash
exit 0
EOF
    chmod +x "$WORK_DIR/bin/mysqladmin"
    PATH="$WORK_DIR/bin:$PATH"
    sleep 10 &
    DB_PID=$!
    echo "$WORK_DIR" > "$SENTINEL/v5dir1"
    echo "$DB_PID" > "$SENTINEL/v5pid1"
    if ! wait_ready "$DB_PID" "$WORK_DIR/mysql.sock" 2; then exit 1; fi
    exit 23
)
RES=$?
set -e
if [ $RES -ne 23 ]; then echo "Fail: injected failure 23" && exit 1; fi
V5DIR1=$(cat "$SENTINEL/v5dir1")
V5PID1=$(cat "$SENTINEL/v5pid1")
if [ -d "$V5DIR1" ]; then echo "Fail: V5DIR1 not cleaned" && exit 1; fi
if kill -0 "$V5PID1" 2>/dev/null; then echo "Fail: child alive" && exit 1; fi

set +e
(
    source "$DIR/core001-smoke-functions.sh"
    reserve_work_dir
    sleep 10 &
    DB_PID=$!
    echo "$WORK_DIR" > "$SENTINEL/v5dir2"
    echo "$DB_PID" > "$SENTINEL/v5pid2"
    kill -TERM $BASHPID
    sleep 1
)
RES=$?
set -e
if [ $RES -ne 143 ]; then echo "Fail: TERM signal 143 not preserved (got $RES)" && exit 1; fi
V5DIR2=$(cat "$SENTINEL/v5dir2")
V5PID2=$(cat "$SENTINEL/v5pid2")
if [ -d "$V5DIR2" ]; then echo "Fail: V5DIR2 not cleaned" && exit 1; fi
if kill -0 "$V5PID2" 2>/dev/null; then echo "Fail: child alive" && exit 1; fi

echo "=== V3: Cleanup removal-error controls ==="
set +e
idx=1
for errexit_mode in "+e" "-e"; do
    for origin in 0 23; do
        for rm_type in "leave_dir" "remove_dir"; do
            echo "Testing cleanup errexit=$errexit_mode origin=$origin rm_type=$rm_type"
            (
                set $errexit_mode
                source "$DIR/core001-smoke-functions.sh"
                reserve_work_dir
                echo "$WORK_DIR" > "$SENTINEL/v3dir_${idx}"
                if [ "$rm_type" = "leave_dir" ]; then
                    rm() { return 9; }
                else
                    rm() { command rm "$@"; return 9; }
                fi
                export -f rm
                exit $origin
            )
            RES=$?
            V3DIR=$(cat "$SENTINEL/v3dir_${idx}")
            
            if [ "$origin" -eq 0 ]; then
                if [ $RES -eq 0 ]; then echo "Fail: origin 0 + rm fail -> should be nonzero" && rm -rf "$V3DIR" && exit 1; fi
            else
                if [ $RES -ne $origin ]; then echo "Fail: origin $origin + rm fail -> should be $origin (got $RES)" && rm -rf "$V3DIR" && exit 1; fi
            fi
            
            if [ "$rm_type" = "leave_dir" ]; then
                if [ ! -d "$V3DIR" ]; then echo "Fail: V3DIR was removed but shouldn't be" && exit 1; fi
            else
                if [ -d "$V3DIR" ]; then echo "Fail: V3DIR was not removed" && rm -rf "$V3DIR" && exit 1; fi
            fi
            
            rm -rf "$V3DIR"
            idx=$((idx + 1))
        done
    done
done
set -e

trap - EXIT
rm -rf "$SENTINEL"
echo "All controls passed."
exit 0
