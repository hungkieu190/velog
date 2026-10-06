<?php
/**
 * RecordSchema — static registry of trusted schema definitions.
 *
 * Satisfies contract §Public Common API:
 *   "RecordSchema::register() is callable only from trusted bootstrap code.
 *    The registry is closed before repository use. An unregistered type fails closed."
 *
 * @package MF\VeLog\Common\Storage
 */

namespace MF\VeLog\Common\Storage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages schema definitions for VeLog private post types.
 *
 * Each definition may contain:
 *   - 'fields'      array<string, callable> — field name → scalar validator returning
 *                   the canonical value or null on rejection.
 *   - 'projections' string[] — allowed projection meta keys (must be prefixed).
 *   - 'states'      string[] — allowed state values for this type.
 *   - 'capability'  string   — CORE-003 capability required for object read/write.
 *   - 'unique_keys' array<string, string[]> — named uniqueness rule → field names.
 *
 * No definition is inferred from request data. The registry is closed (sealed)
 * by calling RecordSchema::seal() before any repository operation. Registrations
 * attempted after sealing throw \LogicException.
 *
 * @since 0.2.0
 */
final class RecordSchema {

	/**
	 * Accepted private CPT slugs (CORE-003 canonical list).
	 *
	 * @var string[]
	 */
	public const ALLOWED_TYPES = array(
		'mf_velog_customer',
		'mf_velog_vehicle',
		'mf_velog_service',
		'mf_velog_reminder',
	);

	/**
	 * The authoritative meta key used to store the versioned envelope.
	 *
	 * @var string
	 */
	public const META_KEY = '_mf_velog_record';

	/**
	 * The non-autoloaded options row used as the InnoDB row-lock target.
	 *
	 * @var string
	 */
	public const WRITE_LOCK_OPTION = 'mf_velog_write_lock';

	/**
	 * Maximum bounded page size for queries.
	 *
	 * @var int
	 */
	public const MAX_PAGE_SIZE = 50;

	/**
	 * Registered schema definitions, keyed by post type slug.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private static array $registry = array();

	/**
	 * Whether the registry has been sealed against further registrations.
	 *
	 * @var bool
	 */
	private static bool $sealed = false;

	/**
	 * Registers a schema definition for one private CPT.
	 *
	 * Must be called only from trusted bootstrap code (e.g., Plugin::define_core_hooks())
	 * before the registry is sealed.
	 *
	 * @param string               $post_type  One of self::ALLOWED_TYPES.
	 * @param array<string, mixed> $definition Schema definition array.
	 * @throws \LogicException If the registry is sealed or type is invalid.
	 */
	public static function register( string $post_type, array $definition ): void {
		if ( self::$sealed ) {
			throw new \LogicException(
				sprintf( 'RecordSchema: registry is sealed; cannot register type "%s".', $post_type )
			);
		}

		if ( ! in_array( $post_type, self::ALLOWED_TYPES, true ) ) {
			throw new \LogicException(
				sprintf( 'RecordSchema: "%s" is not an allowed post type.', $post_type )
			);
		}

		// Validate definition structure.
		$definition = self::normalize_definition( $definition );

		self::$registry[ $post_type ] = $definition;
	}

	/**
	 * Seals the registry against further registrations.
	 *
	 * Must be called before any repository read or write operation.
	 * Idempotent: additional calls after the first are ignored.
	 */
	public static function seal(): void {
		self::$sealed = true;
	}

	/**
	 * Returns true if the registry is sealed.
	 *
	 * @return bool
	 */
	public static function is_sealed(): bool {
		return self::$sealed;
	}

	/**
	 * Returns the definition for a registered post type.
	 *
	 * @param string $post_type CPT slug.
	 * @return array<string, mixed>
	 * @throws \InvalidArgumentException If the type is not registered (fails closed).
	 */
	public static function get( string $post_type ): array {
		if ( ! isset( self::$registry[ $post_type ] ) ) {
			throw new \InvalidArgumentException(
				sprintf( 'RecordSchema: type "%s" is not registered; failing closed.', $post_type )
			);
		}

		return self::$registry[ $post_type ];
	}

	/**
	 * Returns true if a post type is registered.
	 *
	 * @param string $post_type CPT slug.
	 * @return bool
	 */
	public static function is_registered( string $post_type ): bool {
		return isset( self::$registry[ $post_type ] );
	}

