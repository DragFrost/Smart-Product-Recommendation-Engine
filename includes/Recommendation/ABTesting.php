<?php
/**
 * SPRE A/B Testing Module.
 *
 * @package SPRE\Recommendation
 */

declare(strict_types=1);

namespace SPRE\Recommendation;

use SPRE\Database\ABTestsRepository;
use SPRE\Tracking\UserTracker;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ABTesting
 *
 * Manages experiment assignment and variation splitting.
 */
class ABTesting {

	/**
	 * AB Tests repository.
	 *
	 * @var ABTestsRepository
	 */
	private ABTestsRepository $ab_repo;

	/**
	 * User Tracker helper.
	 *
	 * @var UserTracker
	 */
	private UserTracker $tracker;

	/**
	 * Constructor.
	 *
	 * @param ABTestsRepository $ab_repo Repository.
	 * @param UserTracker       $tracker Tracker.
	 */
	public function __construct( ABTestsRepository $ab_repo, UserTracker $tracker ) {
		$this->ab_repo = $ab_repo;
		$this->tracker = $tracker;
	}

	/**
	 * Fetch the currently active experiment details.
	 *
	 * @return array<string, mixed>|null Active test or null.
	 */
	public function get_active_test(): ?array {
		return $this->ab_repo->get_active_test();
	}

	/**
	 * Assign a user deterministically to variation 'A' or 'B' for an active experiment.
	 *
	 * Uses a stateless hashing approach on the user's tracking session and experiment ID.
	 *
	 * @param array $test Active test structure.
	 * @return string 'A' or 'B'.
	 */
	public function get_variation_for_user( array $test ): string {
		$session_hash = $this->tracker->get_session_hash();
		$test_id      = (int) ( $test['id'] ?? 0 );

		if ( empty( $session_hash ) || $test_id <= 0 ) {
			return 'A'; // Safe fallback
		}

		// Stateless hash-based assignment.
		// Combines session hash and test ID, hashes it, takes the first 8 characters,
		// converts to decimal, and computes the modulus against 100.
		$hash       = md5( $session_hash . '_' . $test_id );
		$hex_segment = substr( $hash, 0, 8 );
		$dec_value   = hexdec( $hex_segment );
		$percentage  = $dec_value % 100;

		$split_limit = (int) ( $test['traffic_split'] ?? 50 );

		return $percentage < $split_limit ? 'A' : 'B';
	}
}
