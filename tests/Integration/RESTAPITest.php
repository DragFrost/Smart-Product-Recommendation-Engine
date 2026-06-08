<?php
/**
 * SPRE REST API Integration Tests.
 *
 * @package SPRE\Tests\Integration
 */

declare(strict_types=1);

namespace SPRE\Tests\Integration;

use WP_UnitTestCase;
use WP_REST_Request;

/**
 * Class RESTAPITest
 *
 * Checks REST routes and security permissions.
 */
class RESTAPITest extends WP_UnitTestCase {

	/**
	 * Verify that admin REST routes require correct user permissions.
	 */
	public function test_admin_routes_require_authorization(): void {
		// Mock a GET request to admin analytics without authentication
		$request = new WP_REST_Request( 'GET', '/spre/v1/admin/analytics' );
		$request->set_param( 'start_date', '2026-05-01' );
		$request->set_param( 'end_date', '2026-06-01' );
		
		$response = rest_do_request( $request );
		
		// Unauthenticated requests must trigger forbidden errors (401 or 403)
		$this->assertContains( $response->get_status(), [ 401, 403 ] );
	}

	/**
	 * Verify that public recommendation routes return success.
	 */
	public function test_public_recommendations_endpoint(): void {
		$request = new WP_REST_Request( 'GET', '/spre/v1/recommendations' );
		$request->set_param( 'widget_type', 'trending' );
		
		$response = rest_do_request( $request );
		
		// Public route should be reachable (even if catalog is empty, returns 200 OK with empty array)
		$this->assertEquals( 200, $response->get_status() );
		
		$data = $response->get_data();
		$this->assertIsArray( $data );
	}
}
