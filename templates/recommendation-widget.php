<?php
/**
 * Template for displaying recommendations widgets.
 *
 * @package SPRE\Templates
 *
 * @var array<\WC_Product> $products
 * @var string             $title
 * @var int|null           $ab_test_id
 * @var string|null        $ab_variation
 * @var string             $widget_type
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $products ) ) {
	return;
}

// Read settings for frontend custom layout options
$settings         = get_option( 'spre_settings', [] );
$show_badges      = ! isset( $settings['show_badges'] ) || (bool) $settings['show_badges'];
$show_rating      = ! isset( $settings['show_rating'] ) || (bool) $settings['show_rating'];
$show_excerpt     = isset( $settings['show_excerpt'] ) && (bool) $settings['show_excerpt'];
$show_price       = ! isset( $settings['show_price'] ) || (bool) $settings['show_price'];
$show_add_to_cart = ! isset( $settings['show_add_to_cart'] ) || (bool) $settings['show_add_to_cart'];
$custom_btn_text  = isset( $settings['add_to_cart_text'] ) ? trim( $settings['add_to_cart_text'] ) : '';
$layout_mode      = isset( $settings['layout_mode'] ) ? sanitize_key( $settings['layout_mode'] ) : 'grid';
?>

<div class="spre-recommendations-wrapper"
	data-widget-type="<?php echo esc_attr( $widget_type ); ?>"
	<?php if ( $ab_test_id ) : ?>
		data-ab-test-id="<?php echo esc_attr( (string) $ab_test_id ); ?>"
		data-ab-variation="<?php echo esc_attr( $ab_variation ); ?>"
	<?php endif; ?>>

	<?php if ( ! empty( $title ) ) : ?>
		<h2 class="spre-widget-title"><?php echo esc_html( $title ); ?></h2>
	<?php endif; ?>

	<?php if ( $layout_mode === 'slider' ) : ?>
		<div class="spre-carousel-wrapper">
			<button type="button" class="spre-carousel-nav spre-carousel-prev" aria-label="<?php esc_attr_e( 'Previous products', 'smart-product-recommendation-engine' ); ?>">&lsaquo;</button>
	<?php endif; ?>

	<div class="spre-products-grid<?php echo $layout_mode === 'slider' ? ' spre-layout-slider' : ''; ?>">
		<?php foreach ( $products as $product ) : ?>
			<?php
			$product_id = $product->get_id();
			$image_id   = $product->get_image_id();
			$image_url  = wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' ) ?: wc_placeholder_img_src();
			$price_html = $product->get_price_html();
			$rating     = $product->get_average_rating();
			$rating_html = wc_get_rating_html( $rating );
			$permalink  = $product->get_permalink();
			?>

			<div class="spre-product-card<?php echo $show_badges ? ' spre-show-badges' : ''; ?>" data-product-id="<?php echo esc_attr( (string) $product_id ); ?>">
				<a href="<?php echo esc_url( $permalink ); ?>" class="spre-product-link">
					<div class="spre-product-image-container">
						<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $product->get_name() ); ?>" class="spre-product-image" loading="lazy" />
					</div>
					<div class="spre-product-details">
						<h3 class="spre-product-name"><?php echo esc_html( $product->get_name() ); ?></h3>
						
						<?php if ( $show_rating && $rating > 0 ) : ?>
							<div class="spre-product-rating">
								<?php echo wp_kses_post( $rating_html ); ?>
							</div>
						<?php endif; ?>

						<?php if ( $show_excerpt ) : ?>
							<div class="spre-product-excerpt" style="font-size: 0.85em; color: #64748b; margin-bottom: 12px; line-height: 1.4; height: 2.8em; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;">
								<?php echo wp_kses_post( wp_trim_words( $product->get_short_description() ?: $product->get_description(), 10 ) ); ?>
							</div>
						<?php endif; ?>

						<?php if ( $show_price ) : ?>
							<div class="spre-product-price">
								<?php echo wp_kses_post( $price_html ); ?>
							</div>
						<?php endif; ?>
					</div>
				</a>
				<?php if ( $show_add_to_cart ) : ?>
					<div class="spre-product-action">
						<a href="<?php echo esc_url( $product->add_to_cart_url() ); ?>"
							data-quantity="1"
							class="spre-add-to-cart-button button alt add_to_cart_button ajax_add_to_cart"
							data-product_id="<?php echo esc_attr( (string) $product_id ); ?>"
							aria-label="<?php echo esc_attr( $product->add_to_cart_description() ); ?>"
							rel="nofollow">
							<?php echo esc_html( ! empty( $custom_btn_text ) ? $custom_btn_text : $product->single_add_to_cart_text() ); ?>
						</a>
					</div>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>

	<?php if ( $layout_mode === 'slider' ) : ?>
			<button type="button" class="spre-carousel-nav spre-carousel-next" aria-label="<?php esc_attr_e( 'Next products', 'smart-product-recommendation-engine' ); ?>">&rsaquo;</button>
		</div>
	<?php endif; ?>
</div>
