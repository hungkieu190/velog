#!/bin/bash
set -euo pipefail

PORT=$1
WP_DIR=$2
COOKIE_MANAGER="$WP_DIR/core004-cookie-manager.txt"
COOKIE_SUBSCRIBER="$WP_DIR/core004-cookie-subscriber.txt"
RESPONSE_BODY="$WP_DIR/core004-response.txt"
RESPONSE_HEADERS="$WP_DIR/core004-headers.txt"

get_raw_bytes() {
    wp eval 'echo base64_encode( (string) $GLOBALS["wpdb"]->get_var( "SELECT option_value FROM {$GLOBALS["wpdb"]->options} WHERE option_name = \"velog_settings\"" ) );' --path="$WP_DIR" 2>/dev/null
}

echo "Running HTTP Endpoint tests on PORT=$PORT..."

# Manager Login
curl -sS -c "$COOKIE_MANAGER" \
    -d "log=manager1&pwd=pass1&wp-submit=Log+In" \
    "http://localhost:$PORT/wp-login.php" > /dev/null
if ! grep -q "wordpress_logged_in" "$COOKIE_MANAGER"; then
    echo "Manager login failed"
    exit 1
fi
NONCE_MGR=$(curl -sS -b "$COOKIE_MANAGER" \
    "http://localhost:$PORT/wp-admin/admin-ajax.php?action=test_get_velog_nonce")

# Subscriber Login
curl -sS -c "$COOKIE_SUBSCRIBER" \
    -d "log=sub1&pwd=pass1&wp-submit=Log+In" \
    "http://localhost:$PORT/wp-login.php" > /dev/null
if ! grep -q "wordpress_logged_in" "$COOKIE_SUBSCRIBER"; then
    echo "Subscriber login failed"
    exit 1
fi
NONCE_SUB=$(curl -sS -b "$COOKIE_SUBSCRIBER" \
    "http://localhost:$PORT/wp-admin/admin-ajax.php?action=test_get_velog_nonce")

if [[ ! "$NONCE_MGR" =~ ^[A-Za-z0-9]+$ ]] || [[ ! "$NONCE_SUB" =~ ^[A-Za-z0-9]+$ ]]; then
    echo "Authenticated nonce endpoint returned an invalid value"
    exit 1
fi
echo "Authenticated nonce acquisition passed."

ROW_BEFORE=$(get_raw_bytes)

echo "Test Malformed Array Input..."
HTTP_CODE=$(curl -sS -o "$RESPONSE_BODY" -D "$RESPONSE_HEADERS" -w "%{http_code}" \
    -b "$COOKIE_MANAGER" \
    -d "action=velog_save_settings&velog_settings_nonce=$NONCE_MGR&record_version=0&distance_unit[]=km&currency_code=JPY" \
    "http://localhost:$PORT/wp-admin/admin-post.php")

if [ "$HTTP_CODE" != "302" ]; then 
    echo "Expected 302 for invalid input, got $HTTP_CODE"
    cat "$RESPONSE_BODY"
    exit 1
fi

if ! grep -qiE '^Location: .*admin\.php\?page=velog-settings&settings-updated=false&error=invalid_input' "$RESPONSE_HEADERS"; then
    echo "Failed to redirect to invalid_input error"
    cat "$RESPONSE_HEADERS"
    exit 1
fi

ROW_AFTER=$(get_raw_bytes)
if [ "$ROW_BEFORE" != "$ROW_AFTER" ]; then echo "Row mutated!"; exit 1; fi
echo "Malformed Array Input rejected successfully."

echo "Test Denied Actor (Subscriber)..."
HTTP_CODE=$(curl -sS -o "$RESPONSE_BODY" -D "$RESPONSE_HEADERS" -w "%{http_code}" \
    -b "$COOKIE_SUBSCRIBER" \
    -d "action=velog_save_settings&velog_settings_nonce=$NONCE_SUB&record_version=0&distance_unit=km&currency_code=JPY" \
    "http://localhost:$PORT/wp-admin/admin-post.php")

if [ "$HTTP_CODE" != "403" ]; then 
    echo "Expected 403 for unauthorized user, got $HTTP_CODE"
    exit 1
fi

ROW_AFTER=$(get_raw_bytes)
if [ "$ROW_BEFORE" != "$ROW_AFTER" ]; then echo "Row mutated!"; exit 1; fi
echo "Denied Actor rejected successfully."

echo "Test Invalid Nonce..."
HTTP_CODE=$(curl -sS -o "$RESPONSE_BODY" -D "$RESPONSE_HEADERS" -w "%{http_code}" \
    -b "$COOKIE_MANAGER" \
    -d "action=velog_save_settings&velog_settings_nonce=invalidnonce123&record_version=0&distance_unit=km&currency_code=JPY" \
    "http://localhost:$PORT/wp-admin/admin-post.php")

if [ "$HTTP_CODE" != "403" ]; then 
    echo "Expected 403 for invalid nonce, got $HTTP_CODE"
    exit 1
fi

ROW_AFTER=$(get_raw_bytes)
if [ "$ROW_BEFORE" != "$ROW_AFTER" ]; then echo "Row mutated!"; exit 1; fi
echo "Invalid Nonce rejected successfully."

rm -f "$COOKIE_MANAGER" "$COOKIE_SUBSCRIBER" "$RESPONSE_BODY" "$RESPONSE_HEADERS"
echo "HTTP Endpoint Tests Passed!"
exit 0