	/**
	 * Validates and normalises a raw definition array.
	 *
	 * @param array<string, mixed> $definition Raw input.
	 * @return array<string, mixed> Normalised definition.
	 * @throws \InvalidArgumentException On structural errors.
	 */
	private static function normalize_definition( array $definition ): array {
		// 'fields': map of field_name => validator callable.
		if ( ! isset( $definition['fields'] ) || ! is_array( $definition['fields'] ) ) {
			$definition['fields'] = array();
		}
		foreach ( $definition['fields'] as $field_name => $validator ) {
			if ( ! is_string( $field_name ) || '' === $field_name ) {
				throw new \InvalidArgumentException( 'RecordSchema: field names must be non-empty strings.' );
			}
			if ( ! is_callable( $validator ) ) {
				throw new \InvalidArgumentException(
					sprintf( 'RecordSchema: validator for field "%s" must be callable.', $field_name )
				);
			}
		}

		// Validated field exposure policies (D1-F-018: public, internal, or contact).
		$field_policies = $definition['field_policies'] ?? array();
		if ( ! is_array( $field_policies ) ) {
			throw new \InvalidArgumentException( 'RecordSchema: field_policies must be an array.' );
		}
		$allowed_exposures = array( 'public', 'internal', 'contact' );
		foreach ( array_keys( $definition['fields'] ) as $field_name ) {
			if ( ! isset( $field_policies[ $field_name ] ) ) {
				throw new \InvalidArgumentException(
					sprintf( 'RecordSchema: Field "%s" has no declared exposure policy.', $field_name )
				);
			}
			if ( ! in_array( $field_policies[ $field_name ], $allowed_exposures, true ) ) {
				throw new \InvalidArgumentException(
					sprintf(
						'RecordSchema: Field "%s" has invalid exposure policy "%s". Allowed: %s.',
						$field_name,
						$field_policies[ $field_name ],
						implode( ', ', $allowed_exposures )
					)
				);
			}
		}
		$definition['field_policies'] = $field_policies;

		// 'projections': list of allowed projection meta keys.
		if ( ! isset( $definition['projections'] ) || ! is_array( $definition['projections'] ) ) {
			$definition['projections'] = array();
		}
		if ( ! isset( $definition['projection_builders'] ) || ! is_array( $definition['projection_builders'] ) ) {
			$definition['projection_builders'] = array();
		}
		foreach ( $definition['projection_builders'] as $projection_key => $builder ) {
			if ( ! is_string( $projection_key ) || ! str_starts_with( $projection_key, '_mf_velog_' ) ) {
				throw new \InvalidArgumentException( 'RecordSchema: projection keys must use the _mf_velog_ prefix.' );
			}
			if ( ! is_callable( $builder ) ) {
				throw new \InvalidArgumentException( 'RecordSchema: projection builders must be callable.' );
			}
		}

		// 'states': allowed state values.
		if ( ! isset( $definition['states'] ) || ! is_array( $definition['states'] ) ) {
			$definition['states'] = array();
		}

		// 'capability': CORE-003 cap required for all operations on this type.
		if ( ! isset( $definition['capability'] ) || ! is_string( $definition['capability'] ) ) {
			$definition['capability'] = 'mf_velog_read_records';
		}

		// 'unique_keys': named uniqueness constraints.
		if ( ! isset( $definition['unique_keys'] ) || ! is_array( $definition['unique_keys'] ) ) {
			$definition['unique_keys'] = array();
		}

		// Optional context builder callable receiving envelope and actor.
		if ( isset( $definition['context_builder'] ) && ! is_callable( $definition['context_builder'] ) ) {
			throw new \InvalidArgumentException( 'RecordSchema: context_builder must be callable.' );
		}

		// 'contact_fields': auto-populated from field_policies with 'contact' class.
		$contact_fields = array();
		foreach ( $field_policies as $fn => $policy ) {
			if ( 'contact' === $policy ) {
				$contact_fields[] = $fn;
			}
		}
		if ( isset( $definition['contact_fields'] ) && is_array( $definition['contact_fields'] ) ) {
			$merged         = array_merge( $contact_fields, $definition['contact_fields'] );
			$contact_fields = array_values( array_unique( $merged ) );
		}
		$definition['contact_fields'] = $contact_fields;

		return $definition;
	}

