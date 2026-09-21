#!/bin/bash
# tests/workflow/core001-smoke-functions.sh

# Detector returns 0 for a VeLog early-warning match, 1 for absence, and >1 for processing failure.
velog_early_warning_detector() {
    if [ -z "${1+x}" ]; then
        return 2 # Processing failure if variable unset
    fi
    local output="$1"
    
    local stripped
    stripped=$(printf "%s\n" "$output" | sed -e 's/<[^>]*>//g' 2>/dev/null) || return 2

    local grep_exit=0
    printf "%s\n" "$stripped" | grep -qiE "_load_textdomain_just_in_time.*the velog domain" || grep_exit=$?

    if [ "$grep_exit" -eq 0 ]; then
        return 0 # Match found
    elif [ "$grep_exit" -eq 1 ]; then
        return 1 # Absence
    else
        return 2 # Processing failure
    fi
}

normal_gate() {
    local output="$1"
    local wp_exit="$2"
    
    if [ "$wp_exit" -ne 0 ]; then
        return 1
    fi
    
    if ! printf "%s\n" "$output" | grep -q "Normal load translation: Xin Chào"; then
        return 1
    fi
    
    local detector_result=0
    velog_early_warning_detector "$output" || detector_result=$?
    
    if [ "$detector_result" -eq 1 ]; then
        return 0 # Success
    else
        return 1 # Warning found or error
    fi
}

reserve_work_dir() {
    # Initialize empty ownership
    WORK_DIR=""
    DB_PID=""
    
    if ! command -v timeout >/dev/null 2>&1; then
        echo "Blocker: 'timeout' utility is missing."
        return 1
    fi
    
    local old_umask
    old_umask=$(umask)
    umask 0077
    
    local temp_dir
    if ! temp_dir=$(mktemp -d /tmp/velog-core001-smoke.XXXXXXXX); then
        umask "$old_umask"
        echo "Failed to allocate temp directory."
        return 1
    fi
    umask "$old_umask"
    
    WORK_DIR="$temp_dir"
    trap cleanup EXIT
    trap 'cleanup 130' INT
    trap 'cleanup 143' TERM
    return 0
}

cleanup() {
    local origin_status=${1:-$?}
    trap - EXIT INT TERM ERR
    
    local cleanup_failed=false
    
    if [ -n "$DB_PID" ]; then
        kill -TERM "$DB_PID" 2>/dev/null || true
        local i=0
        while [ $i -lt 10 ]; do
            if ! kill -0 "$DB_PID" 2>/dev/null; then
                break
            fi
            sleep 1
            i=$((i+1))
        done
        if kill -0 "$DB_PID" 2>/dev/null; then
            kill -KILL "$DB_PID" 2>/dev/null || true
        fi
        wait "$DB_PID" 2>/dev/null || true
        
        if kill -0 "$DB_PID" 2>/dev/null; then
            echo "Failed to terminate child $DB_PID"
            cleanup_failed=true
        fi
        DB_PID=""
    fi
    
    if [ -n "$WORK_DIR" ] && [ -d "$WORK_DIR" ]; then
        if [ "$cleanup_failed" = false ]; then
            if [[ "$WORK_DIR" == /tmp/velog-core001-smoke.* ]]; then
                local rm_status=0
                rm -rf "$WORK_DIR" 2>/dev/null || rm_status=$?
                if [ "$rm_status" -ne 0 ]; then
                    echo "rm failed for $WORK_DIR with status $rm_status"
                    cleanup_failed=true
                fi
                if [ -d "$WORK_DIR" ]; then
                    echo "Failed to remove directory $WORK_DIR"
                    cleanup_failed=true
                fi
            else
                echo "Directory $WORK_DIR does not match expected pattern"
                cleanup_failed=true
            fi
        else
            echo "Retaining directory $WORK_DIR due to previous cleanup failures"
        fi
    fi
    
    local final_status=0
    if [ "$origin_status" -ne 0 ]; then
        final_status="$origin_status"
    elif [ "$cleanup_failed" = true ]; then
        final_status=1
    fi
    echo "Cleanup completed. Final status: $final_status"
    exit "$final_status"
}

wait_ready() {
    local child_pid="$1"
    local probe_socket="$2"
    local timeout_sec="${3:-30}"
    
    local deadline
    deadline=$(($(date +%s) + timeout_sec))
    
    while [ $(date +%s) -lt $deadline ]; do
        if ! kill -0 "$child_pid" 2>/dev/null; then
            echo "Child $child_pid dead after $(($(date +%s) - (deadline - timeout_sec)))s"
            return 1
        fi
        
        if timeout 1 mysqladmin ping -S "$probe_socket" --silent 2>/dev/null; then
            if kill -0 "$child_pid" 2>/dev/null; then
                return 0
            fi
        fi
        
        sleep 1
    done
    
    echo "Deadline expired ($timeout_sec seconds)"
    return 1
}
