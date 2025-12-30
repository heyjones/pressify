<?php

namespace Pressify;

if (!defined('ABSPATH')) {
	exit;
}

final class Shortcodes {
	public static function register(): void {
		add_shortcode('pressify_products', [self::class, 'products']);
		add_shortcode('pressify_cart', [self::class, 'cart']);
	}

	public static function products(array $atts = []): string {
		$atts = shortcode_atts([
			'per_page' => 24,
		], $atts, 'pressify_products');

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
		<div class="pressify-products" data-pressify-products>
			<?php foreach ($q->posts as $p) : ?>
				<?php
				$post_id = (int) $p->ID;
				$img = Products::get_featured_image_url($post_id);
				$variants = Products::get_variants($post_id);
				?>
				<div class="pressify-product-card">
					<?php if ($img) : ?>
						<img class="pressify-product-image" src="<?php echo esc_url($img); ?>" alt="">
					<?php endif; ?>
					<h3 class="pressify-product-title">
						<a href="<?php echo esc_url(get_permalink($post_id)); ?>"><?php echo esc_html(get_the_title($post_id)); ?></a>
					</h3>

					<?php if (!empty($variants)) : ?>
						<label class="pressify-variant-label">
							<span class="screen-reader-text">Variant</span>
							<select class="pressify-variant-select" data-pressify-variant-select>
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

						<button type="button" class="pressify-add-to-cart" data-pressify-add-to-cart>
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
		<div class="pressify-cart" data-pressify-cart>
			<div class="pressify-cart-status" data-pressify-cart-status>Loading cart…</div>
			<div class="pressify-cart-lines" data-pressify-cart-lines></div>
			<div class="pressify-cart-summary" data-pressify-cart-summary></div>
			<div class="pressify-cart-actions" data-pressify-cart-actions>
				<a class="pressify-checkout-button" data-pressify-checkout href="#" rel="nofollow">Checkout</a>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}

