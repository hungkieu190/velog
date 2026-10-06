<?php
/**
 * Manager-only customer admin page and POST boundary.
 *
 * @package MF\VeLog\Admin
 */

// phpcs:disable Squiz.Commenting.FunctionComment.MissingParamTag,Generic.Files.LineLength.TooLong,WordPress.Security.NonceVerification

namespace MF\VeLog\Admin;

// phpcs:disable Squiz.Commenting.FunctionComment.MissingParamTag,Generic.Files.LineLength.TooLong

use MF\VeLog\Common\Customer\CustomerQuery;
use MF\VeLog\Common\Customer\CustomerService;
use MF\VeLog\Common\Storage\RecordRepository;
use MF\VeLog\Core\Loader;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Handles customer forms, list rendering, and mutations. */
final class CustomerPage {
	/**
	 * Customer mutation service.
	 *
	 * @var CustomerService
	 */
	private CustomerService $service;

	/**
	 * Customer query service.
	 *
	 * @var CustomerQuery
	 */
	private CustomerQuery $query;

	/** Initialize services. */
	public function __construct() {
		$this->service = new CustomerService();
		$this->query   = new CustomerQuery();
	}

	/** Register the customer POST handler. */
	public function register_hooks( Loader $loader ): void {
		$loader->add_action( 'admin_post_velog_customer_save', $this, 'handle_post' );
	}

