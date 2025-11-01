<?php

namespace PluginClassName\Foundation\Model;

use DateTimeImmutable;
use PluginClassName\Foundation\Model\Concerns\Conditionable;
use PluginClassName\Foundation\Model\Concerns\QueriesRelationships;
use PluginClassName\Foundation\Model\Concerns\Withable;
use PluginClassName\Models\Model;
use PluginClassName\Support\Logger;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * @template TModel of \PluginClassName\Models\Model
 * @implements \IteratorAggregate<int, TModel>
 * @method TModel|null first()
 * @method TModel|null find(int $id)
 * @method TModel findOrFail(int $id)
 * @method array<int,TModel> get()
 */
class Builder
{
	use Withable, Conditionable, QueriesRelationships;

    /** @var class-string<Model> */
    protected string $model;

    /** @var mixed WordPress database connection instance */
	protected static $db = null;

    /** @var ?string ORDER BY clause for the query */
	private ?string $orderBy = null;

	/** @var ?string LIMIT clause for the query */
	private ?string $limit = null;

	/** @var array Parameter bindings for prepared statements */
	private array $bindings = [];

	/** @var array Columns to select in the query */
	private array $columns = ['*'];

	protected array $joins = [];

	/** @var ?string OFFSET clause for the query */
	private ?string $offset = null;

    /** @var array Collection of where conditions for the query */
	private array $andConditions = [];
	private array $orConditions = [];

	private array $groupByCols = [];
	private array $havingClauses = [];

    protected bool $applyGlobalScopes = true;
	private bool $scopesApplied = false;

    private const VALID_OPERATORS = ['=', '!=', '<', '>', '<=', '>=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'IS', 'IS NOT'];

    public function __construct(string $model)
    {
        $this->model = $model;
    }

	private function ensureGlobalScopes(): void
    {
        if ($this->applyGlobalScopes && !$this->scopesApplied) {
            if (is_callable([$this->model, 'applyGlobalScopes'])) {
                ($this->model)::applyGlobalScopes($this);
            }
            $this->scopesApplied = true;
        }
    }

    /**
     * Create a new instance of the model.
     */
    protected function new(array $row = []): Model
    {
        $cls = $this->model;
        return new $cls($row);
    }

	public function getTable(): string
	{
		return $this->table();
	}

    /**
     * Get the table name for the model.
     */
    protected function table(): string
    {
        return $this->model::table();
    }

    /**
     * Get the primary key for the model.
     */
    protected function pk(): string
    {
        return $this->model::primaryKey();
    }

    /**
     * Get the timestamps setting for the model.
     */
    protected function timestamps(): bool
    {
        return $this->model::usesTimestamps();
    }

	protected function trashable(): bool
	{
		return $this->model::isTrashable();
	}

    /**
	 * Get the WordPress database connection instance.
	 *
	 * @return mixed The WordPress database connection
	 */
	public function db()
	{
		if (!self::$db) {
			global $wpdb;
			self::$db = $wpdb;
		}
		return self::$db;
	}

    /**
     * Disable global scopes for the current query.
     *
     * @return static
     */
    public function withoutGlobalScopes(): static
    {
        $this->applyGlobalScopes = false;
        $this->removeDeletedAtScope();
        return $this;
    }

    /**
     * Include trashed records in the query.
     *
     * @return static
     */
    public function withTrashed(): static
    {
        $this->removeDeletedAtScope();
        return $this;
    }

	public function onlyTrashed(): static
	{
		$this->removeDeletedAtScope();

		$this->where('deleted_at', null, 'IS NOT');
		return $this;
	}

    /**
     * Remove the deleted at scope from the query.
     *
     * @return void
     */
    protected function removeDeletedAtScope(): void
    {
        $this->andConditions = array_values(array_filter(
            $this->andConditions ?? [],
            fn($sql) => stripos($sql, '`deleted_at`') === false
        ));
    }

    /**
	 * Set the columns to be selected.
	 *
	 * @param array $columns The columns to select
	 * @return static The current query builder instance
	 */
	public function select(array $columns): self
	{
		$this->columns = array_map(fn($col) => "`" . esc_sql($col) . "`", $columns);
		return $this;
	}

	public function addSelect(string ...$columns): self
	{
		foreach ($columns as $c) {
			if ($c !== '*' && in_array('*', $this->columns, true)) {
				$this->columns = array_values(array_diff($this->columns, ['*']));
			}

			if (!in_array($c, $this->columns, true)) {
				$this->columns[] = $c;
			}
		}

		return $this;
	}

	protected function quoteColumnForSelect(string $col): string
	{
		$col = trim($col);

		// '*' puro
		if ($col === '*') return '*';

		// 'table.*'
		if (preg_match('/^[A-Za-z0-9_]+\.\*$/', $col)) return $col;

		// Se contiene spazi, backtick, parentesi, punto, oppure ' AS ' ⇒ è un'espressione già formata
		if (preg_match('/[\s`().]/', $col) || stripos($col, ' as ') !== false) {
			return $col;
		}

		// Identificatore semplice
		return '`' . esc_sql($col) . '`';
	}

	protected function quoteIdentifierForWhere(string $column): string
	{
		$column = trim($column);

		// Se contiene dot/backtick/parentesi/spazi o ' AS ' ⇒ è già un'espressione pronta
		if (preg_match('/[\s`().]/', $column) || strpos($column, '.') !== false || stripos($column, ' as ') !== false) {
			return $column;
		}

		if (!empty($this->joins)) {
			return $this->table() . '.`' . esc_sql($column) . '`';
		}

		return '`' . esc_sql($column) . '`';
	}

	protected function quoteColumnForAggregate(string $col): string
	{
		$col = trim($col);

		// '*' o 'table.*'
		if ($col === '*' || preg_match('/^[A-Za-z0-9_]+\.\*$/', $col)) return $col;

		// Se sembra un'espressione (spazi, backtick, parentesi, punto, funzioni, AS) la lasciamo “raw”
		if (preg_match('/[\s`().]/', $col) || strpos($col, '.') !== false || stripos($col, ' as ') !== false) {
			return $col;
		}

		if (!empty($this->joins)) {
			return $this->table() . '.`' . esc_sql($col) . '`';
		}

		return '`' . esc_sql($col) . '`';
	}


	public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): self
	{
		$type = strtoupper($type);

		$this->joins[] = sprintf("%s JOIN %s ON %s %s %s", $type, $table, $first, $operator, $second);
		return $this;
	}

