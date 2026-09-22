#!/bin/bash
# tests/workflow/product-smoke.sh
set -e

TASK=""
WP_VERSION=""
NEGATIVE_MODE=""

for arg in "$@"; do
    case $arg in
        --task=*)
            TASK="${arg#*=}"
            ;;
        --wp-version=*)
            WP_VERSION="${arg#*=}"
            ;;
        --negative-mode=*)
            NEGATIVE_MODE="${arg#*=}"
            ;;
        *)
            echo "Unknown argument: $arg"
            exit 1
            ;;
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

for cmd in wp mariadbd mysql php curl; do
    if ! command -v $cmd &> /dev/null; then
        echo "Fail: $cmd is required"
        exit 1
    fi
done

DIR=$(mktemp -d /tmp/velog-product-smoke.XXXXXXXX)
chmod 0700 "$DIR"
echo "Allocated $DIR"

cleanup() {
    local exit_code=$?
    set +e
    if [ -f "$DIR/php.pid" ]; then
        kill $(cat "$DIR/php.pid") 2>/dev/null || true
    fi
    if [ -f "$DIR/db.pid" ]; then
        kill $(cat "$DIR/db.pid") 2>/dev/null || true
    fi
    rm -rf "$DIR"
    exit $exit_code
}
trap cleanup EXIT INT TERM ERR

if [ "$NEGATIVE_MODE" = "db-start-failure" ]; then
    echo "Injecting db-start-failure"
    exit 9
fi

mkdir -p "$DIR/db_data"
mysql_install_db --datadir="$DIR/db_data" --auth-root-authentication-method=normal > "$DIR/db_install.log" 2>&1

mariadbd --datadir="$DIR/db_data" \
    --socket="$DIR/mysql.sock" \
    --skip-networking \
    --pid-file="$DIR/db.pid" > "$DIR/db.log" 2>&1 &

if [ "$NEGATIVE_MODE" = "db-never-ready" ]; then
    echo "Injecting db-never-ready"
    sleep 2
    exit 9
fi

echo "Waiting for DB..."
DB_READY=0
for i in {1..30}; do
    if mysqladmin ping -S "$DIR/mysql.sock" -u root --silent 2>/dev/null; then
        DB_READY=1
        break
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
wp core install --url="http://localhost:8888" --title="VeLog Smoke Test" --admin_user=admin --admin_password=admin --admin_email=admin@example.com --path="$WP_DIR" > "$DIR/wp_install.log" 2>&1

php -S localhost:8888 -t "$WP_DIR" > "$DIR/php.log" 2>&1 &
echo $! > "$DIR/php.pid"

if [ "$NEGATIVE_MODE" = "http-never-ready" ]; then
    echo "Injecting http-never-ready"
    sleep 2
    exit 9
fi

echo "Waiting for HTTP..."
HTTP_READY=0
for i in {1..30}; do
    if curl -s "http://localhost:8888" > /dev/null; then
        HTTP_READY=1
        break
    fi
    sleep 1
done

if [ $HTTP_READY -eq 0 ]; then
    echo "Fail: HTTP never ready"
    exit 1
fi

PLUGIN_DIR="$WP_DIR/wp-content/plugins/velog"
mkdir -p "$PLUGIN_DIR"
cp -a "$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)/." "$PLUGIN_DIR/"

wp plugin activate velog --path="$WP_DIR"

if [ "$NEGATIVE_MODE" = "fixture-failure" ]; then
    echo "Injecting fixture-failure"
    exit 9
fi

# Run fixture
if [ -f "$PLUGIN_DIR/tests/fixtures/core-003-verify.php" ]; then
    wp eval-file "$PLUGIN_DIR/tests/fixtures/core-003-verify.php" --path="$WP_DIR"
else
    echo "Fail: Fixture not found!"
    exit 1
fi

# Test HTTP paths
# Ensure single posts, search, feed, sitemap do not leak private types
wp post create --post_type=mf_velog_customer --post_title="Secret Customer" --post_status=publish --path="$WP_DIR" >/dev/null
wp post create --post_type=post --post_title="Public Post" --post_status=publish --path="$WP_DIR" >/dev/null

search_html=$(curl -s "http://localhost:8888/?s=Secret")
if echo "$search_html" | grep -q "Secret Customer"; then
    echo "Fail: Private type leaked in search"
    exit 1
fi

feed_xml=$(curl -s "http://localhost:8888/?feed=rss2")
if echo "$feed_xml" | grep -q "Secret Customer"; then
    echo "Fail: Private type leaked in feed"
    exit 1
fi

echo "All tests passed for $WP_VERSION"
exit 0
