<?php
/**
 * SPRE Admin Dashboard assets loader.
 *
 * @package SPRE\Admin
 */

declare(strict_types=1);

namespace SPRE\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Dashboard
 *
 * Enqueues React dashboard assets and localizes REST configurations.
 */
class Dashboard {

	/**
	 * Register actions.
	 */
	public function register(): void {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_dashboard_assets' ] );
	}

	/**
	 * Enqueue stylesheet and React bundle when visiting our settings sub-menu.
	 *
	 * @param string $hook The current admin page hook.
	 */
	public function enqueue_dashboard_assets( string $hook ): void {
		// Only load on our specific dashboard page
		if ( strpos( $hook, 'spre-dashboard' ) === false ) {
			return;
		}

		// Enqueue compiled React CSS bundle
		if ( file_exists( SPRE_PLUGIN_DIR . 'assets/css/admin.css' ) ) {
			wp_enqueue_style(
				'spre-admin-css',
				SPRE_PLUGIN_URL . 'assets/css/admin.css',
				[],
				SPRE_VERSION
			);
		}

		// Enqueue compiled React JS bundle
		wp_enqueue_script(
			'spre-admin-js',
			SPRE_PLUGIN_URL . 'assets/js/admin-dashboard.js',
			[ 'wp-element', 'wp-components', 'wp-api-fetch' ], // WordPress core React dependencies
			SPRE_VERSION,
			true
		);

		// Localize parameters for React application
		wp_localize_script(
			'spre-admin-js',
			'spre_admin_params',
			[
				'api_url'    => esc_url_raw( rest_url( 'spre/v1' ) ),
				'nonce'      => wp_create_nonce( 'wp_rest' ),
				'algorithms' => [
					[ 'value' => 'related', 'label' => esc_html__( 'Related Similarity', 'smart-product-recommendation-engine' ) ],
					[ 'value' => 'fbt', 'label' => esc_html__( 'Frequently Bought Together', 'smart-product-recommendation-engine' ) ],
					[ 'value' => 'personalized', 'label' => esc_html__( 'Personalized Suggestions', 'smart-product-recommendation-engine' ) ],
					[ 'value' => 'trending', 'label' => esc_html__( 'Trending Velocity', 'smart-product-recommendation-engine' ) ],
				],
				'settings'   => wp_parse_args( get_option( 'spre_settings', [] ), [
					'replace_default_related' => true,
					'show_fbt_in_summary'     => true,
					'require_cookie_consent'  => false,
					'related_limit'           => 4,
					'fbt_limit'               => 3,
					'show_badges'             => true,
					'show_excerpt'            => false,
					'show_rating'             => true,
					'show_price'              => true,
					'show_add_to_cart'        => true,
					'add_to_cart_text'        => '',
					'primary_color'           => '',
					'text_color'              => '',
					'price_color'             => '',
					'badge_bg_color'          => '',
					'btn_bg_color'            => '',
					'btn_text_color'          => '',
					'layout_mode'             => 'grid',
					'custom_css'              => '',
				] ),
			]
		);
	}
}
