<?php

namespace SSS;

if (!defined('ABSPATH')) {
	exit;
}

final class Options {
	public const GROUP = 'sss_options';

	public static function get(): array {
		$opts = get_option(self::GROUP, []);
		return is_array($opts) ? $opts : [];
	}

	public static function sanitize($raw): array {
		$raw = is_array($raw) ? $raw : [];

		$opts = [];
		$opts['shop_domain'] = isset($raw['shop_domain']) ? sanitize_text_field((string) $raw['shop_domain']) : '';
		$opts['admin_access_token'] = isset($raw['admin_access_token']) ? sanitize_text_field((string) $raw['admin_access_token']) : '';
		$opts['storefront_access_token'] = isset($raw['storefront_access_token']) ? sanitize_text_field((string) $raw['storefront_access_token']) : '';
		$opts['api_version'] = isset($raw['api_version']) && $raw['api_version'] !== ''
			? sanitize_text_field((string) $raw['api_version'])
			: '2025-10';
		$opts['enable_cron'] = !empty($raw['enable_cron']) ? 1 : 0;

		// Optional for webhook verification (not required for the initial sync/cart).
		$opts['webhook_secret'] = isset($raw['webhook_secret']) ? sanitize_text_field((string) $raw['webhook_secret']) : '';

		/**
		 * If options change we may need to reschedule cron.
		 * We avoid direct scheduling here to keep sanitize pure; Sync handles it on load.
		 */
		return $opts;
	}

	public static function get_required(string $key): string {
		$opts = self::get();
		$val = isset($opts[$key]) ? trim((string) $opts[$key]) : '';
		if ($val === '') {
			throw new \RuntimeException(sprintf('Missing required setting: %s', $key));
		}
		return $val;
	}
}

