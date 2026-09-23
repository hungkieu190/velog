#!/bin/bash
# tests/workflow/product-smoke.sh
set -e

TASK=""
WP_VERSION=""
NEGATIVE_MODE=""

for arg in "$@"; do
    case $arg in
        --task=*) TASK="${arg#*=}" ;;
        --wp-version=*) WP_VERSION="${arg#*=}" ;;
        --negative-mode=*) NEGATIVE_MODE="${arg#*=}" ;;
        *) echo "Unknown argument: $arg"; exit 1 ;;
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
    if ! command -v $cmd &> /dev/null; then
        echo "Fail: $cmd is required"
        exit 1
    fi
done

DIR=$(mktemp -d /tmp/velog-product-smoke.XXXXXXXX)
chmod 0700 "$DIR"
echo "Allocated $DIR"

# Clean exactly once
CLEANED=0
cleanup() {
    local exit_code=$?
    if [ $CLEANED -eq 1 ]; then
        exit $exit_code
    fi
    CLEANED=1
    set +e
    
    # Reap PHP
    if [ -f "$DIR/php.pid" ]; then
        PHP_PID=$(cat "$DIR/php.pid")
        if kill -0 $PHP_PID 2>/dev/null; then
            kill -TERM $PHP_PID 2>/dev/null
            for i in {1..5}; do
                if ! kill -0 $PHP_PID 2>/dev/null; then break; fi
                sleep 1
            done
            if kill -0 $PHP_PID 2>/dev/null; then
                kill -KILL $PHP_PID 2>/dev/null
            fi
        fi
    fi
    
    # Reap MariaDB
    if [ -f "$DIR/db.pid" ]; then
        DB_PID=$(cat "$DIR/db.pid")
        if kill -0 $DB_PID 2>/dev/null; then
            kill -TERM $DB_PID 2>/dev/null
            for i in {1..10}; do
                if ! kill -0 $DB_PID 2>/dev/null; then break; fi
                sleep 1
            done
            if kill -0 $DB_PID 2>/dev/null; then
                kill -KILL $DB_PID 2>/dev/null
            fi
        fi
    fi
    
    rm -rf "$DIR"
    if [ -d "$DIR" ]; then
        echo "Fail: Cleanup failed to remove $DIR"
        exit_code=1
    fi
    
    if [ "$NEGATIVE_MODE" != "" ]; then
        echo "Cleanup completed for negative mode $NEGATIVE_MODE"
    fi
    
    exit $exit_code
}

trap 'cleanup' EXIT
trap 'exit 1' INT TERM ERR

mkdir -p "$DIR/db_data"

if [ "$NEGATIVE_MODE" = "db-start-failure" ]; then
    echo "Injecting db-start-failure"
    # Don't install db, let mariadbd fail
    mariadbd --datadir="$DIR/db_data_missing" --socket="$DIR/mysql.sock" --skip-networking --pid-file="$DIR/db.pid" > "$DIR/db.log" 2>&1 &
else
    mysql_install_db --datadir="$DIR/db_data" --auth-root-authentication-method=normal > "$DIR/db_install.log" 2>&1
    mariadbd --datadir="$DIR/db_data" \
        --socket="$DIR/mysql.sock" \
        --skip-networking \
        --pid-file="$DIR/db.pid" > "$DIR/db.log" 2>&1 &
fi

if [ -f "$DIR/db.pid" ]; then
    DB_PID=$(cat "$DIR/db.pid")
else
    DB_PID=$!
    echo $DB_PID > "$DIR/db.pid"
fi

if [ "$NEGATIVE_MODE" = "db-never-ready" ]; then
    echo "Injecting db-never-ready"
    # Overwrite socket path so it never becomes ready
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
    if ! kill -0 $(cat "$DIR/db.pid") 2>/dev/null; then
        echo "Fail: DB process died during startup"
        exit 1
    fi
    sleep 1
done

if [ $DB_READY -eq 0 ]; then
    echo "Fail: DB never ready"
    exit 1
fi

mysql -S "$DIR/mysql.sock" -u root -e "CREATE DATABASE velog_test;"

WP_DIR="$DIR/wp"
mkdir -p "$WP_DIR"
wp core download --version="$WP_VERSION" --path="$WP_DIR" > "$DIR/wp_download.log" 2>&1
wp config create --dbname=velog_test --dbuser=root --dbhost="localhost:$DIR/mysql.sock" --path="$WP_DIR" > "$DIR/wp_config.log" 2>&1
wp config set DISABLE_WP_CRON true --raw --path="$WP_DIR"

# Bounded port retry
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

wp core install --url="http://localhost:$PORT" --title="VeLog Smoke Test" --admin_user=admin --admin_password=admin --admin_email=admin@example.com --path="$WP_DIR" > "$DIR/wp_install.log" 2>&1

# Create unique marker
MARKER="velog-marker-$(basename $DIR)"
echo "<?php echo '$MARKER';" > "$WP_DIR/marker.php"

if [ "$NEGATIVE_MODE" = "http-never-ready" ]; then
    echo "Injecting http-never-ready"
    php -S localhost:$PORT -t "$DIR/missing_dir" > "$DIR/php.log" 2>&1 &
else
    php -S localhost:$PORT -t "$WP_DIR" > "$DIR/php.log" 2>&1 &
fi
echo $! > "$DIR/php.pid"

echo "Waiting for HTTP..."
HTTP_READY=0
for i in {1..30}; do
    HTTP_RESP=$(curl -s "http://localhost:$PORT/marker.php" || true)
    if [ "$HTTP_RESP" = "$MARKER" ]; then
        HTTP_READY=1
        break
    fi
    if ! kill -0 $(cat "$DIR/php.pid") 2>/dev/null; then
        echo "Fail: PHP process died during startup"
        exit 1
    fi
    sleep 1
done

if [ $HTTP_READY -eq 0 ]; then
    echo "Fail: HTTP never ready or wrong marker"
    exit 1
fi

PLUGIN_DIR="$WP_DIR/wp-content/plugins/velog"
mkdir -p "$PLUGIN_DIR"
cp -a "$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)/." "$PLUGIN_DIR/"

wp plugin activate velog --path="$WP_DIR"

if [ "$NEGATIVE_MODE" = "fixture-failure" ]; then
    echo "Injecting fixture-failure"
    export NEGATIVE_MODE="fixture-failure"
fi

# Run fixture
if [ -f "$PLUGIN_DIR/tests/fixtures/core-003-verify.php" ]; then
    wp eval-file "$PLUGIN_DIR/tests/fixtures/core-003-verify.php" "$PORT" --path="$WP_DIR"
else
    echo "Fail: Fixture not found!"
    exit 1
fi

echo "All tests passed for $WP_VERSION"
exit 0
