<?php

namespace Pressify;

if (!defined('ABSPATH')) {
	exit;
}

final class Assets {
	public static function register(): void {
		if (is_admin()) {
			return;
		}

		if (!self::page_uses_shortcodes(['pressify_products', 'pressify_cart'])) {
			return;
		}

		wp_register_script(
			'pressify-frontend',
			PRESSIFY_PLUGIN_URL . 'assets/pressify.js',
			[],
			PRESSIFY_PLUGIN_VERSION,
			true
		);

		wp_register_style(
			'pressify-frontend',
			PRESSIFY_PLUGIN_URL . 'assets/pressify.css',
			[],
			PRESSIFY_PLUGIN_VERSION
		);

		wp_enqueue_script('pressify-frontend');
		wp_enqueue_style('pressify-frontend');

		$cfg = [
			'restBase' => esc_url_raw(rest_url('pressify/v1')),
		];

		wp_add_inline_script('pressify-frontend', 'window.Pressify = ' . wp_json_encode($cfg) . ';', 'before');
	}

	private static function page_uses_shortcodes(array $shortcodes): bool {
		global $post;
		if (!$post || empty($post->post_content)) {
			return false;
		}
		foreach ($shortcodes as $sc) {
			if (has_shortcode($post->post_content, $sc)) {
				return true;
			}
		}
		return false;
	}
}

