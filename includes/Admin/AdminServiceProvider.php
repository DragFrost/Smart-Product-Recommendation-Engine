<?php
/**
 * SPRE Admin Service Provider.
 *
 * @package SPRE\Admin
 */

declare(strict_types=1);

namespace SPRE\Admin;

use SPRE\Core\Container;
use SPRE\Core\ServiceProviderInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AdminServiceProvider
 *
 * Plugs the administrative dashboard and menu nodes into the WordPress lifecycle.
 */
class AdminServiceProvider implements ServiceProviderInterface {

	/**
	 * Register services in the container.
	 *
	 * @param Container $container Container instance.
	 */
	public function register( Container $container ): void {
		$container->singleton( Menu::class, function () {
			return new Menu();
		} );

		$container->singleton( Dashboard::class, function () {
			return new Dashboard();
		} );
	}

	/**
	 * Boot services.
	 *
	 * @param Container $container Container instance.
	 */
	public function boot( Container $container ): void {
		$container->get( Menu::class )->register();
		$container->get( Dashboard::class )->register();
	}
}