	/**
	 * Returns all possible projection keys registered for a post type.
	 *
	 * Includes fixed system projections (_mf_velog_state, and _mf_velog_vehicle_visible for service)
	 * plus any custom projections defined in the schema.
	 *
	 * @param string $post_type CPT slug.
	 * @return string[] Unique list of meta keys.
	 */
	public static function get_registered_projection_keys( string $post_type ): array {
		$definition = self::get( $post_type );
		$keys       = array( '_mf_velog_state' );
		if ( 'mf_velog_service' === $post_type ) {
			$keys[] = '_mf_velog_vehicle_visible';
		}
		$custom = $definition['projections'] ?? array();
		foreach ( $custom as $k ) {
			if ( is_string( $k ) && '' !== $k ) {
				$keys[] = $k;
			}
		}
		foreach ( array_keys( $definition['projection_builders'] ?? array() ) as $projection_key ) {
			$keys[] = (string) $projection_key;
		}
		return array_values( array_unique( $keys ) );
	}

	/**
	 * Derives expected active projection meta keys and scalar string values for an envelope.
	 *
	 * Satisfies contract §3 and Architect Review 5 Constraint 1:
	 *  - _mf_velog_state is derived from $envelope['state'].
	 *  - _mf_velog_vehicle_visible is derived for services ('1' or '0').
	 *  - Registered custom projections are included ONLY if present and non-null in fields.
	 *  - Absent/null projections are omitted from the returned map (absent projections have no row).
	 *
	 * @param string               $post_type CPT slug.
	 * @param array<string, mixed> $envelope  Authoritative envelope.
	 * @return array<string, string> Active meta_key => meta_value map.
	 */
	public static function derive_projections( string $post_type, array $envelope ): array {
		$definition  = self::get( $post_type );
		$projections = array();

		// Universal state projection.
		if ( isset( $envelope['state'] ) && is_scalar( $envelope['state'] ) ) {
			$projections['_mf_velog_state'] = (string) $envelope['state'];
		}

		// Service vehicle visibility projection.
		if ( 'mf_velog_service' === $post_type ) {
			$is_vis = false;
			if ( isset( $envelope['fields'] ) && is_array( $envelope['fields'] ) ) {
				$is_vis = true === ( $envelope['fields']['vehicle_visible'] ?? false );
			}
			$projections['_mf_velog_vehicle_visible'] = $is_vis ? '1' : '0';
		}

		// Custom registered projections.
		$custom = $definition['projections'] ?? array();
		$fields = (array) ( $envelope['fields'] ?? array() );
		foreach ( $custom as $proj_key ) {
			if (
				! is_string( $proj_key )
				|| '_mf_velog_state' === $proj_key
				|| '_mf_velog_vehicle_visible' === $proj_key
			) {
				continue;
			}
			if ( array_key_exists( $proj_key, $fields ) && null !== $fields[ $proj_key ] ) {
				$projections[ $proj_key ] = (string) $fields[ $proj_key ];
			}
		}
		foreach ( $definition['projection_builders'] ?? array() as $proj_key => $builder ) {
			$value = $builder( $fields, $envelope );
			if ( null !== $value ) {
				$projections[ $proj_key ] = (string) $value;
			}
		}

		return $projections;
	}

	/**
	 * Builds authorization context for an object.
	 *
	 * @param string               $post_type CPT slug.
	 * @param array<string, mixed> $envelope  Decoded envelope.
	 * @param \WP_User             $actor     Authenticated actor.
	 * @return array<string, mixed> Context for AccessPolicy.
	 */
	public static function build_context( string $post_type, array $envelope, \WP_User $actor ): array {
		$definition = self::get( $post_type );
		if ( isset( $definition['context_builder'] ) && is_callable( $definition['context_builder'] ) ) {
			return ( $definition['context_builder'] )( $envelope, $actor );
		}

		$policy_type = str_starts_with( $post_type, 'mf_' ) ? $post_type : 'mf_' . $post_type;
		$context     = array(
			'type'   => $policy_type,
			'state'  => $envelope['state'] ?? 'draft',
			'author' => (int) ( $envelope['created_by'] ?? 0 ),
		);

		if ( 'mf_velog_service' === $policy_type ) {
			$context['vehicle_visible'] = (bool) ( $envelope['fields']['vehicle_visible'] ?? true );
		}

		return $context;
	}

