<?php
/**
 * V1: Rejection integration probe — runs inside a disposable WordPress site.
 *
 * Tests (all must produce no mutation):
 *   V1-A: Unknown schema type rejected.
 *   V1-B: Unknown field rejected.
 *   V1-C: PHP object value rejected.
 *   V1-D: Validator-failing value rejected.
 *   V1-E: Valid canonical values preserved unchanged.
 *   V1-F: Forged audit/version input rejected (audit is generated internally).
 *   V1-G: Attempt to create record without CORE-003 capability → forbidden.
 *
 * Exit code 0 = all assertions pass.
 * Exit code 1 = any assertion failed.
 *
 * @package MF\VeLog\Tests\Fixtures
 */

use MF\VeLog\Common\Storage\RecordSchema;
use MF\VeLog\Common\Storage\RecordRepository;

// Autoloader.
$autoload_candidates = array(
	defined( 'VELOG_PLUGIN_DIR' ) ? VELOG_PLUGIN_DIR . 'vendor/autoload.php' : '',
	dirname( __DIR__, 4 ) . '/vendor/autoload.php',
	WP_PLUGIN_DIR . '/velog/vendor/autoload.php',
);
foreach ( $autoload_candidates as $cand ) {
	if ( '' !== $cand && file_exists( $cand ) ) {
		require_once $cand;
		break;
	}
}

$GLOBALS['velog_failures'] = array();

/**
 * Assert helper — records failure without dying.
 *
 * @param bool   $condition Assertion condition.
 * @param string $label     Assertion label.
 * @param string $details   Optional failure details.
 */
function v1_assert( bool $condition, string $label, string $details = '' ): void {
	if ( $condition ) {
		echo "PASS: $label\n";
	} else {
		$msg = "FAIL: $label";
		if ( '' !== $details ) {
			$msg .= " ($details)";
		}
		echo "$msg\n";
		$GLOBALS['velog_failures'][] = $msg;
	}
}

// ─── Register minimal schemas ─────────────────────────────────────────────────

RecordSchema::reset_for_testing();

RecordSchema::register(
	'mf_velog_customer',
	array(
		'field_policies'  => array(
			'name'  => 'public',
			'score' => 'internal',
			'phone' => 'contact',
			'email' => 'contact',
		),
		'fields'          => array(
			'name'  => static fn ( mixed $v ) => is_string( $v ) && strlen( $v ) > 0 ? $v : null,
			'score' => static fn ( mixed $v ) => is_int( $v ) && $v >= 0 ? $v : null,
			'phone' => static fn ( mixed $v ) => is_string( $v ) && strlen( $v ) > 0 ? $v : null,
			'email' => static fn ( mixed $v ) => is_string( $v ) && strlen( $v ) > 0 ? $v : null,
		),
		'states'          => array( 'active', 'archived' ),
		'capability'      => 'mf_velog_manage_customers',
		'read_capability' => 'mf_velog_read_records',
	)
);

RecordSchema::register(
	'mf_velog_service',
	array(
		'capability'      => 'mf_velog_create_services',
		'read_capability' => 'mf_velog_read_records',
		'states'          => array( 'draft', 'finalized' ),
		'field_policies'  => array(
			'vehicle_visible' => 'public',
		),
		'fields'          => array(
			'vehicle_visible' => static fn ( mixed $v ) => is_bool( $v ) ? $v : null,
		),
		'context_builder' => static function ( array $envelope, \WP_User $actor ): array {
			unset( $actor );
			return array(
				'type'            => 'mf_velog_service',
				'state'           => $envelope['state'] ?? 'draft',
				'author'          => (int) ( $envelope['created_by'] ?? 0 ),
				'vehicle_visible' => (bool) ( $envelope['fields']['vehicle_visible'] ?? false ),
			);
		},
	)
);

RecordSchema::seal();

// ─── V1-A: Unknown schema type fails closed ──────────────────────────────────

try {
	RecordSchema::get( 'mf_velog_vehicle' ); // Not registered.
	v1_assert( false, 'V1-A: unregistered type should throw' );
} catch ( \InvalidArgumentException $e ) {
	v1_assert( true, 'V1-A: unregistered type throws InvalidArgumentException' );
}

// ─── V1-B: Unknown field rejected ───────────────────────────────────────────

$result = RecordSchema::validate_fields(
	'mf_velog_customer',
	array(
		'name'          => 'Alice',
		'_forged_field' => 'injection',
	)
);
v1_assert( $result instanceof WP_Error, 'V1-B: unknown field returns WP_Error' );
v1_assert( $result instanceof WP_Error && 'invalid_input' === $result->get_error_code(), 'V1-B: error code is invalid_input' );

// ─── V1-C: PHP object value rejected ────────────────────────────────────────

$result = RecordSchema::validate_fields(
	'mf_velog_customer',
	array(
		'name' => new stdClass(),
	)
);
v1_assert( $result instanceof WP_Error, 'V1-C: object value returns WP_Error' );

