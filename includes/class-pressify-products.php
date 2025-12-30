<?php

namespace Pressify;

if (!defined('ABSPATH')) {
	exit;
}

final class Products {
	public const CPT = 'pressify_product';

	// Post meta keys (new).
	private const META_PRODUCT_ID = 'pressify_shopify_product_id';
	private const META_HANDLE = 'pressify_handle';
	private const META_FEATURED_IMAGE_URL = 'pressify_featured_image_url';
	private const META_VARIANTS = 'pressify_variants';
	private const META_OPTIONS = 'pressify_options';

	public static function register_cpt(): void {
		register_post_type(self::CPT, [
			'labels' => [
				'name' => 'Pressify Products',
				'singular_name' => 'Pressify Product',
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

		$existing = self::find_post_id_by_shopify_product_id($productId);
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

		update_post_meta($post_id, self::META_PRODUCT_ID, $productId);
		update_post_meta($post_id, self::META_HANDLE, (string) ($product['handle'] ?? ''));
		update_post_meta($post_id, 'pressify_vendor', (string) ($product['vendor'] ?? ''));
		update_post_meta($post_id, 'pressify_product_type', (string) ($product['productType'] ?? ''));
		update_post_meta($post_id, 'pressify_status', (string) ($product['status'] ?? ''));
		update_post_meta($post_id, 'pressify_updated_at', (string) ($product['updatedAt'] ?? ''));

		$featuredImage = $product['featuredImage']['url'] ?? '';
		update_post_meta($post_id, self::META_FEATURED_IMAGE_URL, (string) $featuredImage);

		$variants = is_array($product['variants'] ?? null) ? (array) $product['variants'] : [];
		update_post_meta($post_id, self::META_VARIANTS, $variants);

		$options = is_array($product['options'] ?? null) ? (array) $product['options'] : [];
		update_post_meta($post_id, self::META_OPTIONS, $options);

		return $post_id;
	}

	public static function find_post_id_by_shopify_product_id(string $productId): ?int {
		return self::find_post_id_by_meta(self::META_PRODUCT_ID, $productId);
	}

	public static function get_handle(int $post_id): string {
		return (string) get_post_meta($post_id, self::META_HANDLE, true);
	}

	public static function get_featured_image_url(int $post_id): string {
		return (string) get_post_meta($post_id, self::META_FEATURED_IMAGE_URL, true);
	}

	public static function get_variants(int $post_id): array {
		$v = get_post_meta($post_id, self::META_VARIANTS, true);
		return is_array($v) ? $v : [];
	}

	public static function get_options(int $post_id): array {
		$o = get_post_meta($post_id, self::META_OPTIONS, true);
		return is_array($o) ? $o : [];
	}

	private static function find_post_id_by_meta(string $meta_key, string $meta_value): ?int {
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

