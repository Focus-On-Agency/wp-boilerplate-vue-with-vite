<?php

namespace PluginClassName\Foundation\Queue\Drivers;

if (!defined('ABSPATH')) {
	exit;
}

class ActionSchedulerDriver
{
    public function dispatch(string $action, array $payload = [], int $delaySeconds = 0, string $group = 'default'): void
    {
        if ($delaySeconds > 0) {
            as_schedule_single_action(time() + $delaySeconds, $action, [$payload], $group);
        } else {
            as_enqueue_async_action($action, [$payload], $group);
        }
    }
}