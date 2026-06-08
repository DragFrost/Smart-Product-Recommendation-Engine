<?php
/**
 * SPRE Related Similarity Algorithm.
 *
 * @package SPRE\Recommendation\Algorithms
 */

declare(strict_types=1);

namespace SPRE\Recommendation\Algorithms;

use SPRE\Database\RelationsRepository;
use WC_Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RelatedSimilarity
 *
 * Computes and queries content-based product similarities.
 */
class RelatedSimilarity implements AlgorithmInterface {

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
	 * Get pre-computed similarity recommendations.
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

		$related_ids = $this->relations_repo->get_relations( $product_id, 'similarity', $limit + count( $exclude_ids ) );

		// Filter out excluded IDs
		if ( ! empty( $exclude_ids ) ) {
			$related_ids = array_values( array_diff( $related_ids, $exclude_ids ) );
		}

		return array_slice( $related_ids, 0, $limit );
	}

	/**
	 * Rebuild similarities for the entire catalog in batches.
	 */
	public function rebuild_all_similarities(): void {
		global $wpdb;

		// Clear old similarities
		$this->relations_repo->delete_relations_by_type( 'similarity' );

		// Process in chunks to prevent memory fatigue on large stores
		$limit  = 100;
		$offset = 0;

		do {
			// Query only active product IDs directly from WP DB for performance
			$sql = "SELECT ID FROM {$wpdb->posts}
				WHERE post_type = 'product' AND post_status = 'publish'
				ORDER BY ID ASC
				LIMIT %d OFFSET %d";

			$query       = $wpdb->prepare( $sql, $limit, $offset ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$product_ids = $wpdb->get_col( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			if ( empty( $product_ids ) ) {
				break;
			}

			foreach ( $product_ids as $product_id ) {
				$this->rebuild_product_similarities( (int) $product_id );
			}

			$offset += $limit;

			// Yield control/prevent execution timeout
			if ( function_exists( 'gc_collect_cycles' ) ) {
				gc_collect_cycles();
			}
		} while ( count( $product_ids ) === $limit );
	}

	/**
	 * Rebuild similarity relationships for a single product.
	 *
	 * @param int $product_id Product ID.
	 */
	private function rebuild_product_similarities( int $product_id ): void {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return;
		}

		// Retrieve scoring attributes
		$category_ids = $product->get_category_ids();
		$tag_ids      = $product->get_tag_ids();
		$price        = (float) $product->get_price();
		$brand        = $product->get_attribute( 'pa_brand' );

		if ( empty( $category_ids ) && empty( $tag_ids ) ) {
			return;
		}

		// Optimization: Find candidates sharing categories or tags
		$candidate_ids = $this->get_similarity_candidates( $product_id, $category_ids, $tag_ids );
		if ( empty( $candidate_ids ) ) {
			return;
		}

		$scores = [];
		foreach ( $candidate_ids as $candidate_id ) {
			$candidate = wc_get_product( $candidate_id );
			if ( ! $candidate ) {
				continue;
			}

			$score = $this->calculate_similarity_score(
				$category_ids,
				$tag_ids,
				$price,
				$brand,
				$candidate
			);

			if ( $score > 0.0 ) {
				$scores[ $candidate_id ] = $score;
			}
		}

		// Sort and slice top 10 similar products
		arsort( $scores );
		$top_candidates = array_slice( $scores, 0, 10, true );

		foreach ( $top_candidates as $related_id => $score ) {
			$this->relations_repo->save_relation(
				$product_id,
				(int) $related_id,
				'similarity',
				(float) $score,
				1
			);
		}
	}

	/**
	 * Calculate a weighted similarity score between primary attributes and a candidate product.
	 *
	 * @param array  $category_ids Primary categories.
	 * @param array  $tag_ids      Primary tags.
	 * @param float  $price        Primary price.
	 * @param string $brand        Primary brand attribute value.
	 * @param \WC_Product $candidate Candidate product object.
	 * @return float Similarity score.
	 */
	private function calculate_similarity_score(
		array $category_ids,
		array $tag_ids,
		float $price,
		string $brand,
		WC_Product $candidate
	): float {
		$score = 0.0;

		// 1. Categories match (Weight: 30)
		$cand_categories = $candidate->get_category_ids();
		$common_cats     = array_intersect( $category_ids, $cand_categories );
		if ( ! empty( $common_cats ) ) {
			$score += 30.0;
		}

		// 2. Tags match (Weight: 30, sliding scale based on overlapping tags count)
		$cand_tags = $candidate->get_tag_ids();
		$common_tags = array_intersect( $tag_ids, $cand_tags );
		if ( ! empty( $common_tags ) && ! empty( $tag_ids ) ) {
			$overlap_ratio = count( $common_tags ) / count( $tag_ids );
			$score        += $overlap_ratio * 30.0;
		}

		// 3. Price similarity (Weight: 20, score decreases as price gap increases)
		$cand_price = (float) $candidate->get_price();
		if ( $price > 0.0 && $cand_price > 0.0 ) {
			$price_diff = abs( $price - $cand_price );
			$deviation  = $price_diff / $price;
			if ( $deviation < 1.0 ) {
				$score += ( 1.0 - $deviation ) * 20.0;
			}
		}

		// 4. Brand matches (Weight: 20)
		$cand_brand = $candidate->get_attribute( 'pa_brand' );
		if ( ! empty( $brand ) && ! empty( $cand_brand ) && strcasecmp( $brand, $cand_brand ) === 0 ) {
			$score += 20.0;
		}

		// 5. Popularity factor (Weight: 10)
		// Pull sales velocity or total sales meta from WC
		$total_sales = (int) $candidate->get_meta( 'total_sales' );
		if ( $total_sales > 0 ) {
			$score += min( 10.0, log( $total_sales, 2 ) );
		}

		return $score;
	}

	/**
	 * Fast SQL helper to find candidate product IDs sharing categories or tags with the parent.
	 * Avoids checking standard WP posts loop.
	 *
	 * @param int   $product_id   Main product.
	 * @param array $category_ids Category IDs.
	 * @param array $tag_ids      Tag IDs.
	 * @return array<int> List of candidate product IDs.
	 */
	private function get_similarity_candidates( int $product_id, array $category_ids, array $tag_ids ): array {
		global $wpdb;

		$term_ids = array_merge( $category_ids, $tag_ids );
		if ( empty( $term_ids ) ) {
			return [];
		}

		$placeholders = implode( ',', array_fill( 0, count( $term_ids ), '%d' ) );

		$sql = "SELECT DISTINCT object_id FROM {$wpdb->term_relationships} tr
			INNER JOIN {$wpdb->posts} p ON tr.object_id = p.ID
			WHERE tr.term_taxonomy_id IN ($placeholders)
			AND p.post_type = 'product'
			AND p.post_status = 'publish'
			AND p.ID != %d
			LIMIT 100"; // Cap at 100 candidates per product to remain performant

		$params   = array_merge( $term_ids, [ $product_id ] );
		$query    = $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$candidate_ids = $wpdb->get_col( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return array_map( 'intval', $candidate_ids );
	}
}
