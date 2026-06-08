<?php
/**
 * SPRE API Service Provider.
 *
 * @package SPRE\API
 */

declare(strict_types=1);

namespace SPRE\API;

use SPRE\Core\Container;
use SPRE\Core\ServiceProviderInterface;
use SPRE\Recommendation\Engine;
use SPRE\Database\ViewsRepository;
use SPRE\Database\AnalyticsRepository;
use SPRE\Database\RulesRepository;
use SPRE\Database\ABTestsRepository;
use SPRE\Tracking\UserTracker;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class APIServiceProvider
 *
 * Plugs API endpoints into the WordPress REST architecture.
 */
class APIServiceProvider implements ServiceProviderInterface {

	/**
	 * Register services in the container.
	 *
	 * @param Container $container Container instance.
	 */
	public function register( Container $container ): void {
		$container->singleton( RecommendationsController::class, function ( Container $c ) {
			return new RecommendationsController(
				$c->get( Engine::class )
			);
		} );

		$container->singleton( TrackingController::class, function ( Container $c ) {
			return new TrackingController(
				$c->get( ViewsRepository::class ),
				$c->get( AnalyticsRepository::class ),
				$c->get( UserTracker::class )
			);
		} );

		$container->singleton( AdminController::class, function ( Container $c ) {
			return new AdminController(
				$c->get( AnalyticsRepository::class ),
				$c->get( RulesRepository::class ),
				$c->get( ABTestsRepository::class )
			);
		} );
	}

	/**
	 * Boot services (register REST endpoints).
	 *
	 * @param Container $container Container instance.
	 */
	public function boot( Container $container ): void {
		add_action( 'rest_api_init', function () use ( $container ) {
			$container->get( RecommendationsController::class )->register_routes();
			$container->get( TrackingController::class )->register_routes();
			$container->get( AdminController::class )->register_routes();
		} );
	}
}
