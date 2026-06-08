<?php
/**
 * SPRE Shortcodes handler.
 *
 * @package SPRE\Frontend
 */

declare(strict_types=1);

namespace SPRE\Frontend;

use SPRE\Recommendation\Engine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Shortcodes
 *
 * Exposes widgets through shortcodes.
 */
class Shortcodes {

	/**
	 * Recommendation Engine.
	 *
	 * @var Engine
	 */
	private Engine $engine;

	/**
	 * Constructor.
	 *
	 * @param Engine $engine Engine instance.
	 */
	public function __construct( Engine $engine ) {
		$this->engine = $engine;
	}

	/**
	 * Register shortcodes.
	 */
	public function register(): void {
		add_shortcode( 'spre_recommendations', [ $this, 'render_personalized' ] );
		add_shortcode( 'spre_trending', [ $this, 'render_trending' ] );
		add_shortcode( 'spre_frequently_bought', [ $this, 'render_fbt' ] );
	}

	/**
	 * Render Personalized Recommendations.
	 *
	 * [spre_recommendations limit="4" title="Recommended for You"]
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string Rendered HTML.
	 */
	public function render_personalized( $atts ): string {
		$args = shortcode_atts(
			[
				'limit' => '4',
				'title' => esc_html__( 'Recommended for You', 'smart-product-recommendation-engine' ),
			],
			$atts,
			'spre_recommendations'
		);

		$context = [
			'limit' => (int) $args['limit'],
		];

		$results = $this->engine->get_recommendations( 'personalized', $context );

		return $this->render_template(
			'personalized',
			$args['title'],
			$results['products'],
			$results['ab_test_id'],
			$results['ab_variation']
		);
	}

	/**
	 * Render Trending Recommendations.
	 *
	 * [spre_trending limit="4" period="7d" title="Trending Now"]
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string Rendered HTML.
	 */
	public function render_trending( $atts ): string {
		$args = shortcode_atts(
			[
				'limit'  => '4',
				'period' => '7d',
				'title'  => esc_html__( 'Trending Now', 'smart-product-recommendation-engine' ),
			],
			$atts,
			'spre_trending'
		);

		$context = [
			'limit'  => (int) $args['limit'],
			'period' => sanitize_key( $args['period'] ),
		];

		$results = $this->engine->get_recommendations( 'trending', $context );

		return $this->render_template(
			'trending',
			$args['title'],
			$results['products'],
			$results['ab_test_id'],
			$results['ab_variation']
		);
	}

	/**
	 * Render Frequently Bought Together (FBT).
	 *
	 * [spre_frequently_bought limit="3" title="Frequently Bought Together"]
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string Rendered HTML.
	 */
	public function render_fbt( $atts ): string {
		// Frequently Bought Together is only valid in product contexts
		$product_id = 0;
		if ( is_product() ) {
			$product_id = get_the_ID();
		}

		if ( $product_id <= 0 ) {
			return '';
		}

		$args = shortcode_atts(
			[
				'limit' => '3',
				'title' => esc_html__( 'Frequently Bought Together', 'smart-product-recommendation-engine' ),
			],
			$atts,
			'spre_frequently_bought'
		);

		$context = [
			'product_id' => $product_id,
			'limit'      => (int) $args['limit'],
		];

		$results = $this->engine->get_recommendations( 'fbt', $context );

		return $this->render_template(
			'fbt',
			$args['title'],
			$results['products'],
			$results['ab_test_id'],
			$results['ab_variation']
		);
	}

	/**
	 * Render the widget layout with output buffering.
	 *
	 * @param string             $widget_type  Widget identifier.
	 * @param string             $title        Display title.
	 * @param array<\WC_Product> $products     Products array.
	 * @param int|null           $ab_test_id   A/B Test ID.
	 * @param string|null        $ab_variation A/B Variation.
	 * @return string Rendered HTML output.
	 */
	private function render_template(
		string $widget_type,
		string $title,
		array $products,
		?int $ab_test_id,
		?string $ab_variation
	): string {
		if ( empty( $products ) ) {
			return '';
		}

		ob_start();

		$template_path = SPRE_PLUGIN_DIR . 'templates/recommendation-widget.php';
		if ( file_exists( $template_path ) ) {
			include $template_path;
		}

		return ob_get_clean() ?: '';
	}
}
