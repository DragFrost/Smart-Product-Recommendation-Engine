<?php
/**
 * SPRE Data Collector.
 *
 * @package SPRE\Tracking
 */

declare(strict_types=1);

namespace SPRE\Tracking;

use SPRE\Database\AnalyticsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DataCollector
 *
 * Hooks into WooCommerce purchase flows to record widget conversions.
 */
class DataCollector {

	/**
	 * Analytics Repository.
	 *
	 * @var AnalyticsRepository
	 */
	private AnalyticsRepository $analytics_repo;

	/**
	 * User Tracker helper.
	 *
	 * @var UserTracker
	 */
	private UserTracker $tracker;

	/**
	 * Constructor.
	 *
	 * @param AnalyticsRepository $analytics_repo Analytics repository.
	 * @param UserTracker         $tracker        User tracker.
	 */
	public function __construct( AnalyticsRepository $analytics_repo, UserTracker $tracker ) {
		$this->analytics_repo = $analytics_repo;
		$this->tracker        = $tracker;
	}

	/**
	 * Register actions.
	 */
	public function register_hooks(): void {
		// Hook into the checkout completed landing page to trigger attribution modeling
		add_action( 'woocommerce_thankyou', [ $this, 'track_order_completion' ], 10, 1 );
	}

	/**
	 * Process order conversions by matching purchased items to session clicks.
	 *
	 * @param int $order_id WooCommerce Order ID.
	 */
	public function track_order_completion( $order_id ): void {
		$order_id = (int) $order_id;
		if ( $order_id <= 0 ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Prevent double-logging if page is reloaded
		if ( $order->get_meta( '_spre_attributed' ) === 'yes' ) {
			return;
		}

		$revenue      = (float) $order->get_total();
		$session_hash = $this->tracker->get_session_hash();
		$user_id      = (int) $order->get_customer_id();

		// Record conversion metrics in analytics db
		$this->analytics_repo->track_order_conversions( $order_id, $revenue, $session_hash, $user_id );

		// Set flag to avoid duplicates
		$order->update_meta_data( '_spre_attributed', 'yes' );
		$order->save();
	}
}
