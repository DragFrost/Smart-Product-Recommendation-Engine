<?php
/**
 * SPRE Tracking REST Controller.
 *
 * @package SPRE\API
 */

declare(strict_types=1);

namespace SPRE\API;

use WP_REST_Request;
use WP_REST_Response;
use SPRE\Database\ViewsRepository;
use SPRE\Database\AnalyticsRepository;
use SPRE\Tracking\UserTracker;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TrackingController
 *
 * Processes tracking requests for front-end events.
 */
class TrackingController extends BaseController {

	/**
	 * Views repository.
	 *
	 * @var ViewsRepository
	 */
	private ViewsRepository $views_repo;

	/**
	 * Analytics repository.
	 *
	 * @var AnalyticsRepository
	 */
	private AnalyticsRepository $analytics_repo;

	/**
	 * User tracker.
	 *
	 * @var UserTracker
	 */
	private UserTracker $tracker;

	/**
	 * Constructor.
	 *
	 * @param ViewsRepository     $views_repo     Views.
	 * @param AnalyticsRepository $analytics_repo Analytics.
	 * @param UserTracker         $tracker        Tracker.
	 */
	public function __construct( ViewsRepository $views_repo, AnalyticsRepository $analytics_repo, UserTracker $tracker ) {
		$this->views_repo     = $views_repo;
		$this->analytics_repo = $analytics_repo;
		$this->tracker        = $tracker;
	}

	/**
	 * Register REST routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/track',
			[
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'track_event' ],
					'permission_callback' => '__return_true', // Public access for frontend JS tracking
					'args'                => [
						'event_type'        => [
							'required'          => true,
							'sanitize_callback' => 'sanitize_key',
						],
						'product_id'        => [
							'required'          => true,
							'sanitize_callback' => 'absint',
						],
						'source_product_id' => [
							'required'          => false,
							'sanitize_callback' => 'absint',
						],
						'widget_type'       => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_key',
						],
						'ab_test_id'        => [
							'required'          => false,
							'sanitize_callback' => 'absint',
						],
						'ab_variation'      => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_key',
						],
					],
				],
			]
		);
	}

	/**
	 * Log a front-end activity event.
	 *
	 * @param WP_REST_Request $request Request parameters.
	 * @return WP_REST_Response Success verification.
	 */
	public function track_event( WP_REST_Request $request ): WP_REST_Response {
		// Verify tracking consent
		if ( ! $this->tracker->is_tracking_allowed() ) {
			return new WP_REST_Response( [ 'success' => false, 'message' => 'Tracking opt-out' ], 200 );
		}

		$event_type        = $request->get_param( 'event_type' );
		$product_id        = $request->get_param( 'product_id' );
		$source_product_id = $request->get_param( 'source_product_id' ) ? (int) $request->get_param( 'source_product_id' ) : null;
		$widget_type       = $request->get_param( 'widget_type' ) ? sanitize_key( $request->get_param( 'widget_type' ) ) : 'direct';
		$ab_test_id        = $request->get_param( 'ab_test_id' ) ? (int) $request->get_param( 'ab_test_id' ) : null;
		$ab_variation      = $request->get_param( 'ab_variation' ) ? sanitize_key( $request->get_param( 'ab_variation' ) ) : null;

		$session_hash = $this->tracker->get_session_hash();
		$user_id      = get_current_user_id();

		// 1. If it's a page view on a product detail page, log in the views repository
		if ( $event_type === 'view' ) {
			$this->views_repo->log_view( $product_id, $session_hash, $user_id );
		}

		// 2. Log in the general analytics tracking table
		$this->analytics_repo->log_event(
			$event_type,
			$product_id,
			$source_product_id,
			$widget_type,
			$session_hash,
			$user_id > 0 ? $user_id : null,
			$ab_test_id,
			$ab_variation,
			null, // No order ID at this point
			0.0   // No revenue at this point
		);

		return new WP_REST_Response( [ 'success' => true ], 200 );
	}
}
