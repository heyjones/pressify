<?php

namespace Pressify;

if (!defined('ABSPATH')) {
	exit;
}

final class ShopifyClient {
	private string $shopDomain;
	private string $apiVersion;
	private string $adminAccessToken;
	private string $storefrontAccessToken;

	public function __construct() {
		$this->shopDomain = Options::get_required('shop_domain');
		$this->adminAccessToken = Options::get_required('admin_access_token');
		$this->storefrontAccessToken = Options::get_required('storefront_access_token');
		$this->apiVersion = $this->resolve_api_version();
	}

	public function admin_graphql(string $query, array $variables = []): array {
		$url = sprintf('https://%s/admin/api/%s/graphql.json', $this->shopDomain, $this->apiVersion);
		return $this->graphql($url, [
			'X-Shopify-Access-Token' => $this->adminAccessToken,
		], $query, $variables);
	}

	public function storefront_graphql(string $query, array $variables = []): array {
		$url = sprintf('https://%s/api/%s/graphql.json', $this->shopDomain, $this->apiVersion);
		return $this->graphql($url, [
			'X-Shopify-Storefront-Access-Token' => $this->storefrontAccessToken,
		], $query, $variables);
	}

	private function resolve_api_version(): string {
		// Default is controlled via a constant so setup is plug-and-play.
		$version = defined('PRESSIFY_SHOPIFY_API_VERSION') ? (string) PRESSIFY_SHOPIFY_API_VERSION : '2025-10';

		// Allow advanced override via saved option (if set).
		$opts = Options::get();
		if (!empty($opts['api_version'])) {
			$version = (string) $opts['api_version'];
		}

		/**
		 * Final override point.
		 *
		 * @param string $version Shopify API version (e.g. "2025-10")
		 */
		$version = (string) apply_filters('pressify_shopify_api_version', $version);

		$version = trim($version);
		if ($version === '') {
			$version = '2025-10';
		}

		return $version;
	}

	private function graphql(string $url, array $headers, string $query, array $variables): array {
		$headers = array_merge($headers, [
			'Content-Type' => 'application/json',
			'Accept' => 'application/json',
		]);

		$resp = wp_remote_post($url, [
			'timeout' => 30,
			'headers' => $headers,
			'body' => wp_json_encode([
				'query' => $query,
				'variables' => (object) $variables,
			]),
		]);

		if (is_wp_error($resp)) {
			throw new \RuntimeException('Shopify request failed: ' . $resp->get_error_message());
		}

		$code = (int) wp_remote_retrieve_response_code($resp);
		$body = wp_remote_retrieve_body($resp);
		$data = json_decode($body, true);

		if ($code < 200 || $code >= 300) {
			throw new \RuntimeException(sprintf('Shopify HTTP %d: %s', $code, is_string($body) ? $body : ''));
		}

		if (!is_array($data)) {
			throw new \RuntimeException('Shopify returned non-JSON response.');
		}

		if (!empty($data['errors'])) {
			throw new \RuntimeException('Shopify GraphQL errors: ' . wp_json_encode($data['errors']));
		}

		return $data;
	}
}

