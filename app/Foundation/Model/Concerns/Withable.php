<?php

namespace PluginClassName\Foundation\Model\Concerns;

use PluginClassName\Foundation\Model\Relations\Relation;

if (!defined('ABSPATH')) {
	exit;
}

trait Withable
{
	/** @var array<string, mixed> */
	protected array $with = [];

    /**
	 * Imposta le relazioni da caricare eager.
	 *
	 * @param array|string $relations Elenco delle relazioni, es. ['items', 'items.groups']
	 * @return static
	 */
    public function with(array|string $relations): static
    {
        $tree = $this->normalizeWith($relations);
        $this->with = $this->mergeWithTree($this->with, $tree);

        return $this;
    }

    public function setWithTree(array $tree): void
    {
        $this->with = $tree;
    }

    public function getWithTree(): array
    {
        return $this->with;
    }

    protected function normalizeWith(array|string $relations): array
    {
        $list = is_array($relations) ? $relations : [$relations];
        $tree = [];
        foreach ($list as $r) {
            if (is_string($r)) {
                $segments = array_values(array_filter(explode('.', $r)));
                if (!$segments) continue;

                $ref =& $tree;
                foreach ($segments as $seg) {
                    $ref[$seg] = $ref[$seg] ?? [];
                    $ref =& $ref[$seg];
                }
            } elseif (is_array($r)) {
                $tree = $this->mergeWithTree($tree, $r);
            }
        }
        return $tree;
    }

    protected function mergeWithTree(array $target, array $source): array
	{
		foreach ($source as $key => $children) {
			$target[$key] = isset($target[$key])
				? $this->mergeWithTree($target[$key], $children)
				: $children;
		}
		return $target;
	}

    private function appendTreeToArray(array &$out, array $tree, self $model, array &$__seen = []): void
	{
		if (empty($tree)) return;

		foreach ($tree as $relationName => $subTree) {
			if (!method_exists($model, $relationName)) {
				continue;
			}

			$relObj = $model->{$relationName}();

			$value = null;

			if ($relObj instanceof Relation) {
				$value = $relObj->getResults();
                $this->setRelation($relationName, $value);
			} else {
				// fallback legacy: il metodo potrebbe tornare Model|array|mixed
				$value = $relObj;
			}

			if (empty($subTree)) {
				$out[$relationName] = $this->serializeRelationValue($value, $__seen);
				continue;
			}

			if (is_array($value))
			{
				$out[$relationName] = array_map(function ($child) use ($subTree, & $__seen)
                {
					if ($child instanceof self) {

						$prev = $child->getWithTree ? $child->getWithTree() : [];
						if (method_exists($child, 'setWithTree')) {
							$child->setWithTree($subTree);
						}

						$arr  = $child->toArray($__seen);
						if (method_exists($child, 'setWithTree')) {
							$child->setWithTree($prev);
						}

						return $arr;
					}

					return $child;
				}, $value);
			} elseif ($value instanceof self)
			{
				$prev = method_exists($value, 'getWithTree') ? $value->getWithTree() : [];
				if (method_exists($value, 'setWithTree')) {
					$value->setWithTree($subTree);
				}
                
				$out[$relationName] = $value->toArray($__seen);
				if (method_exists($value, 'setWithTree')) {
					$value->setWithTree($prev);
				}
			} else {
				$out[$relationName] = $value;
			}
		}
	}

    protected function serializeRelationValue(mixed $value, array &$__seen): mixed
	{
		if (is_array($value)) {
			return array_map(fn($v) => $this->serializeRelationValue($v, $__seen), $value);
		}

		if ($value instanceof self) {
			
			$oid = spl_object_id($value);
			
			if (isset($__seen[$oid])) {
				return [$value->getPrimaryKey() => $value->{$value->getPrimaryKey()}];
			}
			$__seen[$oid] = true;

			return $value->toArray($__seen);
		}

		if ($value instanceof \DateTimeInterface) {
			return $value->format('Y-m-d H:i:s');
		}

		return $value;
	}
}
