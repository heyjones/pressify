<?php
/**
 * Pressify uninstall cleanup.
 *
 * This file is executed by WordPress when the plugin is uninstalled (not just deactivated).
 *
 * @see https://developer.wordpress.org/plugins/plugin-basics/uninstall-methods/
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

// Remove plugin options.
delete_option('pressify_options');
delete_option('pressify_last_sync_at');
delete_option('pressify_last_sync_count');

// Unschedule any remaining cron events (in case the plugin was removed without deactivation).
$hook = 'pressify_sync_cron';
$ts = wp_next_scheduled($hook);
while ($ts) {
	wp_unschedule_event($ts, $hook);
	$ts = wp_next_scheduled($hook);
}

// Optional: remove synced product posts.
// If you want to keep data on uninstall, delete this block.
$post_ids = get_posts([
	'post_type' => 'pressify_product',
	'fields' => 'ids',
	'posts_per_page' => -1,
	'post_status' => 'any',
	'no_found_rows' => true,
	'suppress_filters' => true,
]);

if (is_array($post_ids)) {
	foreach ($post_ids as $post_id) {
		wp_delete_post((int) $post_id, true);
	}
}

