<?php
/**
 * SPRE Elementor Widget integration.
 *
 * @package SPRE\Frontend
 */

declare(strict_types=1);

namespace SPRE\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ElementorWidget
 *
 * Exposes recommendations as an Elementor page builder widget.
 */
class ElementorWidget extends \Elementor\Widget_Base {

	/**
	 * Retrieve the widget name.
	 *
	 * @return string Widget name.
	 */
	public function get_name(): string {
		return 'spre_recommendations_widget';
	}

	/**
	 * Retrieve the widget title.
	 *
	 * @return string Widget title.
	 */
	public function get_title(): string {
		return esc_html__( 'Smart Product Recommendations', 'smart-product-recommendation-engine' );
	}

	/**
	 * Retrieve the widget icon.
	 *
	 * @return string Widget icon.
	 */
	public function get_icon(): string {
		return 'eicon-products';
	}

	/**
	 * Retrieve the list of categories the widget belongs to.
	 *
	 * @return array Widget categories.
	 */
	public function get_categories(): array {
		return [ 'general', 'woocommerce' ];
	}

	/**
	 * Register widget controls in Elementor panel.
	 */
	protected function register_controls(): void {
		$this->start_controls_section(
			'section_content',
			[
				'label' => esc_html__( 'Configuration', 'smart-product-recommendation-engine' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'widget_type',
			[
				'label'   => esc_html__( 'Widget Type', 'smart-product-recommendation-engine' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'related',
				'options' => [
					'related'      => esc_html__( 'Related Products (Weighted)', 'smart-product-recommendation-engine' ),
					'fbt'          => esc_html__( 'Frequently Bought Together', 'smart-product-recommendation-engine' ),
					'personalized' => esc_html__( 'Personalized (Recommended for You)', 'smart-product-recommendation-engine' ),
					'trending'     => esc_html__( 'Trending Products', 'smart-product-recommendation-engine' ),
				],
			]
		);

		$this->add_control(
			'title',
			[
				'label'       => esc_html__( 'Title', 'smart-product-recommendation-engine' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => esc_html__( 'Recommended Products', 'smart-product-recommendation-engine' ),
				'placeholder' => esc_html__( 'Enter section title', 'smart-product-recommendation-engine' ),
			]
		);

		$this->add_control(
			'limit',
			[
				'label'   => esc_html__( 'Product Limit', 'smart-product-recommendation-engine' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'min'     => 1,
				'max'     => 20,
				'step'    => 1,
				'default' => 4,
			]
		);

		$this->add_control(
			'period',
			[
				'label'     => esc_html__( 'Trending Window', 'smart-product-recommendation-engine' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'default'   => '7d',
				'options'   => [
					'24h' => esc_html__( 'Last 24 Hours', 'smart-product-recommendation-engine' ),
					'7d'  => esc_html__( 'Last 7 Days', 'smart-product-recommendation-engine' ),
					'30d' => esc_html__( 'Last 30 Days', 'smart-product-recommendation-engine' ),
				],
				'condition' => [
					'widget_type' => 'trending',
				],
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Render the widget output on the frontend.
	 */
	protected function render(): void {
		$settings = $this->get_settings_for_display();

		$widget_type = sanitize_key( $settings['widget_type'] );
		$title       = sanitize_text_field( $settings['title'] );
		$limit       = (int) $settings['limit'];
		$period      = sanitize_key( $settings['period'] ?? '7d' );

		$product_id = 0;
		if ( is_product() ) {
			$product_id = get_the_ID();
		}

		$context = [
			'limit'      => $limit,
			'period'     => $period,
			'product_id' => $product_id,
		];

		// Eagerly resolve engine from DI Container
		$container = \SPRE\Core\Container::getInstance();
		$engine    = $container->get( \SPRE\Recommendation\Engine::class );

		$results = $engine->get_recommendations( $widget_type, $context );
		$products = $results['products'];
		$ab_test_id = $results['ab_test_id'];
		$ab_variation = $results['ab_variation'];

		if ( empty( $products ) ) {
			return;
		}

		include SPRE_PLUGIN_DIR . 'templates/recommendation-widget.php';
	}
}
