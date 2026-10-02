#!/bin/bash
set -e

echo "=== F-001 Concurrent CAS Verification ==="
# Setup a quick wp-cli environment or just run against the current local site if there is one.
# Since we need an isolated test, let's use the current directory assuming wp-cli works.
WP_CMD="wp --allow-root"

# Seed settings
$WP_CMD eval "
require_once ABSPATH . 'wp-admin/includes/plugin.php';
\MF\VeLog\Common\Regional\ShopSettings::save_settings(array(
    'distance_unit' => 'km',
    'currency_code' => 'VND'
), 0, 'dummy_nonce');
" || true

echo "Firing concurrent writers..."
# Writer A: tries to set version 1 to USD
$WP_CMD eval "
\MF\VeLog\Common\Regional\ShopSettings::save_settings(array(
    'distance_unit' => 'mi',
    'currency_code' => 'USD'
), 1, 'dummy_nonce');
echo 'Writer A Finished\n';
" &
PID_A=$!

# Writer B: tries to set version 1 to JPY
$WP_CMD eval "
\MF\VeLog\Common\Regional\ShopSettings::save_settings(array(
    'distance_unit' => 'km',
    'currency_code' => 'JPY'
), 1, 'dummy_nonce');
echo 'Writer B Finished\n';
" &
PID_B=$!

wait $PID_A
wait $PID_B

echo "Final Option Row in DB:"
$WP_CMD db query "SELECT option_value FROM wp_options WHERE option_name = 'velog_settings';" --skip-column-names

echo -e "\n=== F-003 Assets Verification ==="
$WP_CMD eval "
\$assets = new \MF\VeLog\Admin\Assets();
\$wp_styles = new \WP_Styles();
\$assets->enqueue_styles('velog_page_velog-settings');
if (wp_style_is('velog-admin', 'enqueued')) {
    echo 'PASS: velog-admin enqueued for settings page. URL: ' . \$wp_styles->registered['velog-admin']->src . \"\n\";
} else {
    echo 'FAIL: velog-admin not enqueued.\n';
}
"

echo -e "\n=== F-006 Menu Verification ==="
$WP_CMD eval "
\$admin = get_role('administrator');
\$manager = get_role('mf_velog_manager');
\$tech = get_role('mf_velog_technician');
\$sub = get_role('subscriber');

echo 'Capabilities granted in DB:\n';
echo 'Admin has mf_velog_read_records: ' . (\$admin->has_cap('mf_velog_read_records') ? 'yes' : 'no') . \"\n\";
echo 'Manager has mf_velog_read_records: ' . (\$manager->has_cap('mf_velog_read_records') ? 'yes' : 'no') . \"\n\";
echo 'Tech has mf_velog_read_records: ' . (\$tech->has_cap('mf_velog_read_records') ? 'yes' : 'no') . \"\n\";
echo 'Sub has mf_velog_read_records: ' . (\$sub->has_cap('mf_velog_read_records') ? 'yes' : 'no') . \"\n\";
"
