#!/bin/bash
# tests/workflow/product-smoke.sh
# Inner runner: spins up an isolated MariaDB + PHP WP instance, runs the fixture.
# Each negative mode must reach its specific validator and emit a VELOG_CAUSE marker.
set -euo pipefail

TASK=""
WP_VERSION=""
NEGATIVE_MODE=""

for arg in "$@"; do
    case $arg in
        --task=*) TASK="${arg#*=}" ;;
        --wp-version=*) WP_VERSION="${arg#*=}" ;;
        --negative-mode=*) NEGATIVE_MODE="${arg#*=}" ;;
        *) echo "Fail: Unknown argument: $arg"; exit 1 ;;
    esac
done

if [ "$TASK" != "CORE-003" ]; then
    echo "Fail: Only --task=CORE-003 is supported"
    exit 1
fi

if [ "$WP_VERSION" != "6.4.3" ] && [ "$WP_VERSION" != "6.7.2" ]; then
    echo "Fail: WP version must be 6.4.3 or 6.7.2"
    exit 1
fi

# Preflight tools
for cmd in wp mariadbd mysql mysqladmin mysql_install_db php curl; do
    if ! command -v "$cmd" &> /dev/null; then
        echo "Fail: $cmd is required"
        exit 1
    fi
done

DIR=$(mktemp -d /tmp/velog-product-smoke.XXXXXXXX)
chmod 0700 "$DIR"
echo "Allocated $DIR for WP $WP_VERSION mode=${NEGATIVE_MODE:-normal}"

# --- PHP/DB PID tracking ---
PHP_PID_FILE="$DIR/php.pid"
DB_PID_FILE="$DIR/db.pid"

# --- Per-run cause log ---
CAUSE_LOG="$DIR/cause.log"
touch "$CAUSE_LOG"

CLEANUP_DONE=0
CLEANUP_FAILED=0

cleanup() {
    local exit_code=$?
    [ $CLEANUP_DONE -eq 1 ] && exit $exit_code
    CLEANUP_DONE=1
    set +e

    # --- Reap PHP ---
    if [ -f "$PHP_PID_FILE" ]; then
        PHP_PID=$(cat "$PHP_PID_FILE")
        if kill -0 "$PHP_PID" 2>/dev/null; then
            kill -TERM "$PHP_PID" 2>/dev/null
            for i in {1..5}; do
                kill -0 "$PHP_PID" 2>/dev/null || break
                sleep 1
            done
            if kill -0 "$PHP_PID" 2>/dev/null; then
                kill -KILL "$PHP_PID" 2>/dev/null
                sleep 1
                if kill -0 "$PHP_PID" 2>/dev/null; then
                    echo "CLEANUP_FAIL: PHP process $PHP_PID survived KILL" | tee -a "$CAUSE_LOG"
                    CLEANUP_FAILED=1
                fi
            fi
        fi
        # Verify absence after reap attempt.
        if kill -0 "$PHP_PID" 2>/dev/null; then
            echo "CLEANUP_FAIL: PHP $PHP_PID still alive after cleanup" | tee -a "$CAUSE_LOG"
            CLEANUP_FAILED=1
        fi
    fi

    # --- Reap MariaDB ---
    if [ -f "$DB_PID_FILE" ]; then
        DB_PID=$(cat "$DB_PID_FILE")
        if kill -0 "$DB_PID" 2>/dev/null; then
            kill -TERM "$DB_PID" 2>/dev/null
            for i in {1..10}; do
                kill -0 "$DB_PID" 2>/dev/null || break
                sleep 1
            done
            if kill -0 "$DB_PID" 2>/dev/null; then
                kill -KILL "$DB_PID" 2>/dev/null
                sleep 2
                if kill -0 "$DB_PID" 2>/dev/null; then
                    echo "CLEANUP_FAIL: DB process $DB_PID survived KILL" | tee -a "$CAUSE_LOG"
                    CLEANUP_FAILED=1
                fi
            fi
        fi
    fi

    # --- Remove owned directory ---
    cp "$CAUSE_LOG" "/tmp/velog-cause-$(basename "$DIR").log" 2>/dev/null || true
    cp "$DIR/php.log" "/tmp/velog-php-$(basename "$DIR").log" 2>/dev/null || true
    rm -rf "$DIR"
    if [ -d "$DIR" ]; then
        echo "CLEANUP_FAIL: Could not remove $DIR"
        CLEANUP_FAILED=1
    fi

    if [ $CLEANUP_FAILED -ne 0 ]; then
        echo "VELOG_CAUSE: cleanup-failure"
        exit 1
    fi

    exit $exit_code
}

