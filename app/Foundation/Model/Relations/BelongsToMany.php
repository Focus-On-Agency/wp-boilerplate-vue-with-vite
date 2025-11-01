<?php

namespace PluginClassName\Foundation\Model\Relations;

use PluginClassName\Foundation\Model\Builder;
use PluginClassName\Foundation\Model\Relations\Concerns\InteractsWithPivotTable;
use PluginClassName\Models\Model;
use PluginClassName\Support\Logger;

if (!defined('ABSPATH')) {
	exit;
}

class BelongsToMany extends Relation
{
    use InteractsWithPivotTable;

    /** @var class-string<Model> */
    protected string $pivotClass;

    public function __construct(
        object $parent,
        string $relatedClass,
        string $pivotClass,
        protected ?string $foreignKey = null,
        protected ?string $relatedKey = null
    ) {
        $this->pivotClass = $pivotClass;
        
        parent::__construct($parent, $relatedClass);
    }

    protected function addConstraints(): void
    {
        $this->prepareKeys();

        /** @var Model $related */
        $related      = $this->newRelated();
        $relatedTable = $related->getTable();
        $relatedPk    = $this->relatedPk();

        /** @var Model $parent */
        $parent       = $this->parent;
        $parentPkVal  = $parent->{$this->parentPk};

        // Se il parent non ha PK valorizzata, evitiamo risultati non filtrati.
        if ($parentPkVal === null) {
            $this->query->where('1', 0, '=');
            return;
        }

        // Nome tabella pivot (dal trait)
        [, $pvTable] = $this->tables();

        // JOIN pivot → related
        $this->query->join(
            $pvTable,
            "{$pvTable}.`{$this->rk}`",
            '=',
            "{$relatedTable}.`{$this->relatedPk}`"
        );

        // WHERE pivot.fk = parent.pk
        $this->query->where("{$pvTable}.`{$this->fk}`", $parentPkVal);

        // SELECT related.* (manteniamo eventuali select pre-esistenti; addSelect gestisce "*")
        $this->query->addSelect("{$relatedTable}.*");

        // Alias colonne pivot come pivot_<col>
        $this->addPivotSelects();

        if (!empty($this->pivotOrder)) {
            $this->query->orderBy("{$pvTable}.`{$this->pivotOrder['column']}`", $this->pivotOrder['direction']);
        }
    }

    public function getResults(): array
    {
        $this->prepareKeys();

        /** @var Model $parent */
        $parent = $this->parent;
        $pid    = $parent->{$this->parentPk};

        if ($pid === null) return [];

        $this->eagerParentIds = [$pid];
        $results = $this->getEager();

        $pivotRowsByParent = $results['pivotRowsByParent'];
        $relatedById       = $results['relatedById'];

        $rows = $pivotRowsByParent[$pid] ?? [];
        $relatedForParent = [];

        foreach ($rows as $row) {
            $rid = $row->{$this->rk};

            if (!isset($relatedById[$rid])) continue;

            $relModel = $relatedById[$rid];
            $relModel->setRelation('pivot', $this->filteredPivotModel($row));
            $relatedForParent[] = $relModel;
        }

        return $relatedForParent;
    }

    protected function afterGet(array $models): array
    {
        return $this->attachPivotAndClean($models);
    }

    protected function afterFirst(?Model $model): ?Model
    {
        if (!$model) return null;

        $this->attachPivotAndClean([$model]);

        return $model;
    }

    protected function afterPaginate(array $page): array
    {
        if (!empty($page['data']) && is_array($page['data'])) {
            $page['data'] = $this->attachPivotAndClean($page['data']);
        }

        return $page;
    }

    protected function attachPivotAndClean(array $models): array
    {
        /** @var Model $pv */
        $pv = new $this->pivotClass();

        foreach ($models as $m) {
            // prendi e rimuovi i pivot_* dagli attributes del related
            $attrs = $m->takeAttributesByPrefix('pivot_'); // es. ['menu_id'=>3,'category_id'=>7,'sort_order'=>2]

            $pivotModel = new $this->pivotClass();

            $pivotModel->setRawAttributes($attrs, false);
            
            $m->setRelation('pivot', $pivotModel);
        }

        return $models;
    }

    public function kind(): string {
        return 'belongsToMany';
    }

    public function pivotTable(): string {
        /** @var Model $pv */
        $pv = new $this->pivotClass();
        
        return $pv->getTable();
    }

    public function foreignKeyName(): string
    {
        $this->prepareKeys();
        return $this->fk;
    }

    public function relatedKeyName(): string
    {
        $this->prepareKeys();
        return $this->rk;
    }

}
