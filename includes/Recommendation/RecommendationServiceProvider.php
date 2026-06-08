<?php
/**
 * SPRE Recommendation Service Provider.
 *
 * @package SPRE\Recommendation
 */

declare(strict_types=1);

namespace SPRE\Recommendation;

use SPRE\Core\Container;
use SPRE\Core\ServiceProviderInterface;
use SPRE\Recommendation\Algorithms\RelatedSimilarity;
use SPRE\Recommendation\Algorithms\FrequentlyBoughtTogether;
use SPRE\Recommendation\Algorithms\Personalized;
use SPRE\Recommendation\Algorithms\Trending;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RecommendationServiceProvider
 *
 * Plugs in recommendation modules, algorithms, and automated background rebuild crons.
 */
class RecommendationServiceProvider implements ServiceProviderInterface {

	/**
	 * Register services.
	 *
	 * @param Container $container Container instance.
	 */
	public function register( Container $container ): void {
		$container->singleton( RuleEngine::class, function ( Container $c ) {
			return new RuleEngine( $c->get( \SPRE\Database\RulesRepository::class ) );
		} );

		$container->singleton( ABTesting::class, function ( Container $c ) {
			return new ABTesting(
				$c->get( \SPRE\Database\ABTestsRepository::class ),
				$c->get( \SPRE\Tracking\UserTracker::class )
			);
		} );

		// Register Algorithms
		$container->singleton( RelatedSimilarity::class, function ( Container $c ) {
			return new RelatedSimilarity(
				$c->get( \SPRE\Database\RelationsRepository::class )
			);
		} );

		$container->singleton( FrequentlyBoughtTogether::class, function ( Container $c ) {
			return new FrequentlyBoughtTogether(
				$c->get( \SPRE\Database\RelationsRepository::class )
			);
		} );

		$container->singleton( Personalized::class, function ( Container $c ) {
			return new Personalized(
				$c->get( \SPRE\Database\ViewsRepository::class ),
				$c->get( \SPRE\Database\RelationsRepository::class )
			);
		} );

		$container->singleton( Trending::class, function ( Container $c ) {
			return new Trending(
				$c->get( \SPRE\Database\ViewsRepository::class ),
				$c->get( \SPRE\Database\AnalyticsRepository::class )
			);
		} );

		$container->singleton( Engine::class, function ( Container $c ) {
			return new Engine(
				$c->get( RuleEngine::class ),
				$c->get( ABTesting::class ),
				[
					'related'      => $c->get( RelatedSimilarity::class ),
					'fbt'          => $c->get( FrequentlyBoughtTogether::class ),
					'personalized' => $c->get( Personalized::class ),
					'trending'     => $c->get( Trending::class ),
				]
			);
		} );
	}

	/**
	 * Boot actions.
	 *
	 * @param Container $container Container instance.
	 */
	public function boot( Container $container ): void {
		// Register Background calculation cron hooks
		add_action( 'spre_rebuild_relations_cron', [ $this, 'run_background_rebuild' ] );

		if ( ! wp_next_scheduled( 'spre_rebuild_relations_cron' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'spre_rebuild_relations_cron' );
		}
	}

	/**
	 * Run full rebuild of similarities and FBT in the background.
	 */
	public function run_background_rebuild(): void {
		$container = Container::getInstance();

		$similarity = $container->get( RelatedSimilarity::class );
		if ( method_exists( $similarity, 'rebuild_all_similarities' ) ) {
			$similarity->rebuild_all_similarities();
		}

		$fbt = $container->get( FrequentlyBoughtTogether::class );
		if ( method_exists( $fbt, 'rebuild_all_co_purchases' ) ) {
			$fbt->rebuild_all_co_purchases();
		}
	}
}
