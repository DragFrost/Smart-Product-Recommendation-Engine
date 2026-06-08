<?php
/**
 * SPRE API Base Controller.
 *
 * @package SPRE\API
 */

declare(strict_types=1);

namespace SPRE\API;

use WP_REST_Controller;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BaseController
 *
 * Abstract foundation for REST API endpoints.
 */
abstract class BaseController extends WP_REST_Controller {

	/**
	 * Route namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'spre/v1';

	/**
	 * Default permission check callback for administrative REST endpoints.
	 *
	 * @return bool True if authorized.
	 */
	public function check_admin_permissions(): bool {
		return current_user_can( 'manage_woocommerce' );
	}
}
