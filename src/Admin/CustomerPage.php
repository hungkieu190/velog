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
		$loader->add_action( 'admin_post_velog_customer_search', $this, 'handle_search' );
	}

	/** Render the manager-only customer page. */
	public function render(): void {
		if ( ! current_user_can( 'mf_velog_manage_customers' ) || ! current_user_can( 'mf_velog_read_customer_contacts' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'velog' ), '', array( 'response' => 403 ) );
		}
		$actor  = wp_get_current_user();
		$record = $this->requested_customer( $actor );
		$input  = $this->query_input( $actor );
		$query  = is_wp_error( $input ) ? $input : $this->query->search( $input, $actor );
		echo '<div class="wrap velog-admin-wrap velog-customers"><h1>' . esc_html__( 'Customers', 'velog' ) . '</h1>';
		$this->render_notice();
		$this->render_form( is_array( $record ) ? $record : null );
		$this->render_search( is_wp_error( $input ) ? array() : $input );
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
		if ( ! current_user_can( 'mf_velog_manage_customers' ) || ! current_user_can( 'mf_velog_read_customer_contacts' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'velog' ), '', array( 'response' => 403 ) );
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
		echo '<table class="form-table" role="presentation"><tr><th scope="row"><label for="velog-customer-name">' . esc_html__( 'Name', 'velog' ) . '</label></th><td><input required maxlength="200" class="regular-text" id="velog-customer-name" name="name" type="text" value="' . esc_attr( (string) ( $fields['name'] ?? '' ) ) . '"></td></tr>';
		echo '<tr><th scope="row"><label for="velog-customer-phone">' . esc_html__( 'Phone', 'velog' ) . '</label></th><td><input maxlength="64" class="regular-text" id="velog-customer-phone" name="phone" type="text" value="' . esc_attr( (string) ( $fields['phone'] ?? '' ) ) . '"></td></tr>';
		echo '<tr><th scope="row"><label for="velog-customer-email">' . esc_html__( 'Email', 'velog' ) . '</label></th><td><input maxlength="254" class="regular-text" id="velog-customer-email" name="email" type="email" value="' . esc_attr( (string) ( $fields['email'] ?? '' ) ) . '"></td></tr></table>';
		submit_button( $editing ? __( 'Update Customer', 'velog' ) : __( 'Create Customer', 'velog' ) );
		echo '</form>';
	}

	/**
	 * Render authorized POST search without placing contact terms in URLs.
	 *
	 * @param array<string, mixed> $input Current scoped criteria.
	 */
	private function render_search( array $input ): void {
		$term  = $input['term'] ?? '';
		$state = $input['state'] ?? '';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="velog-customer-search">';
		echo '<input type="hidden" name="action" value="velog_customer_search">';
		wp_nonce_field( 'velog_customer_search', 'velog_customer_nonce' );
		echo '<label for="velog-customer-search">' . esc_html__( 'Search customers', 'velog' ) . '</label> ';
		echo '<input type="text" id="velog-customer-search" name="term" maxlength="100" value="' . esc_attr( (string) $term ) . '"> ';
		echo '<label class="screen-reader-text" for="velog-customer-state">' . esc_html__( 'Customer state', 'velog' ) . '</label>';
		echo '<select id="velog-customer-state" name="state"><option value="">' . esc_html__( 'All states', 'velog' ) . '</option>';
		foreach ( array( 'active', 'archived' ) as $option ) {
			echo '<option value="' . esc_attr( $option ) . '"' . selected( $state, $option, false ) . '>' . esc_html( ucfirst( $option ) ) . '</option>';
		}
		echo '</select> ';
		submit_button( __( 'Search', 'velog' ), 'secondary', 'submit', false );
		echo '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=velog-customers' ) ) . '">' . esc_html__( 'Clear search', 'velog' ) . '</a>';
		echo '</form>';
	}

	/** Validate private search criteria and redirect with an actor-scoped token. */
	public function handle_search(): void {
		if ( ! current_user_can( 'mf_velog_manage_customers' ) || ! current_user_can( 'mf_velog_read_customer_contacts' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'velog' ), '', array( 'response' => 403 ) );
		}
		$nonce = isset( $_POST['velog_customer_nonce'] ) && is_string( $_POST['velog_customer_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['velog_customer_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'velog_customer_search' ) ) {
			wp_die( esc_html__( 'Invalid security token.', 'velog' ), '', array( 'response' => 403 ) );
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- exact UTF-8/plain-text domain validation follows, without changing the term.
		$term     = isset( $_POST['term'] ) && is_string( $_POST['term'] ) ? wp_unslash( $_POST['term'] ) : array();
		$state    = isset( $_POST['state'] ) && is_string( $_POST['state'] ) ? sanitize_key( wp_unslash( $_POST['state'] ) ) : array();
		$criteria = CustomerQuery::normalize_args(
			array(
				'term'  => $term,
				'state' => $state,
			)
		);
		if ( is_wp_error( $criteria ) ) {
			$this->redirect( 'invalid_input' );
		}
		$token = wp_generate_uuid4();
		$key   = 'velog_customer_search_' . get_current_user_id() . '_' . $token;
		if ( ! set_transient( $key, $criteria, 15 * MINUTE_IN_SECONDS ) ) {
			$this->redirect( 'storage_unavailable' );
		}
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'            => 'velog-customers',
					'customer_search' => $token,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
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
		$links  = paginate_links(
			array(
				'base'    => add_query_arg( 'paged', '%#%', remove_query_arg( array( 'term', 'customer_id', 'velog_notice' ) ) ),
				'current' => (int) ( $query['page'] ?? 1 ),
				'total'   => $total_pages,
			)
		);
		$markup = new \WP_HTML_Tag_Processor( (string) $links );
		while ( $markup->next_tag() ) {
			if ( 'A' === $markup->get_tag() ) {
				$markup->add_class( 'button' );
			} elseif ( $markup->has_class( 'current' ) ) {
				$markup->add_class( 'tablenav-pages-navspan' );
			}
		}
		echo '<nav class="tablenav" aria-label="' . esc_attr__( 'Customer pagination', 'velog' ) . '"><div class="tablenav-pages">' . wp_kses_post( $markup->get_updated_html() ) . '</div></nav>';
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
	 * @param \WP_User $actor Authenticated manager.
	 * @return array<string, mixed>|\WP_Error
	 */
	private function query_input( \WP_User $actor ): array|\WP_Error {
		$input = array();
		if ( isset( $_GET['term'] ) ) {
			return new \WP_Error( 'invalid_input', __( 'Use the private search form.', 'velog' ) );
		}
		if ( isset( $_GET['customer_search'] ) ) {
			$token = is_string( $_GET['customer_search'] ) ? sanitize_text_field( wp_unslash( $_GET['customer_search'] ) ) : '';
			if ( ! preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D', $token ) ) {
				return new \WP_Error( 'invalid_input', __( 'Invalid search reference.', 'velog' ) );
			}
			$stored = get_transient( 'velog_customer_search_' . (int) $actor->ID . '_' . $token );
			if ( ! is_array( $stored ) ) {
				return new \WP_Error( 'invalid_input', __( 'Search expired or is unavailable for this account. Submit the search again.', 'velog' ) );
			}
			$input = $stored;
		} else {
			foreach ( array( 'state', 'sort', 'direction' ) as $key ) {
				if ( isset( $_GET[ $key ] ) ) {
					if ( ! is_string( $_GET[ $key ] ) ) {
						return new \WP_Error( 'invalid_input', __( 'Invalid list filter.', 'velog' ) );
					}
					$input[ $key ] = sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
				}
			}
		}
		if ( isset( $_GET['paged'] ) ) {
			$paged = is_string( $_GET['paged'] ) ? sanitize_text_field( wp_unslash( $_GET['paged'] ) ) : '';
			if ( ! ctype_digit( $paged ) || strlen( $paged ) > 7 ) {
				return new \WP_Error( 'invalid_input', __( 'Invalid page number.', 'velog' ) );
			}
			$input['page'] = (int) $paged;
		}
		return CustomerQuery::normalize_args( $input );
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
			'forbidden'           => __( 'You do not have permission to save this customer.', 'velog' ),
			'storage_unavailable' => __( 'Customer could not be saved because storage is unavailable. Contact your administrator.', 'velog' ),
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
