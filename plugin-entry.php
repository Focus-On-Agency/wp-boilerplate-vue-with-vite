<?php

/**
 * Plugin Name: PluginName
 * Plugin URI: http://wpminers.com/
 * Description: A sample WordPress plugin to implement Vue with tailwind.
 * Author: Hasanuzzaman Shamim
 * Author URI: http://hasanuzzaman.com/
 * Version: 1.0.6
 * Text Domain: pluginslug
 */
define('PLUGIN_CONST_URL', plugin_dir_url(__FILE__));
define('PLUGIN_CONST_DIR', plugin_dir_path(__FILE__));

define('PLUGIN_CONST_VERSION', '1.0.5');

// This will automatically update, when you run dev or production
define('PLUGIN_CONST_PRODUCTION', 'yes');

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
	ob_start();
	require_once __DIR__ . '/vendor/autoload.php';
	ob_end_clean();
}

use Respect\Validation\Factory;
use PluginClassName\Console\Schedules;

class PluginClassName {
	public function boot()
	{
		$this->loadClasses();
		//$this->disableUpdateNag();
		$this->registerShortCodes();
		$this->registerEntities();
		$this->registerJobs();
		$this->registerCron();
		$this->renderMenu();
		$this->loadTextDomain();

		if (defined('WP_CLI') && WP_CLI && defined('PluginClassName_DEVELOPMENT') && PluginClassName_DEVELOPMENT === 'yes') {
			\WP_CLI::add_command('fson make:migration', \PluginClassName\Foundation\Console\MakeMigration::class);
			\WP_CLI::add_command('fson make:job', \PluginClassName\Foundation\Console\MakeJob::class);
		}
	}

	public function loadClasses()
	{
		require_once PLUGIN_CONST_DIR . 'routes/web.php';

		Factory::setDefaultInstance(
			(new Factory())
				->withRuleNamespace('PluginClassName\Support\Validation\Rules')
				->withExceptionNamespace('PluginClassName\Support\Validation\Exceptions')
		);
	}

	public function renderMenu()
	{
		add_action('admin_menu', function () {
			if (!current_user_can('manage_options')) {
				return;
			}
			global $submenu;
			add_menu_page(
				'PluginClassName',
				'PluginName',
				'manage_options',
				'pluginslug.php',
				array($this, 'renderAdminPage'),
				'dashicons-editor-code',
				25
			);
			$submenu['pluginslug.php']['dashboard'] = array(
				'Dashboard',
				'manage_options',
				'admin.php?page=pluginslug.php#/',
			);
			$submenu['pluginslug.php']['contact'] = array(
				'Contact',
				'manage_options',
				'admin.php?page=pluginslug.php#/contact',
			);
		});
	}

	/**
	 * Main admin Page where the Vue app will be rendered
	 * For translatable string localization you may use like this
	 * 
	 *      add_filter('pluginlowercase/frontend_translatable_strings', function($translatable){
	 *          $translatable['world'] = __('World', 'pluginslug');
	 *          return $translatable;
	 *      }, 10, 1);
	 */
	public function renderAdminPage()
	{
		$loadAssets = new \PluginClassName\Foundation\LoadAssets();
		$loadAssets->admin();

		$translatable = apply_filters('pluginlowercase/frontend_translatable_strings', array(
			'hello' => __('Hello', 'pluginslug'),
		));

		$pluginlowercase = apply_filters('pluginlowercase/admin_app_vars', array(
			'assets_url' => PLUGIN_CONST_URL . 'assets/',
			'ajaxurl' => admin_url('admin-ajax.php'),
			'i18n' => $translatable,
			'rest' => [
				'url' => rest_url('pluginlowercase/v1/'),
				'root' => rest_url(),
				'namespace' => 'pluginlowercase/v1',
				'nonce' => wp_create_nonce('wp_rest')
			],
		));

		wp_localize_script('pluginlowercase-script-boot', 'pluginlowercaseAdmin', $pluginlowercase);

		echo '<div class="pluginlowercase-admin-page" id="pluginlowercase_app"></div>';
	}

