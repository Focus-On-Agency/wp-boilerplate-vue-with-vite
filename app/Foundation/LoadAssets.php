<?php

namespace PluginClassName\Foundation;

use function is_singular;
use function get_post;
use function has_shortcode;

if (!defined('ABSPATH')) {
	exit;
}

class LoadAssets
{
	public function admin()
	{
		Vite::enqueueScript('PluginClassName-script-boot', 'js/admin/main.js', ['wp-i18n','wp-hooks'], PluginClassName_VERSION, true);
	}

	public function frontend(): void
	{
		// Early return if not singular page
		if (!is_singular()) {
			return;
		}
		
		$post = get_post();
		if (!$post || empty($post->post_content)) {
			return;
		}
		
		// Check for shortcode only if we have content
		if (has_shortcode($post->post_content, 'restaurant_reservations_app')) {
			Vite::enqueueScript('PluginClassName-script-boot', 'js/frontend/main.js', ['wp-i18n','wp-hooks'], PluginClassName_VERSION, true);
		}
	}
}
