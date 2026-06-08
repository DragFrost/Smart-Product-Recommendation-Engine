<?php
/**
 * SPRE Gutenberg Blocks registration.
 *
 * @package SPRE\Frontend
 */

declare(strict_types=1);

namespace SPRE\Frontend;

use SPRE\Core\Container;
use SPRE\Recommendation\Engine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Blocks
 *
 * Handles Gutenberg block registration and Server-Side Rendering callbacks.
 */
class Blocks {

	/**
	 * Recommendation Engine.
	 *
	 * @var Engine
	 */
	private Engine $engine;

	/**
	 * Constructor.
	 *
	 * @param Engine $engine Engine.
	 */
	public function __construct( Engine $engine ) {
		$this->engine = $engine;
	}

	/**
	 * Bind block registers.
	 */
	public function register(): void {
		add_action( 'init', [ $this, 'register_gutenberg_block' ] );
		add_action( 'elementor/widgets/register', [ $this, 'register_elementor_widget' ] );
	}

	/**
	 * Register Gutenberg blocks.
	 */
	public function register_gutenberg_block(): void {
		// Register editor script if block editor is active
		wp_register_script(
			'spre-block-editor-js',
			SPRE_PLUGIN_URL . 'assets/js/block-editor.js',
			[ 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor' ],
			SPRE_VERSION,
			true
		);

		register_block_type(
			'spre/recommendation-block',
			[
				'editor_script'   => 'spre-block-editor-js',
				'render_callback' => [ $this, 'render_gutenberg_block_ssr' ],
				'attributes'      => [
					'widget_type' => [
						'type'    => 'string',
						'default' => 'related',
					],
					'title'       => [
						'type'    => 'string',
						'default' => '',
					],
					'limit'       => [
						'type'    => 'number',
						'default' => 4,
					],
					'period'      => [
						'type'    => 'string',
						'default' => '7d',
					],
				],
			]
		);
	}

	/**
	 * Server-Side Render (SSR) callback for Gutenberg Blocks.
	 *
	 * @param array $attributes Block attributes.
	 * @return string Rendered block HTML.
	 */
	public function render_gutenberg_block_ssr( array $attributes ): string {
		$widget_type = sanitize_key( $attributes['widget_type'] ?? 'related' );
		$title       = sanitize_text_field( $attributes['title'] ?? '' );
		$limit       = (int) ( $attributes['limit'] ?? 4 );
		$period      = sanitize_key( $attributes['period'] ?? '7d' );

		$product_id = 0;
		if ( is_product() ) {
			$product_id = get_the_ID();
		}

		$context = [
			'limit'      => $limit,
			'period'     => $period,
			'product_id' => $product_id,
		];

		$results = $this->engine->get_recommendations( $widget_type, $context );
		$products = $results['products'];
		$ab_test_id = $results['ab_test_id'];
		$ab_variation = $results['ab_variation'];

		if ( empty( $products ) ) {
			return '';
		}

		ob_start();
		include SPRE_PLUGIN_DIR . 'templates/recommendation-widget.php';
		return ob_get_clean() ?: '';
	}

	/**
	 * Register custom Elementor widget.
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager.
	 */
	public function register_elementor_widget( $widgets_manager ): void {
		if ( class_exists( '\Elementor\Widget_Base' ) ) {
			$widgets_manager->register( new ElementorWidget() );
		}
	}
}
