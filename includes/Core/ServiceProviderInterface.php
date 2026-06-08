<?php
/**
 * SPRE Service Provider Interface.
 *
 * @package SPRE\Core
 */

declare(strict_types=1);

namespace SPRE\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface ServiceProviderInterface
 *
 * Standard contract for modular service providers.
 */
interface ServiceProviderInterface {

	/**
	 * Register services in the container.
	 *
	 * @param Container $container Container instance.
	 */
	public function register( Container $container ): void;

	/**
	 * Boot services after all registrations are complete.
	 *
	 * @param Container $container Container instance.
	 */
	public function boot( Container $container ): void;
}
