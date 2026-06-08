<?php
/**
 * SPRE A/B Tests Repository.
 *
 * @package SPRE\Database
 */

declare(strict_types=1);

namespace SPRE\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ABTestsRepository
 *
 * Direct database access for recommendation A/B testing configurations.
 */
class ABTestsRepository {

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
		$this->table_name = $wpdb->prefix . 'spre_ab_tests';
	}

	/**
	 * Retrieve all tests.
	 *
	 * @return array<array<string, mixed>> List of tests.
	 */
	public function get_all_tests(): array {
		global $wpdb;

		$sql  = "SELECT * FROM {$this->table_name} ORDER BY id DESC";
		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return array_map(
			function ( $row ) {
				return [
					'id'            => (int) $row['id'],
					'name'          => $row['name'],
					'status'        => $row['status'],
					'algorithm_a'   => $row['algorithm_a'],
					'algorithm_b'   => $row['algorithm_b'],
					'traffic_split' => (int) $row['traffic_split'],
					'created_at'    => $row['created_at'],
					'started_at'    => $row['started_at'],
					'ended_at'      => $row['ended_at'],
				];
			},
			$rows
		);
	}

	/**
	 * Retrieve the currently active A/B test (if any).
	 *
	 * Uses WordPress transients to cache the active test definition for performance.
	 *
	 * @return array<string, mixed>|null Active test data or null.
	 */
	public function get_active_test(): ?array {
		$cache_key   = 'spre_active_ab_test';
		$cached_test = get_transient( $cache_key );

		if ( false !== $cached_test ) {
			return is_array( $cached_test ) ? $cached_test : null;
		}

		global $wpdb;

		$sql   = "SELECT * FROM {$this->table_name} WHERE status = 'active' LIMIT 1";
		$row   = $wpdb->get_row( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$result = null;

		if ( $row ) {
			$result = [
				'id'            => (int) $row['id'],
				'name'          => $row['name'],
				'status'        => $row['status'],
				'algorithm_a'   => $row['algorithm_a'],
				'algorithm_b'   => $row['algorithm_b'],
				'traffic_split' => (int) $row['traffic_split'],
				'created_at'    => $row['created_at'],
				'started_at'    => $row['started_at'],
				'ended_at'      => $row['ended_at'],
			];
		}

		// Cache for 1 hour
		set_transient( $cache_key, $result ? $result : 'none', HOUR_IN_SECONDS );

		return $result;
	}

	/**
	 * Create a new A/B test config.
	 *
	 * @param string $name         Test name.
	 * @param string $algorithm_a  Algorithm name for variation A.
	 * @param string $algorithm_b  Algorithm name for variation B.
	 * @param int    $traffic_split Group A split percentage.
	 * @return int Insert ID or 0 on failure.
	 */
	public function create_test( string $name, string $algorithm_a, string $algorithm_b, int $traffic_split = 50 ): int {
		global $wpdb;

		$result = $wpdb->insert(
			$this->table_name,
			[
				'name'          => sanitize_text_field( $name ),
				'status'        => 'draft',
				'algorithm_a'   => sanitize_key( $algorithm_a ),
				'algorithm_b'   => sanitize_key( $algorithm_b ),
				'traffic_split' => min( 100, max( 0, (int) $traffic_split ) ),
				'created_at'    => current_time( 'mysql', true ),
			],
			[ '%s', '%s', '%s', '%s', '%d', '%s' ]
		);

		return $result ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Start an A/B test, pausing all other active tests.
	 *
	 * @param int $id Test ID.
	 * @return bool True on success.
	 */
	public function start_test( int $id ): bool {
		global $wpdb;

		// 1. Pause other active tests
		$wpdb->query( "UPDATE {$this->table_name} SET status = 'completed', ended_at = '" . current_time( 'mysql', true ) . "' WHERE status = 'active'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// 2. Set this test active
		$result = $wpdb->update(
			$this->table_name,
			[
				'status'     => 'active',
				'started_at' => current_time( 'mysql', true ),
			],
			[ 'id' => $id ],
			[ '%s', '%s' ],
			[ '%d' ]
		);

		// Clear transient cache
		delete_transient( 'spre_active_ab_test' );

		return false !== $result;
	}

	/**
	 * End an A/B test.
	 *
	 * @param int $id Test ID.
	 * @return bool True on success.
	 */
	public function end_test( int $id ): bool {
		global $wpdb;

		$result = $wpdb->update(
			$this->table_name,
			[
				'status'   => 'completed',
				'ended_at' => current_time( 'mysql', true ),
			],
			[ 'id' => $id ],
			[ '%s', '%s' ],
			[ '%d' ]
		);

		// Clear transient cache
		delete_transient( 'spre_active_ab_test' );

		return false !== $result;
	}

	/**
	 * Delete a test.
	 *
	 * @param int $id Test ID.
	 * @return bool True on success.
	 */
	public function delete_test( int $id ): bool {
		global $wpdb;

		$result = $wpdb->delete(
			$this->table_name,
			[ 'id' => $id ],
			[ '%d' ]
		);

		// Clear transient cache
		delete_transient( 'spre_active_ab_test' );

		return false !== $result;
	}
}
