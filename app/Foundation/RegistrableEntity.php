<?php

namespace PluginClassName\Foundation;

use function add_action;

if (!defined('ABSPATH')) {
	exit;
}

abstract class RegistrableEntity
{
	/**
	 * Hook alla registrazione (init di default)
	 */
	public function boot(string $hook = 'init', int $priority = 10): void
	{
		if (empty($hook)) {
			throw new \InvalidArgumentException('Hook name cannot be empty');
		}
		
		add_action($hook, function () {
			if ($this->shouldRegister()) {
				$this->register();
			}
		}, $priority);
	}

	/**
	 * Verifica se deve essere registrato (override se necessario)
	 * 
	 * @return bool True if the entity should be registered, false otherwise
	 */
	public function shouldRegister(): bool
	{
		// Validate slug before registration
		$slug = $this->getSlug();
		if (empty(trim($slug))) {
			return false;
		}
		
		return true;
	}

	/**
	 * Slug dell'entità
	 */
	abstract public function getSlug(): string;

	/**
	 * Argomenti di registrazione
	 */
	abstract public function toArray(): array;

	/**
	 * Metodo effettivo di registrazione (da implementare)
	 */
	abstract public function register(): void;
}