// ─── V1-D: Validator-failing value rejected ──────────────────────────────────

$result = RecordSchema::validate_fields(
	'mf_velog_customer',
	array(
		'score' => -99,
	)
);
v1_assert( $result instanceof WP_Error, 'V1-D: invalid score returns WP_Error' );

// ─── V1-E: Valid canonical values preserved ───────────────────────────────────

$result = RecordSchema::validate_fields(
	'mf_velog_customer',
	array(
		'name'  => 'Alice',
		'score' => 10,
	)
);
v1_assert( ! ( $result instanceof WP_Error ), 'V1-E: valid fields pass' );
v1_assert( isset( $result['name'] ) && 'Alice' === $result['name'], 'V1-E: name canonical value preserved' );
v1_assert( isset( $result['score'] ) && 10 === $result['score'], 'V1-E: score canonical value preserved' );

// ─── V1-F: Forged actor/version in fields rejected (schema has no such key) ──

$result = RecordSchema::validate_fields(
	'mf_velog_customer',
	array(
		'name'           => 'Bob',
		'actor_id'       => 9999,   // Forged — not in schema.
		'record_version' => 999,    // Forged — not in schema.
		'audit'          => array(), // Forged — not in schema.
	)
);
v1_assert( $result instanceof WP_Error, 'V1-F: forged actor/version/audit rejected' );

// ─── V1-G: Forbidden actor (no CORE-003 capability) ─────────────────────────

// Create an actor with no capabilities.
$no_cap_actor     = new WP_User();
$no_cap_actor->ID = 2;

$result = RecordRepository::get( 'mf_velog_customer', 1, $no_cap_actor );
v1_assert( $result instanceof WP_Error, 'V1-G: no-capability actor gets WP_Error on get' );

// ─── V1-H: Technician customer contact redaction (D1-F-001) ─────────────────

// Admin creates a customer with contacts.
$admin = new WP_User( 1 );
$admin->add_cap( 'mf_velog_manage_customers' );
$admin->add_cap( 'mf_velog_read_records' );
$admin->add_cap( 'mf_velog_read_customer_contacts' );

$cust = RecordRepository::create(
	'mf_velog_customer',
	array(
		'name'  => 'Secret Contact Customer',
		'phone' => '0988776655',
		'email' => 'secret@example.com',
	),
	$admin,
	'v1h-cust-' . wp_generate_uuid4()
);

if ( ! ( $cust instanceof WP_Error ) ) {
	// Create technician actor: has read_records, but NOT read_customer_contacts.
	$tech = new WP_User( 2 );
	$tech->add_cap( 'mf_velog_read_records' );

	$tech_read = RecordRepository::get( 'mf_velog_customer', $cust['id'], $tech );
	v1_assert( ! ( $tech_read instanceof WP_Error ), 'V1-H: technician can read customer record' );
	if ( ! ( $tech_read instanceof WP_Error ) ) {
		v1_assert( 'Secret Contact Customer' === $tech_read['fields']['name'], 'V1-H: customer name visible' );
		v1_assert( ! isset( $tech_read['fields']['phone'] ), 'V1-H: phone is redacted for technician' );
		v1_assert( ! isset( $tech_read['fields']['email'] ), 'V1-H: email is redacted for technician' );
	}
}

// ─── V1-I: Service visibility constraint (D1-F-001) ──────────────────────────

// Create service with vehicle_visible = false.
$admin->add_cap( 'mf_velog_create_services' );
$admin->add_cap( 'mf_velog_read_records' );
$invis_service = RecordRepository::create(
	'mf_velog_service',
	array( 'vehicle_visible' => false ),
	$admin,
	'v1i-invis-' . wp_generate_uuid4()
);
v1_assert( ! is_wp_error( $invis_service ), 'V1-I: create invisible service succeeds', is_wp_error( $invis_service ) ? $invis_service->get_error_message() : '' );

if ( ! ( $invis_service instanceof WP_Error ) ) {
	$read_invis = RecordRepository::get( 'mf_velog_service', $invis_service['id'], $admin );
	v1_assert( $read_invis instanceof WP_Error, 'V1-I: invisible service denied on get' );
	if ( $read_invis instanceof WP_Error ) {
		v1_assert( 'forbidden' === $read_invis->get_error_code(), 'V1-I: invisible service error is forbidden' );
	}
}

// ─── Summary ─────────────────────────────────────────────────────────────────

echo "\n=== V1 SUMMARY ===\n";
if ( empty( $GLOBALS['velog_failures'] ) ) {
	echo "ALL V1 ASSERTIONS PASS\n";
	exit( 0 );
} else {
	echo sprintf( "FAILURES: %d assertion(s) failed:\n", count( $GLOBALS['velog_failures'] ) );
	foreach ( $GLOBALS['velog_failures'] as $f ) {
		echo "  - $f\n";
	}
	exit( 1 );
}
