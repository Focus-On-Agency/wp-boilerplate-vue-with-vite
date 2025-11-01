<?php

namespace PluginClassName\Foundation\Model\Relations;

use PluginClassName\Foundation\Model\Builder;
use PluginClassName\Foundation\Model\Concerns\Conditionable;
use PluginClassName\Models\Model;

if (!defined('ABSPATH')) {
	exit;
}

abstract class Relation
{
    use Conditionable;
    
    protected object $parent;
    protected string $relatedClass;

    protected static bool $constraints = true;

    protected ?Builder $query = null;

    abstract public function getResults();
    abstract protected function addConstraints(): void;

    public function __construct(object $parent, string $relatedClass)
    {
        $this->parent = $parent;
        $this->relatedClass = $relatedClass;

        /** @var Model $related */
        $related = $this->newRelated();
        $this->query = $related->query();

        if (static::constraintsEnabled()) {
            $this->addConstraints();
        }
    }

    public function __call($method, $parameters)
    {
        $builder = $this->getQuery();
        if (!$builder || !method_exists($builder, $method)) {
            throw new \BadMethodCallException(static::class . " missing method {$method}");
        }

        $result = $builder->{$method}(...$parameters);

        // Se il metodo restituisce il Builder, ritorna $this per chaining sulla Relation
        if ($result instanceof Builder) {
            $this->query = $result;
            return $this;
        }

        if ($method === 'get' && is_array($result)) {
            return $this->afterGet($result);
        }
        if ($method === 'first') {
            return $this->afterFirst($result);
        }
        if ($method === 'paginate' && is_array($result) && isset($result['data'])) {
            return $this->afterPaginate($result);
        }

        return $result;
    }

    public static function noConstraints(callable $callback)
    {
        $previous = static::$constraints;
        static::$constraints = false;

        try { return $callback(); }
        finally { static::$constraints = $previous; }
    }

    protected static function constraintsEnabled(): bool
    {
        return static::$constraints;
    }

    protected function afterGet(array $models): array 
    {
        return $models;
    }

    protected function afterFirst(?Model $model): ?Model 
    {
        return $model;
    }

    protected function afterPaginate(array $page): array 
    {
        return $page;
    }

    public function getQuery(): Builder
    {
        return $this->query;
    }

    protected function newRelated(): object
    {
        $cls = $this->relatedClass;
        return new $cls();
    }
    
    protected function relatedPk(): string
    {
        return $this->newRelated()->getPrimaryKey();
    }

    public function kind(): string
    {
        return 'relation';
    }

    public function relatedClass(): string
    {
        return $this->relatedClass;
    }

    public function relatedTable(): string
    {
        return $this->newRelated()->getTable();
    }

    public function relatedPrimaryKey(): string
    {
        return $this->relatedPk();
    }
}
