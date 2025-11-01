<?php

namespace PluginClassName\Support\Validation\Rules;

use Respect\Validation\Rules\Core\Simple;
use wpdb;

if (!defined('ABSPATH')) {
	exit;
}

final class Exists extends Simple
{
    protected string $table;
    protected string $column;
    protected wpdb $db;

    public function __construct(string $table, string $column)
    {        
        global $wpdb;

        $customPrefix = defined('PluginClassName_DB_PREFIX') ? PluginClassName_DB_PREFIX : 'fson_';
        $this->db = $wpdb;
        $this->table = $this->db->prefix . $customPrefix . $table;
        $this->column = $column;
    }

    public function isValid(mixed $input): bool
    {
        $query = $this->db->prepare(
            "SELECT 1 FROM {$this->table} WHERE {$this->column} = %s LIMIT 1",
            $input
        );

        $result = $this->db->get_var($query);

        return (bool) $result;
    }
}
