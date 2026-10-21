<?php
/**
 * Admin Menu Registration
 *
 * @package MF\VeLog\Admin
 */

namespace MF\VeLog\Admin;

use MF\VeLog\Core\Loader;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles registration of the VeLog admin menu and subpages.
 */
class AdminMenu {

	/**
	 * Settings page instance.
	 *
	 * @var RegionalSettingsPage
	 */
	private RegionalSettingsPage $settings_page;

	/**
	 * Customer page instance.
	 *
	 * @var CustomerPage
	 */
	private CustomerPage $customer_page;

	/**
	 * Vehicle page.
	 *
	 * @var VehiclePage
	 */
	private VehiclePage $vehicle_page;

	/**
	 * Read-only history screen.
	 *
	 * @var ServiceHistoryPage
	 */
	private ServiceHistoryPage $history_page;

	/**
	 * Manual maintenance screen.
	 *
	 * @var ReminderPage
	 */
	private ReminderPage $reminder_page;


	/**
	 * Service workflow page.
	 *
	 * @var ServicePage
	 */
	private ServicePage $service_page;

	/**
	 * Private service-type editor.
	 *
	 * @var ServiceTypePage
	 */
	private ServiceTypePage $service_type_page;

	/**
	 * Constructor.
	 *
	 * @param RegionalSettingsPage $settings_page Settings page.
	 * @param CustomerPage|null    $customer_page Customer page.
	 */
	public function __construct( RegionalSettingsPage $settings_page, ?CustomerPage $customer_page = null ) {
		$this->vehicle_page      = new VehiclePage();
		$this->history_page      = new ServiceHistoryPage();
		$this->reminder_page     = new ReminderPage();
		$this->service_page      = new ServicePage();
		$this->service_type_page = new ServiceTypePage();
		$this->settings_page     = $settings_page;
		$this->customer_page     = $customer_page ?? new CustomerPage();
	}

	/**
	 * Register hooks.
	 *
	 * @param Loader $loader Plugin hook loader.
	 */
	public function register_hooks( Loader $loader ): void {
		$loader->add_action( 'admin_menu', $this, 'add_menu_pages' );
		$this->settings_page->register_hooks( $loader );
		$this->customer_page->register_hooks( $loader );
		$this->vehicle_page->register_hooks( $loader );
		$this->reminder_page->register_hooks( $loader );
		$this->service_page->register_hooks( $loader );
		$this->service_type_page->register_hooks( $loader );
	}

	/**
	 * Add menu pages.
	 */
	public function add_menu_pages(): void {
		// Main menu - accessible by mf_velog_read_records.
		add_menu_page(
			__( 'VeLog', 'velog' ),
			__( 'VeLog', 'velog' ),
			'mf_velog_read_records',
			'velog',
			array( $this, 'render_dashboard' ),
			'dashicons-car',
			30
		);

		// Settings subpage - requires mf_velog_manage_settings.
		add_submenu_page(
			'velog',
			__( 'Customers', 'velog' ),
			__( 'Customers', 'velog' ),
			'mf_velog_manage_customers',
			'velog-customers',
			array( $this->customer_page, 'render' )
		);

		add_submenu_page(
			'velog',
			__( 'Vehicles', 'velog' ),
			__( 'Vehicles', 'velog' ),
			'mf_velog_read_records',
			'velog-vehicles',
			array( $this->vehicle_page, 'render' )
		);

		add_submenu_page(
			'velog',
			__( 'Services', 'velog' ),
			__( 'Services', 'velog' ),
			'mf_velog_read_records',
			'velog-services',
			array( $this->service_page, 'render' )
		);
		add_submenu_page(
			'velog',
			__( 'Service types', 'velog' ),
			__( 'Service types', 'velog' ),
			'mf_velog_manage_settings',
			'velog-service-types',
			array( $this->service_type_page, 'render' )
		);

		add_submenu_page(
			'velog',
			__( 'Service history', 'velog' ),
			__( 'Service history', 'velog' ),
			'mf_velog_read_records',
			'velog-history',
			array( $this->history_page, 'render' )
		);
		add_submenu_page(
			'velog',
			__( 'Maintenance reminders', 'velog' ),
			__( 'Reminders', 'velog' ),
			'mf_velog_manage_reminders',
			'velog-reminders',
			array( $this->reminder_page, 'render' )
		);

		add_submenu_page(
			'velog',
			__( 'Regional Settings', 'velog' ),
			__( 'Settings', 'velog' ),
			'mf_velog_manage_settings',
			'velog-settings',
			array( $this->settings_page, 'render' )
		);
	}

	/**
	 * Render the main dashboard page.
	 */
	public function render_dashboard(): void {
		if ( ! current_user_can( 'mf_velog_read_records' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'velog' ) );
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'VeLog Dashboard', 'velog' ) . '</h1>';
		echo '<p>' . esc_html__( 'Welcome to the VeLog service records system.', 'velog' ) . '</p>';
		echo '</div>';
	}
}
