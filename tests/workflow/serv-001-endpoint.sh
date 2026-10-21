#!/bin/bash
# Real authenticated service/type HTTP assertions on a disposable site only.
set -euo pipefail
trap 'echo "Service HTTP failure at line $LINENO" >&2' ERR
PORT=$1
WP_DIR=$2
[[ "$WP_DIR" == /tmp/velog-product-smoke.*/wp ]] || exit 1
BASE="http://localhost:$PORT"
BODY="$WP_DIR/serv-body.html"
HEADERS="$WP_DIR/serv-headers.txt"
COOKIE="$WP_DIR/serv-cookie.txt"
MANAGER="$WP_DIR/serv-manager-cookie.txt"
CONTEXT=""
ids() { php -r '$r=json_decode(file_get_contents($argv[1]),true); echo $r[$argv[2]];' "$WP_DIR/../serv001-fixture.json" "$1"; }
field() { php -r '$s=file_get_contents($argv[1]); $k=preg_quote($argv[2],"~"); if(!preg_match("~name=\"".$k."\" value=\"([^\"]*)\"~",$s,$m))exit(1); echo html_entity_decode($m[1],ENT_QUOTES);' "$BODY" "$1"; }
command_nonce() { php -r '$s=file_get_contents($argv[1]); $k=preg_quote($argv[2],"~"); if(!preg_match("~name=\"service_command\" value=\"".$k.":([^\"]*)\"~",$s,$m))exit(1); echo $m[1];' "$BODY" "$1"; }
login() {
    local account=$1 cookie_file=$2 secret
    secret=$(wp eval 'echo wp_generate_password(24,false);' --path="$WP_DIR")
    wp user update "$account" --user_pass="$secret" --path="$WP_DIR" >/dev/null
    curl -fsS --max-time 20 -c "$cookie_file" --data-urlencode "log=$account" --data-urlencode "pwd=$secret" "$BASE/wp-login.php" >/dev/null
    grep -q wordpress_logged_in "$cookie_file"
}
post_service() {
    local cookie=$1 operation=$2 id=$3 version=$4 nonce=$5 request=$6
    shift 6
    curl -sS --max-time 20 -b "$cookie" -D "$HEADERS" -o "$BODY" -w '%{http_code}' \
        --data-urlencode action=mf_velog_service_save --data-urlencode "service_action=$operation" \
        --data-urlencode "service_id=$id" --data-urlencode "record_version=$version" \
        --data-urlencode "service_nonce=$nonce" --data-urlencode "request_id=$request" \
        --data-urlencode "settings_context=$CONTEXT" --data-urlencode "vehicle_id=$(ids vehicle)" \
        --data-urlencode service_date=2020-01-05 --data-urlencode reading=250 \
        --data-urlencode "service_type_id=$(ids type)" --data-urlencode notes=HTTP-SERVICE \
        --data-urlencode cost=12.30 --data-urlencode reason= "$@" "$BASE/wp-admin/admin-post.php"
}
login serv_tech "$COOKIE"
curl -fsS --max-time 20 -b "$COOKIE" "$BASE/wp-admin/admin.php?page=velog-services" > "$BODY"
CONTEXT=$(field settings_context)
NONCE=$(command_nonce create)
REQUEST=$(field request_id)
[[ $(post_service "$COOKIE" create 0 0 "$NONCE" "$REQUEST") == 302 ]]
grep -qi 'Location: .*service_notice=saved' "$HEADERS"
ID=$(php -r '$s=file_get_contents($argv[1]); preg_match("~service_id=([0-9]+)~",$s,$m);echo $m[1]??"";' "$HEADERS")
[[ -n "$ID" ]]
[[ $(post_service "$COOKIE" create 0 0 "$NONCE" "$REQUEST") == 302 ]]
grep -qi "service_id=$ID" "$HEADERS"
echo 'PASS: Authenticated draft create/replay retains one service.'
curl -fsS --max-time 20 -b "$COOKIE" "$BASE/wp-admin/admin.php?page=velog-services&service_id=$ID" > "$BODY"
NONCE=$(command_nonce finalize)
REQUEST=$(field request_id)
VERSION=$(field record_version)
[[ $(post_service "$COOKIE" finalize "$ID" "$VERSION" "$NONCE" "$REQUEST") == 302 ]]
grep -qi 'Location: .*service_notice=saved' "$HEADERS"
curl -fsS --max-time 20 -b "$COOKIE" "$BASE/wp-admin/admin.php?page=velog-services&service_id=$ID" > "$BODY"
grep -q 'Finalized service' "$BODY"
! grep -q 'name="service_command"' "$BODY"
! grep -q 'SERV-PRIVATE-CONTACT\|private.serv@example.com' "$BODY"
echo 'PASS: Own draft finalizes through real handler; technician finalized detail has no mutation/contact UI.'
[[ $(post_service "$COOKIE" finalize "$ID" 2 invalid "$REQUEST") == 403 ]]
login serv_other "$WP_DIR/serv-other-cookie.txt"
FOREIGN=$(ids foreign_draft)
curl -fsS --max-time 20 -b "$WP_DIR/serv-other-cookie.txt" "$BASE/wp-admin/admin.php?page=velog-services&service_id=$FOREIGN" > "$BODY"
grep -q 'notice-error' "$BODY"
! grep -q 'name="service_command"' "$BODY"
curl -fsS --max-time 20 -b "$WP_DIR/serv-other-cookie.txt" "$BASE/wp-admin/admin.php?page=velog-services&state=draft&paged=2" > "$BODY"
php -r '$s=file_get_contents($argv[1]); if(!preg_match("~<tbody>(.*?)</tbody>~s",$s,$m)||substr_count($m[1],"<tr>")!==1)exit(1);' "$BODY"
echo 'PASS: Foreign draft ID does not expose content/form; authenticated own-draft pagination works.'
login serv_manager "$MANAGER"
curl -fsS --max-time 20 -b "$MANAGER" "$BASE/wp-admin/admin.php?page=velog-services&service_id=$ID" > "$BODY"
CONTEXT=$(field settings_context)
NONCE=$(command_nonce correct)
REQUEST=$(field request_id)
VERSION=$(field record_version)
[[ $(post_service "$MANAGER" correct "$ID" "$VERSION" "$NONCE" "$REQUEST" --data-urlencode notes=CorrectedHTTP) == 302 ]]
grep -qi 'service_notice=reason_required' "$HEADERS"
[[ $(post_service "$MANAGER" correct "$ID" "$VERSION" "$NONCE" "$REQUEST" --data-urlencode notes=CorrectedHTTP --data-urlencode reason=Correction) == 302 ]]
grep -qi 'service_notice=saved' "$HEADERS"
[[ $(post_service "$MANAGER" correct "$ID" "$VERSION" "$NONCE" "$REQUEST" --data-urlencode notes=StaleHTTP --data-urlencode reason=Correction) == 302 ]]
grep -qi 'service_notice=stale_version' "$HEADERS"
echo 'PASS: HTTP manager correction requires reason, persists audit, rejects stale version.'
# Currency/unit context is bound server-side and cannot relabel an old form.
wp eval '$s=MF\VeLog\Common\Regional\ShopSettings::get_settings();$s["distance_unit"]="mi";$r=MF\VeLog\Common\Regional\ShopSettings::save_settings($s,$s["record_version"]);if(is_wp_error($r))throw new RuntimeException($r->get_error_code());' --user=1 --path="$WP_DIR"
[[ $(post_service "$MANAGER" correct "$ID" 3 "$NONCE" "$REQUEST" --data-urlencode reason=Correction) == 302 ]]
grep -qi 'service_notice=settings_changed' "$HEADERS"
wp eval '$s=MF\VeLog\Common\Regional\ShopSettings::get_settings();$s["distance_unit"]="km";$r=MF\VeLog\Common\Regional\ShopSettings::save_settings($s,$s["record_version"]);if(is_wp_error($r))throw new RuntimeException($r->get_error_code());' --user=1 --path="$WP_DIR"
echo 'PASS: Stale global Settings context cannot reinterpret service reading.'
curl -fsS --max-time 20 -b "$MANAGER" "$BASE/wp-admin/admin.php?page=velog-service-types" > "$BODY"
NONCE=$(field type_nonce)
REQUEST=$(field request_id)
STATUS=$(curl -sS --max-time 20 -b "$MANAGER" -D "$HEADERS" -o "$BODY" -w '%{http_code}' \
    --data-urlencode action=mf_velog_service_type_save --data-urlencode type_id=0 \
    --data-urlencode record_version=0 --data-urlencode "type_nonce=$NONCE" --data-urlencode "request_id=$REQUEST" \
    --data-urlencode type_name=HTTP-Type --data-urlencode type_state=active "$BASE/wp-admin/admin-post.php")
