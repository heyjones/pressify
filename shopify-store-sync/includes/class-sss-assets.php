<?php

namespace SSS;

if (!defined('ABSPATH')) {
	exit;
}

final class Assets {
	public static function register(): void {
		if (is_admin()) {
			return;
		}

		// Only load when shortcodes are present on the page.
		if (!self::page_uses_shortcodes(['sss_products', 'sss_cart'])) {
			return;
		}

		wp_register_script(
			'sss-frontend',
			SSS_PLUGIN_URL . 'assets/sss.js',
			[],
			SSS_PLUGIN_VERSION,
			true
		);

		wp_register_style(
			'sss-frontend',
			SSS_PLUGIN_URL . 'assets/sss.css',
			[],
			SSS_PLUGIN_VERSION
		);

		wp_enqueue_script('sss-frontend');
		wp_enqueue_style('sss-frontend');

		$cfg = [
			'restBase' => esc_url_raw(rest_url('sss/v1')),
		];

		wp_add_inline_script('sss-frontend', 'window.SSS = ' . wp_json_encode($cfg) . ';', 'before');
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

