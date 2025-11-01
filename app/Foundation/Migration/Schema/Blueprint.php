<?php

namespace PluginClassName\Foundation\Migration\Schema;

if (!defined('ABSPATH')) {
    exit;
}

class Blueprint
{
    public string $table;
    public bool $create = false;

    /** @var array<int, string> */
    protected array $statements = [];

    public function __construct(string $table)
    {
        $this->table = $table;
    }

    /* ---------- tipi base ---------- */

    public function id(string $name = 'id'): self
    {
        return $this->bigIncrements($name);
    }

    public function bigIncrements(string $name): self
    {
       $this->column("`$name` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT");

       $this->primary($name);

       return $this;
    }

    public function primary(array|string $columns): self
    {
        $cols = (array) $columns;
        $this->statements[] = "PRIMARY KEY (" . implode(',', array_map(fn($c) => "`$c`", $cols)) . ")";
        return $this;
    }

    public function string(string $name, int $length = 255, bool $nullable = false, ?string $default = null): self
    {
        $def = "`$name` VARCHAR($length) " . ($nullable ? "NULL" : "NOT NULL");
        if ($default !== null) {
            $def .= " DEFAULT " . $this->quoteDefault($default);
        }
        return $this->column($def);
    }

    public function enum(string $name, array $allowed, bool $nullable = false, ?string $default = null): self
    {
        if (empty($allowed)) {
            throw new \InvalidArgumentException("Enum '$name' requires at least one allowed value.");
        }
        $vals = implode(',', array_map(fn($v) => $this->quoteDefault((string)$v), $allowed));
        $def = "`$name` ENUM($vals) " . ($nullable ? "NULL" : "NOT NULL");
        if ($default !== null) {
            $def .= " DEFAULT " . $this->quoteDefault($default);
        }
        return $this->column($def);
    }

    public function text(string $name, bool $nullable = true): self
    {
        return $this->column("`$name` TEXT " . ($nullable ? "NULL" : "NOT NULL"));
    }

    public function integer(string $name, bool $nullable = false, ?int $default = null): self
    {
        $def = "`$name` INT " . ($nullable ? "NULL" : "NOT NULL");
        if ($default !== null) {
            $def .= " DEFAULT " . (int)$default;
        }
        return $this->column($def);
    }

    public function unsignedInteger(string $name, bool $nullable = false, ?int $default = null): self
    {
        $def = "`$name` INT UNSIGNED " . ($nullable ? "NULL" : "NOT NULL");
        if ($default !== null) {
            $def .= " DEFAULT " . (int)$default;
        }
        return $this->column($def);
    }

    public function bigInteger(string $name, bool $unsigned = false, bool $nullable = false, ?int $default = null): self
    {
        $def = "`$name` BIGINT" . ($unsigned ? " UNSIGNED" : "") . " " . ($nullable ? "NULL" : "NOT NULL");
        if ($default !== null) {
            // NB: default fuori range per BIGINT UNSIGNED causerebbe errore MySQL
            $def .= " DEFAULT " . (string)$default;
        }
        return $this->column($def);
    }

    public function unsignedBigInteger(string $name, bool $nullable = false, ?int $default = null): self
    {
        return $this->bigInteger($name, true, $nullable, $default);
    }

    public function tinyInteger(string $name, bool $nullable = false, ?int $default = null): self
    {
        $def = "`$name` TINYINT " . ($nullable ? "NULL" : "NOT NULL");
        if ($default !== null) {
            $def .= " DEFAULT " . (int)$default;
        }
        return $this->column($def);
    }

    public function boolean(string $name, bool $nullable = false, ?bool $default = null): self
    {
        $def = "`$name` TINYINT(1) " . ($nullable ? "NULL" : "NOT NULL");
        if ($default !== null) {
            $def .= " DEFAULT " . ($default ? 1 : 0);
        }
        return $this->column($def);
    }

    public function json(string $name, bool $nullable = true): self
    {
        // MySQL < 5.7.8 può non supportare JSON: fallback gestito a livello DB se necessario
        return $this->column("`$name` JSON " . ($nullable ? "NULL" : "NOT NULL"));
    }

    public function dateTime(string $name, bool $nullable = true): self
    {
        return $this->column("`$name` DATETIME " . ($nullable ? "NULL" : "NOT NULL"));
    }

    public function date(string $name, bool $nullable = true, ?string $default = null): self
    {
        $def = "`$name` DATE " . ($nullable ? "NULL" : "NOT NULL");
        if ($default !== null) {
            // MySQL 8 consente DEFAULT 'YYYY-MM-DD' o DEFAULT (CURRENT_DATE)
            $def .= " DEFAULT " . $this->quoteDefault($default);
        }
        return $this->column($def);
    }

