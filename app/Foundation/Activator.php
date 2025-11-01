<?php

namespace PluginClassName\Foundation;

use Exception;
use function flush_rewrite_rules;
use function get_sites;
use function get_current_network_id;
use function switch_to_blog;
use function restore_current_blog;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Handles plugin activation and database migrations
 * @since 1.0.0
 */
class Activator
{
	/** @var bool */
	private bool $networkWide;

	private const OPTION_INSTANCE_UUID = 'fson_rrt_instance_uuid';

	/**
	 * @param bool $networkWide
	 */
	public function __construct(bool $networkWide = false)
	{
		$this->networkWide = $networkWide;
	}

	public function boot(): void
	{
		$this->networkWide();
		flush_rewrite_rules();
	}

	public function networkWide(): void
	{
		if ($this->networkWide && function_exists('get_sites') && function_exists('get_current_network_id')) {
			$site_ids = get_sites([
				'fields'     => 'ids',
				'network_id' => get_current_network_id(),
			]);

			foreach ($site_ids as $site_id) {
				switch_to_blog($site_id);

				$this->runMigrator();
				$this->ensureInstanceUuid();
				$this->scheduleEvent();

				restore_current_blog();
			}
		} else {
			$this->runMigrator();
			$this->ensureInstanceUuid();
			$this->scheduleEvent();
		}
	}

	private function runMigrator(): void
	{
		try {
			$migrationsPath = PluginClassName_DIR . 'database/Migrations';

			$migrator = new Migrator(
				$migrationsPath,
				'PluginClassName\\Database\\Migrations'
			);

			$migrator->runPending();
		} catch (Exception $e) {
			error_log('[RRT Activator] Migration error: ' . $e->getMessage());
		}
	}

    private function ensureInstanceUuid(): void
    {
        $uuid = get_option(self::OPTION_INSTANCE_UUID);
        if (is_string($uuid) && $uuid !== '') {
            return;
        }

        if (function_exists('wp_generate_uuid4')) {
			// WP ≥5.7
            $uuid = \wp_generate_uuid4();
        } else {
            $uuid = bin2hex(random_bytes(16));
        }

        update_option(self::OPTION_INSTANCE_UUID, $uuid, true); // autoload yes
    }

	private function scheduleEvent(): void
	{
		add_filter('cron_schedules', function ($s) {
			$s['every_15_minutes'] = [
				'interval' => 15 * MINUTE_IN_SECONDS,
				'display'  => __('Every 15 Minutes', PluginClassName_NAME_SPACE),
			];
			return $s;
		});

		if (!wp_next_scheduled('fson_rrt_cleanup_drafts')) {
			wp_schedule_event(time() + 120, 'every_15_minutes', 'fson_rrt_cleanup_drafts');
		}

		if (!wp_next_scheduled('fson_rrt_remind_bookings')) {
			wp_schedule_event(time() + 300, 'hourly', 'fson_rrt_remind_bookings');
		}
	}
}