<?php

namespace Pressify;

if (!defined('ABSPATH')) {
	exit;
}

final class Sync {
	public const CRON_HOOK = 'pressify_sync_cron';

	public static function activate(): void {
		self::maybe_schedule();
	}

	public static function deactivate(): void {
		self::unschedule();
	}

	public static function maybe_schedule(): void {
		$opts = Options::get();
		$enabled = !empty($opts['enable_cron']);

		if (!$enabled) {
			self::unschedule();
			return;
		}

		if (!wp_next_scheduled(self::CRON_HOOK)) {
			wp_schedule_event(time() + 60, 'hourly', self::CRON_HOOK);
		}
	}

	public static function unschedule(): void {
		$ts = wp_next_scheduled(self::CRON_HOOK);
		while ($ts) {
			wp_unschedule_event($ts, self::CRON_HOOK);
			$ts = wp_next_scheduled(self::CRON_HOOK);
		}
	}

	public static function run_scheduled_sync(): void {
		try {
			self::run_sync();
		} catch (\Throwable $e) {
			if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
				error_log('[Pressify] Scheduled sync failed: ' . $e->getMessage()); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
			}
		}
	}

	public static function run_manual_sync(): string {
		self::run_sync();
		return 'ok';
	}

	public static function run_sync(): void {
		self::maybe_schedule();

		$client = new ShopifyClient();
		$cursor = null;
		$total = 0;

		$query = <<<'GQL'
query Products($first: Int!, $after: String) {
  products(first: $first, after: $after) {
    pageInfo { hasNextPage endCursor }
    edges {
      node {
        id
        handle
        title
        description
        descriptionHtml
        vendor
        productType
        status
        updatedAt
        featuredImage { url altText }
        options { name values }
        variants(first: 100) {
          edges {
            node {
              id
              title
              sku
              availableForSale
              price
              compareAtPrice
              selectedOptions { name value }
              image { url altText }
            }
          }
        }
      }
    }
  }
}
GQL;

		do {
			$data = $client->admin_graphql($query, [
				'first' => 50,
				'after' => $cursor,
			]);

			$products = $data['data']['products'] ?? null;
			if (!is_array($products)) {
				throw new \RuntimeException('Unexpected Shopify response shape (products missing).');
			}

			$edges = $products['edges'] ?? [];
			foreach ($edges as $edge) {
				$node = $edge['node'] ?? null;
				if (!is_array($node)) {
					continue;
				}

				$variantEdges = $node['variants']['edges'] ?? [];
				$variants = [];
				foreach ($variantEdges as $ve) {
					$vn = $ve['node'] ?? null;
					if (!is_array($vn)) {
						continue;
					}
					$variants[] = [
						'id' => (string) ($vn['id'] ?? ''),
						'title' => (string) ($vn['title'] ?? ''),
						'sku' => (string) ($vn['sku'] ?? ''),
						'availableForSale' => (bool) ($vn['availableForSale'] ?? false),
						'price' => (string) ($vn['price'] ?? ''),
						'compareAtPrice' => (string) ($vn['compareAtPrice'] ?? ''),
						'selectedOptions' => is_array($vn['selectedOptions'] ?? null) ? $vn['selectedOptions'] : [],
						'image' => isset($vn['image']) && is_array($vn['image']) ? $vn['image'] : null,
					];
				}

				$node['variants'] = $variants;
				Products::upsert_from_shopify($node);
				$total++;
			}

			$pageInfo = $products['pageInfo'] ?? [];
			$hasNext = !empty($pageInfo['hasNextPage']);
			$cursor = $hasNext ? (string) ($pageInfo['endCursor'] ?? '') : null;
		} while (!empty($hasNext) && !empty($cursor));

		update_option('pressify_last_sync_at', gmdate('c'));
		update_option('pressify_last_sync_count', (int) $total);
	}
}

