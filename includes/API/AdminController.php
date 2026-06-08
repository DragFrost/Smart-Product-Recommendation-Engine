<?php
/**
 * SPRE Admin REST Controller.
 *
 * @package SPRE\API
 */

declare(strict_types=1);

namespace SPRE\API;

use WP_REST_Request;
use WP_REST_Response;
use SPRE\Database\AnalyticsRepository;
use SPRE\Database\RulesRepository;
use SPRE\Database\ABTestsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AdminController
 *
 * Handles dashboard data, rule management, and experiment control.
 */
class AdminController extends BaseController {

	/**
	 * Analytics repository.
	 *
	 * @var AnalyticsRepository
	 */
	private AnalyticsRepository $analytics_repo;

	/**
	 * Rules repository.
	 *
	 * @var RulesRepository
	 */
	private RulesRepository $rules_repo;

	/**
	 * AB Tests repository.
	 *
	 * @var ABTestsRepository
	 */
	private ABTestsRepository $ab_repo;

	/**
	 * Constructor.
	 *
	 * @param AnalyticsRepository $analytics_repo Analytics.
	 * @param RulesRepository     $rules_repo     Rules.
	 * @param ABTestsRepository   $ab_repo        AB Tests.
	 */
	public function __construct(
		AnalyticsRepository $analytics_repo,
		RulesRepository $rules_repo,
		ABTestsRepository $ab_repo
	) {
		$this->analytics_repo = $analytics_repo;
		$this->rules_repo     = $rules_repo;
		$this->ab_repo        = $ab_repo;
	}

