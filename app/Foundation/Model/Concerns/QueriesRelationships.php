<?php

namespace PluginClassName\Foundation\Model\Concerns;

use PluginClassName\Foundation\Model\Builder;
use PluginClassName\Models\Model;
use PluginClassName\Foundation\Model\Relations\Relation;
use PluginClassName\Foundation\Model\Relations\BelongsToMany;
use PluginClassName\Foundation\Model\Relations\HasMany;
use PluginClassName\Foundation\Model\Relations\HasOne;
use PluginClassName\Foundation\Model\Relations\BelongsTo;

if (!defined('ABSPATH')) { exit; }

/**
 * whereHas / whereRelation super-ottimizzati:
 * - EXISTS annidati (dot-notation)
 * - cache metadati per catene di relazioni
 * - nessuna reflection (usa i getter delle Relation)
 * - supporto opzionale a GROUP BY/HAVING dentro il callback
 */
trait QueriesRelationships
{
    /** @var array<string, array> Cache meta per catene: "Model|rel.rel2" => [hop, hop, ...] */
    protected static array $relChainCache = [];

    /* ===================== API pubblica ===================== */

    public function whereHas(
        string $relationPath,
        ?callable $callback = null,
        string $operator = '>=',
        int $count = 1
    ): self {
        // caso fast-path: >= 1 → EXISTS
        if ($operator === '>=' && $count === 1) {
            [$sql, $bind] = $this->compileExistsChain($relationPath, $callback);
            return $this->whereRaw("EXISTS ({$sql})", $bind);
        }

        // single-hop conteggio (hook opzionale)
        if (substr_count($relationPath, '.') === 0) {
            [$sql, $bind] = $this->compileCountSingleHop($relationPath, $callback);
            return $this->whereRaw("({$sql}) {$operator} %d", array_merge($bind, [$count]));
        }

        throw new \LogicException("count-operator su chain multiple non supportato in questa versione (usa EXISTS).");
    }

    public function orWhereHas(
        string $relationPath,
        ?callable $callback = null,
        string $operator = '>=',
        int $count = 1
    ): self {
        if ($operator === '>=' && $count === 1) {
            [$sql, $bind] = $this->compileExistsChain($relationPath, $callback);
            return $this->orWhereRaw("EXISTS ({$sql})", $bind);
        }
        if (substr_count($relationPath, '.') === 0) {
            [$sql, $bind] = $this->compileCountSingleHop($relationPath, $callback);
            return $this->orWhereRaw("({$sql}) {$operator} %d", array_merge($bind, [$count]));
        }
        throw new \LogicException("count-operator su chain multiple non supportato (usa EXISTS).");
    }

    public function doesntHave(string $relationPath, ?callable $callback = null): self
    {
        [$sql, $bind] = $this->compileExistsChain($relationPath, $callback);
        return $this->whereRaw("NOT EXISTS ({$sql})", $bind);
    }

    public function orWhereDoesntHave(string $relationPath, ?callable $callback = null): self
    {
        [$sql, $bind] = $this->compileExistsChain($relationPath, $callback);
        return $this->orWhereRaw("NOT EXISTS ({$sql})", $bind);
    }

    public function whereRelation(
        string $relationPath,
        string $column,
        mixed $operatorOrValue,
        mixed $value = null
    ): self {
        // compila un EXISTS e applica la singola where sull’ultimo related
        $cb = function (Builder $q) use ($column, $operatorOrValue, $value) {
            $q->where($column, $operatorOrValue, $value);
        };
        [$sql, $bind] = $this->compileExistsChain($relationPath, $cb);
        return $this->whereRaw("EXISTS ({$sql})", $bind);
    }

    public function orWhereRelation(
        string $relationPath,
        string $column,
        mixed $operatorOrValue,
        mixed $value = null
    ): self {
        $cb = function (Builder $q) use ($column, $operatorOrValue, $value) {
            $q->where($column, $operatorOrValue, $value);
        };
        [$sql, $bind] = $this->compileExistsChain($relationPath, $cb);
        return $this->orWhereRaw("EXISTS ({$sql})", $bind);
    }

    /* ===================== CORE: EXISTS ===================== */

    protected function compileExistsChain(string $relationPath, ?callable $callback): array
    {
        $segments = array_values(array_filter(explode('.', $relationPath)));
        if (!$segments) {
            throw new \InvalidArgumentException("Relation path non valido: '{$relationPath}'");
        }

        $rootClass = $this->model;
        /** @var Model $rootProto */
        $rootProto = new $rootClass();
        $rootTable = $rootProto->getTable();
        $rootPk    = $rootProto->getPrimaryKey();

        // meta cache (per evitare rebuild ogni volta)
        $meta = $this->resolveChainMeta($rootClass, $segments);

        // costruzione ricorsiva della EXISTS finale
        [$sql, $bind] = $this->existsFromMeta($meta, $rootTable, $rootPk, $callback);
        // MySQL in EXISTS si ferma al primo match; LIMIT 1 è facoltativo (a volte aiuta)
        return [$sql . " LIMIT 1", $bind];
    }

