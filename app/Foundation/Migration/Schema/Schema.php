<?php

namespace PluginClassName\Foundation\Migration\Schema;

if (!defined('ABSPATH')) {
    exit;
}

class Schema
{
    public static function create(string $table, \Closure $callback): void
    {
        $bp = new Blueprint($table);
        $bp->create = true;
        $callback($bp);
        $bp->apply();
    }

    public static function table(string $table, \Closure $callback): void
    {
        $bp = new Blueprint($table);
        $callback($bp);
        $bp->apply();
    }

    public static function dropIfExists(string $table): void
    {
        global $wpdb;
        $customPrefix = defined('PluginClassName_DB_PREFIX')
            ? PluginClassName_DB_PREFIX
            : 'fson_';
        $full = $wpdb->prefix . $customPrefix . $table;
        
        $wpdb->query("DROP TABLE IF EXISTS `{$full}`");
    }
}