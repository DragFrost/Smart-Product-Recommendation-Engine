<?php
/**
 * SPRE Frontend Assets Loader.
 *
 * @package SPRE\Frontend
 */

declare(strict_types=1);

namespace SPRE\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Assets
 *
 * Handles enqueuing of frontend stylesheets and scripts.
 */
class Assets {

	/**
	 * Bind script hooks.
	 */
	public function register(): void {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Enqueue frontend CSS and tracking JavaScript.
	 */
	public function enqueue_assets(): void {
		// Enqueue styles
		wp_enqueue_style(
			'spre-frontend-css',
			SPRE_PLUGIN_URL . 'assets/css/frontend.css',
			[],
			SPRE_VERSION
		);

		// Dynamic Color CSS injection & Custom CSS block
		$settings = get_option( 'spre_settings', [] );
		$custom_css_rules = '';

		$color_vars = [];
		if ( ! empty( $settings['primary_color'] ) ) {
			$color_vars[] = '--spre-primary-color: ' . esc_attr( $settings['primary_color'] ) . ';';
			$color_vars[] = '--spre-primary-hover: ' . esc_attr( $settings['primary_color'] ) . 'dd;';
		}
		if ( ! empty( $settings['text_color'] ) ) {
			$color_vars[] = '--spre-text-color: ' . esc_attr( $settings['text_color'] ) . ';';
		}
		if ( ! empty( $settings['price_color'] ) ) {
			$color_vars[] = '--spre-price-color: ' . esc_attr( $settings['price_color'] ) . ';';
		}
		if ( ! empty( $settings['badge_bg_color'] ) ) {
			$color_vars[] = '--spre-badge-bg-color: ' . esc_attr( $settings['badge_bg_color'] ) . ';';
		}
		if ( ! empty( $settings['btn_bg_color'] ) ) {
			$color_vars[] = '--spre-btn-bg-color: ' . esc_attr( $settings['btn_bg_color'] ) . ';';
		}
		if ( ! empty( $settings['btn_text_color'] ) ) {
			$color_vars[] = '--spre-btn-text-color: ' . esc_attr( $settings['btn_text_color'] ) . ';';
		}

		if ( ! empty( $color_vars ) ) {
			$custom_css_rules .= ".spre-recommendations-wrapper {\n\t" . implode( "\n\t", $color_vars ) . "\n}\n";
		}

		if ( ! empty( $settings['custom_css'] ) ) {
			$custom_css_rules .= "\n/* SPRE Custom CSS */\n" . $settings['custom_css'] . "\n";
		}

		if ( ! empty( $custom_css_rules ) ) {
			wp_add_inline_style( 'spre-frontend-css', $custom_css_rules );
		}

		// Enqueue tracking scripts
		wp_enqueue_script(
			'spre-tracking-js',
			SPRE_PLUGIN_URL . 'assets/js/tracking.js',
			[],
			SPRE_VERSION,
			true // Enqueue in footer for performance
		);

		// Localize tracking details
		wp_localize_script(
			'spre-tracking-js',
			'spre_tracking_params',
			[
				'api_url'    => esc_url_raw( rest_url( 'spre/v1/track' ) ),
				'product_id' => is_product() ? (int) get_the_ID() : 0,
				'nonce'      => wp_create_nonce( 'wp_rest' ),
			]
		);
	}
}
