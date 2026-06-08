<?php
/**
 * SPRE Analytics Repository.
 *
 * @package SPRE\Database
 */

declare(strict_types=1);

namespace SPRE\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AnalyticsRepository
 *
 * Direct database access for recommendation widget analytics, conversions, and metrics.
 */
class AnalyticsRepository {

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
		$this->table_name = $wpdb->prefix . 'spre_analytics';
	}

	/**
	 * Log a recommendation event (impression, click, add_to_cart).
	 *
	 * @param string      $event_type        Event type ('impression', 'click', 'add_to_cart', 'purchase').
	 * @param int         $product_id        Recommended product ID.
	 * @param int|null    $source_product_id Product ID of the page where recommended.
	 * @param string      $widget_type       Widget type ('fbt', 'related', 'trending', 'personalized').
	 * @param string      $session_hash      GDPR-safe session cookie hash.
	 * @param int|null    $user_id           User ID.
	 * @param int|null    $ab_test_id        Active A/B test ID.
	 * @param string|null $ab_variation     A/B test variation ('A' or 'B').
	 * @param int|null    $order_id          Associated order ID (for purchase conversions).
	 * @param float       $revenue           Attributed revenue.
	 * @return bool True on success.
	 */
	public function log_event(
		string $event_type,
		int $product_id,
		?int $source_product_id,
		string $widget_type,
		string $session_hash,
		?int $user_id = null,
		?int $ab_test_id = null,
		?string $ab_variation = null,
		?int $order_id = null,
		float $revenue = 0.0
	): bool {
		global $wpdb;

		$result = $wpdb->insert(
			$this->table_name,
			[
				'event_type'        => $event_type,
				'product_id'        => $product_id,
				'source_product_id' => $source_product_id,
				'widget_type'       => $widget_type,
				'user_id'           => $user_id > 0 ? $user_id : null,
				'session_hash'      => $session_hash,
				'ab_test_id'        => $ab_test_id,
				'ab_variation'      => $ab_variation,
				'order_id'          => $order_id,
				'revenue'           => $revenue,
				'created_at'        => current_time( 'mysql', true ),
			],
			[
				'%s',
				'%d',
				'%d',
				'%s',
				'%d',
				'%s',
				'%d',
				'%s',
				'%d',
				'%f',
				'%s',
			]
		);

		return false !== $result;
	}

	/**
	 * Track and attribute conversions from a completed order.
	 *
	 * Scans for items in the order and matches them to prior click events
	 * from the same session/user within the last 24 hours.
	 *
	 * @param int   $order_id WooCommerce Order ID.
	 * @param float $revenue  Total order value (fallback).
	 * @param string $session_hash Session hash.
	 * @param int   $user_id  User ID.
	 */
	public function track_order_conversions( int $order_id, float $revenue, string $session_hash, int $user_id = 0 ): void {
		global $wpdb;

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$items = $order->get_items();
		$attribution_window = date( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ); // 24 hours lookup

		foreach ( $items as $item ) {
			$product_id = (int) $item->get_product_id();
			$line_total = (float) $item->get_total();

			// Look for a click event for this product in this session/user during the window.
			if ( $user_id > 0 ) {
				$sql = "SELECT id, widget_type, source_product_id, ab_test_id, ab_variation FROM {$this->table_name}
					WHERE product_id = %d
					AND event_type = 'click'
					AND (user_id = %d OR session_hash = %s)
					AND created_at >= %s
					ORDER BY created_at DESC
					LIMIT 1";
				$query = $wpdb->prepare( $sql, $product_id, $user_id, $session_hash, $attribution_window ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			} else {
				$sql = "SELECT id, widget_type, source_product_id, ab_test_id, ab_variation FROM {$this->table_name}
					WHERE product_id = %d
					AND event_type = 'click'
					AND session_hash = %s
					AND created_at >= %s
					ORDER BY created_at DESC
					LIMIT 1";
				$query = $wpdb->prepare( $sql, $product_id, $session_hash, $attribution_window ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			}

			$prior_click = $wpdb->get_row( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			if ( $prior_click ) {
				// We found a matching click event! Log a purchase conversion.
				$this->log_event(
					'purchase',
					$product_id,
					$prior_click['source_product_id'] ? (int) $prior_click['source_product_id'] : null,
					$prior_click['widget_type'],
					$session_hash,
					$user_id > 0 ? $user_id : null,
					$prior_click['ab_test_id'] ? (int) $prior_click['ab_test_id'] : null,
					$prior_click['ab_variation'],
					$order_id,
					$line_total
				);
			}
		}
	}

	/**
	 * Get aggregated performance statistics for the admin dashboard.
	 *
	 * @param string $start_date ISO start date (YYYY-MM-DD).
	 * @param string $end_date   ISO end date (YYYY-MM-DD).
	 * @return array<string, mixed> Stats summary and chart nodes.
	 */
	public function get_dashboard_stats( string $start_date, string $end_date ): array {
		global $wpdb;

		// Format date ranges
		$start = sanitize_text_field( $start_date ) . ' 00:00:00';
		$end   = sanitize_text_field( $end_date ) . ' 23:59:59';

		// 1. Total Metrics Summary
		$sql_summary = "SELECT
			SUM(CASE WHEN event_type = 'impression' THEN 1 ELSE 0 END) as impressions,
			SUM(CASE WHEN event_type = 'click' THEN 1 ELSE 0 END) as clicks,
			SUM(CASE WHEN event_type = 'purchase' THEN 1 ELSE 0 END) as conversions,
			SUM(CASE WHEN event_type = 'purchase' THEN revenue ELSE 0 END) as revenue
			FROM {$this->table_name}
			WHERE created_at BETWEEN %s AND %s";

		$query_summary = $wpdb->prepare( $sql_summary, $start, $end ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$summary       = $wpdb->get_row( $query_summary, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$impressions = (int) ( $summary['impressions'] ?? 0 );
		$clicks      = (int) ( $summary['clicks'] ?? 0 );
		$conversions = (int) ( $summary['conversions'] ?? 0 );
		$revenue     = (float) ( $summary['revenue'] ?? 0.0 );
		$ctr         = $impressions > 0 ? round( ( $clicks / $impressions ) * 100, 2 ) : 0.0;
		$conv_rate   = $clicks > 0 ? round( ( $conversions / $clicks ) * 100, 2 ) : 0.0;

		// 2. Timeline chart groupings (by day)
		$sql_chart = "SELECT
			DATE(created_at) as event_date,
			SUM(CASE WHEN event_type = 'impression' THEN 1 ELSE 0 END) as impressions,
			SUM(CASE WHEN event_type = 'click' THEN 1 ELSE 0 END) as clicks,
			SUM(CASE WHEN event_type = 'purchase' THEN 1 ELSE 0 END) as conversions,
			SUM(CASE WHEN event_type = 'purchase' THEN revenue ELSE 0 END) as revenue
			FROM {$this->table_name}
			WHERE created_at BETWEEN %s AND %s
			GROUP BY DATE(created_at)
			ORDER BY event_date ASC";

		$query_chart = $wpdb->prepare( $sql_chart, $start, $end ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$chart_rows  = $wpdb->get_results( $query_chart, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// 3. Top performing recommended products
		$sql_products = "SELECT
			product_id,
			SUM(CASE WHEN event_type = 'impression' THEN 1 ELSE 0 END) as impressions,
			SUM(CASE WHEN event_type = 'click' THEN 1 ELSE 0 END) as clicks,
			SUM(CASE WHEN event_type = 'purchase' THEN 1 ELSE 0 END) as conversions,
			SUM(CASE WHEN event_type = 'purchase' THEN revenue ELSE 0 END) as revenue
			FROM {$this->table_name}
			WHERE created_at BETWEEN %s AND %s
			GROUP BY product_id
			ORDER BY revenue DESC, conversions DESC
			LIMIT 5";

		$query_products = $wpdb->prepare( $sql_products, $start, $end ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$product_rows   = $wpdb->get_results( $query_products, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$top_products = [];
		foreach ( $product_rows as $row ) {
			$product = wc_get_product( $row['product_id'] );
			if ( $product ) {
				$top_products[] = [
					'id'          => $row['product_id'],
					'name'        => $product->get_name(),
					'sku'         => $product->get_sku(),
					'impressions' => (int) $row['impressions'],
					'clicks'      => (int) $row['clicks'],
					'conversions' => (int) $row['conversions'],
					'revenue'     => (float) $row['revenue'],
				];
			}
		}

		return [
			'summary'      => [
				'impressions' => $impressions,
				'clicks'      => $clicks,
				'conversions' => $conversions,
				'revenue'     => $revenue,
				'ctr'         => $ctr,
				'conv_rate'   => $conv_rate,
			],
			'chart_data'   => array_map(
				function ( $row ) {
					return [
						'date'        => $row['event_date'],
						'impressions' => (int) $row['impressions'],
						'clicks'      => (int) $row['clicks'],
						'conversions' => (int) $row['conversions'],
						'revenue'     => (float) $row['revenue'],
					];
				},
				$chart_rows
			),
			'top_products' => $top_products,
		];
	}

	/**
	 * Get A/B Test comparisons.
	 *
	 * @param int $ab_test_id Test ID to analyze.
	 * @return array<string, mixed> Comparison table details.
	 */
	public function get_ab_test_stats( int $ab_test_id ): array {
		global $wpdb;

		$sql = "SELECT ab_variation,
			SUM(CASE WHEN event_type = 'impression' THEN 1 ELSE 0 END) as impressions,
			SUM(CASE WHEN event_type = 'click' THEN 1 ELSE 0 END) as clicks,
			SUM(CASE WHEN event_type = 'purchase' THEN 1 ELSE 0 END) as conversions,
			SUM(CASE WHEN event_type = 'purchase' THEN revenue ELSE 0 END) as revenue
			FROM {$this->table_name}
			WHERE ab_test_id = %d
			GROUP BY ab_variation";

		$query   = $wpdb->prepare( $sql, $ab_test_id ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$stats = [
			'A' => [ 'impressions' => 0, 'clicks' => 0, 'conversions' => 0, 'revenue' => 0.0, 'ctr' => 0.0, 'conv_rate' => 0.0 ],
			'B' => [ 'impressions' => 0, 'clicks' => 0, 'conversions' => 0, 'revenue' => 0.0, 'ctr' => 0.0, 'conv_rate' => 0.0 ],
		];

		foreach ( $results as $row ) {
			$var = $row['ab_variation'] === 'B' ? 'B' : 'A';
			$impr = (int) $row['impressions'];
			$clks = (int) $row['clicks'];
			$conv = (int) $row['conversions'];
			$rev  = (float) $row['revenue'];

			$stats[ $var ] = [
				'impressions' => $impr,
				'clicks'      => $clks,
				'conversions' => $conv,
				'revenue'     => $rev,
				'ctr'         => $impr > 0 ? round( ( $clks / $impr ) * 100, 2 ) : 0.0,
				'conv_rate'   => $clks > 0 ? round( ( $conv / $clks ) * 100, 2 ) : 0.0,
			];
		}

		return $stats;
	}

	/**
	 * Purge old analytics logs.
	 *
	 * @param int $days Retention days limit.
	 * @return int Number of deleted logs.
	 */
	public function purge_old_analytics( int $days = 90 ): int {
		global $wpdb;

		$time_threshold = date( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
		$sql            = "DELETE FROM {$this->table_name} WHERE created_at < %s";
		$query          = $wpdb->prepare( $sql, $time_threshold ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$result = $wpdb->query( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return is_int( $result ) ? $result : 0;
	}
}