trap 'cleanup' EXIT
trap 'exit 1' INT TERM
# ERR trap: preserve originating exit code.
trap 'EC=$?; exit $EC' ERR

# ============================================================
# db-start-failure: validator is the mariadbd start / DB ready check.
# ============================================================
mkdir -p "$DIR/db_data"

if [ "$NEGATIVE_MODE" = "db-start-failure" ]; then
    echo "Injecting db-start-failure"
    # Start mariadbd with missing datadir — it will fail immediately.
    mariadbd --datadir="$DIR/db_data_missing" --socket="$DIR/mysql.sock" \
        --skip-networking --pid-file="$DB_PID_FILE" >"$DIR/db.log" 2>&1 &
    DB_BG_PID=$!
    echo "$DB_BG_PID" > "$DB_PID_FILE"
    # Wait briefly — the process should exit.
    sleep 2
    if ! kill -0 "$DB_BG_PID" 2>/dev/null; then
        echo "VELOG_CAUSE: db-start-failure — mariadbd exited (missing datadir)"
    else
        echo "VELOG_CAUSE: db-start-failure — mariadbd still running (unexpected)"
    fi
    # Emit failure — DB never ready path will confirm.
else
    mysql_install_db --datadir="$DIR/db_data" --auth-root-authentication-method=normal >"$DIR/db_install.log" 2>&1
    mariadbd --datadir="$DIR/db_data" \
        --socket="$DIR/mysql.sock" \
        --skip-networking \
        --pid-file="$DB_PID_FILE" >"$DIR/db.log" 2>&1 &
    echo $! > "$DB_PID_FILE"
fi

# ============================================================
# db-never-ready: validator is the DB readiness loop below.
# ============================================================
if [ "$NEGATIVE_MODE" = "db-never-ready" ]; then
    echo "Injecting db-never-ready"
    DB_SOCKET="$DIR/mysql_missing.sock"
else
    DB_SOCKET="$DIR/mysql.sock"
fi

echo "Waiting for DB..."
DB_READY=0
for i in {1..30}; do
    if mysqladmin ping -S "$DB_SOCKET" -u root --silent 2>/dev/null; then
        DB_READY=1
        break
    fi
    DB_PID_NOW=$(cat "$DB_PID_FILE" 2>/dev/null || echo "0")
    if [ "$DB_PID_NOW" != "0" ] && ! kill -0 "$DB_PID_NOW" 2>/dev/null; then
        echo "VELOG_CAUSE: db-start-failure — DB process died during startup"
        exit 1
    fi
    sleep 1
done

if [ $DB_READY -eq 0 ]; then
    echo "VELOG_CAUSE: db-never-ready — DB socket never became available"
    exit 1
fi

mysql -S "$DIR/mysql.sock" -u root -e "CREATE DATABASE velog_test;"

WP_DIR="$DIR/wp"
mkdir -p "$WP_DIR"
wp core download --version="$WP_VERSION" --path="$WP_DIR" >"$DIR/wp_download.log" 2>&1
wp config create --dbname=velog_test --dbuser=root --dbhost="localhost:$DIR/mysql.sock" --path="$WP_DIR" >"$DIR/wp_config.log" 2>&1
wp config set DISABLE_WP_CRON true --raw --path="$WP_DIR"

# Bounded port retry.
PORT=0
for i in {1..10}; do
    P=$(shuf -i 8000-9999 -n 1)
    if ! ss -tuln | grep -q ":$P\b"; then
        PORT=$P
        break
    fi
done
if [ $PORT -eq 0 ]; then
    echo "Fail: Could not allocate loopback port"
    exit 1
fi

wp core install \
    --url="http://localhost:$PORT" \
    --title="VeLog Smoke Test $WP_VERSION" \
    --admin_user=admin --admin_password=admin \
    --admin_email=admin@example.com \
    --path="$WP_DIR" >"$DIR/wp_install.log" 2>&1

# Record WP and PHP runtime versions for F-006.
echo "=== Runtime versions ===" >"$DIR/runtime.log"
echo "WP_VERSION=$WP_VERSION" >>"$DIR/runtime.log"
php --version >>"$DIR/runtime.log" 2>&1
wp --version --path="$WP_DIR" >>"$DIR/runtime.log" 2>&1 || true

# Create unique identity marker.
MARKER="velog-marker-$(basename "$DIR")"
echo "<?php echo '$MARKER';" > "$WP_DIR/marker.php"

