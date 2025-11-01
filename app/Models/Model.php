<?php

namespace PluginClassName\Models;

/**
 * @method
 */

use DateTimeInterface;

use JsonSerializable;
use PluginClassName\Foundation\Model\Helper;
use PluginClassName\Foundation\Model\Builder;
use PluginClassName\Foundation\Model\Concerns\HasAttributes;
use PluginClassName\Foundation\Model\Concerns\HasRelationships;
use PluginClassName\Foundation\Model\Concerns\Withable;
use PluginClassName\Support\Logger;

if (!defined('ABSPATH')) {
	exit;
}

abstract class Model implements JsonSerializable
{
	use Helper, Withable, HasRelationships, HasAttributes;

	/** @var string The database table associated with the model */
	protected $table = '';

	/** @var bool Whether to include the table custom prefix when querying */
	protected $withOutPrefix = false;

	/** @var string The primary key column name */
	protected $primaryKey = 'id';

	/** @var string The data type of the primary key */
	protected $keyType = 'int';

	/** @var bool Whether to automatically manage created_at and updated_at timestamps */
	protected $timestamps = true;

	/** @var bool Whether the model supports soft deletes via deleted_at */
	protected $trashable = true;
	
	/** @var array Fillable attributes for mass assignment */
	protected $fillable = [];

	/** @var array Casts for attribute types */
	protected $casts = [];

	/** @var array Associative array of model attributes */
	protected $attributes = [];

	protected $appends = [];

	protected array $with = [];

	/**
	 * Initialize a new model instance.
	 *
	 * @throws \InvalidArgumentException If table name is not set
	 */
	public function __construct(array $attributes = [])
	{
		if (empty($this->table)) {
			throw new \InvalidArgumentException('Table name is required');
		}

		if (!empty($attributes)) {
			$this->hydrate($attributes);
		}

		$this->casts = array_merge($this->casts, $this->getDefaultCasts());
	}

	/**
	 * Magic method for getting a property.
	 *
	 * @param string $name The property name (camelCase or snake_case)
	 * @return mixed|null The property value or null if not found
	 */
	public function __get(string $name)
	{
		$snakeName = $this->camelToSnake($name);
		$camelName = $this->snakeToCamel($name);
		$accessor = 'get' . ucfirst($camelName) . 'Attribute';

		$rawExists = array_key_exists($snakeName, $this->attributes);
		$raw = $rawExists ? $this->attributes[$snakeName] : null;

		if (method_exists($this, $accessor)) {
			return $this->{$accessor}($raw);
		}

		if ($rawExists) {
			if (isset($this->casts[$snakeName])) {
				$raw = $this->castAttribute($raw, $this->casts[$snakeName]);
			}

			return $raw;
		}

		if (method_exists($this, $snakeName)) {
			$loaded = $this->getRelation($this, $snakeName);
			if ($loaded !== null) return $loaded;

			$rel = $this->{$snakeName}();

			if (is_object($rel) && method_exists($rel, 'getResults')) {
				$rel = $rel->getResults();
			}

			$this->setRelation($snakeName, $rel);

			return $rel;
		}

		return null;
	}

	/**
	 * Magic method for setting a property.
	 *
	 * @param string $name The property name (camelCase or snake_case)
	 * @param mixed $value The value to set
	 */
	public function __set(string $name, $value)
	{
		$snakeName = $this->camelToSnake($name);
		$camelName = $this->snakeToCamel($name);
		$method = 'set' . ucfirst($camelName) . 'Attribute';

		if (method_exists($this, $method)) {
			$this->{$method}($value);
			return;
		}

		if (isset($this->casts[$snakeName])) {
			$value = $this->castToStorage($value, $this->casts[$snakeName]);
		}

		$this->attributes[$snakeName] = $value;
	}

	/**
	 * Convert the model to its string representation.
	 *
	 * @return string
	 */
	public function __toString(): string
	{
		return json_encode($this->toArray());
	}

	static public function __callStatic($method, $parameters)
	{
		$builder = static::query();

        if (method_exists($builder, $method)) {
            return $builder->$method(...$parameters);
        }

        throw new \BadMethodCallException("Method {$method} does not exist on ".static::class." or its Builder.");
	}

	public function __isset($name): bool
	{
		$snake = $this->camelToSnake($name);

		if (array_key_exists($snake, $this->attributes)) return true;

		if ($this->relationLoaded($snake)) return true;

		if (method_exists($this, $snake)) {
			$rel = $this->{$snake}();

			if (is_object($rel) && method_exists($rel, 'getResults')) {

				$rel = $rel->getResults();
			}

			$this->setRelation($snake, $rel);

			return $rel !== null;
		}

		return false;
	}

	public static function table(): string
    {
        $proto = new static();
        return $proto->getTable();
    }

    public static function primaryKey(): string
    {
        $proto = new static();
        return $proto->primaryKey;
    }

	public function getPrimaryKey(): string
	{
		return $this->primaryKey;
	}

    public static function usesTimestamps(): bool
    {
        $proto = new static();
        return $proto->timestamps;
    }

    public static function isTrashable(): bool
    {
        $proto = new static();
        return $proto->trashable;
    }

	/**
	 * Get the table name with the WordPress prefix.
	 *
	 * @return string The full table name with prefix
	 */
	public function getTable(): string
	{
		global $wpdb;
		$customPrefix = defined('PluginClassName_DB_PREFIX') ? PluginClassName_DB_PREFIX : 'fson_';

		if ($this->withOutPrefix) {
			return $wpdb->prefix . $this->table;
		}

		return $wpdb->prefix . $customPrefix . $this->table;
	}

