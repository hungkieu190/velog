#!/bin/bash
set -e
trap 'echo "Error on line $LINENO"; exit 1' ERR

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$DIR/core001-smoke-functions.sh"

PLUGIN_SRC="/home/ecommercelife/Local Sites/velog/app/public/wp-content/plugins/velog"
LOG_FILE="${CORE001_LOG_FILE:-$PLUGIN_SRC/ai-document/evidence/CORE-001/round-7/isolated-smoke.log}"
mkdir -p "$(dirname "$LOG_FILE")"

exec > >(tee "$LOG_FILE") 2>&1

echo "Starting isolated smoke test at $(date)"
echo "PHP Version: $(php -v | head -n1)"

echo "Reserving work directory..."
if ! reserve_work_dir; then
    echo "Failed to reserve work directory"
    exit 1
fi
echo "Work dir: $WORK_DIR"

DB_DIR="$WORK_DIR/db"
WP_DIR="$WORK_DIR/wp"
SOCK="$WORK_DIR/mysql.sock"

echo "Initializing MariaDB..."
mkdir -p "$DB_DIR"
mysql_install_db --datadir="$DB_DIR" --auth-root-authentication-method=normal || mariadb-install-db --datadir="$DB_DIR" --auth-root-authentication-method=normal

echo "Starting MariaDB..."
mysqld --datadir="$DB_DIR" --socket="$SOCK" --pid-file="$WORK_DIR/mysqld.pid" --skip-networking &
DB_PID=$!

echo "Waiting for MariaDB to be ready..."
if ! wait_ready "$DB_PID" "$SOCK" 30; then
    echo "MariaDB failed to start or timeout reached"
    exit 1
fi

echo "Creating database..."
mysql -u root -S "$SOCK" -e "CREATE DATABASE wp_test;"

echo "Preparing isolated plugin copy..."
ISO_PLUGIN="$WORK_DIR/velog_plugin"
cp -r "$PLUGIN_SRC" "$ISO_PLUGIN"
mkdir -p "$ISO_PLUGIN/languages"

# Generate translation file for testing
cat << 'EOF' > "$WORK_DIR/velog-vi.po"
msgid ""
msgstr ""
"Project-Id-Version: VeLog 0.1.0\n"
"Language: vi\n"
"MIME-Version: 1.0\n"
"Content-Type: text/plain; charset=UTF-8\n"
"Content-Transfer-Encoding: 8bit\n"

msgid "Hello World"
msgstr "Xin Chào"
EOF
msgfmt -o "$ISO_PLUGIN/languages/velog-vi.mo" "$WORK_DIR/velog-vi.po"

for WP_VERSION in "6.4.3" "6.7.2"; do
    echo ""
    echo "======================================"
    echo "Testing against WordPress $WP_VERSION"
    echo "======================================"
    
    rm -rf "$WP_DIR"
    mkdir -p "$WP_DIR"
    
    echo "Downloading WordPress $WP_VERSION..."
    wp core download --version="$WP_VERSION" --path="$WP_DIR" --force >/dev/null
    
    echo "Creating wp-config.php..."
    wp config create --path="$WP_DIR" --dbname=wp_test --dbuser=root --dbpass="" --dbhost="localhost:$SOCK" --extra-php <<PHP
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', false );
define( 'WP_DEBUG_DISPLAY', true );
PHP
    
    echo "Installing WordPress $WP_VERSION..."
    wp core install --path="$WP_DIR" --url="http://localhost:8000" --title="Test" --admin_user=admin --admin_password=admin --admin_email=admin@test.com --skip-email >/dev/null
    
    echo "Setting language to Vietnamese..."
    wp language core install vi --path="$WP_DIR" >/dev/null
    wp site switch-language vi --path="$WP_DIR" >/dev/null
    
    echo "Linking isolated VeLog plugin..."
    ln -s "$ISO_PLUGIN" "$WP_DIR/wp-content/plugins/velog"
    
    echo "Testing plugin activation..."
    wp plugin activate velog --path="$WP_DIR"
    
    echo "Creating diagnostic mu-plugin..."
    mkdir -p "$WP_DIR/wp-content/mu-plugins"
    cat << 'EOF' > "$WP_DIR/wp-content/mu-plugins/diagnostic.php"
<?php
if ( getenv('TEST_NEGATIVE_CONTROL') ) {
    add_action('plugins_loaded', function() {
        load_plugin_textdomain('velog', false, 'velog/languages');
        __( 'Hello World', 'velog' );
    }, 1);
}

if ( getenv('TEST_NORMAL_LOAD') ) {
    add_action('init', function() {
        echo "Normal load translation: " . __( 'Hello World', 'velog' ) . "\n";
    }, 99);
}
EOF

    echo "Running Normal Load Test..."
    set +e
    NORMAL_OUTPUT=$(TEST_NORMAL_LOAD=1 wp eval "echo 'WP-CLI Booted';" --path="$WP_DIR" 2>&1)
    NORMAL_EXIT=$?
    set -e
    echo "$NORMAL_OUTPUT"
    
    if normal_gate "$NORMAL_OUTPUT" $NORMAL_EXIT; then
        echo "SUCCESS: Normal gate passed (Translated output found and no VeLog early-loading warning detected)"
    else
        echo "FAIL: Normal gate failed"
        exit 1
    fi

    # The warning behavior for early loading was introduced in WP >= 6.7.
    if [[ "$WP_VERSION" == 6.7* ]]; then
        echo "Running Early Load Test (Negative Control) on $WP_VERSION..."
        set +e
        EARLY_OUTPUT=$(TEST_NEGATIVE_CONTROL=1 wp eval "echo 'WP-CLI Booted';" --path="$WP_DIR" 2>&1)
        EARLY_EXIT=$?
        set -e
        echo "$EARLY_OUTPUT"

        velog_early_warning_detector "$EARLY_OUTPUT"
        if [ $? -eq 0 ]; then
            echo "SUCCESS: Negative control correctly triggered early warning"
        else
            echo "FAIL: Negative control did not trigger early warning"
            exit 1
        fi

        # negative_replay as described in the blueprint
        echo "Running negative replay assertions..."
        if ! normal_gate "${NORMAL_OUTPUT}\n${EARLY_OUTPUT}" 0; then
            echo "SUCCESS: Normal gate rejected combined output (negative control output appended)"
        else
            echo "FAIL: Normal gate passed with contaminated output"
            exit 1
        fi
        
        if ! normal_gate "$NORMAL_OUTPUT" 1; then
            echo "SUCCESS: Normal gate rejected simulated nonzero exit code"
        else
            echo "FAIL: Normal gate passed with nonzero exit code"
            exit 1
        fi
    fi

    echo "Testing plugin deactivation..."
    wp plugin deactivate velog --path="$WP_DIR"
    
    echo "Testing plugin reactivation..."
    wp plugin activate velog --path="$WP_DIR"

done

echo ""
echo "All smoke tests completed successfully."
exit 0