    /**
	 * Add a where clause to the query.
	 *
	 * @param string $column The column name
	 * @param mixed $value The value to compare against
	 * @param mixed $operator The comparison operator
	 * 
	 * @return static The current query builder instance
	 */
	public function where(string $column, mixed $value, mixed $operator = '='): self
	{
		if ($operator === null) $operator = '=';

		[$column, $operator, $value] = $this->normalizeWhereArgs($column, $operator, $value);
		
		$this->pushCondition($this->andConditions, $column, $value, $operator);
		return $this;
	}

	/**
	 * Add an OR WHERE clause to the query.
	 *
	 * @param string $column The column name
	 * @param mixed $value The value to compare against
	 * @param mixed $operator The comparison operator
	 * 
	 * @return static The current query builder instance
	 */
	public function orWhere(string $column, mixed $value, mixed $operator = '='): self
	{
		if ($operator === null) $operator = '=';

		[$column, $operator, $value] = $this->normalizeWhereArgs($column, $operator, $value);

		$this->pushCondition($this->orConditions, $column, $value, $operator);
		return $this;
	}

    /**
	 * Add a WHERE IN clause to the query.
	 *
	 * @param string $column The column name
	 * @param array $values The list of values to compare against
	 * 
	 * @return static
	 */
	public function whereIn(string $column, ?array $values, bool $or = false): self
	{
		if (empty($values)) {
			$this->andConditions[] = ($or) ? '(0=1)' : '0=1';
			return $this;
		}

		if ($or) {
        	$this->pushCondition($this->orConditions, $column, $values, 'IN');
		} else {
			$this->pushCondition($this->andConditions, $column, $values, 'IN');
		}
		return $this;
	}

    /**
	 * Add a WHERE NOT IN clause to the query.
	 *
	 * @param string $column The column name
	 * @param array $values The list of values to exclude
	 * 
	 * @return static
	 */
	public function whereNotIn(string $column, array $values, bool $or = false): self
	{
		if ($or) {
			$this->pushCondition($this->orConditions, $column, $values, 'NOT IN');
		} else {
			$this->pushCondition($this->andConditions, $column, $values, 'NOT IN');
		}
		return $this;
	}

