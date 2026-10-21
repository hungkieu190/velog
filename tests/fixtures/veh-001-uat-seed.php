<?php
/**
 * Explicit, resumable VEH-001 UAT preparation. Plan mode is read-only.
 *
 * @package MF\VeLog\Tests\Fixtures
 */

use MF\VeLog\Common\Storage\RecordRepository;
use MF\VeLog\Common\Storage\WriteCoordinator;
use MF\VeLog\Common\Storage\WriteUnit;
use MF\VeLog\Common\Vehicle\VehicleQuery;

if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 1 );
}

/** Refuse unsafe preparation without exposing identity credentials. */
function mf_velog_vehicle_seed_require( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

/** Persist a private manifest atomically before/after external operations. */
function mf_velog_vehicle_seed_manifest( string $path, array $manifest ): void {
	$temp = $path . '.pending';
	mf_velog_vehicle_seed_require( ! is_link( $temp ), 'Manifest temporary path must not be a symlink.' );
	$handle = fopen( $temp, 'xb' );
	mf_velog_vehicle_seed_require( false !== $handle, 'Manifest temporary path already exists or is unavailable.' );
	try {
		mf_velog_vehicle_seed_require( chmod( $temp, 0600 ), 'Cannot protect manifest.' );
		$body = wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		mf_velog_vehicle_seed_require( false !== $body && strlen( $body ) === fwrite( $handle, $body ), 'Cannot write manifest.' );
	} catch ( Throwable $exception ) {
		unlink( $temp );
		throw $exception;
	} finally {
		fclose( $handle );
	}
	if ( ! rename( $temp, $path ) ) {
		unlink( $temp );
		throw new RuntimeException( 'Cannot commit manifest.' );
	}
}

/** Stable create IDs make an interrupted manifest update recoverable. */
function mf_velog_vehicle_seed_request( string $batch, string $label ): string {
	$hash = hash( 'sha256', $batch . ':' . $label );
	return substr( $hash, 0, 8 ) . '-' . substr( $hash, 8, 4 ) . '-4' . substr( $hash, 13, 3 )
		. '-a' . substr( $hash, 17, 3 ) . '-' . substr( $hash, 20, 12 );
}

/** Owned snapshots must stay exact; manual UAT changes are never reset. */
function mf_velog_vehicle_seed_verify( array $manifest, WP_User $actor ): array {
	foreach ( $manifest['records'] as $record ) {
		$snapshot = RecordRepository::get( $record['type'], $record['id'], $actor );
		mf_velog_vehicle_seed_require( ! is_wp_error( $snapshot ), 'Owned fixture cannot be read.' );
		mf_velog_vehicle_seed_require( hash( 'sha256', serialize( $snapshot ) ) === $record['digest'], 'Owned fixture changed; refuse to reset UAT results.' );
	}
	$user = get_user_by( 'login', 'velog_vehicle_uat_denied' );
	mf_velog_vehicle_seed_require( false !== $user && $user->ID === $manifest['subscriber_id']
		&& array( 'subscriber' ) === $user->roles
		&& $manifest['batch'] === get_user_meta( $user->ID, 'mf_velog_vehicle_uat_batch', true ), 'Owned subscriber drift or collision.' );
	$credentials = json_decode( (string) file_get_contents( $manifest['credentials_path'] ), true );
	mf_velog_vehicle_seed_require( is_array( $credentials ) && is_string( $credentials['password'] ?? null )
		&& wp_check_password( $credentials['password'], $user->user_pass, $user->ID ), 'Subscriber credentials drift; no reset is allowed.' );
	$page1 = ( new VehicleQuery() )->search( array( 'term' => 'VELOG-VEH-UAT-PAGE' ), $actor );
	$page2 = ( new VehicleQuery() )->search( array( 'term' => 'VELOG-VEH-UAT-PAGE', 'page' => 2 ), $actor );
	mf_velog_vehicle_seed_require( ! is_wp_error( $page1 ) && ! is_wp_error( $page2 )
		&& 51 === $page1['total'] && 50 === count( $page1['items'] ) && 1 === count( $page2['items'] ), 'Pagination fixture verification failed.' );
	return array( 'mode' => 'verify', 'verified' => true, 'records' => $manifest['records'], 'subscriber_id' => $user->ID, 'pagination' => array( 50, 1 ) );
}

/** Execute a bounded CLI preparation on the explicit expected site. */
function mf_velog_vehicle_seed_run( array $arguments ): array {
	$mode = $arguments[0] ?? 'plan';
	$expected_site = $arguments[1] ?? '';
	$manifest_path = $arguments[2] ?? '';
	$backup = $arguments[3] ?? '';
	$root = realpath( ABSPATH );
	$isolated = is_string( $root ) && str_starts_with( $root, '/tmp/velog-product-smoke.' );
	$live = '/home/ecommercelife/Local Sites/velog/app/public' === $root && 'https://velog.local' === home_url();
	mf_velog_vehicle_seed_require( in_array( $mode, array( 'plan', 'apply', 'verify' ), true ) && ( $isolated || $live )
		&& home_url() === $expected_site, 'Explicit approved site/mode boundary failed.' );
	mf_velog_vehicle_seed_require( current_user_can( 'manage_options' ) && current_user_can( 'mf_velog_manage_vehicles' )
		&& current_user_can( 'mf_velog_manage_customers' ), 'Administrator preparation actor required.' );
	$private = realpath( dirname( $manifest_path ) );
	mf_velog_vehicle_seed_require( is_string( $private ) && is_string( $root ) && ! str_starts_with( $private . '/', $root . '/' )
		&& 0700 === ( fileperms( $private ) & 0777 ) && ! is_link( $manifest_path ), 'Private 0700 directory outside public root required.' );
	$manifest_path = $private . '/' . basename( $manifest_path );
	$credentials_path = $manifest_path . '.credentials.json';
	mf_velog_vehicle_seed_require( ! is_link( $credentials_path ), 'Credentials path must not be a symlink.' );
	foreach ( array( 'velog_uat_manager' => 'mf_velog_manager', 'velog_uat_technician' => 'mf_velog_technician' ) as $login => $role ) {
		$user = get_user_by( 'login', $login );
		mf_velog_vehicle_seed_require( false !== $user && in_array( $role, $user->roles, true ), 'Existing CUST-001 staff fixtures required; no roles/passwords will be reset.' );
	}
	$actor = wp_get_current_user();
	$manifest = null;
	if ( file_exists( $manifest_path ) ) {
		mf_velog_vehicle_seed_require( 0600 === ( fileperms( $manifest_path ) & 0777 ), 'Manifest must be private.' );
		$manifest = json_decode( (string) file_get_contents( $manifest_path ), true );
		mf_velog_vehicle_seed_require( is_array( $manifest ) && ( $manifest['task'] ?? '' ) === 'VEH-001-UAT'
			&& ( $manifest['site'] ?? '' ) === $expected_site && ( $manifest['actor'] ?? 0 ) === $actor->ID, 'Foreign manifest refused.' );
		if ( 'complete' === ( $manifest['state'] ?? '' ) ) {
			return mf_velog_vehicle_seed_verify( $manifest, $actor );
		}
	}
	if ( null === $manifest ) {
		$query = ( new VehicleQuery() )->search( array( 'term' => 'VELOG-VEH-UAT-PAGE', 'state' => '' ), $actor );
		mf_velog_vehicle_seed_require( ! is_wp_error( $query ) && 0 === $query['total']
			&& false === username_exists( 'velog_vehicle_uat_denied' ) && ! file_exists( $credentials_path ), 'Non-owned UAT data/account/credentials collision.' );
	}
	if ( 'plan' === $mode ) {
		return array( 'mode' => 'plan', 'writes' => false, 'existing_staff_reused' => true,
			'new_customers' => 3, 'new_vehicles' => 51, 'new_subscriber' => 1,
			'live_backup_required' => $live, 'pending_manifest' => null !== $manifest );
	}
	mf_velog_vehicle_seed_require( 'apply' === $mode, 'No complete manifest exists for verification.' );
	if ( $live ) {
		$backup_real = realpath( $backup );
		mf_velog_vehicle_seed_require( is_string( $backup_real ) && ! str_starts_with( $backup_real, $root . '/' )
			&& is_file( $backup_real ) && filesize( $backup_real ) > 0 && 0600 === ( fileperms( $backup_real ) & 0777 ), 'Private pre-write database backup required.' );
	}
	if ( null === $manifest ) {
		$manifest = array( 'task' => 'VEH-001-UAT', 'site' => $expected_site, 'actor' => $actor->ID,
			'batch' => wp_generate_uuid4(), 'state' => 'pending', 'records' => array(), 'credentials_path' => $credentials_path );
		mf_velog_vehicle_seed_manifest( $manifest_path, $manifest );
	}
	// Block all mail from this preparation, including third-party notifications.
	add_filter( 'pre_wp_mail', '__return_true' );
	try {
		if ( ! file_exists( $credentials_path ) ) {
			$handle = fopen( $credentials_path, 'xb' );
			mf_velog_vehicle_seed_require( false !== $handle, 'Cannot allocate private credential file.' );
			try {
				mf_velog_vehicle_seed_require( chmod( $credentials_path, 0600 ), 'Cannot protect credential file.' );
				$body = wp_json_encode( array( 'login' => 'velog_vehicle_uat_denied', 'password' => wp_generate_password( 32, true, true ) ) );
				mf_velog_vehicle_seed_require( false !== $body && strlen( $body ) === fwrite( $handle, $body ), 'Cannot write private credentials.' );
			} finally {
				fclose( $handle );
			}
		}
		mf_velog_vehicle_seed_require( 0600 === ( fileperms( $credentials_path ) & 0777 ), 'Credentials must be private.' );
		$credentials = json_decode( (string) file_get_contents( $credentials_path ), true );
		mf_velog_vehicle_seed_require( is_array( $credentials ) && is_string( $credentials['password'] ?? null ), 'Invalid private credential file.' );
		$subscriber = get_user_by( 'login', 'velog_vehicle_uat_denied' );
		if ( false === $subscriber ) {
			$subscriber_id = wp_insert_user( array( 'user_login' => 'velog_vehicle_uat_denied', 'user_pass' => $credentials['password'],
				'role' => 'subscriber', 'meta_input' => array( 'mf_velog_vehicle_uat_batch' => $manifest['batch'] ) ) );
			mf_velog_vehicle_seed_require( ! is_wp_error( $subscriber_id ), 'Cannot create the dedicated denied-role account.' );
			$subscriber = new WP_User( $subscriber_id );
		}
		mf_velog_vehicle_seed_require( array( 'subscriber' ) === $subscriber->roles
			&& $manifest['batch'] === get_user_meta( $subscriber->ID, 'mf_velog_vehicle_uat_batch', true )
			&& wp_check_password( $credentials['password'], $subscriber->user_pass, $subscriber->ID ), 'Subscriber collision or drift; no account reset is allowed.' );
		$manifest['subscriber_id'] = $subscriber->ID;
		mf_velog_vehicle_seed_manifest( $manifest_path, $manifest );
		$result = WriteCoordinator::run( $actor, static function ( WriteUnit $unit ) use ( $manifest ): array|WP_Error {
			$records = array();
			$owners = array();
			foreach ( array( 'A', 'B', 'Archived' ) as $label ) {
				$record = $unit->create( 'mf_velog_customer', array( 'name' => 'Vehicle UAT Owner ' . $label ), mf_velog_vehicle_seed_request( $manifest['batch'], 'owner-' . $label ) );
				if ( is_wp_error( $record ) ) {
					return $record;
				}
				if ( 'Archived' === $label && 'active' === $record['state'] ) {
					$record = $unit->save( 'mf_velog_customer', $record['id'], 1, array(), mf_velog_vehicle_seed_request( $manifest['batch'], 'archive-owner' ), 'uat_fixture', 'archived' );
					if ( is_wp_error( $record ) ) {
						return $record;
					}
				}
				if ( $record['record_version'] !== ( 'Archived' === $label ? 2 : 1 )
					|| $record['state'] !== ( 'Archived' === $label ? 'archived' : 'active' )
					|| array( 'name' => 'Vehicle UAT Owner ' . $label ) !== $record['fields'] ) {
					return new WP_Error( 'fixture_drift', 'Pending customer fixture changed; refuse to adopt.' );
				}
				$owners[ $label ] = $record['id'];
				$records[] = array( 'type' => 'mf_velog_customer', 'id' => $record['id'], 'label' => $label, 'digest' => hash( 'sha256', serialize( $record ) ) );
			}
			for ( $i = 1; $i <= 51; ++$i ) {
				$record = $unit->create( 'mf_velog_vehicle', array( 'vin' => sprintf( 'VELOG-VEH-UAT-PAGE-%03d', $i ), 'current_customer_id' => $owners['A'], 'preferred_odometer_unit' => 'km' ), mf_velog_vehicle_seed_request( $manifest['batch'], 'vehicle-' . $i ) );
				if ( is_wp_error( $record ) ) {
					return $record;
				}
				if ( 1 !== $record['record_version'] || 'active' !== $record['state']
					|| array( 'vin' => sprintf( 'VELOG-VEH-UAT-PAGE-%03d', $i ), 'current_customer_id' => $owners['A'], 'preferred_odometer_unit' => 'km' ) !== $record['fields'] ) {
					return new WP_Error( 'fixture_drift', 'Pending vehicle fixture changed; refuse to adopt.' );
				}
				$records[] = array( 'type' => 'mf_velog_vehicle', 'id' => $record['id'], 'label' => sprintf( 'PAGE-%03d', $i ), 'digest' => hash( 'sha256', serialize( $record ) ) );
			}
			return array( 'records' => $records, 'owners' => $owners );
		} );
		mf_velog_vehicle_seed_require( ! is_wp_error( $result ), 'Fixture transaction failed; no customer/vehicle batch was committed.' );
		$manifest = array_merge( $manifest, $result, array( 'state' => 'complete' ) );
		mf_velog_vehicle_seed_manifest( $manifest_path, $manifest );
		return mf_velog_vehicle_seed_verify( $manifest, $actor );
	} finally {
		remove_filter( 'pre_wp_mail', '__return_true' );
	}
}

$mf_velog_seed_lock = null;
try {
    if ( 'apply' === ( $args[0] ?? 'plan' ) ) {
        $private = realpath( dirname( $args[2] ?? '' ) );
        $root = realpath( ABSPATH );
        mf_velog_vehicle_seed_require( is_string( $private ) && is_string( $root )
            && ! str_starts_with( $private . '/', $root . '/' )
            && 0700 === ( fileperms( $private ) & 0777 ), 'Private directory required before locking.' );
        $lock_path = $private . '/' . basename( $args[2] ) . '.lock';
        mf_velog_vehicle_seed_require( ! is_link( $lock_path ), 'Seed lock must not be a symlink.' );
        $mf_velog_seed_lock = fopen( $lock_path, 'c' );
        mf_velog_vehicle_seed_require( false !== $mf_velog_seed_lock && chmod( $lock_path, 0600 )
            && flock( $mf_velog_seed_lock, LOCK_EX | LOCK_NB ), 'Another preparation is running.' );
    }
    WP_CLI::line( wp_json_encode( mf_velog_vehicle_seed_run( $args ?? array() ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
} catch ( Throwable $exception ) {
    WP_CLI::error( $exception->getMessage() );
} finally {
    if ( is_resource( $mf_velog_seed_lock ) ) {
        flock( $mf_velog_seed_lock, LOCK_UN );
        fclose( $mf_velog_seed_lock );
    }
}
