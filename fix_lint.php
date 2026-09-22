<?php
$f = 'src/Core/Capabilities.php';
$c = file_get_contents($f);
$c = str_replace(
"		global \$wp_roles;\n\t\tif ( ! isset( \$wp_roles ) ) {\n\t\t\t\$wp_roles = new \WP_Roles();\n\t\t}",
"		if ( ! function_exists( 'wp_roles' ) ) {\n\t\t\trequire_once ABSPATH . 'wp-includes/capabilities.php';\n\t\t}\n\t\twp_roles();",
$c
);
file_put_contents($f, $c);

$f = 'src/Core/Activator.php';
$c = file_get_contents($f);
$c = str_replace("esc_html__( 'VeLog capability installation failed. A role collision or setup error occurred. Activation aborted.', 'velog' ),", "esc_html__( 'VeLog capability install failed.', 'velog' ),", $c);
file_put_contents($f, $c);

$f = 'src/Core/PostTypes.php';
$c = file_get_contents($f);
$c = str_replace("// \"Supply a complete native post capability array whose edit/read/delete/publish/create primitives resolve to do_not_allow; set map_meta_cap false.\"", "// Supply a complete native post capability array.", $c);
file_put_contents($f, $c);

$f = 'src/Common/AccessPolicy.php';
$c = file_get_contents($f);
$c = str_replace("// \"if technician mutates service: require service type, draft state and actor == author\"", "// Technician mutation rules.", $c);
file_put_contents($f, $c);
