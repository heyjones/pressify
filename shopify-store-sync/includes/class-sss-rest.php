<?php

namespace SSS;

use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) {
	exit;
}

final class Rest {
	private const NS = 'sss/v1';
	private const CART_COOKIE = 'sss_cart_id';

	public static function register_routes(): void {
		register_rest_route(self::NS, '/products', [
			'methods' => 'GET',
			'permission_callback' => '__return_true',
			'callback' => [self::class, 'products_index'],
		]);

		register_rest_route(self::NS, '/products/(?P<handle>[a-z0-9\\-]+)', [
			'methods' => 'GET',
			'permission_callback' => '__return_true',
			'callback' => [self::class, 'products_show'],
			'args' => [
				'handle' => ['required' => true],
			],
		]);

		register_rest_route(self::NS, '/cart', [
			'methods' => 'GET',
			'permission_callback' => '__return_true',
			'callback' => [self::class, 'cart_get'],
		]);

		register_rest_route(self::NS, '/cart/create', [
			'methods' => 'POST',
			'permission_callback' => '__return_true',
			'callback' => [self::class, 'cart_create'],
		]);

		register_rest_route(self::NS, '/cart/lines/add', [
			'methods' => 'POST',
			'permission_callback' => '__return_true',
			'callback' => [self::class, 'cart_lines_add'],
		]);

		register_rest_route(self::NS, '/cart/lines/update', [
			'methods' => 'POST',
			'permission_callback' => '__return_true',
			'callback' => [self::class, 'cart_lines_update'],
		]);

		register_rest_route(self::NS, '/cart/lines/remove', [
			'methods' => 'POST',
			'permission_callback' => '__return_true',
			'callback' => [self::class, 'cart_lines_remove'],
		]);

		register_rest_route(self::NS, '/cart/checkout', [
			'methods' => 'GET',
			'permission_callback' => '__return_true',
			'callback' => [self::class, 'cart_checkout'],
		]);
	}

	public static function products_index(WP_REST_Request $req): WP_REST_Response {
		$limit = (int) $req->get_param('per_page');
		if ($limit <= 0 || $limit > 100) {
			$limit = 24;
		}

		$q = new \WP_Query([
			'post_type' => Products::CPT,
			'post_status' => 'publish',
			'posts_per_page' => $limit,
			'orderby' => 'date',
			'order' => 'DESC',
			'no_found_rows' => true,
		]);

		$out = [];
		foreach ($q->posts as $p) {
			$out[] = self::serialize_product((int) $p->ID);
		}
		return new WP_REST_Response(['products' => $out], 200);
	}

	public static function products_show(WP_REST_Request $req): WP_REST_Response {
		$handle = (string) $req->get_param('handle');
		$q = new \WP_Query([
			'post_type' => Products::CPT,
			'post_status' => 'publish',
			'posts_per_page' => 1,
			'meta_query' => [[
				'key' => 'sss_handle',
				'value' => $handle,
				'compare' => '=',
			]],
			'no_found_rows' => true,
		]);

		if (empty($q->posts)) {
			return new WP_REST_Response(['message' => 'Not found'], 404);
		}

		return new WP_REST_Response(['product' => self::serialize_product((int) $q->posts[0]->ID)], 200);
	}

	private static function serialize_product(int $post_id): array {
		$variants = get_post_meta($post_id, 'sss_variants', true);
		if (!is_array($variants)) {
			$variants = [];
		}
		$options = get_post_meta($post_id, 'sss_options', true);
		if (!is_array($options)) {
			$options = [];
		}

		return [
			'id' => $post_id,
			'title' => get_the_title($post_id),
			'permalink' => get_permalink($post_id),
			'handle' => (string) get_post_meta($post_id, 'sss_handle', true),
			'featuredImageUrl' => (string) get_post_meta($post_id, 'sss_featured_image_url', true),
			'variants' => $variants,
			'options' => $options,
		];
	}

	public static function cart_get(WP_REST_Request $req): WP_REST_Response {
		$cartId = self::get_cart_id();
		if ($cartId === null) {
			return new WP_REST_Response(['cart' => null], 200);
		}

		return new WP_REST_Response(['cart' => self::shopify_cart_fetch($cartId)], 200);
	}

	public static function cart_create(WP_REST_Request $req): WP_REST_Response {
		$cart = self::shopify_cart_create();
		self::set_cart_id((string) $cart['id']);
		return new WP_REST_Response(['cart' => $cart], 201);
	}

