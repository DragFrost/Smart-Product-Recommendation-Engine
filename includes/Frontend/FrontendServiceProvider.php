<?php
/**
 * SPRE Frontend Service Provider.
 *
 * @package SPRE\Frontend
 */

declare(strict_types=1);

namespace SPRE\Frontend;

use SPRE\Core\Container;
use SPRE\Core\ServiceProviderInterface;
use SPRE\Recommendation\Engine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FrontendServiceProvider
 *
 * Configures the frontend elements (shortcodes, hooks, enqueues).
 */
class FrontendServiceProvider implements ServiceProviderInterface {

	/**
	 * Register services.
	 *
	 * @param Container $container Container instance.
	 */
	public function register( Container $container ): void {
		$container->singleton( Shortcodes::class, function ( Container $c ) {
			return new Shortcodes( $c->get( Engine::class ) );
		} );

		$container->singleton( WooCommerceHooks::class, function ( Container $c ) {
			return new WooCommerceHooks( $c->get( Engine::class ) );
		} );

		$container->singleton( Assets::class, function () {
			return new Assets();
		} );

		$container->singleton( Blocks::class, function ( Container $c ) {
			return new Blocks( $c->get( Engine::class ) );
		} );
	}

	/**
	 * Boot services.
	 *
	 * @param Container $container Container instance.
	 */
	public function boot( Container $container ): void {
		$container->get( Shortcodes::class )->register();
		$container->get( WooCommerceHooks::class )->register();
		$container->get( Assets::class )->register();
		$container->get( Blocks::class )->register();
	}
}
