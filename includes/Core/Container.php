<?php
/**
 * SPRE Dependency Injection Container.
 *
 * @package SPRE\Core
 */

declare(strict_types=1);

namespace SPRE\Core;

use Exception;
use ReflectionClass;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Container
 *
 * A lightweight, PSR-11 inspired dependency injection container.
 */
class Container {

	/**
	 * Registered bindings.
	 *
	 * @var array<string, mixed>
	 */
	private array $bindings = [];

	/**
	 * Resolved singleton instances.
	 *
	 * @var array<string, mixed>
	 */
	private array $instances = [];

	/**
	 * Single static instance of the Container.
	 *
	 * @var Container|null
	 */
	private static ?Container $instance = null;

	/**
	 * Get the singleton instance of the Container.
	 *
	 * @return Container
	 */
	public static function getInstance(): Container {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register a binding.
	 *
	 * @param string $key      The class name or identifier.
	 * @param mixed  $resolver A callable factory or instance.
	 */
	public function bind( string $key, $resolver ): void {
		$this->bindings[ $key ] = $resolver;
	}

	/**
	 * Register a singleton binding.
	 *
	 * @param string $key      The class name or identifier.
	 * @param mixed  $resolver A callable factory or instance.
	 */
	public function singleton( string $key, $resolver ): void {
		$this->bindings[ $key ] = function ( Container $container ) use ( $resolver ) {
			static $instance;
			if ( null === $instance ) {
				$instance = is_callable( $resolver ) ? $resolver( $container ) : $resolver;
			}
			return $instance;
		};
	}

	/**
	 * Get a bound instance or automatically resolve it.
	 *
	 * @param string $key The class name or identifier.
	 * @return mixed The resolved instance.
	 * @throws Exception If resolution fails.
	 */
	public function get( string $key ) {
		if ( isset( $this->instances[ $key ] ) ) {
			return $this->instances[ $key ];
		}

		if ( ! isset( $this->bindings[ $key ] ) ) {
			if ( class_exists( $key ) ) {
				return $this->resolve( $key );
			}
			throw new Exception( sprintf( 'Target binding [%s] does not exist.', $key ) );
		}

		$resolver = $this->bindings[ $key ];
		$instance = is_callable( $resolver ) ? $resolver( $this ) : $resolver;

		// If this is a singleton binding, cache it.
		// (The singleton callback itself handles caching the value internally too, but this provides extra safety).
		return $instance;
	}

	/**
	 * Automatically resolve a class and its constructor dependencies via Reflection.
	 *
	 * @param string $class Class name.
	 * @return mixed Instantiated class.
	 * @throws Exception If instantiation or parameter resolution fails.
	 */
	public function resolve( string $class ) {
		$reflector = new ReflectionClass( $class );

		if ( ! $reflector->isInstantiable() ) {
			throw new Exception( sprintf( 'Class %s is not instantiable.', $class ) );
		}

		$constructor = $reflector->getConstructor();
		if ( null === $constructor ) {
			return new $class();
		}

		$parameters = $constructor->getParameters();
		$dependencies = [];

		foreach ( $parameters as $parameter ) {
			$type = $parameter->getType();

			if ( $type && ! $type->isBuiltin() ) {
				// Type is a class/interface. Recursively get it.
				$dependencies[] = $this->get( $type->getName() );
			} elseif ( $parameter->isDefaultValueAvailable() ) {
				// Use default constructor value if available
				$dependencies[] = $parameter->getDefaultValue();
			} else {
				throw new Exception(
					sprintf(
						'Unresolvable constructor parameter [%s] in class %s',
						$parameter->getName(),
						$class
					)
				);
			}
		}

		return $reflector->newInstanceArgs( $dependencies);
	}
}
