<?php
/**
 * Plugin Name: Pressify
 * Description: Sync Shopify products/variants into WordPress and provide a Shopify-backed cart + checkout (no WooCommerce required).
 * Version: 0.1.0
 * Author: Your Company
 * License: GPL-2.0-or-later
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
	exit;
}

define('PRESSIFY_PLUGIN_VERSION', '0.1.0');
define('PRESSIFY_PLUGIN_FILE', __FILE__);
define('PRESSIFY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PRESSIFY_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once PRESSIFY_PLUGIN_DIR . 'includes/class-pressify-plugin.php';

add_action('plugins_loaded', static function () {
	\Pressify\Plugin::instance()->init();
});