	public function whereNull(string $column, bool $or = false): self
	{
		if ($or) {
			$this->orConditions[] = sprintf("%s IS NULL", $this->quoteIdentifierForWhere($column));
		} else {
			$this->andConditions[] = sprintf("%s IS NULL", $this->quoteIdentifierForWhere($column));
		}
		return $this;
	}

	/**
	 * Add a raw where clause to the query.
	 *
	 * @param string $sql The raw SQL clause
	 * @param array $bindings The bindings for the raw clause
	 * @return static The current query builder instance
	 */
	public function whereRaw(string $sql, array $bindings = []): self
	{
		$this->andConditions[] = $sql;
		if ($bindings) $this->bindings = array_merge($this->bindings, $bindings);
		return $this;
	}

	/**
	 * Add an OR WHERE clause to the query.
	 *
	 * @param string $sql The raw SQL clause
	 * @param array $bindings The bindings for the raw clause
	 * @return static The current query builder instance
	 */
	public function orWhereRaw(string $sql, array $bindings = []): self
	{
		$this->orConditions[] = $sql;
		if ($bindings) $this->bindings = array_merge($this->bindings, $bindings);

		return $this;
	}

	private function normalizeWhereArgs(string $column, mixed $operatorOrValue, mixed $value = null): array
	{
		$op  = '=';
        $val = null;

		if (func_num_args() === 2 || ($value === null && !is_string($operatorOrValue))) {
			$op  = '=';
			$val = $operatorOrValue;
		} else {
			// 3 argomenti
			if (is_string($operatorOrValue) && in_array(strtoupper($operatorOrValue), self::VALID_OPERATORS, true)) {
				// Laravel-like: where('col', '>=', 10)
				$op  = strtoupper($operatorOrValue);
				$val = $value;
			} else {
				// Compat con la tua firma storica: where('col', 10, '>=')
				$op  = is_string($value) ? strtoupper($value) : '=';
				$val = $operatorOrValue;
			}
		}

		return [$column, $op, $val];
	} 

    /**
	 * Add an order by clause to the query.
	 *
	 * @param string $column The column to order by
	 * @param string $direction The sort direction (ASC or DESC)
	 * @return static The current query builder instance
	 */
	public function orderBy(string $column, string $direction = 'ASC'): self
	{
		$direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
		$clause = sprintf("`%s` %s", esc_sql($column), $direction);

		if (preg_match('/[.`(]/', $column)) {
			$clause = $column . ' ' . $direction;
		} else {
			if (!empty($this->joins)) {
				$clause = $this->table() . '.`' . esc_sql($column) . '` ' . $direction;
			} else {
				$clause = '`' . esc_sql($column) . '` ' . $direction;
			}
		}

		if (empty($this->orderBy)) {
			$this->orderBy = "ORDER BY $clause";
		} else {
			$this->orderBy .= ", $clause";
		}

		return $this;
	}

	/**
	 * Add a GROUP BY clause to the query.
	 *
	 * @param string ...$columns The columns to group by
	 * @return static The current query builder instance
	 */
	public function groupBy(string ...$columns): self
	{
		foreach ($columns as $c) {
			if (!in_array($c, $this->groupByCols, true)) {
				$this->groupByCols[] = $c;
			}
		}
		return $this;
	}

	/**
	 * Add a HAVING clause to the query.
	 *
	 * @param string $sql The HAVING clause SQL
	 * @param array $bindings The bindings for the HAVING clause
	 * @return static The current query builder instance
	 */
	public function havingRaw(string $sql, array $bindings = []): self
	{
		$this->havingClauses[] = $sql;
		if (!empty($bindings)) {
			$this->bindings = array_merge($this->bindings, $bindings);
		}
		return $this;
	}

    /**
	 * Set the maximum number of records to return.
	 *
	 * @param int $number The maximum number of records
	 * @return static The current query builder instance
	 */
	public function limit(int $number): self
	{
		$this->limit = sprintf("LIMIT %d", $number);
		return $this;
	}

    /**
	 * Set the number of records to skip.
	 *
	 * @param int $number The number of records to skip
	 * @return static The current query builder instance
	 */
	public function offset(int $number): self
	{
		$this->offset = sprintf("OFFSET %d", $number);
		return $this;
	}

