<?php

namespace PluginClassName\Foundation\Model;

use DateTimeImmutable;
use PluginClassName\Support\Logger;

if (!defined('ABSPATH')) {
	exit;
}

trait Helper
{
    /** @var array Global scope conditions applied to all queries */
	protected array $globalScope = [];

    /**
	 * Hydrate the model with an array of attributes.
	 *
	 * @param array $data The data to hydrate the model with
	 * @return static The current model instance
	 */
	private function hydrate(array $data): static
	{
		foreach ($data as $key => $value) {
			$this->attributes[$this->camelToSnake($key)] = $value;
		}
		return $this;
	}

    /**
	 * Convert a string from camelCase to snake_case.
	 *
	 * @param string $input The input string in camelCase
	 * @return string The converted string in snake_case
	 */
	private function camelToSnake(string $input): string
	{
		return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $input));
	}

    /**
	 * Convert a string from snake_case to camelCase.
	 *
	 * @param string $input The input string in snake_case
	 * @return string The converted string in camelCase
	 */
	private function snakeToCamel(string $input): string
	{
		return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $input))));
	}

    /**
	 * Apply global scope conditions to the query.
	 *
	 * @return void
	 */
	private function applyGlobalScope(): void
	{
		if (empty(static::$globalScope)) {
			return;
		}
		foreach (static::$globalScope as $column => $value) {
			$this->where($column, $value);
		}
	}
}