	/** Render the manager-only customer page. */
	public function render(): void {
		if ( ! current_user_can( 'mf_velog_manage_customers' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'velog' ) );
		}
		$actor  = wp_get_current_user();
		$record = $this->requested_customer( $actor );
		$query  = $this->query->search( $this->query_input(), $actor );
		echo '<div class="wrap velog-admin-wrap velog-customers"><h1>' . esc_html__( 'Customers', 'velog' ) . '</h1>';
		$this->render_notice();
		$this->render_form( is_array( $record ) ? $record : null );
		$this->render_search();
		if ( is_wp_error( $query ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $query->get_error_message() ) . '</p></div>';
		} else {
			$this->render_bulk_form();
			( new CustomerListTable() )->render( $query );
			$this->render_pagination( $query );
		}
		echo '</div>';
	}

	/** Process create, update, archive, and restore requests. */
	public function handle_post(): void {
		if ( ! current_user_can( 'mf_velog_manage_customers' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'velog' ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- action selects the action-specific nonce checked below.
		$action = isset( $_POST['customer_action'] ) && is_string( $_POST['customer_action'] ) ? sanitize_key( wp_unslash( $_POST['customer_action'] ) ) : '';
		if ( ! in_array( $action, array( 'create', 'update', 'archive', 'restore', 'bulk' ), true ) ) {
			$this->redirect( 'invalid_input' );
		}
		check_admin_referer( 'velog_customer_' . $action, 'velog_customer_nonce' );
		$actor = wp_get_current_user();
		if ( 'bulk' === $action ) {
			$this->handle_bulk( $actor );
		}
		$request_id = wp_generate_uuid4();
		$id         = $this->posted_positive_int( 'customer_id' );
		$version    = $this->posted_positive_int( 'record_version' );
		if ( 'create' === $action ) {
			$result = $this->service->create( $this->posted_fields(), $actor, $request_id );
		} elseif ( 'update' === $action ) {
			$result = $this->service->update( $id, $version, $this->posted_fields(), $actor, $request_id );
		} elseif ( 'archive' === $action ) {
			$result = $this->service->archive( $id, $version, $actor, $request_id );
		} else {
			$result = $this->service->restore( $id, $version, $actor, $request_id );
		}
		$this->redirect( is_wp_error( $result ) ? (string) $result->get_error_code() : 'saved' );
	}

	/** Process bounded per-customer bulk transitions. */
	private function handle_bulk( \WP_User $actor ): never {
		$operation = isset( $_POST['bulk_action'] ) && is_string( $_POST['bulk_action'] ) ? sanitize_key( wp_unslash( $_POST['bulk_action'] ) ) : '';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each ID is strictly digit-validated below.
		$ids = isset( $_POST['customer_ids'] ) && is_array( $_POST['customer_ids'] ) ? wp_unslash( $_POST['customer_ids'] ) : array();
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each version is strictly digit-validated below.
		$versions = isset( $_POST['customer_versions'] ) && is_array( $_POST['customer_versions'] ) ? wp_unslash( $_POST['customer_versions'] ) : array();
		if ( ! in_array( $operation, array( 'archive', 'restore' ), true ) || count( $ids ) > 50 ) {
			$this->redirect( 'invalid_input' );
		}
		$success = 0;
		$failed  = 0;
		foreach ( $ids as $raw_id ) {
			if ( ! is_string( $raw_id ) || ! ctype_digit( $raw_id ) || ! isset( $versions[ $raw_id ] ) || ! is_string( $versions[ $raw_id ] ) || ! ctype_digit( $versions[ $raw_id ] ) ) {
				++$failed;
				continue;
			}
			$result = 'archive' === $operation ? $this->service->archive( (int) $raw_id, (int) $versions[ $raw_id ], $actor, wp_generate_uuid4() ) : $this->service->restore( (int) $raw_id, (int) $versions[ $raw_id ], $actor, wp_generate_uuid4() );
			is_wp_error( $result ) ? ++$failed : ++$success;
		}
		set_transient(
			'velog_customer_bulk_' . (int) $actor->ID,
			array(
				'success' => $success,
				'failed'  => $failed,
			),
			MINUTE_IN_SECONDS
		);
		$this->redirect( 'bulk_result' );
	}

	/**
	 * Render create or edit fields.
	 *
	 * @param array<string, mixed>|null $record Customer snapshot.
	 */
	private function render_form( ?array $record ): void {
		$fields  = $record['fields'] ?? array();
		$editing = null !== $record;
		$action  = $editing ? 'update' : 'create';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="velog-customer-form">';
		echo '<input type="hidden" name="action" value="velog_customer_save"><input type="hidden" name="customer_action" value="' . esc_attr( $action ) . '">';
		if ( $editing ) {
			echo '<input type="hidden" name="customer_id" value="' . esc_attr( (string) $record['id'] ) . '"><input type="hidden" name="record_version" value="' . esc_attr( (string) $record['record_version'] ) . '">';
		}
		wp_nonce_field( 'velog_customer_' . $action, 'velog_customer_nonce' );
		echo '<table class="form-table"><tr><th><label for="velog-customer-name">' . esc_html__( 'Name', 'velog' ) . '</label></th><td><input required maxlength="200" id="velog-customer-name" name="name" type="text" value="' . esc_attr( (string) ( $fields['name'] ?? '' ) ) . '"></td></tr>';
		echo '<tr><th><label for="velog-customer-phone">' . esc_html__( 'Phone', 'velog' ) . '</label></th><td><input maxlength="64" id="velog-customer-phone" name="phone" type="text" value="' . esc_attr( (string) ( $fields['phone'] ?? '' ) ) . '"></td></tr>';
		echo '<tr><th><label for="velog-customer-email">' . esc_html__( 'Email', 'velog' ) . '</label></th><td><input maxlength="254" id="velog-customer-email" name="email" type="email" value="' . esc_attr( (string) ( $fields['email'] ?? '' ) ) . '"></td></tr></table>';
		submit_button( $editing ? __( 'Update Customer', 'velog' ) : __( 'Create Customer', 'velog' ) );
		echo '</form>';
	}

	/** Render bounded search and state controls. */
	private function render_search(): void {
		$term  = isset( $_GET['term'] ) && is_string( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';
		$state = isset( $_GET['state'] ) && is_string( $_GET['state'] ) ? sanitize_key( wp_unslash( $_GET['state'] ) ) : '';
		echo '<form method="get" class="velog-customer-search"><input type="hidden" name="page" value="velog-customers">';
		echo '<label for="velog-customer-search">' . esc_html__( 'Search customers', 'velog' ) . '</label> ';
		echo '<input id="velog-customer-search" name="term" maxlength="100" value="' . esc_attr( $term ) . '"> ';
		echo '<select name="state"><option value="">' . esc_html__( 'All states', 'velog' ) . '</option>';
		foreach ( array( 'active', 'archived' ) as $option ) {
			echo '<option value="' . esc_attr( $option ) . '"' . selected( $state, $option, false ) . '>' . esc_html( ucfirst( $option ) ) . '</option>';
		}
		echo '</select> ';
		submit_button( __( 'Search', 'velog' ), 'secondary', 'submit', false );
		echo '</form>';
	}

	/** Render the independent bulk form used by row checkboxes. */
	private function render_bulk_form(): void {
		echo '<form id="velog-customer-bulk" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="velog_customer_save"><input type="hidden" name="customer_action" value="bulk">';
		wp_nonce_field( 'velog_customer_bulk', 'velog_customer_nonce' );
		echo '<label for="velog-customer-bulk-action">' . esc_html__( 'Bulk action', 'velog' ) . '</label> ';
		echo '<select id="velog-customer-bulk-action" name="bulk_action"><option value="archive">' . esc_html__( 'Archive', 'velog' ) . '</option><option value="restore">' . esc_html__( 'Restore', 'velog' ) . '</option></select> ';
		submit_button( __( 'Apply', 'velog' ), 'secondary', 'submit', false );
		echo '</form>';
	}

	/**
	 * Render stable native pagination.
	 *
	 * @param array<string, mixed> $query Customer query result.
	 */
	private function render_pagination( array $query ): void {
		$total_pages = max( 1, (int) ceil( (int) ( $query['total'] ?? 0 ) / 50 ) );
		if ( $total_pages <= 1 ) {
			return;
		}
		$links = paginate_links(
			array(
				'base'    => add_query_arg( 'paged', '%#%' ),
				'current' => (int) ( $query['page'] ?? 1 ),
				'total'   => $total_pages,
			)
		);
		echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post( (string) $links ) . '</div></div>';
	}

	/**
	 * Return strictly scalar posted fields.
	 *
	 * @return array<string, mixed>
	 */
	private function posted_fields(): array {
		$fields = array();
		foreach ( array( 'name', 'phone', 'email' ) as $key ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- strict domain validators reject changed or unsafe values after nonce verification.
			$fields[ $key ] = isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : array();
		}
		return $fields;
	}

	/** Return a positive posted integer or zero. */
	private function posted_positive_int( string $key ): int {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- exact digit validation follows after nonce verification.
		$value = isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
		return ctype_digit( $value ) && (int) $value > 0 ? (int) $value : 0;
	}

	/**
	 * Build allowlisted query input.
	 *
	 * @return array<string, mixed>
	 */
	private function query_input(): array {
		$input = array();
		foreach ( array( 'term', 'state', 'sort', 'direction' ) as $key ) {
			if ( isset( $_GET[ $key ] ) && is_string( $_GET[ $key ] ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter.
				$input[ $key ] = sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
			}
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination.
		$paged = isset( $_GET['paged'] ) && is_string( $_GET['paged'] ) ? sanitize_text_field( wp_unslash( $_GET['paged'] ) ) : '';
		if ( ctype_digit( $paged ) ) {
			$input['page'] = max( 1, (int) $paged );
		}
		return $input;
	}

	/**
	 * Load an explicitly requested customer.
	 *
	 * @return array<string, mixed>|null
	 */
	private function requested_customer( \WP_User $actor ): ?array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only edit selection.
		$id = isset( $_GET['customer_id'] ) && is_string( $_GET['customer_id'] ) ? sanitize_text_field( wp_unslash( $_GET['customer_id'] ) ) : '';
		if ( ! ctype_digit( $id ) ) {
			return null;
		}
		$result = RecordRepository::get( 'mf_velog_customer', (int) $id, $actor );
		return is_wp_error( $result ) ? null : $result;
	}

	/** Render a fixed allowlisted notice code. */
	private function render_notice(): void {
		$code = isset( $_GET['velog_notice'] ) && is_string( $_GET['velog_notice'] ) ? sanitize_key( wp_unslash( $_GET['velog_notice'] ) ) : '';
		if ( 'bulk_result' === $code ) {
			$key     = 'velog_customer_bulk_' . get_current_user_id();
			$summary = get_transient( $key );
			delete_transient( $key );
			if ( is_array( $summary ) ) {
				/* translators: 1: successful customer transitions, 2: failed customer transitions. */
				echo '<div class="notice notice-info"><p>' . esc_html( sprintf( __( '%1$d succeeded; %2$d failed.', 'velog' ), (int) ( $summary['success'] ?? 0 ), (int) ( $summary['failed'] ?? 0 ) ) ) . '</p></div>';
			}
			return;
		}
		$map = array(
			'saved'               => __( 'Customer saved.', 'velog' ),
			'stale_version'       => __( 'The customer changed. Reload and try again.', 'velog' ),
			'active_vehicle_link' => __( 'Reassign or archive active vehicles first.', 'velog' ),
			'invalid_input'       => __( 'Customer data is invalid.', 'velog' ),
		);
		if ( isset( $map[ $code ] ) ) {
			echo '<div class="notice notice-' . ( 'saved' === $code ? 'success' : 'error' ) . '"><p>' . esc_html( $map[ $code ] ) . '</p></div>';
		}
	}

	/** Redirect to the fixed customer page with an allowlisted notice code. */
	private function redirect( string $code ): never {
		$allowed = array( 'saved', 'bulk_result', 'stale_version', 'active_vehicle_link', 'invalid_input', 'forbidden', 'storage_unavailable' );
		$notice  = in_array( $code, $allowed, true ) ? $code : 'invalid_input';
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         => 'velog-customers',
					'velog_notice' => $notice,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}

// phpcs:enable Squiz.Commenting.FunctionComment.MissingParamTag,Generic.Files.LineLength.TooLong,WordPress.Security.NonceVerification