    public function time(string $name, bool $nullable = false, ?string $default = null): self
    {
        $def = "`$name` TIME " . ($nullable ? "NULL" : "NOT NULL");
        if ($default !== null) {
            // Esempio: '00:00:00' oppure (CURRENT_TIME)
            $def .= " DEFAULT " . $this->quoteDefault($default);
        }
        return $this->column($def);
    }

    public function timestamps(): self
    {
        $this->datetime('created_at');
        $this->datetime('updated_at');
        return $this;
    }

    public function softDeletes(string $col = 'deleted_at'): self
    {
        return $this->datetime($col);
    }

    /* ---------- indici e fk ---------- */

    /**
     * $columns può essere string|array. $name opzionale.
     */
    public function index(array|string $columns, ?string $name = null): self
    {
        $cols = (array) $columns;
        $name ??= 'idx_' . implode('_', $cols);
        $this->statements[] = "INDEX `$name` (" . implode(',', array_map(fn($c) => "`$c`", $cols)) . ")";
        return $this;
    }

    public function dropIndex(array|string $columns, ?string $name = null): self
    {
        $cols = (array) $columns;
        $name ??= 'idx_' . implode('_', $cols);
        $this->statements[] = "DROP INDEX `$name`";
        return $this;
    }

    public function unique(array|string $columns, ?string $name = null): self
    {
        $cols = (array) $columns;
        $name ??= 'uniq_' . implode('_', $cols);

        $payload = "UNIQUE `{$name}` (" . implode(',', array_map(fn($c) => "`$c`", $cols)) . ")";
        $this->statements[] = $this->create ? $payload : "ADD {$payload}";

        return $this;
    }

    public function foreign(string $column, string $refTable, string $refColumn = 'id', string $onDelete = 'RESTRICT', string $onUpdate = 'CASCADE', ?string $name = null): self
    {
        $name ??= "fk_{$this->table}_{$column}";

        $payload = "CONSTRAINT `{$name}` FOREIGN KEY (`{$column}`) REFERENCES `{$refTable}`(`{$refColumn}`) ON DELETE {$onDelete} ON UPDATE {$onUpdate}";
        $this->statements[] = $this->create ? $payload : "ADD {$payload}";

        return $this;
    }

    /* ---------- alter helpers ---------- */

    public function addColumn(string $definition): self
    {
        $this->statements[] = "ADD COLUMN " . $definition;
        return $this;
    }

    public function after(string $column): self
    {
        $last = array_pop($this->statements);
        if ($last === null) {
            throw new \RuntimeException("Cannot use 'after' without a preceding column definition.");
        }
        $this->statements[] = $last . " AFTER `{$column}`";
        return $this;
    }

    public function dropColumn(string $column): self
    {
        $this->statements[] = "DROP COLUMN `{$column}`";
        return $this;
    }

    public function renameColumn(string $from, string $to, string $sqlType): self
    {
        $this->statements[] = "CHANGE `{$from}` `{$to}` {$sqlType}";
        return $this;
    }

    /* ---------- esecuzione ---------- */

    protected function column(string $def): self
    {
        if ($this->create) {
            $this->statements[] = $def;
        } else {
            $this->addColumn($def);
        }
        return $this;
    }

    protected function quoteDefault(string $value): string
    {
        return "'" . esc_sql($value) . "'";
    }

    public function apply(): void
    {
        global $wpdb;
        $customPrefix = defined('PluginClassName_DB_PREFIX') ? PluginClassName_DB_PREFIX : 'fson_';
        $table = $wpdb->prefix . $customPrefix . $this->table;

        if ($this->create) {
            // Filtra solo definizioni creabili dentro CREATE TABLE
            $defs = [];
            $keys = [];
            foreach ($this->statements as $s) {
                if (preg_match('/^(ADD |DROP |CHANGE )/i', $s)) {
                    // è un alter: ignora qui
                    continue;
                }
                // distinguo colonne vs chiavi da tenere in CREATE
                if (stripos($s, 'PRIMARY KEY') !== false || stripos($s, 'UNIQUE ') !== false || stripos($s, '(INDEX) ') !== false || stripos($s, 'CONSTRAINT ') !== false) {
                    $keys[] = $s;
                } else {
                    $defs[] = $s;
                }
            }

            $sql = "CREATE TABLE `{$table}` (\n" . implode(",\n", array_merge($defs, $keys)) . "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta($sql);
            return;
        }

        if (!$this->statements) {
            return;
        }

        $alter = "ALTER TABLE `{$table}` " . implode(", ", $this->statements) . ";";
        $wpdb->query($alter);
    }
}