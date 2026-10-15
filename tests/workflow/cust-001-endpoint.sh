#!/bin/bash
# HTTP regression for the native customer form; run only in product-smoke's disposable site.
set -euo pipefail
trap 'echo "HTTP regression failed at line $LINENO" >&2' ERR
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
HTTP_ID=$(wp post list --post_type=mf_velog_customer --post_status=private --orderby=ID --order=DESC --posts_per_page=1 --field=ID --path="$WP_DIR")
[[ "$HTTP_ID" =~ ^[1-9][0-9]*$ ]]
curl -fsS --max-time 15 -b "$COOKIE" "$BASE/wp-admin/admin.php?page=velog-customers&velog_notice=saved&customer_id=$HTTP_ID" > "$BODY"
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

# Contact search posts private criteria and redirects with an opaque, scoped token.
curl -fsS --max-time 15 -b "$COOKIE" "$BASE/wp-admin/admin.php?page=velog-customers" > "$BODY"
SEARCH_NONCE=$(php -r '$s=file_get_contents($argv[1]); if(!preg_match("~<form[^>]*class=\"velog-customer-search\"[^>]*>(.*?)</form>~s",$s,$f) || !preg_match("~name=\"velog_customer_nonce\" value=\"([^\"]+)\"~",$f[1],$n))exit(1); echo $n[1];' "$BODY")
STATUS=$(curl -sS --max-time 15 -b "$COOKIE" -D "$HEADERS" -o "$BODY" -w '%{http_code}' \
    --data-urlencode action=velog_customer_search --data-urlencode "velog_customer_nonce=$SEARCH_NONCE" \
    --data-urlencode term=http.customer@example.com --data-urlencode state=active \
    "$BASE/wp-admin/admin-post.php")
[[ "$STATUS" == 302 ]]
LOCATION=$(sed -n 's/^[Ll]ocation: //p' "$HEADERS" | tr -d '\r')
[[ "$LOCATION" == *customer_search=* ]]
[[ "$LOCATION" != *http.customer* && "$LOCATION" != *term=* && "$LOCATION" != *%40* ]]
curl -fsS --max-time 15 -b "$COOKIE" "$LOCATION" > "$BODY"
grep -q 'HTTP Nguyễn 測試' "$BODY"
echo 'PASS: Contact search uses POST and an opaque token without contact values in its redirect.'
# An independently authorized administrator cannot reuse the manager's reference.
OTHER_COOKIE="$WP_DIR/cust001-other-cookie.txt"
TEST_PASSWORD=$(wp eval 'echo wp_generate_password(24, false);' --path="$WP_DIR")
wp user update admin --user_pass="$TEST_PASSWORD" --path="$WP_DIR" >/dev/null
curl -fsS --max-time 15 -c "$OTHER_COOKIE" --data-urlencode log=admin \
    --data-urlencode "pwd=$TEST_PASSWORD" "$BASE/wp-login.php" >/dev/null
unset TEST_PASSWORD
curl -fsS --max-time 15 -b "$OTHER_COOKIE" "$LOCATION" > "$BODY"
grep -q 'Search expired or is unavailable for this account' "$BODY"
! grep -q 'HTTP Nguyễn 測試' "$BODY"
echo 'PASS: Another authorized account cannot reuse the private search reference.'
# Expired and foreign references fail closed instead of broadening the result.
TOKEN=${LOCATION##*customer_search=}
wp transient delete "velog_customer_search_$(wp user get cust_manager --field=ID --path="$WP_DIR")_$TOKEN" --path="$WP_DIR" >/dev/null
curl -fsS --max-time 15 -b "$COOKIE" "$LOCATION" > "$BODY"
grep -q 'Search expired or is unavailable for this account' "$BODY"
! grep -q 'HTTP Nguyễn 測試' "$BODY"
echo 'PASS: Expired search token reports an error and does not expose unfiltered rows.'
STATUS=$(curl -sS --max-time 15 -b "$COOKIE" -o "$BODY" -w '%{http_code}' \
    -d 'action=velog_customer_search&velog_customer_nonce=invalid&term=private' \
    "$BASE/wp-admin/admin-post.php")
[[ "$STATUS" == 403 ]]
echo 'PASS: Invalid search nonce is rejected.'
# Pagination retains only the scoped reference, never the private search term.
STATUS=$(curl -sS --max-time 15 -b "$COOKIE" -D "$HEADERS" -o "$BODY" -w '%{http_code}' \
    --data-urlencode action=velog_customer_search --data-urlencode "velog_customer_nonce=$SEARCH_NONCE" \
    --data-urlencode 'term=Batch Fixture' --data-urlencode state=active \
    "$BASE/wp-admin/admin-post.php")
[[ "$STATUS" == 302 ]]
LOCATION=$(sed -n 's/^[Ll]ocation: //p' "$HEADERS" | tr -d '\r')
curl -fsS --max-time 15 -b "$COOKIE" "$LOCATION" > "$BODY"
grep -q 'customer_search=' "$BODY"
! grep -q 'term=Batch' "$BODY"
curl -fsS --max-time 15 -b "$COOKIE" "${LOCATION}&paged=2" > "$BODY"
grep -q 'Batch Fixture 051' "$BODY"
! grep -q 'Batch Fixture 001' "$BODY"
echo 'PASS: HTTP search pagination keeps the token and reaches the remaining row.'
# Anonymous clients cannot use the private search handler.
STATUS=$(curl -sS --max-time 15 -o "$BODY" -w '%{http_code}' \
    -d 'action=velog_customer_search&term=private' "$BASE/wp-admin/admin-post.php")
[[ "$STATUS" == 302 || "$STATUS" == 400 || "$STATUS" == 403 ]]
echo 'PASS: Anonymous search cannot enter the authorized handler.'
