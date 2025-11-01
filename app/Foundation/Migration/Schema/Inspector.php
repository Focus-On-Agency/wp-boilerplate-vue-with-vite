<?php

namespace PluginClassName\Foundation\Migration\Schema;

if (!defined('ABSPATH')) {
    exit;
}

class Inspector
{
    public static function tableExists(string $table): bool
    {
        global $wpdb;
        $name = $wpdb->prefix . $table;
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $name));
        return $exists === $name;
    }

    public static function columnExists(string $table, string $column): bool
    {
        global $wpdb;
        $t = $wpdb->prefix . $table;
        $db = $wpdb->dbname;
        $sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
            $db, $t, $column
        );
        return (int) $wpdb->get_var($sql) > 0;
    }

    public static function indexExists(string $table, string $indexName): bool
    {
        global $wpdb;
        $t = $wpdb->prefix . $table;
        $sql = $wpdb->prepare("SHOW INDEX FROM `$t` WHERE Key_name = %s", $indexName);
        return (int) count($wpdb->get_results($sql)) > 0;
    }

    public static function addColumnIfMissing(string $table, string $column, string $sqlType): void
    {
        if (!self::columnExists($table, $column)) {
            Schema::table($table, function (Blueprint $t) use ($column, $sqlType) {
                $t->addColumn("`$column` $sqlType");
            });
        }
    }

    public static function addIndexIfMissing(string $table, array|string $columns, string $indexName): void
    {
        if (!self::indexExists($table, $indexName)) {
            Schema::table($table, function (Blueprint $t) use ($columns, $indexName) {
                $t->index($columns, $indexName);
            });
        }
    }
}