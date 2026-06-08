<?php
/**
 * SPRE Product Relations Repository.
 *
 * @package SPRE\Database
 */

declare(strict_types=1);

namespace SPRE\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RelationsRepository
 *
 * Direct database access layer for product-to-product recommendation relations.
 */
class RelationsRepository {

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
		$this->table_name = $wpdb->prefix . 'spre_product_relations';
	}

	/**
	 * Save or update a product relationship.
	 *
	 * Uses composite keys for upsert optimization.
	 *
	 * @param int    $product_id   Source product ID.
	 * @param int    $related_id   Target recommended product ID.
	 * @param string $type         Relation type ('similarity', 'co_purchase').
	 * @param float  $score        Computed score.
	 * @param int    $occurrences  Number of co-purchases / matching counts.
	 * @return bool True on success, false on failure.
	 */
	public function save_relation( int $product_id, int $related_id, string $type, float $score, int $occurrences = 1 ): bool {
		global $wpdb;

		$sql = "INSERT INTO {$this->table_name} (product_id, related_id, relation_type, score, occurrences, updated_at)
			VALUES (%d, %d, %s, %f, %d, %s)
			ON DUPLICATE KEY UPDATE
				score = VALUES(score),
				occurrences = occurrences + VALUES(occurrences),
				updated_at = VALUES(updated_at)";

		$query = $wpdb->prepare(
			$sql, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$product_id,
			$related_id,
			$type,
			$score,
			$occurrences,
			current_time( 'mysql', true )
		);

		$result = $wpdb->query( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return false !== $result;
	}

	/**
	 * Fetch related product IDs for a given product and relation type.
	 *
	 * @param int    $product_id Source product ID.
	 * @param string $type       Relation type ('similarity', 'co_purchase').
	 * @param int    $limit      Limit of results.
	 * @return array<int> Array of related product IDs.
	 */
	public function get_relations( int $product_id, string $type, int $limit = 4 ): array {
		global $wpdb;

		$sql = "SELECT related_id FROM {$this->table_name}
			WHERE product_id = %d AND relation_type = %s
			ORDER BY score DESC, occurrences DESC
			LIMIT %d";

		$query = $wpdb->prepare(
			$sql, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$product_id,
			$type,
			$limit
		);

		$results = $wpdb->get_col( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return array_map( 'intval', $results );
	}

	/**
	 * Delete all relations of a certain type (used when recalculating catalog).
	 *
	 * @param string $type Relation type.
	 * @return bool
	 */
	public function delete_relations_by_type( string $type ): bool {
		global $wpdb;

		$sql = "DELETE FROM {$this->table_name} WHERE relation_type = %s";
		$query = $wpdb->prepare( $sql, $type ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return false !== $wpdb->query( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Purge old relations that haven't been updated for a certain period.
	 *
	 * @param int $days_threshold Number of days after which relation is considered stale.
	 * @return bool
	 */
	public function delete_stale_relations( int $days_threshold = 30 ): bool {
		global $wpdb;

		$sql   = "DELETE FROM {$this->table_name} WHERE updated_at < %s";
		$query = $wpdb->prepare( $sql, date( 'Y-m-d H:i:s', time() - ( $days_threshold * DAY_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return false !== $wpdb->query( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}
}
