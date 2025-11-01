<?php

namespace PluginClassName\Foundation\Model\Relations\Concerns;

use PluginClassName\Foundation\Model\Builder;
use PluginClassName\Models\Model;
use PluginClassName\Support\Logger;

if (!defined('ABSPATH')) { exit; }

trait InteractsWithPivotTable
{
    /** @var array<string> */
    protected array $pivotColumns = [];

    protected ?array $pivotOrder = null;

    /** @var array<array{0:string,1:string,2:mixed}> */
    protected array $pivotWheres = [];

    protected array $eagerParentIds = [];
    protected string $parentPk;
    protected string $relatedPk;
    protected string $fk;
    protected string $rk;

    public function withPivot(string ...$columns): static
    {
        $this->pivotColumns = array_values(array_unique(array_merge($this->pivotColumns, $columns)));
        
        if ($this->query) $this->addPivotSelects();

        return $this;
    }

    public function orderByPivot(string $column, string $direction = 'asc'): static
    {
        $this->pivotOrder = [
            'column'    => $column,
            'direction' => strtolower($direction) === 'desc' ? 'DESC' : 'ASC',
        ];

        if ($this->query) {
            [$relTable, $pvTable] = $this->tables();
            $this->query->orderBy("{$pvTable}.`{$column}`", $this->pivotOrder['direction']);
        }

        return $this;
    }

    public function wherePivot(string $column, mixed $value, mixed $operator = '='): static
    {
        [, $pvTable] = $this->tables();

        $this->query->where("{$pvTable}.`{$column}`", $value, $operator);

        return $this;
    }

    /** ---- Attach/Detach/Sync ---- */

    public function attach(int|array $ids, array $attributes = []): void
    {
        [$idList, $attrMap] = $this->normalizePayload($ids, $attributes);
        $map = $this->combineIdsAndAttrs($idList, $attrMap);
        $this->sync($map, false);
    }

    public function detach(?array $ids = null): void
    {
        $this->sync($ids ? $this->combineIdsAndAttrs($ids, []) : [], false, true);
    }

    public function detachAll(): void
    {
        $this->sync([], detaching: true, detachOnly: true);
    }

    public function sync(array $ids, bool $detaching = true, bool $detachOnly = false): void
    {
        $this->prepareKeys();

        /** @var Model $pv */
        $pv = new $this->pivotClass();

        /** @var Model $parent */
        $parent = $this->parent;

        $foreignId = $parent->{$this->parentPk};

        $existingRows = $pv::query()
            ->where($this->fk, $foreignId)
            ->get()
        ;

        $existingByRid = [];
        $existingIds   = [];
        foreach ($existingRows as $row) {
            $rid = (int) $row->{$this->rk};
            $existingByRid[$rid] = $row;
            $existingIds[] = $rid;
        }

        $desired    = $this->normalizeSyncMap($ids);
        $desiredIds = array_keys($desired);

        $toInsert      = $detachOnly ? [] : array_values(array_diff($desiredIds, $existingIds));
        $toDelete      = $detaching  ? array_values(array_diff($existingIds, $desiredIds)) : [];
        $toMaybeUpdate = array_values(array_intersect($existingIds, $desiredIds));

        foreach ($toInsert as $rid) {
            $payload = $pv->castArrayForStorage($desired[$rid] ?? []);
            $payload[$this->fk] = $foreignId;
            $payload[$this->rk] = (int) $rid;

            $pv::query()->create($payload);
        }

        foreach ($toMaybeUpdate as $rid) {
            $payload = $pv->castArrayForStorage($desired[$rid] ?? []);
            if (!empty($payload)) {
                $pv::query()
                    ->where($this->fk, $foreignId)
                    ->where($this->rk, (int) $rid)
                    ->update($payload)
                ;
            }
        }

        if (!empty($toDelete)) {
            $pv::query()
                ->where($this->fk, $foreignId)
                ->whereIn($this->rk, array_map('intval', $toDelete))
                ->delete()
            ;
        }
    }

    public function syncWithoutDetaching(array $ids): void
    {
        $this->sync($ids, false);
    }

    public function updateExistingPivot(int|string $id, array $attributes): bool
    {
        $this->prepareKeys();

        /** @var Model $pv */
        $pv = new $this->pivotClass();

        /** @var Model $parent */
        $parent = $this->parent;

        $foreignId = $parent->{$this->parentPk};
        $payload   = $pv->castArrayForStorage($attributes);

        if (empty($payload)) {
            return false;
        }

        return $pv::query()
            ->where($this->fk, $foreignId)
            ->where($this->rk, (int) $id)
            ->update($payload)
        ;
    }

    public function hasPivotColumn(string $column): bool
    {
        /** @var Model $pv */
        $pv = new $this->pivotClass();

        $fillable = method_exists($pv, 'getFillable') ? $pv->getFillable() : ($pv->fillable ?? []);

        if (in_array($column, $fillable, true)) {
            return true;
        }

        $this->prepareKeys();
        return $column === $this->fk || $column === $this->rk;
    }

    public function initRelation(array $models, string $relation): array
    {
        foreach ($models as $m) {
            $m->setRelation($relation, []);
        }
        return $models;
    }

    public function addEagerConstraints(array $models): void
    {
        $this->prepareKeys();
        $this->eagerParentIds = [];

        foreach ($models as $m) {
            $id = $m->{$this->parentPk};
            if ($id !== null) $this->eagerParentIds[] = $id;
        }

        $this->eagerParentIds = array_values(array_unique($this->eagerParentIds));
    }

    public function getEager(): array
    {
        if (empty($this->eagerParentIds)) {
            return ['pivotRowsByParent' => [], 'relatedById' => []];
        }
        return $this->runBatch();
    }