	public static function cart_lines_add(WP_REST_Request $req): WP_REST_Response {
		$variantId = (string) $req->get_param('variantId');
		$qty = (int) $req->get_param('quantity');
		if ($variantId === '' || $qty <= 0) {
			return new WP_REST_Response(['message' => 'variantId and quantity required'], 400);
		}

		$cartId = self::get_or_create_cart_id();
		$cart = self::shopify_cart_lines_add($cartId, $variantId, $qty);
		return new WP_REST_Response(['cart' => $cart], 200);
	}

	public static function cart_lines_update(WP_REST_Request $req): WP_REST_Response {
		$lineId = (string) $req->get_param('lineId');
		$qty = (int) $req->get_param('quantity');
		if ($lineId === '' || $qty < 0) {
			return new WP_REST_Response(['message' => 'lineId and quantity required'], 400);
		}

		$cartId = self::get_cart_id();
		if ($cartId === null) {
			return new WP_REST_Response(['message' => 'No cart'], 404);
		}

		$cart = self::shopify_cart_lines_update($cartId, $lineId, $qty);
		return new WP_REST_Response(['cart' => $cart], 200);
	}

	public static function cart_lines_remove(WP_REST_Request $req): WP_REST_Response {
		$lineIds = $req->get_param('lineIds');
		if (is_string($lineIds)) {
			$lineIds = [$lineIds];
		}
		if (!is_array($lineIds) || empty($lineIds)) {
			return new WP_REST_Response(['message' => 'lineIds required'], 400);
		}
		$lineIds = array_values(array_filter(array_map('strval', $lineIds)));

		$cartId = self::get_cart_id();
		if ($cartId === null) {
			return new WP_REST_Response(['message' => 'No cart'], 404);
		}

		$cart = self::shopify_cart_lines_remove($cartId, $lineIds);
		return new WP_REST_Response(['cart' => $cart], 200);
	}

	public static function cart_checkout(WP_REST_Request $req): WP_REST_Response {
		$cartId = self::get_cart_id();
		if ($cartId === null) {
			return new WP_REST_Response(['message' => 'No cart'], 404);
		}

		$cart = self::shopify_cart_fetch($cartId);
		$url = (string) ($cart['checkoutUrl'] ?? '');
		return new WP_REST_Response(['checkoutUrl' => $url, 'cart' => $cart], 200);
	}

	private static function get_cart_id(): ?string {
		if (empty($_COOKIE[self::CART_COOKIE])) {
			return null;
		}
		$id = (string) wp_unslash($_COOKIE[self::CART_COOKIE]);
		return $id !== '' ? $id : null;
	}

	private static function get_or_create_cart_id(): string {
		$existing = self::get_cart_id();
		if ($existing !== null) {
			return $existing;
		}
		$cart = self::shopify_cart_create();
		$id = (string) $cart['id'];
		self::set_cart_id($id);
		return $id;
	}

	private static function set_cart_id(string $id): void {
		$secure = is_ssl();
		$httponly = true;
		$samesite = 'Lax';

		// PHP < 7.3 compatibility: fall back to a simple cookie string if needed.
		if (PHP_VERSION_ID >= 70300) {
			setcookie(self::CART_COOKIE, $id, [
				'expires' => time() + 60 * 60 * 24 * 14,
				'path' => COOKIEPATH ? COOKIEPATH : '/',
				'domain' => COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
				'secure' => $secure,
				'httponly' => $httponly,
				'samesite' => $samesite,
			]);
		} else {
			$path = (COOKIEPATH ? COOKIEPATH : '/') . '; samesite=' . $samesite;
			setcookie(self::CART_COOKIE, $id, time() + 60 * 60 * 24 * 14, $path, COOKIE_DOMAIN ? COOKIE_DOMAIN : '', $secure, $httponly);
		}

		$_COOKIE[self::CART_COOKIE] = $id;
	}

	private static function shopify_cart_create(): array {
		$client = new ShopifyClient();
		$mutation = <<<'GQL'
mutation CartCreate($input: CartInput!) {
  cartCreate(input: $input) {
    cart {
      id
      checkoutUrl
      totalQuantity
      cost {
        subtotalAmount { amount currencyCode }
        totalAmount { amount currencyCode }
      }
    }
    userErrors { field message }
  }
}
GQL;
		$vars = [
			'input' => (object) [],
		];

		$data = $client->storefront_graphql($mutation, $vars);
		$payload = $data['data']['cartCreate'] ?? null;
		if (!is_array($payload)) {
			throw new \RuntimeException('Unexpected Storefront response (cartCreate missing).');
		}
		if (!empty($payload['userErrors'])) {
			throw new \RuntimeException('Shopify cartCreate failed: ' . wp_json_encode($payload['userErrors']));
		}
		return (array) ($payload['cart'] ?? []);
	}

