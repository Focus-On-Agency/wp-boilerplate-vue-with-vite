<?php

namespace PluginClassName\Foundation;

use Exception;
use function add_filter;
use function wp_register_script;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function esc_html;
use function esc_url;
use const WP_DEBUG;

if (!defined('ABSPATH')) {
	exit;
}

class Vite
{
	private static $instance = null;
	private string $viteHostProtocol = 'https://';
	private string $viteHost = 'dev-wp.ddev.site';
	private string $vitePort = '5173';
	private string $resourceDirectory = 'resources/';
	private array $moduleScripts = [];
	private bool $isScriptFilterAdded = false;
	private array $manifestData = [];
	private bool $manifestLoaded = false;


	public static function getInstance(): self
	{
		if (static::$instance === null) {
			static::$instance = new static();
			if (!static::isDevMode()) {
				(static::$instance)->viteManifest();
			}
		}
		return self::$instance;
	}

	/***
	 * @param $handle
	 * @param $src string file path relative to resource/src directory before build
	 * @param array $dependency
	 * @param null $version
	 * @param bool $inFooter
	 * @return Vite
	 * 
	 * @throws Exception If dev mode is on and file not found in manifest
	 * 
	 */
	public static function enqueueScript(string $handle, string $src, array $dependency = [], ?string $version = null, bool $inFooter = false): void
	{
		$instance = self::getInstance();
		if (in_array($handle, $instance->moduleScripts, true)) {
			if (self::isDevMode()) {
				throw new Exception('This handle has already been used');
			}
			return;
		}
	
		$instance->moduleScripts[] = $handle;

		if (!$instance->isScriptFilterAdded) {
			add_filter('script_loader_tag', [$instance, 'addModuleToScript'], 10, 3);
			$instance->isScriptFilterAdded = true;
		}

		$srcPath = self::isDevMode() 
			? self::getDevPath() . $src 
			: self::getProductionFilePath($instance->getFileFromManifest($src))
		;

		wp_register_script($handle, $srcPath, $dependency, $version, $inFooter);
		wp_enqueue_script($handle);
	}

	public static function enqueueStyle(string $handle, string $src, array $dependency = [], ?string $version = null): void
	{
		$instance = self::getInstance();
		$srcPath = self::isDevMode() 
			? self::getDevPath() . $src 
			: self::getProductionFilePath($instance->getFileFromManifest($src))
		;

		if (!$version) {
			$version = file_exists($srcPath) ? filemtime($srcPath) : PluginClassName_VERSION;
		}

		wp_enqueue_style($handle, $srcPath, $dependency, $version);
	}

	private function viteManifest(): void
	{
		// Return if already loaded
		if ($this->manifestLoaded) {
			return;
		}

		$manifestPath = realpath(__DIR__ . '/../../assets/manifest.json');

		if (!file_exists($manifestPath)) {
			$this->manifestLoaded = true;
			throw new Exception('Vite Manifest Not Found. Run: npm run dev or npm run build');
		}

		$manifestContent = file_get_contents($manifestPath);
		if (!$manifestContent) {
			$this->manifestLoaded = true;
			throw new Exception("Failed to read manifest file.");
		}

		$this->manifestData = json_decode($manifestContent, true, 512, JSON_THROW_ON_ERROR);
		$this->manifestLoaded = true;
	}

	/**
	 * @throws Exception
	 */
	private function getFileFromManifest(string $src): array
	{
		$fullSrc = $this->resourceDirectory . $src;

		// Check if file exists in manifest
		if (!isset($this->manifestData[$fullSrc])) {
			if (self::isDevMode() && defined('WP_DEBUG') && WP_DEBUG) {
				error_log("Vite: File '{$src}' not found in manifest");
			}
			throw new Exception(esc_html("$src file not found in Vite manifest. Make sure it is included in rollupOptions input and rebuild."));
		}

		return $this->manifestData[$fullSrc];
	}

	public function addModuleToScript($tag, $handle, $src)
	{
		if (in_array($handle, $this->moduleScripts)) {
			$tag = '<script type="module" src="' . esc_url($src) . '"></script>';
		}
		return $tag;
	}

	public static function isDevMode(): bool
	{
		return defined('PluginClassName_DEVELOPMENT') && PluginClassName_DEVELOPMENT === 'yes';
	}

	private static function getDevPath(): string
	{
		return self::getInstance()->viteHostProtocol . self::getInstance()->viteHost . ':' . self::getInstance()->vitePort . '/' . self::getInstance()->resourceDirectory;
	}

	private static function getAssetPath(): string
	{
		return PluginClassName_URL . 'assets/';
	}

	private static function getProductionFilePath($file): string
	{
		$assetPath = self::getAssetPath();
		if (isset($file['css']) && is_array($file['css'])) {
			foreach ($file['css'] as $key => $path) {
				wp_enqueue_style(
					$file['file'] . '_' . $key . '_css',
					$assetPath . $path
				);
			}
		}

		if (isset($file['imports']) && is_array($file['imports'])) {
			foreach ($file['imports'] as $import) {
				if (isset(self::getInstance()->manifestData[$import])) {
					$imported = self::getInstance()->manifestData[$import];
					if (isset($imported['css']) && is_array($imported['css'])) {
						foreach ($imported['css'] as $key => $css) {
							wp_enqueue_style(
								$import . '_' . $key . '_css',
								$assetPath . $css
							);
						}
					}
				}
			}
		}
		
		return ($assetPath . $file['file']);
	}
}
