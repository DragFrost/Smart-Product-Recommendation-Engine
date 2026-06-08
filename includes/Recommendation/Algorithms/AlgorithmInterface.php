<?php
/**
 * SPRE Recommendation Algorithm Interface.
 *
 * @package SPRE\Recommendation\Algorithms
 */

declare(strict_types=1);

namespace SPRE\Recommendation\Algorithms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface AlgorithmInterface
 *
 * Contract for recommendation engine algorithms.
 */
interface AlgorithmInterface {

	/**
	 * Compute or fetch recommended product IDs based on the provided context.
	 *
	 * @param array{
	 *     product_id?: int,
	 *     user_id?: int,
	 *     session_hash?: string,
	 *     limit?: int,
	 *     exclude_ids?: array<int>
	 * } $context Execution context mapping details.
	 * @return array<int> Sorted list of recommended product IDs.
	 */
	public function get_recommendations( array $context ): array;
}