    /**
	 * Execute the query and get the results.
	 *
	 * @return array The query results
	 */
	public function get(): array
	{
		$selects = !empty($this->columns) ? $this->columns : ['*'];
		$query = $this->buildQuery(
			"SELECT " . implode(',', array_map([$this, 'quoteColumnForSelect'], $selects)) . " FROM " . $this->table()
		);
		
		$results = self::db()->get_results(self::db()->prepare($query, ...$this->bindings), ARRAY_A);

		$items = array_map(fn($row) => $this->new($row), $results ?: []);

		if (!empty($this->with)) {

			foreach ($items as $m) {
				$m->setWithTree($this->with);
			}

            $this->eagerLoadRelations($items, $this->with);
        }

        return $items;
	}

	protected function eagerLoadRelations(array $models, array $withTree): void
	{
		if (empty($models) || empty($withTree)) return;

		foreach ($withTree as $relation => $children) {

			$rel = null;
			foreach ($models as $m) {
				if (method_exists($m, $relation)) {
					$rel = $m->{$relation}();
					break;
				}
			}

			if (!$rel) continue;

			if (method_exists($rel, 'initRelation')
				&& method_exists($rel, 'addEagerConstraints')
				&& method_exists($rel, 'getEager')
				&& method_exists($rel, 'match')) {

				$models = $rel->initRelation($models, $relation);

				$rel->addEagerConstraints($models);

				$results = $rel->getEager();

				$models  = $rel->match($models, $results, $relation);

				if (!empty($children)) {
					$childrenParents = [];
					foreach ($models as $m) {
						$loaded = $m->getRelation($relation);
						if (is_array($loaded)) {
							foreach ($loaded as $child) $childrenParents[] = $child;
						} elseif ($loaded) {
							$childrenParents[] = $loaded;
						}
					}
					if (!empty($childrenParents)) {
						$this->eagerLoadRelations($childrenParents, $children);
					}
				}

				continue;
			}

			foreach ($models as $m) {
				if (!method_exists($m, $relation)) continue;
				$value = $m->{$relation}(); // può essere getResults() interno
				$m->setRelation($relation, $value);

				if (!empty($children) && $value) {
					if (is_array($value)) {
						$this->eagerLoadRelations($value, $children);
					} else {
						$this->eagerLoadRelations([$value], $children);
					}
				}
			}
		}
	}

	/**
	 * Build the complete SQL query string.
	 *
	 * @param string $baseQuery The base SQL query
	 * @return string The complete SQL query
	 */
	private function buildQuery(string $baseQuery): string
	{
		$this->ensureGlobalScopes();

		$where = [];

		if (!empty($this->joins)) {
			$baseQuery .= ' ' . implode(' ', $this->joins);
		}

		if (!empty($this->andConditions)) $where[] = implode(' AND ', $this->andConditions);
		if (!empty($this->orConditions)) $where[] = '(' . implode(' OR ', $this->orConditions) . ')';
		
		if (!empty($where)) $baseQuery .= ' WHERE ' . implode(' AND ', $where);

		if ($this->orderBy) $baseQuery .= " " . $this->orderBy;
		if ($this->limit) $baseQuery .= " " . $this->limit;
		if ($this->offset) $baseQuery .= " " . $this->offset;

		if (!empty($this->groupByCols)) $baseQuery .= ' GROUP BY ' . implode(', ', $this->groupByCols);
		if (!empty($this->havingClauses)) $baseQuery .= ' HAVING ' . implode(' AND ', $this->havingClauses);

		return $baseQuery;
	}

	private function buildQueryForAggregate(string $baseQuery): string
	{
		$this->ensureGlobalScopes();

		// JOINS
		if (!empty($this->joins)) {
			$baseQuery .= ' ' . implode(' ', $this->joins);
		}

		// WHERE
		$where = [];
		if (!empty($this->andConditions)) $where[] = implode(' AND ', $this->andConditions);
		if (!empty($this->orConditions))  $where[] = '(' . implode(' OR ', $this->orConditions) . ')';
		if (!empty($where)) $baseQuery .= ' WHERE ' . implode(' AND ', $where);

		// GROUP BY / HAVING (ordine corretto SQL)
		if (!empty($this->groupByCols))   $baseQuery .= ' GROUP BY ' . implode(', ', $this->groupByCols);
		if (!empty($this->havingClauses)) $baseQuery .= ' HAVING ' . implode(' AND ', $this->havingClauses);

		return $baseQuery;
	}

