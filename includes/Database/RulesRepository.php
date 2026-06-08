<?php
/**
 * SPRE Rules Repository.
 *
 * @package SPRE\Database
 */

declare(strict_types=1);

namespace SPRE\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RulesRepository
 *
 * Handles database operations for the admin custom Recommendation Rule Builder.
 */
class RulesRepository {

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
		$this->table_name = $wpdb->prefix . 'spre_recommendations';
	}

	/**
	 * Retrieve a single rule by ID.
	 *
	 * @param int $id Rule ID.
	 * @return array<string, mixed>|null The rule array or null.
	 */
	public function get_rule( int $id ): ?array {
		global $wpdb;

		$sql   = "SELECT * FROM {$this->table_name} WHERE id = %d";
		$query = $wpdb->prepare( $sql, $id ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$row   = $wpdb->get_row( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( ! $row ) {
			return null;
		}

		$row['conditions'] = json_decode( $row['conditions'], true );
		$row['actions']    = json_decode( $row['actions'], true );

		return $row;
	}

	/**
	 * Retrieve all active recommendation rules, sorted by priority.
	 *
	 * @return array<array<string, mixed>> List of rules.
	 */
	public function get_active_rules(): array {
		global $wpdb;

		$sql   = "SELECT * FROM {$this->table_name} WHERE status = 'active' ORDER BY priority DESC, id DESC";
		$rows  = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rules = [];

		foreach ( $rows as $row ) {
			$row['conditions'] = json_decode( $row['conditions'], true );
			$row['actions']    = json_decode( $row['actions'], true );
			$rules[]           = $row;
		}

		return $rules;
	}

	/**
	 * Retrieve all rules (active & inactive) for the admin dashboard.
	 *
	 * @return array<array<string, mixed>> List of rules.
	 */
	public function get_all_rules(): array {
		global $wpdb;

		$sql   = "SELECT * FROM {$this->table_name} ORDER BY priority DESC, id DESC";
		$rows  = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rules = [];

		foreach ( $rows as $row ) {
			$row['conditions'] = json_decode( $row['conditions'], true );
			$row['actions']    = json_decode( $row['actions'], true );
			$rules[]           = $row;
		}

		return $rules;
	}

	/**
	 * Create a new custom recommendation rule.
	 *
	 * @param string $name       Rule name.
	 * @param array  $conditions Rule conditions.
	 * @param array  $actions    Rule actions.
	 * @param int    $priority   Processing priority.
	 * @return int Insert ID or 0 on failure.
	 */
	public function create_rule( string $name, array $conditions, array $actions, int $priority = 0 ): int {
		global $wpdb;

		$result = $wpdb->insert(
			$this->table_name,
			[
				'rule_name'  => sanitize_text_field( $name ),
				'conditions' => wp_json_encode( $conditions ),
				'actions'    => wp_json_encode( $actions ),
				'priority'   => (int) $priority,
				'status'     => 'active',
				'created_at' => current_time( 'mysql', true ),
				'updated_at' => current_time( 'mysql', true ),
			],
			[ '%s', '%s', '%s', '%d', '%s', '%s', '%s' ]
		);

		return $result ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Update an existing rule.
	 *
	 * @param int    $id         Rule ID.
	 * @param string $name       Rule name.
	 * @param array  $conditions Rule conditions.
	 * @param array  $actions    Rule actions.
	 * @param int    $priority   Priority.
	 * @param string $status     Status ('active', 'inactive').
	 * @return bool True on success.
	 */
	public function update_rule( int $id, string $name, array $conditions, array $actions, int $priority = 0, string $status = 'active' ): bool {
		global $wpdb;

		$result = $wpdb->update(
			$this->table_name,
			[
				'rule_name'  => sanitize_text_field( $name ),
				'conditions' => wp_json_encode( $conditions ),
				'actions'    => wp_json_encode( $actions ),
				'priority'   => (int) $priority,
				'status'     => sanitize_key( $status ),
				'updated_at' => current_time( 'mysql', true ),
			],
			[ 'id' => $id ],
			[ '%s', '%s', '%s', '%d', '%s', '%s' ],
			[ '%d' ]
		);

		return false !== $result;
	}

	/**
	 * Delete a rule.
	 *
	 * @param int $id Rule ID.
	 * @return bool True on success.
	 */
	public function delete_rule( int $id ): bool {
		global $wpdb;

		$result = $wpdb->delete(
			$this->table_name,
			[ 'id' => $id ],
			[ '%d' ]
		);

		return false !== $result;
	}
}
