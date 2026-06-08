<?php
/**
 * SPRE Trending Products Algorithm.
 *
 * @package SPRE\Recommendation\Algorithms
 */

declare(strict_types=1);

namespace SPRE\Recommendation\Algorithms;

use SPRE\Database\ViewsRepository;
use SPRE\Database\AnalyticsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Trending
 *
 * Computes trending products based on sales velocity and view activity.
 */
class Trending implements AlgorithmInterface {

	/**
	 * Views Repository.
	 *
	 * @var ViewsRepository
	 */
	private ViewsRepository $views_repo;

	/**
	 * Analytics Repository.
	 *
	 * @var AnalyticsRepository
	 */
	private AnalyticsRepository $analytics_repo;

	/**
	 * Constructor.
	 *
	 * @param ViewsRepository     $views_repo     Views tracker.
	 * @param AnalyticsRepository $analytics_repo Analytics tracker.
	 */
	public function __construct( ViewsRepository $views_repo, AnalyticsRepository $analytics_repo ) {
		$this->views_repo     = $views_repo;
		$this->analytics_repo = $analytics_repo;
	}

	/**
	 * Get trending products based on velocity in a given period.
	 *
	 * @param array{period?: string, limit?: int, exclude_ids?: array<int>} $context
	 * @return array<int> Trending product IDs.
	 */
	public function get_recommendations( array $context ): array {
		$period      = isset( $context['period'] ) ? sanitize_key( $context['period'] ) : '7d';
		$limit       = isset( $context['limit'] ) ? (int) $context['limit'] : 4;
		$exclude_ids = isset( $context['exclude_ids'] ) ? (array) $context['exclude_ids'] : [];

		// Hours conversion
		switch ( $period ) {
			case '24h':
				$hours = 24;
				break;
			case '30d':
				$hours = 720;
				break;
			case '7d':
			default:
				$hours = 168;
				break;
		}

		// Transient Cache check to avoid frequent heavy aggregates
		$cache_key = "spre_trending_products_{$period}";
		$trending_ids = get_transient( $cache_key );

		if ( false === $trending_ids ) {
			$trending_ids = $this->calculate_trending_products( $hours );
			set_transient( $cache_key, $trending_ids, HOUR_IN_SECONDS );
		}

		if ( ! is_array( $trending_ids ) ) {
			$trending_ids = [];
		}

		// Filter exclusions
		if ( ! empty( $exclude_ids ) ) {
			$trending_ids = array_values( array_diff( $trending_ids, $exclude_ids ) );
		}

		return array_slice( $trending_ids, 0, $limit );
	}

	/**
	 * Compute trending scores for products using views and sales events.
	 *
	 * Score = (Sales * 5) + (CartAdds * 3) + (Views * 1)
	 *
	 * @param int $hours Hours window.
	 * @return array<int> Sorted product IDs.
	 */
	private function calculate_trending_products( int $hours ): array {
		global $wpdb;

		// 1. Get views velocity counts
		$views_data = $this->views_repo->get_trending_views( $hours, 100 );

		// 2. Query sales/add-to-carts from analytics logs
		$time_threshold = date( 'Y-m-d H:i:s', time() - ( $hours * HOUR_IN_SECONDS ) );
		$table_name     = $wpdb->prefix . 'spre_analytics';

		$sql = "SELECT product_id,
			SUM(CASE WHEN event_type = 'purchase' THEN 1 ELSE 0 END) as sales_count,
			SUM(CASE WHEN event_type = 'add_to_cart' THEN 1 ELSE 0 END) as cart_count
			FROM {$table_name}
			WHERE created_at >= %s
			GROUP BY product_id
			LIMIT 100";

		$query          = $wpdb->prepare( $sql, $time_threshold ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$analytics_rows = $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// 3. Merge and compute weighted scores
		$scores = [];

		// Add views scores
		foreach ( $views_data as $prod_id => $view_count ) {
			$scores[ $prod_id ] = $view_count * 1.0;
		}

		// Add sales & cart scores
		foreach ( $analytics_rows as $row ) {
			$prod_id    = (int) $row['product_id'];
			$sale_score = (int) $row['sales_count'] * 5.0;
			$cart_score = (int) $row['cart_count'] * 3.0;

			if ( ! isset( $scores[ $prod_id ] ) ) {
				$scores[ $prod_id ] = 0.0;
			}
			$scores[ $prod_id ] += ( $sale_score + $cart_score );
		}

		// If scores is empty, fallback to WooCommerce standard top sellers
		if ( empty( $scores ) ) {
			return $this->get_woocommerce_popular_products();
		}

		arsort( $scores );

		return array_map( 'intval', array_keys( $scores ) );
	}

	/**
	 * Fallback helper when analytics data is empty.
	 *
	 * @return array<int> Popular product IDs.
	 */
	private function get_woocommerce_popular_products(): array {
		$args = [
			'limit'   => 10,
			'status'  => 'publish',
			'orderby' => 'meta_value_num',
			'meta_key' => 'total_sales',
			'order'   => 'DESC',
			'return'  => 'ids',
		];

		return array_map( 'intval', wc_get_products( $args ) );
	}
}
