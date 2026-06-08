<?php
/**
 * Plugin Name: Smart Product Recommendation Engine
 * Plugin URI: https://wordpress.org/plugins/smart-product-recommendation-engine/
 * Description: Enterprise-grade product recommendation system for WooCommerce utilizing machine learning, weighted category/tag similarity, trending velocity, and A/B testing.
 * Version: 1.0.0
 * Author: Senior WordPress Architect
 * Author URI: https://github.com/google-deepmind
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: smart-product-recommendation-engine
 * Domain Path: /languages
 * Requires PHP: 7.4
 * Requires At Least: 5.8
 * WC Requires At Least: 5.0
 *
 * @package SPRE
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define Constants
define( 'SPRE_VERSION', '1.0.0' );
define( 'SPRE_MIN_PHP_VERSION', '7.4' );
define( 'SPRE_PLUGIN_FILE', __FILE__ );
define( 'SPRE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SPRE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Fallback PSR-4 Autoloader for SPRE namespace.
 * Used if composer install has not been run.
 *
 * @param string $class Class name.
 */
function spre_fallback_autoloader( string $class ): void {
	$prefix = 'SPRE\\';
	$base_dir = SPRE_PLUGIN_DIR . 'includes/';

	$len = strlen( $prefix );
	if ( strncmp( $prefix, $class, $len ) !== 0 ) {
		return;
	}

	$relative_class = substr( $class, $len );
	$file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

	if ( file_exists( $file ) ) {
		require_once $file;
	}
}

// Load Composer Autoloader or fallback immediately in global scope
if ( file_exists( SPRE_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once SPRE_PLUGIN_DIR . 'vendor/autoload.php';
} else {
	spl_autoload_register( 'spre_fallback_autoloader' );
}

// Register Activation and Deactivation Hooks in global scope so they are caught early
register_activation_hook( SPRE_PLUGIN_FILE, [ 'SPRE\Database\Migrator', 'activate' ] );
register_deactivation_hook( SPRE_PLUGIN_FILE, [ 'SPRE\Database\Migrator', 'deactivate' ] );

/**
 * Check requirements and bootstrap the plugin.
 */
function spre_bootstrap(): void {
	// Check PHP Version
	if ( version_compare( PHP_VERSION, SPRE_MIN_PHP_VERSION, '<' ) ) {
		add_action( 'admin_notices', 'spre_php_version_error' );
		return;
	}

	// Check if WooCommerce is active
	if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) {
		// Also check in multisite
		if ( ! is_multisite() || ! is_plugin_active_for_network( 'woocommerce/woocommerce.php' ) ) {
			add_action( 'admin_notices', 'spre_woocommerce_missing_error' );
			return;
		}
	}

	// Boot the core plugin lifecycle handler
	SPRE\Core\Plugin::instance();
}


/**
 * Display PHP version notice.
 */
function spre_php_version_error(): void {
	$message = sprintf(
		/* translators: %s: minimum PHP version */
		esc_html__( 'Smart Product Recommendation Engine requires PHP %s or higher. Your current PHP version is %s.', 'smart-product-recommendation-engine' ),
		SPRE_MIN_PHP_VERSION,
		PHP_VERSION
	);
	echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
}

/**
 * Display WooCommerce missing notice.
 */
function spre_woocommerce_missing_error(): void {
	$message = esc_html__( 'Smart Product Recommendation Engine requires WooCommerce to be installed and active.', 'smart-product-recommendation-engine' );
	echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
}

// Start the engine
add_action( 'plugins_loaded', 'spre_bootstrap', 10 );
