<?php

namespace SSS;

if (!defined('ABSPATH')) {
	exit;
}

final class Shortcodes {
	public static function register(): void {
		add_shortcode('sss_products', [self::class, 'products']);
		add_shortcode('sss_cart', [self::class, 'cart']);
	}

	public static function products(array $atts = []): string {
		$atts = shortcode_atts([
			'per_page' => 24,
		], $atts, 'sss_products');

		$limit = (int) $atts['per_page'];
		if ($limit <= 0 || $limit > 100) {
			$limit = 24;
		}

		$q = new \WP_Query([
			'post_type' => Products::CPT,
			'post_status' => 'publish',
			'posts_per_page' => $limit,
			'orderby' => 'date',
			'order' => 'DESC',
			'no_found_rows' => true,
		]);

		ob_start();
		?>
		<div class="sss-products" data-sss-products>
			<?php foreach ($q->posts as $p) : ?>
				<?php
				$post_id = (int) $p->ID;
				$img = (string) get_post_meta($post_id, 'sss_featured_image_url', true);
				$variants = get_post_meta($post_id, 'sss_variants', true);
				$variants = is_array($variants) ? $variants : [];
				?>
				<div class="sss-product-card">
					<?php if ($img) : ?>
						<img class="sss-product-image" src="<?php echo esc_url($img); ?>" alt="">
					<?php endif; ?>
					<h3 class="sss-product-title">
						<a href="<?php echo esc_url(get_permalink($post_id)); ?>"><?php echo esc_html(get_the_title($post_id)); ?></a>
					</h3>

					<?php if (!empty($variants)) : ?>
						<label class="sss-variant-label">
							<span class="screen-reader-text">Variant</span>
							<select class="sss-variant-select" data-sss-variant-select>
								<?php foreach ($variants as $v) : ?>
									<?php
									$vid = (string) ($v['id'] ?? '');
									$title = (string) ($v['title'] ?? '');
									$price = (string) ($v['price'] ?? '');
									$available = !empty($v['availableForSale']);
									?>
									<option value="<?php echo esc_attr($vid); ?>" <?php disabled(!$available); ?>>
										<?php echo esc_html($title . ($price !== '' ? ' — ' . $price : '') . (!$available ? ' (Sold out)' : '')); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</label>

						<button type="button" class="sss-add-to-cart" data-sss-add-to-cart>
							Add to cart
						</button>
					<?php else : ?>
						<p>No variants synced.</p>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	public static function cart(array $atts = []): string {
		ob_start();
		?>
		<div class="sss-cart" data-sss-cart>
			<div class="sss-cart-status" data-sss-cart-status>Loading cart…</div>
			<div class="sss-cart-lines" data-sss-cart-lines></div>
			<div class="sss-cart-summary" data-sss-cart-summary></div>
			<div class="sss-cart-actions" data-sss-cart-actions>
				<a class="sss-checkout-button" data-sss-checkout href="#" rel="nofollow">Checkout</a>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}

