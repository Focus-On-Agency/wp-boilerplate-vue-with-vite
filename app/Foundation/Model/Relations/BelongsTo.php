<?php
namespace PluginClassName\Foundation\Model\Relations;

use PluginClassName\Support\Logger;

if (!defined('ABSPATH')) {
	exit;
}

class BelongsTo extends Relation
{
    public function __construct(
        object $parent,
        string $relatedClass,
        protected string $foreignKey,
        protected ?string $ownerKey = null
    ) {
        $this->ownerKey = $ownerKey ?? (new $relatedClass())->getPrimaryKey();

        parent::__construct($parent, $relatedClass);
    }

    protected function addConstraints(): void
    {
        $fkValue = $this->parent->{$this->foreignKey} ?? null;
        if ($fkValue === null) {
            return;
        }

        $this->query->where($this->ownerKey, $this->parent->{$this->foreignKey});
    }

    public function getResults()
    {
        $fk = $this->foreignKeyValue();
        if ($fk === null) {
            return null;
        }

        return $this->query
            ->first()
        ;
    }

    public function kind(): string
    {
        return 'belongsTo';
    }

    public function foreignKeyName(): string
    {
        return $this->foreignKey;
    }

    protected function foreignKeyValue(): mixed
    {
        return $this->parent->{$this->foreignKey} ?? null;
    }

    public function ownerKeyName(): ?string
    {
        return $this->ownerKey ?? (new $this->relatedClass())->getPrimaryKey();
    }
}
