#!/bin/bash
# HTTP regression for the native customer form; run only in product-smoke's disposable site.
set -euo pipefail
PORT=$1
WP_DIR=$2
[[ "$WP_DIR" == /tmp/velog-product-smoke.*/wp ]] || { echo 'Disposable site required'; exit 1; }
COOKIE="$WP_DIR/cust001-cookie.txt"
BODY="$WP_DIR/cust001-body.html"
HEADERS="$WP_DIR/cust001-headers.txt"
BASE="http://localhost:$PORT"
TEST_PASSWORD=$(wp eval 'echo wp_generate_password(24, false);' --path="$WP_DIR")
wp user update cust_manager --user_pass="$TEST_PASSWORD" --path="$WP_DIR" >/dev/null
curl -fsS --max-time 15 -c "$COOKIE" --data-urlencode 'log=cust_manager' \
    --data-urlencode "pwd=$TEST_PASSWORD" "$BASE/wp-login.php" >/dev/null
unset TEST_PASSWORD
grep -q wordpress_logged_in "$COOKIE"
curl -fsS --max-time 15 -b "$COOKIE" "$BASE/wp-admin/admin.php?page=velog-customers" > "$BODY"
NONCE=$(php -r '$s=file_get_contents($argv[1]); if(!preg_match("/name=\"velog_customer_nonce\" value=\"([^\"]+)\"/",$s,$m))exit(1); echo $m[1];' "$BODY")
count_customers() {
    wp post list --post_type=mf_velog_customer --post_status=any --format=count --path="$WP_DIR"
}
BEFORE=$(count_customers)
STATUS=$(curl -sS --max-time 15 -b "$COOKIE" -D "$HEADERS" -o "$BODY" -w '%{http_code}' \
    --data-urlencode action=velog_customer_save --data-urlencode customer_action=create \
    --data-urlencode "velog_customer_nonce=$NONCE" --data-urlencode 'name=HTTP Nguyễn 測試' \
    --data-urlencode 'phone=+66 81 234 5678' --data-urlencode 'email=http.customer@example.com' \
    "$BASE/wp-admin/admin-post.php")
[[ "$STATUS" == 302 ]]
grep -qi 'Location: .*velog_notice=saved' "$HEADERS"
[[ $(count_customers) -eq $((BEFORE + 1)) ]]
curl -fsS --max-time 15 -b "$COOKIE" "$BASE/wp-admin/admin.php?page=velog-customers&velog_notice=saved" > "$BODY"
grep -q 'Customer saved.' "$BODY"
grep -q 'HTTP Nguyễn 測試' "$BODY"
grep -q 'http.customer@example.com' "$BODY"
echo 'PASS: Authenticated native form POST creates exactly one Unicode customer, redirects, and displays success and persisted data.'
STATUS=$(curl -sS --max-time 15 -b "$COOKIE" -o "$BODY" -w '%{http_code}' \
    -d 'action=velog_customer_save&customer_action=create&velog_customer_nonce=invalid&name=Denied' \
    "$BASE/wp-admin/admin-post.php")
[[ "$STATUS" == 403 ]]
[[ $(count_customers) -eq $((BEFORE + 1)) ]]
echo 'PASS: Invalid nonce rejects the HTTP write without adding a customer.'
for CODE in storage_unavailable forbidden; do
    curl -fsS --max-time 15 -b "$COOKIE" "$BASE/wp-admin/admin.php?page=velog-customers&velog_notice=$CODE" > "$BODY"
    grep -q 'notice-error' "$BODY"
done
echo 'PASS: Storage and permission redirect codes render visible error notices over HTTP.'
