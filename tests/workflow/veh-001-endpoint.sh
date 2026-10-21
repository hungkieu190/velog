#!/bin/bash
# Real authenticated HTTP vehicle checks, restricted to a disposable product-smoke site.
set -euo pipefail
trap 'echo "Vehicle HTTP failure at line $LINENO" >&2' ERR
PORT=$1
WP_DIR=$2
[[ "$WP_DIR" == /tmp/velog-product-smoke.*/wp ]] || { echo 'Disposable site required'; exit 1; }
BASE="http://localhost:$PORT"
CONTEXT=""
COOKIE="$WP_DIR/veh001-cookie.txt"
TECH_COOKIE="$WP_DIR/veh001-tech-cookie.txt"
BODY="$WP_DIR/veh001-body.html"
HEADERS="$WP_DIR/veh001-headers.txt"
field() {
    php -r '$s=file_get_contents($argv[1]); $key=preg_quote($argv[2],"~"); if(!preg_match("~name=\"".$key."\" value=\"([^\"]*)\"~",$s,$m))exit(1); echo html_entity_decode($m[1],ENT_QUOTES);' "$BODY" "$1"
}
login() {
    local account=$1 cookie_file=$2 test_password
    test_password=$(wp eval 'echo wp_generate_password(24, false);' --path="$WP_DIR")
    wp user update "$account" --user_pass="$test_password" --path="$WP_DIR" >/dev/null
    curl -fsS --max-time 20 -c "$cookie_file" --data-urlencode "log=$account" \
        --data-urlencode "pwd=$test_password" "$BASE/wp-login.php" >/dev/null
    grep -q wordpress_logged_in "$cookie_file"
}
count_vehicles() {
    wp post list --post_type=mf_velog_vehicle --post_status=private --format=count --path="$WP_DIR"
}
post_vehicle() {
    local cookie_file=$1 operation=$2 id=$3 version=$4 nonce=$5 request=$6
    shift 6
    curl -sS --max-time 20 -b "$cookie_file" -D "$HEADERS" -o "$BODY" -w '%{http_code}' \
        --data-urlencode action=mf_velog_vehicle_save --data-urlencode "vehicle_action=$operation" \
        --data-urlencode "vehicle_id=$id" --data-urlencode "record_version=$version" \
        --data-urlencode "mf_velog_vehicle_nonce=$nonce" --data-urlencode "request_id=$request" \
        --data-urlencode jurisdiction= --data-urlencode plate= --data-urlencode vin=HTTP-VEH001 \
        --data-urlencode year= --data-urlencode make=HTTP --data-urlencode model=Model \
        --data-urlencode color= --data-urlencode current_customer_id= \
        --data-urlencode "distance_context=$CONTEXT" --data-urlencode baseline_value=0 \
        --data-urlencode baseline_date=2020-01-01 \
        --data-urlencode reason= "$@" "$BASE/wp-admin/admin-post.php"
}
# The old isolated fixture never configured shop defaults; the new UI requires them.
wp eval '$r=MF\VeLog\Common\Regional\ShopSettings::save_settings(array("distance_unit"=>"km","currency_code"=>"USD","region"=>"US"),0); if(is_wp_error($r))throw new RuntimeException($r->get_error_code());' --user=1 --path="$WP_DIR"
login veh_manager "$COOKIE"
curl -fsS --max-time 20 -b "$COOKIE" "$BASE/wp-admin/admin.php?page=velog-vehicles" > "$BODY"
CONTEXT=$(field distance_context)
NONCE=$(field mf_velog_vehicle_nonce)
REQUEST=$(field request_id)
grep -q '<select id="velog-vehicle-year" name="year">' "$BODY"
! grep -q 'name="preferred_odometer_unit"\|name="baseline_unit"' "$BODY"
grep -q 'value="1886"' "$BODY"
BEFORE=$(count_vehicles)
[[ $(post_vehicle "$COOKIE" create 0 0 "$NONCE" "$REQUEST") == 302 ]]
grep -qi 'Location: .*velog_notice=saved' "$HEADERS"
[[ $(count_vehicles) -eq $((BEFORE + 1)) ]]
ID=$(wp post list --post_type=mf_velog_vehicle --post_status=private --orderby=ID --order=DESC --posts_per_page=1 --field=ID --path="$WP_DIR")
[[ $(post_vehicle "$COOKIE" create 0 0 "$NONCE" "$REQUEST") == 302 ]]
[[ $(count_vehicles) -eq $((BEFORE + 1)) ]]
echo 'PASS: Authenticated HTTP Create succeeds and replay does not duplicate the vehicle.'
curl -fsS --max-time 20 -b "$COOKIE" "$BASE/wp-admin/admin.php?page=velog-vehicles&vehicle_id=$ID" > "$BODY"
grep -q 'HTTP-VEH001' "$BODY"
CONTEXT=$(field distance_context)
NONCE=$(field mf_velog_vehicle_nonce)
REQUEST=$(field request_id)
VERSION=$(field record_version)
[[ $(post_vehicle "$COOKIE" update "$ID" "$VERSION" "$NONCE" "$REQUEST" --data-urlencode model=Updated) == 302 ]]
grep -qi 'Location: .*velog_notice=saved' "$HEADERS"
[[ $(post_vehicle "$COOKIE" update "$ID" "$VERSION" "$NONCE" "$REQUEST" --data-urlencode model=Stale) == 302 ]]
grep -qi 'Location: .*velog_notice=stale_version' "$HEADERS"
ACTUAL=$(VELOG_TEST_VEHICLE_ID="$ID" wp eval '$r=MF\VeLog\Common\Storage\RecordRepository::get("mf_velog_vehicle",(int)getenv("VELOG_TEST_VEHICLE_ID"),new WP_User(1)); echo $r["fields"]["model"];' --path="$WP_DIR")
[[ "$ACTUAL" == Updated ]]
echo 'PASS: HTTP Update persists, stale replay leaves the winning edit intact.'
[[ $(post_vehicle "$COOKIE" update "$ID" 2 invalid "$REQUEST") == 403 ]]
[[ $(count_vehicles) -eq $((BEFORE + 1)) ]]
echo 'PASS: Invalid object/action nonce rejects the write.'
# The same authenticated form must reject stale system-unit context without a write.
OLD_CONTEXT=$CONTEXT
wp eval '$s=MF\VeLog\Common\Regional\ShopSettings::get_settings(); $s["distance_unit"]="mi"; $r=MF\VeLog\Common\Regional\ShopSettings::save_settings($s,$s["record_version"]); if(is_wp_error($r))throw new RuntimeException($r->get_error_code());' --user=1 --path="$WP_DIR"
[[ $(post_vehicle "$COOKIE" update "$ID" 2 "$NONCE" "$REQUEST" --data-urlencode model=WrongUnit) == 302 ]]
grep -qi 'Location: .*velog_notice=settings_changed' "$HEADERS"
curl -fsS --max-time 20 -b "$COOKIE" "$BASE/wp-admin/admin.php?page=velog-vehicles&vehicle_id=$ID" > "$BODY"
CONTEXT=$(field distance_context)
NONCE=$(field mf_velog_vehicle_nonce)
REQUEST=$(field request_id)
VERSION=$(field record_version)
[[ "$CONTEXT" != "$OLD_CONTEXT" ]]
# Forged legacy unit overrides cannot affect the authoritative mi interpretation.
[[ $(post_vehicle "$COOKIE" update "$ID" "$VERSION" "$NONCE" "$REQUEST" --data-urlencode model=Updated --data-urlencode baseline_value=1 --data-urlencode baseline_unit=km --data-urlencode preferred_odometer_unit=km) == 302 ]]
grep -qi 'Location: .*velog_notice=saved' "$HEADERS"
ACTUAL=$(VELOG_TEST_VEHICLE_ID="$ID" wp eval '$r=MF\VeLog\Common\Storage\RecordRepository::get("mf_velog_vehicle",(int)getenv("VELOG_TEST_VEHICLE_ID"),new WP_User(1)); echo $r["fields"]["baseline"]["canonical_mm"];' --path="$WP_DIR")
[[ "$ACTUAL" == 1609344 ]]
wp eval '$s=MF\VeLog\Common\Regional\ShopSettings::get_settings(); $s["distance_unit"]="km"; $r=MF\VeLog\Common\Regional\ShopSettings::save_settings($s,$s["record_version"]); if(is_wp_error($r))throw new RuntimeException($r->get_error_code());' --user=1 --path="$WP_DIR"
curl -fsS --max-time 20 -b "$COOKIE" "$BASE/wp-admin/admin.php?page=velog-vehicles&vehicle_id=$ID" > "$BODY"
grep -q 'value="1.609"' "$BODY"
CONTEXT=$(field distance_context)
NONCE=$(field mf_velog_vehicle_nonce)
REQUEST=$(field request_id)
VERSION=$(field record_version)
[[ $(post_vehicle "$COOKIE" update "$ID" "$VERSION" "$NONCE" "$REQUEST" --data-urlencode model=Updated --data-urlencode baseline_value=1.609) == 302 ]]
grep -qi 'Location: .*velog_notice=saved' "$HEADERS"
ACTUAL=$(VELOG_TEST_VEHICLE_ID="$ID" wp eval '$r=MF\VeLog\Common\Storage\RecordRepository::get("mf_velog_vehicle",(int)getenv("VELOG_TEST_VEHICLE_ID"),new WP_User(1)); echo $r["fields"]["baseline"]["canonical_mm"].":".$r["fields"]["baseline"]["unit"];' --path="$WP_DIR")
[[ "$ACTUAL" == 1609344:mi ]]
# Year is still validated server-side if a request bypasses the dropdown.
curl -fsS --max-time 20 -b "$COOKIE" "$BASE/wp-admin/admin.php?page=velog-vehicles&vehicle_id=$ID" > "$BODY"
CONTEXT=$(field distance_context)
NONCE=$(field mf_velog_vehicle_nonce)
REQUEST=$(field request_id)
VERSION=$(field record_version)
[[ $(post_vehicle "$COOKIE" update "$ID" "$VERSION" "$NONCE" "$REQUEST" --data-urlencode year=1885 --data-urlencode model=Updated --data-urlencode baseline_value=1.609) == 302 ]]
grep -qi 'Location: .*velog_notice=invalid_input' "$HEADERS"
echo 'PASS: Native Year select, global unit authority, stale settings rejection, forged overrides, converted display/no-drift save and invalid year.'
# Read the row archive form, including its own action-specific token.
curl -fsS --max-time 20 -b "$COOKIE" "$BASE/wp-admin/admin.php?page=velog-vehicles&term=HTTP-VEH001" > "$BODY"
php -r '$s=file_get_contents($argv[1]); if(!preg_match("~<form[^>]*class=\"velog-vehicle-state-form\"[^>]*>.*?</form>~s",$s,$m))exit(1); file_put_contents($argv[1],$m[0]);' "$BODY"
NONCE=$(field mf_velog_vehicle_nonce)
REQUEST=$(field request_id)
VERSION=$(field record_version)
[[ $(post_vehicle "$COOKIE" archive "$ID" "$VERSION" "$NONCE" "$REQUEST") == 302 ]]
grep -qi 'Location: .*velog_notice=saved' "$HEADERS"
curl -fsS --max-time 20 -b "$COOKIE" "$BASE/wp-admin/admin.php?page=velog-vehicles&state=archived&term=HTTP-VEH001" > "$BODY"
grep -q 'HTTP-VEH001' "$BODY"
php -r '$s=file_get_contents($argv[1]); if(!preg_match("~<form[^>]*class=\"velog-vehicle-state-form\"[^>]*>.*?</form>~s",$s,$m))exit(1); file_put_contents($argv[1],$m[0]);' "$BODY"
NONCE=$(field mf_velog_vehicle_nonce)
REQUEST=$(field request_id)
VERSION=$(field record_version)
[[ $(post_vehicle "$COOKIE" restore "$ID" "$VERSION" "$NONCE" "$REQUEST") == 302 ]]
grep -qi 'Location: .*velog_notice=saved' "$HEADERS"
echo 'PASS: HTTP Archive and Restore preserve the record and use distinct action tokens.'
# Missing/corrupt settings fail closed; restore the exact isolated option afterwards.
curl -fsS --max-time 20 -b "$COOKIE" "$BASE/wp-admin/admin.php?page=velog-vehicles&vehicle_id=$ID" > "$BODY"
NONCE=$(field mf_velog_vehicle_nonce)
REQUEST=$(field request_id)
VERSION=$(field record_version)
wp eval '$s=get_option("velog_settings"); file_put_contents(ABSPATH."../veh001-settings.json",wp_json_encode($s)); delete_option("velog_settings");' --path="$WP_DIR"
curl -fsS --max-time 20 -b "$COOKIE" "$BASE/wp-admin/admin.php?page=velog-vehicles&vehicle_id=$ID" > "$BODY"
! grep -q 'class="velog-vehicle-form"' "$BODY"
grep -q 'Configure the system distance unit' "$BODY"
[[ $(post_vehicle "$COOKIE" update "$ID" "$VERSION" "$NONCE" "$REQUEST") == 302 ]]
grep -qi 'Location: .*velog_notice=settings_required' "$HEADERS"
wp eval 'update_option("velog_settings","corrupt");' --path="$WP_DIR"
curl -fsS --max-time 20 -b "$COOKIE" "$BASE/wp-admin/admin.php?page=velog-vehicles&vehicle_id=$ID" > "$BODY"
! grep -q 'class="velog-vehicle-form"' "$BODY"
grep -q 'Configure the system distance unit' "$BODY"
wp eval 'update_option("velog_settings",json_decode(file_get_contents(ABSPATH."../veh001-settings.json"),true)); unlink(ABSPATH."../veh001-settings.json");' --path="$WP_DIR"
echo 'PASS: Missing and corrupt system settings disable vehicle editing with recovery guidance.'
login veh_technician "$TECH_COOKIE"
curl -fsS --max-time 20 -b "$TECH_COOKIE" "$BASE/wp-admin/admin.php?page=velog-vehicles&vehicle_id=$ID" > "$BODY"
grep -q 'HTTP-VEH001' "$BODY"
! grep -q 'name="vehicle_action"' "$BODY"
! grep -q 'PRIVATE-PHONE-VEH001\|private.vehicle@example.com' "$BODY"
TECH_SESSION=$(awk '$6 ~ /^wordpress_logged_in_/ {print $7}' "$TECH_COOKIE")
TECH_NONCE=$(VELOG_TEST_COOKIE="$TECH_SESSION" VELOG_TEST_VEHICLE_ID="$ID" wp eval 'wp_set_current_user(get_user_by("login","veh_technician")->ID); $_COOKIE[LOGGED_IN_COOKIE]=getenv("VELOG_TEST_COOKIE"); echo wp_create_nonce("mf_velog_vehicle_update_".getenv("VELOG_TEST_VEHICLE_ID"));' --path="$WP_DIR")
unset TECH_SESSION
[[ $(post_vehicle "$TECH_COOKIE" update "$ID" 4 "$TECH_NONCE" "$REQUEST" --data-urlencode model=Denied) == 403 ]]
ACTUAL=$(VELOG_TEST_VEHICLE_ID="$ID" wp eval '$r=MF\VeLog\Common\Storage\RecordRepository::get("mf_velog_vehicle",(int)getenv("VELOG_TEST_VEHICLE_ID"),new WP_User(1)); echo $r["fields"]["model"];' --path="$WP_DIR")
[[ "$ACTUAL" == Updated ]]
echo 'PASS: Authenticated technician has a read control but no mutation/contact UI; an actor-session nonce cannot authorize a write.'
login veh_denied "$WP_DIR/veh001-denied-cookie.txt"
STATUS=$(curl -sS --max-time 20 -b "$WP_DIR/veh001-denied-cookie.txt" -o "$BODY" -w '%{http_code}' "$BASE/wp-admin/admin.php?page=velog-vehicles")
[[ "$STATUS" == 403 ]]
! grep -q 'HTTP-VEH001' "$BODY"
STATUS=$(curl -sS --max-time 20 -o "$BODY" -w '%{http_code}' "$BASE/wp-admin/admin.php?page=velog-vehicles")
[[ "$STATUS" == 302 ]]
! grep -q 'HTTP-VEH001' "$BODY"
rm -f "$WP_DIR/veh001-denied-cookie.txt"
echo 'PASS: Subscriber and anonymous vehicle reads do not expose private records.'
# Pagination and invalid detail are checked through the real route, without claiming browser rendering.
curl -fsS --max-time 20 -b "$COOKIE" "$BASE/wp-admin/admin.php?page=velog-vehicles&term=VEH001-PAGE&paged=2" > "$BODY"
grep -q 'VEH001-PAGE-051' "$BODY"
! grep -q 'VEH001-PAGE-001' "$BODY"
curl -fsS --max-time 20 -b "$COOKIE" "$BASE/wp-admin/admin.php?page=velog-vehicles&vehicle_id=999999" > "$BODY"
grep -q 'notice-error' "$BODY"
! grep -q 'class="velog-vehicle-form"' "$BODY"
echo 'PASS: HTTP page two retains filters; missing detail fails safely without a create fallback.'
rm -f "$COOKIE" "$TECH_COOKIE" "$BODY" "$HEADERS"
