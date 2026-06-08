<?php
/**
 * SPRE Admin Menu handler.
 *
 * @package SPRE\Admin
 */

declare(strict_types=1);

namespace SPRE\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Menu
 *
 * Configures the WooCommerce administrative sub-menu page.
 */
class Menu {

	/**
	 * Bind admin menu action.
	 */
	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
	}

	/**
	 * Register sub-menu page under WooCommerce.
	 */
	public function add_admin_menu(): void {
		add_submenu_page(
			'woocommerce',
			esc_html__( 'Recommendation Engine', 'smart-product-recommendation-engine' ),
			esc_html__( 'Recommendation Engine', 'smart-product-recommendation-engine' ),
			'manage_woocommerce',
			'spre-dashboard',
			[ $this, 'render_dashboard_page' ]
		);
	}

	/**
	 * Callback to output the React Dashboard root node.
	 */
	public function render_dashboard_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'smart-product-recommendation-engine' ) );
		}

		echo '<div id="spre-admin-dashboard" class="spre-admin-root"></div>';
	}
}