	/*
	* NB: text-domain should match exact same as plugin directory name (Plugin Name)
	* WordPress plugin convention: if plugin name is "My Plugin", then text-domain should be "my-plugin"
	* 
	* For PHP you can use __() or _e() function to translate text like this __('My Text', 'pluginslug')
	* For Vue you can use $t('My Text') to translate text, You must have to localize "My Text" in PHP first
	* Check example in "renderAdminPage" function, how to localize text for Vue in i18n array
	*/
	public function loadTextDomain()
	{
		load_plugin_textdomain('pluginslug', false, basename(dirname(__FILE__)) . '/languages');
	}


	/**
	 * Disable update nag for the dashboard area
	 */
	public function disableUpdateNag()
	{
		add_action('admin_init', function () {
			$disablePages = [
				'pluginslug.php',
			];

			if (isset($_GET['page']) && in_array($_GET['page'], $disablePages)) {
				remove_all_actions('admin_notices');
			}
		}, 20);
	}


	/**
	 * Activate plugin
	 * Migrate DB tables if needed
	 */
	public static function activate($newWorkWide)
	{
		require_once PLUGIN_CONST_DIR . 'app/Foundation/Activator.php';
		$activator = new \PluginClassName\Foundation\Activator($newWorkWide);
		$activator->boot();
	}

	/**
	 * Register ShortCodes here
	 */
	public function registerShortCodes()
	{
		if (defined('LSCWP_V')) {
			do_action('litespeed_control_set_nocache');
		}
		// Use add_shortcode('shortcode_name', 'function_name') to register shortcode
	}

	/**
	 * Register all post types and taxonomies during activation.
	 */
	public function registerEntities(): void
	{
		$directory = PLUGIN_CONST_DIR . 'app/RegistrableEntity';
		$namespace = 'PluginClassName\\RegistrableEntity\\';

		$classes = [];

		foreach (glob($directory . '/*.php') as $file) {
			$className = $namespace . basename($file, '.php');

			if (!class_exists($className)) {
				continue;
			}

			$reflection = new \ReflectionClass($className);

			if (
				$reflection->isSubclassOf(\PluginClassName\Foundation\RegistrableEntity::class)
				&& !$reflection->isAbstract()
			) {
				$classes[] = $className;
			}
		}

		usort($classes, function ($a, $b) {
			return is_subclass_of($a, 'PluginClassName\Foundation\RegistrableEntity\Taxonomy') ? -1 : 1;
		});

		foreach ($classes as $class) {
			(new $class)->boot();
		}
	}

	/*
	 * Register all jobs here
	 */
	public function registerJobs(): void
	{
		// Use ScheduledJobManager to register jobs
	}

	/*
	 * Register all cron jobs here
	 */
	public function registerCron(): void
	{
		// Add new interval
		$schedules = new Schedules();

		$schedules->register();
	}

}

(new PluginClassName())->boot();

register_activation_hook(__FILE__, ['PluginClassName', 'activate']);

add_action('upgrader_process_complete', function ($upgrader, $options) {
    if ($options['action'] === 'update' && $options['type'] === 'plugin') {
        if (!empty($options['plugins']) && in_array(plugin_basename(__FILE__), $options['plugins'], true)) {
            $migrator = new \PluginClassName\Foundation\Migrator(
                PLUGIN_CONST_DIR . 'database/Migrations',
                'PluginClassName\\Database\\Migrations'
            );
            $migrator->runPending();
        }
    }
}, 10, 2);

register_deactivation_hook(__FILE__, function () {
	
	// Clear scheduled events
    //if ($ts = wp_next_scheduled('event_hook_name')) {
    //    wp_unschedule_event($ts, 'event_hook_name');
    //}
});