	/**
	 * Get the fillable attributes for the model.
	 *
	 * @return array The fillable attributes
	 */
	public function getFillable(): array
	{
		return $this->fillable;
	}

	public function getAttributes(): array
	{
		return $this->attributes;
	}

	public function getCasts(): array
	{
		return $this->casts;
	}

	public function getDefaultCasts(): array
	{
		$default = [];

		$default[$this->primaryKey] = $this->keyType;

		if ($this->timestamps) {
			$default['created_at'] = 'datetime';
			$default['updated_at'] = 'datetime';
		}

		if ($this->trashable) {
			$default['deleted_at'] = 'datetime';
		}

		return $default;
	}

	/**
	 * Create a new query builder instance for the model.
	 *
	 * @return Builder The query builder instance
	 */
	public static function query(): Builder
    {
		$builder = new Builder(static::class);
		static::applyGlobalScopes($builder);

		return $builder;
    }

	public static function applyGlobalScopes(Builder $q): void
	{
		// Soft delete di default
		if (static::isTrashable()) {
			$q->where('deleted_at', null, 'IS');
		}

		// Hook overridabile dal modello concreto (es. Room::booted($q))
		static::booted($q);
	}

	protected static function booted(Builder $q): void {}

	/**
	 * Create a new model instance.
	 *
	 * @param array $attributes The attributes to set on the model
	 * @return static The newly created model instance
	 */
	static public function create(array $attributes = []): static
	{
		$model = new static();
		$model->fill($attributes);
		$model->insertOrUpdate();

		return $model;
	}

	/**
	 * Save a new model instance (insert or update).
	 *
	 * @param array $attributes The attributes to set on the model
	 * @return static The newly created model instance
	 */
	public function save(): static
	{
		$this->insertOrUpdate();

		return $this;
	}
	
	/**
	 * Update the model instance with new attributes.
	 *
	 * @param array $attributes The attributes to update
	 * 
	 * @return bool|int True if the update was successful
	 */
	public function update(array $attributes = []): bool|int
	{
		if (!empty($attributes)) {
			$this->fill($attributes);
		}

		return $this->insertOrUpdate();
	}

	/**
	 * Save the current model state (insert or update).
	 *
	 * @return bool|int ID inserito o true in caso di update
	 */
	public function insertOrUpdate(): bool|int
	{
		$pk = $this->primaryKey ?? 'id';
		$builder = static::query();
		
		if (!empty($this->attributes[$pk]) && $builder->where($pk, $this->attributes[$pk])->exists()) {
			return static::query()->where($pk, $this->attributes[$pk])->update($this->attributes);
		}

		$id = $builder->create($this->attributes);
		if ($id) {
			$this->attributes[$pk] = $id;
		}

		return $id;
	}

	/**
	 * Delete records matching the query.
	 *
	 * @return bool True if the deletion was successful
	 */
	public function delete(): bool
	{
		$pk = $this->primaryKey ?? 'id';
		$id = $this->attributes[$pk] ?? null;

		if ($id === null) {
			throw new \RuntimeException("Cannot delete model without primary key value.");
		}

		$q = static::query()->where($pk, $id);

		if ($this->trashable) {
			$data = ['deleted_at' => current_time('mysql')];
			if (!empty($this->timestamps)) {
				$data['updated_at'] = current_time('mysql');
			}
			return $q->softDelete($data);
		}

		return $q->delete();
	}

	/**
	 * Fill the model with an array of attributes (mass assignment).
	 *
	 * @param array $data
	 * @return static $this
	 */
	public function fill(array $data): static
	{
		foreach ($data as $key => $value) {
			if (in_array($key, $this->fillable, true)) {
				$this->__set($key, $value);
			}
		}

		return $this;
	}

	/**
	 * Convert the model instance to an array.
	 *
	 * @return array The model attributes as an array
	 */
	public function toArray(array $__seen = []): array
	{
		$out = [];
		foreach (array_keys($this->attributes) as $key) {
			$val = $this->__get($key);
			$out[$key] = $val instanceof \DateTimeInterface ? $val->format('Y-m-d H:i:s') : $val;
		}

		if (!empty($this->with)) {
			$this->appendTreeToArray($out, $this->with, $this, $__seen);
		}

		foreach ($this->relations as $name => $value) {
			if (!array_key_exists($name, $out)) {
				$out[$name] = $this->serializeRelationValue($value, $__seen);
			}
		}

		if (!empty($this->appends)) {
			foreach ($this->appends as $attr) {
				if (!array_key_exists($attr, $out)) {
					$out[$attr] = $this->__get($attr);
				}
			}
		}

		return $out;
	}

	/**
	 * Convert the model instance to JSON.
	 *
	 * @return mixed The model attributes as JSON
	 */
	public function jsonSerialize(): mixed {
		return $this->toArray();
	}

	/**
	 * Load the specified relations for the model.
	 *
	 * @param array|string $relations The relations to load
	 * 
	 * @return static The model instance with loaded relations
	 * 
	 * @throws \InvalidArgumentException If the relation name is not defined
	 * @throws \RuntimeException If the relation configuration is invalid
	 */
	public function load(array|string $relations): static
	{
		$relations = is_array($relations) ? $relations : func_get_args();

		// Costruisci un albero a partire dai path passati
		$tree = [];
		foreach ($relations as $relation) {
			$segments = array_values(array_filter(explode('.', $relation)));
			if (!$segments) continue;

			$ref =& $tree;
			foreach ($segments as $seg) {
				$ref[$seg] = $ref[$seg] ?? [];
				$ref =& $ref[$seg];
			}
		}

		// Carica ricorsivamente seguendo l’albero
		$this->loadTree($this, $tree);

		return $this;
	}
}