	private function runAggregate(string $fn, string $column): float|int|null
	{
		$expr = $this->quoteColumnForAggregate($column);

		if (empty($this->groupByCols)) {
			$sql = $this->buildQueryForAggregate("SELECT {$fn}({$expr}) AS aggregate FROM " . $this->table());
			$val = self::db()->get_var(self::db()->prepare($sql, ...$this->bindings));
			return $val !== null ? (float)$val : 0.0;
		}

		$inner = $this->buildQueryForAggregate("SELECT {$fn}({$expr}) AS agg_val FROM " . $this->table());
		$outer = "SELECT SUM(agg_val) FROM ({$inner}) _agg";
		$val   = self::db()->get_var(self::db()->prepare($outer, ...$this->bindings));

		return $val !== null ? (float)$val : 0.0;
	}

	/**
	 * Push a condition onto the query.
	 *
	 * @param array $bag The condition bag to push to
	 * @param string $column The column name
	 * @param mixed $value The value to compare against
	 * @param string $operator The comparison operator
	 * 
	 * @return void
	 */
	private function pushCondition(array &$bag, string $column, mixed $value, string $operator): void
	{
		$op = strtoupper($operator);
		if (!in_array($op, self::VALID_OPERATORS, true)) {
			throw new \InvalidArgumentException(sprintf('Invalid SQL operator: %s', esc_html($operator)));
		}

		$col = $this->quoteIdentifierForWhere($column);

		// IS / IS NOT → gestisci NULL senza placeholder
		if ($op === 'IS' || $op === 'IS NOT') {
			if ($value === null) {
				$bag[] = "$col $op NULL";
				return;
			}
			// Se serve IS TRUE/FALSE/… consenti placeholder
			$bag[] = "$col $op %s";
			$this->bindings[] = $value;
			return;
		}

		// IN / NOT IN
		if ($op === 'IN' || $op === 'NOT IN') {
			if (!is_array($value)) {
				throw new \InvalidArgumentException('The values for IN/NOT IN must be an array.');
			}

			if (empty($value)) {
				$bag[] = ($op === 'IN') ? '0=1' : '1=1';
				return;
			}

			$placeholders = implode(', ', array_fill(0, count($value), '%s'));
			$bag[] = "$col $op ($placeholders)";
			$this->bindings = array_merge($this->bindings, $value);
			return;
		}

		// Operatori standard
		$bag[] = "$col $op %s";
		$this->bindings[] = $value;
	}

    /**
	 * Get the first record matching the query.
	 *
	 * @return static|null The first matching record or null if none found
	 */
	public function first(): ?Model
	{
		$this->limit = 'LIMIT 1';
		
		return $this->get()[0] ?? null;
	}

    /**
	 * Get the value of a single column from the first record.
	 *
	 * @param string $column The column name
	 * @return mixed The value of the specified column, or null if not found
	 */
	public function value(string $column) {
		$record = $this->first();
		return $record ? $record->{$column} : null;
	}

    /**
	 * Extract values from a specific column, optionally using another column as keys.
	 *
	 * @param string $valueColumn The column to extract values from.
	 * @param string|null $keyColumn Optional column to use as keys in the resulting array.
	 * @return array The plucked values.
	 */
	public function pluck(string $valueColumn, ?string $keyColumn = null): array
	{
		$results = $this->get();
		if ($keyColumn === null) {
			// array semplice: ['val1', 'val2', ...]
			return array_map(fn($record) => $record->{$valueColumn}, $results);
		}

		// array associativo: ['key' => 'value']
		$assoc = [];
		foreach ($results as $record) {
			$key = $record->{$keyColumn};
			if (isset($assoc[$key])) {
				throw new \RuntimeException("Duplicate key '$key' found in pluck().");
			}
			$assoc[$key] = $record->{$valueColumn};
		}

		return $assoc;
	}

    /**
	 * Count the number of records matching the query.
	 *
	 * @return int The number of matching records
	 */
	public function count(): int
	{
		$query = $this->buildQuery("SELECT COUNT(*) FROM " . $this->table());
		return (int) self::db()->get_var(self::db()->prepare($query, ...$this->bindings));
	}

	public function sum(string $column): float|int
	{
		return $this->runAggregate('SUM', $column);
	}

