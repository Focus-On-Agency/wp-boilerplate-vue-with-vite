<?php

namespace PluginClassName\Foundation\Model\Concerns;

use PluginClassName\Foundation\Model\Relations\BelongsTo;
use PluginClassName\Foundation\Model\Relations\BelongsToMany;
use PluginClassName\Foundation\Model\Relations\HasMany;
use PluginClassName\Foundation\Model\Relations\HasOne;

if (!defined('ABSPATH')) {
	exit;
}

trait HasRelationships
{

	protected $relations = [];

    /**
	 * Define a one-to-one relationship.
	 *
	 * @param string $relatedModel The related model class name
	 * @param string $foreignKey The foreign key column name
	 * @param string|null $localKey The local key column name (optional)
	 * 
	 * @return static|null The related record
	 */
    public function hasOne(string $related, string $foreignKey, ?string $localKey = null): HasOne
    {
        $localKey = $localKey ?: $this->getPrimaryKey();
        return new HasOne($this, $related, $foreignKey, $localKey);
    }

    /**
	 * Define a one-to-many relationship.
	 *
	 * @param string $relatedModel The related model class name
	 * @param string $foreignKey The foreign key column name
	 * @param string|null $localKey The local key column name (optional)
	 * 
	 * @return array The related records
	 */
    public function hasMany(string $related, string $foreignKey, ?string $localKey = null): HasMany
    {
        $localKey = $localKey ?: $this->getPrimaryKey();
        return new HasMany($this, $related, $foreignKey, $localKey);
    }

    /**
	 * Define a belongs-to relationship.
	 *
	 * @param string $relatedModel The related model class name
	 * @param string $foreignKey The foreign key column name
	 * @param string|null $ownerKey The owner key column name of the related model (optional)
	 * @return static|null The related record
	 */
    public function belongsTo(string $related, string $foreignKey, ?string $ownerKey = null): BelongsTo
    {
        return new BelongsTo($this, $related, $foreignKey, $ownerKey);
    }

    /**
	 * Infer the pivot table name based on the related model.
	 *
	 * @param string $related The related model class name
	 * @param string $pivotTable The pivot table name
	 * @param string|null $foreignKey The foreign key column name
	 * @param string|null $relatedKey The related key column name
	 * 
	 * @return string The inferred pivot table name
	 */
    public function belongsToMany(string $related, string $pivotClass, ?string $foreignKey = null, ?string $relatedKey = null): BelongsToMany
    {
        return new BelongsToMany($this, $related, $pivotClass, $foreignKey, $relatedKey);
    }

	public function getRelation(string $name): mixed
	{
		return $this->relations[$name] ?? null;
	}
	
	public function setRelation(string $name, mixed $value): void
	{
		$this->relations[$name] = $value;
	}

	public function relationLoaded(string $name): bool
	{
		return array_key_exists($name, $this->relations);
	}

	/**
	 * Load the specified relations for the given items.
	 *
	 * @param array $items The items to load relations for
	 * 
	 * @return array The items with loaded relations
	 * 
	 * @throws \InvalidArgumentException If the relation name is not defined
	 * @throws \RuntimeException If the relation configuration is invalid
	 */
    protected function loadTree(self $model, array $tree): void
	{
		foreach ($tree as $relation => $children) {
			if (!method_exists($model, $relation)) {
				continue;
			}

			$value = $this->getRelation($relation);
			if ($value === null) {
				$value = $model->{$relation}();
				$this->setRelation($relation, $value);
			}

			if (!empty($children)) {
				if ($value instanceof self) {
					$this->loadTree($value, $children);
				} elseif (is_array($value)) {
					foreach ($value as $child) {
						if ($child instanceof self) {
							$this->loadTree($child, $children);
						}
					}
				}
			}
		}
	}

	public function pivot(string $relationName)
	{
		if (!method_exists($this, $relationName)) {
			throw new \InvalidArgumentException("Relation '$relationName' not defined on ".static::class);
		}

		$rel = $this->{$relationName}();
		if (!is_object($rel) || !method_exists($rel, 'attach')) {
			throw new \RuntimeException("Relation '$relationName' is not a BelongsToMany.");
		}
		
		return $rel;
	}
}