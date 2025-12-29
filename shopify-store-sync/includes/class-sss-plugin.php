<?php

namespace SSS;

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
	}

	private function includes(): void {
		require_once SSS_PLUGIN_DIR . 'includes/class-sss-options.php';
		require_once SSS_PLUGIN_DIR . 'includes/class-sss-shopify-client.php';
		require_once SSS_PLUGIN_DIR . 'includes/class-sss-products.php';
		require_once SSS_PLUGIN_DIR . 'includes/class-sss-sync.php';
		require_once SSS_PLUGIN_DIR . 'includes/class-sss-rest.php';
		require_once SSS_PLUGIN_DIR . 'includes/class-sss-shortcodes.php';
		require_once SSS_PLUGIN_DIR . 'includes/class-sss-assets.php';
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
		add_action('sss_sync_cron', [Sync::class, 'run_scheduled_sync']);
		register_activation_hook(SSS_PLUGIN_FILE, [Sync::class, 'activate']);
		register_deactivation_hook(SSS_PLUGIN_FILE, [Sync::class, 'deactivate']);
	}
}

// Minimal admin page lives in a nested namespace for clarity.
namespace SSS\Admin;

use SSS\Options;
use SSS\Sync;

if (!defined('ABSPATH')) {
	exit;
}

final class OptionsPage {
	public static function register_menu(): void {
		add_options_page(
			'Shopify Store Sync',
			'Shopify Sync',
			'manage_options',
			'sss-shopify-sync',
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
			'sss_section_shopify',
			'Shopify connection',
			static function () {
				echo '<p>Configure your Shopify store connection. The Admin token is used for product/variant sync. The Storefront token is used for cart + checkout.</p>';
			},
			'sss-shopify-sync'
		);

		self::add_field('shop_domain', 'Shop domain', 'e.g. <code>my-store.myshopify.com</code>');
		self::add_field('admin_access_token', 'Admin access token', 'Private; required to sync products/variants.', true);
		self::add_field('storefront_access_token', 'Storefront access token', 'Used for cart operations; can be exposed but we keep it server-side.', true);
		self::add_field('api_version', 'API version', 'e.g. <code>2025-10</code>.');

		add_settings_section(
			'sss_section_sync',
			'Sync',
			static function () {
				echo '<p>Run a manual sync now, or enable scheduled sync.</p>';
			},
			'sss-shopify-sync'
		);

		add_settings_field(
			'sss_enable_cron',
			'Scheduled sync',
			static function () {
				$opts = Options::get();
				$checked = !empty($opts['enable_cron']) ? 'checked' : '';
				echo '<label><input type="checkbox" name="' . esc_attr(Options::GROUP) . '[enable_cron]" value="1" ' . $checked . '> Enable hourly sync</label>';
			},
			'sss-shopify-sync',
			'sss_section_sync'
		);
	}

	private static function add_field(string $key, string $label, string $help, bool $is_secret = false): void {
		add_settings_field(
			'sss_' . $key,
			esc_html($label),
			static function () use ($key, $help, $is_secret) {
				$opts = Options::get();
				$val = isset($opts[$key]) ? (string) $opts[$key] : '';
				$type = $is_secret ? 'password' : 'text';
				echo '<input type="' . esc_attr($type) . '" class="regular-text" name="' . esc_attr(Options::GROUP) . '[' . esc_attr($key) . ']" value="' . esc_attr($val) . '">';
				echo '<p class="description">' . wp_kses_post($help) . '</p>';
			},
			'sss-shopify-sync',
			'sss_section_shopify'
		);
	}

	public static function render(): void {
		if (!current_user_can('manage_options')) {
			return;
		}

		$did_sync = false;
		$sync_error = null;
		if (isset($_POST['sss_manual_sync']) && check_admin_referer('sss_manual_sync_action')) {
			try {
				$result = Sync::run_manual_sync();
				$did_sync = true;
				add_settings_error('sss_messages', 'sss_synced', 'Sync complete: ' . esc_html($result), 'updated');
			} catch (\Throwable $e) {
				$sync_error = $e->getMessage();
				add_settings_error('sss_messages', 'sss_sync_failed', 'Sync failed: ' . esc_html($sync_error), 'error');
			}
		}

		?>
		<div class="wrap">
			<h1>Shopify Store Sync</h1>
			<?php settings_errors('sss_messages'); ?>
			<form action="options.php" method="post">
				<?php
				settings_fields(Options::GROUP);
				do_settings_sections('sss-shopify-sync');
				submit_button('Save settings');
				?>
			</form>

			<hr>
			<h2>Manual sync</h2>
			<form method="post">
				<?php wp_nonce_field('sss_manual_sync_action'); ?>
				<?php submit_button('Run sync now', 'secondary', 'sss_manual_sync', false); ?>
			</form>
			<?php if ($did_sync && $sync_error === null) : ?>
				<p>Done.</p>
			<?php endif; ?>
		</div>
		<?php
	}
}