	public function sumDistinct(string $column): float|int
	{
		$expr = 'DISTINCT ' . $this->quoteColumnForAggregate($column);
		return $this->runAggregate('SUM', $expr);
	}

	public function avg(string $column): float|int
	{
		return $this->runAggregate('AVG', $column);
	}

	public function min(string $column): float|int
	{
		return $this->runAggregate('MIN', $column);
	}

	public function max(string $column): float|int
	{
		return $this->runAggregate('MAX', $column);
	}

    /**
	 * Check if any record exists matching the query.
	 *
	 * @return bool True if records exist, false otherwise
	 */
	public function exists(): bool
	{
		$this->limit = 'LIMIT 1';
		$query = $this->buildQuery("SELECT 1 FROM " . $this->table());
		return (bool) self::db()->get_row(self::db()->prepare($query, ...$this->bindings), ARRAY_N);
	}

    /**
	 * Find a record by its primary key.
	 *
	 * @param int $id The primary key value
	 * @return ?static The found record or null if not found
	 */
	public function find($id): ?Model
	{
		return $this->where($this->pk(), $id)
			->first()
        ;
	}

    /**
	 * Find a record by its primary key or throw an exception if not found.
	 *
	 * @param int $id The primary key value
	 * @return static The found record
	 * @throws \InvalidArgumentException If the record is not found
	 */
	public function findOrFail(int|string $id): Model
	{
		$record = $this->find($id);
		if (!$record) {
			throw new \InvalidArgumentException(sprintf('Record with ID %d not found', $id));
		}

		return $record;
	}

    /**
	 * Get all records from the database.
	 *
	 * @return array Array of all records
	 */
	public function all(): array
	{
		return $this->get();
	}

    /**
	 * Paginate the query results.
	 *
	 * @param int $perPage The number of items per page
	 * @param int|null $page The current page number
	 * 
	 * @return array The paginated results
	 * 
	 * @throws \InvalidArgumentException If the page number is not valid
	 * @throws \RuntimeException If the query is not valid
	 */
    public function paginate(int $perPage = 20, ?int $page = null): array
    {
        if ($page === null && isset($_GET['page']) && is_numeric($_GET['page'])) {
            $page = (int) $_GET['page'];
        }

        $page = max(1, (int) ($page ?? 1));
        $offset = ($page - 1) * $perPage;

        $countSql = $this->buildQuery(
            "SELECT COUNT(*) FROM " . $this->table(),
            $this->andConditions,
            $this->orConditions,
            null,
            null,
            null
        );

        $total = (int) $this->db()->get_var(
            $this->db()->prepare($countSql, ...$this->bindings)
        );

        $colsList = !empty($this->columns) ? $this->columns : ['*'];
		$cols = implode(',', array_map([$this, 'quoteColumnForSelect'], $colsList));

		$this->limit($perPage);
		$this->offset($offset);

        $sql = $this->buildQuery("SELECT {$cols} FROM " . $this->table());

        $rows = $this->db()->get_results(
            $this->db()->prepare($sql, ...$this->bindings),
            ARRAY_A
        );

        $items = [];
        foreach ($rows ?: [] as $row) {
            $m = $this->new();
            
            foreach ($row as $k => $v) {
                $m->{$k} = $v;
            }

            if (!empty($this->with)) {
                $m->setWithTree($this->with);
            }
            $items[] = $m;
        }

        return [
            'data'         => $items,
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => (int) ceil($total / max(1, $perPage)),
        ];
    }

    /**
	 * Create a new record in the database.
	 *
	 * @param array $data The data to insert
	 * @return int|null The ID of the inserted record or null if the operation failed
	 */
	public function create(array $data): int|string|null
	{
		if (empty($data)) return null;

		$model = $this->new();

		if ($this->timestamps()) {
			$now = current_time('mysql');

			$data['created_at'] = $now;
			$data['updated_at'] = $now;
		}

		$ok = self::db()->insert($model->getTable(), $data);
		if (!$ok) return null;

		$id = self::db()->insert_id;

		$pk = $model->getPrimaryKey();
		if ($pk) {
			$model->$pk = $id;
		}

		return $id;
	}