    /**
     * @return array<array>  lista di hop con chiavi: type, leftClass,leftTable,leftPk, rightClass,rightTable,rightPk, [pivotTable,fk,rk]/[foreignKey,localKey]/[foreignKey,ownerKey]
     */
    protected function resolveChainMeta(string $rootClass, array $segments): array
    {
        $key = $rootClass . '|' . implode('.', $segments);
        if (isset(self::$relChainCache[$key])) return self::$relChainCache[$key];

        $meta = [];
        $leftClass = $rootClass;
        /** @var Model $leftProto */
        $leftProto = new $leftClass();
        $leftTable = $leftProto->getTable();
        $leftPk    = $leftProto->getPrimaryKey();

        foreach ($segments as $relName) {
            if (!method_exists($leftProto, $relName)) {
                throw new \InvalidArgumentException("Relazione '{$relName}' non definita su {$leftClass}");
            }

            /** @var Relation $rel */
            $rel = $leftProto->{$relName}();

            $type        = $rel->kind();
            $rightClass  = $rel->relatedClass();
            /** @var Model $rightProto */
            $rightProto  = new $rightClass();
            $rightTable  = $rightProto->getTable();
            $rightPk     = $rightProto->getPrimaryKey();

            $hop = [
                'type'       => $type,
                'leftClass'  => $leftClass,
                'leftTable'  => $leftTable,
                'leftPk'     => $leftPk,
                'rightClass' => $rightClass,
                'rightTable' => $rightTable,
                'rightPk'    => $rightPk,
            ];

            switch ($type) {
                case 'belongsToMany':
                    /** @var BelongsToMany $rel */
                    $hop['pivotTable'] = $rel->pivotTable();
                    $hop['fk']         = $rel->foreignKeyName(); // pivot -> left
                    $hop['rk']         = $rel->relatedKeyName(); // pivot -> right
                    break;

                case 'hasMany':
                case 'hasOne':
                    /** @var HasMany|HasOne $rel */
                    $hop['foreignKey'] = $rel->foreignKeyName(); // right->left
                    $hop['localKey']   = $rel->localKeyName();
                    break;

                case 'belongsTo':
                    /** @var BelongsTo $rel */
                    $hop['foreignKey'] = $rel->foreignKeyName();          // left.fk
                    $hop['ownerKey']   = $rel->ownerKeyName() ?: $rightPk; // right.pk/owner
                    break;

                default:
                    throw new \LogicException("Tipo relazione non supportato: {$type}");
            }

            $meta[] = $hop;

            // avanza
            $leftClass = $rightClass;
            $leftProto = $rightProto;
            $leftTable = $rightTable;
            $leftPk    = $rightPk;
        }

        return self::$relChainCache[$key] = $meta;
    }

    /**
     * Converte la meta-chain in una EXISTS nidificata; applica il callback sull’ultimo hop.
     */
    protected function existsFromMeta(array $meta, string $rootTable, string $rootPk, ?callable $callback): array
    {
        $bind = [];
        $sql  = $this->compileSingleHopExists($meta[0], $rootTable, $rootPk);

        // annida hop interni
        for ($i = 1; $i < count($meta); $i++) {
            $inner = $this->compileSingleHopExists($meta[$i], $meta[$i-1]['rightTable'], $meta[$i-1]['rightPk']);
            $sql  .= " AND EXISTS ({$inner})";
        }

        // callback sull’ultimo related
        if ($callback) {
            $last = end($meta);
            $relatedQ = new Builder($last['rightClass']);
            $callback($relatedQ);

            // where esportate
            [$condSql, $condBind] = $this->qualifyWhereForTable(
                $relatedQ->exportWhereParts(),
                $last['rightTable']
            );
            if ($condSql !== '') {
                $sql  .= " AND {$condSql}";
                $bind = array_merge($bind, $condBind);
            }

            // group/having esportati (se usati nel callback)
            [$ghSql, $ghBind] = $this->qualifyGroupHavingForTable(
                $relatedQ->exportGroupHavingParts(),
                $last['rightTable']
            );
            if ($ghSql !== '') {
                $sql  .= " {$ghSql}";
                $bind  = array_merge($bind, $ghBind);
            }
        }

        return [$sql, $bind];
    }

    /**
     * Compila un singolo hop di EXISTS (correlato al "left").
     */
    protected function compileSingleHopExists(array $hop, string $leftTable, string $leftPk): string
    {
        switch ($hop['type']) {
            case 'belongsToMany':
                $pv = $hop['pivotTable'];
                $fk = $hop['fk']; // pv -> left
                $rk = $hop['rk']; // pv -> right
                return "SELECT 1 FROM {$pv} JOIN {$hop['rightTable']} ".
                       "ON {$hop['rightTable']}.`{$hop['rightPk']}` = {$pv}.`{$rk}` ".
                       "WHERE {$pv}.`{$fk}` = {$leftTable}.`{$leftPk}`";

            case 'hasMany':
            case 'hasOne':
                $fk = $hop['foreignKey']; // right.fk -> left.localKey
                $lk = $hop['localKey'];
                return "SELECT 1 FROM {$hop['rightTable']} ".
                       "WHERE {$hop['rightTable']}.`{$fk}` = {$leftTable}.`{$lk}`";

            case 'belongsTo':
                $fk = $hop['foreignKey']; // left.fk -> right.ownerKey
                $ok = $hop['ownerKey'];
                return "SELECT 1 FROM {$hop['rightTable']} ".
                       "WHERE {$hop['rightTable']}.`{$ok}` = {$leftTable}.`{$fk}`";
        }

        throw new \LogicException('Hop non supportato');
    }

