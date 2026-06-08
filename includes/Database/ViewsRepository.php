<?php
/**
 * SPRE Views Repository.
 *
 * @package SPRE\Database
 */

declare(strict_types=1);

namespace SPRE\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ViewsRepository
 *
 * Handles raw logging and querying of product pageviews for recommendation generation.
 */
class ViewsRepository {

	/**
	 * Table name.
	 *
	 * @var string
	 */
	private string $table_name;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'spre_views';
	}

	/**
	 * Log a product view.
	 *
	 * @param int    $product_id   Product being viewed.
	 * @param string $session_hash GDPR-safe session hash identifier.
	 * @param int    $user_id      User ID if logged in (default: 0).
	 * @return bool True on success.
	 */
	public function log_view( int $product_id, string $session_hash, int $user_id = 0 ): bool {
		global $wpdb;

		$result = $wpdb->insert(
			$this->table_name,
			[
				'user_id'      => $user_id > 0 ? $user_id : null,
				'session_hash' => $session_hash,
				'product_id'   => $product_id,
				'viewed_at'    => current_time( 'mysql', true ),
			],
			[
				'%d',
				'%s',
				'%d',
				'%s',
			]
		);

		return false !== $result;
	}

	/**
	 * Retrieve recently viewed product IDs for a session or user.
	 *
	 * Combines user history and session history for high usability.
	 *
	 * @param string $session_hash Session hash.
	 * @param int    $user_id      User ID if logged in.
	 * @param int    $limit        Result limit.
	 * @return array<int> Unique list of recently viewed product IDs.
	 */
	public function get_recently_viewed( string $session_hash, int $user_id = 0, int $limit = 10 ): array {
		global $wpdb;

		if ( $user_id > 0 ) {
			$sql   = "SELECT product_id, MAX(viewed_at) as latest_view FROM {$this->table_name}
				WHERE user_id = %d OR session_hash = %s
				GROUP BY product_id
				ORDER BY latest_view DESC
				LIMIT %d";
			$query = $wpdb->prepare( $sql, $user_id, $session_hash, $limit ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		} else {
			$sql   = "SELECT product_id, MAX(viewed_at) as latest_view FROM {$this->table_name}
				WHERE session_hash = %s
				GROUP BY product_id
				ORDER BY latest_view DESC
				LIMIT %d";
			$query = $wpdb->prepare( $sql, $session_hash, $limit ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		$results = $wpdb->get_col( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return array_map( 'intval', $results );
	}

	/**
	 * Fetch views aggregation within a time window for the trending engine.
	 *
	 * @param int $hours Hours window.
	 * @param int $limit Max candidates.
	 * @return array<int, int> Map of product_id => view_count.
	 */
	public function get_trending_views( int $hours = 24, int $limit = 100 ): array {
		global $wpdb;

		$time_threshold = date( 'Y-m-d H:i:s', time() - ( $hours * HOUR_IN_SECONDS ) );

		$sql = "SELECT product_id, COUNT(id) as view_count FROM {$this->table_name}
			WHERE viewed_at >= %s
			GROUP BY product_id
			ORDER BY view_count DESC
			LIMIT %d";

		$query = $wpdb->prepare( $sql, $time_threshold, $limit ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$results = $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$views_map = [];
		foreach ( $results as $row ) {
			$views_map[ (int) $row['product_id'] ] = (int) $row['view_count'];
		}

		return $views_map;
	}

	/**
	 * Purge view records older than a specific amount of days.
	 * Keeps database sizes in check and meets GDPR storage limitation requirements.
	 *
	 * @param int $days Days threshold (default: 30 days).
	 * @return int Number of rows deleted.
	 */
	public function purge_old_views( int $days = 30 ): int {
		global $wpdb;

		$time_threshold = date( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
		$sql            = "DELETE FROM {$this->table_name} WHERE viewed_at < %s";
		$query          = $wpdb->prepare( $sql, $time_threshold ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$result = $wpdb->query( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return is_int( $result ) ? $result : 0;
	}
}
