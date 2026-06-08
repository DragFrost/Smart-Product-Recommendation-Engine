<?php
/**
 * SPRE Recommendations REST Controller.
 *
 * @package SPRE\API
 */

declare(strict_types=1);

namespace SPRE\API;

use WP_REST_Request;
use WP_REST_Response;
use SPRE\Recommendation\Engine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RecommendationsController
 *
 * Public endpoints for fetching product recommendations.
 */
class RecommendationsController extends BaseController {

	/**
	 * Recommendation Engine.
	 *
	 * @var Engine
	 */
	private Engine $engine;

	/**
	 * Constructor.
	 *
	 * @param Engine $engine Engine instance.
	 */
	public function __construct( Engine $engine ) {
		$this->engine = $engine;
	}

	/**
	 * Register REST routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/recommendations',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'get_recommendations' ],
					'permission_callback' => '__return_true', // Public access for widgets
					'args'                => [
						'widget_type' => [
							'required'          => true,
							'sanitize_callback' => 'sanitize_key',
						],
						'product_id'  => [
							'required'          => false,
							'sanitize_callback' => 'absint',
						],
						'limit'       => [
							'required'          => false,
							'default'           => 4,
							'sanitize_callback' => 'absint',
						],
						'period'      => [
							'required'          => false,
							'default'           => '7d',
							'sanitize_callback' => 'sanitize_key',
						],
					],
				],
			]
		);
	}

	/**
	 * Retrieve recommendations for the current frontend context.
	 *
	 * @param WP_REST_Request $request Request details.
	 * @return WP_REST_Response JSON payload of formatted products.
	 */
	public function get_recommendations( WP_REST_Request $request ): WP_REST_Response {
		$widget_type = $request->get_param( 'widget_type' );
		$product_id  = $request->get_param( 'product_id' );
		$limit       = $request->get_param( 'limit' );
		$period      = $request->get_param( 'period' );

		$context = [
			'limit'  => $limit,
			'period' => $period,
		];

		if ( $product_id > 0 ) {
			$context['product_id'] = $product_id;
		}

		$results = $this->engine->get_recommendations( $widget_type, $context );
		$products = $results['products'];
		$ab_test_id = $results['ab_test_id'];
		$ab_variation = $results['ab_variation'];

		$formatted = [];
		foreach ( $products as $product ) {
			$formatted[] = [
				'id'            => $product->get_id(),
				'name'          => $product->get_name(),
				'price_html'    => $product->get_price_html(),
				'permalink'     => $product->get_permalink(),
				'image'         => wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_thumbnail' ) ?: wc_placeholder_img_src(),
				'add_to_cart'   => $product->add_to_cart_url(),
				'rating_html'   => wc_get_rating_html( $product->get_average_rating() ),
				'ab_test_id'    => $ab_test_id,
				'ab_variation'  => $ab_variation,
				'widget_type'   => $widget_type,
			];
		}

		return new WP_REST_Response( $formatted, 200 );
	}
}
