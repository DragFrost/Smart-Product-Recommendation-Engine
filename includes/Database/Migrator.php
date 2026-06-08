<?php
/**
 * SPRE Database Migrator.
 *
 * @package SPRE\Database
 */

declare(strict_types=1);

namespace SPRE\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Migrator
 *
 * Handles database activation, upgrades, and deactivation.
 */
class Migrator {

	/**
	 * Run migrations on plugin activation.
	 */
	public static function activate(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// We need dbDelta.
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// 1. Recommendations Rules Builder Configuration
		$table_recommendations = $wpdb->prefix . 'spre_recommendations';
		$sql_recommendations   = "CREATE TABLE {$table_recommendations} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			rule_name varchar(255) NOT NULL,
			conditions longtext NOT NULL,
			actions longtext NOT NULL,
			priority int(11) NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY  status_priority (status, priority)
		) {$charset_collate};";
		dbDelta( $sql_recommendations );

		// 2. Pre-calculated relationships (co-purchase & similar product matrices)
		$table_relations = $wpdb->prefix . 'spre_product_relations';
		$sql_relations   = "CREATE TABLE {$table_relations} (
			product_id bigint(20) unsigned NOT NULL,
			related_id bigint(20) unsigned NOT NULL,
			relation_type varchar(50) NOT NULL,
			score float NOT NULL DEFAULT 0.0,
			occurrences int(11) unsigned NOT NULL DEFAULT 1,
			updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (product_id, related_id, relation_type),
			KEY  related_id_idx (related_id),
			KEY  type_score_idx (relation_type, score)
		) {$charset_collate};";
		dbDelta( $sql_relations );

		// 3. GDPR-Friendly product browsing logs
		$table_views = $wpdb->prefix . 'spre_views';
		$sql_views   = "CREATE TABLE {$table_views} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned DEFAULT NULL,
			session_hash varchar(64) NOT NULL,
			product_id bigint(20) unsigned NOT NULL,
			viewed_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY  user_view_idx (user_id, viewed_at),
			KEY  session_view_idx (session_hash, viewed_at),
			KEY  product_view_idx (product_id, viewed_at)
		) {$charset_collate};";
		dbDelta( $sql_views );

		// 4. Recommendation event analytics
		$table_analytics = $wpdb->prefix . 'spre_analytics';
		$sql_analytics   = "CREATE TABLE {$table_analytics} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_type varchar(50) NOT NULL,
			product_id bigint(20) unsigned NOT NULL,
			source_product_id bigint(20) unsigned DEFAULT NULL,
			widget_type varchar(50) NOT NULL,
			user_id bigint(20) unsigned DEFAULT NULL,
			session_hash varchar(64) NOT NULL,
			ab_test_id bigint(20) unsigned DEFAULT NULL,
			ab_variation varchar(10) DEFAULT NULL,
			order_id bigint(20) unsigned DEFAULT NULL,
			revenue decimal(19,4) NOT NULL DEFAULT 0.0000,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY  event_type_idx (event_type),
			KEY  ab_test_idx (ab_test_id, ab_variation),
			KEY  created_at_idx (created_at),
			KEY  conversion_tracking_idx (session_hash, product_id, event_type)
		) {$charset_collate};";
		dbDelta( $sql_analytics );

		// 5. A/B Testing records
		$table_ab_tests = $wpdb->prefix . 'spre_ab_tests';
		$sql_ab_tests   = "CREATE TABLE {$table_ab_tests} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'draft',
			algorithm_a varchar(50) NOT NULL,
			algorithm_b varchar(50) NOT NULL,
			traffic_split int(11) NOT NULL DEFAULT 50,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			started_at datetime DEFAULT NULL,
			ended_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY  status_idx (status)
		) {$charset_collate};";
		dbDelta( $sql_ab_tests );

		// Save DB version.
		update_option( 'spre_db_version', SPRE_VERSION );

		// Schedule daily analytics cleanup (for view/impression logs retention).
		if ( ! wp_next_scheduled( 'spre_daily_cleanup_cron' ) ) {
			wp_schedule_event( time(), 'daily', 'spre_daily_cleanup_cron' );
		}
	}

	/**
	 * Run cleanup on plugin deactivation.
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'spre_daily_cleanup_cron' );
	}
}
