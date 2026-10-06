#!/bin/bash
set -euo pipefail

DIR="/tmp/velog-fe-verify-$$"
mkdir -p "$DIR/db_data"
echo "Allocated $DIR"

mysql_install_db --datadir="$DIR/db_data" --auth-root-authentication-method=normal

mariadbd --datadir="$DIR/db_data" --socket="$DIR/mysql.sock" --skip-networking --pid-file="$DIR/db.pid" >"$DIR/db.log" 2>&1 &
DB_PID=$!
echo $DB_PID > "$DIR/db.pid"

echo "Waiting for DB..."
set +e
for i in {1..30}; do
    mysqladmin ping -S "$DIR/mysql.sock" -u root --silent 2>/dev/null
    if [ $? -eq 0 ]; then
        break
    fi
    sleep 1
done
set -e

mysql -S "$DIR/mysql.sock" -u root -e "CREATE DATABASE velog_test;"

WP_DIR="$DIR/wp"
mkdir -p "$WP_DIR"
wp core download --version="6.4.3" --path="$WP_DIR"
wp config create --dbname=velog_test --dbuser=root --dbhost="localhost:$DIR/mysql.sock" --path="$WP_DIR"
wp config set DISABLE_WP_CRON true --raw --path="$WP_DIR"

PORT=8080
wp core install --url="http://localhost:$PORT" --title="VeLog FE Verify" --admin_user=admin --admin_password=admin --admin_email=admin@example.com --path="$WP_DIR"

PLUGIN_DIR="$WP_DIR/wp-content/plugins/velog"
mkdir -p "$PLUGIN_DIR"
cp -a "$(cd "$(dirname "${BASH_SOURCE[0]}")/../../../.." && pwd)/." "$PLUGIN_DIR/"

wp plugin activate velog --path="$WP_DIR"

php -S "localhost:$PORT" -t "$WP_DIR" >"$DIR/php.log" 2>&1 &
PHP_PID=$!
echo $PHP_PID > "$DIR/php.pid"

echo "WordPress running at http://localhost:$PORT"
echo "DIR=$DIR"

# Wait forever until killed
wait
