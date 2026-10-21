#!/bin/bash
# Exercise the future live-preparation helper only on an owned disposable site.
set -euo pipefail
trap 'echo "Vehicle UAT seed regression failed at line $LINENO" >&2' ERR
wp_dir=$1
[[ "$wp_dir" == /tmp/velog-product-smoke.*/wp ]] || exit 1
fixture="$wp_dir/wp-content/plugins/velog/tests/fixtures/veh-001-uat-seed.php"
private="$(dirname "$wp_dir")/veh-uat-private"
mkdir -m 0700 "$private"
manifest="$private/manifest.json"
site=$(wp option get home --path="$wp_dir")
for pair in velog_uat_manager:mf_velog_manager velog_uat_technician:mf_velog_technician; do
    login=${pair%%:*}
    role=${pair#*:}
    password=$(wp eval 'echo wp_generate_password(32);' --path="$wp_dir")
    wp user create "$login" "$login@example.com" --role="$role" --user_pass="$password" --path="$wp_dir" >/dev/null
    unset password
done
seed() { wp eval-file "$fixture" "$1" "$site" "$manifest" --user=admin --path="$wp_dir"; }
refusal() {
    if "$@" > "$private/refusal.txt" 2>&1; then
        echo 'FAIL: seed accepted a negative control'; exit 1
    fi
    echo 'PASS: vehicle preparation refused the negative control'
}
# Compare every existing authoritative record and user credential/role row, excluding only later owned IDs.
wp eval '$w=$GLOBALS["wpdb"];echo serialize(array($w->get_results("SELECT ID,user_login,user_pass FROM {$w->users} ORDER BY ID",ARRAY_A),$w->get_results($w->prepare("SELECT user_id,meta_key,meta_value FROM {$w->usermeta} WHERE meta_key=%s ORDER BY umeta_id",$w->prefix."capabilities"),ARRAY_A),$w->get_results("SELECT post_id,meta_key,meta_value FROM {$w->postmeta} WHERE meta_key IN (\"_mf_velog_record\",\"_mf_velog_state\") ORDER BY meta_id",ARRAY_A)));' --path="$wp_dir" > "$private/before"
seed plan > "$private/plan.json"
[[ ! -e "$manifest" && ! -e "$manifest.credentials.json" && ! -e "$manifest.lock" ]]
refusal wp eval-file "$fixture" apply https://wrong.example "$manifest" --user=admin --path="$wp_dir"
refusal wp eval-file "$fixture" apply "$site" "$manifest" --user=veh_technician --path="$wp_dir"
seed apply > "$private/apply.json"
seed verify > "$private/verify.json"
sha256sum "$manifest" "$manifest.credentials.json" > "$private/hash-before"
seed apply > "$private/reapply.json"
sha256sum "$manifest" "$manifest.credentials.json" > "$private/hash-after"
cmp "$private/hash-before" "$private/hash-after"
VELOG_TEST_VEH_MANIFEST="$manifest" wp eval '$m=json_decode(file_get_contents(getenv("VELOG_TEST_VEH_MANIFEST")),true);$ids=array_column($m["records"],"id");$owned=implode(",",array_map("intval",$ids));$u=(int)$m["subscriber_id"];$w=$GLOBALS["wpdb"];echo serialize(array($w->get_results("SELECT ID,user_login,user_pass FROM {$w->users} WHERE ID!=$u ORDER BY ID",ARRAY_A),$w->get_results($w->prepare("SELECT user_id,meta_key,meta_value FROM {$w->usermeta} WHERE meta_key=%s AND user_id!=%d ORDER BY umeta_id",$w->prefix."capabilities",$u),ARRAY_A),$w->get_results("SELECT post_id,meta_key,meta_value FROM {$w->postmeta} WHERE post_id NOT IN ($owned) AND meta_key IN (\"_mf_velog_record\",\"_mf_velog_state\") ORDER BY meta_id",ARRAY_A)));' --path="$wp_dir" > "$private/after"
cmp "$private/before" "$private/after"
php -r '$m=json_decode(file_get_contents($argv[1]),true);$c=json_decode(file_get_contents($argv[2]),true);$v=json_decode(file_get_contents($argv[3]),true);if(count($m["records"])!==54||$v["pagination"]!==[50,1]||str_contains(file_get_contents($argv[1]),$c["password"]))exit(1);' "$manifest" "$manifest.credentials.json" "$private/verify.json"
echo 'PASS: vehicle plan is read-only; apply/verify/reapply creates exactly 3 customers, 51 vehicles and a subscriber, preserves existing identities/records, and keeps credentials private.'
ID=$(php -r '$m=json_decode(file_get_contents($argv[1]),true);echo $m["records"][3]["id"];' "$manifest")
VELOG_TEST_FIXTURE_ID="$ID" wp eval 'wp_set_current_user(1);$r=(new MF\VeLog\Common\Vehicle\VehicleService())->update((int)getenv("VELOG_TEST_FIXTURE_ID"),1,array("make"=>"Tester changed"),wp_get_current_user(),wp_generate_uuid4());if(is_wp_error($r))WP_CLI::error("Drift setup failed");' --path="$wp_dir"
refusal seed apply
CURRENT=$(VELOG_TEST_FIXTURE_ID="$ID" wp eval '$r=MF\VeLog\Common\Storage\RecordRepository::get("mf_velog_vehicle",(int)getenv("VELOG_TEST_FIXTURE_ID"),new WP_User(1));echo $r["fields"]["make"];' --path="$wp_dir")
[[ "$CURRENT" == 'Tester changed' ]]
echo 'PASS: manual fixture drift is refused without resetting observations.'
# All private credentials, manifests and test records are removed by the outer runner trap.
