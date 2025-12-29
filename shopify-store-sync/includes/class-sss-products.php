<?php

namespace SSS;

if (!defined('ABSPATH')) {
	exit;
}

final class Products {
	public const CPT = 'sss_product';

	public static function register_cpt(): void {
		register_post_type(self::CPT, [
			'labels' => [
				'name' => 'Shopify Products',
				'singular_name' => 'Shopify Product',
			],
			'public' => true,
			'show_in_menu' => true,
			'show_in_rest' => true,
			'has_archive' => false,
			'rewrite' => ['slug' => 'shop'],
			'supports' => ['title', 'editor', 'thumbnail', 'excerpt'],
			'menu_icon' => 'dashicons-store',
		]);
	}

	public static function upsert_from_shopify(array $product): int {
		$productId = (string) ($product['id'] ?? '');
		if ($productId === '') {
			throw new \RuntimeException('Shopify product missing id');
		}

		$existing = self::find_post_id_by_meta('sss_shopify_product_id', $productId);
		$postarr = [
			'post_type' => self::CPT,
			'post_status' => 'publish',
			'post_title' => (string) ($product['title'] ?? ''),
			'post_name' => (string) ($product['handle'] ?? ''),
			'post_content' => (string) ($product['descriptionHtml'] ?? ''),
			'post_excerpt' => (string) ($product['description'] ?? ''),
		];

		if ($existing) {
			$postarr['ID'] = $existing;
			$post_id = (int) wp_update_post($postarr, true);
		} else {
			$post_id = (int) wp_insert_post($postarr, true);
		}

		if (is_wp_error($post_id) || $post_id <= 0) {
			$msg = is_wp_error($post_id) ? $post_id->get_error_message() : 'unknown error';
			throw new \RuntimeException('Failed to upsert WP product: ' . $msg);
		}

		update_post_meta($post_id, 'sss_shopify_product_id', $productId);
		update_post_meta($post_id, 'sss_handle', (string) ($product['handle'] ?? ''));
		update_post_meta($post_id, 'sss_vendor', (string) ($product['vendor'] ?? ''));
		update_post_meta($post_id, 'sss_product_type', (string) ($product['productType'] ?? ''));
		update_post_meta($post_id, 'sss_status', (string) ($product['status'] ?? ''));
		update_post_meta($post_id, 'sss_updated_at', (string) ($product['updatedAt'] ?? ''));

		$featuredImage = $product['featuredImage']['url'] ?? '';
		update_post_meta($post_id, 'sss_featured_image_url', (string) $featuredImage);

		$variants = is_array($product['variants'] ?? null) ? (array) $product['variants'] : [];
		update_post_meta($post_id, 'sss_variants', $variants);

		$options = is_array($product['options'] ?? null) ? (array) $product['options'] : [];
		update_post_meta($post_id, 'sss_options', $options);

		return $post_id;
	}

	public static function find_post_id_by_meta(string $meta_key, string $meta_value): ?int {
		$q = new \WP_Query([
			'post_type' => self::CPT,
			'post_status' => 'any',
			'posts_per_page' => 1,
			'fields' => 'ids',
			'meta_query' => [[
				'key' => $meta_key,
				'value' => $meta_value,
				'compare' => '=',
			]],
			'no_found_rows' => true,
		]);

		if (empty($q->posts)) {
			return null;
		}

		return (int) $q->posts[0];
	}
}