	private static function shopify_cart_fetch(string $cartId): array {
		$client = new ShopifyClient();
		$query = <<<'GQL'
query Cart($id: ID!) {
  cart(id: $id) {
    id
    checkoutUrl
    totalQuantity
    cost {
      subtotalAmount { amount currencyCode }
      totalAmount { amount currencyCode }
    }
    lines(first: 50) {
      edges {
        node {
          id
          quantity
          cost { totalAmount { amount currencyCode } }
          merchandise {
            ... on ProductVariant {
              id
              title
              sku
              image { url altText }
              selectedOptions { name value }
              product { title handle }
              price { amount currencyCode }
            }
          }
        }
      }
    }
  }
}
GQL;
		$data = $client->storefront_graphql($query, ['id' => $cartId]);
		$cart = $data['data']['cart'] ?? null;
		if (!is_array($cart)) {
			// Cart might have expired/been completed; clear cookie on next request.
			return [];
		}

		// Normalize edges for frontend convenience.
		$lines = [];
		$edges = $cart['lines']['edges'] ?? [];
		foreach ($edges as $edge) {
			$node = $edge['node'] ?? null;
			if (is_array($node)) {
				$lines[] = $node;
			}
		}
		$cart['lines'] = $lines;

		return $cart;
	}

	private static function shopify_cart_lines_add(string $cartId, string $variantId, int $qty): array {
		$client = new ShopifyClient();
		$mutation = <<<'GQL'
mutation CartLinesAdd($cartId: ID!, $lines: [CartLineInput!]!) {
  cartLinesAdd(cartId: $cartId, lines: $lines) {
    cart { id }
    userErrors { field message }
  }
}
GQL;
		$data = $client->storefront_graphql($mutation, [
			'cartId' => $cartId,
			'lines' => [[
				'merchandiseId' => $variantId,
				'quantity' => $qty,
			]],
		]);

		$payload = $data['data']['cartLinesAdd'] ?? null;
		if (!is_array($payload)) {
			throw new \RuntimeException('Unexpected Storefront response (cartLinesAdd missing).');
		}
		if (!empty($payload['userErrors'])) {
			throw new \RuntimeException('Shopify cartLinesAdd failed: ' . wp_json_encode($payload['userErrors']));
		}

		return self::shopify_cart_fetch($cartId);
	}

	private static function shopify_cart_lines_update(string $cartId, string $lineId, int $qty): array {
		$client = new ShopifyClient();
		$mutation = <<<'GQL'
mutation CartLinesUpdate($cartId: ID!, $lines: [CartLineUpdateInput!]!) {
  cartLinesUpdate(cartId: $cartId, lines: $lines) {
    cart { id }
    userErrors { field message }
  }
}
GQL;
		$data = $client->storefront_graphql($mutation, [
			'cartId' => $cartId,
			'lines' => [[
				'id' => $lineId,
				'quantity' => $qty,
			]],
		]);

		$payload = $data['data']['cartLinesUpdate'] ?? null;
		if (!is_array($payload)) {
			throw new \RuntimeException('Unexpected Storefront response (cartLinesUpdate missing).');
		}
		if (!empty($payload['userErrors'])) {
			throw new \RuntimeException('Shopify cartLinesUpdate failed: ' . wp_json_encode($payload['userErrors']));
		}

		return self::shopify_cart_fetch($cartId);
	}

	private static function shopify_cart_lines_remove(string $cartId, array $lineIds): array {
		$client = new ShopifyClient();
		$mutation = <<<'GQL'
mutation CartLinesRemove($cartId: ID!, $lineIds: [ID!]!) {
  cartLinesRemove(cartId: $cartId, lineIds: $lineIds) {
    cart { id }
    userErrors { field message }
  }
}
GQL;
		$data = $client->storefront_graphql($mutation, [
			'cartId' => $cartId,
			'lineIds' => array_values($lineIds),
		]);

		$payload = $data['data']['cartLinesRemove'] ?? null;
		if (!is_array($payload)) {
			throw new \RuntimeException('Unexpected Storefront response (cartLinesRemove missing).');
		}
		if (!empty($payload['userErrors'])) {
			throw new \RuntimeException('Shopify cartLinesRemove failed: ' . wp_json_encode($payload['userErrors']));
		}

		return self::shopify_cart_fetch($cartId);
	}
}