    public function match(array $models, array $results, string $relation): array
    {
        $pivotRowsByParent = $results['pivotRowsByParent'];
        $relatedById       = $results['relatedById'];

        foreach ($models as $m) {
            $pid  = $m->{$this->parentPk};
            $rows = $pivotRowsByParent[$pid] ?? [];
            if (empty($rows)) {
                $m->setRelation($relation, []);
                continue;
            }
            $relatedForParent = [];
            foreach ($rows as $row) {
                $rid = $row->{$this->rk};
                if (!isset($relatedById[$rid])) continue;

                $relModel = $relatedById[$rid];
                $relModel->setRelation('pivot', $this->filteredPivotModel($row));
                $relatedForParent[] = $relModel;
            }
            $m->setRelation($relation, $relatedForParent);
        }
        return $models;
    }

    protected function prepareKeys(): void
    {
        /** @var Model $parent */
        $parent = $this->parent;

        /** @var Model $rel */
        $rel = $this->newRelated();

        $this->parentPk = $parent->getPrimaryKey();
        $this->relatedPk = $rel->getPrimaryKey();

        $this->fk = $this->foreignKey ?: $this->inferForeignKey($parent);
        $this->rk = $this->relatedKey ?: $this->inferForeignKey($rel);
    }

    protected function runBatch(): array
    {
        /** @var Model $pv */
        $pv = new $this->pivotClass();

        $pvQuery = $pv::query()
            ->whereIn($this->fk, $this->eagerParentIds)
        ;

        // wherePivot()
        foreach ($this->pivotWheres as [$col, $op, $val]) {
            $pvQuery = $pvQuery->where($col, $op, $val);
        }

        if (!empty($this->pivotOrder)) {
            $pvQuery = $pvQuery->orderBy($this->pivotOrder['column'], $this->pivotOrder['direction']);
        }

        /** @var Model[] $pivotRows */
        $pivotRows = $pvQuery->get();

        if (empty($pivotRows)) {
            return ['pivotRowsByParent' => [], 'relatedById' => []];
        }

        $pivotRowsByParent = [];
        $relatedIds = [];

        foreach ($pivotRows as $row) {
            $pid = $row->{$this->fk};
            $rid = $row->{$this->rk};
            $pivotRowsByParent[$pid][] = $row;
            $relatedIds[] = $rid;
        }
        $relatedIds = array_values(array_unique($relatedIds));

        $related = $this->relatedClass::query()
            ->whereIn($this->relatedPk, $relatedIds)
            ->get()
        ;

        $relatedById = [];
        foreach ($related as $rm) {
            $relatedById[$rm->{$this->relatedPk}] = $rm;
        }

        return ['pivotRowsByParent' => $pivotRowsByParent, 'relatedById' => $relatedById];
    }

    protected function filteredPivotModel(Model $pivotRow): Model
    {
        /** @var Model $pv */
        $pv = new $this->pivotClass();

        $keep = !empty($this->pivotColumns)
            ? array_unique(array_merge([$this->fk, $this->rk], $this->pivotColumns))
            : ($pv->getFillable() ?? [])
        ;

        $attrs = [];
        foreach ($keep as $f) {
            if (in_array($f, $pv->getFillable() ?? [], true)) {
                $attrs[$f] = $pivotRow->__get($f);
            }
        }

        $pv->fill($attrs);
        return $pv;
    }

    protected function addPivotSelects(): void
    {
        [$relTable, $pvTable] = $this->tables();
        $basePivot = [$this->fk, $this->rk];

        $cols = array_values(array_unique(array_merge($basePivot, $this->pivotColumns)));
        foreach ($cols as $col) {
            $this->query->addSelect("{$pvTable}.`{$col}` AS `pivot_{$col}`");
        }
    }

    protected function tables(?Builder $qbRel = null, ?Builder $qbPv = null): array
    {
        $qbRel = $qbRel ?: $this->relatedClass::query();

        /** @var Model $pv */
        $pv = new $this->pivotClass();

        $qbPv = $qbPv ?: $pv::query();

        return [$qbRel->getTable(), $qbPv->getTable()];
    }

    private function normalizePayload(int|array $ids, array $attributes = []): array
    {
        if (is_int($ids)) {
            return [[(int)$ids], [(int)$ids => $attributes]];
        }

        if (array_is_list($ids)) {
            $ids = array_map('intval', $ids);
            $map = [];
            foreach ($ids as $id) $map[$id] = $attributes;
            return [array_values(array_unique($ids)), $map];
        }

        $idList = [];
        $map = [];

        foreach ($ids as $id => $attrs) {
            $id = (int)$id;
            $idList[] = $id;
            $map[$id] = is_array($attrs) ? $attrs : [];
        }

        return [array_values(array_unique($idList)), $map];
    }

    private function combineIdsAndAttrs(array $idList, array $attrMap): array
    {
        $out = [];

        foreach ($idList as $id) {
            $out[(int)$id] = $attrMap[$id] ?? [];
        }

        return $out;
    }

    private function normalizeSyncMap(array $ids): array
    {
        if (empty($ids)) return [];

        if (array_is_list($ids)) {
            $out = [];
            foreach ($ids as $id) $out[(int)$id] = [];
            return $out;
        }

        $out = [];
        foreach ($ids as $id => $attrs) {
            $out[(int)$id] = is_array($attrs) ? $attrs : [];
        }

        return $out;
    }

    private function inferForeignKey(object $model): string
    {
        $cls = (new \ReflectionClass($model))->getShortName();

        $base = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $cls));

        return $base . '_id';
    }
}