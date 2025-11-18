<?php

namespace PluginClassName\Console;

use PluginClassName\Console\Commands\BookingReminder;
use PluginClassName\Console\Commands\CleanupDraftBookings;

if (!defined('ABSPATH')) {
	exit;
}

class Schedules
{
    public function __construct()
    {
        add_filter('cron_schedules', function ($schedules) {
			$schedules['every_15_minutes'] = [
				'interval' => 15 * MINUTE_IN_SECONDS,
				'display'  => __('Every 15 Minutes', PluginClassName_NAME_SPACE),
			];

			return $schedules;
		});
    }

    static public function register(): void
    {
       //
    }
}