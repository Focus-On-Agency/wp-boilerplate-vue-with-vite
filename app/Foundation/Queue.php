<?php

namespace PluginClassName\Foundation;

use PluginClassName\Foundation\Queue\Drivers\ActionSchedulerDriver;
use PluginClassName\Foundation\Queue\Drivers\WPCronDriver;

if (!defined('ABSPATH')) {
	exit;
}

class Queue
{
    protected static ?self $instance = null;

    protected $driver;

    protected function __construct()
    {
        // Se Action Scheduler è disponibile → driver AS, altrimenti WP-Cron
        $this->driver = function_exists('as_enqueue_async_action')
            ? new ActionSchedulerDriver()
            : new WPCronDriver()
        ;
    }

    public static function instance(): self
    {
        return static::$instance ??= new self();
    }

    public function driver()
    {
        return $this->driver;
    }

    public function dispatch(string $action, array $payload = [], int $delaySeconds = 0, string $group = 'default'): void
    {
        $this->driver->dispatch($action, $payload, $delaySeconds, $group);
    }
}