    /**
	 * Update records matching the query.
	 *
	 * @param array $data The data to update
	 * @return bool True if the update was successful
	 */
	public function update(array $data): bool
	{
		if (empty($data)) return false;

        $model = $this->new();

		if (empty($data) && !$this->timestamps() && !array_key_exists('deleted_at', $data)) {
			return false;
		}

		if ($this->timestamps()) {
			$now = current_time('mysql');

			$data['updated_at'] = $now;
		}

		if (empty($this->andConditions) && empty($this->orConditions)) {
			throw new \RuntimeException('Refusing to update without a WHERE clause.');
		}

		$pk = $this->pk();
		if (array_key_exists($pk, $data)) {
			unset($data[$pk]);
		}

		$pairs  = [];
    	$values = [];

        foreach ($data as $col => $val) {
			// NB: esc_sql è pensato per dati, non per identificatori.
			// Se le colonne sono whitelisted dal Model è ok così:
			$colSql = '`' . esc_sql($col) . '`';

			if ($val === null) {
				$pairs[] = "{$colSql} = NULL";
			} else {
				$pairs[] = "{$colSql} = %s";
				$values[] = $val;
			}
		}

		if (empty($pairs)) {
			// niente da aggiornare
			return false;
		}

		$sql = "UPDATE " . $model->getTable() . " SET " . implode(', ', $pairs);
		$sql = $this->buildQuery($sql, $this->andConditions, $this->orConditions);

		if (!empty($this->bindings)) {
			$values = array_merge($values, $this->bindings);
		}

		$prepared = self::db()->prepare($sql, ...$values);
		return (bool) self::db()->query($prepared);
	}

    /**
     * Update a record or create it if it doesn't exist.
     * @param array $data
     * @return Model
     */
    public function updateOrCreate(array $data): Model
	{
		$model = $this->new();
        $pk    = $this->pk();
	
		// se ha PK -> update by PK, altrimenti insert
        if (!empty($data[$pk]) && $this->where($pk, $data[$pk])->exists())
		{
			$id = $data[$pk];
			unset($data[$pk]);

            $model->query()->where($pk, $id)->update($data);
			
			return $model->query()->findOrFail($id);
        }

        $id = $this->create($data);
        return $model->query()->findOrFail($id);
	}

	/**
	 * First or create a record.
	 * @param array $where Conditions to find the record
	 * @param array $data Data to create if not found
	 * @return Model
	 */
	public function firstOrCreate(array $where, array $data): Model
	{
		$model = $this->new();

		$query = clone $this;
		foreach ($where as $col => $val) {
			$query->where($col, $val);
		}

		$found = $query->first();
		if ($found) {
			return $found;
		}

		$recordData = array_merge($where, $data);
		$id = $this->create($recordData);
		if ($id) {
			$model->{$this->pk()} = $id;
		}

		return $model;
	}

    /**
     * Delete records that match the current query.
     * @return bool
     */
    public function delete(): bool
    {
        if (empty($this->andConditions) && empty($this->orConditions)) {
            throw new \RuntimeException('Refusing to delete without a WHERE clause.');
        }

		if ($this->trashable()) {

			return $this->softDelete([
				'deleted_at' => current_time('mysql')
			]);
		}

		return $this->forceDelete();
    }

    /**
     * Hard delete records that match the current query.
     * @return bool
     */
    public function forceDelete(): bool
    {
		if (empty($this->andConditions) && empty($this->orConditions)) {
            throw new \RuntimeException('Refusing to delete without a WHERE clause.');
        }

		$table = $this->table();
        $sql   = $this->buildQuery("DELETE FROM {$table}", $this->andConditions, $this->orConditions);

        return (bool) $this->db()->query($this->db()->prepare($sql, ...$this->bindings));
    }

    /**
     * Soft delete records that match the current query.
     * @param array $data
     * @return bool
     */
    public function softDelete(array $data): bool
    {
        return $this->update($data);
    }

	public function exportWhereParts(): array
	{
		return [
			'and'      => $this->andConditions,
			'or'       => $this->orConditions,
			'bindings' => $this->bindings,
		];
	}

	public function exportGroupHavingParts(): array
	{
		return [
			'groupBy'  => $this->groupByCols,
			'having'   => $this->havingClauses,
		];
	}

	/**
	 * Get the last executed SQL query.
	 *
	 * @return string The last executed SQL query
	 */
	public function lastQuery(): string
	{
		return self::db()->last_query;
	}
}