	/**
	 * Register REST routes.
	 */
	public function register_routes(): void {
		// 1. Dashboard analytics endpoint
		register_rest_route(
			$this->namespace,
			'/admin/analytics',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'get_analytics' ],
					'permission_callback' => [ $this, 'check_admin_permissions' ],
					'args'                => [
						'start_date' => [
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						],
						'end_date'   => [
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						],
					],
				],
			]
		);

		// 2. Rules endpoints
		register_rest_route(
			$this->namespace,
			'/admin/rules',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'get_rules' ],
					'permission_callback' => [ $this, 'check_admin_permissions' ],
				],
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'create_rule' ],
					'permission_callback' => [ $this, 'check_admin_permissions' ],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/admin/rules/(?P<id>\d+)',
			[
				[
					'methods'             => 'PUT',
					'callback'            => [ $this, 'update_rule' ],
					'permission_callback' => [ $this, 'check_admin_permissions' ],
				],
				[
					'methods'             => 'DELETE',
					'callback'            => [ $this, 'delete_rule' ],
					'permission_callback' => [ $this, 'check_admin_permissions' ],
				],
			]
		);

		// 3. A/B Tests endpoints
		register_rest_route(
			$this->namespace,
			'/admin/ab-tests',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'get_ab_tests' ],
					'permission_callback' => [ $this, 'check_admin_permissions' ],
				],
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'create_ab_test' ],
					'permission_callback' => [ $this, 'check_admin_permissions' ],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/admin/ab-tests/(?P<id>\d+)',
			[
				[
					'methods'             => 'DELETE',
					'callback'            => [ $this, 'delete_ab_test' ],
					'permission_callback' => [ $this, 'check_admin_permissions' ],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/admin/ab-tests/(?P<id>\d+)/start',
			[
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'start_ab_test' ],
					'permission_callback' => [ $this, 'check_admin_permissions' ],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/admin/ab-tests/(?P<id>\d+)/end',
			[
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'end_ab_test' ],
					'permission_callback' => [ $this, 'check_admin_permissions' ],
				],
			]
		);

		// 4. Utility selectors
		register_rest_route(
			$this->namespace,
			'/admin/products/search',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'search_products' ],
					'permission_callback' => [ $this, 'check_admin_permissions' ],
					'args'                => [
						'q' => [
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						],
					],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/admin/categories',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'get_categories' ],
					'permission_callback' => [ $this, 'check_admin_permissions' ],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/admin/settings',
			[
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'save_settings' ],
					'permission_callback' => [ $this, 'check_admin_permissions' ],
				],
			]
		);
	}

	/**
	 * Retrieve analytics for dashboard charts.
	 */
	public function get_analytics( WP_REST_Request $request ): WP_REST_Response {
		$start = $request->get_param( 'start_date' );
		$end   = $request->get_param( 'end_date' );

		$stats = $this->analytics_repo->get_dashboard_stats( $start, $end );

		return new WP_REST_Response( $stats, 200 );
	}

	/**
	 * Retrieve rules list.
	 */
	public function get_rules( WP_REST_Request $request ): WP_REST_Response {
		$rules = $this->rules_repo->get_all_rules();
		return new WP_REST_Response( $rules, 200 );
	}

	/**
	 * Create a new rule.
	 */
	public function create_rule( WP_REST_Request $request ): WP_REST_Response {
		$params = $request->get_json_params();

		$name       = isset( $params['name'] ) ? sanitize_text_field( $params['name'] ) : '';
		$conditions = isset( $params['conditions'] ) ? (array) $params['conditions'] : [];
		$actions    = isset( $params['actions'] ) ? (array) $params['actions'] : [];
		$priority   = isset( $params['priority'] ) ? (int) $params['priority'] : 0;

		if ( empty( $name ) || empty( $conditions ) || empty( $actions ) ) {
			return new WP_REST_Response( [ 'message' => 'Missing required fields' ], 400 );
		}

		$id = $this->rules_repo->create_rule( $name, $conditions, $actions, $priority );

		return new WP_REST_Response( [ 'success' => true, 'id' => $id ], 200 );
	}

	/**
	 * Update an existing rule.
	 */
	public function update_rule( WP_REST_Request $request ): WP_REST_Response {
		$id     = (int) $request->get_param( 'id' );
		$params = $request->get_json_params();

		$name       = isset( $params['name'] ) ? sanitize_text_field( $params['name'] ) : '';
		$conditions = isset( $params['conditions'] ) ? (array) $params['conditions'] : [];
		$actions    = isset( $params['actions'] ) ? (array) $params['actions'] : [];
		$priority   = isset( $params['priority'] ) ? (int) $params['priority'] : 0;
		$status     = isset( $params['status'] ) ? sanitize_key( $params['status'] ) : 'active';

		$success = $this->rules_repo->update_rule( $id, $name, $conditions, $actions, $priority, $status );

		return new WP_REST_Response( [ 'success' => $success ], 200 );
	}

	/**
	 * Delete a rule.
	 */
	public function delete_rule( WP_REST_Request $request ): WP_REST_Response {
		$id      = (int) $request->get_param( 'id' );
		$success = $this->rules_repo->delete_rule( $id );

		return new WP_REST_Response( [ 'success' => $success ], 200 );
	}

	/**
	 * Retrieve all A/B tests.
	 */
	public function get_ab_tests( WP_REST_Request $request ): WP_REST_Response {
		$tests = $this->ab_repo->get_all_tests();

		// Attach performance results for each test
		foreach ( $tests as &$test ) {
			$test['metrics'] = $this->analytics_repo->get_ab_test_stats( $test['id'] );
		}

		return new WP_REST_Response( $tests, 200 );
	}

	/**
	 * Create an A/B test.
	 */
	public function create_ab_test( WP_REST_Request $request ): WP_REST_Response {
		$params = $request->get_json_params();

		$name         = isset( $params['name'] ) ? sanitize_text_field( $params['name'] ) : '';
		$algorithm_a  = isset( $params['algorithm_a'] ) ? sanitize_key( $params['algorithm_a'] ) : '';
		$algorithm_b  = isset( $params['algorithm_b'] ) ? sanitize_key( $params['algorithm_b'] ) : '';
		$traffic_split = isset( $params['traffic_split'] ) ? (int) $params['traffic_split'] : 50;

		if ( empty( $name ) || empty( $algorithm_a ) || empty( $algorithm_b ) ) {
			return new WP_REST_Response( [ 'message' => 'Missing parameter values' ], 400 );
		}

		$id = $this->ab_repo->create_test( $name, $algorithm_a, $algorithm_b, $traffic_split );

		return new WP_REST_Response( [ 'success' => true, 'id' => $id ], 200 );
	}

	/**
	 * Start an A/B test.
	 */
	public function start_ab_test( WP_REST_Request $request ): WP_REST_Response {
		$id      = (int) $request->get_param( 'id' );
		$success = $this->ab_repo->start_test( $id );

		return new WP_REST_Response( [ 'success' => $success ], 200 );
	}

	/**
	 * End an A/B test.
	 */
	public function end_ab_test( WP_REST_Request $request ): WP_REST_Response {
		$id      = (int) $request->get_param( 'id' );
		$success = $this->ab_repo->end_test( $id );

		return new WP_REST_Response( [ 'success' => $success ], 200 );
	}

	/**
	 * Delete an A/B test.
	 */
	public function delete_ab_test( WP_REST_Request $request ): WP_REST_Response {
		$id      = (int) $request->get_param( 'id' );
		$success = $this->ab_repo->delete_test( $id );

		return new WP_REST_Response( [ 'success' => $success ], 200 );
	}

	/**
	 * Autocomplete search products for visual rule builder dropdown.
	 */
	public function search_products( WP_REST_Request $request ): WP_REST_Response {
		$query = $request->get_param( 'q' );

		$products = wc_get_products(
			[
				's'      => $query,
				'limit'  => 10,
				'status' => 'publish',
			]
		);

		$formatted = [];
		foreach ( $products as $product ) {
			$formatted[] = [
				'id'   => $product->get_id(),
				'name' => $product->get_name(),
				'sku'  => $product->get_sku(),
			];
		}

		return new WP_REST_Response( $formatted, 200 );
	}

	/**
	 * Fetch WooCommerce product categories for autocomplete selector.
	 */
	public function get_categories( WP_REST_Request $request ): WP_REST_Response {
		$terms = get_terms(
			[
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
			]
		);

		if ( is_wp_error( $terms ) ) {
			return new WP_REST_Response( [], 200 );
		}

		$formatted = [];
		foreach ( $terms as $term ) {
			$formatted[] = [
				'id'   => $term->term_id,
				'name' => $term->name,
				'slug' => $term->slug,
			];
		}

		return new WP_REST_Response( $formatted, 200 );
	}

	/**
	 * Save general configurations.
	 */
	public function save_settings( \WP_REST_Request $request ): \WP_REST_Response {
		$params = $request->get_json_params();

		$settings = [
			'replace_default_related' => isset( $params['replace_default_related'] ) ? (bool) $params['replace_default_related'] : true,
			'show_fbt_in_summary'     => isset( $params['show_fbt_in_summary'] ) ? (bool) $params['show_fbt_in_summary'] : true,
			'require_cookie_consent'  => isset( $params['require_cookie_consent'] ) ? (bool) $params['require_cookie_consent'] : false,
			'related_limit'           => isset( $params['related_limit'] ) ? min( 10, max( 1, (int) $params['related_limit'] ) ) : 4,
			'fbt_limit'               => isset( $params['fbt_limit'] ) ? min( 10, max( 1, (int) $params['fbt_limit'] ) ) : 3,
			'show_badges'             => isset( $params['show_badges'] ) ? (bool) $params['show_badges'] : true,
			'show_excerpt'            => isset( $params['show_excerpt'] ) ? (bool) $params['show_excerpt'] : false,
			'show_rating'             => isset( $params['show_rating'] ) ? (bool) $params['show_rating'] : true,
			'show_price'              => isset( $params['show_price'] ) ? (bool) $params['show_price'] : true,
			'show_add_to_cart'        => isset( $params['show_add_to_cart'] ) ? (bool) $params['show_add_to_cart'] : true,
			'add_to_cart_text'        => isset( $params['add_to_cart_text'] ) ? sanitize_text_field( $params['add_to_cart_text'] ) : '',
			'primary_color'           => isset( $params['primary_color'] ) ? sanitize_text_field( $params['primary_color'] ) : '',
			'text_color'              => isset( $params['text_color'] ) ? sanitize_text_field( $params['text_color'] ) : '',
			'price_color'             => isset( $params['price_color'] ) ? sanitize_text_field( $params['price_color'] ) : '',
			'badge_bg_color'          => isset( $params['badge_bg_color'] ) ? sanitize_text_field( $params['badge_bg_color'] ) : '',
			'btn_bg_color'            => isset( $params['btn_bg_color'] ) ? sanitize_text_field( $params['btn_bg_color'] ) : '',
			'btn_text_color'          => isset( $params['btn_text_color'] ) ? sanitize_text_field( $params['btn_text_color'] ) : '',
			'layout_mode'             => isset( $params['layout_mode'] ) ? sanitize_key( $params['layout_mode'] ) : 'grid',
			'custom_css'              => isset( $params['custom_css'] ) ? wp_strip_all_tags( $params['custom_css'] ) : '',
		];

		update_option( 'spre_settings', $settings );

		return new WP_REST_Response( [ 'success' => true ], 200 );
	}
}