	/**
	 * Registers default core schemas for the 4 private post types and seals registry.
	 *
	 * Idempotent. Called during plugin bootstrap on init hook.
	 */
	public static function register_core_schemas(): void {
		if ( self::$sealed ) {
			return;
		}

		// Customer schema.
		if ( ! isset( self::$registry['mf_velog_customer'] ) ) {
			self::register(
				'mf_velog_customer',
				array(
					'capability'          => 'mf_velog_manage_customers',
					'read_capability'     => 'mf_velog_read_records',
					'states'              => array( 'active', 'archived' ),
					'field_policies'      => array(
						'name'  => 'public',
						'phone' => 'contact',
						'email' => 'contact',
					),
					'fields'              => array(
						'name'  => array( \MF\VeLog\Common\Customer\CustomerService::class, 'validate_name' ),
						'phone' => array( \MF\VeLog\Common\Customer\CustomerService::class, 'validate_phone' ),
						'email' => array( \MF\VeLog\Common\Customer\CustomerService::class, 'validate_email' ),
					),
					// phpcs:disable Generic.Files.LineLength.TooLong
					'projection_builders' => array(
						'_mf_velog_customer_name'  => array( \MF\VeLog\Common\Customer\CustomerService::class, 'project_name' ),
						'_mf_velog_customer_phone' => array( \MF\VeLog\Common\Customer\CustomerService::class, 'project_phone' ),
						'_mf_velog_customer_email' => array( \MF\VeLog\Common\Customer\CustomerService::class, 'project_email' ),
					),
					// phpcs:enable Generic.Files.LineLength.TooLong
				)
			);
		}

		// Vehicle schema.
		if ( ! isset( self::$registry['mf_velog_vehicle'] ) ) {
			self::register(
				'mf_velog_vehicle',
				array(
					'capability'          => 'mf_velog_manage_vehicles',
					'read_capability'     => 'mf_velog_read_records',
					'states'              => array( 'active', 'archived' ),
					'field_policies'      => array(
						'current_customer_id' => 'internal',
					),
					// phpcs:disable Generic.Files.LineLength.TooLong
					'fields'              => array(
						'current_customer_id' => array( \MF\VeLog\Common\Customer\CustomerRelations::class, 'validate_customer_id' ),
					),
					// phpcs:enable Generic.Files.LineLength.TooLong
					'projection_builders' => array(
						'_mf_velog_current_customer_id' => array(
							\MF\VeLog\Common\Customer\CustomerRelations::class,
							'project_customer_id',
						),
					),
				)
			);
		}

		// Service schema.
		if ( ! isset( self::$registry['mf_velog_service'] ) ) {
			self::register(
				'mf_velog_service',
				array(
					'capability'      => 'mf_velog_create_services',
					'read_capability' => 'mf_velog_read_records',
					'states'          => array( 'draft', 'finalized' ),
					'fields'          => array(),
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
		}

		// Reminder schema.
		if ( ! isset( self::$registry['mf_velog_reminder'] ) ) {
			self::register(
				'mf_velog_reminder',
				array(
					'capability'      => 'mf_velog_manage_reminders',
					'read_capability' => 'mf_velog_read_records',
					'states'          => array( 'pending', 'sent', 'dismissed' ),
					'fields'          => array(),
				)
			);
		}

		self::seal();
	}

	/**
	 * Resets the registry to its empty, unsealed state.
	 *
	 * FOR TESTING ONLY. Must not be called in production bootstrap paths.
	 *
	 * @internal
	 */
	public static function reset_for_testing(): void {
		self::$registry = array();
		self::$sealed   = false;
	}

	/**
	 * Validates a set of domain fields against a registered schema.
	 *
	 * Returns an associative array of accepted canonical values, or a
	 * WP_Error for the first invalid field.
	 *
	 * Rejects:
	 *  - Any key not present in the schema definition.
	 *  - Any PHP object value (strict scalar/array enforcement).
	 *  - Validator returning null.
	 *
	 * @param string               $post_type CPT slug.
	 * @param array<string, mixed> $fields    Input fields from caller.
	 * @return array<string, mixed>|\WP_Error Canonical fields or error.
	 */
	public static function validate_fields( string $post_type, array $fields ): array|\WP_Error {
		$definition    = self::get( $post_type ); // Throws on unregistered type.
		$schema_fields = $definition['fields'];
		$canonical     = array();

		foreach ( $fields as $key => $value ) {
			if ( ! is_string( $key ) ) {
				return new \WP_Error( 'invalid_input', 'Field keys must be strings.' );
			}
			if ( ! array_key_exists( $key, $schema_fields ) ) {
				return new \WP_Error(
					'invalid_input',
					sprintf( 'Unknown field "%s" for type "%s".', $key, $post_type )
				);
			}
			// Reject PHP objects.
			if ( is_object( $value ) ) {
				return new \WP_Error(
					'invalid_input',
					sprintf( 'Field "%s" must not be an object.', $key )
				);
			}
			$validated = ( $schema_fields[ $key ] )( $value );
			if ( null === $validated ) {
				return new \WP_Error(
					'invalid_input',
					sprintf( 'Field "%s" failed validation.', $key )
				);
			}
			$canonical[ $key ] = $validated;
		}

		return $canonical;
	}
}
