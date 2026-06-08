<?php
/**
 * SPRE Frequently Bought Together (FBT) Algorithm.
 *
 * @package SPRE\Recommendation\Algorithms
 */

declare(strict_types=1);

namespace SPRE\Recommendation\Algorithms;

use SPRE\Database\RelationsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FrequentlyBoughtTogether
 *
 * Generates recommendations based on product co-occurrence in orders.
 */
class FrequentlyBoughtTogether implements AlgorithmInterface {

	/**
	 * Relations Repository.
	 *
	 * @var RelationsRepository
	 */
	private RelationsRepository $relations_repo;

	/**
	 * Constructor.
	 *
	 * @param RelationsRepository $relations_repo Repository instance.
	 */
	public function __construct( RelationsRepository $relations_repo ) {
		$this->relations_repo = $relations_repo;
	}

	/**
	 * Get co-purchase recommendations for the product.
	 *
	 * @param array{product_id?: int, limit?: int, exclude_ids?: array<int>} $context
	 * @return array<int> Recommended product IDs.
	 */
	public function get_recommendations( array $context ): array {
		$product_id = isset( $context['product_id'] ) ? (int) $context['product_id'] : 0;
		if ( $product_id <= 0 ) {
			return [];
		}

		$limit       = isset( $context['limit'] ) ? (int) $context['limit'] : 4;
		$exclude_ids = isset( $context['exclude_ids'] ) ? (array) $context['exclude_ids'] : [];

		$fbt_ids = $this->relations_repo->get_relations( $product_id, 'co_purchase', $limit + count( $exclude_ids ) );

		if ( ! empty( $exclude_ids ) ) {
			$fbt_ids = array_values( array_diff( $fbt_ids, $exclude_ids ) );
		}

		return array_slice( $fbt_ids, 0, $limit );
	}

	/**
	 * Run high-performance SQL query to rebuild all co-purchase relationships.
	 *
	 * Uses an optimized MySQL self-join on order items tables to fetch pairings in bulk,
	 * bypassing standard PHP loops and meta queries.
	 */
	public function rebuild_all_co_purchases(): void {
		global $wpdb;

		// 1. Drop old co-purchase rows
		$this->relations_repo->delete_relations_by_type( 'co_purchase' );

		$oi_table    = $wpdb->prefix . 'woocommerce_order_items';
		$oim_table   = $wpdb->prefix . 'woocommerce_order_itemmeta';
		$posts_table = $wpdb->posts;

		// 2. Query co-purchase pairs.
		// Standard HPOS (High-Performance Order Storage) vs Legacy WooCommerce:
		// We query the standard posts tables which covers legacy, and check HPOS as well if active.
		// To support both, we check the legacy shop_order tables:
		$sql = "SELECT
					a.meta_value as product_a_id,
					b.meta_value as product_b_id,
					COUNT(*) as co_purchase_count
				FROM {$oi_table} oi_a
				INNER JOIN {$oim_table} a ON oi_a.order_item_id = a.order_item_id AND a.meta_key = '_product_id'
				INNER JOIN {$oi_table} oi_b ON oi_a.order_id = oi_b.order_id AND oi_a.order_item_id != oi_b.order_item_id
				INNER JOIN {$oim_table} b ON oi_b.order_item_id = b.order_item_id AND b.meta_key = '_product_id'
				INNER JOIN {$posts_table} orders ON oi_a.order_id = orders.ID AND orders.post_type = 'shop_order' AND orders.post_status = 'wc-completed'
				WHERE CAST(a.meta_value AS UNSIGNED) > 0 AND CAST(b.meta_value AS UNSIGNED) > 0
				GROUP BY product_a_id, product_b_id
				ORDER BY co_purchase_count DESC";

		$results = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( empty( $results ) ) {
			return;
		}

		// Calculate total purchases per product to normalize the score:
		// Score = (co_purchases / total_purchases_product_a) * 100
		$product_purchase_counts = [];
		foreach ( $results as $row ) {
			$a_id = (int) $row['product_a_id'];
			if ( ! isset( $product_purchase_counts[ $a_id ] ) ) {
				$product_purchase_counts[ $a_id ] = 0;
			}
			$product_purchase_counts[ $a_id ] += (int) $row['co_purchase_count'];
		}

		// Save computed co-purchase scores in batches
		foreach ( $results as $row ) {
			$a_id        = (int) $row['product_a_id'];
			$b_id        = (int) $row['product_b_id'];
			$occurrences = (int) $row['co_purchase_count'];

			$total_purchases_a = $product_purchase_counts[ $a_id ] ?: 1;
			$score             = ( $occurrences / $total_purchases_a ) * 100.0;

			$this->relations_repo->save_relation(
				$a_id,
				$b_id,
				'co_purchase',
				(float) $score,
				$occurrences
			);
		}
	}
}
