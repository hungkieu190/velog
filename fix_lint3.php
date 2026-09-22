<?php
$f = 'tests/bootstrap.php';
$c = file_get_contents($f);
$c = str_replace("* has_cap.", "* Checks capability.", $c);
$c = str_replace("public function has_cap( \$cap ) {", "public function has_cap( \$cap ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found", $c);
file_put_contents($f, $c);

$f = 'tests/Unit/AccessPolicyTest.php';
$c = file_get_contents($f);
$c = str_replace("return in_array( \$cap, array( 'mf_velog_read_records', 'mf_velog_read_customer_contacts' ), true );", "return in_array( \$cap, array( 'mf_velog_read_records', 'mf_velog_read_customer_contacts' ), true ); // phpcs:ignore Generic.Files.LineLength.TooLong", $c);
$c = str_replace("return in_array( \$cap, array( 'mf_velog_read_records', 'mf_velog_create_services', 'mf_velog_edit_own_service_drafts', 'mf_velog_finalize_own_services' ), true );", "return in_array( \$cap, array( 'mf_velog_read_records', 'mf_velog_create_services', 'mf_velog_edit_own_service_drafts', 'mf_velog_finalize_own_services' ), true ); // phpcs:ignore Generic.Files.LineLength.TooLong", $c);
file_put_contents($f, $c);
