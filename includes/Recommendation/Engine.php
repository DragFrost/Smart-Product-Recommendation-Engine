<?php
/**
 * SPRE Recommendation Engine.
 *
 * @package SPRE\Recommendation
 */

declare(strict_types=1);

namespace SPRE\Recommendation;

use SPRE\Recommendation\Algorithms\AlgorithmInterface;
use SPRE\Core\Container;
use WC_Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Engine
 *
 * Directs traffic, applies rules, splits A/B tests, and queries algorithms.
 */
class Engine {

	/**
	 * Rule Engine.
	 *
	 * @var RuleEngine
	 */
	private RuleEngine $rule_engine;

	/**
	 * A/B Testing module.
	 *
	 * @var ABTesting
	 */
	private ABTesting $ab_testing;

	/**
	 * Registered algorithms.
	 *
	 * @var array<string, AlgorithmInterface>
	 */
	private array $algorithms;

	/**
	 * Constructor.
	 *
	 * @param RuleEngine $rule_engine Rule engine.
	 * @param ABTesting  $ab_testing  A/B testing.
	 * @param array      $algorithms  Map of registered algorithms.
	 */
	public function __construct( RuleEngine $rule_engine, ABTesting $ab_testing, array $algorithms ) {
		$this->rule_engine = $rule_engine;
		$this->ab_testing  = $ab_testing;
		$this->algorithms  = $algorithms;
	}

	/**
	 * Fetch products recommendation matching a specific widget request.
	 *
	 * @param string $widget_type Widget type ('fbt', 'related', 'personalized', 'trending').
	 * @param array  $context     Custom parameters.
	 * @return array{products: array<WC_Product>, ab_test_id: int|null, ab_variation: string|null} Result wrapper.
	 */
	public function get_recommendations( string $widget_type, array $context = [] ): array {
		$limit = isset( $context['limit'] ) ? (int) $context['limit'] : 4;

		// 1. Fetch user tracking info
		$tracker      = Container::getInstance()->get( \SPRE\Tracking\UserTracker::class );
		$user_id      = get_current_user_id();
		$session_hash = $tracker->get_session_hash();

		$context['user_id']      = $context['user_id'] ?? $user_id;
		$context['session_hash'] = $context['session_hash'] ?? $session_hash;
		$context['limit']        = $limit;

		$ab_test_id   = null;
		$ab_variation = null;

		// 2. Evaluate Rule Builder Overrides (High priority)
		$rule_product_ids = $this->rule_engine->get_rule_recommendations( $context );
		if ( ! empty( $rule_product_ids ) ) {
			return [
				'products'     => $this->populate_products( $rule_product_ids ),
				'ab_test_id'   => null,
				'ab_variation' => null,
			];
		}

		// 3. Determine if A/B Testing is running (only for 'related' and 'personalized' widgets)
		$selected_algorithm = $widget_type;
		if ( in_array( $widget_type, [ 'related', 'personalized' ], true ) ) {
			$active_test = $this->ab_testing->get_active_test();
			if ( $active_test ) {
				$ab_test_id   = $active_test['id'];
				$ab_variation = $this->ab_testing->get_variation_for_user( $active_test );
				$selected_algorithm = $ab_variation === 'B' ? $active_test['algorithm_b'] : $active_test['algorithm_a'];
			}
		}

		// 4. Generate Cache key based on parameters
		$cache_key = $this->generate_cache_key( $widget_type, $selected_algorithm, $context, $ab_variation );
		$recommended_ids = get_transient( $cache_key );

		if ( false === $recommended_ids ) {
			// Query algorithm
			$algo = $this->algorithms[ $selected_algorithm ] ?? null;
			if ( $algo ) {
				$recommended_ids = $algo->get_recommendations( $context );
			} else {
				$recommended_ids = [];
			}

			// Fallbacks
			if ( empty( $recommended_ids ) ) {
				if ( $widget_type === 'personalized' ) {
					// Fallback to trending
					$recommended_ids = $this->algorithms['trending']->get_recommendations( $context );
				} elseif ( $widget_type === 'fbt' || $widget_type === 'related' ) {
					// Fallback to similar related products
					$recommended_ids = $this->algorithms['related']->get_recommendations( $context );
					if ( empty( $recommended_ids ) ) {
						$recommended_ids = $this->algorithms['trending']->get_recommendations( $context );
					}
				}
			}

			// Save to Transient (Cache for 1 hour, shorter for personalized)
			$expiration = $widget_type === 'personalized' ? 15 * MINUTE_IN_SECONDS : HOUR_IN_SECONDS;
			set_transient( $cache_key, $recommended_ids, $expiration );
		}

		if ( ! is_array( $recommended_ids ) ) {
			$recommended_ids = [];
		}

		return [
			'products'     => $this->populate_products( $recommended_ids ),
			'ab_test_id'   => $ab_test_id,
			'ab_variation' => $ab_variation,
		];
	}

	/**
	 * Generate cached keys depending on algorithms and widget contexts.
	 *
	 * @param string      $widget     Widget name.
	 * @param string      $algorithm  Algorithm slug.
	 * @param array       $context    Context array.
	 * @param string|null $variation  A/B variation.
	 * @return string Cache transient key.
	 */
	private function generate_cache_key( string $widget, string $algorithm, array $context, ?string $variation ): string {
		$prod_id  = $context['product_id'] ?? 0;
		$user_id  = $context['user_id'] ?? 0;
		$limit    = $context['limit'] ?? 4;
		$period   = $context['period'] ?? '7d';
		$sess_hash = substr( $context['session_hash'] ?? '', 0, 8 );

		// Clean keys for transients (max length 172 chars)
		if ( $widget === 'personalized' ) {
			$id_suffix = $user_id > 0 ? "u_{$user_id}" : "s_{$sess_hash}";
			return "spre_c_ps_{$id_suffix}_l{$limit}";
		}

		if ( $widget === 'trending' ) {
			return "spre_c_tr_{$period}_l{$limit}";
		}

		$var_suffix = $variation ? "_{$variation}" : '';
		return "spre_c_{$widget}_{$prod_id}_l{$limit}{$var_suffix}";
	}

	/**
	 * Fetch WC_Product objects for a list of product IDs in one single batch.
	 *
	 * Avoids N+1 queries by utilizing wc_get_products eager-loading.
	 *
	 * @param array<int> $product_ids Product IDs.
	 * @return array<WC_Product> WooCommerce product objects.
	 */
	private function populate_products( array $product_ids ): array {
		if ( empty( $product_ids ) ) {
			return [];
		}

		return wc_get_products(
			[
				'include' => $product_ids,
				'status'  => 'publish',
				'return'  => 'objects',
			]
		);
	}
}
