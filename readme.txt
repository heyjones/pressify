=== Pressify ===
Contributors: pressify
Tags: shopify, ecommerce, cart, checkout, products
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Sync Shopify products into WordPress and provide a Shopify-backed cart + checkout (no WooCommerce required).

== Description ==

Pressify is a standalone WordPress plugin that:

* Syncs Shopify products + variants into WordPress as a custom post type for browsing/content pages.
* Provides a Shopify-backed cart + checkout using the Shopify Storefront API.

== Installation ==

1. Upload the plugin folder to the `/wp-content/plugins/` directory (so `pressify.php` is at `/wp-content/plugins/pressify/pressify.php`).
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **Settings → Pressify** and configure your Shopify credentials.

== Frequently Asked Questions ==

= Do I need WooCommerce? =

No.

= What Shopify tokens do I need? =

You need:

* An **Admin API access token** to sync products/variants.
* A **Storefront API access token** for cart + checkout operations.

== Shortcodes ==

* `[pressify_products]` (optional: `per_page="24"`)
* `[pressify_cart]`

== Uninstall ==

Pressify includes `uninstall.php` to clean up options and cron on uninstall.

== Changelog ==

= 0.1.0 =
* Initial release.

