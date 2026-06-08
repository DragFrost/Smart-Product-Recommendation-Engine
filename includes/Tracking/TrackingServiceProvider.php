<?php
/**
 * SPRE Tracking Service Provider.
 *
 * @package SPRE\Tracking
 */

declare(strict_types=1);

namespace SPRE\Tracking;

use SPRE\Core\Container;
use SPRE\Core\ServiceProviderInterface;
use SPRE\Database\ViewsRepository;
use SPRE\Database\AnalyticsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TrackingServiceProvider
 *
 * Boots and coordinates GDPR tracking and cron-based database log cleanups.
 */
class TrackingServiceProvider implements ServiceProviderInterface {

	/**
	 * Register tracking dependencies.
	 *
	 * @param Container $container DI Container.
	 */
	public function register( Container $container ): void {
		$container->singleton( UserTracker::class, function () {
			return new UserTracker();
		} );

		$container->singleton( DataCollector::class, function ( Container $c ) {
			return new DataCollector(
				$c->get( AnalyticsRepository::class ),
				$c->get( UserTracker::class )
			);
		} );
	}

	/**
	 * Boot hooks.
	 *
	 * @param Container $container DI Container.
	 */
	public function boot( Container $container ): void {
		// Start tracking WooCommerce backend triggers
		$container->get( DataCollector::class )->register_hooks();

		// Bind daily analytics/views cleanups to scheduled cron
		add_action( 'spre_daily_cleanup_cron', [ $this, 'run_daily_database_cleanup' ] );
	}

	/**
	 * Cleanup old history logs to comply with GDPR storage restrictions
	 * and prevent database bloat.
	 */
	public function run_daily_database_cleanup(): void {
		$container = Container::getInstance();

		$views_repo = $container->get( ViewsRepository::class );
		$views_repo->purge_old_views( 30 ); // Keep views for 30 days

		$analytics_repo = $container->get( AnalyticsRepository::class );
		$analytics_repo->purge_old_analytics( 90 ); // Keep analytics logs for 90 days
	}
}
