<?php
/**
 * Customer list presentation contract.
 *
 * @package MF\VeLog\Admin
 */

// phpcs:disable Squiz.Commenting.FunctionComment.MissingParamTag,Generic.Files.LineLength.TooLong

namespace MF\VeLog\Admin;

// phpcs:disable Squiz.Commenting.FunctionComment.MissingParamTag,Generic.Files.LineLength.TooLong

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Renders escaped customer rows from authorized snapshots. */
final class CustomerListTable {
	/**
	 * Render the customer list table.
	 *
	 * @param array<string, mixed> $result Authorized query result.
	 */
	public function render( array $result ): void {
		echo '<table class="widefat striped velog-customer-table"><thead><tr>';
		echo '<th class="check-column">' . esc_html__( 'Select', 'velog' ) . '</th>';
		echo '<th>' . esc_html__( 'Name', 'velog' ) . '</th><th>' . esc_html__( 'Phone', 'velog' ) . '</th>';
		echo '<th>' . esc_html__( 'Email', 'velog' ) . '</th><th>' . esc_html__( 'State', 'velog' ) . '</th>';
		echo '<th>' . esc_html__( 'Actions', 'velog' ) . '</th></tr></thead><tbody>';
		if ( empty( $result['items'] ) ) {
			echo '<tr><td colspan="6">' . esc_html__( 'No customers found.', 'velog' ) . '</td></tr>';
		}
		foreach ( $result['items'] ?? array() as $item ) {
			$this->render_row( $item );
		}
		echo '</tbody></table>';
	}

	/**
	 * Render one escaped manager-only row.
	 *
	 * @param array<string, mixed> $item Customer snapshot.
	 */
	private function render_row( array $item ): void {
		$fields = $item['fields'] ?? array();
		echo '<tr><td class="check-column"><input form="velog-customer-bulk" type="checkbox" name="customer_ids[]" value="' . esc_attr( (string) $item['id'] ) . '">';
		echo '<input form="velog-customer-bulk" type="hidden" name="customer_versions[' . esc_attr( (string) $item['id'] ) . ']" value="' . esc_attr( (string) $item['record_version'] ) . '"></td>';
		echo '<td>' . esc_html( (string) ( $fields['name'] ?? '' ) ) . '</td>';
		echo '<td>' . esc_html( (string) ( $fields['phone'] ?? '' ) ) . '</td>';
		echo '<td>' . esc_html( (string) ( $fields['email'] ?? '' ) ) . '</td>';
		echo '<td>' . esc_html( (string) ( $item['state'] ?? '' ) ) . '</td><td>';
		$url = add_query_arg(
			array(
				'page'        => 'velog-customers',
				'customer_id' => (int) $item['id'],
			),
			admin_url( 'admin.php' )
		);
		echo '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Edit', 'velog' ) . '</a> ';
		$this->render_state_form( $item );
		echo '</td></tr>';
	}

	/**
	 * Render a nonce-protected state form.
	 *
	 * @param array<string, mixed> $item Customer snapshot.
	 */
	private function render_state_form( array $item ): void {
		$action = 'active' === ( $item['state'] ?? '' ) ? 'archive' : 'restore';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="velog-customer-state-form">';
		echo '<input type="hidden" name="action" value="velog_customer_save">';
		echo '<input type="hidden" name="customer_action" value="' . esc_attr( $action ) . '">';
		echo '<input type="hidden" name="customer_id" value="' . esc_attr( (string) $item['id'] ) . '">';
		echo '<input type="hidden" name="record_version" value="' . esc_attr( (string) $item['record_version'] ) . '">';
		wp_nonce_field( 'velog_customer_' . $action, 'velog_customer_nonce' );
		submit_button( ucfirst( $action ), 'small', 'submit', false );
		echo '</form>';
	}
}
