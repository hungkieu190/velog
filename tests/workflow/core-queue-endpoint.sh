#!/bin/bash
# QA-001 authenticated requests against disposable history/reminder routes only.
set -euo pipefail
trap 'echo "Queue HTTP failure at line $LINENO" >&2' ERR
PORT=$1
WP_DIR=$2
[[ "$WP_DIR" == /tmp/velog-product-smoke.*/wp ]] || exit 1
BASE="http://localhost:$PORT"
BODY="$WP_DIR/queue-body.html"
HEADERS="$WP_DIR/queue-headers.txt"
COOKIE="$WP_DIR/queue-cookie.txt"
ids() { php -r '$r=json_decode(file_get_contents($argv[1]),true); echo $r[$argv[2]];' "$WP_DIR/../queue-fixture.json" "$1"; }
field() { php -r '$s=file_get_contents($argv[1]);$k=preg_quote($argv[2],"~");if(!preg_match("~name=\"".$k."\" value=\"([^\"]*)\"~",$s,$m))exit(1);echo html_entity_decode($m[1],ENT_QUOTES);' "$BODY" "$1"; }
nonce() { php -r '$s=file_get_contents($argv[1]);$k=preg_quote($argv[2],"~");if(!preg_match("~name=\"reminder_command\" value=\"".$k.":([^\"]*)\"~",$s,$m))exit(1);echo $m[1];' "$BODY" "$1"; }
login() {
    local account=$1 secret
    secret=$(wp eval 'echo wp_generate_password(24,false);' --path="$WP_DIR")
    wp user update "$account" --user_pass="$secret" --path="$WP_DIR" >/dev/null
    curl -fsS --max-time 20 -c "$COOKIE" --data-urlencode "log=$account" --data-urlencode "pwd=$secret" "$BASE/wp-login.php" >/dev/null
    grep -q wordpress_logged_in "$COOKIE"
}
post_reminder() {
    local operation=$1 id=$2 version=$3 token=$4 request=$5
    shift 5
    curl -sS --max-time 20 -b "$COOKIE" -D "$HEADERS" -o "$BODY" -w '%{http_code}' \
      --data-urlencode action=mf_velog_reminder_save --data-urlencode "reminder_command=$operation:$token" \
      --data-urlencode "reminder_id=$id" --data-urlencode "record_version=$version" \
      --data-urlencode "request_id=$request" --data-urlencode "settings_context=${CONTEXT:-}" \
      --data-urlencode "vehicle_id=$(ids vehicle)" --data-urlencode title=HTTP-QUEUE \
      --data-urlencode "due_date=$(ids future)" --data-urlencode threshold=100 --data-urlencode "snooze_until=$(ids future)" \
      "$@" "$BASE/wp-admin/admin-post.php"
}
login serv_manager
curl -fsS --max-time 20 -b "$COOKIE" "$BASE/wp-admin/admin.php?page=velog-history&vehicle_id=$(ids vehicle)&term=Corrected" > "$BODY"
grep -q 'Corrected queue note' "$BODY"
grep -q 'Fix recorded note' "$BODY"
! grep -q 'QUEUE-SECRET\|SERV-PRIVATE-CONTACT\|private.serv@example.com' "$BODY"
echo 'PASS: Authenticated finalized history displays filtered corrections without contact leaks.'
curl -fsS --max-time 20 -b "$COOKIE" "$BASE/wp-admin/admin.php?page=velog-reminders&state=all&filter_vehicle=$(ids vehicle)&paged=2" > "$BODY"
grep -q 'Queue-page-' "$BODY"
CONTEXT=$(field settings_context)
TOKEN=$(nonce create)
REQUEST=$(field request_id)
[[ $(post_reminder create 0 0 "$TOKEN" "$REQUEST") == 302 ]]
grep -q 'reminder_notice=saved' "$HEADERS"
ID=$(php -r '$s=file_get_contents($argv[1]);preg_match("~reminder_id=([0-9]+)~",$s,$m);echo $m[1]??"";' "$HEADERS")
[[ -n "$ID" ]]
[[ $(post_reminder create 0 0 "$TOKEN" "$REQUEST") == 302 ]]
grep -q "reminder_id=$ID" "$HEADERS"
echo 'PASS: Reminder create/replay uses native handler and one record.'
for operation in snooze resume complete; do
    curl -fsS --max-time 20 -b "$COOKIE" "$BASE/wp-admin/admin.php?page=velog-reminders&state=all&reminder_id=$ID" > "$BODY"
    TOKEN=$(nonce "$operation")
    REQUEST=$(field request_id)
    VERSION=$(field record_version)
    [[ $(post_reminder "$operation" "$ID" "$VERSION" "$TOKEN" "$REQUEST") == 302 ]]
    grep -q 'reminder_notice=saved' "$HEADERS"
done
[[ $(post_reminder complete "$ID" "$VERSION" "$TOKEN" "$REQUEST") == 302 ]]
grep -q 'reminder_notice=saved' "$HEADERS"
[[ $(post_reminder complete "$ID" "$VERSION" invalid "$REQUEST") == 403 ]]
echo 'PASS: Snooze/resume/completion and completion replay succeed; invalid nonce denied.'
curl -fsS --max-time 20 -b "$COOKIE" "$BASE/wp-admin/admin.php?page=velog-reminders&state=all&reminder_id=$ID" > "$BODY"
grep -q 'This reminder is completed' "$BODY"
! grep -q 'name="reminder_command"' "$BODY"
login serv_tech
[[ $(curl -sS --max-time 20 -b "$COOKIE" -o "$BODY" -w '%{http_code}' "$BASE/wp-admin/admin.php?page=velog-reminders") == 403 ]]
[[ $(post_reminder complete "$ID" 4 invalid "$REQUEST") == 403 ]]
curl -fsS --max-time 20 -b "$COOKIE" "$BASE/wp-admin/admin.php?page=velog-history&vehicle_id=$(ids vehicle)" > "$BODY"
grep -q 'Finalized service history' "$BODY"
! grep -q 'QUEUE-SECRET\|SERV-PRIVATE-CONTACT\|private.serv@example.com' "$BODY"
echo 'PASS: Technician can read contact-free history but cannot access reminder page or handler.'
login serv_denied
[[ $(curl -sS --max-time 20 -b "$COOKIE" -o "$BODY" -w '%{http_code}' "$BASE/wp-admin/admin.php?page=velog-history&vehicle_id=$(ids vehicle)") == 403 ]]
echo 'PASS: Subscriber cannot access private history.'