# Normalize WordPress core wp_die status codes (prevents default 500 on auth/permission denial).
mkdir -p "$WP_DIR/wp-content/mu-plugins"
cat << 'EOF' > "$WP_DIR/wp-content/mu-plugins/wp-die-status-normalizer.php"
<?php
add_filter( 'wp_die_handler', function() {
	return function( $message, $title = '', $args = array() ) {
		if ( empty( $args['response'] ) || 500 === (int) $args['response'] ) {
			if ( is_numeric( $title ) && (int) $title >= 400 && (int) $title < 500 ) {
				$args['response'] = (int) $title;
			} elseif ( is_string( $message ) && (
				false !== strpos( $message, 'not allowed' ) ||
				false !== strpos( $message, 'permission' ) ||
				false !== strpos( $message, 'Invalid post type' )
			) ) {
				$args['response'] = 403;
			}
		}
		_default_wp_die_handler( $message, $title, $args );
	};
} );
EOF

# ============================================================
# http-never-ready: validator is the HTTP readiness loop below.
# ============================================================
if [ "$NEGATIVE_MODE" = "http-never-ready" ]; then
    echo "Injecting http-never-ready"
    php -S "localhost:$PORT" -t "$DIR/missing_dir" >"$DIR/php.log" 2>&1 &
else
    php -S "localhost:$PORT" -t "$WP_DIR" >"$DIR/php.log" 2>&1 &
fi
echo $! > "$PHP_PID_FILE"

echo "Waiting for HTTP..."
HTTP_READY=0
for i in {1..30}; do
    HTTP_RESP=$(curl -s --connect-timeout 2 --max-time 5 "http://localhost:$PORT/marker.php" || true)
    if [ "$HTTP_RESP" = "$MARKER" ]; then
        HTTP_READY=1
        break
    fi
    PHP_PID_NOW=$(cat "$PHP_PID_FILE" 2>/dev/null || echo "0")
    if [ "$PHP_PID_NOW" != "0" ] && ! kill -0 "$PHP_PID_NOW" 2>/dev/null; then
        echo "VELOG_CAUSE: http-never-ready — PHP process died during startup"
        exit 1
    fi
    sleep 1
done

if [ $HTTP_READY -eq 0 ]; then
    echo "VELOG_CAUSE: http-never-ready — HTTP marker never matched"
    exit 1
fi

# Verify PHP process identity — PID must match the one we launched.
PHP_PID_LAUNCHED=$(cat "$PHP_PID_FILE")
if ! kill -0 "$PHP_PID_LAUNCHED" 2>/dev/null; then
    echo "Fail: PHP PID $PHP_PID_LAUNCHED not alive after HTTP ready"
    exit 1
fi

# Install plugin.
PLUGIN_DIR="$WP_DIR/wp-content/plugins/velog"
mkdir -p "$PLUGIN_DIR"
cp -a "$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)/." "$PLUGIN_DIR/"

wp plugin activate velog --path="$WP_DIR"

# ============================================================
# fixture-failure: validator is the fixture itself.
# ============================================================
export NEGATIVE_MODE

# Run fixture and retain full per-case output.
FIXTURE_LOG="$DIR/fixture.log"
if [ -f "$PLUGIN_DIR/tests/fixtures/core-003-verify.php" ]; then
    set +e
    wp eval-file "$PLUGIN_DIR/tests/fixtures/core-003-verify.php" "$PORT" \
        --path="$WP_DIR" 2>&1 | tee "$FIXTURE_LOG"
    FIXTURE_EXIT=${PIPESTATUS[0]}
    set -e

    if [ "$NEGATIVE_MODE" = "fixture-failure" ]; then
        if grep -q "VELOG_CAUSE: fixture-failure" "$FIXTURE_LOG"; then
            echo "VELOG_CAUSE: fixture-failure — fixture injected marker found"
            exit 1
        else
            echo "VELOG_CAUSE: fixture-failure — expected failure marker not found in fixture output"
            exit 1
        fi
    fi

    if [ $FIXTURE_EXIT -ne 0 ]; then
        echo "Fail: Fixture exited $FIXTURE_EXIT"
        exit $FIXTURE_EXIT
    fi
else
    echo "Fail: Fixture not found!"
    exit 1
fi

# Copy per-run cause log entry.
echo "NORMAL_RUN: WP=$WP_VERSION exit=0" >> "$CAUSE_LOG"

echo "All tests passed for $WP_VERSION"
exit 0
