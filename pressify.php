<?php
/**
 * Plugin Name: Pressify
 * Description: Sync Shopify products/variants into WordPress and provide a Shopify-backed cart + checkout (no WooCommerce required).
 * Version: 0.1.0
 * Author: Your Company
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: pressify
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

defined('ABSPATH') || exit;

define('PRESSIFY_PLUGIN_VERSION', '0.1.0');
define('PRESSIFY_PLUGIN_FILE', __FILE__);
define('PRESSIFY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PRESSIFY_PLUGIN_URL', plugin_dir_url(__FILE__));
if (!defined('PRESSIFY_SHOPIFY_API_VERSION')) {
	define('PRESSIFY_SHOPIFY_API_VERSION', '2025-10');
}

require_once PRESSIFY_PLUGIN_DIR . 'includes/class-pressify-plugin.php';

add_action('plugins_loaded', static function () {
	\Pressify\Plugin::instance()->init();
});

