<?php
/**
 * SPRE Main Plugin orchestrator.
 *
 * @package SPRE\Core
 */

declare(strict_types=1);

namespace SPRE\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Plugin
 *
 * The main loader and lifecycle manager of the plugin.
 */
final class Plugin {

	/**
	 * Single static instance of the Plugin.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * DI Container instance.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Loaded service providers.
	 *
	 * @var array<ServiceProviderInterface>
	 */
	private array $providers = [];

	/**
	 * Constructor.
	 *
	 * Instantiates the container and boots providers.
	 */
	private function __construct() {
		$this->container = Container::getInstance();
		$this->registerProviders();
		$this->bootProviders();
	}

	/**
	 * Get the singleton instance.
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Get the DI Container instance.
	 *
	 * @return Container
	 */
	public function get_container(): Container {
		return $this->container;
	}

	/**
	 * Register all plugin service providers.
	 */
	private function registerProviders(): void {
		$providers = [
			\SPRE\Database\DatabaseServiceProvider::class,
			\SPRE\Tracking\TrackingServiceProvider::class,
			\SPRE\Recommendation\RecommendationServiceProvider::class,
			\SPRE\API\APIServiceProvider::class,
			\SPRE\Admin\AdminServiceProvider::class,
			\SPRE\Frontend\FrontendServiceProvider::class,
		];

		foreach ( $providers as $provider_class ) {
			if ( class_exists( $provider_class ) ) {
				$provider = new $provider_class();
				if ( $provider instanceof ServiceProviderInterface ) {
					$provider->register( $this->container );
					$this->providers[] = $provider;
				}
			}
		}
	}

	/**
	 * Boot all plugin service providers.
	 */
	private function bootProviders(): void {
		foreach ( $this->providers as $provider ) {
			$provider->boot( $this->container );
		}
	}
}
