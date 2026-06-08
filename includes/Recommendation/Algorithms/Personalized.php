<?php
/**
 * SPRE Personalized Recommendation Algorithm.
 *
 * @package SPRE\Recommendation\Algorithms
 */

declare(strict_types=1);

namespace SPRE\Recommendation\Algorithms;

use SPRE\Database\ViewsRepository;
use SPRE\Database\RelationsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Personalized
 *
 * Generates personalized product listings based on individual user profiles.
 */
class Personalized implements AlgorithmInterface {

	/**
	 * Views Repository.
	 *
	 * @var ViewsRepository
	 */
	private ViewsRepository $views_repo;

	/**
	 * Relations Repository.
	 *
	 * @var RelationsRepository
	 */
	private RelationsRepository $relations_repo;

	/**
	 * Constructor.
	 *
	 * @param ViewsRepository     $views_repo     Views tracker.
	 * @param RelationsRepository $relations_repo Relations tracker.
	 */
	public function __construct( ViewsRepository $views_repo, RelationsRepository $relations_repo ) {
		$this->views_repo     = $views_repo;
		$this->relations_repo = $relations_repo;
	}

	/**
	 * Get personalized recommendations for a user.
	 *
	 * @param array{user_id?: int, session_hash?: string, limit?: int, exclude_ids?: array<int>} $context
	 * @return array<int> Recommended product IDs.
	 */
	public function get_recommendations( array $context ): array {
		$user_id      = isset( $context['user_id'] ) ? (int) $context['user_id'] : 0;
		$session_hash = isset( $context['session_hash'] ) ? sanitize_text_field( $context['session_hash'] ) : '';
		$limit        = isset( $context['limit'] ) ? (int) $context['limit'] : 4;
		$exclude_ids  = isset( $context['exclude_ids'] ) ? (array) $context['exclude_ids'] : [];

		if ( $user_id <= 0 && empty( $session_hash ) ) {
			return []; // No identifier to personalize with
		}

		// 1. Gather historical products (viewed & purchased)
		$viewed_ids = $this->views_repo->get_recently_viewed( $session_hash, $user_id, 15 );
		$purchased_ids = [];

		if ( $user_id > 0 ) {
			$purchased_ids = $this->get_user_purchased_products( $user_id );
		}

		$history_ids = array_unique( array_merge( $viewed_ids, $purchased_ids ) );

		// If user has no browsing history, we cannot personalize. Return empty (calling code will fallback)
		if ( empty( $history_ids ) ) {
			return [];
		}

		// Add history to excluded list so we don't recommend things they already bought or viewed
		$exclude_ids = array_unique( array_merge( $exclude_ids, $history_ids ) );

		// 2. Identify favorite categories
		$favorite_categories = $this->get_favorite_categories( $history_ids );
		if ( empty( $favorite_categories ) ) {
			return [];
		}

		// 3. Query candidate products from favorite categories
		$candidates = $this->get_popular_products_in_categories( $favorite_categories, $exclude_ids, $limit * 2 );
		if ( empty( $candidates ) ) {
			return [];
		}

		// 4. Score candidates based on similarity to the user's last viewed product
		$scored_candidates = [];
		$last_viewed_id    = reset( $viewed_ids ); // Top of views list

		if ( $last_viewed_id ) {
			// Find products similar to their last viewed product
			$similar_to_last = $this->relations_repo->get_relations( $last_viewed_id, 'similarity', 50 );
			foreach ( $candidates as $candidate_id ) {
				$score = 10.0; // Base score
				if ( in_array( $candidate_id, $similar_to_last, true ) ) {
					$score += 50.0; // Huge weight if similar to last viewed
				}
				$scored_candidates[ $candidate_id ] = $score;
			}
			arsort( $scored_candidates );
			$recommended_ids = array_keys( $scored_candidates );
		} else {
			$recommended_ids = $candidates;
		}

		return array_slice( $recommended_ids, 0, $limit );
	}

	/**
	 * Fetch products bought by the user.
	 *
	 * @param int $user_id User ID.
	 * @return array<int> List of product IDs.
	 */
	private function get_user_purchased_products( int $user_id ): array {
		$orders = wc_get_orders(
			[
				'customer' => $user_id,
				'status'   => [ 'completed', 'processing' ],
				'limit'    => 10,
				'return'   => 'ids',
			]
		);

		$purchased_ids = [];
		foreach ( $orders as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( $order ) {
				foreach ( $order->get_items() as $item ) {
					$purchased_ids[] = (int) $item->get_product_id();
				}
			}
		}

		return array_filter( array_unique( $purchased_ids ) );
	}

	/**
	 * Extract categories from product IDs and determine the highest frequency ones.
	 *
	 * @param array<int> $product_ids List of product IDs.
	 * @return array<int> Favorite category IDs.
	 */
	private function get_favorite_categories( array $product_ids ): array {
		$category_counts = [];

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( $product ) {
				$cats = $product->get_category_ids();
				foreach ( $cats as $cat_id ) {
					if ( ! isset( $category_counts[ $cat_id ] ) ) {
						$category_counts[ $cat_id ] = 0;
					}
					$category_counts[ $cat_id ]++;
				}
			}
		}

		arsort( $category_counts );

		// Return top 3 category IDs
		return array_slice( array_keys( $category_counts ), 0, 3 );
	}

	/**
	 * Get products from target categories sorted by sales popularity.
	 *
	 * @param array<int> $category_ids Category IDs.
	 * @param array<int> $exclude_ids  IDs to exclude.
	 * @param int        $limit        Result count.
	 * @return array<int> List of product IDs.
	 */
	private function get_popular_products_in_categories( array $category_ids, array $exclude_ids, int $limit = 8 ): array {
		$args = [
			'category'     => array_map(
				function( $cat_id ) {
					$term = get_term( $cat_id, 'product_cat' );
					return $term ? $term->slug : '';
				},
				$category_ids
			),
			'exclude'      => $exclude_ids,
			'limit'        => $limit,
			'status'       => 'publish',
			'orderby'      => 'meta_value_num',
			'meta_key'     => 'total_sales',
			'order'        => 'DESC',
			'return'       => 'ids',
		];

		return array_map( 'intval', wc_get_products( $args ) );
	}
}
