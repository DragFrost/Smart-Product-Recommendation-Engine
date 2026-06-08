<?php
/**
 * SPRE Database Service Provider.
 *
 * @package SPRE\Database
 */

declare(strict_types=1);

namespace SPRE\Database;

use SPRE\Core\Container;
use SPRE\Core\ServiceProviderInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DatabaseServiceProvider
 *
 * Binds database repositories and migrations.
 */
class DatabaseServiceProvider implements ServiceProviderInterface {

	/**
	 * Register database components.
	 *
	 * @param Container $container Container instance.
	 */
	public function register( Container $container ): void {
		$container->singleton( Migrator::class, function () {
			return new Migrator();
		} );

		$container->singleton( RelationsRepository::class, function () {
			return new RelationsRepository();
		} );

		$container->singleton( ViewsRepository::class, function () {
			return new ViewsRepository();
		} );

		$container->singleton( AnalyticsRepository::class, function () {
			return new AnalyticsRepository();
		} );

		$container->singleton( RulesRepository::class, function () {
			return new RulesRepository();
		} );
	}

	/**
	 * Boot database actions.
	 *
	 * @param Container $container Container instance.
	 */
	public function boot( Container $container ): void {
		// Can hook into standard queries or execution limits if needed.
	}
}
