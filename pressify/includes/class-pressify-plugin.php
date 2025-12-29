<?php

namespace Pressify;

if (!defined('ABSPATH')) {
	exit;
}

final class Plugin {
	private static ?Plugin $instance = null;

	public static function instance(): Plugin {
		if (self::$instance === null) {
			self::$instance = new Plugin();
		}
		return self::$instance;
	}

	public function init(): void {
		$this->includes();
		$this->hooks();
		Sync::maybe_schedule();
	}

	private function includes(): void {
		require_once PRESSIFY_PLUGIN_DIR . 'includes/class-pressify-options.php';
		require_once PRESSIFY_PLUGIN_DIR . 'includes/class-pressify-shopify-client.php';
		require_once PRESSIFY_PLUGIN_DIR . 'includes/class-pressify-products.php';
		require_once PRESSIFY_PLUGIN_DIR . 'includes/class-pressify-sync.php';
		require_once PRESSIFY_PLUGIN_DIR . 'includes/class-pressify-rest.php';
		require_once PRESSIFY_PLUGIN_DIR . 'includes/class-pressify-shortcodes.php';
		require_once PRESSIFY_PLUGIN_DIR . 'includes/class-pressify-assets.php';
	}

	private function hooks(): void {
		// Admin.
		add_action('admin_menu', [Admin\OptionsPage::class, 'register_menu']);
		add_action('admin_init', [Admin\OptionsPage::class, 'register_settings']);
		add_action('update_option_' . Options::GROUP, [Sync::class, 'maybe_schedule'], 10, 0);
		add_action('add_option_' . Options::GROUP, [Sync::class, 'maybe_schedule'], 10, 0);

		// Data model.
		add_action('init', [Products::class, 'register_cpt']);

		// REST.
		add_action('rest_api_init', [Rest::class, 'register_routes']);

		// Shortcodes + assets.
		add_action('init', [Shortcodes::class, 'register']);
		add_action('wp_enqueue_scripts', [Assets::class, 'register']);

		// Cron.
		add_action(Sync::CRON_HOOK, [Sync::class, 'run_scheduled_sync']);
		register_activation_hook(PRESSIFY_PLUGIN_FILE, [Sync::class, 'activate']);
		register_deactivation_hook(PRESSIFY_PLUGIN_FILE, [Sync::class, 'deactivate']);
	}
}

// Admin page (kept in this file for now, small plugin).
namespace Pressify\Admin;

use Pressify\Options;
use Pressify\Sync;

if (!defined('ABSPATH')) {
	exit;
}

final class OptionsPage {
	public static function register_menu(): void {
		add_options_page(
			'Pressify',
			'Pressify',
			'manage_options',
			'pressify',
			[self::class, 'render']
		);
	}

	public static function register_settings(): void {
		register_setting(Options::GROUP, Options::GROUP, [
			'type' => 'array',
			'sanitize_callback' => [Options::class, 'sanitize'],
			'default' => [],
		]);

		add_settings_section(
			'pressify_section_shopify',
			'Shopify connection',
			static function () {
				echo '<p>Configure your Shopify store connection. The Admin token is used for product/variant sync. The Storefront token is used for cart + checkout.</p>';
			},
			'pressify'
		);

		self::add_field('shop_domain', 'Shop domain', 'e.g. <code>my-store.myshopify.com</code>');
		self::add_field('admin_access_token', 'Admin access token', 'Private; required to sync products/variants.', true);
		self::add_field('storefront_access_token', 'Storefront access token', 'Used for cart operations; can be exposed but we keep it server-side.', true);
		self::add_field('api_version', 'API version', 'e.g. <code>2025-10</code>.');

		add_settings_section(
			'pressify_section_sync',
			'Sync',
			static function () {
				echo '<p>Run a manual sync now, or enable scheduled sync.</p>';
			},
			'pressify'
		);

		add_settings_field(
			'pressify_enable_cron',
			'Scheduled sync',
			static function () {
				$opts = Options::get();
				$checked = !empty($opts['enable_cron']) ? 'checked' : '';
				echo '<label><input type="checkbox" name="' . esc_attr(Options::GROUP) . '[enable_cron]" value="1" ' . $checked . '> Enable hourly sync</label>';
			},
			'pressify',
			'pressify_section_sync'
		);
	}

	private static function add_field(string $key, string $label, string $help, bool $is_secret = false): void {
		add_settings_field(
			'pressify_' . $key,
			esc_html($label),
			static function () use ($key, $help, $is_secret) {
				$opts = Options::get();
				$val = isset($opts[$key]) ? (string) $opts[$key] : '';
				$type = $is_secret ? 'password' : 'text';
				echo '<input type="' . esc_attr($type) . '" class="regular-text" name="' . esc_attr(Options::GROUP) . '[' . esc_attr($key) . ']" value="' . esc_attr($val) . '">';
				echo '<p class="description">' . wp_kses_post($help) . '</p>';
			},
			'pressify',
			'pressify_section_shopify'
		);
	}

	public static function render(): void {
		if (!current_user_can('manage_options')) {
			return;
		}

		if (isset($_POST['pressify_manual_sync']) && check_admin_referer('pressify_manual_sync_action')) {
			try {
				$result = Sync::run_manual_sync();
				add_settings_error('pressify_messages', 'pressify_synced', 'Sync complete: ' . esc_html($result), 'updated');
			} catch (\Throwable $e) {
				add_settings_error('pressify_messages', 'pressify_sync_failed', 'Sync failed: ' . esc_html($e->getMessage()), 'error');
			}
		}

		?>
		<div class="wrap">
			<h1>Pressify</h1>
			<?php settings_errors('pressify_messages'); ?>
			<form action="options.php" method="post">
				<?php
				settings_fields(Options::GROUP);
				do_settings_sections('pressify');
				submit_button('Save settings');
				?>
			</form>

			<hr>
			<h2>Manual sync</h2>
			<form method="post">
				<?php wp_nonce_field('pressify_manual_sync_action'); ?>
				<?php submit_button('Run sync now', 'secondary', 'pressify_manual_sync', false); ?>
			</form>
		</div>
		<?php
	}
}