    /**
     * Qualifica le where esportate (AND/OR) sulla tabella indicata e restituisce SQL + bindings.
     */
    protected function qualifyWhereForTable(array $export, string $table): array
    {
        $qual = function (string $cond) use ($table): string {
            // Le tue where sono tutte della forma "`col` OP ..." (grazie a quoteIdentifierForWhere)
            // Qui prefissiamo SOLO il primo identificatore backtickato all’inizio della condizione.
            return preg_replace('/^`([^`]+)`/', $table . '.`$1`', $cond);
        };

        $pieces = [];
        foreach ($export['and'] as $c) $pieces[] = $qual($c);
        if (!empty($export['or'])) {
            $ors = array_map($qual, $export['or']);
            $pieces[] = '(' . implode(' OR ', $ors) . ')';
        }

        $sql = implode(' AND ', $pieces);
        return [$sql, $export['bindings']];
    }

    /**
     * Qualifica GROUP BY / HAVING del callback e restituisce frammento SQL + bindings.
     * NB: HAVING viene passato ‘as-is’ (si assume che chi lo usa sappia cosa sta facendo).
     */
    protected function qualifyGroupHavingForTable(array $export, string $table): array
    {
        $g = $export['groupBy'] ?? [];
        $h = $export['having']  ?? [];

        $group = '';
        if (!empty($g)) {
            $cols = array_map(function ($col) use ($table) {
                $col = trim($col);
                // se è già qualificato o è un’espressione, lascialo
                if (preg_match('/[\s`().]/', $col) || strpos($col, '.') !== false) {
                    return $col;
                }
                return $table . '.`' . esc_sql($col) . '`';
            }, $g);
            $group = 'GROUP BY ' . implode(', ', $cols);
        }

        $having = '';
        if (!empty($h)) {
            // le having sono stringhe raw già pronte (con eventuali %s)
            $having = 'HAVING ' . implode(' AND ', $h);
        }

        $sql = trim($group . ' ' . $having);

        // nessun nuovo binding qui: i bind di HAVING sono già nei bindings esportati dal where (se servisse, potresti veicolarli anche qui)
        return [$sql, []];
    }

    /* ===================== Conteggio single-hop (opzionale) ===================== */

    protected function compileCountSingleHop(string $relation, ?callable $callback): array
    {
        $rootClass = $this->model;
        /** @var Model $rootProto */
        $rootProto = new $rootClass();
        $rootTable = $rootProto->getTable();
        $rootPk    = $rootProto->getPrimaryKey();

        $meta = $this->resolveChainMeta($rootClass, [$relation]);
        $hop  = $meta[0];

        $bind = [];
        switch ($hop['type']) {
            case 'belongsToMany':
                $pv = $hop['pivotTable']; $fk = $hop['fk']; $rk = $hop['rk'];
                $sql = "SELECT COUNT(DISTINCT {$hop['rightTable']}.`{$hop['rightPk']}`) ".
                       "FROM {$pv} JOIN {$hop['rightTable']} ".
                       "ON {$hop['rightTable']}.`{$hop['rightPk']}` = {$pv}.`{$rk}` ".
                       "WHERE {$pv}.`{$fk}` = {$rootTable}.`{$rootPk}`";
                break;

            case 'hasMany':
            case 'hasOne':
                $fk = $hop['foreignKey']; $lk = $hop['localKey'];
                $sql = "SELECT COUNT(*) FROM {$hop['rightTable']} ".
                       "WHERE {$hop['rightTable']}.`{$fk}` = {$rootTable}.`{$lk}`";
                break;

            case 'belongsTo':
                $fk = $hop['foreignKey']; $ok = $hop['ownerKey'];
                $sql = "SELECT COUNT(*) FROM {$hop['rightTable']} ".
                       "WHERE {$hop['rightTable']}.`{$ok}` = {$rootTable}.`{$fk}`";
                break;

            default:
                throw new \LogicException('Conteggio non supportato per hop');
        }

        if ($callback) {
            $relatedQ = new Builder($hop['rightClass']);
            $callback($relatedQ);

            [$condSql, $condBind] = $this->qualifyWhereForTable(
                $relatedQ->exportWhereParts(),
                $hop['rightTable']
            );
            if ($condSql !== '') {
                $sql  .= " AND {$condSql}";
                $bind = array_merge($bind, $condBind);
            }

            // group/having nel conteggio non sono applicati in questa versione
        }

        return [$sql, $bind];
    }
}