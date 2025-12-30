# Pressify

Pressify is a **standalone WordPress plugin** (no WooCommerce dependency) that:

- **Syncs Shopify products + variants** into WordPress for browsing/content pages.
- Provides a **Shopify-backed cart + checkout** using the **Shopify Storefront API** (the cart lives in Shopify; WordPress stores a `pressify_cart_id` cookie).

## How it works (high level)

- **Products/variants sync**:
  - Pressify pulls products from the Shopify **Admin GraphQL API** and stores them as a WordPress custom post type.
  - Variants and option data are stored as post meta so templates/shortcodes/REST can render them.
- **Cart integration**:
  - “Add to cart”, quantity updates, and remove actions call Pressify’s WP REST endpoints.
  - Those endpoints call Shopify’s **Storefront GraphQL API** to create/fetch/update a Shopify cart.
  - Checkout is Shopify’s hosted checkout via the returned `checkoutUrl`.

## Requirements

- **WordPress**: 6.0+
- **PHP**: 7.4+
- **Shopify**:
  - A Shopify store domain (e.g. `your-store.myshopify.com`)
  - A Shopify **Admin API access token** (for syncing products/variants)
  - A Shopify **Storefront API access token** (for cart + checkout)

## Installation

1. Copy this plugin into your WordPress plugins directory so the main file ends up at:
   - `wp-content/plugins/pressify/pressify.php`
2. In WP Admin, go to **Plugins** → **Installed Plugins** and activate **Pressify**.

## Setup (WP Admin)

1. Go to **Settings** → **Pressify**.
2. Fill in:
   - **Shop domain**: `your-store.myshopify.com`
   - **Admin access token**: used for product/variant sync
   - **Storefront access token**: used for cart operations
3. (Optional) Enable **Scheduled sync** to run an hourly sync via WP-Cron.
4. Click **Save settings**.
5. Click **Run sync now** to import products/variants.

## Shopify token notes (what Pressify expects)

Pressify does not walk you through Shopify’s UI, but conceptually you need:

- **Admin token**:
  - From a Shopify app with Admin API access (Custom app or public app).
  - Must have scopes that allow reading products/variants (commonly “read_products”).
- **Storefront token**:
  - A Storefront API access token enabled for your store/app.
  - Must have Storefront API access to create carts and read product variant information.

## What gets created in WordPress

- **Custom post type**: `pressify_product`
  - Title/content are populated from Shopify.
  - Useful post meta includes:
    - `pressify_shopify_product_id`
    - `pressify_handle`
    - `pressify_featured_image_url`
    - `pressify_variants` (array)
    - `pressify_options` (array)

## Frontend usage

### Shortcodes

- **Product grid**:
  - `[pressify_products]`
  - Optional: `[pressify_products per_page="24"]`
- **Cart widget/page**:
  - `[pressify_cart]`

These shortcodes automatically enqueue Pressify’s frontend assets:

- `assets/pressify.js`
- `assets/pressify.css`

### Typical page setup

- Create a **Shop page** in WP and add:
  - `[pressify_products]`
- Create a **Cart page** in WP and add:
  - `[pressify_cart]`

## REST API (reference)

Pressify exposes a small public REST surface under:

- Base: `/wp-json/pressify/v1`

### Products

- `GET /products`
  - Query: `per_page` (default 24, max 100)
- `GET /products/{handle}`

### Cart (Shopify Storefront cart)

- `GET /cart`
- `POST /cart/create`
- `POST /cart/lines/add`
  - JSON body: `{ "variantId": "...", "quantity": 1 }`
- `POST /cart/lines/update`
  - JSON body: `{ "lineId": "...", "quantity": 2 }`
- `POST /cart/lines/remove`
  - JSON body: `{ "lineIds": ["..."] }`
- `GET /cart/checkout`
  - Returns `checkoutUrl`

## Scheduled sync

If “Scheduled sync” is enabled, Pressify schedules the WP-Cron hook:

- `pressify_sync_cron` (hourly)

Manual sync is always available from **Settings → Pressify**.

## Notes / limitations (current)

- **No webhooks** yet: product sync is currently pull-based (manual or hourly cron).
- **No order syncing**: checkout is handled by Shopify; Pressify does not create WP “orders”.
- **Cart line pagination**: cart fetch reads up to 50 cart lines per request.
- **Inventory/price freshness**: cart/checkout pricing is authoritative in Shopify; synced WP product content may lag until the next sync.

## Advanced: Shopify API version overrides

Pressify uses a default Shopify API version constant:

- `PRESSIFY_SHOPIFY_API_VERSION` (defined in `pressify.php`)

You normally **do not** need to set this during setup. If Shopify deprecates the version you’re using, you can override it:

- **Option A (recommended)**: define it in `wp-config.php` (so upgrades don’t overwrite your choice):

```php
define('PRESSIFY_SHOPIFY_API_VERSION', '2025-10');
```

- **Option B**: filter it from a small mu-plugin/theme snippet:

```php
add_filter('pressify_shopify_api_version', function ($version) {
    return '2025-10';
});
```

## Project layout

- `pressify.php`: plugin bootstrap (WordPress plugin entry file)
- `includes/`: PHP code (settings page, sync, REST, shortcodes)
- `assets/`: frontend JS/CSS
- `uninstall.php`: uninstall cleanup (options, cron, synced posts)
- `.cursor/rules/wordpress-plugin-handbook.mdc`: rules for future agents (follow the WP Plugin Handbook)

## Development note (for future contributors/agents)

This repository is structured to match the **WordPress Plugin Handbook**. When making changes, follow:

- https://developer.wordpress.org/plugins/intro/

