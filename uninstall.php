<?php
/**
 * Smart Product Recommendation Engine Uninstaller
 *
 * @package SPRE
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Table names
$tables = [
	$wpdb->prefix . 'spre_recommendations',
	$wpdb->prefix . 'spre_product_relations',
	$wpdb->prefix . 'spre_views',
	$wpdb->prefix . 'spre_analytics',
	$wpdb->prefix . 'spre_ab_tests',
];

// Drop tables
foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

// Delete options
delete_option( 'spre_settings' );
delete_option( 'spre_db_version' );

// Delete cached transients
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_spre_%' OR option_name LIKE '_transient_timeout_spre_%'" );
