#!/bin/bash
# Disposable-only provisioning regression checks. Never accepts a live site.
set -euo pipefail
wp_dir=$1
case "$wp_dir" in
    /tmp/velog-product-smoke.*/wp) ;;
    *) echo 'FAIL: disposable fixture boundary required'; exit 1 ;;
esac
fixture="$wp_dir/wp-content/plugins/velog/tests/fixtures/cust-001-uat-seed.php"
private_dir="$(dirname "$wp_dir")/uat-private"
mkdir -m 0700 "$private_dir"
credentials="$private_dir/credentials.json"
site=$(wp option get home --path="$wp_dir")
seed() { wp eval-file "$fixture" "$1" "$site" "$credentials" --user=admin --path="$wp_dir"; }
expect_refusal() {
    if "$@" > "$private_dir/refusal.log" 2>&1; then
        echo 'FAIL: expected provisioning refusal'; exit 1
    fi
    echo 'PASS: provisioning refused the negative control'
}
wp eval 'echo hash("sha256", serialize(array(get_users(array("fields"=>"ID")),get_option("mf_velog_uat_cust001_v1",null))));' --path="$wp_dir" > "$private_dir/before"
seed plan > "$private_dir/plan.json"
[ ! -e "$credentials" ]
wp eval 'echo hash("sha256", serialize(array(get_users(array("fields"=>"ID")),get_option("mf_velog_uat_cust001_v1",null))));' --path="$wp_dir" > "$private_dir/after"
cmp "$private_dir/before" "$private_dir/after"
echo 'PASS: plan created no credentials, identities or manifest'
expect_refusal wp eval-file "$fixture" apply https://wrong.example "$credentials" --user=admin --path="$wp_dir"
expect_refusal wp eval-file "$fixture" apply "$site" "$credentials" --user=cust_technician --path="$wp_dir"
wp eval '$id=wp_insert_user(array("user_login"=>"velog_uat_manager","user_email"=>"collision@example.com","user_pass"=>wp_generate_password(32),"role"=>"subscriber")); if(is_wp_error($id)){WP_CLI::error("Collision setup failed");} echo $id;' --path="$wp_dir" > "$private_dir/collision-id"
expect_refusal seed apply
[ ! -e "$credentials" ]
wp user delete "$(cat "$private_dir/collision-id")" --yes --path="$wp_dir" > /dev/null
seed apply > "$private_dir/apply.json"
seed verify > "$private_dir/verify.json"
sha256sum "$credentials" > "$private_dir/credentials-before"
wp option get mf_velog_uat_cust001_v1 --format=json --path="$wp_dir" > "$private_dir/manifest-before"
seed apply > "$private_dir/reapply.json"
sha256sum "$credentials" > "$private_dir/credentials-after"
wp option get mf_velog_uat_cust001_v1 --format=json --path="$wp_dir" > "$private_dir/manifest-after"
cmp "$private_dir/credentials-before" "$private_dir/credentials-after"
cmp "$private_dir/manifest-before" "$private_dir/manifest-after"
php -r '$c=json_decode(file_get_contents($argv[1]),true);$m=file_get_contents($argv[2]);$r=json_decode(file_get_contents($argv[3]),true);foreach($c["accounts"] as $a){if(str_contains($m,$a["password"])){exit(1);}}if(!$r["preparation_verified"]||$r["pagination"]!==[50,1]||count($r["customer_ids"])!==51||$r["mail_attempts_blocked"]!==0){exit(1);}' "$credentials" "$private_dir/manifest-after" "$private_dir/verify.json"
echo 'PASS: apply/verify/reapply; 52 customers, linked vehicle, permissions, pagination, no credential reset or manifest secrets'
wp user set-role velog_uat_technician subscriber --path="$wp_dir" > /dev/null
expect_refusal seed apply
[ "$(wp user get velog_uat_technician --field=roles --path="$wp_dir")" = subscriber ]
wp user set-role velog_uat_technician mf_velog_technician --path="$wp_dir" > /dev/null
seed verify > /dev/null
echo 'PASS: account drift refused without resetting roles'
# Credentials and all disposable records are removed by the parent runner trap.
