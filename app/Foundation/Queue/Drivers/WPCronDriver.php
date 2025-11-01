<?php

namespace PluginClassName\Foundation\Queue\Drivers;

if (!defined('ABSPATH')) {
	exit;
}

class WPCronDriver
{
	public function dispatch(string $action, array $payload = [], int $delaySeconds = 0, string $group = 'default'): void
    {
        // $group non ha significato in WP-Cron, lo ignoriamo.
        $ts = time() + max(0, $delaySeconds);
        // Con WP-Cron gli args vanno passati come array dentro un array
        wp_schedule_single_event($ts, $action, [$payload]);
    }
}