[[ "$STATUS" == 302 ]]
grep -qi 'service_notice=saved' "$HEADERS"
STATUS=$(curl -sS --max-time 20 -b "$COOKIE" -o "$BODY" -w '%{http_code}' "$BASE/wp-admin/admin.php?page=velog-service-types")
[[ "$STATUS" == 403 ]]
# A valid technician-session action nonce is still insufficient for type writes/corrections.
TOKEN=$(awk '$6 ~ /^wordpress_logged_in_/ {print $7}' "$COOKIE")
TECH_NONCE=$(VELOG_SERV_COOKIE="$TOKEN" wp eval 'wp_set_current_user(get_user_by("login","serv_tech")->ID);$_COOKIE[LOGGED_IN_COOKIE]=getenv("VELOG_SERV_COOKIE");echo wp_create_nonce("mf_velog_service_type_0");' --path="$WP_DIR")
STATUS=$(curl -sS --max-time 20 -b "$COOKIE" -o "$BODY" -w '%{http_code}' --data-urlencode action=mf_velog_service_type_save --data-urlencode type_id=0 --data-urlencode "type_nonce=$TECH_NONCE" "$BASE/wp-admin/admin-post.php")
[[ "$STATUS" == 403 ]]
unset TOKEN
echo 'PASS: Manager type create succeeds; technician valid-session nonce cannot authorize type mutation.'
login serv_denied "$WP_DIR/serv-denied-cookie.txt"
STATUS=$(curl -sS --max-time 20 -b "$WP_DIR/serv-denied-cookie.txt" -o "$BODY" -w '%{http_code}' "$BASE/wp-admin/admin.php?page=velog-services")
[[ "$STATUS" == 403 ]]
STATUS=$(curl -sS --max-time 20 -o "$BODY" -w '%{http_code}' "$BASE/wp-admin/admin.php?page=velog-services")
[[ "$STATUS" == 302 ]]
echo 'PASS: Subscriber and anonymous service reads do not expose private records.'
rm -f "$BODY" "$HEADERS" "$COOKIE" "$MANAGER" "$WP_DIR/serv-other-cookie.txt" "$WP_DIR/serv-denied-cookie.txt"
