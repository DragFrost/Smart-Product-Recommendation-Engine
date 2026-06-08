<?php
/**
 * SPRE Recommendation Rule Engine.
 *
 * @package SPRE\Recommendation
 */

declare(strict_types=1);

namespace SPRE\Recommendation;

use SPRE\Database\RulesRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RuleEngine
 *
 * Evaluates custom recommendation rules set by the shop admin.
 */
class RuleEngine {

	/**
	 * Rules Repository.
	 *
	 * @var RulesRepository
	 */
	private RulesRepository $rules_repo;

	/**
	 * Constructor.
	 *
	 * @param RulesRepository $rules_repo Repository instance.
	 */
	public function __construct( RulesRepository $rules_repo ) {
		$this->rules_repo = $rules_repo;
	}

	/**
	 * Check and apply matching rules to generate recommendation overrides.
	 *
	 * Evaluates rules by priority and returns the first matching rule's recommendations.
	 *
	 * @param array{product_id?: int, cart_items?: array<int>, limit?: int} $context
	 * @return array<int> Recommendation product IDs or empty array if no rules match.
	 */
	public function get_rule_recommendations( array $context ): array {
		$active_rules = $this->rules_repo->get_active_rules();
		if ( empty( $active_rules ) ) {
			return [];
		}

		$current_product_id = isset( $context['product_id'] ) ? (int) $context['product_id'] : 0;
		$cart_product_ids   = isset( $context['cart_items'] ) ? (array) $context['cart_items'] : [];

		// If on cart page or cart context is not passed, let's load it from WooCommerce session directly as a fallback
		if ( empty( $cart_product_ids ) && function_exists( 'WC' ) && WC()->cart ) {
			foreach ( WC()->cart->get_cart() as $cart_item ) {
				$cart_product_ids[] = (int) $cart_item['product_id'];
			}
		}

		foreach ( $active_rules as $rule ) {
			if ( $this->evaluate_rule( $rule['conditions'], $current_product_id, $cart_product_ids ) ) {
				$recommended = $this->execute_actions( $rule['actions'], $context['limit'] ?? 4 );
				if ( ! empty( $recommended ) ) {
					return $recommended;
				}
			}
		}

		return [];
	}

	/**
	 * Evaluate the rule conditions array against the current product and cart contents.
	 *
	 * @param array $conditions       List of conditions (e.g. [['type' => 'viewing_category', 'value' => 12]]).
	 * @param int   $current_prod_id  Product page context ID.
	 * @param array $cart_product_ids IDs currently in the cart.
	 * @return bool True if all conditions match (AND logic).
	 */
	private function evaluate_rule( array $conditions, int $current_prod_id, array $cart_product_ids ): bool {
		if ( empty( $conditions ) ) {
			return false;
		}

		foreach ( $conditions as $cond ) {
			$type  = $cond['type'] ?? '';
			$value = $cond['value'] ?? null;

			switch ( $type ) {
				case 'viewing_product':
					if ( $current_prod_id !== (int) $value ) {
						return false;
					}
					break;

				case 'viewing_category':
					if ( $current_prod_id <= 0 ) {
						return false;
					}
					$product = wc_get_product( $current_prod_id );
					if ( ! $product || ! in_array( (int) $value, $product->get_category_ids(), true ) ) {
						return false;
					}
					break;

				case 'cart_contains_product':
					if ( ! in_array( (int) $value, $cart_product_ids, true ) ) {
						return false;
					}
					break;

				case 'cart_contains_category':
					$cat_matched = false;
					foreach ( $cart_product_ids as $cart_prod_id ) {
						$product = wc_get_product( $cart_prod_id );
						if ( $product && in_array( (int) $value, $product->get_category_ids(), true ) ) {
							$cat_matched = true;
							break;
						}
					}
					if ( ! $cat_matched ) {
						return false;
					}
					break;

				default:
					return false; // Unknown condition type
			}
		}

		return true;
	}

	/**
	 * Execute the rule actions to generate candidate recommendation product IDs.
	 *
	 * @param array $actions Actions configuration.
	 * @param int   $limit   Maximum recommendations to retrieve.
	 * @return array<int> Product IDs.
	 */
	private function execute_actions( array $actions, int $limit ): array {
		$recommended_ids = [];

		foreach ( $actions as $action ) {
			$type  = $action['type'] ?? '';
			$value = $action['value'] ?? null;

			switch ( $type ) {
				case 'recommend_products':
					if ( is_array( $value ) ) {
						$recommended_ids = array_merge( $recommended_ids, array_map( 'intval', $value ) );
					}
					break;

				case 'recommend_category':
					$cat_id = (int) $value;
					if ( $cat_id > 0 ) {
						// Retrieve products in category
						$term = get_term( $cat_id, 'product_cat' );
						if ( $term ) {
							$products = wc_get_products(
								[
									'category' => [ $term->slug ],
									'limit'    => $limit,
									'status'   => 'publish',
									'return'   => 'ids',
								]
							);
							$recommended_ids = array_merge( $recommended_ids, array_map( 'intval', $products ) );
						}
					}
					break;
			}
		}

		return array_slice( array_unique( $recommended_ids ), 0, $limit );
	}
}
