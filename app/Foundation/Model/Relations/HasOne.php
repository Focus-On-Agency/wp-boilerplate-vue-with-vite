<?php
namespace PluginClassName\Foundation\Model\Relations;

if (!defined('ABSPATH')) {
	exit;
}

class HasOne extends Relation
{
    public function __construct(
        object $parent,
        string $relatedClass,
        protected string $foreignKey,
        protected string $localKey
    ) {
        parent::__construct($parent, $relatedClass);
    }

    protected function addConstraints(): void
    {
        $this->query->where($this->foreignKey, $this->parent->{$this->localKey});
    }

    public function getResults()
    {
        return $this->query
            ->first()
        ;
    }

    public function kind(): string
    {
        return 'hasOne';
    }

    public function foreignKeyName(): string
    {
        return $this->foreignKey;
    }

    public function localKeyName(): string
    {
        return $this->localKey;
    }
}