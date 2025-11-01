<?php

namespace PluginClassName\Foundation\Model\Concerns;

use PluginClassName\Support\Logger;

if (!defined('ABSPATH')) {
	exit;
}

trait HasAttributes
{
    public function setRawAttributes(array $attributes, bool $merge = true): void
    {
        if (!$merge) {
            $this->attributes = [];
        }
        foreach ($attributes as $k => $v) {
            $this->attributes[$k] = $v;
        }
    }

    public function takeAttributesByPrefix(string $prefix): array
	{
		$out = [];
		foreach (array_keys($this->attributes) as $key) {
			if (strpos($key, $prefix) === 0) {
				$out[substr($key, strlen($prefix))] = $this->attributes[$key];
				unset($this->attributes[$key]);
			}
		}
		return $out;
	}

    public function castArrayForStorage(array $attributes): array
	{
		$out = [];

		foreach ($attributes as $key => $value) {

			if (!in_array($key, $this->getFillable(), true)) {
				continue;
			}

			if (isset($this->casts[$key])) {
				$out[$key] = $this->castToStorage($value, $this->casts[$key]);
			} else {
				$out[$key] = $value;
			}
		}
		
		return $out;
	}

    /**
	 * Cast an attribute to its appropriate type for storage.
	 *
	 * @param string $key The attribute key
	 * @param mixed $value The value to cast
	 * @return mixed The casted value
	 */
    protected function castToStorage(mixed $value, string $type): mixed
	{
		if ($value === null) {
			return null;
		}

		if (($type === 'datetime' || $type === 'time') && ($value === '' || $value === '0000-00-00 00:00:00')) {
			return null;
		}

		return match ($type) {
			'int'      => (int) $value,
			'float'    => (float) $value,
			'string'   => (string) $value,
			'bool'     => $value ? 1 : 0,
			'array'    => json_encode($value),
			'datetime' => $value instanceof \DateTimeInterface ? $value->format('Y-m-d H:i:s') : $value,
			'time'     => $value instanceof \DateTimeInterface ? $value->format('H:i:s') : $value,
			default    => $value,
		};
	}

    /**
	 * Cast an attribute to its appropriate type.
	 *
	 * @param string $key The attribute key
	 * @param mixed $value The value to cast
	 * @return mixed The casted value
	 */
	protected function castAttribute(mixed $value, ?string $type): mixed
	{
		if ($value === null) return null;

		if (($type === 'datetime' || $type === 'time') && ($value === '' || $value === '0000-00-00 00:00:00')) {
			return null;
		}

		return match ($type) {
			'int'      => (int) $value,
			'float'    => (float) $value,
			'string'   => (string) $value,
			'bool'     => filter_var($value, FILTER_VALIDATE_BOOLEAN),
			'array'    => is_string($value) ? json_decode($value, true) : (array) $value,
			'datetime' => $value ? new \DateTimeImmutable($value) : null,
			'time'     => $value ? \DateTimeImmutable::createFromFormat('H:i:s', $value) ?: \DateTimeImmutable::createFromFormat('H:i', $value) : null,
			default    => $value,
		};
	}

    protected function attributesForStorage(): array
	{
		$data = [];
		$attributes = $this->getAttributes();
		$casts = $this->getCasts();

		foreach ($this->getFillable() as $col) {
			if (array_key_exists($col, $attributes) && array_key_exists($col, $casts)) {
				$value = $attributes[$col];
				$type = $casts[$col] ?? null;

				$data[$col] = $this->castToStorage($value, $type);
			}

			$data[$col] = $attributes[$col] ?? null;
		}

		return $data;
	}
}